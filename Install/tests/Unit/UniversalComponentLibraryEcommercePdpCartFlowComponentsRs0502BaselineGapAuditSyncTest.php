<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryEcommercePdpCartFlowComponentsRs0502BaselineGapAuditSyncTest extends TestCase
{
    public function test_rs_05_02_progress_audit_doc_locks_pdp_cart_flow_parity_mutation_flow_and_runtime_gap_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_PDP_CART_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_02_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_PDP_CART_FLOW_COMPONENTS_PARITY_RUNTIME_HOOKS_ALIAS_COMPAT_CLOSURE_AUDIT_RS_05_02_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $webRoutesPath = base_path('routes/web.php');
        $publicStorefrontControllerPath = base_path('app/Http/Controllers/Ecommerce/PublicStorefrontController.php');
        $storefrontServicePath = base_path('app/Ecommerce/Services/EcommercePublicStorefrontService.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $ecommerceOpenApiPath = base_path('docs/openapi/webu-ecommerce-minimal.v1.openapi.yaml');
        $ecommercePublicApiTestPath = base_path('tests/Feature/Ecommerce/EcommercePublicApiTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderEcommercePdpCartRuntimeHooksContractTest.php');
        $closureSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryEcommercePdpCartFlowComponentsRs0502ClosureAuditSyncTest.php');
        $api02AuditDocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_PUBLIC_API_COVERAGE_AUDIT_API_02_2026_02_25.md');
        $api02SyncTestPath = base_path('tests/Unit/BackendBuilderPublicApiCoverageApi02SyncTest.php');
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
            $ecommercePublicApiTestPath,
            $runtimeContractTestPath,
            $closureSyncTestPath,
            $api02AuditDocPath,
            $api02SyncTestPath,
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
        $ecommercePublicApiTest = File::get($ecommercePublicApiTestPath);
        $api02AuditDoc = File::get($api02AuditDocPath);
        $api02SyncTest = File::get($api02SyncTestPath);
        $ecommerceCoverageContract = File::get($ecommerceCoverageContractPath);
        $activationFrontendContract = File::get($activationFrontendContractPath);
        $activationUnitTest = File::get($activationUnitTestPath);
        $aliasMap = File::get($aliasMapPath);

        foreach ([
            '## 5.4 ecom.productGallery',
            'Content: zoom enable, thumbs position',
            'Data: GET /products/:slug',
            '## 5.5 ecom.productDetail',
            'Content: showVariants, showQty, showAddToCart, showSKU',
            'Data: GET /products/:slug + POST /cart/items',
            'Style: typography, button styles, variant selector styles',
            '## 5.6 ecom.cart',
            'Content: showCoupon, showShippingEstimate',
            'Data: GET/PUT/DELETE /cart',
            'Style: table/card layout, totals box style',
            '## 5.7 ecom.couponBox',
            'Content: placeholder, apply button label',
            'Data: POST /coupons/apply',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-05-02` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_PDP_CART_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_02_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_PDP_CART_FLOW_COMPONENTS_PARITY_RUNTIME_HOOKS_ALIAS_COMPAT_CLOSURE_AUDIT_RS_05_02_2026_02_26.md',
            'UniversalComponentLibraryEcommercePdpCartFlowComponentsRs0502BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryEcommercePdpCartFlowComponentsRs0502ClosureAuditSyncTest.php',
            'BuilderEcommercePdpCartRuntimeHooksContractTest.php',
            'EcommercePublicApiTest.php',
            'WEBU_BACKEND_BUILDER_PUBLIC_API_COVERAGE_AUDIT_API_02_2026_02_25.md',
            'BackendBuilderPublicApiCoverageApi02SyncTest.php',
            '`✅` baseline parity/gap audit is preserved and superseded by a closure audit covering standalone PDP/coupon runtime hooks and cart-item payload alias compatibility',
            '`✅` `BuilderService` ecommerce runtime now mounts standalone `data-webby-ecommerce-product-detail`, `data-webby-ecommerce-product-gallery`, and `data-webby-ecommerce-coupon` widgets and exports coupon JS helpers',
            '`✅` cart-item add path now supports source-style payload aliases (`product_slug`, `qty`) via controller/service translation layer and is feature-tested',
            '`✅` DoD closure achieved',
            '`⚠️` endpoint path/method exactness remains accepted variant',
            '`⚠️` minimal OpenAPI add-item schema still only documents source-style alias payload',
            '`🧪` RS-05-02 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '## Scope',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            '## Audit Inputs Reviewed',
            '## What Was Done (This Pass)',
            '## Executive Result (`RS-05-02`)',
            '## PDP / Cart Flow Parity Matrix',
            '### Matrix (`content/style/panel-preview/runtime-data/endpoint/interaction-flow/style-state/gating/tests`)',
            '`ecom.productGallery`',
            '`ecom.productDetail`',
            '`ecom.cart`',
            '`ecom.couponBox`',
            '`webu_ecom_product_gallery_01`',
            '`webu_ecom_product_detail_01`',
            '`webu_ecom_cart_page_01`',
            '`webu_ecom_coupon_ui_01`',
            '## Endpoint Contract Verification (`GET /products/:slug`, `/cart`, `/cart/items`, `/coupons/apply`)',
            '### Source-to-Current Endpoint Matrix',
            '`exact_semantics_path_variant`',
            '`partial_equivalent`',
            'Runtime/controller payload uses `product_id` + `quantity`; minimal OpenAPI documents `product_slug` + `qty`.',
            '## Ecommerce-Only Gating Baseline (Source Vertical Constraint)',
            'builderSectionAvailabilityMatrix',
            'requiredModules: [MODULE_ECOMMERCE]',
            '## PDP Interaction Parity + Product Action Style/State Evidence',
            '### `ecom.productDetail` (`webu_ecom_product_detail_01`)',
            '### `ecom.productGallery` (`webu_ecom_product_gallery_01`)',
            '### `ecom.cart` + `ecom.couponBox` (`webu_ecom_cart_page_01`, `webu_ecom_coupon_ui_01`)',
            '## Cart Mutation Flow Verification (`GET/PUT/DELETE /cart`, `POST /coupons/apply` + remove scenario)',
            '### `EcommercePublicApiTest.php` Flow Evidence Matrix',
            'Add-to-cart (API)',
            'Coupon totals recalc after qty update',
            '## Runtime Widget / Binding Status (`productDetail`, `productGallery`, `cart`, `couponBox`)',
            'What is not currently evidenced in `BuilderService`',
            'coupon JS mutator helpers (`applyCoupon`, `removeCoupon`)',
            '## OpenAPI / Controller Contract Drift (Cart Item Add Payload)',
            'required: [product_slug, qty]',
            '## DoD Verdict (`RS-05-02`)',
            'Conclusion: `RS-05-02` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD)',
            '## Conclusion',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'webu_ecom_product_detail_01',
            'webu_ecom_product_gallery_01',
            'webu_ecom_cart_page_01',
            'webu_ecom_coupon_ui_01',
            'data-webby-ecommerce-product-detail',
            'data-webby-ecommerce-product-gallery',
            'data-webby-ecommerce-cart',
            'data-webby-ecommerce-coupon',
            'show_variants',
            'show_qty',
            'qty_default',
            'qty_min',
            'qty_max',
            'show_add_to_cart',
            'show_meta',
            'enable_zoom',
            'thumbnail_position',
            'show_coupon_slot',
            'show_order_summary',
            'applied_message',
            'show_applied_message',
            "if (normalized === 'webu_ecom_product_detail_01')",
            "if (normalized === 'webu_ecom_product_gallery_01')",
            "if (normalized === 'webu_ecom_cart_page_01')",
            "if (normalized === 'webu_ecom_coupon_ui_01')",
            "if (normalizedSectionType === 'webu_ecom_product_detail_01')",
            "if (normalizedSectionType === 'webu_ecom_product_gallery_01')",
            "if (normalizedSectionType === 'webu_ecom_cart_page_01')",
            "if (normalizedSectionType === 'webu_ecom_coupon_ui_01')",
            'builderPreviewMode !== \'mobile\'',
            'const thumbnailPosition = typeof effectiveProps.thumbnail_position === \'string\'',
            'const enableZoom = parseBooleanProp(effectiveProps.enable_zoom, true);',
            'const showCounter = parseBooleanProp(effectiveProps.show_counter, true);',
            'applyEcomPreviewState',
            "'webu_ecom_cart_page_01',",
            "'webu_ecom_coupon_ui_01',",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'source_component_key": "ecom.productGallery"',
            'webu_ecom_product_gallery_01',
            'source_component_key": "ecom.productDetail"',
            'webu_ecom_product_detail_01',
            'webu_ecom_add_to_cart_button_01',
            'source_component_key": "ecom.cart"',
            'webu_ecom_cart_page_01',
            'source_component_key": "ecom.couponBox"',
            'webu_ecom_coupon_ui_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            "Route::get('/{site}/ecommerce/products/{slug}'",
            "Route::post('/{site}/ecommerce/carts'",
            "Route::get('/{site}/ecommerce/carts/{cart}'",
            "Route::post('/{site}/ecommerce/carts/{cart}/items'",
            "Route::put('/{site}/ecommerce/carts/{cart}/items/{item}'",
            "Route::delete('/{site}/ecommerce/carts/{cart}/items/{item}'",
            "Route::post('/{site}/ecommerce/carts/{cart}/coupon'",
            "Route::delete('/{site}/ecommerce/carts/{cart}/coupon'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }
        $this->assertStringNotContainsString("Route::post('/{site}/ecommerce/coupons/apply'", $webRoutes);

        foreach ([
            'public function product(Request $request, Site $site, string $slug): JsonResponse',
            'public function cart(Request $request, Site $site, EcommerceCart $cart): JsonResponse',
            'public function addCartItem(Request $request, Site $site, EcommerceCart $cart): JsonResponse',
            'public function updateCartItem(',
            'public function removeCartItem(',
            'public function applyCoupon(Request $request, Site $site, EcommerceCart $cart): JsonResponse',
            'public function removeCoupon(Request $request, Site $site, EcommerceCart $cart): JsonResponse',
            "'product_id' => ['nullable', 'integer', 'min:1', 'required_without:product_slug']",
            "'product_slug' => ['nullable', 'string', 'max:191', 'required_without:product_id']",
            "'variant_id' => ['nullable', 'integer', 'min:1']",
            "'quantity' => ['nullable', 'integer', 'min:1', 'required_without:qty']",
            "'qty' => ['nullable', 'integer', 'min:1', 'required_without:quantity']",
            "\$validated['quantity'] = (int) \$validated['qty'];",
            "'code' => ['required', 'string', 'max:64']",
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicStorefrontController);
        }

        foreach ([
            "\$productSlug = trim((string) (\$payload['product_slug'] ?? ''));",
            "\$quantity = max(1, (int) (\$payload['quantity'] ?? (\$payload['qty'] ?? 1)));",
            "\$resolvedProductBySlug = \$this->repository->findPublishedProductBySiteAndSlug",
            "throw new EcommerceDomainException('product_id or product_slug is required.', 422);",
        ] as $needle) {
            $this->assertStringContainsString($needle, $storefrontService);
        }

        foreach ([
            '/public/sites/{site}/ecommerce/products/{slug}:',
            '/public/sites/{site}/ecommerce/carts/{cart}:',
            '/public/sites/{site}/ecommerce/carts/{cart}/items:',
            'required: [product_slug, qty]',
            'product_slug: { type: string }',
            'qty: { type: integer, minimum: 1 }',
            '/public/sites/{site}/ecommerce/carts/{cart}/items/{item}:',
            '/public/sites/{site}/ecommerce/carts/{cart}/coupon:',
            'summary: Apply coupon code',
            'summary: Remove coupon code',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceOpenApi);
        }
        $this->assertStringNotContainsString('product_id: { type: integer', $ecommerceOpenApi);
        $this->assertStringNotContainsString('variant_id: { type: integer', $ecommerceOpenApi);

        foreach ([
            'public function test_coupon_can_be_applied_and_removed_with_totals_recalculated(): void',
            'test_public_cart_add_item_accepts_source_style_payload_aliases_product_slug_and_qty',
            "route('public.sites.ecommerce.products.show'",
            "route('public.sites.ecommerce.carts.store'",
            "route('public.sites.ecommerce.carts.show'",
            "route('public.sites.ecommerce.carts.items.store'",
            "route('public.sites.ecommerce.carts.items.update'",
            "route('public.sites.ecommerce.carts.items.destroy'",
            "route('public.sites.ecommerce.carts.coupon.apply'",
            "route('public.sites.ecommerce.carts.coupon.remove'",
            '\'product_id\' => $alphaProduct->id',
            "'product_slug' => 'alias-payload-product'",
            "'qty' => 2",
            "'quantity' => 2",
            "->assertJsonPath('cart.items.0.product_url', route('public.sites.ecommerce.products.show'",
            "->assertJsonPath('coupon.code', 'SAVE10')",
            "->assertJsonPath('cart.coupon.effective_discount_total', '15.00')",
            "->assertJsonPath('cart.coupon', null)",
            "->assertJsonPath('error', 'Coupon code is invalid.')",
            '->assertStatus(409)',
            'cart_identity_mismatch',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommercePublicApiTest);
        }

        foreach ([
            '| `GET /products/{slug}` | `GET /public/sites/{site}/ecommerce/products/{slug}` |',
            '| `GET /cart` | `GET /public/sites/{site}/ecommerce/carts/{cart}` |',
            '| `POST /cart/items` | `POST /public/sites/{site}/ecommerce/carts/{cart}/items` |',
            '| `PUT /cart/items/{id}` | `PUT /public/sites/{site}/ecommerce/carts/{cart}/items/{item}` |',
            '| `DELETE /cart/items/{id}` | `DELETE /public/sites/{site}/ecommerce/carts/{cart}/items/{item}` |',
            '| `POST /coupons/apply` | `POST /public/sites/{site}/ecommerce/carts/{cart}/coupon` |',
            '| `POST /coupons/remove` | `DELETE /public/sites/{site}/ecommerce/carts/{cart}/coupon` |',
            'Payload uses `quantity` (runtime) vs spec `qty`; `variant_id` supported.',
            'Remove semantics exist but method differs (`DELETE` vs spec `POST`).',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api02AuditDoc);
        }

        foreach ([
            '`GET /products/{slug}`',
            '`GET /cart`',
            '`POST /cart/items`',
            '`PUT /cart/items/{id}`',
            '`DELETE /cart/items/{id}`',
            '`POST /coupons/apply`',
            '`POST /coupons/remove`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api02SyncTest);
        }

        foreach ([
            'webu_ecom_product_detail_01',
            'webu_ecom_product_gallery_01',
            'webu_ecom_cart_page_01',
            'webu_ecom_coupon_ui_01',
            'data-webby-ecommerce-cart',
            'data-webby-ecommerce-coupon',
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
            '\'product_url_pattern\' => $publicPrefix ? "{$publicPrefix}/products/{slug}" : null',
            '\'create_cart_url\' => $publicPrefix ? "{$publicPrefix}/carts" : null',
            '\'cart_url_pattern\' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}" : null',
            '\'cart_items_url_pattern\' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}/items" : null',
            '\'cart_item_url_pattern\' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}/items/{item_id}" : null',
            '\'coupon_url_pattern\' => $publicPrefix ? "{$publicPrefix}/carts/{cart_id}/coupon" : null',
            "'products_selector' => '[data-webby-ecommerce-products]'",
            "'product_detail_selector' => '[data-webby-ecommerce-product-detail]'",
            "'product_gallery_selector' => '[data-webby-ecommerce-product-gallery]'",
            "'coupon_selector' => '[data-webby-ecommerce-coupon]'",
            "'cart_selector' => '[data-webby-ecommerce-cart]'",
            'function resolveProductSlugForWidget(container, options) {',
            'function getProduct(slug) {',
            'function getCart(cartId) {',
            'function addCartItem(cartId, payload) {',
            'function updateCartItem(cartId, itemId, payload) {',
            'function removeCartItem(cartId, itemId) {',
            'function applyCoupon(cartId, payload) {',
            'function removeCoupon(cartId, payload) {',
            'function mountProductsWidget(container, options) {',
            'function mountProductDetailWidget(container, options) {',
            'function mountProductGalleryWidget(container, options) {',
            'function mountCouponWidget(container, options) {',
            'function renderCartWidget(container) {',
            'function mountWidgets() {',
            "var productsSelector = (ecommerce.widgets && ecommerce.widgets.products_selector) || '[data-webby-ecommerce-products]';",
            "var productDetailSelector = (ecommerce.widgets && ecommerce.widgets.product_detail_selector) || '[data-webby-ecommerce-product-detail]';",
            "var productGallerySelector = (ecommerce.widgets && ecommerce.widgets.product_gallery_selector) || '[data-webby-ecommerce-product-gallery]';",
            "var couponSelector = (ecommerce.widgets && ecommerce.widgets.coupon_selector) || '[data-webby-ecommerce-coupon]';",
            "var cartSelector = (ecommerce.widgets && ecommerce.widgets.cart_selector) || '[data-webby-ecommerce-cart]';",
            'getProduct: getProduct,',
            'addCartItem: addCartItem,',
            'updateCartItem: updateCartItem,',
            'removeCartItem: removeCartItem,',
            'applyCoupon: applyCoupon,',
            'removeCoupon: removeCoupon,',
            'mountProductsWidget: mountProductsWidget,',
            'mountProductDetailWidget: mountProductDetailWidget,',
            'mountProductGalleryWidget: mountProductGalleryWidget,',
            'mountCouponWidget: mountCouponWidget,',
            'mountCartWidget: renderCartWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'data-webby-ecommerce-product-detail-state',
            'data-webby-ecommerce-product-gallery-state',
            'data-webby-ecommerce-coupon-state',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }
    }
}
