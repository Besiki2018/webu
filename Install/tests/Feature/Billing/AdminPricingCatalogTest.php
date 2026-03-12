<?php

namespace Tests\Feature\Billing;

use App\Models\Plan;
use App\Models\PlanVersion;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPricingCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_admin_can_create_and_activate_versioned_pricing_catalog_with_audit_entries(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create([
            'name' => 'Business',
            'price' => 39.00,
            'billing_period' => 'monthly',
        ]);

        $catalog = $this->actingAs($admin)
            ->getJson(route('admin.plans.pricing-catalog.show', ['plan' => $plan->id]))
            ->assertOk()
            ->assertJsonPath('plan.id', $plan->id)
            ->assertJsonPath('active_version_number', 1)
            ->assertJsonCount(1, 'versions')
            ->assertJsonCount(10, 'versions.0.module_addons');

        $initialVersionId = (int) $catalog->json('versions.0.id');

        $draft = $this->actingAs($admin)
            ->postJson(route('admin.plans.pricing-catalog.versions.store', ['plan' => $plan->id]), [
                'base_price' => 59.99,
                'billing_period' => 'yearly',
                'currency' => 'usd',
                'notes' => 'Annual repricing',
            ])
            ->assertCreated()
            ->assertJsonPath('version.status', PlanVersion::STATUS_DRAFT)
            ->assertJsonPath('version.version_number', 2);

        $draftVersionId = (int) $draft->json('version.id');

        $this->actingAs($admin)
            ->postJson(route('admin.plans.pricing-catalog.addons.upsert', [
                'plan' => $plan->id,
                'version' => $draftVersionId,
            ]), [
                'code' => 'ecommerce-pro',
                'name' => 'Ecommerce Pro Module',
                'addon_group' => 'module',
                'pricing_mode' => 'fixed',
                'amount' => 12.50,
                'currency' => 'USD',
                'is_active' => true,
                'sort_order' => 15,
            ])
            ->assertOk()
            ->assertJsonPath('addon.code', 'ecommerce-pro');

        $this->actingAs($admin)
            ->postJson(route('admin.plans.pricing-catalog.rules.upsert', [
                'plan' => $plan->id,
                'version' => $draftVersionId,
            ]), [
                'code' => 'annual-discount',
                'name' => 'Annual Launch Discount',
                'rule_type' => 'promotion',
                'adjustment_type' => 'percentage',
                'amount' => -10,
                'priority' => 5,
                'is_active' => true,
                'conditions_json' => [
                    'module' => 'ecommerce',
                    'min_months' => 12,
                ],
            ])
            ->assertOk()
            ->assertJsonPath('rule.code', 'annual-discount');

        $this->actingAs($admin)
            ->postJson(route('admin.plans.pricing-catalog.versions.activate', [
                'plan' => $plan->id,
                'version' => $draftVersionId,
            ]), [
                'reason' => 'Roll out annual catalog update',
            ])
            ->assertOk()
            ->assertJsonPath('version.id', $draftVersionId)
            ->assertJsonPath('version.status', PlanVersion::STATUS_ACTIVE)
            ->assertJsonPath('plan.price', 59.99)
            ->assertJsonPath('plan.billing_period', 'yearly');

        $plan->refresh();

        $this->assertSame(59.99, (float) $plan->price);
        $this->assertSame('yearly', $plan->billing_period);

        $this->assertDatabaseHas('plan_versions', [
            'id' => $initialVersionId,
            'plan_id' => $plan->id,
            'status' => PlanVersion::STATUS_ARCHIVED,
            'version_number' => 1,
        ]);

        $this->assertDatabaseHas('plan_versions', [
            'id' => $draftVersionId,
            'plan_id' => $plan->id,
            'status' => PlanVersion::STATUS_ACTIVE,
            'version_number' => 2,
            'base_price' => '59.99',
            'billing_period' => 'yearly',
        ]);

        $this->assertDatabaseHas('module_addons', [
            'plan_version_id' => $draftVersionId,
            'code' => 'ecommerce-pro',
            'amount' => '12.50',
        ]);

        $this->assertDatabaseHas('price_rules', [
            'plan_version_id' => $draftVersionId,
            'code' => 'annual-discount',
            'adjustment_type' => 'percentage',
            'amount' => '-10.00',
        ]);

        $this->assertDatabaseHas('plan_version_audits', [
            'plan_version_id' => $draftVersionId,
            'action' => 'version_created',
            'actor_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('plan_version_audits', [
            'plan_version_id' => $draftVersionId,
            'action' => 'addon_upserted',
            'actor_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('plan_version_audits', [
            'plan_version_id' => $draftVersionId,
            'action' => 'price_rule_upserted',
            'actor_id' => $admin->id,
        ]);

        $this->assertDatabaseHas('plan_version_audits', [
            'plan_version_id' => $draftVersionId,
            'action' => 'version_activated',
            'actor_id' => $admin->id,
        ]);
    }

    public function test_plan_update_flow_creates_new_pricing_version_when_price_changes(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create([
            'name' => 'Starter',
            'price' => 25.00,
            'billing_period' => 'monthly',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.plans.update', ['plan' => $plan->id]), [
                'name' => 'Starter',
                'price' => 45.00,
                'billing_period' => 'monthly',
            ])
            ->assertRedirect(route('admin.plans'));

        $plan->refresh();
        $this->assertSame(45.00, (float) $plan->price);

        $versions = PlanVersion::query()
            ->where('plan_id', $plan->id)
            ->orderBy('version_number')
            ->get();

        $this->assertCount(2, $versions);
        $this->assertSame(1, (int) $versions[0]->version_number);
        $this->assertSame(2, (int) $versions[1]->version_number);
        $this->assertSame(PlanVersion::STATUS_ARCHIVED, $versions[0]->status);
        $this->assertSame(PlanVersion::STATUS_ACTIVE, $versions[1]->status);
        $this->assertSame(25.00, (float) $versions[0]->base_price);
        $this->assertSame(45.00, (float) $versions[1]->base_price);

        $this->assertDatabaseHas('plan_version_audits', [
            'plan_version_id' => $versions[1]->id,
            'action' => 'version_created_from_plan_update',
            'actor_id' => $admin->id,
        ]);
    }
}
