<?php

namespace Tests\Feature\Ecommerce;

use App\Contracts\PaymentGatewayPlugin;
use App\Ecommerce\Contracts\EcommerceAccountingServiceContract;
use App\Ecommerce\Services\EcommerceAccountingService;
use App\Models\EcommerceAccountingEntry;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderPayment;
use App\Models\EcommerceProduct;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class EcommerceAccountingLedgerTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_checkout_creates_balanced_order_placed_accounting_entry(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Accounting Checkout Product',
            'slug' => 'accounting-checkout-product',
            'sku' => 'ACC-CHK-1',
            'price' => '80.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 20,
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
            'quantity' => 2,
        ])->assertOk();

        $checkout = $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer',
            'shipping_address_json' => [
                'country_code' => 'GE',
                'city' => 'Tbilisi',
                'address' => 'Rustaveli 10',
            ],
        ])->assertCreated();

        $orderId = (int) $checkout->json('order.id');

        $entry = EcommerceAccountingEntry::query()
            ->where('site_id', $site->id)
            ->where('order_id', $orderId)
            ->where('event_type', EcommerceAccountingService::EVENT_ORDER_PLACED)
            ->with('lines')
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame((string) $entry->total_debit, (string) $entry->total_credit);
        $this->assertGreaterThan(1, $entry->lines->count());

        $this->assertTrue($entry->lines->contains(fn ($line): bool => $line->account_code === 'asset.accounts_receivable' && $line->side === 'debit'));
        $this->assertTrue($entry->lines->contains(fn ($line): bool => $line->account_code === 'revenue.sales' && $line->side === 'credit'));
    }

    public function test_paid_and_refund_webhooks_create_balanced_entries_and_reconciliation_payload(): void
    {
        $this->mockGateway('paypal');
        [$owner, $site, $order, $payment] = $this->createOrderWithPayment();

        app(EcommerceAccountingServiceContract::class)->recordOrderPlaced(
            site: $site,
            order: $order,
            eventKey: sprintf('order:%d:placed', $order->id),
            meta: ['source' => 'test_seed']
        );

        $this->postJson('/payment-gateways/paypal/webhook', [
            'id' => 'evt-ledger-paid-1',
            'event_type' => 'payment.succeeded',
            'ecommerce' => [
                'transaction_reference' => $payment->transaction_reference,
                'status' => 'paid',
                'amount' => '160.00',
            ],
        ])->assertOk();

        $this->postJson('/payment-gateways/paypal/webhook', [
            'id' => 'evt-ledger-refund-1',
            'event_type' => 'payment.refunded',
            'ecommerce' => [
                'transaction_reference' => $payment->transaction_reference,
                'status' => 'refunded',
                'refund_amount' => '40.00',
            ],
        ])->assertOk();

        $settledEntry = EcommerceAccountingEntry::query()
            ->where('site_id', $site->id)
            ->where('order_id', $order->id)
            ->where('event_type', EcommerceAccountingService::EVENT_PAYMENT_SETTLED)
            ->first();

        $refundEntry = EcommerceAccountingEntry::query()
            ->where('site_id', $site->id)
            ->where('order_id', $order->id)
            ->where('event_type', EcommerceAccountingService::EVENT_REFUND)
            ->first();

        $this->assertNotNull($settledEntry);
        $this->assertNotNull($refundEntry);
        $this->assertSame((string) $settledEntry->total_debit, (string) $settledEntry->total_credit);
        $this->assertSame((string) $refundEntry->total_debit, (string) $refundEntry->total_credit);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.accounting.entries', ['site' => $site->id, 'order_id' => $order->id]))
            ->assertOk()
            ->assertJsonPath('summary.is_balanced', true)
            ->assertJsonPath('entries.0.order_id', $order->id);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.ecommerce.accounting.reconciliation', ['site' => $site->id, 'order_id' => $order->id]))
            ->assertOk()
            ->assertJsonPath('summary.is_balanced', true);
    }

    public function test_owner_can_record_return_adjustment_and_intruder_is_forbidden(): void
    {
        [$owner, $site, $order] = $this->createOrderWithPayment();
        $intruder = User::factory()->create();

        $this->actingAs($owner)
            ->postJson(route('panel.sites.ecommerce.orders.returns.store', ['site' => $site->id, 'order' => $order->id]), [
                'amount' => '25.50',
                'reason' => 'Damaged package return',
                'reference' => 'RET-0001',
            ])
            ->assertCreated();

        $entry = EcommerceAccountingEntry::query()
            ->where('site_id', $site->id)
            ->where('order_id', $order->id)
            ->where('event_type', EcommerceAccountingService::EVENT_RETURN_ADJUSTMENT)
            ->first();

        $this->assertNotNull($entry);
        $this->assertSame((string) $entry->total_debit, (string) $entry->total_credit);

        $this->actingAs($intruder)
            ->postJson(route('panel.sites.ecommerce.orders.returns.store', ['site' => $site->id, 'order' => $order->id]), [
                'amount' => '10.00',
            ])
            ->assertForbidden();
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
     * @return array{0: User, 1: Site, 2: EcommerceOrder, 3: EcommerceOrderPayment}
     */
    private function createOrderWithPayment(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        /** @var EcommerceOrder $order */
        $order = EcommerceOrder::query()->create([
            'site_id' => $site->id,
            'order_number' => 'ORD-'.strtoupper(Str::random(10)),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '+995555000111',
            'customer_name' => 'Buyer',
            'subtotal' => '160.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'discount_total' => '0.00',
            'grand_total' => '160.00',
            'paid_total' => '0.00',
            'outstanding_total' => '160.00',
            'placed_at' => now(),
            'meta_json' => [
                'source' => 'test',
            ],
        ]);

        /** @var EcommerceOrderPayment $payment */
        $payment = EcommerceOrderPayment::query()->create([
            'site_id' => $site->id,
            'order_id' => $order->id,
            'provider' => 'paypal',
            'status' => 'pending',
            'method' => 'card',
            'transaction_reference' => 'REF-'.strtoupper(Str::random(12)),
            'amount' => '160.00',
            'currency' => 'GEL',
            'is_installment' => false,
            'installment_plan_json' => [],
            'raw_payload_json' => [],
        ]);

        return [$owner, $site, $order, $payment];
    }

    /**
     * @return array{0: Project, 1: Site}
     */
    private function createPublishedProjectWithSite(User $owner, bool $enableEcommerce): array
    {
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        $settings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $moduleSettings = is_array($settings['modules'] ?? null) ? $settings['modules'] : [];
        $moduleSettings['ecommerce'] = $enableEcommerce;
        $settings['modules'] = $moduleSettings;
        $site->update(['theme_settings' => $settings]);

        return [$project, $site];
    }
}
