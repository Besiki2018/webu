<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class BillingOverviewPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_billing_index_exposes_overview_with_plan_usage_and_recommendations(): void
    {
        $starter = Plan::factory()->create([
            'name' => 'Starter',
            'sort_order' => 1,
            'price' => 19.00,
            'monthly_build_credits' => 10000,
            'max_monthly_orders' => 20,
            'max_monthly_bookings' => 10,
        ]);

        $pro = Plan::factory()->create([
            'name' => 'Pro',
            'sort_order' => 2,
            'price' => 49.00,
            'monthly_build_credits' => 100000,
            'max_monthly_orders' => 200,
            'max_monthly_bookings' => 100,
        ]);

        $lite = Plan::factory()->create([
            'name' => 'Lite',
            'sort_order' => 0,
            'price' => 9.00,
            'monthly_build_credits' => 3000,
            'max_monthly_orders' => 5,
            'max_monthly_bookings' => 5,
        ]);

        $user = User::factory()->withPlan($starter)->create([
            'build_credits' => 7500,
            'build_credit_overage_balance' => 500,
        ]);

        Subscription::factory()
            ->for($user)
            ->for($starter)
            ->active()
            ->create([
                'amount' => 19.00,
                'renewal_at' => now()->addDays(7),
            ]);

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Billing/Index')
                ->where('billingOverview.current_plan.plan_id', $starter->id)
                ->where('billingOverview.renewal.state', 'upcoming')
                ->where('billingOverview.usage.build_credits.remaining', 7500)
                ->where('billingOverview.usage.build_credits.overage_balance', 500)
                ->where('billingOverview.recommendations.upgrade.plan_id', $pro->id)
                ->where('billingOverview.recommendations.downgrade.plan_id', $lite->id)
            );
    }

    public function test_billing_index_exposes_upgrade_target_when_user_has_no_plan(): void
    {
        $free = Plan::factory()->create([
            'name' => 'Free',
            'slug' => 'free',
            'sort_order' => 0,
            'price' => 0,
            'monthly_build_credits' => 1000,
        ]);

        $starter = Plan::factory()->create([
            'name' => 'Starter',
            'sort_order' => 1,
            'price' => 19.00,
            'monthly_build_credits' => 10000,
        ]);

        $user = User::factory()->create([
            'plan_id' => null,
            'build_credits' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('billing.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Billing/Index')
                ->where('billingOverview.current_plan.plan_id', $free->id)
                ->where('billingOverview.recommendations.upgrade.plan_id', $starter->id)
                ->where('billingOverview.recommendations.downgrade', null)
            );
    }
}
