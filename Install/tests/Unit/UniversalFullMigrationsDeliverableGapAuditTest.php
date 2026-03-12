<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalFullMigrationsDeliverableGapAuditTest extends TestCase
{
    public function test_full_migrations_deliverable_has_explicit_gap_audit_matrix_covering_all_source_spec_tables(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_FULL_MIGRATIONS_DELIVERABLE_GAP_AUDIT_BASELINE.md');

        foreach ([$roadmapPath, $docPath] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $doc = File::get($docPath);

        // Source-spec deliverable references.
        $this->assertStringContainsString('# 17) Deliverables', $roadmap);
        $this->assertStringContainsString('1) Full migrations for all tables above', $roadmap);
        $this->assertStringContainsString('Source-spec reference: `PROJECT_ROADMAP_TASKS_KA.md:6415`', $doc);
        $this->assertStringContainsString('**COMPLETE**', $doc);

        $sourceTables = $this->parseSourceSpecTablesFromRoadmap($roadmap);
        $matrix = $this->parseAuditMatrix($doc);
        $matrixTables = array_column($matrix, 'table');

        $this->assertCount(69, $sourceTables, 'Unexpected source-spec table count in #0..#15 section');
        $this->assertSame($sourceTables, $matrixTables, 'Gap-audit matrix must cover source-spec tables in the same order');

        $statusCounts = ['exact' => 0, 'equivalent' => 0, 'partial' => 0, 'missing' => 0];
        $rowsByTable = [];

        foreach ($matrix as $row) {
            $statusCounts[$row['status']]++;
            $rowsByTable[$row['table']] = $row;
        }

        $this->assertSame([
            'exact' => 50,
            'equivalent' => 19,
            'partial' => 0,
            'missing' => 0,
        ], $statusCounts);

        // Summary counts in doc must match parsed matrix rows.
        $this->assertStringContainsString('Coverage Matrix (69 source-spec tables)', $doc);
        $this->assertStringContainsString('- Total source-spec tables audited: `69`', $doc);
        $this->assertStringContainsString('- `exact`: `50`', $doc);
        $this->assertStringContainsString('- `equivalent`: `19`', $doc);
        $this->assertStringContainsString('- `partial`: `0`', $doc);
        $this->assertStringContainsString('- `missing`: `0`', $doc);

        // Representative row classifications (sanity checks across modules).
        $this->assertSame('exact', $rowsByTable['projects']['status']);
        $this->assertStringContainsString('`projects`', $rowsByTable['projects']['current']);
        $this->assertSame('exact', $rowsByTable['tenants']['status']);
        $this->assertSame('equivalent', $rowsByTable['tenant_users']['status']);

        $this->assertSame('exact', $rowsByTable['customers']['status']);
        $this->assertStringContainsString('`customers`', $rowsByTable['customers']['current']);
        $this->assertSame('exact', $rowsByTable['customer_sessions']['status']);
        $this->assertSame('exact', $rowsByTable['customer_addresses']['status']);
        $this->assertSame('exact', $rowsByTable['otp_requests']['status']);
        $this->assertSame('exact', $rowsByTable['social_accounts']['status']);
        $this->assertSame('exact', $rowsByTable['menu_items']['status']);
        $this->assertSame('exact', $rowsByTable['post_categories']['status']);
        $this->assertSame('exact', $rowsByTable['post_category_relations']['status']);
        $this->assertSame('exact', $rowsByTable['payment_methods']['status']);
        $this->assertSame('exact', $rowsByTable['payments']['status']);
        $this->assertSame('exact', $rowsByTable['payment_webhooks']['status']);
        $this->assertSame('exact', $rowsByTable['shipping_methods']['status']);
        $this->assertSame('exact', $rowsByTable['shipping_zones']['status']);
        $this->assertSame('exact', $rowsByTable['shipping_zone_regions']['status']);
        $this->assertSame('exact', $rowsByTable['shipping_rates']['status']);
        $this->assertSame('exact', $rowsByTable['coupons']['status']);
        $this->assertSame('exact', $rowsByTable['coupon_redemptions']['status']);
        $this->assertSame('exact', $rowsByTable['rooms']['status']);
        $this->assertSame('exact', $rowsByTable['room_images']['status']);
        $this->assertSame('exact', $rowsByTable['room_reservations']['status']);
        $this->assertSame('exact', $rowsByTable['restaurant_menu_categories']['status']);
        $this->assertSame('exact', $rowsByTable['restaurant_menu_items']['status']);
        $this->assertSame('exact', $rowsByTable['table_reservations']['status']);
        $this->assertSame('exact', $rowsByTable['portfolio_items']['status']);
        $this->assertSame('exact', $rowsByTable['portfolio_images']['status']);
        $this->assertSame('exact', $rowsByTable['properties']['status']);
        $this->assertSame('exact', $rowsByTable['property_images']['status']);

        $this->assertSame('equivalent', $rowsByTable['forms']['status']);
        $this->assertStringContainsString('`site_forms`', $rowsByTable['forms']['current']);
        $this->assertSame('equivalent', $rowsByTable['notification_templates']['status']);
        $this->assertStringContainsString('`site_notification_templates`', $rowsByTable['notification_templates']['current']);
        $this->assertSame('equivalent', $rowsByTable['products']['status']);
        $this->assertStringContainsString('`ecommerce_products`', $rowsByTable['products']['current']);
        $this->assertSame('exact', $rowsByTable['posts']['status']);
        $this->assertStringContainsString('`posts`', $rowsByTable['posts']['current']);
        $this->assertSame('exact', $rowsByTable['pages']['status']);
        $this->assertSame('exact', $rowsByTable['page_revisions']['status']);
        $this->assertSame('exact', $rowsByTable['menus']['status']);
        $this->assertSame('exact', $rowsByTable['media']['status']);
        $this->assertSame('exact', $rowsByTable['project_settings']['status']);
        $this->assertSame('exact', $rowsByTable['feature_flags']['status']);
        $this->assertSame('exact', $rowsByTable['leads']['status']);
        $this->assertSame('exact', $rowsByTable['product_category_relations']['status']);
        $this->assertSame('exact', $rowsByTable['order_addresses']['status']);
        $this->assertSame('exact', $rowsByTable['services']['status']);
        $this->assertStringContainsString('`services`', $rowsByTable['services']['current']);
        $this->assertSame('exact', $rowsByTable['service_categories']['status']);
        $this->assertSame('exact', $rowsByTable['staff']['status']);
        $this->assertSame('exact', $rowsByTable['staff_services']['status']);
        $this->assertSame('exact', $rowsByTable['resources']['status']);
        $this->assertSame('exact', $rowsByTable['availability_rules']['status']);
        $this->assertSame('exact', $rowsByTable['blocked_times']['status']);
        $this->assertSame('exact', $rowsByTable['coupons']['status']);
        $this->assertStringContainsString('`coupons`', $rowsByTable['coupons']['current']);
        $this->assertStringContainsString('`ecommerce_discounts`', $rowsByTable['coupons']['notes']);

        $this->assertSame('exact', $rowsByTable['audit_logs']['status']);
        $this->assertStringContainsString('`audit_logs`', $rowsByTable['audit_logs']['current']);

        // Audit should explicitly call out the main migration-gap themes.
        $this->assertStringContainsString('Migration coverage is complete: there are no `partial` or `missing` source-spec rows', $doc);
        $this->assertStringContainsString('Remaining work is runtime adoption/backfill/cutover', $doc);
        $this->assertStringContainsString('`equivalent` rows are intentional compatibility mappings', $doc);
        $this->assertStringContainsString('Services normalization tables were closed', $doc);
        $this->assertStringContainsString('Customer/auth table batch was closed', $doc);
        $this->assertStringContainsString('Content normalization menu/blog category tables were closed', $doc);
        $this->assertStringContainsString('Universal payments + shipping/coupon normalization tables were closed', $doc);
        $this->assertStringContainsString('Vertical module tables were closed by `2026_02_24_241000_create_universal_vertical_modules_normalization_tables.php`', $doc);
        $this->assertStringContainsString('Partial parity rows were closed by `2026_02_24_242000_add_canonical_parity_columns_and_tables_for_partial_rows.php`', $doc);
    }

    /**
     * @return list<string>
     */
    private function parseSourceSpecTablesFromRoadmap(string $roadmap): array
    {
        $startHeading = '# 0) CORE MULTI-TENANT';
        $endHeading = '# 16) INDEXES / CONSTRAINTS (must)';

        $startPos = strpos($roadmap, $startHeading);
        $endPos = strpos($roadmap, $endHeading);

        $this->assertNotFalse($startPos, 'Could not locate source-spec DB schema section start');
        $this->assertNotFalse($endPos, 'Could not locate source-spec DB schema section end');
        $this->assertGreaterThan($startPos, $endPos, 'Source-spec DB schema section ordering changed unexpectedly');

        $block = substr($roadmap, $startPos, $endPos - $startPos);

        preg_match_all('/^##\s+([a-z_]+)\b/m', $block, $matches);
        $tables = [];
        $seen = [];

        foreach ($matches[1] as $table) {
            if (isset($seen[$table])) {
                continue;
            }
            $seen[$table] = true;
            $tables[] = $table;
        }

        return $tables;
    }

    /**
     * @return list<array{table:string,status:string,current:string,notes:string}>
     */
    private function parseAuditMatrix(string $doc): array
    {
        $rows = [];
        $inMatrix = false;

        foreach (preg_split('/\R/', $doc) as $line) {
            if (str_starts_with($line, '| Source table | Status | Current migration table(s) | Notes |')) {
                $inMatrix = true;
                continue;
            }

            if (! $inMatrix) {
                continue;
            }

            if (str_starts_with($line, '## Summary')) {
                break;
            }

            if (! str_starts_with($line, '|')) {
                continue;
            }

            if (preg_match('/^\|\s*-+\s*\|/', $line) === 1) {
                continue;
            }

            if (preg_match('/^\|\s*([a-z_]+)\s*\|\s*(exact|equivalent|partial|missing)\s*\|\s*(.*?)\s*\|\s*(.*?)\s*\|\s*$/', $line, $m) === 1) {
                $rows[] = [
                    'table' => $m[1],
                    'status' => $m[2],
                    'current' => $m[3],
                    'notes' => $m[4],
                ];
            }
        }

        $this->assertNotEmpty($rows, 'Gap-audit matrix rows were not parsed');

        return $rows;
    }
}
