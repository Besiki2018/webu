<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalPaymentsShippingNormalizationTablesMigrationsDeliverableProgressTest extends TestCase
{
    public function test_payments_shipping_normalization_migrations_batch_progress_is_documented_and_reflected_in_gap_audit(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $migrationPath = base_path('database/migrations/2026_02_24_239000_create_universal_payments_shipping_normalization_tables.php');
        $docPath = base_path('docs/qa/UNIVERSAL_PAYMENTS_SHIPPING_NORMALIZATION_TABLES_MIGRATIONS_DELIVERABLE_PROGRESS.md');
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
            "Schema::create('payment_methods'",
            "Schema::create('payments'",
            "Schema::create('payment_webhooks'",
            "Schema::create('shipping_methods'",
            "Schema::create('shipping_zones'",
            "Schema::create('shipping_zone_regions'",
            "Schema::create('shipping_rates'",
            "Schema::create('coupons'",
            "Schema::create('coupon_redemptions'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }

        foreach ([
            "\$table->json('config_json')->nullable()",
            "\$table->json('provider_payload')->nullable()",
            "\$table->json('payload_json')",
            "\$table->foreign('zone_id')->references('id')->on('shipping_zones')",
            "\$table->foreign('order_id')->references('id')->on('ecommerce_orders')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $migration);
        }

        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:6415', $doc);
        $this->assertStringContainsString('2026_02_24_239000_create_universal_payments_shipping_normalization_tables.php', $doc);
        $this->assertStringContainsString('UniversalPaymentsShippingNormalizationTablesSchemaTest.php', $doc);
        $this->assertStringContainsString('UniversalPaymentsShippingNormalizationTablesMigrationsDeliverableProgressTest.php', $doc);
        $this->assertStringContainsString('still NOT COMPLETE', $doc);

        foreach ([
            '| payment_methods | exact | `payment_methods` |',
            '| payments | exact | `payments` |',
            '| payment_webhooks | exact | `payment_webhooks` |',
            '| shipping_methods | exact | `shipping_methods` |',
            '| shipping_zones | exact | `shipping_zones` |',
            '| shipping_zone_regions | exact | `shipping_zone_regions` |',
            '| shipping_rates | exact | `shipping_rates` |',
            '| coupons | exact | `coupons` |',
            '| coupon_redemptions | exact | `coupon_redemptions` |',
        ] as $needle) {
            $this->assertStringContainsString($needle, $gapAudit);
        }

        $this->assertStringContainsString('- `exact`: `50`', $gapAudit);
        $this->assertStringContainsString('- `equivalent`: `19`', $gapAudit);
        $this->assertStringContainsString('- `partial`: `0`', $gapAudit);
        $this->assertStringContainsString('- `missing`: `0`', $gapAudit);
    }
}
