<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryEcommerceGlobalContractsEcm01ClosureAuditSyncTest extends TestCase
{
    public function test_ecm_01_closure_audit_locks_global_ecommerce_contract_reconciliation_runtime_widget_breadth_and_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_GLOBAL_CONTRACTS_BASELINE_GAP_AUDIT_ECM_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_GLOBAL_CONTRACTS_CLOSURE_AUDIT_ECM_01_2026_02_26.md');

        $schemaPath = base_path('docs/architecture/schemas/cms-canonical-page-node.v1.schema.json');
        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $bindingResolverPath = base_path('app/Services/CmsCanonicalBindingResolver.php');

        $builderCatalogRuntimeHooksTestPath = base_path('tests/Unit/BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest.php');
        $builderPdpCartRuntimeHooksTestPath = base_path('tests/Unit/BuilderEcommercePdpCartRuntimeHooksContractTest.php');
        $builderCheckoutOrdersRuntimeHooksTestPath = base_path('tests/Unit/BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest.php');
        $builderVerticalRuntimeHelpersTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $rs0501ClosureSyncPath = base_path('tests/Unit/UniversalComponentLibraryEcommerceCatalogDiscoveryComponentsRs0501ClosureAuditSyncTest.php');
        $rs0502ClosureSyncPath = base_path('tests/Unit/UniversalComponentLibraryEcommercePdpCartFlowComponentsRs0502ClosureAuditSyncTest.php');
        $rs0503ClosureSyncPath = base_path('tests/Unit/UniversalComponentLibraryEcommerceCheckoutOrderFlowComponentsRs0503ClosureAuditSyncTest.php');
        $rs1301ClosureSyncPath = base_path('tests/Unit/UniversalComponentLibraryAccountAuthComponentsRs1301ClosureAuditSyncTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryEcommerceGlobalContractsEcm01BaselineGapAuditSyncTest.php');
        $ecm02ClosureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_CLOSURE_AUDIT_ECM_02_2026_02_26.md');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $baselineDocPath,
            $closureDocPath,
            $schemaPath,
            $cmsPath,
            $builderServicePath,
            $bindingResolverPath,
            $builderCatalogRuntimeHooksTestPath,
            $builderPdpCartRuntimeHooksTestPath,
            $builderCheckoutOrdersRuntimeHooksTestPath,
            $builderVerticalRuntimeHelpersTestPath,
            $rs0501ClosureSyncPath,
            $rs0502ClosureSyncPath,
            $rs0503ClosureSyncPath,
            $rs1301ClosureSyncPath,
            $baselineSyncTestPath,
            $ecm02ClosureDocPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $closureDoc = File::get($closureDocPath);
        $schema = File::get($schemaPath);
        $cms = File::get($cmsPath);
        $builderService = File::get($builderServicePath);
        $bindingResolver = File::get($bindingResolverPath);
        $builderCatalogRuntimeHooksTest = File::get($builderCatalogRuntimeHooksTestPath);
        $builderPdpCartRuntimeHooksTest = File::get($builderPdpCartRuntimeHooksTestPath);
        $builderCheckoutOrdersRuntimeHooksTest = File::get($builderCheckoutOrdersRuntimeHooksTestPath);
        $builderVerticalRuntimeHelpersTest = File::get($builderVerticalRuntimeHelpersTestPath);
        $rs0501ClosureSync = File::get($rs0501ClosureSyncPath);
        $rs0502ClosureSync = File::get($rs0502ClosureSyncPath);
        $rs0503ClosureSync = File::get($rs0503ClosureSyncPath);
        $rs1301ClosureSync = File::get($rs1301ClosureSyncPath);
        $baselineSyncTest = File::get($baselineSyncTestPath);
        $ecm02ClosureDoc = File::get($ecm02ClosureDocPath);

        foreach ([
            '# 0) Global Rules (applies to all ecommerce components)',
            '## 0.1 Base Props Contract (all components)',
            '## 0.2 UI states',
            '## 0.3 Styling system',
            '## 0.4 Data binding',
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
            '`✅` baseline `ECM-01` audit is preserved and superseded by a closure audit that reconciles expanded `BuilderService` ecommerce runtime widget breadth across discovery/checkout/orders/auth-account clusters',
            '`✅` reusable ecommerce contract audit now passes with closure-current test hooks including `BuilderEcommerce*RuntimeHooksContractTest.php`, `BuilderServicePublicVerticalRuntimeHelpersContractTest.php`, and family closure sync tests (`RS-05-01/02/03`, `RS-13-01`)',
            '`✅` published `BuilderService` ecommerce widget contract is no longer limited to `products/cart`: selector/mount/helper coverage now includes `search/categories`, checkout/order widgets, and auth/account-profile/security widget mounts',
            '`⚠️` source ecommerce `meta` exactness (`storeId/locale/currency/routeParams`), shared `success` preview-state strictness, and shorthand binding namespace exactness remain partial and are retained as non-blocking closure gaps',
            '`🧪` ECM-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Closure Rationale (Why `ECM-01` Can Be `DONE`)',
            '## What Changed Since Baseline (Closure Delta)',
            'Reusable Published Runtime Widget Contract Breadth Is Materially Expanded',
            'search_selector',
            'categories_selector',
            'checkout_form_selector',
            'orders_list_selector',
            'order_detail_selector',
            'auth_selector',
            'account_profile_selector',
            'account_security_selector',
            'mountSearchWidget',
            'mountCategoriesWidget',
            'mountCheckoutFormWidget',
            'mountOrdersListWidget',
            'mountOrderDetailWidget',
            'mountAuthWidget',
            'mountAccountProfileWidget',
            'mountAccountSecurityWidget',
            '## Reusable Ecommerce Global Contract Closure Summary (`ECM-01`)',
            '| ecommerce props/controls contract matrix | `pass` |',
            '| state/style support audit | `pass` |',
            '| reusable contract audit with test hooks listed | `pass` |',
            '## Remaining Exactness Gaps (Truthful, Non-Blocking for `ECM-01`)',
            'applyEcomPreviewState',
            '`success` state strictness remains `partial`',
            'source shorthand binding namespaces',
            '## DoD Verdict (`ECM-01`)',
            'Conclusion: `ECM-01` is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            '"meta"',
            '"schema_version"',
            '"label"',
            '"locked"',
            '"hidden"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $schema);
        }
        foreach ([
            '"storeId"',
            '"routeParams"',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $schema);
        }

        foreach ([
            'const applyEcomPreviewState = (options: {',
            'preview_state',
            'loading_title',
            'error_title',
            'empty_title',
            'skeleton_items',
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
            'listCategories: listCategories,',
            'validateCheckout: validateCheckout,',
            'getOrders: getOrders,',
            'getOrder: getOrder,',
            'mountSearchWidget: mountSearchWidget,',
            'mountCategoriesWidget: mountCategoriesWidget,',
            'mountCheckoutFormWidget: mountCheckoutFormWidget,',
            'mountOrdersListWidget: mountOrdersListWidget,',
            'mountOrderDetailWidget: mountOrderDetailWidget,',
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
            "'search_selector' => '[data-webby-ecommerce-search]'",
            "'categories_selector' => '[data-webby-ecommerce-categories]'",
            'mountSearchWidget: mountSearchWidget,',
            'mountCategoriesWidget: mountCategoriesWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderCatalogRuntimeHooksTest);
        }

        foreach ([
            "'product_detail_selector' => '[data-webby-ecommerce-product-detail]'",
            "'product_gallery_selector' => '[data-webby-ecommerce-product-gallery]'",
            "'coupon_selector' => '[data-webby-ecommerce-coupon]'",
            'mountProductDetailWidget: mountProductDetailWidget,',
            'mountProductGalleryWidget: mountProductGalleryWidget,',
            'mountCouponWidget: mountCouponWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderPdpCartRuntimeHooksTest);
        }

        foreach ([
            "'checkout_form_selector' => '[data-webby-ecommerce-checkout-form]'",
            "'orders_list_selector' => '[data-webby-ecommerce-orders-list]'",
            "'order_detail_selector' => '[data-webby-ecommerce-order-detail]'",
            'validateCheckout: validateCheckout,',
            'getOrders: getOrders,',
            'getOrder: getOrder,',
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
            'DoD-complete',
            'window.WebbyEcommerce',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0501ClosureSync.$rs0502ClosureSync.$rs0503ClosureSync.$rs1301ClosureSync);
        }

        foreach ([
            'closure_supersession',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_GLOBAL_CONTRACTS_CLOSURE_AUDIT_ECM_01_2026_02_26.md',
            'BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
        ] as $needle) {
            $this->assertStringContainsString($needle, $baselineSyncTest);
        }

        foreach ([
            'Status: `DONE`',
            'Summary (`implemented/partial/missing`)',
            'ecom.addToCart',
            'ecom.miniCart',
            'ecom.account',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecm02ClosureDoc);
        }
    }
}
