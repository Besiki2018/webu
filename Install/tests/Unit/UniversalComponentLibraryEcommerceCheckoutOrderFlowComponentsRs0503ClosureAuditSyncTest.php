<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryEcommerceCheckoutOrderFlowComponentsRs0503ClosureAuditSyncTest extends TestCase
{
    public function test_rs_05_03_closure_audit_locks_checkout_order_endpoint_variants_widget_hooks_and_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_03_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_RUNTIME_ENDPOINT_VARIANTS_WIDGET_HOOKS_CLOSURE_AUDIT_RS_05_03_2026_02_26.md');

        $routesPath = base_path('routes/web.php');
        $controllerPath = base_path('app/Http/Controllers/Ecommerce/PublicStorefrontController.php');
        $serviceContractPath = base_path('app/Ecommerce/Contracts/EcommercePublicStorefrontServiceContract.php');
        $servicePath = base_path('app/Ecommerce/Services/EcommercePublicStorefrontService.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');

        $featureTestPath = base_path('tests/Feature/Ecommerce/EcommercePublicApiTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryEcommerceCheckoutOrderFlowComponentsRs0503BaselineGapAuditSyncTest.php');
        $api03SyncTestPath = base_path('tests/Unit/BackendBuilderCheckoutOrdersPaymentsCustomerAuthApi03SyncTest.php');
        $api03AuditDocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md');
        $ecommerceCheckoutAcceptanceTestPath = base_path('tests/Feature/Ecommerce/EcommerceCheckoutAcceptanceTest.php');
        $ecommerceShippingAcceptanceTestPath = base_path('tests/Feature/Ecommerce/EcommerceShippingAcceptanceTest.php');
        $builderCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts');
        $activationUnitTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $baselineDocPath,
            $closureDocPath,
            $routesPath,
            $controllerPath,
            $serviceContractPath,
            $servicePath,
            $builderServicePath,
            $featureTestPath,
            $runtimeContractTestPath,
            $baselineSyncTestPath,
            $api03SyncTestPath,
            $api03AuditDocPath,
            $ecommerceCheckoutAcceptanceTestPath,
            $ecommerceShippingAcceptanceTestPath,
            $builderCoverageContractPath,
            $activationUnitTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $baselineDoc = File::get($baselineDocPath);
        $closureDoc = File::get($closureDocPath);
        $routes = File::get($routesPath);
        $controller = File::get($controllerPath);
        $serviceContract = File::get($serviceContractPath);
        $service = File::get($servicePath);
        $builderService = File::get($builderServicePath);
        $featureTest = File::get($featureTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);
        $api03SyncTest = File::get($api03SyncTestPath);
        $api03AuditDoc = File::get($api03AuditDocPath);

        foreach ([
            '## 5.8 ecom.checkoutForm',
            '## 5.9 ecom.shippingSelector',
            '## 5.10 ecom.paymentSelector',
            '## 5.11 ecom.orderSummary',
            '## 5.12 ecom.ordersList',
            '## 5.13 ecom.orderDetail',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-05-03` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_03_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_RUNTIME_ENDPOINT_VARIANTS_WIDGET_HOOKS_CLOSURE_AUDIT_RS_05_03_2026_02_26.md',
            'UniversalComponentLibraryEcommerceCheckoutOrderFlowComponentsRs0503BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryEcommerceCheckoutOrderFlowComponentsRs0503ClosureAuditSyncTest.php',
            'BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest.php',
            'EcommercePublicApiTest.php',
            'EcommerceCheckoutAcceptanceTest.php',
            'EcommerceShippingAcceptanceTest.php',
            'EcommercePublicStorefrontService.php',
            '`✅` baseline parity/gap audit is preserved and superseded by a closure audit covering checkout-validate path variant, customer-order endpoint variants, and standalone checkout/order widget runtime hooks',
            '`✅` public ecommerce runtime now exposes path-variant checkout preflight and customer-order endpoints (`/carts/{cart}/checkout/validate`, `/customer-orders`, `/customer-orders/{order}`) with feature coverage',
            '`✅` `BuilderService` ecommerce runtime now mounts standalone checkout/order widget selectors and exports `validateCheckout`, `getOrders`, and `getOrder` helpers',
            '`✅` DoD closure achieved via baseline parity evidence + endpoint variant coverage + widget runtime contract coverage + existing checkout/shipping/payment acceptance flows',
            '`⚠️` source `/orders/my` and `/orders/{id}` endpoints remain accepted path variants (`/customer-orders*`) to preserve `API-03` exact-route sync truth',
            '`⚠️` minimal OpenAPI docs lag remains for new path-variant checkout/customer-orders endpoints (non-blocking for `RS-05-03` DoD)',
            '`🧪` RS-05-03 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'Conclusion: `RS-05-03` remains `IN_PROGRESS`.',
            'there is no separate `/checkout/validate` endpoint',
            'What is not currently evidenced in `BuilderService`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $baselineDoc);
        }

        foreach ([
            'Status: `DONE`',
            '## Goal (`RS-05-03` Closure Pass)',
            '## ✅ What Was Done (Closure Pass)',
            'checkout preflight endpoint support through path variant route',
            'customer order runtime endpoints through path variants',
            'standalone `BuilderService` ecommerce widget hooks',
            '## Executive Result (`RS-05-03`)',
            '`RS-05-03` is now **DoD-complete** as a checkout/order flow parity verification task.',
            '## Closure Delta Against Baseline (`2026-02-25`)',
            'resolved_via_path_variant',
            'resolved_via_runtime_hooks',
            'accepted_variant',
            'docs_lag_non_blocking',
            '## Endpoint Variant Closure (`routes/web.php` + controller/service)',
            'POST /{site}/ecommerce/carts/{cart}/checkout/validate',
            'GET /{site}/ecommerce/customer-orders',
            'GET /{site}/ecommerce/customer-orders/{order}',
            'API-03` exact-route sync preservation',
            'source `GET /orders/my` -> runtime `GET /customer-orders`',
            'source `GET /orders/{id}` -> runtime `GET /customer-orders/{order}`',
            '## Runtime Hook Closure (`BuilderService`)',
            'mountCheckoutFormWidget(container, options)',
            'mountOrderSummaryWidget(container, options)',
            'mountShippingSelectorWidget(container, options)',
            'mountPaymentSelectorWidget(container, options)',
            'mountOrdersListWidget(container, options)',
            'mountOrderDetailWidget(container, options)',
            'validateCheckout(cartId, payload)',
            'getOrders(params)',
            'getOrder(orderId)',
            'data-webby-ecommerce-checkout-form-state',
            'data-webby-ecommerce-order-summary-state',
            'data-webby-ecommerce-orders-list-state',
            '## Feature / Runtime Evidence Added (Closure Pass)',
            'test_public_checkout_validate_endpoint_returns_preflight_shipping_payment_and_checkout_contract',
            'test_public_checkout_validate_endpoint_rejects_empty_cart',
            'test_public_customer_orders_endpoints_require_auth_and_are_scoped_to_authenticated_customer_email',
            'BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest.php',
            '## Remaining Exactness Gaps (Truthful, Non-Blocking for `RS-05-03` DoD)',
            'Source route exactness remains accepted variant:',
            '`/orders/my` vs `/customer-orders`',
            '`/orders/{id}` vs `/customer-orders/{order}`',
            'Minimal OpenAPI docs were not expanded in this closure pass',
            '## DoD Closure Matrix (`RS-05-03`)',
            '## DoD Verdict (`RS-05-03`)',
            '`RS-05-03` passes and is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            "Route::post('/{site}/ecommerce/carts/{cart}/checkout/validate'",
            "Route::get('/{site}/ecommerce/customer-orders'",
            "Route::get('/{site}/ecommerce/customer-orders/{order}'",
            "Route::post('/{site}/ecommerce/orders/{order}/payments/start'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }
        $this->assertStringNotContainsString("Route::get('/{site}/ecommerce/orders/my'", $routes);
        $this->assertStringNotContainsString("Route::get('/{site}/ecommerce/orders/{order}'", $routes);

        foreach ([
            'public function checkoutValidate(Request $request, Site $site, EcommerceCart $cart): JsonResponse',
            'public function customerOrders(Request $request, Site $site): JsonResponse',
            'public function customerOrder(Request $request, Site $site, EcommerceOrder $order): JsonResponse',
            "'cart_identity_token' => ['nullable', 'string', 'max:191']",
            "'per_page' => ['nullable', 'integer', 'min:1', 'max:50']",
            '$payload = $this->storefront->checkoutValidate($site, $cart, $validated, $request->user());',
            '$payload = $this->storefront->customerOrders($site, $validated, $request->user());',
            '$payload = $this->storefront->customerOrder($site, $order, $request->user());',
        ] as $needle) {
            $this->assertStringContainsString($needle, $controller);
        }

        foreach ([
            'public function checkoutValidate(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null): array;',
            'public function customerOrders(Site $site, array $filters = [], ?User $viewer = null): array;',
            'public function customerOrder(Site $site, EcommerceOrder $order, ?User $viewer = null): array;',
        ] as $needle) {
            $this->assertStringContainsString($needle, $serviceContract);
        }

        foreach ([
            'public function checkoutValidate(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null): array',
            'public function customerOrders(Site $site, array $filters = [], ?User $viewer = null): array',
            'public function customerOrder(Site $site, EcommerceOrder $order, ?User $viewer = null): array',
            'private function assertAuthenticatedCustomerUser(?User $viewer): User',
            'Cart is empty.',
            'customer_auth_required',
            "'checkout_endpoint' => route('public.sites.ecommerce.carts.checkout'",
            "'orders' => \$items,",
            "'customer_email' => \$customer->email,",
        ] as $needle) {
            $this->assertStringContainsString($needle, $service);
        }

        foreach ([
            "'checkout_validate_url_pattern' =>",
            "'customer_orders_url' =>",
            "'customer_order_url_pattern' =>",
            "'checkout_form_selector' => '[data-webby-ecommerce-checkout-form]'",
            "'order_summary_selector' => '[data-webby-ecommerce-order-summary]'",
            "'shipping_selector' => '[data-webby-ecommerce-shipping-selector]'",
            "'payment_selector' => '[data-webby-ecommerce-payment-selector]'",
            "'orders_list_selector' => '[data-webby-ecommerce-orders-list]'",
            "'order_detail_selector' => '[data-webby-ecommerce-order-detail]'",
            'function validateCheckout(cartId, payload) {',
            'function getOrders(params) {',
            'function getOrder(orderId) {',
            'function mountCheckoutFormWidget(container, options) {',
            'function mountOrderSummaryWidget(container, options) {',
            'function mountShippingSelectorWidget(container, options) {',
            'function mountPaymentSelectorWidget(container, options) {',
            'function mountOrdersListWidget(container, options) {',
            'function mountOrderDetailWidget(container, options) {',
            'mountCheckoutFormWidget(node, {});',
            'mountOrderSummaryWidget(node, {});',
            'mountShippingSelectorWidget(node, {});',
            'mountPaymentSelectorWidget(node, {});',
            'mountOrdersListWidget(node, {});',
            'mountOrderDetailWidget(node, {});',
            'validateCheckout: validateCheckout,',
            'getOrders: getOrders,',
            'getOrder: getOrder,',
            'mountCheckoutFormWidget: mountCheckoutFormWidget,',
            'mountOrderSummaryWidget: mountOrderSummaryWidget,',
            'mountShippingSelectorWidget: mountShippingSelectorWidget,',
            'mountPaymentSelectorWidget: mountPaymentSelectorWidget,',
            'mountOrdersListWidget: mountOrdersListWidget,',
            'mountOrderDetailWidget: mountOrderDetailWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'test_public_checkout_validate_endpoint_returns_preflight_shipping_payment_and_checkout_contract',
            'test_public_checkout_validate_endpoint_rejects_empty_cart',
            'test_public_customer_orders_endpoints_require_auth_and_are_scoped_to_authenticated_customer_email',
            "route('public.sites.ecommerce.carts.checkout.validate'",
            "route('public.sites.ecommerce.customer_orders.index'",
            "route('public.sites.ecommerce.customer_orders.show'",
            "->assertJsonPath('validation.valid', true)",
            "->assertJsonPath('error', 'Cart is empty.')",
            "->assertJsonPath('reason', 'customer_auth_required')",
            "->assertJsonPath('pagination.total', 1)",
        ] as $needle) {
            $this->assertStringContainsString($needle, $featureTest);
        }

        foreach ([
            'BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest',
            'function mountCheckoutFormWidget(container, options) {',
            'function mountOrdersListWidget(container, options) {',
            'validateCheckout: validateCheckout,',
            'getOrders: getOrders,',
            'getOrder: getOrder,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }

        foreach ([
            '`8.1 POST /checkout/validate`',
            '`8.3 GET /orders/my`',
            'Route::post(\'/{site}/ecommerce/carts/{cart}/checkout\'',
            'assertStringNotContainsString("Route::get(\'/{site}/ecommerce/orders/my\'", $webRoutes)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api03SyncTest);
        }

        foreach ([
            '| `8.1 POST /checkout/validate` |',
            '| `8.3 GET /orders/my` (customer auth) |',
            'no dedicated endpoint; validation/precondition checks happen inside checkout execution',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api03AuditDoc);
        }
    }
}
