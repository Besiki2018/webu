<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalCoreTenantAccessFoundationSummaryP5Test extends TestCase
{
    public function test_phase5_summary_core_multitenant_and_universal_project_checkboxes_are_locked_to_foundation_ddl(): void
    {
        $docPath = base_path('docs/architecture/UNIVERSAL_CORE_TENANT_ACCESS_FOUNDATION_P5_SUMMARY_BASELINE.md');
        $migrationTablesPath = base_path('database/migrations/2026_02_24_236000_create_universal_core_tenant_access_tables.php');
        $migrationProjectsPath = base_path('database/migrations/2026_02_24_236500_add_universal_core_columns_to_projects_table.php');
        $projectModelPath = base_path('app/Models/Project.php');
        $tenantModelPath = base_path('app/Models/Tenant.php');
        $tenantUserModelPath = base_path('app/Models/TenantUser.php');
        $projectMemberModelPath = base_path('app/Models/ProjectMember.php');
        $schemaTestPath = base_path('tests/Feature/Platform/UniversalCoreTenantAccessFoundationSchemaTest.php');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([
            $docPath,
            $migrationTablesPath,
            $migrationProjectsPath,
            $projectModelPath,
            $tenantModelPath,
            $tenantUserModelPath,
            $projectMemberModelPath,
            $schemaTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $migrationTables = File::get($migrationTablesPath);
        $migrationProjects = File::get($migrationProjectsPath);
        $projectModel = File::get($projectModelPath);
        $tenantModel = File::get($tenantModelPath);
        $tenantUserModel = File::get($tenantUserModelPath);
        $projectMemberModel = File::get($projectMemberModelPath);
        $schemaTest = File::get($schemaTestPath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('Multi-tenant core tables and access model', $doc);
        $this->assertStringContainsString('Universal project model', $doc);
        $this->assertStringContainsString('P5-F1-02', $doc);
        $this->assertStringContainsString('project_shares', $doc);
        $this->assertStringContainsString('without read-path cutover', strtolower($doc));
        $this->assertStringContainsString('tenants', $doc);
        $this->assertStringContainsString('tenant_users', $doc);
        $this->assertStringContainsString('project_members', $doc);
        $this->assertStringContainsString('projects', $doc);
        $this->assertStringContainsString('tenant_id', $doc);

        $this->assertStringContainsString("Schema::create('tenants'", $migrationTables);
        $this->assertStringContainsString("Schema::create('tenant_users'", $migrationTables);
        $this->assertStringContainsString("Schema::create('project_members'", $migrationTables);
        $this->assertStringContainsString("Schema::create('roles'", $migrationTables);
        $this->assertStringContainsString("Schema::create('permissions'", $migrationTables);
        $this->assertStringContainsString("Schema::create('role_permissions'", $migrationTables);
        $this->assertStringContainsString("Schema::create('user_roles'", $migrationTables);

        $this->assertStringContainsString("'tenant_id'", $migrationProjects);
        $this->assertStringContainsString("'type'", $migrationProjects);
        $this->assertStringContainsString("'default_currency'", $migrationProjects);
        $this->assertStringContainsString("'default_locale'", $migrationProjects);
        $this->assertStringContainsString("'timezone'", $migrationProjects);

        $this->assertStringContainsString('function tenant(): BelongsTo', $projectModel);
        $this->assertStringContainsString('function projectMembers(): HasMany', $projectModel);
        $this->assertStringContainsString("'tenant_id'", $projectModel);
        $this->assertStringContainsString("'type'", $projectModel);

        $this->assertStringContainsString('class Tenant extends Model', $tenantModel);
        $this->assertStringContainsString('function tenantUsers(): HasMany', $tenantModel);
        $this->assertStringContainsString('function projects(): HasMany', $tenantModel);

        $this->assertStringContainsString('class TenantUser extends Model', $tenantUserModel);
        $this->assertStringContainsString('function projects(): BelongsToMany', $tenantUserModel);

        $this->assertStringContainsString('class ProjectMember extends Model', $projectMemberModel);
        $this->assertStringContainsString('function project(): BelongsTo', $projectMemberModel);

        $this->assertStringContainsString('UniversalCoreTenantAccessFoundationSchemaTest', $schemaTest);
        $this->assertStringContainsString('roles', $schemaTest);
        $this->assertStringContainsString('permissions', $schemaTest);

        $this->assertStringContainsString('- ✅ Multi-tenant core tables and access model', $roadmap);
        $this->assertStringContainsString('- ✅ Universal project model', $roadmap);
    }
}
