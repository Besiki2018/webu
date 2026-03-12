<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\ReferralCreditTransaction;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingCreditOveragePurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_user_can_purchase_overage_pack_with_referral_credits(): void
    {
        $plan = Plan::factory()->withBuildCredits(1000)->create();
        $user = User::factory()->withPlan($plan)->create([
            'referral_credit_balance' => 15.00,
            'build_credit_overage_balance' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('billing.usage.purchase'), [
                'pack_key' => 'starter_25k',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('purchase.pack.key', 'starter_25k')
            ->assertJsonPath('purchase.overage_balance_after', 25000);

        $user->refresh();

        $this->assertSame(26000, (int) $user->build_credits);
        $this->assertSame(25000, (int) $user->build_credit_overage_balance);
        $this->assertSame(10.00, (float) $user->referral_credit_balance);

        $this->assertDatabaseHas('transactions', [
            'user_id' => $user->id,
            'type' => Transaction::TYPE_CREDIT_TOPUP,
            'status' => Transaction::STATUS_COMPLETED,
            'payment_method' => Transaction::PAYMENT_MANUAL,
        ]);

        $this->assertDatabaseHas('referral_credit_transactions', [
            'user_id' => $user->id,
            'type' => ReferralCreditTransaction::TYPE_BILLING_REDEMPTION,
        ]);
    }

    public function test_overage_purchase_is_blocked_when_balance_is_insufficient(): void
    {
        $plan = Plan::factory()->withBuildCredits(1000)->create();
        $user = User::factory()->withPlan($plan)->create([
            'referral_credit_balance' => 1.00,
            'build_credit_overage_balance' => 0,
        ]);

        $this->actingAs($user)
            ->postJson(route('billing.usage.purchase'), [
                'pack_key' => 'starter_25k',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $user->refresh();
        $this->assertSame(1000, (int) $user->build_credits);
        $this->assertSame(0, (int) $user->build_credit_overage_balance);
        $this->assertSame(1.00, (float) $user->referral_credit_balance);
    }

    public function test_monthly_credit_reset_preserves_remaining_overage_balance(): void
    {
        $plan = Plan::factory()->withBuildCredits(10000)->create();
        $user = User::factory()->withPlan($plan)->create([
            'build_credits' => 4321,
            'build_credit_overage_balance' => 2500,
        ]);

        $this->artisan('credits:reset', [
            '--user' => (string) $user->id,
            '--triggered-by' => 'test-suite',
        ])->assertSuccessful();

        $user->refresh();

        $this->assertSame(12500, (int) $user->build_credits);
        $this->assertSame(2500, (int) $user->build_credit_overage_balance);
    }

    public function test_credit_deduction_consumes_monthly_pool_before_overage_pool(): void
    {
        $plan = Plan::factory()->withBuildCredits(1000)->create();
        $user = User::factory()->withPlan($plan)->create([
            'build_credits' => 1200,
            'build_credit_overage_balance' => 200,
        ]);

        $this->assertTrue($user->deductBuildCredits(1100));
        $user->refresh();

        $this->assertSame(100, (int) $user->build_credits);
        $this->assertSame(100, (int) $user->build_credit_overage_balance);
    }
}
