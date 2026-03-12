<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryEcommerceGlobalContractsEcm01BaselineGapAuditSyncTest extends TestCase
{
    public function test_ecm_01_progress_audit_doc_locks_global_ecommerce_contract_matrix_state_style_and_binding_gap_truth_and_closure_supersession(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_GLOBAL_CONTRACTS_BASELINE_GAP_AUDIT_ECM_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_GLOBAL_CONTRACTS_CLOSURE_AUDIT_ECM_01_2026_02_26.md');

        $pageNodeSchemaPath = base_path('docs/architecture/schemas/cms-canonical-page-node.v1.schema.json');
        $cmsCanonicalSchemaContractsTestPath = base_path('tests/Unit/CmsCanonicalSchemaContractsTest.php');
        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $bindingResolverPath = base_path('app/Services/CmsCanonicalBindingResolver.php');
        $bindingResolverTestPath = base_path('tests/Unit/CmsCanonicalBindingResolverTest.php');
        $bindingValidatorTestPath = base_path('tests/Unit/CmsBindingExpressionValidatorTest.php');
        $dynamicHookTestPath = base_path('tests/Unit/CmsDynamicControlHookServiceTest.php');
        $dynamicThemeUxContractPath = base_path('resources/js/Pages/Project/__tests__/CmsDynamicAndThemeUx.contract.test.ts');
        $ecommerceCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts');
        $activationUnitTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');
        $bindingCompatibilityUnitTestPath = base_path('tests/Unit/UniversalBindingNamespaceCompatibilityP5F5Test.php');
        $responsiveStateWrapperSyncPath = base_path('tests/Unit/Phase3ResponsiveStateWrapperSummaryStatusSyncTest.php');
        $builderCatalogRuntimeHooksTestPath = base_path('tests/Unit/BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest.php');
        $builderPdpCartRuntimeHooksTestPath = base_path('tests/Unit/BuilderEcommercePdpCartRuntimeHooksContractTest.php');
        $builderCheckoutOrdersRuntimeHooksTestPath = base_path('tests/Unit/BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest.php');
        $builderVerticalRuntimeHelpersTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $closureSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryEcommerceGlobalContractsEcm01ClosureAuditSyncTest.php');

        $rs0001AuditPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_BASE_NODE_TABS_REGISTRY_AUDIT_RS_00_01_2026_02_25.md');
        $rs0002AuditPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_STYLE_GROUP_PARITY_AUDIT_RS_00_02_2026_02_25.md');
        $rs0003AuditPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_DATA_BINDING_RULE_COVERAGE_AUDIT_RS_00_03_2026_02_25.md');
        $ar02AuditPath = base_path('docs/qa/LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_BUILDER_CORE_REGISTRY_DYNAMIC_BINDING_COMPONENT_REQUIREMENTS_RECONCILIATION_AUDIT_AR_02_2026_02_25.md');
        $rs0501AuditPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CATALOG_DISCOVERY_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_01_2026_02_25.md');
        $rs0502AuditPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_PDP_CART_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_02_2026_02_25.md');
        $rs0503AuditPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_03_2026_02_25.md');
        $rs1301AuditPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_13_01_2026_02_25.md');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $closureDocPath,
            $pageNodeSchemaPath,
            $cmsCanonicalSchemaContractsTestPath,
            $cmsPath,
            $builderServicePath,
            $bindingResolverPath,
            $bindingResolverTestPath,
            $bindingValidatorTestPath,
            $dynamicHookTestPath,
            $dynamicThemeUxContractPath,
            $ecommerceCoverageContractPath,
            $activationUnitTestPath,
            $bindingCompatibilityUnitTestPath,
            $responsiveStateWrapperSyncPath,
            $builderCatalogRuntimeHooksTestPath,
            $builderPdpCartRuntimeHooksTestPath,
            $builderCheckoutOrdersRuntimeHooksTestPath,
            $builderVerticalRuntimeHelpersTestPath,
            $closureSyncTestPath,
            $rs0001AuditPath,
            $rs0002AuditPath,
            $rs0003AuditPath,
            $ar02AuditPath,
            $rs0501AuditPath,
            $rs0502AuditPath,
            $rs0503AuditPath,
            $rs1301AuditPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $pageNodeSchema = File::get($pageNodeSchemaPath);
        $cmsCanonicalSchemaContractsTest = File::get($cmsCanonicalSchemaContractsTestPath);
        $cms = File::get($cmsPath);
        $builderService = File::get($builderServicePath);
        $bindingResolver = File::get($bindingResolverPath);
        $bindingResolverTest = File::get($bindingResolverTestPath);
        $bindingValidatorTest = File::get($bindingValidatorTestPath);
        $dynamicHookTest = File::get($dynamicHookTestPath);
        $dynamicThemeUxContract = File::get($dynamicThemeUxContractPath);
        $ecommerceCoverageContract = File::get($ecommerceCoverageContractPath);
        $builderCatalogRuntimeHooksTest = File::get($builderCatalogRuntimeHooksTestPath);
        $builderPdpCartRuntimeHooksTest = File::get($builderPdpCartRuntimeHooksTestPath);
        $builderCheckoutOrdersRuntimeHooksTest = File::get($builderCheckoutOrdersRuntimeHooksTestPath);
        $builderVerticalRuntimeHelpersTest = File::get($builderVerticalRuntimeHelpersTestPath);
        $rs0002Audit = File::get($rs0002AuditPath);
        $rs0003Audit = File::get($rs0003AuditPath);
        $ar02Audit = File::get($ar02AuditPath);

        foreach ([
            '# 0) Global Rules (applies to all ecommerce components)',
            '## 0.1 Base Props Contract (all components)',
            '- props.content',
            '- props.style',
            '- props.advanced',
            '- props.data (API bindings config)',
            '- props.states (normal/hover/focus/active style overrides)',
            '- props.responsive (desktop/tablet/mobile overrides)',
            '### Standard meta for ecommerce components',
            '- `storeId` (resolved from context)',
            '- `currency` (resolved from store settings)',
            '- `routeParams` (slug/id)',
            '## 0.2 UI states',
            '- success state (where relevant, e.g. add-to-cart)',
            'These states must be styleable.',
            '## 0.3 Styling system',
            '- responsive columns/layout',
            '- hover states where relevant',
            '## 0.4 Data binding',
            '- `{{store.*}}`',
            '- `{{route.params.*}}`',
            '- `{{customer.*}}`',
            '- `{{product.*}}`',
            '- `{{category.*}}`',
            '- `{{cart.*}}`',
            '- `{{order.*}}`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `ECM-01` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_GLOBAL_CONTRACTS_BASELINE_GAP_AUDIT_ECM_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_GLOBAL_CONTRACTS_CLOSURE_AUDIT_ECM_01_2026_02_26.md',
            'UniversalComponentLibraryEcommerceGlobalContractsEcm01BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryEcommerceGlobalContractsEcm01ClosureAuditSyncTest.php',
            'BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest.php',
            'BuilderEcommercePdpCartRuntimeHooksContractTest.php',
            'BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            'CmsCanonicalSchemaContractsTest.php',
            'CmsCanonicalBindingResolverTest.php',
            'CmsBindingExpressionValidatorTest.php',
            'CmsDynamicControlHookServiceTest.php',
            'CmsDynamicAndThemeUx.contract.test.ts',
            'CmsEcommerceBuilderCoverage.contract.test.ts',
            'Phase3ResponsiveStateWrapperSummaryStatusSyncTest.php',
            '`✅` source `0.1`-`0.4` ecommerce global rules audited into reusable contract matrix with truthful `pass/equivalent/partial` labels and test hooks list',
            '`✅` base prop groups (`content/data/style/advanced/responsive/states`) + canonical `bindings` baseline are cross-linked to `RS-00-01` / `CmsCanonicalSchemaContractsTest`',
            '`✅` ecommerce preview-state scaffolding + dynamic binding UX/runtime validation baseline is evidenced (`applyEcomPreviewState`, `preview_state`, `loading/error/empty/skeleton`, `Dynamic` / `Clear Dynamic`)',
            '`✅` baseline `ECM-01` audit is preserved and superseded by a closure audit that reconciles expanded `BuilderService` ecommerce runtime widget breadth across discovery/checkout/orders/auth-account clusters',
            '`✅` reusable ecommerce contract audit now passes with closure-current test hooks including `BuilderEcommerce*RuntimeHooksContractTest.php`, `BuilderServicePublicVerticalRuntimeHelpersContractTest.php`, and family closure sync tests (`RS-05-01/02/03`, `RS-13-01`)',
            '`✅` published `BuilderService` ecommerce widget contract is no longer limited to `products/cart`: selector/mount/helper coverage now includes `search/categories`, checkout/order widgets, and auth/account-profile/security widget mounts',
            '`⚠️` source ecommerce `meta` exactness (`storeId/locale/currency/routeParams`), shared `success` preview-state strictness, and shorthand binding namespace exactness remain partial and are retained as non-blocking closure gaps',
            '`🧪` ECM-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '## Scope',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            'applyEcomPreviewState',
            'shared ecommerce preview-state helper (`applyEcomPreviewState`) covers `loading/empty/error/skeleton` (+ `unauthorized`) but has no explicit shared `success` state path',
            'source binding shorthand namespaces (`{{store.*}}`, `{{product.*}}`, `{{category.*}}`, `{{cart.*}}`, `{{order.*}}`)',
            '## Audit Inputs Reviewed',
            'UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_BASE_NODE_TABS_REGISTRY_AUDIT_RS_00_01_2026_02_25.md',
            'LEGACY_REFERENCE_ARCHIVE_ECOMMERCE_FULL_INTEGRATION_BUILDER_CORE_REGISTRY_DYNAMIC_BINDING_COMPONENT_REQUIREMENTS_RECONCILIATION_AUDIT_AR_02_2026_02_25.md',
            '## What Was Done (This Pass)',
            '## Executive Result (`ECM-01`)',
            '## Ecommerce Props / Controls Contract Matrix (Source `0.1`-`0.4`)',
            'standard ecommerce meta (`storeId`, `locale`, `currency`, `routeParams`)',
            'ecommerce builder coverage contracts evidence `loading`, `empty`, `error`, `skeleton`, plus `unauthorized` for auth/account variants',
            'reusable published runtime widget hooks (global ecommerce contract practicality)',
            '## State / Style Support Audit (`ECM-01` Deliverable)',
            '### A. Ecommerce UI State Scaffolding Baseline (Builder Preview)',
            '### B. State Styleability Baseline (Reusable Foundations vs Ecommerce Strictness)',
            '### C. Source `0.3` Styling System Coverage (Global Ecommerce Interpretation)',
            '## Data Binding Audit (Ecommerce Global Interpretation, Source `0.4`)',
            '### Namespace + Syntax Matrix (Source `0.4`)',
            'Cross-check:',
            '`AR-02` already documented the shorthand example mismatch',
            '## Current Test Hooks / Evidence Locks (Reusable While `ECM-01` Is Open)',
            '## DoD Verdict (`ECM-01`)',
            'Therefore `ECM-01` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD)',
            '## Conclusion',
            'not DoD-complete yet',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            '"required": [',
            '"content"',
            '"data"',
            '"style"',
            '"advanced"',
            '"responsive"',
            '"states"',
            '"bindings"',
            '"meta"',
            '"schema_version"',
            '"label"',
            '"locked"',
            '"hidden"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $pageNodeSchema);
        }

        foreach ([
            '"storeId"',
            '"routeParams"',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $pageNodeSchema);
        }

        foreach ([
            'test_page_node_v1_schema_exists_and_requires_canonical_prop_groups',
            "['content', 'data', 'style', 'advanced', 'responsive', 'states']",
            "['type', 'props', 'bindings', 'meta']",
            "properties.meta.properties.schema_version.type",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsCanonicalSchemaContractsTest);
        }

        foreach ([
            'const applyEcomPreviewState = (options: {',
            'unauthorizedNode?: HTMLElement | null;',
            'preview_state',
            'loading_title',
            'error_title',
            'empty_title',
            'skeleton_items',
            '{{route.params.slug}}',
            '{{route.params.id}}',
            'data-webby-ecommerce-products',
            'data-webby-ecommerce-cart',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'successNode?: HTMLElement | null;',
            'success_title',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $cms);
        }

        foreach ([
            "'global_helper' => 'window.WebbyEcommerce'",
            "'products_selector' => '[data-webby-ecommerce-products]'",
            "'cart_selector' => '[data-webby-ecommerce-cart]'",
            "'search_selector' => '[data-webby-ecommerce-search]'",
            "'categories_selector' => '[data-webby-ecommerce-categories]'",
            "'checkout_form_selector' => '[data-webby-ecommerce-checkout-form]'",
            "'orders_list_selector' => '[data-webby-ecommerce-orders-list]'",
            "'order_detail_selector' => '[data-webby-ecommerce-order-detail]'",
            "'auth_selector' => '[data-webby-ecommerce-auth]'",
            "'account_profile_selector' => '[data-webby-ecommerce-account-profile]'",
            "'account_security_selector' => '[data-webby-ecommerce-account-security]'",
            'window.WebbyEcommerce = {',
            'listCategories: listCategories,',
            'validateCheckout: validateCheckout,',
            'getOrders: getOrders,',
            'getOrder: getOrder,',
            'mountProductsWidget: mountProductsWidget,',
            'mountSearchWidget: mountSearchWidget,',
            'mountCategoriesWidget: mountCategoriesWidget,',
            'mountCheckoutFormWidget: mountCheckoutFormWidget,',
            'mountOrdersListWidget: mountOrdersListWidget,',
            'mountOrderDetailWidget: mountOrderDetailWidget,',
            'mountCartWidget: renderCartWidget,',
            'window.WebbyEcommerce.mountAuthWidget = mountAuthWidget;',
            'window.WebbyEcommerce.mountAccountProfileWidget = mountAccountProfileWidget;',
            'window.WebbyEcommerce.mountAccountSecurityWidget = mountAccountSecurityWidget;',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            "'project',",
            "'site',",
            "'route',",
            "'customer',",
            "'ecommerce',",
            "'content',",
        ] as $needle) {
            $this->assertStringContainsString($needle, $bindingResolver);
        }

        foreach ([
            "'store',",
            "'product',",
            "'category',",
            "'cart',",
            "'order',",
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $bindingResolver);
        }

        foreach ([
            'test_it_resolves_canonical_paths_against_runtime_payload',
            "{{ecommerce.endpoints.products}}",
            "{{route.params.slug}}",
            'test_it_marks_deferred_semantic_bindings_without_throwing',
        ] as $needle) {
            $this->assertStringContainsString($needle, $bindingResolverTest);
        }

        foreach ([
            'test_it_accepts_canonical_route_bindings_for_universal_vertical_detail_components',
            'webu_ecom_order_detail_01',
            'missing_route_product_slug_binding',
            'invalid_route_product_slug_binding',
        ] as $needle) {
            $this->assertStringContainsString($needle, $bindingValidatorTest);
        }

        foreach ([
            'test_it_builds_dynamic_hooks_for_text_image_and_link_fields',
            "'text'",
            "'image'",
            "'link'",
            'binding_namespaces',
        ] as $needle) {
            $this->assertStringContainsString($needle, $dynamicHookTest);
        }

        foreach ([
            'CMS dynamic bindings and theme UX contracts',
            'const dynamicControls = bindingMeta.dynamic_controls;',
            'supports_dynamic',
            'binding_namespaces',
            "{t('Dynamic')}",
            "{t('Clear')}",
        ] as $needle) {
            $this->assertStringContainsString($needle, $dynamicThemeUxContract);
        }

        foreach ([
            'CMS ecommerce builder component coverage contracts',
            'data-webby-ecommerce-products',
            'data-webby-ecommerce-categories',
            'data-webby-ecommerce-search',
            'data-webby-ecommerce-checkout-form',
            'data-webby-ecommerce-auth',
            'data-webby-ecommerce-orders-list',
            'preview_state',
            'loading_title',
            'error_title',
            'empty_title',
            'skeleton_items',
            'applyEcomPreviewState',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceCoverageContract);
        }

        foreach ([
            'BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest',
            "'search_selector' => '[data-webby-ecommerce-search]'",
            "'categories_selector' => '[data-webby-ecommerce-categories]'",
            'function mountSearchWidget(container, options) {',
            'function mountCategoriesWidget(container, options) {',
            'listCategories: listCategories,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderCatalogRuntimeHooksTest);
        }

        foreach ([
            'BuilderEcommercePdpCartRuntimeHooksContractTest',
            "'product_detail_selector' => '[data-webby-ecommerce-product-detail]'",
            "'product_gallery_selector' => '[data-webby-ecommerce-product-gallery]'",
            "'coupon_selector' => '[data-webby-ecommerce-coupon]'",
            'function mountProductDetailWidget(container, options) {',
            'function mountProductGalleryWidget(container, options) {',
            'function mountCouponWidget(container, options) {',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderPdpCartRuntimeHooksTest);
        }

        foreach ([
            'BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest',
            "'checkout_form_selector' => '[data-webby-ecommerce-checkout-form]'",
            "'orders_list_selector' => '[data-webby-ecommerce-orders-list]'",
            "'order_detail_selector' => '[data-webby-ecommerce-order-detail]'",
            'function validateCheckout(cartId, payload) {',
            'function getOrders(params) {',
            'function getOrder(orderId) {',
            'mountOrdersListWidget: mountOrdersListWidget,',
            'mountOrderDetailWidget: mountOrderDetailWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderCheckoutOrdersRuntimeHooksTest);
        }

        foreach ([
            "'auth_selector' => '[data-webby-ecommerce-auth]'",
            "'account_profile_selector' => '[data-webby-ecommerce-account-profile]'",
            "'account_security_selector' => '[data-webby-ecommerce-account-security]'",
            'window.WebbyEcommerce.mountAuthWidget = mountAuthWidget;',
            'window.WebbyEcommerce.mountAccountProfileWidget = mountAccountProfileWidget;',
            'window.WebbyEcommerce.mountAccountSecurityWidget = mountAccountSecurityWidget;',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderVerticalRuntimeHelpersTest);
        }

        foreach ([
            'Summary Counts (Top-Level Style Groups)',
            '- `implemented`: `1` (`Custom CSS`)',
            '- `partial`: `11`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0002Audit);
        }

        foreach ([
            '## Binding Support Matrix by Field Type (`text` / `image` / `link`)',
            '`{{key.path}}`',
            'Source Namespace Coverage (`project/page/customer/ecommerce/booking/content`)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0003Audit);
        }

        foreach ([
            'syntax examples (`{{store.name}}`, `{{product.title}}`, `{{cart.total}}`, `{{customer.name}}`)',
            'source example shorthand (`{{product.title}}`, `{{cart.total}}`)',
            'canonical namespaced expressions (`{{ecommerce.*}}`, `{{route.params.*}}`)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ar02Audit);
        }
    }
}
