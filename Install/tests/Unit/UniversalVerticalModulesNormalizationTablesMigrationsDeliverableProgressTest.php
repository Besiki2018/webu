<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalVerticalModulesNormalizationTablesMigrationsDeliverableProgressTest extends TestCase
{
    public function test_vertical_modules_normalization_migrations_batch_progress_is_documented_and_reflected_in_gap_audit(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $migrationPath = base_path('database/migrations/2026_02_24_241000_create_universal_vertical_modules_normalization_tables.php');
        $docPath = base_path('docs/qa/UNIVERSAL_VERTICAL_MODULES_NORMALIZATION_TABLES_MIGRATIONS_DELIVERABLE_PROGRESS.md');
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
        ] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }

        foreach ([
            "\$table->decimal('price_per_night', 12, 2)",
            "\$table->foreign('room_id')->references('id')->on('rooms')",
            "\$table->foreign('category_id')->references('id')->on('restaurant_menu_categories')",
            "\$table->foreign('portfolio_item_id')->references('id')->on('portfolio_items')",
            "\$table->decimal('lat', 10, 7)->nullable()",
            "\$table->foreign('property_id')->references('id')->on('properties')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }

        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:6415', $doc);
        $this->assertStringContainsString('2026_02_24_241000_create_universal_vertical_modules_normalization_tables.php', $doc);
        $this->assertStringContainsString('UniversalVerticalModulesNormalizationTablesSchemaTest.php', $doc);
        $this->assertStringContainsString('UniversalVerticalModulesNormalizationTablesMigrationsDeliverableProgressTest.php', $doc);
        $this->assertStringContainsString('still NOT COMPLETE', $doc);

        foreach ([
            '| rooms | exact | `rooms` |',
            '| room_images | exact | `room_images` |',
            '| room_reservations | exact | `room_reservations` |',
            '| restaurant_menu_categories | exact | `restaurant_menu_categories` |',
            '| restaurant_menu_items | exact | `restaurant_menu_items` |',
            '| table_reservations | exact | `table_reservations` |',
            '| portfolio_items | exact | `portfolio_items` |',
            '| portfolio_images | exact | `portfolio_images` |',
            '| properties | exact | `properties` |',
            '| property_images | exact | `property_images` |',
        ] as $needle) {
            $this->assertStringContainsString($needle, $gapAudit);
        }

        $this->assertStringContainsString('- `exact`: `50`', $gapAudit);
        $this->assertStringContainsString('- `equivalent`: `19`', $gapAudit);
        $this->assertStringContainsString('- `partial`: `0`', $gapAudit);
        $this->assertStringContainsString('- `missing`: `0`', $gapAudit);
    }
}
