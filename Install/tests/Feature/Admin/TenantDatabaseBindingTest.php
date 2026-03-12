<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\TenantDatabaseBinding;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantDatabaseBindingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_admin_can_manage_dedicated_db_binding_for_enterprise_project(): void
    {
        config()->set('tenancy.dedicated_db_enabled', true);

        $enterprise = Plan::factory()->create([
            'slug' => 'enterprise',
            'name' => 'Enterprise',
        ]);

        $admin = User::factory()->admin()->create();
        $owner = User::factory()->withPlan($enterprise)->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($admin)
            ->putJson(route('admin.projects.dedicated-db.provision', $project), [
                'driver' => 'mysql',
                'host' => '127.0.0.1',
                'port' => 3306,
                'database' => 'tenant_'.$project->id,
                'username' => 'tenant_user',
                'password' => 'secret',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('binding.status', TenantDatabaseBinding::STATUS_ACTIVE);

        $this->assertDatabaseHas('tenant_database_bindings', [
            'project_id' => $project->id,
            'status' => TenantDatabaseBinding::STATUS_ACTIVE,
        ]);

        $this->actingAs($admin)
            ->getJson(route('admin.projects.dedicated-db.status', $project))
            ->assertOk()
            ->assertJsonPath('feature_enabled', true)
            ->assertJsonPath('eligible', true)
            ->assertJsonPath('binding.status', TenantDatabaseBinding::STATUS_ACTIVE);

        $this->actingAs($admin)
            ->deleteJson(route('admin.projects.dedicated-db.disable', $project))
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('binding.status', TenantDatabaseBinding::STATUS_DISABLED);
    }

    public function test_provision_is_blocked_when_dedicated_db_feature_is_disabled(): void
    {
        config()->set('tenancy.dedicated_db_enabled', false);

        $admin = User::factory()->admin()->create();
        $project = Project::factory()->create();

        $this->actingAs($admin)
            ->putJson(route('admin.projects.dedicated-db.provision', $project), [
                'host' => '127.0.0.1',
                'database' => 'tenant_db',
            ])
            ->assertStatus(422);
    }
}

