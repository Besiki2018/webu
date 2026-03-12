<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryEcommerceComponentSpecsEcm02ClosureAuditSyncTest extends TestCase
{
    public function test_ecm_02_closure_audit_locks_discovery_to_order_component_grouping_endpoint_mapping_runtime_surface_and_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_BASELINE_GAP_AUDIT_ECM_02_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_CLOSURE_AUDIT_ECM_02_2026_02_26.md');

        $aliasMapPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $webRoutesPath = base_path('routes/web.php');
        $ecommerceOpenApiPath = base_path('docs/openapi/webu-ecommerce-minimal.v1.openapi.yaml');
        $authCustomersOpenApiPath = base_path('docs/openapi/webu-auth-customers-minimal.v1.openapi.yaml');

        $builderCatalogRuntimeHooksTestPath = base_path('tests/Unit/BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest.php');
        $builderPdpCartRuntimeHooksTestPath = base_path('tests/Unit/BuilderEcommercePdpCartRuntimeHooksContractTest.php');
        $builderCheckoutOrdersRuntimeHooksTestPath = base_path('tests/Unit/BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest.php');
        $builderVerticalRuntimeHelpersTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $rs0501ClosureSyncPath = base_path('tests/Unit/UniversalComponentLibraryEcommerceCatalogDiscoveryComponentsRs0501ClosureAuditSyncTest.php');
        $rs0502ClosureSyncPath = base_path('tests/Unit/UniversalComponentLibraryEcommercePdpCartFlowComponentsRs0502ClosureAuditSyncTest.php');
        $rs0503ClosureSyncPath = base_path('tests/Unit/UniversalComponentLibraryEcommerceCheckoutOrderFlowComponentsRs0503ClosureAuditSyncTest.php');
        $rs1301ClosureSyncPath = base_path('tests/Unit/UniversalComponentLibraryAccountAuthComponentsRs1301ClosureAuditSyncTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryEcommerceComponentSpecsEcm02BaselineGapAuditSyncTest.php');
        $ecm01ClosureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_GLOBAL_CONTRACTS_CLOSURE_AUDIT_ECM_01_2026_02_26.md');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $baselineDocPath,
            $closureDocPath,
            $aliasMapPath,
            $cmsPath,
            $builderServicePath,
            $webRoutesPath,
            $ecommerceOpenApiPath,
            $authCustomersOpenApiPath,
            $builderCatalogRuntimeHooksTestPath,
            $builderPdpCartRuntimeHooksTestPath,
            $builderCheckoutOrdersRuntimeHooksTestPath,
            $builderVerticalRuntimeHelpersTestPath,
            $rs0501ClosureSyncPath,
            $rs0502ClosureSyncPath,
            $rs0503ClosureSyncPath,
            $rs1301ClosureSyncPath,
            $baselineSyncTestPath,
            $ecm01ClosureDocPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $closureDoc = File::get($closureDocPath);
        $aliasMap = File::get($aliasMapPath);
        $cms = File::get($cmsPath);
        $builderService = File::get($builderServicePath);
        $webRoutes = File::get($webRoutesPath);
        $ecommerceOpenApi = File::get($ecommerceOpenApiPath);
        $authCustomersOpenApi = File::get($authCustomersOpenApiPath);
        $builderCatalogRuntimeHooksTest = File::get($builderCatalogRuntimeHooksTestPath);
        $builderPdpCartRuntimeHooksTest = File::get($builderPdpCartRuntimeHooksTestPath);
        $builderCheckoutOrdersRuntimeHooksTest = File::get($builderCheckoutOrdersRuntimeHooksTestPath);
        $builderVerticalRuntimeHelpersTest = File::get($builderVerticalRuntimeHelpersTestPath);
        $rs0501ClosureSync = File::get($rs0501ClosureSyncPath);
        $rs0502ClosureSync = File::get($rs0502ClosureSyncPath);
        $rs0503ClosureSync = File::get($rs0503ClosureSyncPath);
        $rs1301ClosureSync = File::get($rs1301ClosureSyncPath);
        $baselineSyncTest = File::get($baselineSyncTestPath);
        $ecm01ClosureDoc = File::get($ecm01ClosureDocPath);

        foreach ([
            '# 1) COMPONENT: ProductGrid (Product List / Grid)',
            '# 13) COMPONENT: OrderDetail',
            'type: `ecom.addToCart`',
            'type: `ecom.miniCart`',
            'type: `ecom.account`',
            'type: `ecom.ordersList`',
            'type: `ecom.orderDetail`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `ECM-02` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_BASELINE_GAP_AUDIT_ECM_02_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_CLOSURE_AUDIT_ECM_02_2026_02_26.md',
            'UniversalComponentLibraryEcommerceComponentSpecsEcm02BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryEcommerceComponentSpecsEcm02ClosureAuditSyncTest.php',
            'BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest.php',
            'BuilderEcommercePdpCartRuntimeHooksContractTest.php',
            'BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            '`✅` baseline `ECM-02` matrix is preserved and superseded by a closure audit with closure-current component grouping (`implemented/partial/missing`) and endpoint mappings backed by `RS-05-*` + `RS-13-01` closures',
            '`✅` `BuilderService` ecommerce runtime auto-mount/export contract now covers discovery, checkout/orders, and auth/account-profile/security clusters (beyond baseline `products/cart` narrowness)',
            '`✅` `ECM-02` DoD closure is now evidenced with explicit closure-current grouping summary and mapped path variants (`/customer-orders*`, site-scoped public auth/account JSON routes, split checkout widgets)',
            '`⚠️` alias-map exact source-key coverage remains partial for `ecom.addToCart` / `ecom.miniCart` / `ecom.cartPage` / `ecom.checkout` / `ecom.account`, and these exactness gaps are retained as non-blocking closure follow-ups',
            '`⚠️` closure-current matrix still keeps `ecom.addToCart`, `ecom.miniCart`, and `ecom.account` as `partial` due standalone runtime/API/control exactness gaps',
            '`🧪` ECM-02 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Closure Rationale (Why `ECM-02` Can Be `DONE`)',
            '## What Changed Since Baseline (Closure Delta)',
            'Runtime Widget Contract Breadth Expanded for Core `ECM-02` Clusters',
            'search_selector',
            'categories_selector',
            'checkout_form_selector',
            'orders_list_selector',
            'order_detail_selector',
            'auth_selector',
            'account_profile_selector',
            'account_security_selector',
            'Public Endpoint Coverage Improved for Checkout / Orders / Auth Clusters',
            'POST /{site}/ecommerce/carts/{cart}/checkout/validate',
            'GET /{site}/ecommerce/customer-orders',
            'GET /{site}/ecommerce/customer-orders/{order}',
            '## Closure-Current Component Grouping Summary (`ECM-02`)',
            '- `implemented`: `10`',
            '- `partial`: `3`',
            '- `missing`: `0`',
            '### `partial` Rows (Closure-Current)',
            'ecom.addToCart',
            'ecom.miniCart',
            'ecom.account',
            '## DoD Verdict (`ECM-02`)',
            'Conclusion: `ECM-02` is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            'source_component_key": "ecom.productGrid"',
            'source_component_key": "ecom.productSearch"',
            'source_component_key": "ecom.categoryList"',
            'source_component_key": "ecom.productDetail"',
            'source_component_key": "ecom.ordersList"',
            'source_component_key": "ecom.orderDetail"',
            'webu_ecom_add_to_cart_button_01',
            'webu_ecom_cart_icon_01',
            'webu_ecom_cart_page_01',
            'webu_ecom_checkout_form_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            'webu_ecom_add_to_cart_button_01',
            'webu_ecom_cart_icon_01',
            'webu_ecom_account_dashboard_01',
            'data-webby-ecommerce-add-to-cart',
            'data-webby-ecommerce-cart-icon',
            'data-webby-ecommerce-account-dashboard',
            'data-webby-ecommerce-orders-list',
            'data-webby-ecommerce-order-detail',
            "if (normalizedSectionType === 'webu_ecom_add_to_cart_button_01')",
            "if (normalizedSectionType === 'webu_ecom_cart_icon_01')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            "'search_selector' => '[data-webby-ecommerce-search]'",
            "'categories_selector' => '[data-webby-ecommerce-categories]'",
            "'checkout_form_selector' => '[data-webby-ecommerce-checkout-form]'",
            "'orders_list_selector' => '[data-webby-ecommerce-orders-list]'",
            "'order_detail_selector' => '[data-webby-ecommerce-order-detail]'",
            "'auth_selector' => '[data-webby-ecommerce-auth]'",
            "'account_profile_selector' => '[data-webby-ecommerce-account-profile]'",
            "'account_security_selector' => '[data-webby-ecommerce-account-security]'",
            'function validateCheckout(cartId, payload) {',
            'function getOrders(params) {',
            'function getOrder(orderId) {',
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
            'mountAddToCartWidget',
            'mountCartIconWidget',
            'mountAccountDashboardWidget',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $builderService);
        }

        foreach ([
            "Route::post('/{site}/ecommerce/carts/{cart}/checkout/validate'",
            "Route::get('/{site}/ecommerce/customer-orders'",
            "Route::get('/{site}/ecommerce/customer-orders/{order}'",
            "Route::post('/{site}/customers/login'",
            "Route::get('/{site}/customers/me'",
            "Route::put('/{site}/customers/me'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }

        foreach ([
            '/public/sites/{site}/ecommerce/carts/{cart}/checkout:',
            '/public/sites/{site}/ecommerce/orders/{order}/payments/start:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceOpenApi);
        }
        $this->assertStringNotContainsString('/public/sites/{site}/ecommerce/carts/{cart}/checkout/validate:', $ecommerceOpenApi);
        $this->assertStringNotContainsString('/public/sites/{site}/ecommerce/customer-orders:', $ecommerceOpenApi);
        $this->assertStringNotContainsString('/public/sites/{site}/ecommerce/customer-orders/{order}:', $ecommerceOpenApi);

        foreach ([
            '/public/sites/{site}/customers/login:',
            '/public/sites/{site}/customers/me:',
            '/public/sites/{site}/auth/otp/request:',
            '/public/sites/{site}/auth/otp/verify:',
            '/public/sites/{site}/auth/google:',
            '/public/sites/{site}/auth/facebook:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $authCustomersOpenApi);
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
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_CLOSURE_AUDIT_ECM_02_2026_02_26.md',
            'BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
        ] as $needle) {
            $this->assertStringContainsString($needle, $baselineSyncTest);
        }

        foreach ([
            'Status: `DONE`',
            'Runtime Widget Contract Breadth Is Materially Expanded',
            'search_selector',
            'checkout_form_selector',
            'account_security_selector',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecm01ClosureDoc);
        }
    }
}
