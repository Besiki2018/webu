<?php

namespace Tests\Feature\Ecommerce;

use App\Contracts\PaymentGatewayPlugin;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderPayment;
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

class EcommercePaymentWebhookOrchestrationTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_webhook_paid_status_synchronizes_payment_and_order(): void
    {
        $this->mockGateway('paypal');
        [, , $order, $payment] = $this->createOrderWithPayment();

        $this->postJson('/payment-gateways/paypal/webhook', [
            'id' => 'evt-paid-1',
            'event_type' => 'payment.succeeded',
            'ecommerce' => [
                'transaction_reference' => $payment->transaction_reference,
                'status' => 'paid',
                'amount' => '160.00',
            ],
        ])->assertOk();

        $payment->refresh();
        $order->refresh();

        $this->assertSame('paid', $payment->status);
        $this->assertNotNull($payment->processed_at);

        $this->assertSame('paid', $order->status);
        $this->assertSame('paid', $order->payment_status);
        $this->assertSame('160.00', (string) $order->paid_total);
        $this->assertSame('0.00', (string) $order->outstanding_total);
        $this->assertNotNull($order->paid_at);
    }

    public function test_webhook_failed_status_marks_order_failed_when_unpaid(): void
    {
        $this->mockGateway('paypal');
        [, , $order, $payment] = $this->createOrderWithPayment();

        $this->postJson('/payment-gateways/paypal/webhook', [
            'id' => 'evt-failed-1',
            'event_type' => 'payment.failed',
            'ecommerce' => [
                'payment_id' => $payment->id,
                'status' => 'failed',
            ],
        ])->assertOk();

        $payment->refresh();
        $order->refresh();

        $this->assertSame('failed', $payment->status);
        $this->assertSame('failed', $order->status);
        $this->assertSame('failed', $order->payment_status);
        $this->assertSame('0.00', (string) $order->paid_total);
        $this->assertSame('160.00', (string) $order->outstanding_total);
    }

    public function test_webhook_refund_status_synchronizes_refund_lifecycle(): void
    {
        $this->mockGateway('paypal');
        [, , $order, $payment] = $this->createOrderWithPayment(
            orderOverrides: [
                'status' => 'paid',
                'payment_status' => 'paid',
                'paid_total' => '160.00',
                'outstanding_total' => '0.00',
                'paid_at' => now(),
            ],
            paymentOverrides: [
                'status' => 'paid',
            ],
        );

        $this->postJson('/payment-gateways/paypal/webhook', [
            'id' => 'evt-refund-1',
            'event_type' => 'payment.refunded',
            'ecommerce' => [
                'transaction_reference' => $payment->transaction_reference,
                'status' => 'refunded',
                'refund_amount' => '160.00',
            ],
        ])->assertOk();

        $payment->refresh();
        $order->refresh();

        $this->assertSame('refunded', $payment->status);
        $this->assertSame('refunded', $order->status);
        $this->assertSame('refunded', $order->payment_status);
        $this->assertSame('0.00', (string) $order->paid_total);
        $this->assertSame('0.00', (string) $order->outstanding_total);
    }

    public function test_partial_refund_status_keeps_order_paid_and_marks_partial_refund(): void
    {
        $this->mockGateway('paypal');
        [, , $order, $payment] = $this->createOrderWithPayment(
            orderOverrides: [
                'status' => 'paid',
                'payment_status' => 'paid',
                'paid_total' => '160.00',
                'outstanding_total' => '0.00',
                'paid_at' => now(),
            ],
            paymentOverrides: [
                'status' => 'paid',
            ],
        );

        $this->postJson('/payment-gateways/paypal/webhook', [
            'id' => 'evt-refund-partial-1',
            'event_type' => 'payment.refunded',
            'ecommerce' => [
                'transaction_reference' => $payment->transaction_reference,
                'status' => 'refunded',
                'refund_amount' => '40.00',
            ],
        ])->assertOk();

        $payment->refresh();
        $order->refresh();

        $this->assertSame('partially_refunded', $payment->status);
        $this->assertSame('paid', $order->status);
        $this->assertSame('partially_refunded', $order->payment_status);
        $this->assertSame('120.00', (string) $order->paid_total);
        $this->assertSame('0.00', (string) $order->outstanding_total);
    }

    /** Part 6: Webhook when gateway throws (e.g. network failure) – order stays safe. */
    public function test_webhook_when_gateway_throws_returns_500_and_order_unchanged(): void
    {
        $gateway = Mockery::mock(PaymentGatewayPlugin::class);
        $gateway->shouldReceive('handleWebhook')
            ->andThrow(new \RuntimeException('Network failure'));

        $pluginManager = Mockery::mock(PluginManager::class);
        $pluginManager->shouldReceive('getGatewayBySlug')
            ->with('paypal')
            ->andReturn($gateway);

        $this->app->instance(PluginManager::class, $pluginManager);

        [, , $order, $payment] = $this->createOrderWithPayment();

        $this->postJson('/payment-gateways/paypal/webhook', [
            'id' => 'evt-network-fail-1',
            'event_type' => 'payment.succeeded',
            'ecommerce' => [
                'transaction_reference' => $payment->transaction_reference,
                'status' => 'paid',
                'amount' => '160.00',
            ],
        ])->assertStatus(500);

        $order->refresh();
        $payment->refresh();

        $this->assertSame('pending', $order->status);
        $this->assertSame('unpaid', $order->payment_status);
        $this->assertSame('0.00', (string) $order->paid_total);
        $this->assertSame('pending', $payment->status);
    }

    public function test_paid_webhook_is_idempotent_for_already_paid_payment(): void
    {
        $this->mockGateway('paypal');
        [, , $order, $payment] = $this->createOrderWithPayment();

        $payload = [
            'event_type' => 'payment.succeeded',
            'ecommerce' => [
                'transaction_reference' => $payment->transaction_reference,
                'status' => 'paid',
                'amount' => '160.00',
            ],
        ];

        $this->postJson('/payment-gateways/paypal/webhook', [
            'id' => 'evt-paid-idempotent-1',
            ...$payload,
        ])->assertOk();

        $this->postJson('/payment-gateways/paypal/webhook', [
            'id' => 'evt-paid-idempotent-2',
            ...$payload,
        ])->assertOk();

        $order->refresh();

        $this->assertSame('160.00', (string) $order->paid_total);
        $this->assertSame('0.00', (string) $order->outstanding_total);
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
     * @param  array<string, mixed>  $orderOverrides
     * @param  array<string, mixed>  $paymentOverrides
     * @return array{0: Project, 1: Site, 2: EcommerceOrder, 3: EcommerceOrderPayment}
     */
    private function createOrderWithPayment(array $orderOverrides = [], array $paymentOverrides = []): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        /** @var EcommerceOrder $order */
        $order = EcommerceOrder::query()->create(array_merge([
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
        ], $orderOverrides));

        /** @var EcommerceOrderPayment $payment */
        $payment = EcommerceOrderPayment::query()->create(array_merge([
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
        ], $paymentOverrides));

        return [$project, $site, $order, $payment];
    }
}
