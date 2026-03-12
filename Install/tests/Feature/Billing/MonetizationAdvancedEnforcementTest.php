<?php

namespace Tests\Feature\Billing;

use App\Models\ModuleAddon;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\Subscription;
use App\Models\SystemSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PricingCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MonetizationAdvancedEnforcementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
        SystemSetting::set('domain_enable_subdomains', true, 'boolean', 'domain');
    }

    public function test_admin_pricing_preview_composes_addons_and_rules_in_realtime(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create([
            'name' => 'Business',
            'price' => 50,
            'billing_period' => 'monthly',
        ]);

        $catalog = $this->actingAs($admin)
            ->getJson(route('admin.plans.pricing-catalog.show', ['plan' => $plan->id]))
            ->assertOk();

        $versionId = (int) $catalog->json('active_version_id');

        $this->actingAs($admin)
            ->postJson(route('admin.plans.pricing-catalog.addons.upsert', [
                'plan' => $plan->id,
                'version' => $versionId,
            ]), [
                'code' => 'inventory',
                'name' => 'Inventory',
                'pricing_mode' => 'fixed',
                'amount' => 10,
                'is_active' => true,
            ])->assertOk();

        $this->actingAs($admin)
            ->postJson(route('admin.plans.pricing-catalog.rules.upsert', [
                'plan' => $plan->id,
                'version' => $versionId,
            ]), [
                'code' => 'volume-discount',
                'name' => 'Volume Discount',
                'rule_type' => 'usage',
                'adjustment_type' => 'percentage',
                'amount' => -10,
                'is_active' => true,
                'conditions_json' => [
                    'min_orders' => 5,
                ],
            ])->assertOk();

        $this->actingAs($admin)
            ->postJson(route('admin.plans.pricing-catalog.preview', ['plan' => $plan->id]), [
                'version_id' => $versionId,
                'addon_codes' => ['inventory'],
                'usage' => [
                    'orders' => 8,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('quote.base_price', 50)
            ->assertJsonPath('quote.totals.final', 54)
            ->assertJsonPath('quote.applied_addons.0', 'inventory')
            ->assertJsonPath('quote.applied_rules.0', 'volume-discount');
    }

    public function test_user_can_preview_and_apply_prorated_plan_change(): void
    {
        $currentPlan = Plan::factory()->withSubdomains()->create([
            'price' => 30,
            'billing_period' => 'monthly',
        ]);
        $targetPlan = Plan::factory()->withSubdomains()->create([
            'price' => 90,
            'billing_period' => 'monthly',
        ]);

        $user = User::factory()->create([
            'plan_id' => $currentPlan->id,
        ]);

        $subscription = Subscription::factory()
            ->for($user)
            ->for($currentPlan)
            ->active()
            ->create([
                'amount' => 30,
                'renewal_at' => now()->addDays(15),
                'payment_method' => Subscription::PAYMENT_MANUAL,
            ]);

        $this->actingAs($user)
            ->postJson(route('billing.plans.proration-preview', ['plan' => $targetPlan->id]), [
                'apply_at_renewal' => false,
            ])
            ->assertOk()
            ->assertJsonPath('proration.subscription_id', $subscription->id)
            ->assertJsonPath('proration.target_plan.id', $targetPlan->id)
            ->assertJsonPath('proration.proration.direction', 'debit');

        $this->actingAs($user)
            ->postJson(route('billing.plans.change', ['plan' => $targetPlan->id]), [
                'apply_at_renewal' => false,
                'reason' => 'Upgrade now',
            ])
            ->assertOk()
            ->assertJsonPath('result.mode', 'immediate');

        $subscription->refresh();
        $user->refresh();

        $this->assertSame($targetPlan->id, (int) $subscription->plan_id);
        $this->assertSame($targetPlan->id, (int) $user->plan_id);
        $this->assertDatabaseHas('transactions', [
            'subscription_id' => $subscription->id,
            'type' => Transaction::TYPE_ADJUSTMENT,
            'status' => Transaction::STATUS_COMPLETED,
        ]);
    }

    public function test_plan_change_can_be_scheduled_for_next_renewal_with_no_immediate_switch(): void
    {
        $currentPlan = Plan::factory()->create([
            'price' => 120,
            'billing_period' => 'monthly',
        ]);
        $targetPlan = Plan::factory()->create([
            'price' => 40,
            'billing_period' => 'monthly',
        ]);

        $user = User::factory()->create([
            'plan_id' => $currentPlan->id,
        ]);

        $subscription = Subscription::factory()
            ->for($user)
            ->for($currentPlan)
            ->active()
            ->create([
                'amount' => 120,
                'renewal_at' => now()->addDays(12),
            ]);

        $this->actingAs($user)
            ->postJson(route('billing.plans.change', ['plan' => $targetPlan->id]), [
                'apply_at_renewal' => true,
                'reason' => 'Downgrade at cycle end',
            ])
            ->assertOk()
            ->assertJsonPath('result.mode', 'scheduled');

        $subscription->refresh();
        $this->assertSame($currentPlan->id, (int) $subscription->plan_id);
        $this->assertIsArray($subscription->metadata);
        $this->assertNotNull($subscription->metadata['pending_plan_change'] ?? null);
    }

    public function test_past_due_subscription_is_blocked_from_publish_actions(): void
    {
        $plan = Plan::factory()->withSubdomains()->create([
            'price' => 49,
        ]);

        $user = User::factory()->create([
            'plan_id' => $plan->id,
        ]);

        Subscription::factory()
            ->for($user)
            ->for($plan)
            ->pastDue(2)
            ->create();

        $project = Project::factory()->for($user)->create();

        $this->actingAs($user)
            ->postJson("/project/{$project->id}/publish", [
                'subdomain' => 'quota-'.Str::lower(Str::random(8)),
                'visibility' => 'public',
            ])
            ->assertStatus(402)
            ->assertJsonPath('code', 'subscription_enforcement');
    }

    public function test_advanced_ecommerce_and_booking_routes_require_addon_entitlements(): void
    {
        $plan = Plan::factory()->create([
            'price' => 99,
            'enable_ecommerce' => true,
            'enable_booking' => true,
        ]);

        $user = User::factory()->create([
            'plan_id' => $plan->id,
        ]);

        Subscription::factory()
            ->for($user)
            ->for($plan)
            ->active()
            ->create([
                'amount' => 99,
                'payment_method' => Subscription::PAYMENT_MANUAL,
            ]);

        $project = Project::factory()->for($user)->create();
        $site = Site::query()->where('project_id', $project->id)->firstOrFail();

        $this->actingAs($user)
            ->getJson(route('panel.sites.ecommerce.inventory.index', ['site' => $site->id]))
            ->assertStatus(403)
            ->assertJsonPath('feature', 'ecommerce_inventory');

        $this->actingAs($user)
            ->getJson(route('panel.sites.booking.finance.reports', ['site' => $site->id]))
            ->assertStatus(403)
            ->assertJsonPath('feature', 'booking_finance');

        $catalog = app(PricingCatalogService::class);
        $version = $catalog->ensurePlanHasInitialVersion($plan);

        ModuleAddon::query()
            ->where('plan_version_id', $version->id)
            ->whereIn('code', ['inventory', 'booking-finance'])
            ->update(['is_active' => true]);

        $this->actingAs($user)
            ->getJson(route('panel.sites.ecommerce.inventory.index', ['site' => $site->id]))
            ->assertOk();

        $this->actingAs($user)
            ->getJson(route('panel.sites.booking.finance.reports', ['site' => $site->id]))
            ->assertOk();
    }
}
