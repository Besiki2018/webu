<?php

namespace Tests\Feature\Ecommerce;

use App\Contracts\PaymentGatewayPlugin;
use App\Ecommerce\Contracts\EcommerceRsSyncServiceContract;
use App\Ecommerce\Services\EcommerceAccountingService;
use App\Models\EcommerceAccountingEntry;
use App\Models\EcommerceInventoryItem;
use App\Models\EcommerceOrder;
use App\Models\EcommerceProduct;
use App\Models\EcommerceRsSync;
use App\Models\EcommerceStockMovement;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class EcommerceAdvancedAcceptanceTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_advanced_flow_covers_inventory_accounting_and_rs_sync_lifecycle(): void
    {
        $this->mockGateway('paypal');
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);
        $this->seedSellerContact($site, withTaxId: true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Advanced Flow Product',
            'slug' => 'advanced-flow-product',
            'sku' => 'ADV-FLOW-1',
            'price' => '100.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 4,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $cartAId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer-a@example.com',
            'customer_name' => 'Buyer A',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartAId]), [
            'product_id' => $product->id,
            'quantity' => 3,
        ])->assertOk();

        $cartBId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer-b@example.com',
            'customer_name' => 'Buyer B',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartBId]), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Requested quantity exceeds available stock.');

        $checkout = $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartAId]), [
            'customer_email' => 'buyer-a@example.com',
            'customer_name' => 'Buyer A',
            'shipping_address_json' => [
                'country_code' => 'GE',
                'city' => 'Tbilisi',
                'address' => 'Rustaveli 10',
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('order.grand_total', '300.00');

        $orderId = (int) $checkout->json('order.id');

        $product->refresh();
        $this->assertSame(1, (int) $product->stock_quantity);

        $inventoryItem = EcommerceInventoryItem::query()
            ->where('site_id', $site->id)
            ->where('product_id', $product->id)
            ->firstOrFail();
        $this->assertSame(1, (int) $inventoryItem->quantity_on_hand);
        $this->assertSame(0, (int) $inventoryItem->quantity_reserved);

        $this->assertDatabaseHas('ecommerce_stock_movements', [
            'site_id' => $site->id,
            'product_id' => $product->id,
            'movement_type' => EcommerceStockMovement::TYPE_RESERVE,
        ]);
        $this->assertDatabaseHas('ecommerce_stock_movements', [
            'site_id' => $site->id,
            'product_id' => $product->id,
            'order_id' => $orderId,
            'movement_type' => EcommerceStockMovement::TYPE_COMMIT,
            'quantity_delta' => -3,
            'reserved_delta' => -3,
        ]);

        $startPayment = $this->postJson(route('public.sites.ecommerce.orders.payment.start', [
            'site' => $site->id,
            'order' => $orderId,
        ]), [
            'provider' => 'paypal',
            'method' => 'card',
            'is_installment' => false,
        ])->assertOk();

        $transactionReference = (string) $startPayment->json('payment.transaction_reference');
        $this->assertNotSame('', $transactionReference);

        $this->postJson('/payment-gateways/paypal/webhook', [
            'id' => 'evt-advanced-paid-1',
            'event_type' => 'payment.succeeded',
            'ecommerce' => [
                'transaction_reference' => $transactionReference,
                'status' => 'paid',
                'amount' => '300.00',
            ],
        ])->assertOk();

        $generatedExport = $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.orders.rs.export', [
                'site' => $site->id,
                'order' => $orderId,
            ]))
            ->assertCreated()
            ->assertJsonPath('export.status', 'valid');

        $exportId = (int) $generatedExport->json('export.id');

        $queueSync = $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.rs.exports.sync', [
                'site' => $site->id,
                'export' => $exportId,
            ]))
            ->assertStatus(202)
            ->assertJsonPath('queued', true);

        $syncId = (int) $queueSync->json('sync.id');
        $sync = EcommerceRsSync::query()->findOrFail($syncId);
        if ($sync->status !== EcommerceRsSync::STATUS_SUCCEEDED) {
            if ($sync->next_retry_at) {
                $this->travelTo($sync->next_retry_at->copy()->addSecond());
            }

            app(EcommerceRsSyncServiceContract::class)->processSyncById($syncId);
            $sync->refresh();
            $this->travelBack();
        }

        $this->assertSame(EcommerceRsSync::STATUS_SUCCEEDED, $sync->status);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.rs.syncs.show', [
                'site' => $site->id,
                'sync' => $syncId,
            ]))
            ->assertOk()
            ->assertJsonPath('sync.status', EcommerceRsSync::STATUS_SUCCEEDED);

        $this->postJson('/payment-gateways/paypal/webhook', [
            'id' => 'evt-advanced-refund-1',
            'event_type' => 'payment.refunded',
            'ecommerce' => [
                'transaction_reference' => $transactionReference,
                'status' => 'refunded',
                'refund_amount' => '50.00',
            ],
        ])->assertOk();

        $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.orders.returns.store', [
                'site' => $site->id,
                'order' => $orderId,
            ]), [
                'amount' => '10.00',
                'reason' => 'Damaged unit',
                'reference' => 'RET-ADV-1',
            ])
            ->assertCreated();

        $order = EcommerceOrder::query()->findOrFail($orderId);
        $this->assertSame('paid', $order->status);
        $this->assertSame('partially_refunded', $order->payment_status);
        $this->assertSame('250.00', (string) $order->paid_total);
        $this->assertSame('0.00', (string) $order->outstanding_total);

        $this->assertSame(1, EcommerceAccountingEntry::query()
            ->where('site_id', $site->id)
            ->where('order_id', $orderId)
            ->where('event_type', EcommerceAccountingService::EVENT_ORDER_PLACED)
            ->count());
        $this->assertSame(1, EcommerceAccountingEntry::query()
            ->where('site_id', $site->id)
            ->where('order_id', $orderId)
            ->where('event_type', EcommerceAccountingService::EVENT_PAYMENT_SETTLED)
            ->count());
        $this->assertSame(1, EcommerceAccountingEntry::query()
            ->where('site_id', $site->id)
            ->where('order_id', $orderId)
            ->where('event_type', EcommerceAccountingService::EVENT_REFUND)
            ->count());
        $this->assertSame(1, EcommerceAccountingEntry::query()
            ->where('site_id', $site->id)
            ->where('order_id', $orderId)
            ->where('event_type', EcommerceAccountingService::EVENT_RETURN_ADJUSTMENT)
            ->count());

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.accounting.reconciliation', [
                'site' => $site->id,
                'order_id' => $orderId,
            ]))
            ->assertOk()
            ->assertJsonPath('summary.is_balanced', true);
    }

    public function test_invalid_rs_export_cannot_be_synced_in_advanced_suite(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);
        $this->seedSellerContact($site, withTaxId: false);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Invalid RS Product',
            'slug' => 'invalid-rs-product',
            'sku' => 'INVALID-RS-1',
            'price' => '50.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 5,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $cartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $checkout = $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer',
        ])->assertCreated();

        $orderId = (int) $checkout->json('order.id');

        $generatedExport = $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.orders.rs.export', [
                'site' => $site->id,
                'order' => $orderId,
            ]))
            ->assertCreated()
            ->assertJsonPath('export.status', 'invalid');

        $exportId = (int) $generatedExport->json('export.id');

        $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.rs.exports.sync', [
                'site' => $site->id,
                'export' => $exportId,
            ]))
            ->assertStatus(422)
            ->assertJsonPath('error', 'Only valid RS exports can be synced.');

        $this->assertSame(0, EcommerceRsSync::query()
            ->where('site_id', $site->id)
            ->where('export_id', $exportId)
            ->count());
    }

    private function mockGateway(string $pluginSlug, int $httpStatus = 200): void
    {
        $gateway = Mockery::mock(PaymentGatewayPlugin::class);
        $gateway->shouldReceive('handleWebhook')
            ->andReturn(response('Webhook handled', $httpStatus));

        $pluginManager = Mockery::mock(PluginManager::class);
        $pluginManager->shouldReceive('getGatewayBySlug')
            ->with($pluginSlug)
            ->andReturn($gateway);

        $this->app->instance(PluginManager::class, $pluginManager);
    }

    /**
     * @return array{0: Project, 1: \App\Models\Site}
     */
    private function createPublishedProjectWithSite(User $owner, bool $enableEcommerce): array
    {
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        $site = $project->site()->firstOrFail();

        $settings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $moduleSettings = is_array($settings['modules'] ?? null) ? $settings['modules'] : [];
        $moduleSettings['ecommerce'] = $enableEcommerce;
        $settings['modules'] = $moduleSettings;
        $site->update(['theme_settings' => $settings]);

        return [$project, $site];
    }

    private function seedSellerContact(\App\Models\Site $site, bool $withTaxId): void
    {
        $contact = [
            'business_name' => 'Webu Advanced Merchant',
            'address' => 'Rustaveli 10',
            'city' => 'Tbilisi',
            'country_code' => 'GE',
            'email' => 'merchant@example.com',
            'phone' => '+995555000111',
        ];

        if ($withTaxId) {
            $contact['tax_id'] = '123456789';
        }

        $site->globalSettings()->updateOrCreate(
            ['site_id' => $site->id],
            [
                'contact_json' => $contact,
                'social_links_json' => [],
                'analytics_ids_json' => [],
            ]
        );
    }
}
