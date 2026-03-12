<?php

namespace Tests\Feature\Ecommerce;

use App\Contracts\PaymentGatewayPlugin;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderPayment;
use App\Models\EcommerceProduct;
use App\Models\GlobalSetting;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\EcommerceOrderMerchantNotification;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Tests\TestCase;

class EcommerceTransactionalNotificationsTest extends TestCase
{
    use MockeryPHPUnitIntegration;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_order_placed_notification_is_sent_to_merchant_on_checkout(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);
        $this->setMerchantNotificationEmail($site, 'merchant@example.com');

        EcommerceProduct::query()->create([
            'site_id' => $site->id,
            'name' => 'Restaurant Combo',
            'slug' => 'restaurant-combo',
            'sku' => 'COMBO-1',
            'price' => '65.00',
            'currency' => 'GEL',
            'status' => 'active',
            'stock_tracking' => true,
            'stock_quantity' => 30,
            'published_at' => now(),
        ]);

        Notification::fake();

        $cartResponse = $this->postJson(route('public.sites.ecommerce.carts.store', ['site' => $site->id]), [
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
        ])->assertCreated();

        $cartId = (string) $cartResponse->json('cart.id');

        $this->postJson(route('public.sites.ecommerce.carts.items.store', [
            'site' => $site->id,
            'cart' => $cartId,
        ]), [
            'product_id' => EcommerceProduct::query()->where('site_id', $site->id)->value('id'),
            'quantity' => 1,
        ])->assertOk();

        $this->postJson(route('public.sites.ecommerce.carts.checkout', [
            'site' => $site->id,
            'cart' => $cartId,
        ]), [
            'customer_email' => 'buyer@example.com',
            'customer_name' => 'Buyer One',
        ])->assertCreated();

        Notification::assertSentOnDemand(EcommerceOrderMerchantNotification::class, function (
            EcommerceOrderMerchantNotification $notification,
            array $channels,
            object $notifiable
        ): bool {
            $route = $notifiable->routeNotificationFor('mail');
            $emails = is_array($route) ? $route : [$route];

            return in_array('mail', $channels, true)
                && $notification->eventType === EcommerceOrderMerchantNotification::EVENT_ORDER_PLACED
                && in_array('merchant@example.com', $emails, true);
        });
    }

    public function test_order_paid_notification_is_sent_on_successful_webhook_sync(): void
    {
        $this->mockGateway('paypal');
        [, $site, $order, $payment] = $this->createOrderWithPayment();
        $this->setMerchantNotificationEmail($site, 'merchant@example.com');

        Notification::fake();

        $this->postJson('/payment-gateways/paypal/webhook', [
            'id' => 'evt-notify-paid-1',
            'event_type' => 'payment.succeeded',
            'ecommerce' => [
                'transaction_reference' => $payment->transaction_reference,
                'status' => 'paid',
                'amount' => '160.00',
            ],
        ])->assertOk();

        Notification::assertSentOnDemand(EcommerceOrderMerchantNotification::class, function (
            EcommerceOrderMerchantNotification $notification,
            array $channels,
            object $notifiable
        ) use ($order): bool {
            $route = $notifiable->routeNotificationFor('mail');
            $emails = is_array($route) ? $route : [$route];

            return in_array('mail', $channels, true)
                && $notification->eventType === EcommerceOrderMerchantNotification::EVENT_ORDER_PAID
                && (int) $notification->order->id === (int) $order->id
                && in_array('merchant@example.com', $emails, true);
        });
    }

    public function test_order_failed_notification_is_sent_on_failed_webhook_sync(): void
    {
        $this->mockGateway('paypal');
        [, $site, $order, $payment] = $this->createOrderWithPayment();
        $this->setMerchantNotificationEmail($site, 'merchant@example.com');

        Notification::fake();

        $this->postJson('/payment-gateways/paypal/webhook', [
            'id' => 'evt-notify-failed-1',
            'event_type' => 'payment.failed',
            'ecommerce' => [
                'payment_id' => $payment->id,
                'status' => 'failed',
            ],
        ])->assertOk();

        Notification::assertSentOnDemand(EcommerceOrderMerchantNotification::class, function (
            EcommerceOrderMerchantNotification $notification,
            array $channels,
            object $notifiable
        ) use ($order): bool {
            $route = $notifiable->routeNotificationFor('mail');
            $emails = is_array($route) ? $route : [$route];

            return in_array('mail', $channels, true)
                && $notification->eventType === EcommerceOrderMerchantNotification::EVENT_ORDER_FAILED
                && (int) $notification->order->id === (int) $order->id
                && in_array('merchant@example.com', $emails, true);
        });
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

    private function setMerchantNotificationEmail(Site $site, string $email): void
    {
        $global = $site->globalSettings()->first();
        if (! $global) {
            $global = GlobalSetting::query()->create([
                'site_id' => $site->id,
                'contact_json' => [],
                'social_links_json' => [],
                'analytics_ids_json' => [],
            ]);
        }

        $contact = is_array($global->contact_json) ? $global->contact_json : [];
        $contact['email'] = $email;
        $global->update([
            'contact_json' => $contact,
        ]);
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

    /**
     * @return array{0: Project, 1: Site, 2: EcommerceOrder, 3: EcommerceOrderPayment}
     */
    private function createOrderWithPayment(): array
    {
        $owner = User::factory()->create();
        [$project, $site] = $this->createPublishedProjectWithSite($owner, true);

        /** @var EcommerceOrder $order */
        $order = EcommerceOrder::query()->create([
            'site_id' => $site->id,
            'order_number' => 'ORD-'.strtoupper(Str::random(10)),
            'status' => 'pending',
            'payment_status' => 'unpaid',
            'fulfillment_status' => 'unfulfilled',
            'currency' => 'GEL',
            'customer_email' => 'buyer@example.com',
            'customer_phone' => '+995555111222',
            'customer_name' => 'Buyer',
            'subtotal' => '160.00',
            'tax_total' => '0.00',
            'shipping_total' => '0.00',
            'discount_total' => '0.00',
            'grand_total' => '160.00',
            'paid_total' => '0.00',
            'outstanding_total' => '160.00',
            'placed_at' => now(),
            'meta_json' => ['source' => 'test'],
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

        return [$project, $site, $order, $payment];
    }
}

