<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalServicesNormalizationTablesMigrationsDeliverableProgressTest extends TestCase
{
    public function test_services_normalization_migrations_batch_progress_is_documented_and_reflected_in_gap_audit(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $migrationPath = base_path('database/migrations/2026_02_24_240000_create_universal_services_normalization_tables.php');
        $docPath = base_path('docs/qa/UNIVERSAL_SERVICES_NORMALIZATION_TABLES_MIGRATIONS_DELIVERABLE_PROGRESS.md');
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

        foreach ([
            "Schema::create('service_categories'",
            "Schema::create('services'",
            "Schema::create('staff'",
            "Schema::create('staff_services'",
            "Schema::create('resources'",
            "Schema::create('availability_rules'",
            "Schema::create('blocked_times'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }

        foreach ([
            "\$table->longText('description_html')->nullable()",
            "\$table->foreign('service_id')->references('id')->on('services')",
            "\$table->foreign('photo_media_id')->references('id')->on('media')",
            "\$table->string('rrule')",
            "\$table->timestamp('starts_at')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }

        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:6415', $doc);
        $this->assertStringContainsString('2026_02_24_240000_create_universal_services_normalization_tables.php', $doc);
        $this->assertStringContainsString('UniversalServicesNormalizationTablesSchemaTest.php', $doc);
        $this->assertStringContainsString('UniversalServicesNormalizationTablesMigrationsDeliverableProgressTest.php', $doc);
        $this->assertStringContainsString('still NOT COMPLETE', $doc);

        foreach ([
            '| services | exact | `services` |',
            '| service_categories | exact | `service_categories` |',
            '| staff | exact | `staff` |',
            '| staff_services | exact | `staff_services` |',
            '| resources | exact | `resources` |',
            '| availability_rules | exact | `availability_rules` |',
            '| blocked_times | exact | `blocked_times` |',
        ] as $needle) {
            $this->assertStringContainsString($needle, $gapAudit);
        }

        $this->assertStringContainsString('- `exact`: `50`', $gapAudit);
        $this->assertStringContainsString('- `equivalent`: `19`', $gapAudit);
        $this->assertStringContainsString('- `partial`: `0`', $gapAudit);
        $this->assertStringContainsString('- `missing`: `0`', $gapAudit);
    }
}
