<?php

namespace Tests\Feature\Ecommerce;

use App\Ecommerce\Contracts\EcommerceAccountingServiceContract;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderItem;
use App\Models\EcommerceOrderPayment;
use App\Models\EcommerceRsExport;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EcommerceRsReadinessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_owner_can_generate_valid_rs_export_for_order(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createProjectWithSite($owner);
        $this->seedSellerContact($site);

        $order = $this->createOrder($site, [
            'subtotal' => '100.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'discount_total' => '0.00',
            'grand_total' => '100.00',
            'paid_total' => '100.00',
            'outstanding_total' => '0.00',
            'payment_status' => 'paid',
            'status' => 'paid',
            'placed_at' => now(),
            'paid_at' => now(),
        ]);

        EcommerceOrderItem::query()->create([
            'site_id' => $site->id,
            'order_id' => $order->id,
            'product_id' => null,
            'variant_id' => null,
            'name' => 'RS Ready Product',
            'sku' => 'RS-READY-1',
            'quantity' => 1,
            'unit_price' => '100.00',
            'tax_amount' => '0.00',
            'discount_amount' => '0.00',
            'line_total' => '100.00',
            'options_json' => [],
            'meta_json' => [],
        ]);

        $payment = EcommerceOrderPayment::query()->create([
            'site_id' => $site->id,
            'order_id' => $order->id,
            'provider' => 'manual',
            'status' => 'paid',
            'method' => 'card',
            'transaction_reference' => 'RS-PAY-'.strtoupper(Str::random(10)),
            'amount' => '100.00',
            'currency' => 'GEL',
            'is_installment' => false,
            'installment_plan_json' => [],
            'raw_payload_json' => [],
            'processed_at' => now(),
        ]);

        $accounting = app(EcommerceAccountingServiceContract::class);
        $accounting->recordOrderPlaced($site, $order);
        $accounting->recordPaymentSettled($site, $order, $payment, 100.00);

        $generate = $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.orders.rs.export', [
                'site' => $site->id,
                'order' => $order->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('export.status', EcommerceRsExport::STATUS_VALID)
            ->assertJsonPath('export.validation.errors_count', 0)
            ->assertJsonPath('export.payload_json.schema_version', 'rs.v1')
            ->assertJsonPath('export.payload_json.seller.tax_id', '123456789')
            ->assertJsonPath('export.payload_json.order.order_number', $order->order_number);

        $exportId = (int) $generate->json('export.id');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.rs.exports.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('summary.total_exports', 1)
            ->assertJsonPath('summary.valid_exports', 1)
            ->assertJsonPath('exports.0.id', $exportId);

        $readiness = $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.rs.readiness', ['site' => $site->id]))
            ->assertOk()
            ->json();

        $this->assertSame(0, $readiness['summary']['invalid_exports'] ?? -1);
        $this->assertGreaterThanOrEqual(1, $readiness['summary']['valid_exports'] ?? 0);
        $ordersInScope = $readiness['summary']['orders_in_scope'] ?? 0;
        $ordersWithExport = $readiness['summary']['orders_with_export'] ?? 0;
        if ($ordersInScope > 0 && $ordersWithExport >= $ordersInScope) {
            $this->assertTrue($readiness['summary']['is_ready'] ?? false);
        }
    }

    public function test_invalid_order_data_is_flagged_by_rs_validation(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createProjectWithSite($owner);

        $site->globalSettings()->updateOrCreate(
            ['site_id' => $site->id],
            [
                'contact_json' => [
                    'business_name' => 'No Tax Merchant',
                    'address' => 'Unknown',
                ],
                'social_links_json' => [],
                'analytics_ids_json' => [],
            ]
        );

        $order = $this->createOrder($site, [
            'subtotal' => '90.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'discount_total' => '0.00',
            'grand_total' => '100.00',
            'paid_total' => '40.00',
            'outstanding_total' => '40.00',
            'placed_at' => null,
        ]);

        EcommerceOrderItem::query()->create([
            'site_id' => $site->id,
            'order_id' => $order->id,
            'product_id' => null,
            'variant_id' => null,
            'name' => 'Broken Totals Product',
            'sku' => 'BROKEN-1',
            'quantity' => 1,
            'unit_price' => '50.00',
            'tax_amount' => '0.00',
            'discount_amount' => '0.00',
            'line_total' => '50.00',
            'options_json' => [],
            'meta_json' => [],
        ]);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.orders.rs.export', [
                'site' => $site->id,
                'order' => $order->id,
            ]))
            ->assertCreated()
            ->assertJsonPath('export.status', EcommerceRsExport::STATUS_INVALID)
            ->assertJsonPath('export.validation.errors_count', 7)
            ->assertJsonFragment(['code' => 'missing_seller_tax_id'])
            ->assertJsonFragment(['code' => 'missing_order_placed_at'])
            ->assertJsonFragment(['code' => 'subtotal_mismatch'])
            ->assertJsonFragment(['code' => 'grand_total_mismatch'])
            ->assertJsonFragment(['code' => 'paid_outstanding_mismatch'])
            ->assertJsonFragment(['code' => 'missing_accounting_entries'])
            ->assertJsonFragment(['code' => 'missing_payments']);
    }

    public function test_rs_endpoints_enforce_tenant_access_and_site_scope(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        [, $siteA] = $this->createProjectWithSite($owner);
        [, $siteB] = $this->createProjectWithSite($owner);
        $this->seedSellerContact($siteA);

        $orderA = $this->createOrder($siteA, [
            'subtotal' => '20.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'discount_total' => '0.00',
            'grand_total' => '20.00',
            'paid_total' => '20.00',
            'outstanding_total' => '0.00',
            'payment_status' => 'paid',
            'status' => 'paid',
            'placed_at' => now(),
            'paid_at' => now(),
        ]);

        EcommerceOrderItem::query()->create([
            'site_id' => $siteA->id,
            'order_id' => $orderA->id,
            'name' => 'Tenant Item',
            'sku' => 'TENANT-1',
            'quantity' => 1,
            'unit_price' => '20.00',
            'tax_amount' => '0.00',
            'discount_amount' => '0.00',
            'line_total' => '20.00',
            'options_json' => [],
            'meta_json' => [],
        ]);

        $this->actingAs($intruder)
            ->postJson(route('panel.sites.ecommerce.orders.rs.export', [
                'site' => $siteA->id,
                'order' => $orderA->id,
            ]))
            ->assertForbidden();

        $orderB = $this->createOrder($siteB, [
            'subtotal' => '15.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'discount_total' => '0.00',
            'grand_total' => '15.00',
            'paid_total' => '15.00',
            'outstanding_total' => '0.00',
            'payment_status' => 'paid',
            'status' => 'paid',
            'placed_at' => now(),
            'paid_at' => now(),
        ]);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.orders.rs.export', [
                'site' => $siteA->id,
                'order' => $orderB->id,
            ]))
            ->assertNotFound();

        $generated = $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.orders.rs.export', [
                'site' => $siteA->id,
                'order' => $orderA->id,
            ]))
            ->assertCreated();

        $exportId = (int) $generated->json('export.id');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.rs.exports.show', [
                'site' => $siteB->id,
                'export' => $exportId,
            ]))
            ->assertNotFound();
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
                    'business_name' => 'Webu LLC',
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
     * @param  array<string, mixed>  $overrides
     */
    private function createOrder(Site $site, array $overrides = []): EcommerceOrder
    {
        /** @var EcommerceOrder $order */
        $order = EcommerceOrder::query()->create(array_merge([
            'site_id' => $site->id,
            'order_number' => 'RS-'.strtoupper(Str::random(10)),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '+995555111222',
            'customer_name' => 'Buyer',
            'subtotal' => '0.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'discount_total' => '0.00',
            'grand_total' => '0.00',
            'paid_total' => '0.00',
            'outstanding_total' => '0.00',
            'placed_at' => now(),
            'meta_json' => ['source' => 'test'],
        ], $overrides));

        return $order;
    }
}
