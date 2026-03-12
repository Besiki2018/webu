<?php

namespace Tests\Feature\Platform;

use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class UniversalCoreTenantAccessFoundationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_universal_core_tenant_access_and_project_foundation_tables_and_columns_exist(): void
    {
        foreach ([
            'tenants',
            'tenant_users',
            'roles',
            'permissions',
            'role_permissions',
            'user_roles',
            'project_members',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Expected table [{$table}] to exist.");
        }

        $this->assertTrue(Schema::hasColumns('projects', [
            'tenant_id',
            'type',
            'default_currency',
            'default_locale',
            'timezone',
        ]));
    }

    public function test_foundation_relations_support_additive_tenant_project_membership_without_replacing_legacy_owner_model(): void
    {
        $owner = User::factory()->create();

        $tenant = Tenant::query()->create([
            'name' => 'Acme Group',
            'slug' => 'acme-group-'.Str::lower(Str::random(6)),
            'status' => 'active',
            'default_currency' => 'USD',
            'default_locale' => 'en',
            'timezone' => 'UTC',
            'created_by_user_id' => $owner->id,
        ]);

        $project = Project::factory()->for($owner)->create([
            'tenant_id' => (string) $tenant->id,
            'type' => 'ecommerce',
            'default_currency' => 'USD',
            'default_locale' => 'en',
            'timezone' => 'UTC',
        ]);

        $tenantUser = TenantUser::query()->create([
            'tenant_id' => (string) $tenant->id,
            'platform_user_id' => $owner->id,
            'name' => $owner->name,
            'email' => $owner->email,
            'status' => 'active',
            'role_legacy' => 'owner',
        ]);

        $member = ProjectMember::query()->create([
            'project_id' => (string) $project->id,
            'tenant_user_id' => $tenantUser->id,
            'role' => 'owner',
            'status' => 'active',
            'invited_by_user_id' => $owner->id,
        ]);

        $this->assertSame((string) $tenant->id, (string) $project->tenant?->id);
        $this->assertSame($owner->id, $project->user_id, 'Legacy project owner link must remain active.');
        $this->assertSame(1, $project->projectMembers()->count());
        $this->assertSame($member->id, $project->projectMembers()->firstOrFail()->id);
        $this->assertSame(1, $tenant->projects()->count());
        $this->assertSame(1, $tenant->tenantUsers()->count());
        $this->assertSame(1, $tenantUser->projects()->count());
        $this->assertSame((string) $project->id, (string) $tenantUser->projects()->firstOrFail()->id);
        $this->assertSame($owner->id, $tenantUser->platformUser?->id);

        DB::table('permissions')->insert([
            'key' => 'projects.view',
            'label' => 'View Projects',
            'group_key' => 'projects',
            'description' => 'Baseline permission',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = DB::table('roles')->insertGetId([
            'tenant_id' => (string) $tenant->id,
            'key' => 'owner',
            'label' => 'Owner',
            'scope' => 'tenant',
            'status' => 'active',
            'description' => 'Tenant owner role',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $permissionId = (int) DB::table('permissions')->where('key', 'projects.view')->value('id');
        $this->assertGreaterThan(0, $permissionId);

        DB::table('role_permissions')->insert([
            'role_id' => $roleId,
            'permission_id' => $permissionId,
            'allowed' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('user_roles')->insert([
            'tenant_user_id' => $tenantUser->id,
            'role_id' => $roleId,
            'project_id' => (string) $project->id,
            'scope_type' => 'project',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertDatabaseHas('user_roles', [
            'tenant_user_id' => $tenantUser->id,
            'role_id' => $roleId,
            'project_id' => (string) $project->id,
            'scope_type' => 'project',
        ]);
    }
}
