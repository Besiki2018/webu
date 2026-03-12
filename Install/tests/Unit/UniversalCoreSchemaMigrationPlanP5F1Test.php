<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalCoreSchemaMigrationPlanP5F1Test extends TestCase
{
    public function test_p5_f1_02_migration_plan_doc_defines_incremental_phases_backfills_and_cutover_strategy(): void
    {
        $path = base_path('docs/architecture/UNIVERSAL_CORE_SCHEMA_MIGRATION_PLAN_P5_F1_02.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('P5-F1-02', $doc);
        $this->assertStringContainsString('incremental migration plan', strtolower($doc));
        $this->assertStringContainsString('UNIVERSAL_CORE_SCHEMA_AUDIT_P5_F1_01', $doc);
        $this->assertStringContainsString('tenants', $doc);
        $this->assertStringContainsString('tenant_users', $doc);
        $this->assertStringContainsString('project_members', $doc);
        $this->assertStringContainsString('projects.tenant_id', $doc);
        $this->assertStringContainsString('project_shares', $doc);
        $this->assertStringContainsString('sites', $doc);
        $this->assertStringContainsString('Dual-Read / Dual-Write', $doc);
        $this->assertStringContainsString('Read Cutover', $doc);
        $this->assertStringContainsString('Rollback', $doc);
        $this->assertStringContainsString('validation gates', strtolower($doc));
        $this->assertStringContainsString('P5-F1-03', $doc);
        $this->assertStringContainsString('P5-F1-04', $doc);
    }
}

