<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalCoreSchemaAuditP5F1Test extends TestCase
{
    public function test_p5_f1_audit_doc_documents_current_vs_universal_core_schema_gap_matrix(): void
    {
        $path = base_path('docs/architecture/UNIVERSAL_CORE_SCHEMA_AUDIT_P5_F1_01.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('P5-F1-01', $doc);
        $this->assertStringContainsString('Universal DB Schema (Multi-tenant, Projects, Any Industry) v1', $doc);
        $this->assertStringContainsString('Gap Matrix', $doc);
        $this->assertStringContainsString('tenants', $doc);
        $this->assertStringContainsString('tenant_users', $doc);
        $this->assertStringContainsString('projects', $doc);
        $this->assertStringContainsString('project_shares', $doc);
        $this->assertStringContainsString('project_members', $doc);
        $this->assertStringContainsString('sites', $doc);
        $this->assertStringContainsString('TenantContext', $doc);
        $this->assertStringContainsString('BelongsToTenantProject', $doc);
        $this->assertStringContainsString('system_settings', $doc);
        $this->assertStringContainsString('feature_flags', $doc);
        $this->assertStringContainsString('audit_logs', $doc);
        $this->assertStringContainsString('operation_logs', $doc);
        $this->assertStringContainsString('present / partial / missing', strtolower($doc));
        $this->assertStringContainsString('P5-F1-02', $doc);
        $this->assertStringContainsString('P5-F1-04', $doc);
    }
}

