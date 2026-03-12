<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryEcommerceCheckoutOrderFlowComponentsRs0503BaselineGapAuditSyncTest extends TestCase
{
    public function test_rs_05_03_progress_audit_doc_locks_checkout_order_flow_parity_contract_and_runtime_gap_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_03_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CHECKOUT_ORDER_FLOW_COMPONENTS_PARITY_RUNTIME_ENDPOINT_VARIANTS_WIDGET_HOOKS_CLOSURE_AUDIT_RS_05_03_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $webRoutesPath = base_path('routes/web.php');
        $publicStorefrontControllerPath = base_path('app/Http/Controllers/Ecommerce/PublicStorefrontController.php');
        $storefrontServicePath = base_path('app/Ecommerce/Services/EcommercePublicStorefrontService.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $ecommerceOpenApiPath = base_path('docs/openapi/webu-ecommerce-minimal.v1.openapi.yaml');
        $authCustomersOpenApiPath = base_path('docs/openapi/webu-auth-customers-minimal.v1.openapi.yaml');
        $ecommercePublicApiTestPath = base_path('tests/Feature/Ecommerce/EcommercePublicApiTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderEcommerceCheckoutOrdersRuntimeHooksContractTest.php');
        $closureSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryEcommerceCheckoutOrderFlowComponentsRs0503ClosureAuditSyncTest.php');
        $ecommerceCheckoutAcceptanceTestPath = base_path('tests/Feature/Ecommerce/EcommerceCheckoutAcceptanceTest.php');
        $ecommerceShippingAcceptanceTestPath = base_path('tests/Feature/Ecommerce/EcommerceShippingAcceptanceTest.php');
        $api03AuditDocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md');
        $api03SyncTestPath = base_path('tests/Unit/BackendBuilderCheckoutOrdersPaymentsCustomerAuthApi03SyncTest.php');
        $ecommerceCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts');
        $activationFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts');
        $activationUnitTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');
        $aliasMapPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $closureDocPath,
            $cmsPath,
            $webRoutesPath,
            $publicStorefrontControllerPath,
            $storefrontServicePath,
            $builderServicePath,
            $ecommerceOpenApiPath,
            $authCustomersOpenApiPath,
            $ecommercePublicApiTestPath,
            $runtimeContractTestPath,
            $closureSyncTestPath,
            $ecommerceCheckoutAcceptanceTestPath,
            $ecommerceShippingAcceptanceTestPath,
            $api03AuditDocPath,
            $api03SyncTestPath,
            $ecommerceCoverageContractPath,
            $activationFrontendContractPath,
            $activationUnitTestPath,
            $aliasMapPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $cms = File::get($cmsPath);
        $webRoutes = File::get($webRoutesPath);
        $publicStorefrontController = File::get($publicStorefrontControllerPath);
        $storefrontService = File::get($storefrontServicePath);
        $builderService = File::get($builderServicePath);
        $ecommerceOpenApi = File::get($ecommerceOpenApiPath);
        $authCustomersOpenApi = File::get($authCustomersOpenApiPath);
        $ecommercePublicApiTest = File::get($ecommercePublicApiTestPath);
        $ecommerceCheckoutAcceptanceTest = File::get($ecommerceCheckoutAcceptanceTestPath);
        $ecommerceShippingAcceptanceTest = File::get($ecommerceShippingAcceptanceTestPath);
        $api03AuditDoc = File::get($api03AuditDocPath);
        $api03SyncTest = File::get($api03SyncTestPath);
        $ecommerceCoverageContract = File::get($ecommerceCoverageContractPath);
        $activationFrontendContract = File::get($activationFrontendContractPath);
        $activationUnitTest = File::get($activationUnitTestPath);
        $aliasMap = File::get($aliasMapPath);

        foreach ([
            '## 5.8 ecom.checkoutForm',
            'Content: fieldsConfig (name/email/phone/address), requireLogin toggle',
            'Data: POST /checkout/validate + POST /orders',
            'Style: form styles, error styles',
            '## 5.9 ecom.shippingSelector',
            'Content: displayMode, defaultMethod',
            'Data: POST /shipping/calc',
            '## 5.10 ecom.paymentSelector',
            'Content: showLogos, defaultMethod',
            'Data: GET /payments/methods + POST /payments/init',
            '## 5.11 ecom.orderSummary',
            'Content: showTax, showDiscount',
            'Data: GET /cart or returned totals from validate',
            '## 5.12 ecom.ordersList',
            'Data: GET /orders/my',
            '## 5.13 ecom.orderDetail',
            'Data: GET /orders/{id}',
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
            'WEBU_BACKEND_BUILDER_CHECKOUT_ORDERS_PAYMENTS_CUSTOMER_AUTH_AUDIT_API_03_2026_02_25.md',
            'BackendBuilderCheckoutOrdersPaymentsCustomerAuthApi03SyncTest.php',
            '`✅` checkout/order flow parity matrix documented for `ecom.checkoutForm`, `ecom.shippingSelector`, `ecom.paymentSelector`, `ecom.orderSummary`, `ecom.ordersList`, `ecom.orderDetail`',
            '`✅` shipping/payment selector endpoint coverage and checkout/payment start happy-path baseline evidenced via `EcommercePublicApiTest.php` + `EcommerceCheckoutAcceptanceTest.php` + `EcommerceShippingAcceptanceTest.php`',
            '`✅` `API-03` endpoint matrix findings reused for checkout/orders/payments/customer-auth contract truth (`/checkout/validate`, `/orders`, `/orders/my`, `/orders/{id}`, `/payments/methods`, `/payments/init`)',
            '`✅` baseline parity/gap audit is preserved and superseded by a closure audit covering checkout-validate path variant, customer-order endpoint variants, and standalone checkout/order widget runtime hooks',
            '`✅` public ecommerce runtime now exposes path-variant checkout preflight and customer-order endpoints (`/carts/{cart}/checkout/validate`, `/customer-orders`, `/customer-orders/{order}`) with feature coverage',
            '`✅` `BuilderService` ecommerce runtime now mounts standalone checkout/order widget selectors and exports `validateCheckout`, `getOrders`, and `getOrder` helpers',
            '`✅` DoD closure achieved via baseline parity evidence + endpoint variant coverage + widget runtime contract coverage + existing checkout/shipping/payment acceptance flows',
            '`⚠️` source exactness gaps remain in builder schemas (`fieldsConfig/requireLogin`, `displayMode/defaultMethod`, `showLogos/defaultMethod`, `showTax/showDiscount`) and several components still use generic preview-state shell behavior',
            '`⚠️` source `/orders/my` and `/orders/{id}` endpoints remain accepted path variants (`/customer-orders*`) to preserve `API-03` exact-route sync truth',
            '`⚠️` minimal OpenAPI docs lag remains for new path-variant checkout/customer-orders endpoints (non-blocking for `RS-05-03` DoD)',
            '`🧪` RS-05-03 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '## Scope',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            '## Audit Inputs Reviewed',
            '## What Was Done (This Pass)',
            '## Executive Result (`RS-05-03`)',
            '## Checkout / Order Flow Parity Matrix',
            '### Matrix (`content/style/panel-preview/runtime-data/endpoint/flow-error/gating/tests`)',
            '`ecom.checkoutForm`',
            '`ecom.shippingSelector`',
            '`ecom.paymentSelector`',
            '`ecom.orderSummary`',
            '`ecom.ordersList`',
            '`ecom.orderDetail`',
            '`webu_ecom_checkout_form_01`',
            '`webu_ecom_shipping_selector_01`',
            '`webu_ecom_payment_selector_01`',
            '`webu_ecom_order_summary_01`',
            '`webu_ecom_orders_list_01`',
            '`webu_ecom_order_detail_01`',
            '## Endpoint Contract Verification (`/checkout/validate`, `/orders`, `/shipping/calc`, `/payments/methods`, `/payments/init`, `/orders/my`, `/orders/{id}`)',
            '### Source-to-Current Endpoint Matrix',
            '`gap`',
            '`partial_equivalent`',
            '`equivalent_split`',
            '`exact_semantics_path_variant`',
            '## Ecommerce-Only Gating Baseline (Source Vertical Constraint)',
            'builderSectionAvailabilityMatrix',
            'requiredModules: [MODULE_ECOMMERCE]',
            '## Builder Preview Parity and Source-Control Exactness Findings',
            'Component-specific preview-update coverage (mixed)',
            'Source-control exactness gaps (builder schema)',
            '`fieldsConfig`, `requireLogin`',
            '`displayMode`, `defaultMethod`',
            '`showLogos`, `defaultMethod`',
            '`showTax`, `showDiscount`',
            '## Checkout / Shipping / Payment / Orders Flow + Error Evidence (Backend)',
            'checkout -> payment start',
            'source DoD sequence says `validate -> payment init -> order`',
            'there is no separate `/checkout/validate` endpoint',
            '## Runtime Widget / Binding Status (`checkoutForm`, `orderSummary`, `shippingSelector`, `paymentSelector`, `ordersList`, `orderDetail`)',
            'What is not currently evidenced in `BuilderService`',
            'mountCheckoutFormWidget',
            'getOrders`, `getOrder`',
            '## OpenAPI / Controller Drift and Customer-Auth Baseline Gaps',
            'Checkout response status drift (`200` docs vs `201` runtime)',
            '## DoD Verdict (`RS-05-03`)',
            'Conclusion: `RS-05-03` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD)',
            '## Conclusion',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'webu_ecom_checkout_form_01',
            'webu_ecom_order_summary_01',
            'webu_ecom_shipping_selector_01',
            'webu_ecom_payment_selector_01',
            'webu_ecom_orders_list_01',
            'webu_ecom_order_detail_01',
            'data-webby-ecommerce-checkout-form',
            'data-webby-ecommerce-order-summary',
            'data-webby-ecommerce-shipping-selector',
            'data-webby-ecommerce-payment-selector',
            'data-webby-ecommerce-orders-list',
            'data-webby-ecommerce-order-detail',
            'show_company',
            'show_notes',
            'subtotal_label',
            'shipping_label',
            'tax_label',
            'total_label',
            'orders_count',
            'pagination_mode',
            'show_pagination',
            'order_id',
            'order_status',
            'show_timeline',
            'show_shipping_block',
            "if (normalized === 'webu_ecom_checkout_form_01')",
            "if (normalized === 'webu_ecom_order_summary_01')",
            "if (normalized === 'webu_ecom_shipping_selector_01' || normalized === 'webu_ecom_payment_selector_01')",
            "if (normalized === 'webu_ecom_orders_list_01')",
            "if (normalized === 'webu_ecom_order_detail_01')",
            "'webu_ecom_checkout_form_01',",
            "'webu_ecom_order_summary_01',",
            "'webu_ecom_shipping_selector_01',",
            "'webu_ecom_payment_selector_01',",
            "'webu_ecom_orders_list_01',",
            "'webu_ecom_order_detail_01',",
            'applyEcomPreviewState',
            "if (normalizedSectionType === 'webu_ecom_orders_list_01')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'fieldsConfig',
            'requireLogin',
            'displayMode',
            'defaultMethod',
            'showLogos',
            'showTax',
            'showDiscount',
            "if (normalizedSectionType === 'webu_ecom_checkout_form_01')",
            "if (normalizedSectionType === 'webu_ecom_order_summary_01')",
            "if (normalizedSectionType === 'webu_ecom_shipping_selector_01')",
            "if (normalizedSectionType === 'webu_ecom_payment_selector_01')",
            "if (normalizedSectionType === 'webu_ecom_order_detail_01')",
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $cms);
        }

        foreach ([
            'source_component_key": "ecom.checkoutForm"',
            'webu_ecom_checkout_form_01',
            'source_component_key": "ecom.shippingSelector"',
            'webu_ecom_shipping_selector_01',
            'source_component_key": "ecom.paymentSelector"',
            'webu_ecom_payment_selector_01',
            'source_component_key": "ecom.orderSummary"',
            'webu_ecom_order_summary_01',
            'source_component_key": "ecom.ordersList"',
            'webu_ecom_orders_list_01',
            'source_component_key": "ecom.orderDetail"',
            'webu_ecom_order_detail_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            "Route::get('/{site}/ecommerce/payment-options'",
            "Route::post('/{site}/ecommerce/carts/{cart}/shipping/options'",
            "Route::put('/{site}/ecommerce/carts/{cart}/shipping'",
            "Route::post('/{site}/ecommerce/carts/{cart}/checkout/validate'",
            "Route::post('/{site}/ecommerce/carts/{cart}/checkout'",
            "Route::get('/{site}/ecommerce/customer-orders'",
            "Route::get('/{site}/ecommerce/customer-orders/{order}'",
            "Route::post('/{site}/ecommerce/orders/{order}/payments/start'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }
        $this->assertStringNotContainsString("Route::get('/{site}/ecommerce/orders/my'", $webRoutes);
        $this->assertStringNotContainsString("Route::get('/{site}/ecommerce/orders/{order}'", $webRoutes);
        $this->assertStringContainsString("Route::get('/ecommerce/orders/{order}'", $webRoutes);

        foreach ([
            'public function paymentOptions(Request $request, Site $site): JsonResponse',
            'public function shippingOptions(Request $request, Site $site, EcommerceCart $cart): JsonResponse',
            'public function updateShipping(Request $request, Site $site, EcommerceCart $cart): JsonResponse',
            'public function checkoutValidate(Request $request, Site $site, EcommerceCart $cart): JsonResponse',
            'public function checkout(Request $request, Site $site, EcommerceCart $cart): JsonResponse',
            'public function customerOrders(Request $request, Site $site): JsonResponse',
            'public function customerOrder(Request $request, Site $site, EcommerceOrder $order): JsonResponse',
            'public function startPayment(Request $request, Site $site, EcommerceOrder $order): JsonResponse',
            "'cart_identity_token' => ['nullable', 'string', 'max:191']",
            "'shipping_provider' => ['required', 'string', 'max:100']",
            "'shipping_rate_id' => ['required', 'string', 'max:191']",
            "'provider' => ['required', 'string', 'max:100']",
            "'method' => ['nullable', 'string', 'max:100']",
            "'is_installment' => ['nullable', 'boolean']",
            'return $this->corsJson($payload, 201);',
            'return $this->corsJson($payload);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicStorefrontController);
        }
        $this->assertStringNotContainsString('Idempotency-Key', $publicStorefrontController);

        foreach ([
            'public function checkoutValidate(Site $site, EcommerceCart $cart, array $payload = [], ?User $viewer = null): array',
            'public function customerOrders(Site $site, array $filters = [], ?User $viewer = null): array',
            'public function customerOrder(Site $site, EcommerceOrder $order, ?User $viewer = null): array',
            'private function assertAuthenticatedCustomerUser(?User $viewer): User',
            'customer_auth_required',
            "'validation' => [",
            "'checkout_endpoint' => route('public.sites.ecommerce.carts.checkout'",
            "'orders' => \$items,",
            "'meta' => [",
            "'customer_email' => \$customer->email,",
        ] as $needle) {
            $this->assertStringContainsString($needle, $storefrontService);
        }

        foreach ([
            '/public/sites/{site}/ecommerce/payment-options:',
            '/public/sites/{site}/ecommerce/carts/{cart}/shipping/options:',
            '/public/sites/{site}/ecommerce/carts/{cart}/shipping:',
            '/public/sites/{site}/ecommerce/carts/{cart}/checkout:',
            '/public/sites/{site}/ecommerce/orders/{order}/payments/start:',
            "'200':",
            'description: Order created',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceOpenApi);
        }
        $this->assertStringNotContainsString('/public/sites/{site}/ecommerce/orders/my:', $ecommerceOpenApi);
        $this->assertStringNotContainsString('/public/sites/{site}/ecommerce/orders/{order}:', $ecommerceOpenApi);

        foreach ([
            '/login:',
            '/register:',
            '/auth/{provider}:',
            '/auth/{provider}/callback:',
            'session-backed',
        ] as $needle) {
            $this->assertStringContainsString($needle, $authCustomersOpenApi);
        }
        $this->assertTrue(
            str_contains($authCustomersOpenApi, 'session-backed and do not yet expose a dedicated `/customers/me` JSON API route')
            || str_contains($authCustomersOpenApi, 'public site-scoped JSON helper routes now exist for customer auth/account widget parity')
        );
        $this->assertStringContainsString('/public/sites/{site}/customers/me:', $authCustomersOpenApi);

        foreach ([
            '| `8.1 POST /checkout/validate` |',
            '| `8.2 POST /orders` (Idempotency-Key required) |',
            '| `8.3 GET /orders/my` (customer auth) |',
            '| `8.4 GET /orders/{id}` |',
            '| `9.1 GET /payments/methods` |',
            '| `9.2 POST /payments/init` |',
            'no dedicated endpoint; validation/precondition checks happen inside checkout execution',
            'no public customer-auth order list endpoint found',
            'no public customer-auth order detail endpoint found',
            'Idempotency-Key',
            'OpenAPI-vs-runtime minor baseline mismatch observed',
            'documents checkout response `200`',
            'returns `201`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api03AuditDoc);
        }

        foreach ([
            '`8.1 POST /checkout/validate`',
            '`8.2 POST /orders`',
            '`8.3 GET /orders/my`',
            '`8.4 GET /orders/{id}`',
            '`9.1 GET /payments/methods`',
            '`9.2 POST /payments/init`',
            'Route::post(\'/{site}/ecommerce/carts/{cart}/checkout\'',
            'Route::post(\'/{site}/ecommerce/orders/{order}/payments/start\'',
            'Route::get(\'/ecommerce/orders/{order}\'',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api03SyncTest);
        }
        $this->assertTrue(
            str_contains($api03SyncTest, 'do not yet expose a dedicated `/customers/me` JSON API route')
            || str_contains($api03SyncTest, 'public site-scoped JSON helper routes now exist')
        );

        foreach ([
            'webu_ecom_checkout_form_01',
            'webu_ecom_order_summary_01',
            'webu_ecom_shipping_selector_01',
            'webu_ecom_payment_selector_01',
            'webu_ecom_orders_list_01',
            'webu_ecom_order_detail_01',
            'data-webby-ecommerce-checkout-form',
            'data-webby-ecommerce-order-summary',
            'data-webby-ecommerce-shipping-selector',
            'data-webby-ecommerce-payment-selector',
            'data-webby-ecommerce-orders-list',
            'data-webby-ecommerce-order-detail',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceCoverageContract);
        }

        foreach ([
            'builderSectionAvailabilityMatrix',
            'requiredModules: [MODULE_ECOMMERCE]',
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationFrontendContract);
            $this->assertStringContainsString($needle, $activationUnitTest);
        }

        foreach ([
            '\'payment_options_url\' => $publicPrefix ? "{$publicPrefix}/payment-options" : null',
            '\'shipping_options_url_pattern\' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}/shipping/options" : null',
            '\'shipping_update_url_pattern\' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}/shipping" : null',
            '\'checkout_validate_url_pattern\' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}/checkout/validate" : null',
            '\'checkout_url_pattern\' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}/checkout" : null',
            '\'customer_orders_url\' => $publicPrefix ? "{$publicPrefix}/customer-orders" : null',
            '\'customer_order_url_pattern\' => $publicPrefix ? "{$publicPrefix}/customer-orders/{order_id}" : null',
            '\'payment_start_url_pattern\' => $publicPrefix ? "{$publicPrefix}/orders/{order_id}/payments/start" : null',
            'function getShippingOptions(cartId, payload) {',
            'function updateShipping(cartId, payload) {',
            'function validateCheckout(cartId, payload) {',
            'function checkout(cartId, payload) {',
            'function startPayment(orderId, payload) {',
            'function getPaymentOptions() {',
            'function getOrders(params) {',
            'function getOrder(orderId) {',
            'getShippingOptions: getShippingOptions,',
            'updateShipping: updateShipping,',
            'validateCheckout: validateCheckout,',
            'checkout: checkout,',
            'getPaymentOptions: getPaymentOptions,',
            'getOrders: getOrders,',
            'getOrder: getOrder,',
            'startPayment: startPayment,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'data-webby-ecommerce-checkout-form',
            'data-webby-ecommerce-order-summary',
            'data-webby-ecommerce-shipping-selector',
            'data-webby-ecommerce-payment-selector',
            'data-webby-ecommerce-orders-list',
            'data-webby-ecommerce-order-detail',
            'checkout_form_selector',
            'order_summary_selector',
            'shipping_selector',
            'payment_selector',
            'orders_list_selector',
            'order_detail_selector',
            'mountCheckoutFormWidget',
            'mountOrderSummaryWidget',
            'mountShippingSelectorWidget',
            'mountPaymentSelectorWidget',
            'mountOrdersListWidget',
            'mountOrderDetailWidget',
            'function getOrders(',
            'function getOrder(',
            'getOrders: getOrders,',
            'getOrder: getOrder,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'test_public_cart_checkout_and_payment_start_flow_works',
            'test_public_checkout_validate_endpoint_returns_preflight_shipping_payment_and_checkout_contract',
            'test_public_checkout_validate_endpoint_rejects_empty_cart',
            'test_public_customer_orders_endpoints_require_auth_and_are_scoped_to_authenticated_customer_email',
            'test_shipping_options_can_be_selected_and_persisted_to_order_on_checkout',
            'test_payment_options_endpoint_returns_manual_and_active_local_gateways',
            'test_payment_options_respect_site_level_provider_availability_settings',
            'test_payment_start_rejects_site_disabled_provider',
            'test_payment_plan_enforcement_blocks_disallowed_provider_and_installments',
            "route('public.sites.ecommerce.carts.checkout.validate'",
            "route('public.sites.ecommerce.customer_orders.index'",
            "route('public.sites.ecommerce.customer_orders.show'",
            "route('public.sites.ecommerce.payment.options'",
            "route('public.sites.ecommerce.carts.shipping.options'",
            "route('public.sites.ecommerce.carts.shipping.update'",
            "route('public.sites.ecommerce.carts.checkout'",
            "route('public.sites.ecommerce.orders.payment.start'",
            "->assertJsonPath('reason', 'customer_auth_required')",
            "->assertJsonPath('reason', 'payment_provider_not_allowed')",
            "->assertJsonPath('reason', 'installments_not_enabled')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommercePublicApiTest);
        }

        foreach ([
            'test_checkout_happy_path_and_payment_success_flow',
            'test_checkout_fails_for_empty_cart',
            'test_payment_start_fails_when_order_has_no_outstanding_balance',
            "route('public.sites.ecommerce.carts.checkout'",
            "route('public.sites.ecommerce.orders.payment.start'",
            '->assertCreated()',
            "->assertJsonPath('error', 'Cart is empty.')",
            "->assertJsonPath('error', 'This order has no outstanding balance.')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceCheckoutAcceptanceTest);
        }

        foreach ([
            'test_shipping_happy_path_quote_selection_checkout_and_tracking_flow',
            'test_shipping_selection_resets_after_cart_change_and_invalid_rate_is_rejected',
            "route('public.sites.ecommerce.carts.shipping.options'",
            "route('public.sites.ecommerce.carts.shipping.update'",
            "route('public.sites.ecommerce.carts.checkout'",
            "->assertJsonPath('shipping.providers.0.provider', 'manual-courier')",
            "->assertJsonPath('error', 'Selected shipping rate is not available.')",
            "->assertJsonPath('cart.meta_json.shipping_selection', null)",
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceShippingAcceptanceTest);
        }
    }
}
