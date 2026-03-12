<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalContentNormalizationTablesMigrationsDeliverableProgressTest extends TestCase
{
    public function test_content_normalization_migrations_batch_progress_is_documented_and_reflected_in_gap_audit(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $migrationPath = base_path('database/migrations/2026_02_24_238000_create_content_normalization_tables.php');
        $docPath = base_path('docs/qa/UNIVERSAL_CONTENT_NORMALIZATION_TABLES_MIGRATIONS_DELIVERABLE_PROGRESS.md');
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
            "Schema::create('menu_items'",
            "Schema::create('post_categories'",
            "Schema::create('post_category_relations'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }

        $this->assertStringContainsString("\$table->foreignId('menu_id')->constrained('menus')", $migration);
        $this->assertStringContainsString("\$table->uuid('tenant_id')", $migration);
        $this->assertStringContainsString("\$table->uuid('project_id')", $migration);
        $this->assertStringContainsString("\$table->primary(['post_id', 'category_id'])", $migration);
        $this->assertStringContainsString("references('id')->on('blog_posts')", $migration);

        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:6415', $doc);
        $this->assertStringContainsString('2026_02_24_238000_create_content_normalization_tables.php', $doc);
        $this->assertStringContainsString('UniversalContentNormalizationTablesSchemaTest.php', $doc);
        $this->assertStringContainsString('UniversalContentNormalizationTablesMigrationsDeliverableProgressTest.php', $doc);
        $this->assertStringContainsString('still NOT COMPLETE', $doc);

        foreach ([
            '| menu_items | exact | `menu_items` |',
            '| post_categories | exact | `post_categories` |',
            '| post_category_relations | exact | `post_category_relations` |',
        ] as $needle) {
            $this->assertStringContainsString($needle, $gapAudit);
        }

        $this->assertStringContainsString('- `exact`: `50`', $gapAudit);
        $this->assertStringContainsString('- `equivalent`: `19`', $gapAudit);
        $this->assertStringContainsString('- `partial`: `0`', $gapAudit);
        $this->assertStringContainsString('- `missing`: `0`', $gapAudit);
    }
}
