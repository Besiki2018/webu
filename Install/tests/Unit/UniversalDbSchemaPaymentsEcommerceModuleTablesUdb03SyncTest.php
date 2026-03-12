<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalDbSchemaPaymentsEcommerceModuleTablesUdb03SyncTest extends TestCase
{
    public function test_udb_03_audit_doc_locks_payments_ecommerce_schema_parity_and_fk_index_state_coverage(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_DB_SCHEMA_PAYMENTS_ECOMMERCE_MODULE_TABLES_AUDIT_UDB_03_2026_02_25.md');

        $migrationEcommerceCorePath = base_path('database/migrations/2026_02_20_070000_create_ecommerce_core_tables.php');
        $migrationInventoryLedgerPath = base_path('database/migrations/2026_02_20_093000_create_ecommerce_inventory_ledger_tables.php');
        $migrationDiscountsPath = base_path('database/migrations/2026_02_22_031000_create_ecommerce_discounts_table.php');
        $migrationUniversalPaymentsPath = base_path('database/migrations/2026_02_24_239000_create_universal_payments_shipping_normalization_tables.php');
        $migrationParityRowsPath = base_path('database/migrations/2026_02_24_242000_add_canonical_parity_columns_and_tables_for_partial_rows.php');

        $paymentsShippingSchemaTestPath = base_path('tests/Feature/Platform/UniversalPaymentsShippingNormalizationTablesSchemaTest.php');
        $partialParitySchemaTestPath = base_path('tests/Feature/Platform/UniversalPartialParityRowsCanonicalMigrationsSchemaTest.php');
        $paymentsProgressTestPath = base_path('tests/Unit/UniversalPaymentsShippingNormalizationTablesMigrationsDeliverableProgressTest.php');
        $partialParityCompletionTestPath = base_path('tests/Unit/UniversalPartialParityRowsMigrationsDeliverableCompletionTest.php');
        $fullGapAuditTestPath = base_path('tests/Unit/UniversalFullMigrationsDeliverableGapAuditTest.php');
        $fullGapAuditDocPath = base_path('docs/qa/UNIVERSAL_FULL_MIGRATIONS_DELIVERABLE_GAP_AUDIT_BASELINE.md');

        $ecommercePanelCrudTestPath = base_path('tests/Feature/Ecommerce/EcommercePanelCrudTest.php');
        $ecommerceCheckoutAcceptanceTestPath = base_path('tests/Feature/Ecommerce/EcommerceCheckoutAcceptanceTest.php');
        $ecommerceWebhookTestPath = base_path('tests/Feature/Ecommerce/EcommercePaymentWebhookOrchestrationTest.php');
        $ecommerceShippingAcceptanceTestPath = base_path('tests/Feature/Ecommerce/EcommerceShippingAcceptanceTest.php');
        $ecommerceInventoryLedgerTestPath = base_path('tests/Feature/Ecommerce/EcommerceInventoryLedgerTest.php');
        $ecommerceAdvancedAcceptanceTestPath = base_path('tests/Feature/Ecommerce/EcommerceAdvancedAcceptanceTest.php');
        $ecommercePublicApiTestPath = base_path('tests/Feature/Ecommerce/EcommercePublicApiTest.php');
        $universalPaymentsAbstractionTestPath = base_path('tests/Unit/UniversalPaymentsAbstractionServiceTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $migrationEcommerceCorePath,
            $migrationInventoryLedgerPath,
            $migrationDiscountsPath,
            $migrationUniversalPaymentsPath,
            $migrationParityRowsPath,
            $paymentsShippingSchemaTestPath,
            $partialParitySchemaTestPath,
            $paymentsProgressTestPath,
            $partialParityCompletionTestPath,
            $fullGapAuditTestPath,
            $fullGapAuditDocPath,
            $ecommercePanelCrudTestPath,
            $ecommerceCheckoutAcceptanceTestPath,
            $ecommerceWebhookTestPath,
            $ecommerceShippingAcceptanceTestPath,
            $ecommerceInventoryLedgerTestPath,
            $ecommerceAdvancedAcceptanceTestPath,
            $ecommercePublicApiTestPath,
            $universalPaymentsAbstractionTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $ecommerceCoreMigration = File::get($migrationEcommerceCorePath);
        $inventoryLedgerMigration = File::get($migrationInventoryLedgerPath);
        $discountsMigration = File::get($migrationDiscountsPath);
        $universalPaymentsMigration = File::get($migrationUniversalPaymentsPath);
        $parityRowsMigration = File::get($migrationParityRowsPath);

        $paymentsShippingSchemaTest = File::get($paymentsShippingSchemaTestPath);
        $partialParitySchemaTest = File::get($partialParitySchemaTestPath);
        $paymentsProgressTest = File::get($paymentsProgressTestPath);
        $partialParityCompletionTest = File::get($partialParityCompletionTestPath);
        $fullGapAuditTest = File::get($fullGapAuditTestPath);
        $fullGapAuditDoc = File::get($fullGapAuditDocPath);

        $ecommercePanelCrudTest = File::get($ecommercePanelCrudTestPath);
        $ecommerceCheckoutAcceptanceTest = File::get($ecommerceCheckoutAcceptanceTestPath);
        $ecommerceWebhookTest = File::get($ecommerceWebhookTestPath);
        $ecommerceShippingAcceptanceTest = File::get($ecommerceShippingAcceptanceTestPath);
        $ecommerceInventoryLedgerTest = File::get($ecommerceInventoryLedgerTestPath);
        $ecommerceAdvancedAcceptanceTest = File::get($ecommerceAdvancedAcceptanceTestPath);
        $ecommercePublicApiTest = File::get($ecommercePublicApiTestPath);
        $universalPaymentsAbstractionTest = File::get($universalPaymentsAbstractionTestPath);

        foreach ([
            '# 8) UNIVERSAL PAYMENTS',
            '## payment_methods',
            '## payments',
            '## payment_webhooks',
            '# 9) ECOMMERCE MODULE',
            '## products',
            '## product_variants',
            '## product_images',
            '## product_categories',
            '## product_category_relations',
            '## carts',
            '## cart_items',
            '## orders',
            '## order_items',
            '## order_addresses',
            '## shipping_methods',
            '## shipping_zones',
            '## shipping_zone_regions',
            '## shipping_rates',
            '## coupons',
            '## coupon_redemptions',
            '## stock_movements',
            'status (initiated/paid/failed/refunded/partial_refund)',
            'status (received/processed/failed)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        // Backlog closure + icon notes.
        $this->assertStringContainsString('- `UDB-03` (`DONE`, `P0`)', $backlog);
        $this->assertStringContainsString('UNIVERSAL_DB_SCHEMA_PAYMENTS_ECOMMERCE_MODULE_TABLES_AUDIT_UDB_03_2026_02_25.md', $backlog);
        $this->assertStringContainsString('UniversalDbSchemaPaymentsEcommerceModuleTablesUdb03SyncTest.php', $backlog);
        $this->assertStringContainsString('`✅` universal payments + ecommerce source tables mapped to exact/equivalent runtime schema tables', $backlog);
        $this->assertStringContainsString('`✅` critical FK/index coverage reconciled across core ecommerce + canonical parity + universal payments migrations', $backlog);
        $this->assertStringContainsString('`✅` status/state-field coverage reconciled with feature evidence (orders/payments/webhooks/stock ledger)', $backlog);
        $this->assertStringContainsString('`🧪` targeted schema + ecommerce evidence batch passed', $backlog);

        // Audit doc structure + coverage claims.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:5947',
            'PROJECT_ROADMAP_TASKS_KA.md:6155',
            '## ✅ What Was Done (Icon Summary)',
            '## Executive Result (`UDB-03`)',
            '`UDB-03` is **complete as an audit task**',
            '## Payment / Ecommerce Schema Parity Matrix',
            '### Source Section `8` — Universal Payments',
            '### Source Section `9` — Ecommerce Module',
            '## Critical FK / Index Coverage Check (UDB-03 DoD Part 1)',
            '## State-Field Coverage Check (UDB-03 DoD Part 2)',
            '## Gaps / Follow-up (Outside UDB-03 Audit Completion)',
            '## Conclusion',
            'payment_methods',
            'payment_webhooks',
            'ecommerce_products',
            'ecommerce_orders',
            'product_category_relations',
            'order_addresses',
            'ecommerce_stock_movements',
            'ecommerce_discounts',
            'implemented_exact_db_first_with_runtime_bridge',
            'implemented_equivalent_runtime_active',
            'implemented_exact_runtime_compat_mode',
            'payment_webhooks.status',
            'ecommerce_orders.status',
            'ecommerce_orders.status` + `payment_status` + `fulfillment_status`',
            'stock_movements.type',
            'movement_type',
            'module-specific',
            'UniversalPaymentsAbstractionService',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'Install/database/migrations/2026_02_20_070000_create_ecommerce_core_tables.php',
            'Install/database/migrations/2026_02_20_093000_create_ecommerce_inventory_ledger_tables.php',
            'Install/database/migrations/2026_02_22_031000_create_ecommerce_discounts_table.php',
            'Install/database/migrations/2026_02_24_239000_create_universal_payments_shipping_normalization_tables.php',
            'Install/database/migrations/2026_02_24_242000_add_canonical_parity_columns_and_tables_for_partial_rows.php',
            'Install/tests/Feature/Platform/UniversalPaymentsShippingNormalizationTablesSchemaTest.php',
            'Install/tests/Feature/Platform/UniversalPartialParityRowsCanonicalMigrationsSchemaTest.php',
            'Install/tests/Unit/UniversalPaymentsShippingNormalizationTablesMigrationsDeliverableProgressTest.php',
            'Install/tests/Unit/UniversalPartialParityRowsMigrationsDeliverableCompletionTest.php',
            'Install/tests/Unit/UniversalFullMigrationsDeliverableGapAuditTest.php',
            'Install/docs/qa/UNIVERSAL_FULL_MIGRATIONS_DELIVERABLE_GAP_AUDIT_BASELINE.md',
            'Install/tests/Feature/Ecommerce/EcommercePanelCrudTest.php',
            'Install/tests/Feature/Ecommerce/EcommerceCheckoutAcceptanceTest.php',
            'Install/tests/Feature/Ecommerce/EcommercePaymentWebhookOrchestrationTest.php',
            'Install/tests/Feature/Ecommerce/EcommerceShippingAcceptanceTest.php',
            'Install/tests/Feature/Ecommerce/EcommerceInventoryLedgerTest.php',
            'Install/tests/Feature/Ecommerce/EcommerceAdvancedAcceptanceTest.php',
            'Install/tests/Unit/UniversalPaymentsAbstractionServiceTest.php',
        ] as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc);
            $this->assertFileExists(base_path('../'.$relativePath));
        }

        // Universal payments normalization migration FK/index/state anchors.
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
            "\$table->unique(['provider', 'event_id'])",
            "\$table->index(['provider', 'status'])",
            "\$table->index(['project_id', 'payable_type', 'payable_id'])",
            "\$table->foreign('order_id')->references('id')->on('ecommerce_orders')",
            "\$table->string('status', 32)->default('initiated')",
            "\$table->string('status', 32)->default('received')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $universalPaymentsMigration);
        }

        // Ecommerce core + parity rows + inventory ledger anchors.
        foreach ([
            "Schema::create('ecommerce_products'",
            "Schema::create('ecommerce_product_variants'",
            "Schema::create('ecommerce_product_images'",
            "Schema::create('ecommerce_orders'",
            "Schema::create('ecommerce_order_items'",
            "Schema::create('ecommerce_order_payments'",
            "Schema::create('ecommerce_carts'",
            "Schema::create('ecommerce_cart_items'",
            "\$table->unique(['site_id', 'slug'])",
            "\$table->unique(['site_id', 'order_number'])",
            "\$table->index(['site_id', 'payment_status'])",
            "\$table->index(['site_id', 'status'])",
            "\$table->foreign('order_id')->references('id')->on('ecommerce_orders')->onDelete('cascade')",
            "\$table->string('status', 30)->default('pending')",
            "\$table->string('payment_status', 30)->default('unpaid')",
            "\$table->string('fulfillment_status', 30)->default('unfulfilled')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceCoreMigration);
        }

        foreach ([
            "Schema::create('ecommerce_stock_movements'",
            "\$table->index(['site_id', 'movement_type']",
            "\$table->index(['site_id', 'order_id']",
            "\$table->index(['site_id', 'cart_id']",
            "\$table->foreign('order_id')->references('id')->on('ecommerce_orders')->nullOnDelete()",
            "\$table->string('movement_type', 40)",
            "\$table->integer('quantity_delta')->default(0)",
            "\$table->integer('reserved_delta')->default(0)",
        ] as $needle) {
            $this->assertStringContainsString($needle, $inventoryLedgerMigration);
        }

        foreach ([
            "Schema::create('product_category_relations'",
            "Schema::create('order_addresses'",
            "\$table->primary(['product_id', 'category_id'])",
            "\$table->index(['order_id', 'type'])",
            "\$table->foreign('order_id')->references('id')->on('ecommerce_orders')->cascadeOnDelete()",
        ] as $needle) {
            $this->assertStringContainsString($needle, $parityRowsMigration);
        }

        // Legacy coupon compatibility path anchor.
        $this->assertStringContainsString("Schema::create('ecommerce_discounts'", $discountsMigration);
        $this->assertStringContainsString("\$table->string('status', 20)->default('draft')", $discountsMigration);

        // Existing QA/gap-audit locks for relevant rows.
        foreach ([
            '| payment_methods | exact | `payment_methods` |',
            '| payments | exact | `payments` |',
            '| payment_webhooks | exact | `payment_webhooks` |',
            '| products | equivalent | `ecommerce_products` |',
            '| product_variants | equivalent | `ecommerce_product_variants` |',
            '| product_images | equivalent | `ecommerce_product_images` |',
            '| product_categories | equivalent | `ecommerce_categories` |',
            '| product_category_relations | exact | `product_category_relations` (+ `ecommerce_products.category_id`) |',
            '| carts | equivalent | `ecommerce_carts` |',
            '| cart_items | equivalent | `ecommerce_cart_items` |',
            '| orders | equivalent | `ecommerce_orders` |',
            '| order_items | equivalent | `ecommerce_order_items` |',
            '| order_addresses | exact | `order_addresses` (+ `ecommerce_orders.*_address_json`) |',
            '| shipping_methods | exact | `shipping_methods` |',
            '| shipping_zones | exact | `shipping_zones` |',
            '| shipping_zone_regions | exact | `shipping_zone_regions` |',
            '| shipping_rates | exact | `shipping_rates` |',
            '| coupons | exact | `coupons` |',
            '| coupon_redemptions | exact | `coupon_redemptions` |',
            '| stock_movements | equivalent | `ecommerce_stock_movements` |',
        ] as $needle) {
            $this->assertStringContainsString($needle, $fullGapAuditDoc);
        }

        // Schema/QA unit test anchors.
        $this->assertStringContainsString('UniversalPaymentsShippingNormalizationTablesMigrationsDeliverableProgressTest', $paymentsProgressTest);
        $this->assertStringContainsString('| payment_webhooks | exact | `payment_webhooks` |', $paymentsProgressTest);
        $this->assertStringContainsString('UniversalPartialParityRowsMigrationsDeliverableCompletionTest', $partialParityCompletionTest);
        $this->assertStringContainsString('| order_addresses | exact | `order_addresses` (+ `ecommerce_orders.*_address_json`) |', $partialParityCompletionTest);
        $this->assertStringContainsString('product_category_relations', $partialParitySchemaTest);
        $this->assertStringContainsString('order_addresses', $partialParitySchemaTest);
        $this->assertStringContainsString('payment_webhooks', $paymentsShippingSchemaTest);
        $this->assertStringContainsString('shipping_rates', $paymentsShippingSchemaTest);
        $this->assertStringContainsString('coupon_redemptions', $paymentsShippingSchemaTest);
        $this->assertStringContainsString('$rowsByTable[\'products\']', $fullGapAuditTest);
        $this->assertStringContainsString('$rowsByTable[\'coupons\']', $fullGapAuditTest);

        // Runtime state-field evidence anchors.
        foreach ([
            "'status' => 'active'",
            "'status' => 'draft'",
            "'payment_status' => 'paid'",
            "'fulfillment_status' => 'unfulfilled'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommercePanelCrudTest);
        }

        $this->assertStringContainsString("'payment_status' => 'paid'", $ecommerceCheckoutAcceptanceTest);
        $this->assertStringContainsString("'fulfillment_status' => 'unfulfilled'", $ecommerceCheckoutAcceptanceTest);
        $this->assertStringContainsString("'payment_status' => 'paid'", $ecommerceWebhookTest);
        $this->assertStringContainsString("'fulfillment_status' => 'unfulfilled'", $ecommerceWebhookTest);
        $this->assertStringContainsString('assertJsonPath(\'order.fulfillment_status\', \'partial\')', $ecommerceShippingAcceptanceTest);
        $this->assertStringContainsString("assertDatabaseHas('ecommerce_stock_movements'", $ecommerceInventoryLedgerTest);
        $this->assertStringContainsString("'movement_type' => EcommerceStockMovement::TYPE_COMMIT", $ecommerceInventoryLedgerTest);
        $this->assertStringContainsString("'quantity_delta' => -2", $ecommerceInventoryLedgerTest);
        $this->assertStringContainsString("'reserved_delta' => -2", $ecommerceInventoryLedgerTest);
        $this->assertStringContainsString("'movement_type' => EcommerceStockMovement::TYPE_RESERVE", $ecommerceAdvancedAcceptanceTest);
        $this->assertStringContainsString("'movement_type' => EcommerceStockMovement::TYPE_COMMIT", $ecommerceAdvancedAcceptanceTest);
        $this->assertStringContainsString("assertDatabaseHas('ecommerce_order_payments'", $ecommercePublicApiTest);

        // Universal payment bridge evidence anchors.
        $this->assertStringContainsString('normalizeEcommercePayment', $universalPaymentsAbstractionTest);
        $this->assertStringContainsString('normalizeBookingPayment', $universalPaymentsAbstractionTest);
        $this->assertStringContainsString("'source.table'));", $universalPaymentsAbstractionTest);
        $this->assertStringContainsString("'ecommerce_order_payments'", $universalPaymentsAbstractionTest);
        $this->assertStringContainsString("'booking_payments'", $universalPaymentsAbstractionTest);
    }
}
