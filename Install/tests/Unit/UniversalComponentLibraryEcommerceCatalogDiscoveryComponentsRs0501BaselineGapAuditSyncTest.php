<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryEcommerceCatalogDiscoveryComponentsRs0501BaselineGapAuditSyncTest extends TestCase
{
    public function test_rs_05_01_progress_audit_doc_locks_catalog_discovery_parity_endpoint_and_runtime_gap_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CATALOG_DISCOVERY_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_05_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_ECOMMERCE_CATALOG_DISCOVERY_COMPONENTS_PARITY_RUNTIME_QUERY_ALIAS_HOOKS_CLOSURE_AUDIT_RS_05_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $webRoutesPath = base_path('routes/web.php');
        $publicStorefrontControllerPath = base_path('app/Http/Controllers/Ecommerce/PublicStorefrontController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $ecommerceOpenApiPath = base_path('docs/openapi/webu-ecommerce-minimal.v1.openapi.yaml');
        $ecommercePublicApiTestPath = base_path('tests/Feature/Ecommerce/EcommercePublicApiTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderEcommerceCatalogDiscoveryRuntimeHooksContractTest.php');
        $closureSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryEcommerceCatalogDiscoveryComponentsRs0501ClosureAuditSyncTest.php');
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
            '# 5) ECOMMERCE COMPONENTS (Enabled only for project.type=ecommerce)',
            '## 5.1 ecom.productGrid',
            'Content: source=products, limit, pagination, columns responsive, card fields toggles',
            'Data: GET /products',
            '## 5.2 ecom.productSearch',
            'Content: placeholder, showFilters, filterFields (category/price)',
            'Data: GET /products?q=...',
            '## 5.3 ecom.categoryList',
            'Content: layout (list/chips), showCounts',
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
            'WEBU_BACKEND_BUILDER_PUBLIC_API_COVERAGE_AUDIT_API_02_2026_02_25.md',
            'BackendBuilderPublicApiCoverageApi02SyncTest.php',
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
            '## Scope',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            '## Audit Inputs Reviewed',
            '## What Was Done (This Pass)',
            '## Executive Result (`RS-05-01`)',
            '## Catalog Discovery Parity Matrix',
            '### Matrix (`content/style/panel-preview/runtime-data/endpoint/responsive/filter-state/gating/tests`)',
            '`ecom.productGrid`',
            '`ecom.productSearch`',
            '`ecom.categoryList`',
            '`webu_ecom_product_grid_01`',
            '`webu_ecom_product_search_01`',
            '`webu_ecom_category_list_01`',
            '## Endpoint Contract Verification (`GET /products`, `/products?q=...`, `/categories`)',
            '### Source-to-Current Endpoint Matrix',
            '`exact_semantics_path_variant`',
            '`partial_equivalent`',
            '`gap`',
            '## Ecommerce-Only Gating Baseline (Source Vertical Constraint)',
            'builderSectionAvailabilityMatrix',
            'requiredModules: [MODULE_ECOMMERCE]',
            '## Responsive Layout + Filter/Search State Handling Evidence',
            'Responsive Card/List Layouts (DoD line)',
            'Filter/Search State Handling (DoD line)',
            '## Runtime Widget / Binding Status (`products`, `search`, `categories`)',
            'no `data-webby-ecommerce-search` selector/hook was found in `BuilderService` runtime script',
            'no `data-webby-ecommerce-categories` selector/hook was found in `BuilderService` runtime script',
            '## DoD Verdict (`RS-05-01`)',
            'Conclusion: `RS-05-01` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD)',
            '## Conclusion',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'webu_ecom_product_grid_01',
            'webu_ecom_category_list_01',
            'webu_ecom_product_search_01',
            'data-webby-ecommerce-products',
            'data-webby-ecommerce-categories',
            'data-webby-ecommerce-search',
            'columns_desktop',
            'columns_mobile',
            'pagination_mode',
            'show_filters',
            'filter_variant',
            'show_sort',
            'show_count',
            'show_dropdown_preview',
            "if (normalized === 'webu_ecom_product_grid_01')",
            "if (normalized === 'webu_ecom_category_list_01')",
            "if (normalized === 'webu_ecom_product_search_01')",
            "if (normalizedSectionType === 'webu_ecom_product_grid_01')",
            "if (normalizedSectionType === 'webu_ecom_category_list_01')",
            "if (normalizedSectionType === 'webu_ecom_product_search_01')",
            'applyEcomPreviewState',
            'paginationMode === \'infinite\'',
            'builderSectionAvailabilityMatrix',
            'requiredModules: [MODULE_ECOMMERCE]',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'source_component_key": "ecom.productGrid"',
            'webu_ecom_product_grid_01',
            'source_component_key": "ecom.productSearch"',
            'webu_ecom_product_search_01',
            'source_component_key": "ecom.categoryList"',
            'webu_ecom_category_list_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            "Route::get('/{site}/ecommerce/products'",
            "Route::get('/{site}/ecommerce/products/{slug}'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }
        $this->assertStringNotContainsString('public.sites.ecommerce.categories', $webRoutes);

        foreach ([
            'public function products(Request $request, Site $site): JsonResponse',
            '$search = $request->query(\'search\');',
            '$request->query->has(\'q\')',
            '$categorySlug = $request->query(\'category_slug\');',
            '$request->query->has(\'category\')',
            '$limit = $request->query(\'limit\');',
            '$request->query->has(\'per_page\')',
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
            '/public/sites/{site}/ecommerce/products:',
            '- name: q',
            '- name: page',
            '/public/sites/{site}/ecommerce/products/{slug}:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommerceOpenApi);
        }
        $this->assertStringNotContainsString('/public/sites/{site}/ecommerce/categories:', $ecommerceOpenApi);

        foreach ([
            "route('public.sites.ecommerce.products.index'",
            "'limit' => 1",
            "'offset' => 0",
            "'search' => 'alpha'",
            "'category_slug' => 'supplements'",
            'test_public_products_endpoint_accepts_source_style_catalog_query_aliases',
            "'q' => 'alpha'",
            "'category' => 'supplements'",
            "'per_page' => 1",
            "'page' => 2",
            '->assertJsonPath(\'pagination.limit\', 1)',
            '->assertJsonPath(\'pagination.offset\', 0)',
            '->assertJsonPath(\'pagination.offset\', 1)',
            '->assertJsonPath(\'products.0.category.slug\', \'supplements\')',
        ] as $needle) {
            $this->assertStringContainsString($needle, $ecommercePublicApiTest);
        }

        foreach ([
            '| `GET /products` | `GET /public/sites/{site}/ecommerce/products` |',
            '| `GET /categories` | no dedicated public categories route found |',
            'exact_semantics_path_variant',
            'Query names differ (`search`, `category_slug`, `limit`, `offset` vs spec `q`, `category`, `page`, `per_page`).',
            '`gap`',
            'No dedicated public categories endpoint',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api02AuditDoc);
        }

        foreach ([
            '`GET /products`',
            '`GET /categories`',
            'No dedicated public categories endpoint',
        ] as $needle) {
            $this->assertStringContainsString($needle, $api02SyncTest);
        }

        foreach ([
            'webu_ecom_product_grid_01',
            'webu_ecom_category_list_01',
            'webu_ecom_product_search_01',
            'data-webby-ecommerce-products',
            'data-webby-ecommerce-categories',
            'data-webby-ecommerce-search',
            'preview_state',
            'pagination_mode',
            'applyEcomPreviewState',
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
            '\'products_url\' => $publicPrefix ? "{$publicPrefix}/products" : null',
            '\'product_url_pattern\' => $publicPrefix ? "{$publicPrefix}/products/{slug}" : null',
            "'products_selector' => '[data-webby-ecommerce-products]'",
            "'search_selector' => '[data-webby-ecommerce-search]'",
            "'categories_selector' => '[data-webby-ecommerce-categories]'",
            'function normalizeCatalogListQuery(params) {',
            'function listProducts(params) {',
            'function listCategories(params) {',
            'function mountProductsWidget(container, options) {',
            'function mountSearchWidget(container, options) {',
            'function mountCategoriesWidget(container, options) {',
            "search: container.getAttribute('data-search') || opts.search || null",
            "category_slug: container.getAttribute('data-category') || opts.categorySlug || null",
            "limit: container.getAttribute('data-limit') || opts.limit || null",
            'mountProductsWidget: mountProductsWidget,',
            'mountSearchWidget: mountSearchWidget,',
            'mountCategoriesWidget: mountCategoriesWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'data-webby-ecommerce-search',
            'data-webby-ecommerce-categories',
            'search_selector',
            'categories_selector',
            'mountSearchWidget',
            'mountCategoriesWidget',
            'data-webby-ecommerce-search-state',
            'data-webby-ecommerce-categories-state',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        $this->assertStringNotContainsString('categories_url', $builderService);
    }
}
