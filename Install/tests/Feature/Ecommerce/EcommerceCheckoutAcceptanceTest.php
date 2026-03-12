<?php

namespace Tests\Feature\Ecommerce;

use App\Contracts\PaymentGatewayPlugin;
use App\Models\EcommerceOrder;
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

class EcommerceCheckoutAcceptanceTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_checkout_happy_path_and_payment_success_flow(): void
    {
        $this->mockGateway('paypal');
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Dental Clinic Service Pack',
            'slug' => 'dental-clinic-service-pack',
            'sku' => 'DENTAL-1',
            'price' => '120.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 10,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $cartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $checkoutResponse = $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
        ])
            ->assertCreated()
            ->assertJsonPath('order.grand_total', '120.00');

        $orderId = (int) $checkoutResponse->json('order.id');
        $this->assertGreaterThan(0, $orderId);

        $startResponse = $this->postJson(route('public.sites.ecommerce.orders.payment.start', [
            'site' => $site->id,
            'order' => $orderId,
        ]), [
            'provider' => 'paypal',
            'method' => 'card',
            'is_installment' => false,
        ])->assertOk();

        $transactionReference = (string) $startResponse->json('payment.transaction_reference');
        $this->assertNotSame('', $transactionReference);

        $this->postJson('/payment-gateways/paypal/webhook', [
            'id' => 'evt-acceptance-paid-1',
            'event_type' => 'payment.succeeded',
            'ecommerce' => [
                'transaction_reference' => $transactionReference,
                'status' => 'paid',
                'amount' => '120.00',
            ],
        ])->assertOk();

        $order = EcommerceOrder::query()->findOrFail($orderId);
        $this->assertSame('paid', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('120.00', (string) $order->paid_total);
        $this->assertSame('0.00', (string) $order->outstanding_total);
    }

    public function test_checkout_fails_for_empty_cart(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $cartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => $cartId]), [
            'customer_email' => 'buyer@example.com',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Cart is empty.');
    }

    public function test_add_to_cart_fails_when_requested_quantity_exceeds_stock(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Low Stock Product',
            'slug' => 'low-stock-product',
            'sku' => 'LOW-1',
            'price' => '40.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 1,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $cartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => $cartId]), [
            'product_id' => $product->id,
            'quantity' => 2,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'Requested quantity exceeds available stock.');
    }

    public function test_payment_start_fails_when_order_has_no_outstanding_balance(): void
    {
        $this->mockGateway('paypal');
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $order = EcommerceOrder::query()->create([
            'site_id' => $site->id,
            'order_number' => 'ORD-'.strtoupper(Str::random(10)),
            'status' => 'paid',
            'payment_status' => 'paid',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer',
            'subtotal' => '80.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'discount_total' => '0.00',
            'grand_total' => '80.00',
            'paid_total' => '80.00',
            'outstanding_total' => '0.00',
            'placed_at' => now(),
            'paid_at' => now(),
            'meta_json' => [],
        ]);

        $this->postJson(route('public.sites.ecommerce.orders.payment.start', [
            'site' => $site->id,
            'order' => $order->id,
        ]), [
            'provider' => 'paypal',
            'method' => 'card',
        ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'This order has no outstanding balance.');
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

