<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryEcommerceComponentSpecsEcm02BaselineGapAuditSyncTest extends TestCase
{
    public function test_ecm_02_progress_audit_doc_locks_ecommerce_component_matrix_endpoint_mapping_and_runtime_editor_gap_truth_and_closure_supersession(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_BASELINE_GAP_AUDIT_ECM_02_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_CLOSURE_AUDIT_ECM_02_2026_02_26.md');

        $rs0501AuditPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CATALOG_DISCOVERY_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_01_2026_02_25.md');
        $rs0502AuditPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_PDP_CART_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_02_2026_02_25.md');
        $rs0503AuditPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_03_2026_02_25.md');
        $rs1301AuditPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_13_01_2026_02_25.md');
        $api02AuditPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_PUBLIC_API_COVERAGE_AUDIT_API_02_2026_02_25.md');
        $api03AuditPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md');
        $aliasMapPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $ecommerceCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts');
        $c6ContractPath = base_path('resources/js/Pages/Project/__tests__/CmsEcommerceCustomerAuthAccountOrdersC6.contract.test.ts');
        $phase2C6SyncPath = base_path('tests/Unit/Phase2CustomerAuthAccountOrdersC6CompletionSummaryStatusSyncTest.php');
        $aliasMapSyncPath = base_path('tests/Unit/UniversalComponentLibrarySpecEquivalenceAliasMapTest.php');
        $api02SyncPath = base_path('tests/Unit/BackendBuilderPublicApiCoverageApi02SyncTest.php');
        $api03SyncPath = base_path('tests/Unit/BackendBuilderCheckoutOrdersPaymentsCustomerAuthApi03SyncTest.php');
        $builderCatalogRuntimeHooksTestPath = base_path('tests/Unit/BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest.php');
        $builderPdpCartRuntimeHooksTestPath = base_path('tests/Unit/BuilderEcommercePdpCartRuntimeHooksContractTest.php');
        $builderCheckoutOrdersRuntimeHooksTestPath = base_path('tests/Unit/BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest.php');
        $builderVerticalRuntimeHelpersTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $rs0501ClosureSyncPath = base_path('tests/Unit/UniversalComponentLibraryEcommerceCatalogDiscoveryComponentsRs0501ClosureAuditSyncTest.php');
        $rs0502ClosureSyncPath = base_path('tests/Unit/UniversalComponentLibraryEcommercePdpCartFlowComponentsRs0502ClosureAuditSyncTest.php');
        $rs0503ClosureSyncPath = base_path('tests/Unit/UniversalComponentLibraryEcommerceCheckoutOrderFlowComponentsRs0503ClosureAuditSyncTest.php');
        $rs1301ClosureSyncPath = base_path('tests/Unit/UniversalComponentLibraryAccountAuthComponentsRs1301ClosureAuditSyncTest.php');
        $closureSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryEcommerceComponentSpecsEcm02ClosureAuditSyncTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $closureDocPath,
            $rs0501AuditPath,
            $rs0502AuditPath,
            $rs0503AuditPath,
            $rs1301AuditPath,
            $api02AuditPath,
            $api03AuditPath,
            $aliasMapPath,
            $cmsPath,
            $builderServicePath,
            $ecommerceCoverageContractPath,
            $c6ContractPath,
            $phase2C6SyncPath,
            $aliasMapSyncPath,
            $api02SyncPath,
            $api03SyncPath,
            $builderCatalogRuntimeHooksTestPath,
            $builderPdpCartRuntimeHooksTestPath,
            $builderCheckoutOrdersRuntimeHooksTestPath,
            $builderVerticalRuntimeHelpersTestPath,
            $rs0501ClosureSyncPath,
            $rs0502ClosureSyncPath,
            $rs0503ClosureSyncPath,
            $rs1301ClosureSyncPath,
            $closureSyncTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $rs0501Audit = File::get($rs0501AuditPath);
        $rs0502Audit = File::get($rs0502AuditPath);
        $rs0503Audit = File::get($rs0503AuditPath);
        $rs1301Audit = File::get($rs1301AuditPath);
        $api02Audit = File::get($api02AuditPath);
        $api03Audit = File::get($api03AuditPath);
        $aliasMap = File::get($aliasMapPath);
        $cms = File::get($cmsPath);
        $builderService = File::get($builderServicePath);
        $ecommerceCoverageContract = File::get($ecommerceCoverageContractPath);
        $c6Contract = File::get($c6ContractPath);
        $phase2C6Sync = File::get($phase2C6SyncPath);
        $builderCatalogRuntimeHooksTest = File::get($builderCatalogRuntimeHooksTestPath);
        $builderPdpCartRuntimeHooksTest = File::get($builderPdpCartRuntimeHooksTestPath);
        $builderCheckoutOrdersRuntimeHooksTest = File::get($builderCheckoutOrdersRuntimeHooksTestPath);
        $builderVerticalRuntimeHelpersTest = File::get($builderVerticalRuntimeHelpersTestPath);

        foreach ([
            '# 1) COMPONENT: ProductGrid (Product List / Grid)',
            'type: `ecom.productGrid`',
            '# 2) COMPONENT: CategoryList',
            'type: `ecom.categoryList`',
            '# 3) COMPONENT: ProductSearchBar',
            'type: `ecom.productSearch`',
            '# 4) COMPONENT: ProductDetail',
            'type: `ecom.productDetail`',
            '# 5) COMPONENT: ProductGallery',
            'type: `ecom.productGallery`',
            '# 6) COMPONENT: AddToCartButton (standalone)',
            'type: `ecom.addToCart`',
            '# 7) COMPONENT: MiniCart (header dropdown)',
            'type: `ecom.miniCart`',
            '# 8) COMPONENT: CartPage',
            'type: `ecom.cartPage`',
            '# 9) COMPONENT: CheckoutPage',
            'type: `ecom.checkout`',
            '# 10) COMPONENT: Auth (Login/Register/OTP/Social)',
            'type: `ecom.auth`',
            '# 11) COMPONENT: AccountDashboard',
            'type: `ecom.account`',
            '# 12) COMPONENT: OrdersList',
            'type: `ecom.ordersList`',
            '# 13) COMPONENT: OrderDetail',
            'type: `ecom.orderDetail`',
            '- GET `/categories`',
            '- POST `/cart/items` (product_id/variant_id/qty)',
            '- GET `/customers/me`',
            '- GET `/customers/addresses`',
            '- GET `/orders/my`',
            '- GET `/orders/{{route.params.id}}`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `ECM-02` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_BASELINE_GAP_AUDIT_ECM_02_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_COMPONENT_SPEC_V1_DISCOVERY_TO_ORDER_CLOSURE_AUDIT_ECM_02_2026_02_26.md',
            'UniversalComponentLibraryEcommerceComponentSpecsEcm02BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryEcommerceComponentSpecsEcm02ClosureAuditSyncTest.php',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CATALOG_DISCOVERY_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_PDP_CART_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_02_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_03_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ACCOUNT_AUTH_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_13_01_2026_02_25.md',
            'BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest.php',
            'BuilderEcommercePdpCartRuntimeHooksContractTest.php',
            'BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            'WEBU_BACKEND_BUILDER_PUBLIC_API_COVERAGE_AUDIT_API_02_2026_02_25.md',
            'WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md',
            'CmsEcommerceBuilderCoverage.contract.test.ts',
            'CmsEcommerceCustomerAuthAccountOrdersC6.contract.test.ts',
            'Phase2CustomerAuthAccountOrdersC6CompletionSummaryStatusSyncTest.php',
            'UniversalComponentLibrarySpecEquivalenceAliasMapTest.php',
            'BackendBuilderPublicApiCoverageApi02SyncTest.php',
            'BackendBuilderCheckoutOrdersPaymentsCustomerAuthApi03SyncTest.php',
            '`✅` source ecommerce component rows `#1..#13` (`ProductGrid`..`OrderDetail`) consolidated into a single `ECM-02` per-component prop/API/control matrix with endpoint mappings and status labels',
            '`✅` `RS-05-01/02/03` + `RS-13-01` parity findings reused as primary truth source for discovery/PDP-cart/checkout-order/auth clusters (no duplicate overclaims)',
            '`✅` `ecom.addToCart`, `ecom.miniCart`, and `ecom.account` builder baseline coverage documented directly via `Cms.tsx` + ecommerce/C6 contract tests',
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
            'Status: `BASELINE_RECORDED`',
            '## Scope',
            'Source components audited:',
            '1. `ecom.productGrid`',
            '13. `ecom.orderDetail`',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            'alias map covers many decomposed ecommerce rows but not all source `ECM-02` type names',
            'published runtime `BuilderService` ecommerce widget auto-mount contract remains narrow (`products` + `cart`)',
            '## Audit Inputs Reviewed',
            '## What Was Done (This Pass)',
            '## Executive Result (`ECM-02`)',
            '13 partial / 0 implemented / 0 missing',
            '## Source → Canonical Mapping Coverage Notes (Before Matrix)',
            'source `ecom.addToCart` (no exact alias row)',
            'source `ecom.miniCart` (no exact alias row)',
            'source `ecom.checkout` (alias map uses split rows `checkoutForm` / `shippingSelector` / `paymentSelector` / `orderSummary`)',
            '## Per-Component Prop / API / Control Coverage Matrix (`ECM-02` Deliverable)',
            '`ecom.addToCart`',
            '`webu_ecom_add_to_cart_button_01`',
            '`ecom.miniCart`',
            '`webu_ecom_cart_icon_01`',
            '`ecom.account`',
            '`webu_ecom_account_dashboard_01`',
            '`ecom.checkout`',
            'Split canonical set: `webu_ecom_checkout_form_01` + `webu_ecom_shipping_selector_01` + `webu_ecom_payment_selector_01` + `webu_ecom_order_summary_01`',
            '### Component Grouping Summary (`implemented/partial/missing`)',
            '- `implemented`: `0`',
            '- `partial`: `13`',
            '- `missing`: `0`',
            '## Source Endpoint Mapping Index (Components Grouped, Endpoints Mapped)',
            '`ecom.categoryList`',
            'no dedicated public categories endpoint',
            '`ecom.account`',
            '`GET /customers/me`, `GET /customers/addresses`, `GET /orders/my`',
            '## Runtime Binding + Editor Control Verification Plan (`ECM-02` Deliverable)',
            '### A. Source-Key / Alias-Map Reconciliation (Component Identity Layer)',
            '### B. Editor Control Exactness Verification (Builder Schema / Preview Layer)',
            '### C. Runtime Binding / Widget Contract Verification (Published Runtime Layer)',
            '### D. Endpoint Contract Alignment (API Layer, Shared with `API-02` / `API-03`)',
            '## DoD Verdict (`ECM-02`)',
            'Therefore `ECM-02` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD Closure)',
            '## Conclusion',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'source_component_key": "ecom.productGrid"',
            'source_component_key": "ecom.productSearch"',
            'source_component_key": "ecom.categoryList"',
            'source_component_key": "ecom.productGallery"',
            'source_component_key": "ecom.productDetail"',
            'source_component_key": "ecom.ordersList"',
            'source_component_key": "ecom.orderDetail"',
            'source_component_key": "ecom.cart"',
            'source_component_key": "ecom.couponBox"',
            'source_component_key": "ecom.checkoutForm"',
            'source_component_key": "ecom.shippingSelector"',
            'source_component_key": "ecom.paymentSelector"',
            'source_component_key": "ecom.orderSummary"',
            'webu_ecom_add_to_cart_button_01',
            'webu_ecom_product_tabs_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            'source_component_key": "ecom.addToCart"',
            'source_component_key": "ecom.miniCart"',
            'source_component_key": "ecom.cartPage"',
            'source_component_key": "ecom.checkout"',
            'source_component_key": "ecom.account"',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $aliasMap);
        }

        foreach ([
            'webu_ecom_add_to_cart_button_01',
            'webu_ecom_cart_icon_01',
            'webu_ecom_cart_page_01',
            'webu_ecom_checkout_form_01',
            'webu_ecom_shipping_selector_01',
            'webu_ecom_payment_selector_01',
            'webu_ecom_order_summary_01',
            'webu_ecom_auth_01',
            'webu_ecom_account_dashboard_01',
            'webu_ecom_orders_list_01',
            'webu_ecom_order_detail_01',
            'data-webby-ecommerce-add-to-cart',
            'data-webby-ecommerce-cart-icon',
            'data-webby-ecommerce-account-dashboard',
            'data-webby-ecommerce-orders-list',
            'data-webby-ecommerce-order-detail',
            "if (normalized === 'webu_ecom_add_to_cart_button_01')",
            "if (normalized === 'webu_ecom_cart_icon_01')",
            "if (normalized === 'webu_ecom_account_dashboard_01')",
            "if (normalizedSectionType === 'webu_ecom_add_to_cart_button_01')",
            "if (normalizedSectionType === 'webu_ecom_cart_icon_01')",
            "if (normalizedSectionType === 'webu_ecom_orders_list_01')",
            'show_timeline',
            'show_shipping_block',
            'show_qty',
            'qty_default',
            'show_count_badge',
            'show_total',
            'show_stats',
            'show_addresses',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'variant_id: {',
            'product_id: {',
            'onSuccess',
            'showOrdersPreview',
            'ordersPreviewCount',
            'ordersUrl',
            'show_payment_status',
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
            'function addCartItem(cartId, payload) {',
            'function updateCartItem(cartId, itemId, payload) {',
            'function removeCartItem(cartId, itemId) {',
            'function getCart(cartId) {',
            'function checkout(cartId, payload) {',
            'function validateCheckout(cartId, payload) {',
            'function getPaymentOptions() {',
            'function getOrders(params) {',
            'function getOrder(orderId) {',
            'function startPayment(orderId, payload) {',
            'onCartUpdated: function (callback)',
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
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'data-webby-ecommerce-add-to-cart',
            'data-webby-ecommerce-account-dashboard',
            'orders_selector',
            'mountCartIconWidget',
            'mountAddToCartWidget',
            'mountAccountDashboardWidget',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $builderService);
        }

        foreach ([
            'CMS ecommerce builder component coverage contracts',
            'webu_ecom_add_to_cart_button_01',
            'webu_ecom_cart_icon_01',
            'webu_ecom_cart_page_01',
            'webu_ecom_checkout_form_01',
            'webu_ecom_auth_01',
            'webu_ecom_account_dashboard_01',
            'webu_ecom_orders_list_01',
            'webu_ecom_order_detail_01',
            'data-webby-ecommerce-add-to-cart',
            'data-webby-ecommerce-cart-icon',
            'data-webby-ecommerce-account-dashboard',
            'data-webby-ecommerce-orders-list',
            'data-webby-ecommerce-order-detail',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceCoverageContract);
        }

        foreach ([
            'CMS ecommerce C6 auth/account/orders builder contracts',
            'webu_ecom_account_dashboard_01',
            'webu_ecom_orders_list_01',
            'webu_ecom_order_detail_01',
            'unauthorized_title',
            'applyEcomPreviewState({',
            'includeUnauthorized: true',
            'resolveEcomAuthBackendFeatureToggles',
        ] as $needle) {
            $this->assertStringContainsString($needle, $c6Contract);
        }

        foreach ([
            'webu_ecom_account_dashboard_01',
            'data-webby-ecommerce-account-dashboard',
            'assertPublishedRouteHtml(\\$host, \'/account\'',
            'assertPublishedRouteHtml(\\$host, \'/account/orders\'',
            'assertPublishedRouteHtml(\\$host, \'/account/orders/\'.\\$orderId',
        ] as $needle) {
            $this->assertStringContainsString($needle, $phase2C6Sync);
        }

        foreach ([
            'BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest',
            "'search_selector' => '[data-webby-ecommerce-search]'",
            "'categories_selector' => '[data-webby-ecommerce-categories]'",
            'mountSearchWidget: mountSearchWidget,',
            'mountCategoriesWidget: mountCategoriesWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderCatalogRuntimeHooksTest);
        }

        foreach ([
            'BuilderEcommercePdpCartRuntimeHooksContractTest',
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
            'BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest',
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
            '## Catalog Discovery Parity Matrix',
            '`ecom.productGrid`',
            '`ecom.productSearch`',
            '`ecom.categoryList`',
            '## Endpoint Contract Verification (`GET /products`, `/products?q=...`, `/categories`)',
            'no dedicated public `GET /categories` endpoint',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0501Audit);
        }

        foreach ([
            '## PDP / Cart Flow Parity Matrix',
            '`ecom.productGallery`',
            '`ecom.productDetail`',
            '`ecom.cart`',
            '`ecom.couponBox`',
            '## Endpoint Contract Verification (`GET /products/:slug`, `/cart`, `/cart/items`, `/coupons/apply`)',
            'coupon JS APIs were not found in `BuilderService`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0502Audit);
        }

        foreach ([
            '## Checkout / Order Flow Parity Matrix',
            '`ecom.checkoutForm`',
            '`ecom.shippingSelector`',
            '`ecom.paymentSelector`',
            '`ecom.orderSummary`',
            '`ecom.ordersList`',
            '`ecom.orderDetail`',
            '## Endpoint Contract Verification (`/checkout/validate`, `/orders`, `/shipping/calc`, `/payments/methods`, `/payments/init`, `/orders/my`, `/orders/{id}`)',
            'no public customer-auth order history endpoint found',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs0503Audit);
        }

        foreach ([
            '## Account / Auth Parity Matrix',
            '`auth.auth`',
            '## Endpoint Contract Verification (`/customers/login`, `/auth/otp/*`, `/auth/google`, `/auth/facebook`, `/customers/me`)',
            'no dedicated `/customers/me` JSON route',
        ] as $needle) {
            $this->assertStringContainsString($needle, $rs1301Audit);
        }

        foreach ([
            '| `GET /products` | `GET /public/sites/{site}/ecommerce/products`',
            '| `GET /categories` | no dedicated public categories route found',
            '| `POST /cart/items` | `POST /public/sites/{site}/ecommerce/carts/{cart}/items`',
            '| `POST /coupons/apply` | `POST /public/sites/{site}/ecommerce/carts/{cart}/coupon`',
            '| `POST /shipping/calc` | `POST /public/sites/{site}/ecommerce/carts/{cart}/shipping/options` + `PUT /.../shipping` to select',
            '| `GET /public/settings` | `GET /public/sites/{site}/settings`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api02Audit);
        }

        foreach ([
            '| `8.2 POST /orders` (Idempotency-Key required) | `POST /public/sites/{site}/ecommerce/carts/{cart}/checkout`',
            '| `8.3 GET /orders/my` (customer auth) | no public customer-auth order list endpoint found',
            '| `8.4 GET /orders/{id}` | no public customer-auth order detail endpoint found',
            '| `9.2 POST /payments/init` | `POST /public/sites/{site}/ecommerce/orders/{order}/payments/start`',
            '| `10.1 POST /customers/register` | `POST /register` (web/session registration)',
            '| `10.2 POST /customers/login` | `POST /login` (web/session login)',
            '| `10.3 POST /auth/otp/request` | no endpoint found',
            '| `10.4 POST /auth/otp/verify` | no endpoint found',
            '| `10.5 POST /auth/google` | social auth exists as redirect/callback GET routes',
            '| `10.6 POST /auth/facebook` | social auth exists as redirect/callback GET routes',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api03Audit);
        }
    }
}
