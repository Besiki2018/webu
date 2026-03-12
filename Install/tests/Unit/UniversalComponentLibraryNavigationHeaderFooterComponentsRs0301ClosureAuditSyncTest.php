<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryNavigationHeaderFooterComponentsRs0301ClosureAuditSyncTest extends TestCase
{
    public function test_rs_03_01_closure_audit_locks_nav_footer_runtime_hooks_and_search_customer_endpoints_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_NAVIGATION_HEADER_FOOTER_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_03_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_NAVIGATION_HEADER_FOOTER_COMPONENTS_PARITY_RUNTIME_ENDPOINT_HOOKS_CLOSURE_AUDIT_RS_03_01_2026_02_26.md');

        $webRoutesPath = base_path('routes/web.php');
        $publicSiteControllerPath = base_path('app/Http/Controllers/Cms/PublicSiteController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');

        $featureTestPath = base_path('tests/Feature/Cms/CmsPublicNavSearchAndCustomerMeEndpointsTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderCmsNavFooterRuntimeHooksContractTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryNavigationHeaderFooterComponentsRs0301BaselineGapAuditSyncTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $baselineDocPath,
            $closureDocPath,
            $webRoutesPath,
            $publicSiteControllerPath,
            $builderServicePath,
            $featureTestPath,
            $runtimeContractTestPath,
            $baselineSyncTestPath,
            base_path('resources/js/Pages/Project/__tests__/CmsNavFooterBuilderCoverage.contract.test.ts'),
            base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts'),
            base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts'),
            base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php'),
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $closureDoc = File::get($closureDocPath);
        $routes = File::get($webRoutesPath);
        $publicSiteController = File::get($publicSiteControllerPath);
        $builderService = File::get($builderServicePath);
        $featureTest = File::get($featureTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);

        foreach ([
            '# 3) NAVIGATION / HEADER / FOOTER',
            '## 3.3 nav.search',
            'Data: GET /search (depends on mode)',
            '## 3.4 nav.cartIcon (if ecommerce enabled)',
            'Data: GET /cart',
            '## 3.5 nav.accountIcon',
            'Data: GET /customers/me',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-03-01` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_NAVIGATION_HEADER_FOOTER_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_03_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_NAVIGATION_HEADER_FOOTER_COMPONENTS_PARITY_RUNTIME_ENDPOINT_HOOKS_CLOSURE_AUDIT_RS_03_01_2026_02_26.md',
            'UniversalComponentLibraryNavigationHeaderFooterComponentsRs0301BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryNavigationHeaderFooterComponentsRs0301ClosureAuditSyncTest.php',
            'BuilderCmsNavFooterRuntimeHooksContractTest.php',
            'CmsPublicNavSearchAndCustomerMeEndpointsTest.php',
            '`✅` public `/{site}/search` and `/{site}/customers/me` endpoints now exist and are feature-tested',
            '`✅` `BuilderService` runtime now mounts standalone nav/footer/cart-icon marker hooks',
            '`✅` DoD closure achieved',
            '`⚠️` source endpoint exactness is still mixed for `/menus` and `/cart`',
            '`⚠️` minimal OpenAPI docs (`public-core` / `auth-customers`) are not yet updated',
            '`🧪` RS-03-01 closure sync lock added',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Goal (`RS-03-01` Closure Pass)',
            '## ✅ What Was Done (Closure Pass)',
            'GET /public/sites/{site}/search',
            'GET /public/sites/{site}/customers/me',
            '## Executive Result (`RS-03-01`)',
            '`RS-03-01` is now **DoD-complete** as a parity verification task.',
            '## Endpoint Integration Closure Matrix (`/menus`, `/search`, `/cart`, `/customers/me`)',
            'accepted_equivalent_variant',
            'accepted_exact_semantics_path_variant',
            '## Published Runtime Hook Closure (`BuilderService`)',
            'mountNavFooterRuntime(payload)',
            'bindNavSearchWidget(node)',
            'renderNavAccountWidget(node)',
            'renderFooterWidget(node, payload)',
            'bindCartIconRuntime()',
            'renderCartIconWidgets(cart)',
            '## Feature / Runtime Evidence Added (Closure Pass)',
            'CmsPublicNavSearchAndCustomerMeEndpointsTest.php',
            'BuilderCmsNavFooterRuntimeHooksContractTest.php',
            '## DoD Closure Matrix (`RS-03-01`)',
            'all 6 components have panel parity + runtime data binding behavior',
            'mode/feature gating documented and tested',
            '## DoD Verdict (`RS-03-01`)',
            '`RS-03-01` passes and is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            "Route::get('/{site}/search', [PublicSiteController::class, 'search'])",
            "Route::get('/{site}/customers/me', [PublicSiteController::class, 'customerMe'])",
            "Route::get('/{site}/menu/{key}'",
            "Route::get('/{site}/ecommerce/carts/{cart}'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }

        foreach ([
            'public function search(Request $request, Site $site): JsonResponse',
            'public function customerMe(Request $request, Site $site): JsonResponse',
            '$this->publicSites->settings(',
            "return match (\$mode) {",
            "'products' => \$this->searchProducts",
            "'posts' => \$this->searchPosts",
            'private function searchPages(Site $site, string $query, int $limit): array',
            'private function searchPosts(Site $site, string $query, int $limit): array',
            'private function searchProducts(Site $site, string $query, int $limit): array',
            "'authenticated' => \$authenticated,",
            "'Cache-Control', 'no-store, private'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicSiteController);
        }

        foreach ([
            "'search_url' =>",
            "'customer_me_url' =>",
            'function cmsSearchUrl()',
            'function cmsCustomerMeUrl()',
            'function bindNavSearchWidget(node)',
            'function renderNavAccountWidget(node)',
            'function renderFooterWidget(node, payload)',
            'function renderCartIconWidgets(cart)',
            'function bindCartIconRuntime()',
            'function mountNavFooterRuntime(payload)',
            '[data-webby-nav-logo]',
            '[data-webby-nav-menu]',
            '[data-webby-nav-search]',
            '[data-webby-nav-account-icon]',
            '[data-webby-footer-layout]',
            '[data-webby-ecommerce-cart-icon]',
            'data-webby-nav-search-bound',
            'data-webby-nav-account-state',
            'window.WebbyEcommerce.onCartUpdated',
            'mountNavFooterRuntime(payload);',
            'mountNavFooterRuntime(window.__WEBBY_CMS__ || null);',
            'mountNavFooterRuntime: function () {',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'CmsPublicNavSearchAndCustomerMeEndpointsTest',
            'test_public_nav_search_endpoint_returns_mode_scoped_results_for_site_products_and_posts',
            'test_public_customer_me_endpoint_returns_guest_and_authenticated_session_payload',
            "route('public.sites.search'",
            "route('public.sites.customers.me'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $featureTest);
        }

        foreach ([
            'BuilderCmsNavFooterRuntimeHooksContractTest',
            'function cmsSearchUrl()',
            'function cmsCustomerMeUrl()',
            'function mountNavFooterRuntime(payload)',
            '[data-webby-ecommerce-cart-icon]',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }
    }
}
