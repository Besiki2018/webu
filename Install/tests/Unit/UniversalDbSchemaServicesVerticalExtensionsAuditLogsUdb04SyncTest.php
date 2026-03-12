<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalDbSchemaServicesVerticalExtensionsAuditLogsUdb04SyncTest extends TestCase
{
    public function test_udb_04_audit_doc_locks_services_vertical_extensions_and_audit_logs_coverage_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_DB_SCHEMA_SERVICES_VERTICAL_EXTENSIONS_AUDIT_LOGS_AUDIT_UDB_04_2026_02_25.md');

        $servicesMigrationPath = base_path('database/migrations/2026_02_24_240000_create_universal_services_normalization_tables.php');
        $verticalsMigrationPath = base_path('database/migrations/2026_02_24_241000_create_universal_vertical_modules_normalization_tables.php');
        $bookingCoreMigrationPath = base_path('database/migrations/2026_02_20_098000_create_booking_core_tables.php');
        $bookingFinanceMigrationPath = base_path('database/migrations/2026_02_20_150000_create_booking_finance_tables.php');
        $auditLogsMigrationPath = base_path('database/migrations/2026_01_19_100004_create_audit_logs_table.php');

        $servicesSchemaTestPath = base_path('tests/Feature/Platform/UniversalServicesNormalizationTablesSchemaTest.php');
        $verticalsSchemaTestPath = base_path('tests/Feature/Platform/UniversalVerticalModulesNormalizationTablesSchemaTest.php');
        $fullGapAuditTestPath = base_path('tests/Unit/UniversalFullMigrationsDeliverableGapAuditTest.php');
        $fullGapAuditDocPath = base_path('docs/qa/UNIVERSAL_FULL_MIGRATIONS_DELIVERABLE_GAP_AUDIT_BASELINE.md');

        $servicesBookingContractTestPath = base_path('tests/Unit/UniversalServicesBookingContractsP5F3Test.php');
        $portfolioModuleContractTestPath = base_path('tests/Unit/UniversalPortfolioModuleComponentsP5F4Test.php');
        $realEstateModuleContractTestPath = base_path('tests/Unit/UniversalRealEstateModuleComponentsP5F4Test.php');
        $restaurantModuleContractTestPath = base_path('tests/Unit/UniversalRestaurantModuleComponentsP5F4Test.php');
        $hotelModuleContractTestPath = base_path('tests/Unit/UniversalHotelModuleComponentsP5F4Test.php');

        $bookingPublicFeatureTestPath = base_path('tests/Feature/Booking/BookingPublicApiTest.php');
        $bookingPanelFeatureTestPath = base_path('tests/Feature/Booking/BookingPanelCrudTest.php');
        $bookingAcceptanceFeatureTestPath = base_path('tests/Feature/Booking/BookingAcceptanceTest.php');
        $bookingAdvancedAcceptanceFeatureTestPath = base_path('tests/Feature/Booking/BookingAdvancedAcceptanceTest.php');
        $bookingFinanceFeatureTestPath = base_path('tests/Feature/Booking/BookingFinanceLedgerTest.php');

        $auditLogModelPath = base_path('app/Models/AuditLog.php');
        $auditLogServicePath = base_path('app/Services/AuditLogService.php');
        $rolloutControlServicePath = base_path('app/Services/CmsAiGenerationRolloutControlService.php');
        $rolloutControlFeatureTestPath = base_path('tests/Feature/Cms/CmsAiGenerationRolloutControlServiceTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $servicesMigrationPath,
            $verticalsMigrationPath,
            $bookingCoreMigrationPath,
            $bookingFinanceMigrationPath,
            $auditLogsMigrationPath,
            $servicesSchemaTestPath,
            $verticalsSchemaTestPath,
            $fullGapAuditTestPath,
            $fullGapAuditDocPath,
            $servicesBookingContractTestPath,
            $portfolioModuleContractTestPath,
            $realEstateModuleContractTestPath,
            $restaurantModuleContractTestPath,
            $hotelModuleContractTestPath,
            $bookingPublicFeatureTestPath,
            $bookingPanelFeatureTestPath,
            $bookingAcceptanceFeatureTestPath,
            $bookingAdvancedAcceptanceFeatureTestPath,
            $bookingFinanceFeatureTestPath,
            $auditLogModelPath,
            $auditLogServicePath,
            $rolloutControlServicePath,
            $rolloutControlFeatureTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $servicesMigration = File::get($servicesMigrationPath);
        $verticalsMigration = File::get($verticalsMigrationPath);
        $bookingCoreMigration = File::get($bookingCoreMigrationPath);
        $bookingFinanceMigration = File::get($bookingFinanceMigrationPath);
        $auditLogsMigration = File::get($auditLogsMigrationPath);

        $servicesSchemaTest = File::get($servicesSchemaTestPath);
        $verticalsSchemaTest = File::get($verticalsSchemaTestPath);
        $fullGapAuditTest = File::get($fullGapAuditTestPath);
        $fullGapAuditDoc = File::get($fullGapAuditDocPath);
        $servicesBookingContractTest = File::get($servicesBookingContractTestPath);
        $portfolioModuleContractTest = File::get($portfolioModuleContractTestPath);
        $realEstateModuleContractTest = File::get($realEstateModuleContractTestPath);
        $restaurantModuleContractTest = File::get($restaurantModuleContractTestPath);
        $hotelModuleContractTest = File::get($hotelModuleContractTestPath);

        $bookingPublicFeatureTest = File::get($bookingPublicFeatureTestPath);
        $bookingPanelFeatureTest = File::get($bookingPanelFeatureTestPath);
        $bookingAcceptanceFeatureTest = File::get($bookingAcceptanceFeatureTestPath);
        $bookingAdvancedAcceptanceFeatureTest = File::get($bookingAdvancedAcceptanceFeatureTestPath);
        $bookingFinanceFeatureTest = File::get($bookingFinanceFeatureTestPath);

        $auditLogModel = File::get($auditLogModelPath);
        $auditLogService = File::get($auditLogServicePath);
        $rolloutControlService = File::get($rolloutControlServicePath);
        $rolloutControlFeatureTest = File::get($rolloutControlFeatureTestPath);

        foreach ([
            '# 10) SERVICES MODULE',
            '## services',
            '## service_categories',
            '## staff',
            '## staff_services',
            '## resources',
            '## availability_rules',
            '## blocked_times',
            '## bookings',
            '## booking_payments',
            '## booking_events',
            '# 11) HOTEL MODULE',
            '## rooms',
            '## room_images',
            '## room_reservations',
            '# 12) RESTAURANT MODULE',
            '## restaurant_menu_categories',
            '## restaurant_menu_items',
            '## table_reservations',
            '# 13) PORTFOLIO MODULE',
            '## portfolio_items',
            '## portfolio_images',
            '# 14) REAL ESTATE MODULE',
            '## properties',
            '## property_images',
            '# 15) AUDIT LOGS (critical)',
            '## audit_logs',
            'tenant_id',
            'project_id (nullable)',
            'actor_type (tenant_user/customer/system)',
            'before_json (nullable)',
            'after_json (nullable)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        // Backlog closure + icon notes.
        $this->assertStringContainsString('- `UDB-04` (`DONE`, `P0`)', $backlog);
        $this->assertStringContainsString('UNIVERSAL_DB_SCHEMA_SERVICES_VERTICAL_EXTENSIONS_AUDIT_LOGS_AUDIT_UDB_04_2026_02_25.md', $backlog);
        $this->assertStringContainsString('UniversalDbSchemaServicesVerticalExtensionsAuditLogsUdb04SyncTest.php', $backlog);
        $this->assertStringContainsString('`✅` services + booking + hotel/restaurant/portfolio/real-estate schema rows mapped to exact/equivalent runtime tables', $backlog);
        $this->assertStringContainsString('`✅` vertical extension coverage reconciled with normalization schema tests and builder/module contracts', $backlog);
        $this->assertStringContainsString('`✅` audit_logs criticality verified with schema/index evidence and runtime write-path usage (truthful field-drift notes included)', $backlog);
        $this->assertStringContainsString('`🧪` targeted schema + booking/vertical/audit-log evidence batch passed', $backlog);

        // Doc structure and truth claims.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:6156',
            'PROJECT_ROADMAP_TASKS_KA.md:6396',
            '## ✅ What Was Done (Icon Summary)',
            '## Executive Result (`UDB-04`)',
            '`UDB-04` is **complete as an audit task**',
            '## Vertical Extension Schema Parity Matrix (Services + Booking + Modules)',
            '### Source Section `10` — Services Module',
            '### Source Sections `11..14` — Hotel / Restaurant / Portfolio / Real Estate',
            '## Audit Logs Criticality Requirement Verification (Source Section `15`)',
            '## Coverage Status Summary (UDB-04 DoD)',
            '## Gaps / Follow-up (Outside UDB-04 Audit Completion)',
            '## Conclusion',
            'implemented_exact_db_first_with_booking_runtime_additive',
            'implemented_equivalent_runtime_active',
            'implemented_equivalent_runtime_active_module_specific',
            'implemented_exact_table_runtime_active_partial_contract_fields',
            'booking_payments',
            'booking_events',
            'audit_logs',
            'tenant/project/actor_type/before_json/after_json',
            'critical audit log infrastructure exists and is used in production features',
            'field naming/scoping contract differs from source canonical multi-tenant/project audit row',
            'no dedicated platform schema test',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'Install/database/migrations/2026_02_24_240000_create_universal_services_normalization_tables.php',
            'Install/database/migrations/2026_02_24_241000_create_universal_vertical_modules_normalization_tables.php',
            'Install/database/migrations/2026_02_20_098000_create_booking_core_tables.php',
            'Install/database/migrations/2026_02_20_150000_create_booking_finance_tables.php',
            'Install/database/migrations/2026_01_19_100004_create_audit_logs_table.php',
            'Install/tests/Feature/Platform/UniversalServicesNormalizationTablesSchemaTest.php',
            'Install/tests/Feature/Platform/UniversalVerticalModulesNormalizationTablesSchemaTest.php',
            'Install/tests/Unit/UniversalFullMigrationsDeliverableGapAuditTest.php',
            'Install/tests/Unit/UniversalServicesBookingContractsP5F3Test.php',
            'Install/tests/Feature/Booking/BookingPublicApiTest.php',
            'Install/tests/Feature/Booking/BookingPanelCrudTest.php',
            'Install/tests/Feature/Booking/BookingAcceptanceTest.php',
            'Install/tests/Feature/Booking/BookingAdvancedAcceptanceTest.php',
            'Install/tests/Feature/Booking/BookingFinanceLedgerTest.php',
            'Install/tests/Unit/UniversalPortfolioModuleComponentsP5F4Test.php',
            'Install/tests/Unit/UniversalRealEstateModuleComponentsP5F4Test.php',
            'Install/tests/Unit/UniversalRestaurantModuleComponentsP5F4Test.php',
            'Install/tests/Unit/UniversalHotelModuleComponentsP5F4Test.php',
            'Install/app/Models/AuditLog.php',
            'Install/app/Services/AuditLogService.php',
            'Install/app/Services/CmsAiGenerationRolloutControlService.php',
            'Install/tests/Feature/Cms/CmsAiGenerationRolloutControlServiceTest.php',
        ] as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc);
            $this->assertFileExists(base_path('../'.$relativePath));
        }

        // Services normalization migration anchors.
        foreach ([
            "Schema::create('service_categories'",
            "Schema::create('services'",
            "Schema::create('staff'",
            "Schema::create('staff_services'",
            "Schema::create('resources'",
            "Schema::create('availability_rules'",
            "Schema::create('blocked_times'",
            "\$table->unique(['project_id', 'slug'])",
            "\$table->index(['project_id', 'status'])",
            "\$table->index(['project_id', 'owner_type', 'owner_id'])",
            "\$table->index(['project_id', 'starts_at', 'ends_at'])",
            "\$table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete()",
        ] as $needle) {
            $this->assertStringContainsString($needle, $servicesMigration);
        }

        // Vertical extensions migration anchors.
        foreach ([
            "Schema::create('rooms'",
            "Schema::create('room_images'",
            "Schema::create('room_reservations'",
            "Schema::create('restaurant_menu_categories'",
            "Schema::create('restaurant_menu_items'",
            "Schema::create('table_reservations'",
            "Schema::create('portfolio_items'",
            "Schema::create('portfolio_images'",
            "Schema::create('properties'",
            "Schema::create('property_images'",
            "\$table->unique(['project_id', 'slug'])",
            "\$table->index(['project_id', 'price'])",
            "\$table->index(['project_id', 'status', 'starts_at'])",
        ] as $needle) {
            $this->assertStringContainsString($needle, $verticalsMigration);
        }

        // Booking core/finance schema anchors (equivalent runtime rows).
        foreach ([
            "Schema::create('bookings'",
            "Schema::create('booking_events'",
            "\$table->unique(['site_id', 'booking_number']",
            "\$table->index(['site_id', 'status']",
            "\$table->index(['site_id', 'service_id', 'starts_at']",
            "\$table->index(['site_id', 'collision_starts_at', 'collision_ends_at']",
            "\$table->string('status', 30)->default('pending')",
            "\$table->string('event_type', 60)",
            "\$table->json('payload_json')->nullable()",
            "\$table->index(['site_id', 'event_type']",
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingCoreMigration);
        }

        foreach ([
            "Schema::create('booking_payments'",
            "\$table->string('provider', 60)->default('manual')",
            "\$table->string('status', 30)->default('paid')",
            "\$table->string('transaction_reference', 190)->nullable()",
            "\$table->boolean('is_prepayment')->default(false)",
            "\$table->json('raw_payload_json')->nullable()",
            "\$table->index(['site_id', 'booking_id'])",
            "\$table->index(['site_id', 'provider', 'status'])",
            "\$table->foreign('booking_id')->references('id')->on('bookings')->onDelete('cascade')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingFinanceMigration);
        }

        // Audit logs schema + runtime anchors.
        foreach ([
            "Schema::create('audit_logs'",
            "\$table->foreignId('user_id')->nullable()->constrained()->onDelete('set null')",
            "\$table->foreignId('actor_id')->nullable()->constrained('users')->onDelete('set null')",
            "\$table->json('old_values')->nullable()",
            "\$table->json('new_values')->nullable()",
            "\$table->json('metadata')->nullable()",
            "\$table->index(['user_id', 'action'])",
            "\$table->index(['actor_id', 'action'])",
            "\$table->index(['entity_type', 'entity_id'])",
            "\$table->index('created_at')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $auditLogsMigration);
        }
        $this->assertStringNotContainsString('tenant_id', $auditLogsMigration);
        $this->assertStringNotContainsString('project_id', $auditLogsMigration);
        $this->assertStringNotContainsString('actor_type', $auditLogsMigration);

        foreach ([
            "'user_id'",
            "'actor_id'",
            "'old_values'",
            "'new_values'",
            "'metadata'",
            'public static function log(',
        ] as $needle) {
            $this->assertStringContainsString($needle, $auditLogModel.$auditLogService);
        }
        $this->assertStringContainsString('AUDIT_ACTION', $rolloutControlService);
        $this->assertStringContainsString('AuditLog::log(', $rolloutControlService);
        $this->assertStringContainsString("assertDatabaseHas('audit_logs'", $rolloutControlFeatureTest);
        $this->assertStringContainsString('CmsAiGenerationRolloutControlService::AUDIT_ACTION', $rolloutControlFeatureTest);

        // Schema and gap-audit row anchors.
        foreach ([
            'services',
            'service_categories',
            'staff_services',
            'availability_rules',
            'blocked_times',
        ] as $needle) {
            $this->assertStringContainsString($needle, $servicesSchemaTest);
        }

        foreach ([
            'rooms',
            'restaurant_menu_categories',
            'portfolio_items',
            'properties',
            'property_images',
        ] as $needle) {
            $this->assertStringContainsString($needle, $verticalsSchemaTest);
        }

        foreach ([
            '| services | exact | `services` |',
            '| service_categories | exact | `service_categories` |',
            '| staff | exact | `staff` |',
            '| staff_services | exact | `staff_services` |',
            '| resources | exact | `resources` |',
            '| availability_rules | exact | `availability_rules` |',
            '| blocked_times | exact | `blocked_times` |',
            '| bookings | equivalent | `bookings` |',
            '| booking_payments | equivalent | `booking_payments` |',
            '| booking_events | equivalent | `booking_events` |',
            '| rooms | exact | `rooms` |',
            '| restaurant_menu_items | exact | `restaurant_menu_items` |',
            '| portfolio_items | exact | `portfolio_items` |',
            '| properties | exact | `properties` |',
            '| audit_logs | exact | `audit_logs` |',
        ] as $needle) {
            $this->assertStringContainsString($needle, $fullGapAuditDoc);
        }
        $this->assertStringContainsString("\$rowsByTable['audit_logs']", $fullGapAuditTest);
        $this->assertStringContainsString("\$rowsByTable['services']", $fullGapAuditTest);

        // Booking + vertical runtime/contract evidence anchors.
        $this->assertStringContainsString("name('panel.sites.booking.finance.payments.store')", $servicesBookingContractTest);
        $this->assertStringContainsString("name('public.sites.booking.bookings.store')", $servicesBookingContractTest);
        $this->assertStringContainsString('normalizeBookingPayment(', $servicesBookingContractTest);
        $this->assertStringContainsString('test_owner_can_manage_booking_services_staff_and_bookings', $servicesBookingContractTest);

        $this->assertStringContainsString("assertDatabaseHas('bookings'", $bookingPublicFeatureTest);
        $this->assertStringContainsString("assertJsonPath('booking.prepayment.status', 'paid')", $bookingPublicFeatureTest);
        $this->assertStringContainsString("assertContains('created', \$eventTypes)", $bookingAcceptanceFeatureTest);
        $this->assertStringContainsString("assertContains('status_updated', \$eventTypes)", $bookingAcceptanceFeatureTest);
        $this->assertStringContainsString("assertContains('rescheduled', \$eventTypes)", $bookingAcceptanceFeatureTest);
        $this->assertStringContainsString("->json('payment.id')", $bookingAdvancedAcceptanceFeatureTest);
        $this->assertStringContainsString("assertJsonPath('payment.booking_id', \$booking->id)", $bookingFinanceFeatureTest);
        $this->assertStringContainsString("assertJsonPath('payment.amount', '50.00')", $bookingFinanceFeatureTest);
        $this->assertStringContainsString("assertDatabaseHas('bookings'", $bookingFinanceFeatureTest);
        $this->assertStringContainsString('test_owner_can_manage_booking_services_staff_and_bookings', $bookingPanelFeatureTest);

        $this->assertStringContainsString('MODULE_PORTFOLIO', $portfolioModuleContractTest);
        $this->assertStringContainsString('MODULE_REAL_ESTATE', $realEstateModuleContractTest);
        $this->assertStringContainsString('MODULE_RESTAURANT', $restaurantModuleContractTest);
        $this->assertStringContainsString('MODULE_HOTEL', $hotelModuleContractTest);
    }
}
