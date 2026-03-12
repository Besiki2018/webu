<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LandingBuilderPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_landing_builder_preview_uses_schema_safe_plan_columns(): void
    {
        SystemSetting::set('installation_completed', true, 'boolean', 'system');

        $admin = User::factory()->admin()->create();
        Plan::factory()->create([
            'is_active' => true,
            'name' => 'Starter',
            'slug' => 'starter',
            'price' => 19,
            'billing_period' => 'monthly',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.landing-builder.preview'));

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Landing')
            ->has('plans', 1)
            ->where('plans.0.name', 'Starter')
            ->where('plans.0.billing_period', 'monthly')
            ->missing('plans.0.currency')
            ->missing('plans.0.billing_cycle')
        );
    }
}
