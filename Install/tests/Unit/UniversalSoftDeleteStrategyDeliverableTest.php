<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalSoftDeleteStrategyDeliverableTest extends TestCase
{
    public function test_optional_soft_delete_strategy_deliverable_is_documented_with_policy_matrix_and_current_code_evidence(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_SOFT_DELETE_STRATEGY_OPTIONAL_V1.md');
        $projectModelPath = base_path('app/Models/Project.php');
        $ecommerceProductModelPath = base_path('app/Models/EcommerceProduct.php');
        $projectsMigrationPath = base_path('database/migrations/2026_01_22_000000_convert_projects_to_uuid.php');
        $ecommerceCoreMigrationPath = base_path('database/migrations/2026_02_20_070000_create_ecommerce_core_tables.php');

        foreach ([$roadmapPath, $docPath, $projectModelPath, $ecommerceProductModelPath, $projectsMigrationPath, $ecommerceCoreMigrationPath] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $doc = File::get($docPath);
        $projectModel = File::get($projectModelPath);
        $ecommerceProductModel = File::get($ecommerceProductModelPath);
        $projectsMigration = File::get($projectsMigrationPath);
        $ecommerceCoreMigration = File::get($ecommerceCoreMigrationPath);

        $this->assertStringContainsString('# 17) Deliverables', $roadmap);
        $this->assertStringContainsString('4) Soft delete strategy where needed (optional v1)', $roadmap);

        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:6425', $doc);
        $this->assertStringContainsString('strategy/policy artifact', $doc);
        $this->assertStringContainsString('optional v1', $doc);
        $this->assertStringContainsString('Current Code Evidence (already implemented)', $doc);
        $this->assertStringContainsString('Canonical Strategy Matrix (v1)', $doc);
        $this->assertStringContainsString('Prefer Soft Delete', $doc);
        $this->assertStringContainsString('Prefer Hard Delete', $doc);
        $this->assertStringContainsString('Prefer Anonymize + Retain', $doc);
        $this->assertStringContainsString('API / Query Contract Rules', $doc);
        $this->assertStringContainsString('Migration Rollout Guidance (incremental)', $doc);
        $this->assertStringContainsString('`#17.4 Soft delete strategy where needed (optional v1)` is **COMPLETE**', $doc);

        foreach ([
            '`projects`',
            '`pages`',
            '`posts`',
            '`products`',
            '`services`',
            '`staff`',
            '`rooms`',
            '`portfolio_items`',
            '`properties`',
            '`orders`',
            '`payments`',
            '`bookings`',
            '`audit_logs`',
            '`customer_sessions`',
            '`otp_requests`',
            '`product_category_relations`',
            '`order_addresses`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        $this->assertStringContainsString('use HasFactory, HasUuids, SoftDeletes;', $projectModel);
        $this->assertStringContainsString('use HasFactory, SoftDeletes;', $ecommerceProductModel);
        $this->assertStringContainsString('softDeletes()', $projectsMigration);
        $this->assertStringContainsString('softDeletes()', $ecommerceCoreMigration);
    }
}
