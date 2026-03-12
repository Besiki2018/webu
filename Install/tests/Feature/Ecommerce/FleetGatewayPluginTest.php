<?php

namespace Tests\Feature\Ecommerce;

use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderPayment;
use App\Models\EcommerceProduct;
use App\Models\Plugin;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Plugins\PaymentGateways\Fleet\FleetPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FleetGatewayPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_flitt_full_payment_session_is_created_with_signed_request(): void
    {
        $this->activateFleetGateway();

        Http::fake([
            'https://sandbox.pay.flitt.dev/api/checkout/url' => Http::response([
                'status' => 'success',
                'checkout_url' => 'https://sandbox.pay.flitt.dev/checkout/FLITT-TXN-1001',
                'payment_id' => '805243692',
            ], 200),
        ]);

        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);
        $order = $this->createCheckoutOrder($site);

        $response = $this->postJson(route('public.sites.ecommerce.orders.payment.start', [
            'site' => $site->id,
            'order' => $order->id,
        ]), [
            'provider' => 'fleet',
            'method' => 'card',
            'is_installment' => false,
        ]);
        $response->assertOk();

        $response
            ->assertJsonPath('payment.provider', 'fleet')
            ->assertJsonPath('payment_session.provider', 'fleet')
            ->assertJsonPath('payment_session.requires_redirect', true)
            ->assertJsonPath('payment_session.redirect_url', 'https://sandbox.pay.flitt.dev/checkout/FLITT-TXN-1001')
            ->assertJsonPath('payment.transaction_reference', '805243692');

        $this->assertDatabaseHas('ecommerce_order_payments', [
            'order_id' => $order->id,
            'provider' => 'fleet',
            'transaction_reference' => '805243692',
            'status' => 'pending',
        ]);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request) use ($order): bool {
            if ($request->url() !== 'https://sandbox.pay.flitt.dev/api/checkout/url') {
                return false;
            }

            $data = $request->data();
            $payload = is_array($data['request'] ?? null) ? $data['request'] : null;
            if (! $payload) {
                return false;
            }

            $signature = $data['signature'] ?? null;
            if (! is_string($signature)) {
                return false;
            }

            return ($payload['merchant_id'] ?? null) === 'flitt-merchant-id'
                && (string) ($payload['order_id'] ?? '') === (string) $order->id
                && (int) ($payload['amount'] ?? 0) === 12000
                && ($payload['payment_systems'] ?? null) === 'cards'
                && $signature === $this->generateFlittSignature($payload, 'flitt-merchant-secret');
        });
    }

    public function test_flitt_installment_payment_sends_installment_parameters_and_signature(): void
    {
        $this->activateFleetGateway();

        Http::fake([
            'https://sandbox.pay.flitt.dev/api/checkout/url' => Http::response([
                'status' => 'success',
                'checkout_url' => 'https://sandbox.pay.flitt.dev/checkout/FLITT-INSTALLMENT-1002',
                'payment_id' => '805243693',
            ], 200),
        ]);

        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);
        $order = $this->createCheckoutOrder($site);

        $response = $this->postJson(route('public.sites.ecommerce.orders.payment.start', [
            'site' => $site->id,
            'order' => $order->id,
        ]), [
            'provider' => 'fleet',
            'method' => 'installment',
            'is_installment' => true,
            'installment_plan_json' => [
                'payment_method' => 'bog_installment',
                'period' => 10,
            ],
        ]);
        $response
            ->assertOk()
            ->assertJsonPath('payment.is_installment', true)
            ->assertJsonPath('payment.method', 'bog_installment')
            ->assertJsonPath('payment_session.installment.enabled', true)
            ->assertJsonPath('payment_session.installment.plan.period', 10);

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if ($request->url() !== 'https://sandbox.pay.flitt.dev/api/checkout/url') {
                return false;
            }

            $data = $request->data();
            $payload = is_array($data['request'] ?? null) ? $data['request'] : null;
            if (! $payload) {
                return false;
            }

            $signature = $data['signature'] ?? null;
            if (! is_string($signature)) {
                return false;
            }

            return ($payload['payment_systems'] ?? null) === 'installments'
                && ($payload['payment_method'] ?? null) === 'bog_installment'
                && (int) ($payload['period'] ?? 0) === 10
                && $signature === $this->generateFlittSignature($payload, 'flitt-merchant-secret');
        });
    }

    public function test_flitt_webhook_rejects_invalid_signature(): void
    {
        $this->activateFleetGateway();

        $payload = [
            'status' => 'success',
            'description' => 'Order paid successfully',
            'order_status' => 'approved',
            'amount' => 12000,
            'order_id' => '1001',
            'payment_hash' => '7058666169203554e7',
            'order_hash' => 'f2d69cc2abbe56f838',
            'payment_id' => '805243692',
            'signature' => 'invalid-signature',
        ];

        $this->postJson('/payment-gateways/fleet/webhook', $payload)
            ->assertStatus(400);
    }

    public function test_flitt_webhook_with_valid_signature_syncs_order_status(): void
    {
        $this->activateFleetGateway();

        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

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
            'subtotal' => '120.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'discount_total' => '0.00',
            'grand_total' => '120.00',
            'paid_total' => '0.00',
            'outstanding_total' => '120.00',
            'placed_at' => now(),
            'meta_json' => ['source' => 'test'],
        ]);

        /** @var EcommerceOrderPayment $payment */
        $payment = EcommerceOrderPayment::query()->create([
            'site_id' => $site->id,
            'order_id' => $order->id,
            'provider' => 'fleet',
            'status' => 'pending',
            'method' => 'card',
            'transaction_reference' => '805243692',
            'amount' => '120.00',
            'currency' => 'GEL',
            'is_installment' => false,
            'installment_plan_json' => [],
            'raw_payload_json' => [],
        ]);

        $payload = [
            'status' => 'success',
            'description' => 'Order paid successfully',
            'order_status' => 'approved',
            'amount' => 12000,
            'order_id' => (string) $order->id,
            'payment_hash' => '7058666169203554e7',
            'order_hash' => 'f2d69cc2abbe56f838',
            'payment_id' => (string) $payment->transaction_reference,
        ];

        $payload['signature'] = $this->generateFlittSignature($payload, 'flitt-merchant-secret');

        $this->postJson('/payment-gateways/fleet/webhook', $payload)
            ->assertStatus(200);

        $order->refresh();
        $payment->refresh();

        $this->assertSame('paid', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('paid', $payment->status);
    }

    public function test_flitt_duplicate_webhook_event_is_ignored_without_double_charge(): void
    {
        $this->activateFleetGateway();

        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

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
            'subtotal' => '120.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'discount_total' => '0.00',
            'grand_total' => '120.00',
            'paid_total' => '0.00',
            'outstanding_total' => '120.00',
            'placed_at' => now(),
            'meta_json' => ['source' => 'test'],
        ]);

        /** @var EcommerceOrderPayment $payment */
        $payment = EcommerceOrderPayment::query()->create([
            'site_id' => $site->id,
            'order_id' => $order->id,
            'provider' => 'fleet',
            'status' => 'pending',
            'method' => 'card',
            'transaction_reference' => '805243694',
            'amount' => '120.00',
            'currency' => 'GEL',
            'is_installment' => false,
            'installment_plan_json' => [],
            'raw_payload_json' => [],
        ]);

        $payload = [
            'id' => 'flitt-evt-dup-1',
            'status' => 'success',
            'description' => 'Order paid successfully',
            'order_status' => 'approved',
            'amount' => 12000,
            'order_id' => (string) $order->id,
            'payment_hash' => '7058666169203554e7',
            'order_hash' => 'f2d69cc2abbe56f838',
            'payment_id' => (string) $payment->transaction_reference,
        ];

        $payload['signature'] = $this->generateFlittSignature($payload, 'flitt-merchant-secret');

        $this->postJson('/payment-gateways/fleet/webhook', $payload)
            ->assertStatus(200);

        $duplicateResponse = $this->postJson('/payment-gateways/fleet/webhook', $payload)
            ->assertStatus(200);

        $this->assertSame('Duplicate webhook ignored', $duplicateResponse->getContent());

        $order->refresh();
        $payment->refresh();

        $this->assertSame('paid', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('120.00', (string) $order->paid_total);
        $this->assertSame('0.00', (string) $order->outstanding_total);
        $this->assertSame('paid', $payment->status);
        $this->assertSame(1, $order->payments()->count(), 'Duplicate webhook must not create a second payment for this order');
    }

    private function activateFleetGateway(): void
    {
        Plugin::query()->create([
            'name' => 'Flitt',
            'slug' => 'fleet',
            'type' => 'payment_gateway',
            'class' => FleetPlugin::class,
            'version' => '1.1.0',
            'status' => 'active',
            'config' => [
                'sandbox' => true,
                'merchant_id' => 'flitt-merchant-id',
                'merchant_secret' => 'flitt-merchant-secret',
                'sandbox_base_url' => 'https://sandbox.pay.flitt.dev',
                'checkout_path' => '/api/checkout/url',
                'default_payment_systems' => 'cards',
            ],
            'metadata' => [
                'slug' => 'fleet',
            ],
            'migrations' => null,
            'installed_at' => now(),
        ]);
    }

    private function generateFlittSignature(array $payload, string $secret): string
    {
        unset($payload['signature'], $payload['response_signature_string']);

        $prepared = [];
        foreach ($payload as $key => $value) {
            $normalized = $this->normalizeSignatureValue($value);
            if ($normalized === null || $normalized === '') {
                continue;
            }

            $prepared[(string) $key] = $normalized;
        }

        ksort($prepared, SORT_STRING);

        $signatureParts = [$secret];
        foreach ($prepared as $value) {
            $signatureParts[] = $value;
        }

        return sha1(implode('|', $signatureParts));
    }

    private function normalizeSignatureValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_array($value)) {
            $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            return $encoded === false ? null : trim($encoded);
        }

        if (! is_scalar($value)) {
            return null;
        }

        return trim((string) $value);
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
        $modules = is_array($settings['modules'] ?? null) ? $settings['modules'] : [];
        $modules['ecommerce'] = $enableEcommerce;
        $settings['modules'] = $modules;
        $site->update(['theme_settings' => $settings]);

        return [$project, $site];
    }

    private function createCheckoutOrder(Site $site): EcommerceOrder
    {
        $product = EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Fleet Test Product',
            'slug' => 'fleet-test-product',
            'sku' => 'FLEET-TEST-1',
            'price' => '120.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 25,
            'allow_backorder' => false,
            'published_at' => now(),
        ]);

        $cartId = (string) $this->postJson(route('public.sites.ecommerce.carts.store', [
            'site' => $site->id,
        ]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
        ])->assertCreated()->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', [
            'site' => $site->id,
            'cart' => $cartId,
        ]), [
            'product_id' => $product->id,
            'quantity' => 1,
        ])->assertOk();

        $checkout = $this->postJson(route('public.sites.ecommerce.carts.checkout', [
            'site' => $site->id,
            'cart' => $cartId,
        ]), [
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
        ])->assertCreated();

        /** @var EcommerceOrder $order */
        $order = EcommerceOrder::query()->findOrFail((int) $checkout->json('order.id'));

        return $order;
    }
}
