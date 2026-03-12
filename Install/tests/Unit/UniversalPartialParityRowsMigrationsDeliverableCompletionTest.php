<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalPartialParityRowsMigrationsDeliverableCompletionTest extends TestCase
{
    public function test_partial_parity_rows_completion_batch_is_documented_and_gap_audit_reports_zero_partial_and_missing(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $migrationPath = base_path('database/migrations/2026_02_24_242000_add_canonical_parity_columns_and_tables_for_partial_rows.php');
        $docPath = base_path('docs/qa/UNIVERSAL_PARTIAL_PARITY_ROWS_MIGRATIONS_DELIVERABLE_COMPLETION.md');
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
            "Schema::table('tenants'",
            "Schema::table('tenant_users'",
            "Schema::table('projects'",
            "Schema::table('pages'",
            "Schema::table('page_revisions'",
            "Schema::table('menus'",
            "Schema::table('media'",
            "Schema::create('posts'",
            "Schema::create('project_settings'",
            "Schema::create('feature_flags'",
            "Schema::create('leads'",
            "Schema::create('product_category_relations'",
            "Schema::create('order_addresses'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }

        foreach ([
            "\$table->string('password_hash')->nullable()",
            "\$table->json('page_json')->nullable()",
            "\$table->longText('page_css')->nullable()",
            "\$table->string('mime_type')->nullable()",
            "\$table->json('value_json')",
            "\$table->json('rules_json')->nullable()",
            "\$table->foreign('order_id')->references('id')->on('ecommerce_orders')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }

        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:6415', $doc);
        $this->assertStringContainsString('2026_02_24_242000_add_canonical_parity_columns_and_tables_for_partial_rows.php', $doc);
        $this->assertStringContainsString('UniversalPartialParityRowsCanonicalMigrationsSchemaTest.php', $doc);
        $this->assertStringContainsString('UniversalPartialParityRowsMigrationsDeliverableCompletionTest.php', $doc);
        $this->assertStringContainsString('**COMPLETE**', $doc);

        foreach ([
            '| tenants | exact | `tenants` |',
            '| tenant_users | equivalent | `tenant_users` + `users` |',
            '| projects | exact | `projects` |',
            '| pages | exact | `pages` |',
            '| page_revisions | exact | `page_revisions` |',
            '| menus | exact | `menus` + `menu_items` |',
            '| posts | exact | `posts` (+ `blog_posts`) |',
            '| media | exact | `media` |',
            '| project_settings | exact | `project_settings` (+ legacy settings stores) |',
            '| feature_flags | exact | `feature_flags` (+ legacy flags in settings) |',
            '| leads | exact | `leads` (+ `site_form_leads`) |',
            '| product_category_relations | exact | `product_category_relations` (+ `ecommerce_products.category_id`) |',
            '| order_addresses | exact | `order_addresses` (+ `ecommerce_orders.*_address_json`) |',
        ] as $needle) {
            $this->assertStringContainsString($needle, $gapAudit);
        }

        $this->assertStringContainsString('- `exact`: `50`', $gapAudit);
        $this->assertStringContainsString('- `equivalent`: `19`', $gapAudit);
        $this->assertStringContainsString('- `partial`: `0`', $gapAudit);
        $this->assertStringContainsString('- `missing`: `0`', $gapAudit);
        $this->assertStringContainsString('**COMPLETE**', $gapAudit);
    }
}
