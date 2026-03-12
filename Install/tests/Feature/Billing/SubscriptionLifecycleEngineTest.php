<?php

namespace Tests\Feature\Billing;

use App\Contracts\PaymentGatewayPlugin;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PluginManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class SubscriptionLifecycleEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');

        config()->set('billing.subscriptions.max_retries', 2);
        config()->set('billing.subscriptions.retry_interval_hours', 1);
        config()->set('billing.subscriptions.grace_days', 2);
        config()->set('billing.subscriptions.fallback_to_default_plan_on_suspend', true);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_due_subscription_is_renewed_when_gateway_reports_active(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-20 10:00:00'));

        $plan = Plan::factory()->create([
            'price' => 29,
            'billing_period' => 'monthly',
        ]);

        $user = User::factory()->withPlan($plan)->create();

        $subscription = Subscription::factory()
            ->for($user)
            ->for($plan)
            ->active()
            ->create([
                'payment_method' => Subscription::PAYMENT_PAYPAL,
                'external_subscription_id' => 'PP-SUB-001',
                'renewal_at' => now()->subMinute(),
                'renewal_retry_count' => 1,
                'last_renewal_error' => 'Old failure',
            ]);

        $this->bindGatewayMock('paypal', function (MockInterface $gateway): void {
            $gateway->shouldReceive('supportsAutoRenewal')->once()->andReturn(true);
            $gateway->shouldReceive('getSubscriptionStatus')->once()->andReturn([
                'status' => 'ACTIVE',
            ]);
        });

        $this->artisan('subscriptions:manage')->assertExitCode(0);

        $subscription->refresh();

        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertSame(0, (int) $subscription->renewal_retry_count);
        $this->assertNull($subscription->next_retry_at);
        $this->assertNull($subscription->grace_ends_at);
        $this->assertTrue($subscription->renewal_at?->greaterThan(now()) ?? false);

        $transaction = Transaction::query()
            ->where('subscription_id', $subscription->id)
            ->where('type', Transaction::TYPE_SUBSCRIPTION_RENEWAL)
            ->latest('id')
            ->first();

        $this->assertNotNull($transaction);
        $this->assertSame(Transaction::STATUS_COMPLETED, $transaction?->status);
    }

    public function test_failed_renewals_move_subscription_to_past_due_then_grace_then_suspend(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-20 10:00:00'));

        $defaultPlan = Plan::factory()->create([
            'name' => 'Default',
            'sort_order' => 0,
            'price' => 0,
        ]);
        SystemSetting::set('default_plan_id', $defaultPlan->id, 'integer', 'system');

        $pro = Plan::factory()->create([
            'name' => 'Pro',
            'sort_order' => 1,
            'price' => 59,
        ]);

        $user = User::factory()->withPlan($pro)->create();

        $subscription = Subscription::factory()
            ->for($user)
            ->for($pro)
            ->active()
            ->create([
                'payment_method' => Subscription::PAYMENT_MANUAL,
                'renewal_at' => now()->subMinute(),
                'renewal_retry_count' => 0,
            ]);

        // Attempt #1 -> past_due + retry schedule
        $this->artisan('subscriptions:manage')->assertExitCode(0);

        $subscription->refresh();
        $this->assertSame(Subscription::STATUS_PAST_DUE, $subscription->status);
        $this->assertSame(1, (int) $subscription->renewal_retry_count);
        $this->assertNotNull($subscription->next_retry_at);

        // Attempt #2 -> grace (retry limit reached)
        Carbon::setTestNow(now()->addHour()->addMinute());
        $this->artisan('subscriptions:manage')->assertExitCode(0);

        $subscription->refresh();
        $user->refresh();
        $this->assertSame(Subscription::STATUS_GRACE, $subscription->status);
        $this->assertSame(2, (int) $subscription->renewal_retry_count);
        $this->assertNull($subscription->next_retry_at);
        $this->assertNotNull($subscription->grace_ends_at);
        $this->assertSame($pro->id, $user->plan_id);

        // Grace expired -> suspended + fallback plan applied
        Carbon::setTestNow($subscription->grace_ends_at?->copy()->addMinute());
        $this->artisan('subscriptions:manage')->assertExitCode(0);

        $subscription->refresh();
        $user->refresh();
        $this->assertSame(Subscription::STATUS_SUSPENDED, $subscription->status);
        $this->assertNotNull($subscription->suspended_at);
        $this->assertSame($defaultPlan->id, $user->plan_id);

        $renewalAttempts = Transaction::query()
            ->where('subscription_id', $subscription->id)
            ->where('type', Transaction::TYPE_SUBSCRIPTION_RENEWAL)
            ->count();

        $this->assertSame(2, $renewalAttempts);
    }

    public function test_retry_cycle_recovers_when_later_gateway_check_becomes_active(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-02-20 10:00:00'));

        $plan = Plan::factory()->create([
            'price' => 39,
            'billing_period' => 'monthly',
        ]);

        $user = User::factory()->withPlan($plan)->create();

        $subscription = Subscription::factory()
            ->for($user)
            ->for($plan)
            ->active()
            ->create([
                'payment_method' => Subscription::PAYMENT_PAYPAL,
                'external_subscription_id' => 'PP-SUB-RETRY-001',
                'renewal_at' => now()->subMinute(),
            ]);

        $this->bindGatewayMock('paypal', function (MockInterface $gateway): void {
            $gateway->shouldReceive('supportsAutoRenewal')->once()->andReturn(true);
            $gateway->shouldReceive('getSubscriptionStatus')->once()->andReturn([
                'status' => 'PENDING',
            ]);
        });

        $this->artisan('subscriptions:manage')->assertExitCode(0);

        $subscription->refresh();
        $this->assertSame(Subscription::STATUS_PAST_DUE, $subscription->status);
        $this->assertSame(1, (int) $subscription->renewal_retry_count);
        $this->assertNotNull($subscription->next_retry_at);

        $this->bindGatewayMock('paypal', function (MockInterface $gateway): void {
            $gateway->shouldReceive('supportsAutoRenewal')->once()->andReturn(true);
            $gateway->shouldReceive('getSubscriptionStatus')->once()->andReturn([
                'status' => 'ACTIVE',
            ]);
        });

        Carbon::setTestNow($subscription->next_retry_at?->copy()->addMinute());
        $this->artisan('subscriptions:manage')->assertExitCode(0);

        $subscription->refresh();

        $this->assertSame(Subscription::STATUS_ACTIVE, $subscription->status);
        $this->assertSame(0, (int) $subscription->renewal_retry_count);
        $this->assertNull($subscription->next_retry_at);
        $this->assertNull($subscription->grace_ends_at);

        $this->assertSame(
            1,
            Transaction::query()
                ->where('subscription_id', $subscription->id)
                ->where('type', Transaction::TYPE_SUBSCRIPTION_RENEWAL)
                ->where('status', Transaction::STATUS_FAILED)
                ->count()
        );

        $this->assertSame(
            1,
            Transaction::query()
                ->where('subscription_id', $subscription->id)
                ->where('type', Transaction::TYPE_SUBSCRIPTION_RENEWAL)
                ->where('status', Transaction::STATUS_COMPLETED)
                ->count()
        );
    }

    private function bindGatewayMock(string $slug, callable $configureGateway): void
    {
        $gateway = Mockery::mock(PaymentGatewayPlugin::class);
        $configureGateway($gateway);

        $pluginManager = Mockery::mock(PluginManager::class);
        $pluginManager->shouldReceive('getGatewayBySlug')
            ->with($slug)
            ->andReturn($gateway);

        $this->app->instance(PluginManager::class, $pluginManager);
    }
}
