<?php

namespace Tests\Feature\Ecommerce;

use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderPayment;
use App\Models\EcommerceProduct;
use App\Models\Plugin;
use App\Models\Project;
use App\Models\Site;
use App\Models\SitePaymentGatewaySetting;
use App\Models\SystemSetting;
use App\Models\User;
use App\Plugins\PaymentGateways\BankOfGeorgia\BankOfGeorgiaPlugin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class BankOfGeorgiaGatewayPluginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_bank_of_georgia_full_payment_session_is_created_from_official_payment_manager_endpoint(): void
    {
        $this->activateBogGateway();

        Http::fake([
            'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token' => Http::response([
                'access_token' => 'bog-payment-token',
                'expires_in' => 3600,
            ], 200),
            'https://api.bog.ge/payments/v1/ecommerce/orders' => Http::response([
                'id' => 'bog-order-1001',
                '_links' => [
                    'details' => [
                        'href' => 'https://api.bog.ge/payments/v1/receipt/bog-order-1001',
                    ],
                    'redirect' => [
                        'href' => 'https://payment.bog.ge/?order_id=bog-order-1001',
                    ],
                ],
            ], 200),
        ]);

        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);
        $order = $this->createCheckoutOrder($site);

        $response = $this->postJson(route('public.sites.ecommerce.orders.payment.start', [
            'site' => $site->id,
            'order' => $order->id,
        ]), [
            'provider' => 'bank-of-georgia',
            'method' => 'card',
            'is_installment' => false,
        ])->assertOk();

        $response
            ->assertJsonPath('payment.provider', 'bank-of-georgia')
            ->assertJsonPath('payment.transaction_reference', 'bog-order-1001')
            ->assertJsonPath('payment_session.provider', 'bank-of-georgia')
            ->assertJsonPath('payment_session.requires_redirect', true)
            ->assertJsonPath('payment_session.redirect_url', 'https://payment.bog.ge/?order_id=bog-order-1001')
            ->assertJsonPath('payment_session.details_url', 'https://api.bog.ge/payments/v1/receipt/bog-order-1001');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if ($request->url() !== 'https://api.bog.ge/payments/v1/ecommerce/orders') {
                return false;
            }

            $payload = $request->data();

            return isset($payload['callback_url'])
                && isset($payload['purchase_units']['basket'])
                && isset($payload['redirect_urls']['success'])
                && isset($payload['redirect_urls']['fail']);
        });

        $this->assertDatabaseHas('ecommerce_order_payments', [
            'order_id' => $order->id,
            'provider' => 'bank-of-georgia',
            'transaction_reference' => 'bog-order-1001',
            'status' => 'pending',
        ]);
    }

    public function test_bank_of_georgia_installment_payment_hits_official_checkout_endpoint(): void
    {
        $this->activateBogGateway();

        Http::fake([
            'https://installment-test.bog.ge/v1/oauth2/token' => Http::response([
                'access_token' => 'bog-installment-token',
                'expires_in' => 3600,
            ], 200),
            'https://installment-test.bog.ge/v1/installment/checkout' => Http::response([
                'status' => 'CREATED',
                'order_id' => 'bog-installment-2001',
                'links' => [
                    [
                        'rel' => 'self',
                        'href' => 'https://installment-test.bog.ge/v1/installment/checkout/bog-installment-2001',
                    ],
                    [
                        'rel' => 'target',
                        'href' => 'https://installment-test.bog.ge/installment/checkout/bog-installment-2001',
                    ],
                ],
            ], 200),
        ]);

        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);
        $order = $this->createCheckoutOrder($site);

        $this->postJson(route('public.sites.ecommerce.orders.payment.start', [
            'site' => $site->id,
            'order' => $order->id,
        ]), [
            'provider' => 'bank-of-georgia',
            'method' => 'installment',
            'is_installment' => true,
            'installment_plan_json' => [
                'month' => 12,
                'type' => 'STANDARD',
            ],
        ])
            ->assertOk()
            ->assertJsonPath('payment.transaction_reference', 'bog-installment-2001')
            ->assertJsonPath('payment.is_installment', true)
            ->assertJsonPath('payment_session.redirect_url', 'https://installment-test.bog.ge/installment/checkout/bog-installment-2001')
            ->assertJsonPath('payment_session.installment.plan.month', 12)
            ->assertJsonPath('payment_session.installment.plan.type', 'STANDARD');

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if ($request->url() !== 'https://installment-test.bog.ge/v1/installment/checkout') {
                return false;
            }

            $payload = $request->data();

            return ($payload['intent'] ?? null) === 'LOAN'
                && (int) ($payload['installment_month'] ?? 0) === 12
                && (string) ($payload['installment_type'] ?? '') === 'STANDARD'
                && ($payload['validate_items'] ?? null) === true;
        });
    }

    public function test_bank_of_georgia_uses_site_level_credentials_when_override_exists(): void
    {
        $this->activateBogGateway();

        Http::fake([
            'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token' => Http::response([
                'access_token' => 'bog-payment-token-site',
                'expires_in' => 3600,
            ], 200),
            'https://api.bog.ge/payments/v1/ecommerce/orders' => Http::response([
                'id' => 'bog-order-site-override',
                '_links' => [
                    'redirect' => [
                        'href' => 'https://payment.bog.ge/?order_id=bog-order-site-override',
                    ],
                ],
            ], 200),
        ]);

        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        SitePaymentGatewaySetting::query()->create([
            'site_id' => $site->id,
            'provider_slug' => 'bank-of-georgia',
            'availability' => SitePaymentGatewaySetting::AVAILABILITY_ENABLED,
            'config' => [
                'client_id' => 'site-client-id',
                'client_secret' => 'site-client-secret',
                'merchant_id' => 'site-merchant-id',
            ],
        ]);

        $order = $this->createCheckoutOrder($site);

        $this->postJson(route('public.sites.ecommerce.orders.payment.start', [
            'site' => $site->id,
            'order' => $order->id,
        ]), [
            'provider' => 'bank-of-georgia',
            'method' => 'card',
            'is_installment' => false,
        ])->assertOk();

        Http::assertSent(function (\Illuminate\Http\Client\Request $request): bool {
            if ($request->url() !== 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token') {
                return false;
            }

            $authorization = $request->header('Authorization')[0] ?? null;

            return $authorization === 'Basic '.base64_encode('site-client-id:site-client-secret');
        });
    }

    public function test_bank_of_georgia_webhook_rejects_invalid_callback_signature(): void
    {
        $this->activateBogGateway();

        $payload = [
            'event' => 'order_payment',
            'body' => [
                'order_id' => 'bog-invalid-order',
                'order_status' => [
                    'key' => 'completed',
                ],
            ],
        ];

        $this->withHeaders([
            'Callback-Signature' => 'invalid-signature',
        ])->postJson('/payment-gateways/bank-of-georgia/webhook', $payload)
            ->assertStatus(400);
    }

    public function test_bank_of_georgia_signed_payment_callback_syncs_order_status(): void
    {
        [$privatePem, $publicPem] = $this->generateRsaKeyPair();
        $this->activateBogGateway($publicPem);

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
            'provider' => 'bank-of-georgia',
            'status' => 'pending',
            'method' => 'card',
            'transaction_reference' => 'bog-order-verify-1',
            'amount' => '120.00',
            'currency' => 'GEL',
            'is_installment' => false,
            'installment_plan_json' => [],
            'raw_payload_json' => [],
        ]);

        $payload = [
            'event' => 'order_payment',
            'body' => [
                'order_id' => $payment->transaction_reference,
                'order_status' => [
                    'key' => 'completed',
                ],
                'purchase_units' => [
                    'transfer_amount' => '120.00',
                    'refund_amount' => '0.00',
                ],
            ],
        ];

        $content = json_encode($payload, JSON_THROW_ON_ERROR);
        openssl_sign($content, $signatureBinary, $privatePem, OPENSSL_ALGO_SHA256);

        $this->call(
            'POST',
            '/payment-gateways/bank-of-georgia/webhook',
            [],
            [],
            [],
            [
                'HTTP_CALLBACK_SIGNATURE' => base64_encode($signatureBinary),
                'CONTENT_TYPE' => 'application/json',
            ],
            $content
        )->assertStatus(200);

        $order->refresh();
        $payment->refresh();

        $this->assertSame('paid', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('paid', $payment->status);
    }

    public function test_bank_of_georgia_duplicate_webhook_event_is_ignored_without_double_charge(): void
    {
        [$privatePem, $publicPem] = $this->generateRsaKeyPair();
        $this->activateBogGateway($publicPem);

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
            'provider' => 'bank-of-georgia',
            'status' => 'pending',
            'method' => 'card',
            'transaction_reference' => 'bog-order-duplicate-1',
            'amount' => '120.00',
            'currency' => 'GEL',
            'is_installment' => false,
            'installment_plan_json' => [],
            'raw_payload_json' => [],
        ]);

        $payload = [
            'id' => 'bog-evt-dup-1',
            'event' => 'order_payment',
            'body' => [
                'order_id' => $payment->transaction_reference,
                'order_status' => [
                    'key' => 'completed',
                ],
                'purchase_units' => [
                    'transfer_amount' => '120.00',
                    'refund_amount' => '0.00',
                ],
            ],
        ];

        $content = json_encode($payload, JSON_THROW_ON_ERROR);
        openssl_sign($content, $signatureBinary, $privatePem, OPENSSL_ALGO_SHA256);
        $server = [
            'HTTP_CALLBACK_SIGNATURE' => base64_encode($signatureBinary),
            'CONTENT_TYPE' => 'application/json',
        ];

        $this->call(
            'POST',
            '/payment-gateways/bank-of-georgia/webhook',
            [],
            [],
            [],
            $server,
            $content
        )->assertStatus(200);

        $duplicateResponse = $this->call(
            'POST',
            '/payment-gateways/bank-of-georgia/webhook',
            [],
            [],
            [],
            $server,
            $content
        )->assertStatus(200);

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

    private function activateBogGateway(?string $callbackPublicKey = null): void
    {
        Plugin::query()->create([
            'name' => 'Bank of Georgia',
            'slug' => 'bank-of-georgia',
            'type' => 'payment_gateway',
            'class' => BankOfGeorgiaPlugin::class,
            'version' => '1.1.0',
            'status' => 'active',
            'config' => [
                'sandbox' => true,
                'client_id' => 'bog-client-id',
                'client_secret' => 'bog-client-secret',
                'merchant_id' => 'bog-merchant-id',
                'payment_token_url' => 'https://oauth2.bog.ge/auth/realms/bog/protocol/openid-connect/token',
                'sandbox_payment_base_url' => 'https://api.bog.ge',
                'payment_order_path' => '/payments/v1/ecommerce/orders',
                'sandbox_installment_base_url' => 'https://installment-test.bog.ge',
                'installment_token_path' => '/v1/oauth2/token',
                'installment_order_path' => '/v1/installment/checkout',
                'installment_client_id' => 'bog-client-id',
                'installment_secret_key' => 'bog-client-secret',
                'callback_public_key' => $callbackPublicKey,
            ],
            'metadata' => [
                'slug' => 'bank-of-georgia',
            ],
            'migrations' => null,
            'installed_at' => now(),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function generateRsaKeyPair(): array
    {
        $privateKey = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        if ($privateKey === false) {
            throw new \RuntimeException('Failed to create RSA private key for test callback signing.');
        }

        openssl_pkey_export($privateKey, $privatePem);
        $details = openssl_pkey_get_details($privateKey);

        if (! is_array($details) || ! is_string($details['key'] ?? null)) {
            throw new \RuntimeException('Failed to extract RSA public key for test callback signing.');
        }

        return [$privatePem, $details['key']];
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
            'name' => 'BoG Test Product',
            'slug' => 'bog-test-product',
            'sku' => 'BOG-TEST-1',
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
