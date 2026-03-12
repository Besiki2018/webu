<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryEcommerceCatalogDiscoveryComponentsRs0501ClosureAuditSyncTest extends TestCase
{
    public function test_rs_05_01_closure_audit_locks_catalog_discovery_query_alias_and_runtime_hooks_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CATALOG_DISCOVERY_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CATALOG_DISCOVERY_COMPONENTS_PARITY_RUNTIME_QUERY_ALIAS_HOOKS_CLOSURE_AUDIT_RS_05_01_2026_02_26.md');

        $routesPath = base_path('routes/web.php');
        $publicStorefrontControllerPath = base_path('app/Http/Controllers/Ecommerce/PublicStorefrontController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $ecommerceOpenApiPath = base_path('docs/openapi/webu-ecommerce-minimal.v1.openapi.yaml');
        $api02AuditDocPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_PUBLIC_API_COVERAGE_AUDIT_API_02_2026_02_25.md');
        $api02SyncTestPath = base_path('tests/Unit/BackendBuilderPublicApiCoverageApi02SyncTest.php');

        $featureTestPath = base_path('tests/Feature/Ecommerce/EcommercePublicApiTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryEcommerceCatalogDiscoveryComponentsRs0501BaselineGapAuditSyncTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $baselineDocPath,
            $closureDocPath,
            $routesPath,
            $publicStorefrontControllerPath,
            $builderServicePath,
            $ecommerceOpenApiPath,
            $api02AuditDocPath,
            $api02SyncTestPath,
            $featureTestPath,
            $runtimeContractTestPath,
            $baselineSyncTestPath,
            base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts'),
            base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts'),
            base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php'),
            base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json'),
            base_path('tests/Unit/MinimalOpenApiBaseModulesDeliverableTest.php'),
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $baselineDoc = File::get($baselineDocPath);
        $closureDoc = File::get($closureDocPath);
        $routes = File::get($routesPath);
        $publicStorefrontController = File::get($publicStorefrontControllerPath);
        $builderService = File::get($builderServicePath);
        $ecommerceOpenApi = File::get($ecommerceOpenApiPath);
        $api02AuditDoc = File::get($api02AuditDocPath);
        $featureTest = File::get($featureTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);

        foreach ([
            '# 5) ECOMMERCE COMPONENTS (Enabled only for project.type=ecommerce)',
            '## 5.1 ecom.productGrid',
            'Data: GET /products',
            '## 5.2 ecom.productSearch',
            'Data: GET /products?q=...',
            '## 5.3 ecom.categoryList',
            'Data: GET /categories',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-05-01` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CATALOG_DISCOVERY_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CATALOG_DISCOVERY_COMPONENTS_PARITY_RUNTIME_QUERY_ALIAS_HOOKS_CLOSURE_AUDIT_RS_05_01_2026_02_26.md',
            'UniversalComponentLibraryEcommerceCatalogDiscoveryComponentsRs0501BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryEcommerceCatalogDiscoveryComponentsRs0501ClosureAuditSyncTest.php',
            'BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest.php',
            'EcommercePublicApiTest.php',
            '`✅` baseline parity/gap audit is preserved and superseded by a closure audit covering source query aliases and standalone `search/categories` runtime hooks',
            '`✅` public products endpoint now accepts source-style catalog query aliases (`q`, `category`, `page`, `per_page`) and is feature-tested',
            '`✅` `BuilderService` ecommerce runtime now mounts standalone `data-webby-ecommerce-search` and `data-webby-ecommerce-categories` widgets and is contract-locked',
            '`✅` DoD closure achieved',
            '`⚠️` no dedicated public `GET /categories` endpoint yet',
            '`⚠️` source-exact `ecom.productSearch` filter control schema (`showFilters` / `filterFields category/price`) remains partial',
            '`🧪` RS-05-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            'no `data-webby-ecommerce-search` selector/hook was found in `BuilderService` runtime script',
            'no `data-webby-ecommerce-categories` selector/hook was found in `BuilderService` runtime script',
            'Conclusion: `RS-05-01` remains `IN_PROGRESS`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $baselineDoc);
        }

        foreach ([
            'Status: `DONE`',
            '## Goal (`RS-05-01` Closure Pass)',
            '## ✅ What Was Done (Closure Pass)',
            'source-query alias handling to public products endpoint',
            'standalone `data-webby-ecommerce-search` and `data-webby-ecommerce-categories` runtime hooks',
            '## Executive Result (`RS-05-01`)',
            '`RS-05-01` is now **DoD-complete** as a catalog discovery parity verification task.',
            '## Closure Delta Against Baseline (`2026-02-25`)',
            'accepted_equivalent_variant',
            'non_blocking_exactness_gap',
            '## Endpoint Integration Closure Matrix (`GET /products`, `/products?q=...`, `/categories`)',
            'accepted_exact_semantics_path_variant',
            'products_derived',
            '## Source Query Alias Closure (`PublicStorefrontController`)',
            '`q` -> `search`',
            '`category` -> `category_slug`',
            '`per_page` -> `limit`',
            '`page` -> computed `offset`',
            '## Published Runtime Hook Closure (`BuilderService`)',
            'Search widget (`data-webby-ecommerce-search`)',
            'Categories widget (`data-webby-ecommerce-categories`)',
            'data-webby-ecommerce-search-state',
            'data-webby-ecommerce-categories-state',
            '## Feature / Runtime Evidence Added (Closure Pass)',
            'BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest.php',
            'test_public_products_endpoint_accepts_source_style_catalog_query_aliases',
            '## Remaining Exactness Gaps (Truthful, Non-Blocking for `RS-05-01` DoD)',
            'No dedicated public `/categories` endpoint exists',
            'API-02',
            '## DoD Closure Matrix (`RS-05-01`)',
            'filter/search state handling tested',
            '## DoD Verdict (`RS-05-01`)',
            '`RS-05-01` passes and is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            "Route::get('/{site}/ecommerce/products'",
            "Route::get('/{site}/ecommerce/products/{slug}'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }
        $this->assertStringNotContainsString('public.sites.ecommerce.categories', $routes);

        foreach ([
            'public function products(Request $request, Site $site): JsonResponse',
            '$search = $request->query(\'search\');',
            '$request->query->has(\'q\')',
            '$request->query(\'q\')',
            '$categorySlug = $request->query(\'category_slug\');',
            '$request->query->has(\'category\')',
            '$request->query(\'category\')',
            '$limit = $request->query(\'limit\');',
            '$request->query->has(\'per_page\')',
            '$request->query(\'per_page\')',
            '$offset = $request->query(\'offset\');',
            '$request->query->has(\'page\')',
            '$offset = ($resolvedPage - 1) * $resolvedLimit;',
            '\'search\' => $search,',
            '\'category_slug\' => $categorySlug,',
            '\'limit\' => $limit,',
            '\'offset\' => $offset,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicStorefrontController);
        }

        foreach ([
            "'search_selector' => '[data-webby-ecommerce-search]'",
            "'categories_selector' => '[data-webby-ecommerce-categories]'",
            'function normalizeCatalogListQuery(params) {',
            'query.search = search;',
            'query.q = search;',
            'query.category_slug = category;',
            'query.category = category;',
            'query.per_page = limit;',
            'query.page = Math.floor(parsedOffset / parsedLimit) + 1;',
            'function listCategories(params) {',
            "source: 'products_derived'",
            'function mountSearchWidget(container, options) {',
            'function mountCategoriesWidget(container, options) {',
            'data-webby-ecommerce-search-bound',
            'data-webby-ecommerce-search-state',
            'data-webby-ecommerce-categories-bound',
            'data-webby-ecommerce-categories-state',
            'var searchSelector = (ecommerce.widgets && ecommerce.widgets.search_selector)',
            'var categoriesSelector = (ecommerce.widgets && ecommerce.widgets.categories_selector)',
            'mountSearchWidget(node, {});',
            'mountCategoriesWidget(node, {});',
            'listCategories: listCategories,',
            'mountSearchWidget: mountSearchWidget,',
            'mountCategoriesWidget: mountCategoriesWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            '/public/sites/{site}/ecommerce/products:',
            '- name: q',
            '- name: page',
            '/public/sites/{site}/ecommerce/products/{slug}:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceOpenApi);
        }
        $this->assertStringNotContainsString('/public/sites/{site}/ecommerce/categories:', $ecommerceOpenApi);

        foreach ([
            '| `GET /categories` | no dedicated public categories route found |',
            'No dedicated public categories endpoint',
            'Query names differ (`search`, `category_slug`, `limit`, `offset` vs spec `q`, `category`, `page`, `per_page`).',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api02AuditDoc);
        }

        foreach ([
            'test_public_products_endpoint_accepts_source_style_catalog_query_aliases',
            "route('public.sites.ecommerce.products.index'",
            "'q' => 'alpha'",
            "'category' => 'supplements'",
            "'per_page' => 1",
            "'page' => 2",
            '->assertJsonPath(\'pagination.offset\', 1)',
            '->assertJsonPath(\'products.0.slug\', \'alpha-wellness-pack-older\')',
        ] as $needle) {
            $this->assertStringContainsString($needle, $featureTest);
        }

        foreach ([
            'BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest',
            'function normalizeCatalogListQuery(params) {',
            'function listCategories(params) {',
            'function mountSearchWidget(container, options) {',
            'function mountCategoriesWidget(container, options) {',
            'data-webby-ecommerce-search-state',
            'data-webby-ecommerce-categories-state',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }
    }
}
