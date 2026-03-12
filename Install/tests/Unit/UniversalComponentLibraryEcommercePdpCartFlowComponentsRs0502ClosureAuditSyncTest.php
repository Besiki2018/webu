<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryEcommercePdpCartFlowComponentsRs0502ClosureAuditSyncTest extends TestCase
{
    public function test_rs_05_02_closure_audit_locks_pdp_cart_runtime_hooks_coupon_api_and_alias_compat_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_PDP_CART_FLOW_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_02_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_PDP_CART_FLOW_COMPONENTS_PARITY_RUNTIME_HOOKS_ALIAS_COMPAT_CLOSURE_AUDIT_RS_05_02_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $routesPath = base_path('routes/web.php');
        $publicStorefrontControllerPath = base_path('app/Http/Controllers/Ecommerce/PublicStorefrontController.php');
        $storefrontServicePath = base_path('app/Ecommerce/Services/EcommercePublicStorefrontService.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $ecommerceOpenApiPath = base_path('docs/openapi/webu-ecommerce-minimal.v1.openapi.yaml');
        $api02AuditDocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_PUBLIC_API_COVERAGE_AUDIT_API_02_2026_02_25.md');

        $featureTestPath = base_path('tests/Feature/Ecommerce/EcommercePublicApiTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderEcommercePdpCartRuntimeHooksContractTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryEcommercePdpCartFlowComponentsRs0502BaselineGapAuditSyncTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $baselineDocPath,
            $closureDocPath,
            $cmsPath,
            $routesPath,
            $publicStorefrontControllerPath,
            $storefrontServicePath,
            $builderServicePath,
            $ecommerceOpenApiPath,
            $api02AuditDocPath,
            $featureTestPath,
            $runtimeContractTestPath,
            $baselineSyncTestPath,
            base_path('tests/Unit/BackendBuilderPublicApiCoverageApi02SyncTest.php'),
            base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts'),
            base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php'),
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $baselineDoc = File::get($baselineDocPath);
        $closureDoc = File::get($closureDocPath);
        $cms = File::get($cmsPath);
        $routes = File::get($routesPath);
        $publicStorefrontController = File::get($publicStorefrontControllerPath);
        $storefrontService = File::get($storefrontServicePath);
        $builderService = File::get($builderServicePath);
        $ecommerceOpenApi = File::get($ecommerceOpenApiPath);
        $api02AuditDoc = File::get($api02AuditDocPath);
        $featureTest = File::get($featureTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);

        foreach ([
            '## 5.4 ecom.productGallery',
            '## 5.5 ecom.productDetail',
            'Data: GET /products/:slug + POST /cart/items',
            '## 5.6 ecom.cart',
            '## 5.7 ecom.couponBox',
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
            'coupon JS mutator helpers (`applyCoupon`, `removeCoupon`)',
            'Conclusion: `RS-05-02` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $baselineDoc);
        }

        foreach ([
            'Status: `DONE`',
            '## Goal (`RS-05-02` Closure Pass)',
            '## ✅ What Was Done (Closure Pass)',
            'coupon JS helper API coverage (`applyCoupon` / `removeCoupon`)',
            'cart-item add payload compatibility for source-style alias payload (`product_slug`, `qty`)',
            '## Executive Result (`RS-05-02`)',
            '`RS-05-02` is now **DoD-complete** as a PDP/cart flow parity verification task.',
            '## Closure Delta Against Baseline (`2026-02-25`)',
            'resolved_via_translation_layer',
            'accepted_variant',
            '## Runtime Hook Closure (`BuilderService`)',
            'mountProductDetailWidget(container, options)',
            'mountProductGalleryWidget(container, options)',
            'mountCouponWidget(container, options)',
            'applyCoupon(cartId, payload)',
            'removeCoupon(cartId, payload)',
            'data-webby-ecommerce-product-detail-state',
            'data-webby-ecommerce-product-gallery-state',
            'data-webby-ecommerce-coupon-state',
            '## Source Payload Alias Compatibility Closure (Cart Add Item)',
            'controller validation accepts either `product_id` or `product_slug`',
            'service resolves `product_slug` to published product ID',
            '## Feature / Runtime Evidence Added (Closure Pass)',
            'test_public_cart_add_item_accepts_source_style_payload_aliases_product_slug_and_qty',
            'BuilderEcommercePdpCartRuntimeHooksContractTest.php',
            '## Remaining Exactness Gaps (Truthful, Non-Blocking for `RS-05-02` DoD)',
            'Minimal OpenAPI add-item schema still documents source-style alias payload',
            '## DoD Closure Matrix (`RS-05-02`)',
            '## DoD Verdict (`RS-05-02`)',
            '`RS-05-02` passes and is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            'data-webby-ecommerce-product-detail',
            'data-webby-ecommerce-product-gallery',
            'data-webby-ecommerce-coupon',
            'webu_ecom_product_detail_01',
            'webu_ecom_product_gallery_01',
            'webu_ecom_coupon_ui_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            "Route::get('/{site}/ecommerce/products/{slug}'",
            "Route::post('/{site}/ecommerce/carts/{cart}/items'",
            "Route::post('/{site}/ecommerce/carts/{cart}/coupon'",
            "Route::delete('/{site}/ecommerce/carts/{cart}/coupon'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }

        foreach ([
            'public function addCartItem(Request $request, Site $site, EcommerceCart $cart): JsonResponse',
            "'product_id' => ['nullable', 'integer', 'min:1', 'required_without:product_slug']",
            "'product_slug' => ['nullable', 'string', 'max:191', 'required_without:product_id']",
            "'quantity' => ['nullable', 'integer', 'min:1', 'required_without:qty']",
            "'qty' => ['nullable', 'integer', 'min:1', 'required_without:quantity']",
            "if (! array_key_exists('quantity', \$validated) && array_key_exists('qty', \$validated)) {",
            "\$validated['quantity'] = (int) \$validated['qty'];",
            'public function applyCoupon(Request $request, Site $site, EcommerceCart $cart): JsonResponse',
            'public function removeCoupon(Request $request, Site $site, EcommerceCart $cart): JsonResponse',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicStorefrontController);
        }

        foreach ([
            "\$productSlug = trim((string) (\$payload['product_slug'] ?? ''));",
            "\$quantity = max(1, (int) (\$payload['quantity'] ?? (\$payload['qty'] ?? 1)));",
            "\$resolvedProductBySlug = \$this->repository->findPublishedProductBySiteAndSlug",
            "throw new EcommerceDomainException('product_id or product_slug is required.', 422);",
            "\$product = \$resolvedProductBySlug ?? \$this->repository->findProductBySiteAndId(\$site, \$productId);",
        ] as $needle) {
            $this->assertStringContainsString($needle, $storefrontService);
        }

        foreach ([
            "'coupon_url_pattern' =>",
            "'product_detail_selector' => '[data-webby-ecommerce-product-detail]'",
            "'product_gallery_selector' => '[data-webby-ecommerce-product-gallery]'",
            "'coupon_selector' => '[data-webby-ecommerce-coupon]'",
            'function resolveProductSlugForWidget(container, options) {',
            'function applyCoupon(cartId, payload) {',
            'function removeCoupon(cartId, payload) {',
            'function mountProductDetailWidget(container, options) {',
            'function mountProductGalleryWidget(container, options) {',
            'function mountCouponWidget(container, options) {',
            'data-webby-ecommerce-product-detail-bound',
            'data-webby-ecommerce-product-detail-state',
            'data-webby-ecommerce-product-gallery-bound',
            'data-webby-ecommerce-product-gallery-state',
            'data-webby-ecommerce-coupon-bound',
            'data-webby-ecommerce-coupon-state',
            'mountProductDetailWidget(node, {});',
            'mountProductGalleryWidget(node, {});',
            'mountCouponWidget(node, {});',
            'applyCoupon: applyCoupon,',
            'removeCoupon: removeCoupon,',
            'mountProductDetailWidget: mountProductDetailWidget,',
            'mountProductGalleryWidget: mountProductGalleryWidget,',
            'mountCouponWidget: mountCouponWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            '/public/sites/{site}/ecommerce/carts/{cart}/items:',
            'required: [product_slug, qty]',
            'product_slug: { type: string }',
            'qty: { type: integer, minimum: 1 }',
            '/public/sites/{site}/ecommerce/carts/{cart}/coupon:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceOpenApi);
        }

        foreach ([
            '| `POST /cart/items` | `POST /public/sites/{site}/ecommerce/carts/{cart}/items` |',
            'Payload uses `quantity` (runtime) vs spec `qty`; `variant_id` supported.',
            '| `POST /coupons/apply` | `POST /public/sites/{site}/ecommerce/carts/{cart}/coupon` |',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api02AuditDoc);
        }

        foreach ([
            'test_public_cart_add_item_accepts_source_style_payload_aliases_product_slug_and_qty',
            "'product_slug' => 'alias-payload-product'",
            "'qty' => 2",
            "->assertJsonPath('cart.items.0.product_slug', 'alias-payload-product')",
            "->assertJsonPath('cart.items.0.quantity', 2)",
            "->assertJsonPath('cart.items.0.line_total', '42.00')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $featureTest);
        }

        foreach ([
            'BuilderEcommercePdpCartRuntimeHooksContractTest',
            'function applyCoupon(cartId, payload) {',
            'function removeCoupon(cartId, payload) {',
            'function mountProductDetailWidget(container, options) {',
            'function mountProductGalleryWidget(container, options) {',
            'function mountCouponWidget(container, options) {',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }
    }
}

