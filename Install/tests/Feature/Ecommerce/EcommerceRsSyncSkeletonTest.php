<?php

namespace Tests\Feature\Ecommerce;

use App\Ecommerce\Contracts\EcommerceAccountingServiceContract;
use App\Ecommerce\Contracts\EcommerceRsConnectorContract;
use App\Ecommerce\Contracts\EcommerceRsReadinessServiceContract;
use App\Ecommerce\Contracts\EcommerceRsSyncServiceContract;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderItem;
use App\Models\EcommerceOrderPayment;
use App\Models\EcommerceRsExport;
use App\Models\EcommerceRsSync;
use App\Models\EcommerceRsSyncAttempt;
use App\Models\OperationLog;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class EcommerceRsSyncSkeletonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_queue_sync_is_idempotent_and_duplicate_submit_is_ignored(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createProjectWithSite($owner);
        $this->seedSellerContact($site);
        [, $export] = $this->createValidExport($site);

        $first = $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.rs.exports.sync', [
                'site' => $site->id,
                'export' => $export->id,
            ]))
            ->assertStatus(202)
            ->assertJsonPath('queued', true);

        $syncId = (int) $first->json('sync.id');

        $sync = EcommerceRsSync::query()->findOrFail($syncId);
        $this->assertSame(EcommerceRsSync::STATUS_SUCCEEDED, $sync->status);
        $this->assertSame(1, (int) $sync->attempts_count);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.rs.exports.sync', [
                'site' => $site->id,
                'export' => $export->id,
            ]))
            ->assertOk()
            ->assertJsonPath('queued', false);

        $this->assertSame(1, EcommerceRsSync::query()->where('site_id', $site->id)->where('export_id', $export->id)->count());
        $this->assertSame(1, EcommerceRsSyncAttempt::query()->where('site_id', $site->id)->where('sync_id', $syncId)->count());

        $this->assertDatabaseHas('operation_logs', [
            'project_id' => $site->project_id,
            'channel' => OperationLog::CHANNEL_SYSTEM,
            'event' => 'ecommerce_rs_sync_duplicate_ignored',
            'identifier' => (string) $syncId,
        ]);
    }

    public function test_retry_flow_records_attempts_and_succeeds_after_transient_failure(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createProjectWithSite($owner);
        $this->seedSellerContact($site);
        [, $export] = $this->createValidExport($site);

        $flakyConnector = new class implements EcommerceRsConnectorContract
        {
            public int $calls = 0;

            public function submitExport(EcommerceRsSync $sync, EcommerceRsExport $export): array
            {
                $this->calls++;

                if ($this->calls === 1) {
                    throw new RuntimeException('Temporary RS connector outage.');
                }

                return [
                    'status' => 'accepted',
                    'connector' => $sync->connector,
                    'remote_reference' => 'RS-TRANSIENT-OK',
                    'accepted_at' => now()->toISOString(),
                    'idempotency_key' => $sync->idempotency_key,
                    'schema_version' => $export->schema_version,
                ];
            }
        };
        $this->app->instance(EcommerceRsConnectorContract::class, $flakyConnector);

        $response = $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.rs.exports.sync', [
                'site' => $site->id,
                'export' => $export->id,
            ]))
            ->assertStatus(202)
            ->assertJsonPath('queued', true);

        $syncId = (int) $response->json('sync.id');

        $sync = EcommerceRsSync::query()->findOrFail($syncId);
        if ($sync->status === EcommerceRsSync::STATUS_QUEUED && $sync->next_retry_at) {
            $this->travelTo($sync->next_retry_at->copy()->addSecond());
            app(EcommerceRsSyncServiceContract::class)->processSyncById($syncId);
            $sync->refresh();
        }

        $this->assertSame(EcommerceRsSync::STATUS_SUCCEEDED, $sync->status);
        $this->assertSame(2, (int) $sync->attempts_count);
        $this->assertSame('RS-TRANSIENT-OK', (string) $sync->remote_reference);
        $this->assertSame(2, $flakyConnector->calls);

        $attemptStatuses = EcommerceRsSyncAttempt::query()
            ->where('site_id', $site->id)
            ->where('sync_id', $syncId)
            ->orderBy('attempt_no')
            ->pluck('status')
            ->all();

        $this->assertSame(
            [EcommerceRsSyncAttempt::STATUS_FAILED, EcommerceRsSyncAttempt::STATUS_SUCCEEDED],
            $attemptStatuses
        );

        $this->assertDatabaseHas('operation_logs', [
            'project_id' => $site->project_id,
            'channel' => OperationLog::CHANNEL_SYSTEM,
            'event' => 'ecommerce_rs_sync_retry_scheduled',
            'identifier' => (string) $syncId,
        ]);

        $this->assertDatabaseHas('operation_logs', [
            'project_id' => $site->project_id,
            'channel' => OperationLog::CHANNEL_SYSTEM,
            'event' => 'ecommerce_rs_sync_succeeded',
            'identifier' => (string) $syncId,
        ]);

        $this->travelBack();
    }

    public function test_retry_endpoint_is_tenant_safe_and_can_resume_failed_sync(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        [, $siteA] = $this->createProjectWithSite($owner);
        [, $siteB] = $this->createProjectWithSite($owner);
        $this->seedSellerContact($siteA);
        [, $exportA] = $this->createValidExport($siteA);

        /** @var EcommerceRsSync $failedSync */
        $failedSync = EcommerceRsSync::query()->create([
            'site_id' => $siteA->id,
            'export_id' => $exportA->id,
            'order_id' => $exportA->order_id,
            'connector' => 'rs-v2-skeleton',
            'idempotency_key' => sprintf('rs-sync:%s:%d:%s', $siteA->id, $exportA->id, 'rs-v2-skeleton'),
            'status' => EcommerceRsSync::STATUS_FAILED,
            'attempts_count' => 5,
            'max_attempts' => 5,
            'last_error' => 'Connector timeout',
            'meta_json' => ['seed' => 'failed_sync'],
        ]);

        $this->actingAs($intruder)
            ->postJson(route('panel.sites.ecommerce.rs.syncs.retry', [
                'site' => $siteA->id,
                'sync' => $failedSync->id,
            ]))
            ->assertForbidden();

        $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.rs.syncs.retry', [
                'site' => $siteB->id,
                'sync' => $failedSync->id,
            ]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.rs.syncs.retry', [
                'site' => $siteA->id,
                'sync' => $failedSync->id,
            ]))
            ->assertStatus(202)
            ->assertJsonPath('queued', true);

        $failedSync->refresh();
        $this->assertSame(EcommerceRsSync::STATUS_SUCCEEDED, $failedSync->status);
        $this->assertSame(6, (int) $failedSync->attempts_count);
        $this->assertSame(6, (int) $failedSync->max_attempts);

        $this->assertDatabaseHas('operation_logs', [
            'project_id' => $siteA->project_id,
            'channel' => OperationLog::CHANNEL_SYSTEM,
            'event' => 'ecommerce_rs_sync_retry_requested',
            'identifier' => (string) $failedSync->id,
        ]);
    }

    /**
     * @return array{0: Project, 1: Site}
     */
    private function createProjectWithSite(User $owner): array
    {
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }

    private function seedSellerContact(Site $site): void
    {
        $site->globalSettings()->updateOrCreate(
            ['site_id' => $site->id],
            [
                'contact_json' => [
                    'business_name' => 'Webu RS Merchant',
                    'tax_id' => '123456789',
                    'address' => 'Rustaveli Avenue 10',
                    'city' => 'Tbilisi',
                    'country_code' => 'GE',
                    'email' => 'merchant@example.com',
                    'phone' => '+995555000111',
                ],
                'social_links_json' => [],
                'analytics_ids_json' => [],
            ]
        );
    }

    /**
     * @return array{0: EcommerceOrder, 1: EcommerceRsExport}
     */
    private function createValidExport(Site $site): array
    {
        /** @var EcommerceOrder $order */
        $order = EcommerceOrder::query()->create([
            'site_id' => $site->id,
            'order_number' => 'RS-SYNC-'.strtoupper(Str::random(8)),
            'status' => 'paid',
            'payment_status' => 'paid',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '+995555111222',
            'customer_name' => 'Buyer',
            'subtotal' => '120.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'discount_total' => '0.00',
            'grand_total' => '120.00',
            'paid_total' => '120.00',
            'outstanding_total' => '0.00',
            'placed_at' => now(),
            'paid_at' => now(),
            'meta_json' => ['source' => 'test'],
        ]);

        EcommerceOrderItem::query()->create([
            'site_id' => $site->id,
            'order_id' => $order->id,
            'product_id' => null,
            'variant_id' => null,
            'name' => 'RS Sync Product',
            'sku' => 'RS-SYNC-ITEM-1',
            'quantity' => 1,
            'unit_price' => '120.00',
            'tax_amount' => '0.00',
            'discount_amount' => '0.00',
            'line_total' => '120.00',
            'options_json' => [],
            'meta_json' => [],
        ]);

        $payment = EcommerceOrderPayment::query()->create([
            'site_id' => $site->id,
            'order_id' => $order->id,
            'provider' => 'manual',
            'status' => 'paid',
            'method' => 'card',
            'transaction_reference' => 'RS-SYNC-PAY-'.strtoupper(Str::random(10)),
            'amount' => '120.00',
            'currency' => 'GEL',
            'is_installment' => false,
            'installment_plan_json' => [],
            'raw_payload_json' => [],
            'processed_at' => now(),
        ]);

        $accounting = app(EcommerceAccountingServiceContract::class);
        $accounting->recordOrderPlaced($site, $order);
        $accounting->recordPaymentSettled($site, $order, $payment, 120.00);

        $exportPayload = app(EcommerceRsReadinessServiceContract::class)->generateOrderExport(
            site: $site,
            order: $order,
            actor: $site->project->user
        );

        $exportId = (int) ($exportPayload['export']['id'] ?? 0);
        $export = EcommerceRsExport::query()->findOrFail($exportId);

        return [$order, $export];
    }
}
