<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalDbSchemaIndexesConstraintsDeliverablesAcceptanceUdb05SyncTest extends TestCase
{
    public function test_udb_05_audit_doc_locks_indexes_constraints_deliverables_and_acceptance_reconciliation_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_DB_SCHEMA_INDEXES_CONSTRAINTS_DELIVERABLES_ACCEPTANCE_AUDIT_UDB_05_2026_02_25.md');

        $udb01DocPath = base_path('docs/qa/UNIVERSAL_DB_SCHEMA_CORE_MULTI_TENANT_PROJECTS_PUBLIC_USERS_AUDIT_UDB_01_2026_02_25.md');
        $udb02DocPath = base_path('docs/qa/UNIVERSAL_DB_SCHEMA_CONTENT_BUILDER_FORMS_NOTIFICATIONS_AUDIT_UDB_02_2026_02_25.md');
        $udb03DocPath = base_path('docs/qa/UNIVERSAL_DB_SCHEMA_PAYMENTS_ECOMMERCE_MODULE_TABLES_AUDIT_UDB_03_2026_02_25.md');
        $udb04DocPath = base_path('docs/qa/UNIVERSAL_DB_SCHEMA_SERVICES_VERTICAL_EXTENSIONS_AUDIT_LOGS_AUDIT_UDB_04_2026_02_25.md');
        $udb03SyncTestPath = base_path('tests/Unit/UniversalDbSchemaPaymentsEcommerceModuleTablesUdb03SyncTest.php');
        $udb04SyncTestPath = base_path('tests/Unit/UniversalDbSchemaServicesVerticalExtensionsAuditLogsUdb04SyncTest.php');

        $projectsCoreColumnsMigrationPath = base_path('database/migrations/2026_02_24_236500_add_universal_core_columns_to_projects_table.php');
        $partialParityMigrationPath = base_path('database/migrations/2026_02_24_242000_add_canonical_parity_columns_and_tables_for_partial_rows.php');
        $cmsCoreMigrationPath = base_path('database/migrations/2026_02_20_050000_create_cms_core_tables.php');
        $ecommerceCoreMigrationPath = base_path('database/migrations/2026_02_20_070000_create_ecommerce_core_tables.php');
        $bookingCoreMigrationPath = base_path('database/migrations/2026_02_20_098000_create_booking_core_tables.php');
        $servicesNormMigrationPath = base_path('database/migrations/2026_02_24_240000_create_universal_services_normalization_tables.php');

        $tenantScopingFeatureTestPath = base_path('tests/Feature/Security/TenantProjectRouteScopingMiddlewareTest.php');
        $tenantIsolationFeatureTestPath = base_path('tests/Feature/Cms/TenantIsolationTest.php');
        $tenantScopingContractTestPath = base_path('tests/Unit/UniversalTenantProjectScopingContractP5F1Test.php');
        $ecommerceRsReadinessFeatureTestPath = base_path('tests/Feature/Ecommerce/EcommerceRsReadinessTest.php');
        $formsLeadsFeatureTestPath = base_path('tests/Feature/Forms/FormsLeadsModuleApiTest.php');

        $fullMigrationsDocPath = base_path('docs/qa/UNIVERSAL_FULL_MIGRATIONS_DELIVERABLE_GAP_AUDIT_BASELINE.md');
        $seedersDocPath = base_path('docs/qa/UNIVERSAL_DEMO_PROJECT_TYPE_SEEDERS_DELIVERABLE_BASELINE.md');
        $openApiReadmePath = base_path('docs/openapi/README.md');
        $softDeleteDocPath = base_path('docs/qa/UNIVERSAL_SOFT_DELETE_STRATEGY_OPTIONAL_V1.md');
        $acceptanceDocPath = base_path('docs/qa/UNIVERSAL_DB_SCHEMA_SOURCE_SPEC_ACCEPTANCE_CRITERIA_COVERAGE.md');

        $fullMigrationsTestPath = base_path('tests/Unit/UniversalFullMigrationsDeliverableGapAuditTest.php');
        $seedersTestPath = base_path('tests/Unit/UniversalDemoProjectTypeSeedersDeliverableTest.php');
        $openApiDeliverableTestPath = base_path('tests/Unit/MinimalOpenApiBaseModulesDeliverableTest.php');
        $softDeleteDeliverableTestPath = base_path('tests/Unit/UniversalSoftDeleteStrategyDeliverableTest.php');
        $acceptanceCoverageTestPath = base_path('tests/Unit/UniversalDbSchemaSourceSpecAcceptanceCriteriaCoverageTest.php');
        $partialParitySchemaTestPath = base_path('tests/Feature/Platform/UniversalPartialParityRowsCanonicalMigrationsSchemaTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $udb01DocPath,
            $udb02DocPath,
            $udb03DocPath,
            $udb04DocPath,
            $udb03SyncTestPath,
            $udb04SyncTestPath,
            $projectsCoreColumnsMigrationPath,
            $partialParityMigrationPath,
            $cmsCoreMigrationPath,
            $ecommerceCoreMigrationPath,
            $bookingCoreMigrationPath,
            $servicesNormMigrationPath,
            $tenantScopingFeatureTestPath,
            $tenantIsolationFeatureTestPath,
            $tenantScopingContractTestPath,
            $ecommerceRsReadinessFeatureTestPath,
            $formsLeadsFeatureTestPath,
            $fullMigrationsDocPath,
            $seedersDocPath,
            $openApiReadmePath,
            $softDeleteDocPath,
            $acceptanceDocPath,
            $fullMigrationsTestPath,
            $seedersTestPath,
            $openApiDeliverableTestPath,
            $softDeleteDeliverableTestPath,
            $acceptanceCoverageTestPath,
            $partialParitySchemaTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $projectsCoreColumnsMigration = File::get($projectsCoreColumnsMigrationPath);
        $partialParityMigration = File::get($partialParityMigrationPath);
        $cmsCoreMigration = File::get($cmsCoreMigrationPath);
        $ecommerceCoreMigration = File::get($ecommerceCoreMigrationPath);
        $bookingCoreMigration = File::get($bookingCoreMigrationPath);
        $servicesNormMigration = File::get($servicesNormMigrationPath);

        $tenantScopingFeatureTest = File::get($tenantScopingFeatureTestPath);
        $tenantIsolationFeatureTest = File::get($tenantIsolationFeatureTestPath);
        $tenantScopingContractTest = File::get($tenantScopingContractTestPath);
        $ecommerceRsReadinessFeatureTest = File::get($ecommerceRsReadinessFeatureTestPath);
        $formsLeadsFeatureTest = File::get($formsLeadsFeatureTestPath);

        foreach ([
            '# 16) INDEXES / CONSTRAINTS (must)',
            '- Unique:',
            '(tenant_id, projects.slug)',
            '(project_id, pages.slug)',
            '(project_id, products.slug)',
            '(project_id, posts.slug)',
            '- Index:',
            'all foreign keys',
            '(project_id, status) on content tables',
            '(project_id, starts_at) on bookings',
            '(project_id, created_at) on orders',
            'Enforce tenant/project scoping in all queries.',
            '# 17) Deliverables',
            '1) Full migrations for all tables above',
            '2) Seeders for demo project types:',
            '3) Minimal OpenAPI docs for base modules:',
            '4) Soft delete strategy where needed (optional v1)',
            '# Acceptance Criteria',
            'Can create tenant → create project → publish pages',
            'No cross-tenant data leakage',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        // Backlog closure + evidence/icon notes.
        foreach ([
            '- `UDB-05` (`DONE`, `P0`)',
            'UNIVERSAL_DB_SCHEMA_INDEXES_CONSTRAINTS_DELIVERABLES_ACCEPTANCE_AUDIT_UDB_05_2026_02_25.md',
            'UniversalDbSchemaIndexesConstraintsDeliverablesAcceptanceUdb05SyncTest.php',
            'UNIVERSAL_DB_SCHEMA_SOURCE_SPEC_ACCEPTANCE_CRITERIA_COVERAGE.md',
            'UniversalDbSchemaSourceSpecAcceptanceCriteriaCoverageTest.php',
            '`✅` source `16` index/constraint rules audited with exact/equivalent/partial truth labels',
            '`✅` source `17` deliverables (`#17.1..#17.4`) reconciled to evidence-locked docs/tests',
            '`✅` source acceptance criteria matrix reused via dedicated DB acceptance coverage lock',
            '`✅` non-blocking partials documented as explicit follow-up items (no silent assumptions)',
            '`🧪` targeted UDB-05 evidence sync lock added',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        // Audit doc structure + truth claims.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:6397',
            'PROJECT_ROADMAP_TASKS_KA.md:6436',
            '## ✅ What Was Done (Icon Summary)',
            '## Executive Result (`UDB-05`)',
            '`UDB-05` is **complete as an audit/verification task**',
            '## Indexes / Constraints Rules Audit (Source Section `16`)',
            '## Deliverables Reconciliation (Source Section `17`)',
            '## Acceptance Criteria Reconciliation (Source `Acceptance Criteria`)',
            '## Gaps / Follow-up (Outside `UDB-05` Audit Completion)',
            '## DoD Verdict (`UDB-05`)',
            '## Conclusion',
            'Source section `16` index/constraint rules are **mostly implemented as exact or equivalent runtime rules**',
            'partial` rows',
            'Unique `(tenant_id, projects.slug)`',
            'Unique `(project_id, pages.slug)`',
            'Unique `(project_id, products.slug)`',
            'Unique `(project_id, posts.slug)`',
            'Index all foreign keys',
            'Index `(project_id, status)` on content tables',
            'Index `(project_id, starts_at)` on bookings',
            'Index `(project_id, created_at)` on orders',
            'Enforce tenant/project scoping in all queries',
            '`exact`',
            '`equivalent`',
            '`partial`',
            '#17.1',
            '#17.2',
            '#17.3',
            '#17.4',
            'resolved_to_evidence',
            'UNIVERSAL_DB_SCHEMA_SOURCE_SPEC_ACCEPTANCE_CRITERIA_COVERAGE.md',
            'UniversalDbSchemaSourceSpecAcceptanceCriteriaCoverageTest.php',
            'TenantProjectRouteScopingMiddlewareTest.php',
            'EcommerceRsReadinessTest.php',
            'FormsLeadsModuleApiTest.php',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        // Cross-links to prior UDB audits must be present (UDB-05 is a reconciliation capstone).
        foreach ([
            'UNIVERSAL_DB_SCHEMA_CORE_MULTI_TENANT_PROJECTS_PUBLIC_USERS_AUDIT_UDB_01_2026_02_25.md',
            'UNIVERSAL_DB_SCHEMA_CONTENT_BUILDER_FORMS_NOTIFICATIONS_AUDIT_UDB_02_2026_02_25.md',
            'UNIVERSAL_DB_SCHEMA_PAYMENTS_ECOMMERCE_MODULE_TABLES_AUDIT_UDB_03_2026_02_25.md',
            'UNIVERSAL_DB_SCHEMA_SERVICES_VERTICAL_EXTENSIONS_AUDIT_LOGS_AUDIT_UDB_04_2026_02_25.md',
            'UniversalDbSchemaPaymentsEcommerceModuleTablesUdb03SyncTest.php',
            'UniversalDbSchemaServicesVerticalExtensionsAuditLogsUdb04SyncTest.php',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        // Deliverable evidence refs must be explicit.
        foreach ([
            'UNIVERSAL_FULL_MIGRATIONS_DELIVERABLE_GAP_AUDIT_BASELINE.md',
            'UniversalFullMigrationsDeliverableGapAuditTest.php',
            'UNIVERSAL_DEMO_PROJECT_TYPE_SEEDERS_DELIVERABLE_BASELINE.md',
            'UniversalDemoProjectTypeSeedersDeliverableTest.php',
            'docs/openapi/README.md',
            'MinimalOpenApiBaseModulesDeliverableTest.php',
            'UNIVERSAL_SOFT_DELETE_STRATEGY_OPTIONAL_V1.md',
            'UniversalSoftDeleteStrategyDeliverableTest.php',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        // Migration anchors for source #16 rules.
        foreach ([
            "\$table->uuid('tenant_id')->nullable()->after('id')",
            "\$table->index('tenant_id')",
            "\$table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete()",
        ] as $needle) {
            $this->assertStringContainsString($needle, $projectsCoreColumnsMigration);
        }

        foreach ([
            "Schema::table('projects', function (Blueprint \$table): void {",
            "\$table->unique(['tenant_id', 'slug'])",
            "Schema::table('pages', function (Blueprint \$table): void {",
            "\$table->unique(['project_id', 'slug'])",
            "Schema::create('posts', function (Blueprint \$table): void {",
            "\$table->index(['project_id', 'status', 'published_at'])",
            "\$table->unique(['project_id', 'slug'])",
        ] as $needle) {
            $this->assertStringContainsString($needle, $partialParityMigration);
        }

        foreach ([
            "Schema::create('pages', function (Blueprint \$table) {",
            "\$table->unique(['site_id', 'slug'])",
            "\$table->index(['site_id', 'status'])",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsCoreMigration);
        }

        foreach ([
            "Schema::create('ecommerce_products', function (Blueprint \$table) {",
            "\$table->unique(['site_id', 'slug'])",
            "\$table->index(['site_id', 'status'])",
            "Schema::create('ecommerce_orders', function (Blueprint \$table) {",
            "\$table->unique(['site_id', 'order_number'])",
            "\$table->index(['site_id', 'status'])",
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceCoreMigration);
        }

        foreach ([
            "Schema::create('bookings', function (Blueprint \$table): void {",
            "\$table->index(['site_id', 'service_id', 'starts_at']",
            "\$table->index(['site_id', 'staff_resource_id', 'starts_at']",
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingCoreMigration);
        }

        foreach ([
            "Schema::create('blocked_times', function (Blueprint \$table): void {",
            "\$table->index(['project_id', 'starts_at', 'ends_at'])",
            "\$table->index(['project_id', 'status'])", // present elsewhere in file and used for content/status rule examples
        ] as $needle) {
            $this->assertStringContainsString($needle, $servicesNormMigration);
        }

        // Scoping enforcement anchors referenced by source #16.
        $this->assertStringContainsString('tenant_scope_route_binding_mismatch', $tenantScopingFeatureTest);
        $this->assertStringContainsString('route_model_site_scope_mismatch', $tenantScopingFeatureTest);
        $this->assertStringContainsString('tenant_scope_route_binding_mismatch', $formsLeadsFeatureTest);
        $this->assertStringContainsString('tenant.route.scope', $tenantScopingContractTest);
        $this->assertStringContainsString('class TenantIsolationTest', $tenantIsolationFeatureTest);
        $this->assertStringContainsString('test_rs_endpoints_enforce_tenant_access_and_site_scope', $ecommerceRsReadinessFeatureTest);
    }
}
