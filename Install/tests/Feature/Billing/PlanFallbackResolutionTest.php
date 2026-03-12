<?php

namespace Tests\Feature\Billing;

use App\Models\Builder;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanFallbackResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_builder_fallback_uses_first_active_builder_when_defaults_are_missing(): void
    {
        $plan = Plan::factory()->create([
            'builder_id' => null,
            'ai_provider_id' => null,
        ]);

        $inactiveBuilder = Builder::factory()->create(['status' => 'inactive']);
        $activeBuilder = Builder::factory()->create(['status' => 'active']);

        SystemSetting::set('default_builder_id', null, 'integer', 'plans');

        $resolved = $plan->fresh()->getBuilderWithFallbacks();

        $this->assertNotNull($resolved);
        $this->assertSame($activeBuilder->id, $resolved->id);
        $this->assertNotSame($inactiveBuilder->id, $resolved->id);
    }

    public function test_user_current_plan_falls_back_to_platform_default_plan(): void
    {
        $free = Plan::factory()->free()->create();
        $pro = Plan::factory()->create(['slug' => 'pro-custom']);

        SystemSetting::set('default_plan_id', $pro->id, 'integer', 'plans');

        $user = User::factory()->create([
            'plan_id' => null,
            'build_credits' => 0,
        ]);

        $resolved = $user->fresh()->getCurrentPlan();

        $this->assertNotNull($resolved);
        $this->assertSame($pro->id, $resolved->id);
        $this->assertNotSame($free->id, $resolved->id);
    }
}

