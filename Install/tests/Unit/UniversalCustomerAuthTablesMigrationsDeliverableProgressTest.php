<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalCustomerAuthTablesMigrationsDeliverableProgressTest extends TestCase
{
    public function test_customer_auth_migrations_batch_progress_is_documented_and_reflected_in_gap_audit(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $migrationPath = base_path('database/migrations/2026_02_24_237000_create_universal_customer_auth_tables.php');
        $docPath = base_path('docs/qa/UNIVERSAL_CUSTOMER_AUTH_TABLES_MIGRATIONS_DELIVERABLE_PROGRESS.md');
        $gapAuditPath = base_path('docs/qa/UNIVERSAL_FULL_MIGRATIONS_DELIVERABLE_GAP_AUDIT_BASELINE.md');

        foreach ([$roadmapPath, $migrationPath, $docPath, $gapAuditPath] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $migration = File::get($migrationPath);
        $doc = File::get($docPath);
        $gapAudit = File::get($gapAuditPath);

        $this->assertStringContainsString('# 17) Deliverables', $roadmap);
        $this->assertStringContainsString('1) Full migrations for all tables above', $roadmap);

        // Migration should define all canonical customer/auth tables.
        foreach ([
            "Schema::create('customers'",
            "Schema::create('customer_sessions'",
            "Schema::create('customer_addresses'",
            "Schema::create('otp_requests'",
            "Schema::create('social_accounts'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }

        $this->assertStringContainsString("\$table->uuid('tenant_id')", $migration);
        $this->assertStringContainsString("\$table->uuid('project_id')", $migration);
        $this->assertStringContainsString("\$table->string('password_hash')->nullable()", $migration);
        $this->assertStringContainsString("\$table->string('token_hash', 190)", $migration);
        $this->assertStringContainsString("\$table->string('provider_user_id', 191)", $migration);

        // Batch progress doc should remain truthful and point to verification.
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:6415', $doc);
        $this->assertStringContainsString('2026_02_24_237000_create_universal_customer_auth_tables.php', $doc);
        $this->assertStringContainsString('UniversalCustomerAuthTablesSchemaTest.php', $doc);
        $this->assertStringContainsString('UniversalCustomerAuthTablesMigrationsDeliverableProgressTest.php', $doc);
        $this->assertStringContainsString('still NOT COMPLETE', $doc);

        // Gap-audit matrix + summary must reflect that these five are no longer missing.
        foreach ([
            '| customers | exact | `customers` |',
            '| customer_sessions | exact | `customer_sessions` |',
            '| customer_addresses | exact | `customer_addresses` |',
            '| otp_requests | exact | `otp_requests` |',
            '| social_accounts | exact | `social_accounts` |',
        ] as $needle) {
            $this->assertStringContainsString($needle, $gapAudit);
        }

        $this->assertStringContainsString('- `exact`: `50`', $gapAudit);
        $this->assertStringContainsString('- `equivalent`: `19`', $gapAudit);
        $this->assertStringContainsString('- `partial`: `0`', $gapAudit);
        $this->assertStringContainsString('- `missing`: `0`', $gapAudit);
    }
}
