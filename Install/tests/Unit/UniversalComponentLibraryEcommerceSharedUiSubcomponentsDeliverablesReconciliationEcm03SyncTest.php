<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryEcommerceSharedUiSubcomponentsDeliverablesReconciliationEcm03SyncTest extends TestCase
{
    public function test_ecm_03_reconciliation_audit_locks_shared_ui_inventory_and_deliverables_checklist_truthfully(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_SHARED_UI_SUBCOMPONENTS_DELIVERABLES_RECONCILIATION_AUDIT_ECM_03_2026_02_25.md');

        $ecm01DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_GLOBAL_CONTRACTS_BASELINE_GAP_AUDIT_ECM_01_2026_02_25.md');
        $ecm02DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_BASELINE_GAP_AUDIT_ECM_02_2026_02_25.md');
        $ar04DocPath = base_path('docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_ACCEPTANCE_DELIVERABLES_RECONCILIATION_AUDIT_AR_04_2026_02_25.md');
        $api05DocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_ECOMMERCE_ROUTING_TEMPLATE_PACK_COMPONENT_API_AUDIT_API_05_2026_02_25.md');
        $rs0003DocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_DATA_BINDING_RULE_COVERAGE_AUDIT_RS_00_03_2026_02_25.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $bindingResolverPath = base_path('app/Services/CmsCanonicalBindingResolver.php');
        $bindingResolverTestPath = base_path('tests/Unit/CmsCanonicalBindingResolverTest.php');
        $bindingValidatorTestPath = base_path('tests/Unit/CmsBindingExpressionValidatorTest.php');
        $dynamicHookTestPath = base_path('tests/Unit/CmsDynamicControlHookServiceTest.php');
        $ecommerceCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts');
        $templateStorefrontE2ePath = base_path('tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php');
        $api05SyncTestPath = base_path('tests/Unit/BackendBuilderEcommerceRoutingTemplatePackApi05SyncTest.php');
        $registryDocPath = base_path('docs/architecture/CMS_CANONICAL_COMPONENT_REGISTRY_SCHEMA_V1.md');
        $workflowDocPath = base_path('docs/architecture/CMS_AI_COMPONENT_REGISTRY_INTEGRATION_WORKFLOW_V1.md');
        $schemaContractsTestPath = base_path('tests/Unit/CmsCanonicalSchemaContractsTest.php');
        $workflowTestPath = base_path('tests/Unit/CmsAiComponentRegistryIntegrationWorkflowServiceTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $ecm01DocPath,
            $ecm02DocPath,
            $ar04DocPath,
            $api05DocPath,
            $rs0003DocPath,
            $cmsPath,
            $builderServicePath,
            $bindingResolverPath,
            $bindingResolverTestPath,
            $bindingValidatorTestPath,
            $dynamicHookTestPath,
            $ecommerceCoverageContractPath,
            $templateStorefrontE2ePath,
            $api05SyncTestPath,
            $registryDocPath,
            $workflowDocPath,
            $schemaContractsTestPath,
            $workflowTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $ecm01Doc = File::get($ecm01DocPath);
        $ecm02Doc = File::get($ecm02DocPath);
        $ar04Doc = File::get($ar04DocPath);
        $api05Doc = File::get($api05DocPath);
        $rs0003Doc = File::get($rs0003DocPath);

        $cms = File::get($cmsPath);
        $builderService = File::get($builderServicePath);
        $bindingResolver = File::get($bindingResolverPath);
        $bindingResolverTest = File::get($bindingResolverTestPath);
        $ecommerceCoverageContract = File::get($ecommerceCoverageContractPath);
        $templateStorefrontE2e = File::get($templateStorefrontE2ePath);
        $registryDoc = File::get($registryDocPath);
        $workflowDoc = File::get($workflowDocPath);
        $schemaContractsTest = File::get($schemaContractsTestPath);
        $workflowTest = File::get($workflowTestPath);

        foreach ([
            '# 14) Shared UI Subcomponents (internal)',
            'Price component (handles sale price)',
            'Badge component',
            'Skeleton loader',
            'Empty state box',
            'Error state box',
            'Quantity stepper',
            'Variant selector',
            'Pagination control',
            'These should use theme tokens by default.',
            '# 15) Deliverables',
            '1) Implement all component types above in registry',
            '2) Implement their renderer + controls config',
            '3) Implement API client layer (auth, store scoping, error handling)',
            '4) Implement dynamic binding resolver (route/store/customer context)',
            '5) Provide demo pages (home, listing, detail, cart, checkout, account, orders)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `ECM-03` (`DONE`, `P1`)',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_SHARED_UI_SUBCOMPONENTS_DELIVERABLES_RECONCILIATION_AUDIT_ECM_03_2026_02_25.md',
            'UniversalComponentLibraryEcommerceSharedUiSubcomponentsDeliverablesReconciliationEcm03SyncTest.php',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_GLOBAL_CONTRACTS_BASELINE_GAP_AUDIT_ECM_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_BASELINE_GAP_AUDIT_ECM_02_2026_02_25.md',
            'WEBU_BACKEND_BUILDER_ECOMMERCE_ROUTING_TEMPLATE_PACK_COMPONENT_API_AUDIT_API_05_2026_02_25.md',
            'LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_ACCEPTANCE_DELIVERABLES_RECONCILIATION_AUDIT_AR_04_2026_02_25.md',
            'CMS_CANONICAL_COMPONENT_REGISTRY_SCHEMA_V1.md',
            'CMS_AI_COMPONENT_REGISTRY_INTEGRATION_WORKFLOW_V1.md',
            '`✅` source `#14` eight internal ecommerce shared UI subcomponents are now inventoried with owner layers (`Cms.tsx` builder preview vs `BuilderService` runtime helper), consumer examples, and implementation maturity labels',
            '`✅` source `#15` deliverables checklist is synced to current code/docs/tests with truthful `implemented/equivalent/partial/gap` labels (no unmapped row)',
            '`✅` `AR-04` follow-up on `Minimal docs: how to add a new ecommerce component` is resolved into an explicit `ECM-03` deliverables decision row (kept `partial` truthfully, with evidence and owner notes)',
            '`⚠️` source `#14` note `These should use theme tokens by default.` remains partial: ecommerce shared preview/runtime UI pieces still rely heavily on hardcoded colors/inline styles despite platform theme-token infrastructure availability',
            '`⚠️` published reusable runtime shared-subcomponent contract remains narrow: `window.WebbyEcommerce` exports API/client helpers + products/cart mounts, but many `#14` shared pieces are builder-preview internals rather than exported runtime widgets',
            '`🧪` ECM-03 reconciliation sync lock added (shared UI inventory + deliverables checklist closure state)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Closure Rationale (Why `ECM-03` Can Be `DONE`)',
            '## Shared UI Subcomponent Inventory + Ownership / Consumer Matrix (Source `14`)',
            'Price component (handles sale price)',
            'Badge component',
            'Skeleton loader',
            'Empty state box',
            'Error state box',
            'Quantity stepper',
            'Variant selector',
            'Pagination control',
            '### Inventory Summary',
            '- `implemented`: `3`',
            '- `partial`: `5`',
            '- `missing`: `0`',
            '### Theme Token Default Requirement Check (Source `#14` Note)',
            'These should use theme tokens by default.',
            'Current truth: `partial`',
            '## Deliverables Checklist Sync (Source `15`)',
            '1) Implement all component types above in registry',
            '4) Implement dynamic binding resolver (route/store/customer context)',
            '5) Provide demo pages (home, listing, detail, cart, checkout, account, orders)',
            '### Deliverables Summary',
            '- `implemented`: `0`',
            '- `equivalent`: `2`',
            '- `partial`: `3`',
            '- `gap`: `0`',
            '## AR-04 Follow-up Resolution (`Minimal docs: how to add a new ecommerce component`)',
            'This closes the `AR-04` assignment as a reconciliation/documentation task while preserving truthful partial status for the doc itself.',
            '## DoD Verdict (`ECM-03`)',
            'DoD: internal shared UI blocks tracked with consumers.',
            'Result: `PASS`',
            'Conclusion: `ECM-03` is `DONE`.',
            '## Follow-up Mapping (Non-blocking for `ECM-03` Closure)',
            '`ECM-04`',
            '`ECM-01`',
            '`ECM-02`',
            '`API-02`, `API-03`, `API-05`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            '| `Price component (handles sale price)` |',
            '| `Badge component` |',
            '| `Skeleton loader` |',
            '| `Empty state box` |',
            '| `Error state box` |',
            '| `Quantity stepper` |',
            '| `Variant selector` |',
            '| `Pagination control` |',
            '| `1) Implement all component types above in registry` | `equivalent` |',
            '| `2) Implement their renderer + controls config` | `partial` |',
            '| `3) Implement API client layer (auth, store scoping, error handling)` | `partial` |',
            '| `4) Implement dynamic binding resolver (route/store/customer context)` | `equivalent` |',
            '| `5) Provide demo pages (home, listing, detail, cart, checkout, account, orders)` | `partial` |',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'const appendEcomStateBox = (',
            'const appendSimpleEcomScaffold = (options: {',
            'const buildEcomSkeletonCards = (skeletonGrid: HTMLElement | null, count: number, includeImage: boolean) => {',
            '[data-webu-role="ecom-state-box"]',
            '[data-webu-role="ecom-pagination"]',
            '[data-webu-role="ecom-pdp-variant-select"]',
            '[data-webu-role="ecom-pdp-qty"]',
            '[data-webu-role="ecom-addtocart-qty"]',
            '[data-webu-role="ecom-cart-icon-badge"]',
            'buildEcomSkeletonCards(skeletonGrid, skeletonItems, showImage);',
            'const qtyWrap = container.querySelector<HTMLElement>(\'[data-webu-role="ecom-pdp-qty"]\');',
            'const badge = container.querySelector<HTMLElement>(\'[data-webu-role="ecom-cart-icon-badge"]\');',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'function currencyAmount(value, currency)',
            'function mountProductsWidget(container, options)',
            'function renderCartWidget(container)',
            'window.WebbyEcommerce = {',
            'mountProductsWidget: mountProductsWidget',
            'mountCartWidget: renderCartWidget',
            'return fetch(url, init).then(parseResponse);',
            "container.innerHTML = '<div style=\"padding:12px;font-size:14px;color:#64748b\">Loading products...</div>';",
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            "'customer',",
            "'ecommerce',",
            "Str::startsWith(\$canonicalPath, 'ecommerce.endpoints.')",
            "Str::startsWith(\$canonicalPath, 'customer.')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $bindingResolver);
        }

        foreach ([
            '{{site.name}}',
            '{{route.params.slug}}',
            '{{ecommerce.endpoints.products}}',
            'test_it_normalizes_legacy_paths_to_canonical_expressions',
        ] as $needle) {
            $this->assertStringContainsString($needle, $bindingResolverTest);
        }

        foreach ([
            'webu_ecom_product_grid_01',
            'webu_ecom_cart_page_01',
            'webu_ecom_checkout_form_01',
            'webu_ecom_auth_01',
            'webu_ecom_account_dashboard_01',
            'webu_ecom_orders_list_01',
            'webu_ecom_order_detail_01',
            'data-webby-ecommerce-products',
            'data-webby-ecommerce-categories',
            'data-webby-ecommerce-search',
            'data-webby-ecommerce-cart',
            'data-webby-ecommerce-checkout-form',
            'preview_state',
            'skeleton_items',
            'pagination_mode',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceCoverageContract);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'source binding shorthand namespaces (`{{store.*}}`, `{{product.*}}`, `{{category.*}}`, `{{cart.*}}`, `{{order.*}}`)',
            'Published runtime widget contract is not yet reusable across the full ecommerce component set (`products/cart` are mounted; many others are preview-only/runtime-gap at widget-hook level).',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecm01Doc);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '13 partial / 0 implemented / 0 missing',
            'published runtime `BuilderService` ecommerce widget auto-mount contract remains narrow (`products` + `cart`)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecm02Doc);
        }

        foreach ([
            'Minimal docs: how to add a new ecommerce component',
            '| `Minimal docs: how to add a new ecommerce component` | `partial` |',
            '- `ECM-03`: delivery artifacts/docs checklist, including explicit decision on minimal “how to add ecommerce component” guide',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ar04Doc);
        }

        foreach ([
            'default `webu-shop` pack does **not** currently include `login/account/orders/order-detail` page blueprints',
            'runtime supported but default pack gap',
            '/cart', '/checkout',
            '/account/orders',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api05Doc);
        }

        foreach ([
            '`{{key.path}}` syntax parse + normalization',
            'Runtime payload path resolution',
            'CmsCanonicalBindingResolverTest.php',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0003Doc);
        }

        foreach ([
            'assertPublishedRouteHtml($host, \'/cart\'',
            'assertPublishedRouteHtml($host, \'/checkout\'',
            'assertPublishedRouteHtml($host, \'/account/orders\'',
        ] as $needle) {
            $this->assertStringContainsString($needle, $templateStorefrontE2e);
        }

        foreach ([
            'CMS Canonical Component Registry Schema v1',
            '`type`',
            '`renderer`',
            '`controls_config`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $registryDoc);
        }

        foreach ([
            'CMS AI Component Registry Integration Workflow v1',
            'CmsAiComponentRegistryIntegrationWorkflowService',
            'preflight_only',
            'registry_entry',
        ] as $needle) {
            $this->assertStringContainsString($needle, $workflowDoc);
        }

        foreach ([
            'test_page_node_v1_schema_exists_and_requires_canonical_prop_groups',
            "['type', 'props', 'bindings', 'meta']",
        ] as $needle) {
            $this->assertStringContainsString($needle, $schemaContractsTest);
        }

        foreach ([
            'prepareActivationFromRawFeatureSpec',
            'ready_for_activation',
            'preflight_only',
        ] as $needle) {
            $this->assertStringContainsString($needle, $workflowTest);
        }
    }
}
