<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class LegacyReferenceArchiveEcommerceFullIntegrationAcceptanceDeliverablesReconciliationAr04SyncTest extends TestCase
{
    public function test_ar_04_legacy_acceptance_and_deliverables_reconciliation_audit_locks_truthfully(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_ACCEPTANCE_DELIVERABLES_RECONCILIATION_AUDIT_AR_04_2026_02_25.md');

        $ar01DocPath = base_path('docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_THEME_SITE_SETTINGS_RECONCILIATION_AUDIT_AR_01_2026_02_25.md');
        $ar02DocPath = base_path('docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_BUILDER_CORE_REGISTRY_DYNAMIC_BINDING_COMPONENT_REQUIREMENTS_RECONCILIATION_AUDIT_AR_02_2026_02_25.md');
        $ar03DocPath = base_path('docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_PAGE_TEMPLATES_ROUTING_PAGE_PARAMS_NOTIFICATIONS_RECONCILIATION_AUDIT_AR_03_2026_02_25.md');
        $api02DocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_PUBLIC_API_COVERAGE_AUDIT_API_02_2026_02_25.md');
        $api03DocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md');
        $api05DocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_ECOMMERCE_ROUTING_TEMPLATE_PACK_COMPONENT_API_AUDIT_API_05_2026_02_25.md');

        $rs0001DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_BASE_NODE_TABS_REGISTRY_AUDIT_RS_00_01_2026_02_25.md');
        $rs0002DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_STYLE_GROUP_PARITY_AUDIT_RS_00_02_2026_02_25.md');
        $rs0003DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_DATA_BINDING_RULE_COVERAGE_AUDIT_RS_00_03_2026_02_25.md');
        $rs0501DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CATALOG_DISCOVERY_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_01_2026_02_25.md');
        $rs0502DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_PDP_CART_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_02_2026_02_25.md');
        $rs0503DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_03_2026_02_25.md');
        $rs1301DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_13_01_2026_02_25.md');
        $udb02DocPath = base_path('docs/qa/UNIVERSAL_DB_SCHEMA_CONTENT_BUILDER_FORMS_NOTIFICATIONS_AUDIT_UDB_02_2026_02_25.md');
        $udb05DocPath = base_path('docs/qa/UNIVERSAL_DB_SCHEMA_INDEXES_CONSTRAINTS_DELIVERABLES_ACCEPTANCE_AUDIT_UDB_05_2026_02_25.md');

        $templateProvisioningSmokePath = base_path('tests/Feature/Templates/TemplateProvisioningSmokeTest.php');
        $templateStorefrontE2ePath = base_path('tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php');
        $cmsPreviewPublishAlignmentPath = base_path('tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php');
        $ecommercePublicApiTestPath = base_path('tests/Feature/Ecommerce/EcommercePublicApiTest.php');
        $ecommerceCheckoutAcceptanceTestPath = base_path('tests/Feature/Ecommerce/EcommerceCheckoutAcceptanceTest.php');
        $ecommerceShippingAcceptanceTestPath = base_path('tests/Feature/Ecommerce/EcommerceShippingAcceptanceTest.php');
        $ecommerceTransactionalNotificationsTestPath = base_path('tests/Feature/Ecommerce/EcommerceTransactionalNotificationsTest.php');
        $componentActivationTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');
        $coverageGapAuditTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php');
        $aliasMapTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecEquivalenceAliasMapTest.php');
        $themeTokenLayerResolverTestPath = base_path('tests/Unit/CmsThemeTokenLayerResolverTest.php');
        $themeTokenValueValidatorTestPath = base_path('tests/Unit/CmsThemeTokenValueValidatorTest.php');
        $partialParitySchemaTestPath = base_path('tests/Feature/Platform/UniversalPartialParityRowsCanonicalMigrationsSchemaTest.php');

        $registryDocPath = base_path('docs/architecture/CMS_CANONICAL_COMPONENT_REGISTRY_SCHEMA_V1.md');
        $registryWorkflowDocPath = base_path('docs/architecture/CMS_AI_COMPONENT_REGISTRY_INTEGRATION_WORKFLOW_V1.md');
        $schemaContractsTestPath = base_path('tests/Unit/CmsCanonicalSchemaContractsTest.php');
        $registryWorkflowTestPath = base_path('tests/Unit/CmsAiComponentRegistryIntegrationWorkflowServiceTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $ar01DocPath,
            $ar02DocPath,
            $ar03DocPath,
            $api02DocPath,
            $api03DocPath,
            $api05DocPath,
            $rs0001DocPath,
            $rs0002DocPath,
            $rs0003DocPath,
            $rs0501DocPath,
            $rs0502DocPath,
            $rs0503DocPath,
            $rs1301DocPath,
            $udb02DocPath,
            $udb05DocPath,
            $templateProvisioningSmokePath,
            $templateStorefrontE2ePath,
            $cmsPreviewPublishAlignmentPath,
            $ecommercePublicApiTestPath,
            $ecommerceCheckoutAcceptanceTestPath,
            $ecommerceShippingAcceptanceTestPath,
            $ecommerceTransactionalNotificationsTestPath,
            $componentActivationTestPath,
            $coverageGapAuditTestPath,
            $aliasMapTestPath,
            $themeTokenLayerResolverTestPath,
            $themeTokenValueValidatorTestPath,
            $partialParitySchemaTestPath,
            $registryDocPath,
            $registryWorkflowDocPath,
            $schemaContractsTestPath,
            $registryWorkflowTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $ar01Doc = File::get($ar01DocPath);
        $ar02Doc = File::get($ar02DocPath);
        $ar03Doc = File::get($ar03DocPath);
        $api02Doc = File::get($api02DocPath);
        $api03Doc = File::get($api03DocPath);
        $api05Doc = File::get($api05DocPath);
        $rs0001Doc = File::get($rs0001DocPath);
        $rs0002Doc = File::get($rs0002DocPath);
        $rs0003Doc = File::get($rs0003DocPath);
        $rs0501Doc = File::get($rs0501DocPath);
        $rs0502Doc = File::get($rs0502DocPath);
        $rs0503Doc = File::get($rs0503DocPath);
        $rs1301Doc = File::get($rs1301DocPath);
        $udb02Doc = File::get($udb02DocPath);
        $udb05Doc = File::get($udb05DocPath);

        $templateProvisioningSmoke = File::get($templateProvisioningSmokePath);
        $templateStorefrontE2e = File::get($templateStorefrontE2ePath);
        $cmsPreviewPublishAlignment = File::get($cmsPreviewPublishAlignmentPath);
        $ecommercePublicApiTest = File::get($ecommercePublicApiTestPath);
        $ecommerceCheckoutAcceptanceTest = File::get($ecommerceCheckoutAcceptanceTestPath);
        $ecommerceShippingAcceptanceTest = File::get($ecommerceShippingAcceptanceTestPath);
        $ecommerceTransactionalNotificationsTest = File::get($ecommerceTransactionalNotificationsTestPath);
        $componentActivationTest = File::get($componentActivationTestPath);
        $coverageGapAuditTest = File::get($coverageGapAuditTestPath);
        $aliasMapTest = File::get($aliasMapTestPath);
        $themeTokenLayerResolverTest = File::get($themeTokenLayerResolverTestPath);
        $themeTokenValueValidatorTest = File::get($themeTokenValueValidatorTestPath);
        $partialParitySchemaTest = File::get($partialParitySchemaTestPath);
        $registryDoc = File::get($registryDocPath);
        $registryWorkflowDoc = File::get($registryWorkflowDocPath);
        $schemaContractsTest = File::get($schemaContractsTestPath);
        $registryWorkflowTest = File::get($registryWorkflowTestPath);

        foreach ([
            '8) Acceptance Criteria (MUST PASS)',
            'New theme exists with tokens + presets + page templates.',
            'Builder library includes all base components + ecommerce components.',
            'Ecommerce components successfully fetch data from backend APIs.',
            'Cart works end-to-end (add/update/remove).',
            'Checkout works end-to-end (shipping calc + coupon + payment init + order create).',
            'Auth component supports Email/SMS/Google/Facebook depending on store settings.',
            'Customer can view orders list and order details via components.',
            'All components have Content/Style/Advanced with responsive + states.',
            'Published pages render correctly and are reproducible from stored JSON/CSS.',
            '9) Deliverables',
            'Component registry + schema',
            'Renderers for all components',
            'Controls configs for all components',
            'New Theme (tokens + presets + templates)',
            'Default page JSON templates',
            'API binding layer + auth session handling',
            'Minimal docs: how to add a new ecommerce component',
            'IMPORTANT NOTES',
            'Builder is the UI layer only.',
            'Backend is already specified by Webu Headless E-commerce Engine.',
            'All data must be scoped by tenant_id + store_id.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `AR-04` (`DONE`, `P0`)',
            'LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_ACCEPTANCE_DELIVERABLES_RECONCILIATION_AUDIT_AR_04_2026_02_25.md',
            'LegacyReferenceArchiveEcommerceFullIntegrationAcceptanceDeliverablesReconciliationAr04SyncTest.php',
            'LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_THEME_SITE_SETTINGS_RECONCILIATION_AUDIT_AR_01_2026_02_25.md',
            'LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_BUILDER_CORE_REGISTRY_DYNAMIC_BINDING_COMPONENT_REQUIREMENTS_RECONCILIATION_AUDIT_AR_02_2026_02_25.md',
            'LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_PAGE_TEMPLATES_ROUTING_PAGE_PARAMS_NOTIFICATIONS_RECONCILIATION_AUDIT_AR_03_2026_02_25.md',
            'WEBU_BACKEND_BUILDER_PUBLIC_API_COVERAGE_AUDIT_API_02_2026_02_25.md',
            'WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md',
            'WEBU_BACKEND_BUILDER_ECOMMERCE_ROUTING_TEMPLATE_PACK_COMPONENT_API_AUDIT_API_05_2026_02_25.md',
            '`✅` all legacy acceptance criteria (`8)`) reconciled to current evidence with explicit `pass/partial` status and follow-up owners for non-pass lines',
            '`✅` all legacy deliverables (`9)`) mapped to current code/docs/tests with `implemented/equivalent/partial/gap` truth labels',
            '`✅` legacy IMPORTANT NOTES reconciled to current canonical terminology/architecture (`site_id` store-equivalent, site-scoped public runtime, builder-vs-backend boundary)',
            '`⚠️` acceptance remains mixed: core cart/checkout/reproducibility lines pass, while auth/orders/all-components parity lines remain partial and are cross-linked to open `RS-*` / `API-*` / `ECM-*` tasks',
            '`⚠️` deliverable row `Minimal docs: how to add a new ecommerce component` is only partially covered by registry/workflow architecture docs and is assigned to `ECM-03` docs/deliverables follow-up',
            '`🧪` AR-04 reconciliation sync lock added (legacy acceptance + deliverables + notes closure state)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Closure Rationale (Why `AR-04` Can Be `DONE`)',
            '## Acceptance Criteria Reconciliation Matrix (Source `8`)',
            '### Matrix (`pass / partial` + follow-up ownership)',
            '### Acceptance Summary',
            '## Deliverables Reconciliation Matrix (Source `9`)',
            '### Matrix (`implemented / equivalent / partial / gap`)',
            '### Deliverables Summary',
            '## Important Notes Reconciliation Matrix',
            '### Matrix (`exact / equivalent / partial`)',
            '## Cross-Source Resolution Notes (Why Legacy Acceptance Is Not Overstated)',
            '## DoD Verdict (`AR-04`)',
            'Conclusion: `AR-04` is `DONE`.',
            '## Follow-up Mapping (Non-blocking for `AR-04` Closure)',
            '`ECM-01`',
            '`ECM-02`',
            '`ECM-03`',
            '`API-02` / `API-03`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            '| `New theme exists with tokens + presets + page templates.` |',
            '| `Builder library includes all base components + ecommerce components.` |',
            '| `Cart works end-to-end (add/update/remove).` | `pass` |',
            '| `Checkout works end-to-end (shipping calc + coupon + payment init + order create).` | `pass` |',
            '| `Auth component supports Email/SMS/Google/Facebook depending on store settings.` |',
            '| `Published pages render correctly and are reproducible from stored JSON/CSS.` | `pass` |',
            '| `Component registry + schema` | `implemented` |',
            '| `Renderers for all components` | `partial` |',
            '| `New Theme (tokens + presets + templates)` | `equivalent` |',
            '| `Minimal docs: how to add a new ecommerce component` | `partial` |',
            '| `Builder is the UI layer only.` | `equivalent` |',
            '| `All data must be scoped by tenant_id + store_id.` | `equivalent` |',
            '- `pass`: `3`',
            '- `partial`: `6`',
            '- `implemented`: `1`',
            '- `equivalent`: `1`',
            '- `partial`: `5`',
            '- `gap`: `0`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([$ar01Doc, $ar02Doc, $ar03Doc] as $legacyDoc) {
            $this->assertStringContainsString('Status: `DONE`', $legacyDoc);
            $this->assertStringContainsString('Conclusion: `AR-', $legacyDoc);
        }

        foreach ([
            'GET /categories',
            '`gap`',
            'No dedicated public categories endpoint',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api02Doc);
        }

        foreach ([
            '`site`-scoped public storefront runtime',
            'no `GET /orders/my`',
            'no public `GET /orders/{id}`',
            'no dedicated `/customers/me` JSON API route exists in baseline',
            'customer auth APIs (register/login/otp/social)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api03Doc);
        }

        foreach ([
            'default `webu-shop` pack does **not** currently include `login/account/orders/order-detail` page blueprints',
            '/products/:slug',
            '/account/orders/:id',
            'runtime supported but default pack gap',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api05Doc);
        }

        foreach ([
            'grouped props contract required by source (`content`, `data`, `style`, `advanced`, `responsive`, `states`) is implemented',
            '`props.responsive {}`',
            '`props.states {}`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0001Doc);
        }

        foreach ([
            'Result: most groups are `partial`',
            'Important: `missing = 0` here does **not** mean full source parity.',
            '- `partial`: `11`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0002Doc);
        }

        foreach ([
            '`{{key.path}}` syntax parse + normalization',
            'Runtime payload path resolution',
            'CmsCanonicalBindingResolverTest.php',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0003Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'Conclusion: `RS-05-03` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0503Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'Conclusion: `RS-13-01` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs1301Doc);
        }

        foreach ([
            '## DoD Verdict (`UDB-05`)',
            'Result:',
            'acceptance and deliverable lines resolved to evidence/follow-up tasks',
        ] as $needle) {
            $this->assertStringContainsString($needle, $udb05Doc);
        }

        foreach ([
            'pages',
            'page_revisions',
            'notifications',
            'leads',
        ] as $needle) {
            $this->assertStringContainsString($needle, $udb02Doc);
        }

        foreach ([
            'Template provisioning should create default pages.',
            'Re-provision should not duplicate pages.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $templateProvisioningSmoke);
        }

        foreach ([
            'assertPublishedRouteHtml($host, \'/account/orders\'',
            'assertPublishedRouteHtml($host, \'/account/orders/\'.$orderId',
            'Found unresolved placeholder in route {$path}',
        ] as $needle) {
            $this->assertStringContainsString($needle, $templateStorefrontE2e);
        }

        foreach ([
            'test_runtime_bridge_normalizes_dynamic_storefront_paths_and_aliases_category_order_params',
            "->assertJsonPath('route.params.id', '1001')",
            'meta.endpoints.ecommerce_checkout',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsPreviewPublishAlignment);
        }

        foreach ([
            "->assertJsonPath('coupon.code', 'SAVE10')",
            "->assertJsonPath('error', 'Coupon code is invalid.')",
            "->assertJsonPath('payment.status', 'pending')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommercePublicApiTest);
        }

        foreach ([
            'test_add_to_cart_fails_when_requested_quantity_exceeds_stock',
            "->assertJsonPath('error', 'Requested quantity exceeds available stock.')",
            'test_checkout_happy_path_and_payment_success_flow',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceCheckoutAcceptanceTest);
        }

        foreach ([
            'test_shipping_happy_path_quote_selection_checkout_and_tracking_flow',
            'test_shipping_selection_resets_after_cart_change_and_invalid_rate_is_rejected',
            'shipping_total',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceShippingAcceptanceTest);
        }

        foreach ([
            'test_order_placed_notification_is_sent_to_merchant_on_checkout',
            'test_order_paid_notification_is_sent_on_successful_webhook_sync',
            'test_order_failed_notification_is_sent_on_failed_webhook_sync',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceTransactionalNotificationsTest);
        }

        foreach ([
            'test_p5_f5_01_and_p5_f5_02_universal_component_library_activation_contract_is_locked',
            'builderSectionAvailabilityMatrix',
            "key: 'ecommerce'",
            "key: 'booking'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $componentActivationTest);
        }

        foreach ([
            'UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md',
            'Component-library spec component coverage gap audit baseline: **COMPLETE**',
        ] as $needle) {
            $this->assertStringContainsString($needle, $coverageGapAuditTest);
        }

        foreach ([
            'UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json',
            'machine-readable alias map',
            'gap_audit_ref',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMapTest);
        }

        foreach ([
            'CmsThemeTokenLayerResolver',
            'theme_preset',
            'effective.theme_tokens',
        ] as $needle) {
            $this->assertStringContainsString($needle, $themeTokenLayerResolverTest);
        }

        foreach ([
            'CmsThemeTokenValueValidator',
            'theme_token_validation_failed',
            'theme_tokens',
            'unsupported_mode',
        ] as $needle) {
            $this->assertStringContainsString($needle, $themeTokenValueValidatorTest);
        }

        foreach ([
            "Schema::hasColumns('pages', ['tenant_id', 'project_id', 'page_json', 'page_css'",
            "Schema::hasColumns('page_revisions', ['page_json', 'page_css'])",
            '\'site_id\' => (string) $site->id',
        ] as $needle) {
            $this->assertStringContainsString($needle, $partialParitySchemaTest);
        }

        foreach ([
            '`type`',
            '`category`',
            '`props_schema`',
            '`default_props`',
            '`renderer`',
            '`controls_config`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $registryDoc);
        }

        foreach ([
            'Purpose:',
            'CmsAiComponentRegistryIntegrationWorkflowService',
            'Pre-Activation Validator Gates (v1)',
            'Coverage',
            'CmsAiComponentRegistryIntegrationWorkflowServiceTest.php',
        ] as $needle) {
            $this->assertStringContainsString($needle, $registryWorkflowDoc);
        }

        foreach ([
            'CmsCanonicalSchemaContractsTest',
            'default_pages',
            'default_sections',
        ] as $needle) {
            $this->assertStringContainsString($needle, $schemaContractsTest);
        }

        foreach ([
            'CmsAiComponentRegistryIntegrationWorkflowServiceTest',
            'prepareActivationFromRawFeatureSpec',
            'ready_for_activation',
        ] as $needle) {
            $this->assertStringContainsString($needle, $registryWorkflowTest);
        }

        foreach ([
            '`✅`',
            '`⚠️`',
            '`🧪`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            '`RS-01-01`',
            '`RS-02-01`',
            '`RS-04-01`',
            '`RS-05-01..03`',
            '`RS-13-01`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            '`8)` acceptance lines',
            '`9)` deliverable lines',
            '`IMPORTANT NOTES`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }
    }
}
