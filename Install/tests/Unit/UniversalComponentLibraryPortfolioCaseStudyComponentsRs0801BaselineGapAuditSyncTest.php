<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryPortfolioCaseStudyComponentsRs0801BaselineGapAuditSyncTest extends TestCase
{
    public function test_rs_08_01_progress_audit_doc_locks_portfolio_case_study_components_parity_endpoint_and_runtime_gap_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_PORTFOLIO_CASE_STUDY_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_08_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_PORTFOLIO_CASE_STUDY_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_08_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $aliasMapPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $webRoutesPath = base_path('routes/web.php');
        $publicSiteControllerPath = base_path('app/Http/Controllers/Cms/PublicSiteController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $publicCoreOpenApiPath = base_path('docs/openapi/webu-public-core-minimal.v1.openapi.yaml');

        $portfolioModuleContractTestPath = base_path('tests/Unit/UniversalPortfolioModuleComponentsP5F4Test.php');
        $portfolioFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsPortfolioBuilderCoverage.contract.test.ts');
        $cmsModuleRegistryFeatureTestPath = base_path('tests/Feature/Cms/CmsModuleRegistryTest.php');
        $cmsPublicVerticalFeatureTestPath = base_path('tests/Feature/Cms/CmsPublicVerticalModulesEndpointsTest.php');
        $projectTypeFlagsUnitTestPath = base_path('tests/Unit/CmsProjectTypeModuleFeatureFlagServiceTest.php');
        $verticalSchemaFeatureTestPath = base_path('tests/Feature/Platform/UniversalVerticalModulesNormalizationTablesSchemaTest.php');
        $activationFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts');
        $activationUnitTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');
        $coverageGapAuditUnitTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $closureSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryPortfolioCaseStudyComponentsRs0801ClosureAuditSyncTest.php');
        $minimalOpenApiDeliverableTestPath = base_path('tests/Unit/MinimalOpenApiBaseModulesDeliverableTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $closureDocPath,
            $cmsPath,
            $aliasMapPath,
            $webRoutesPath,
            $publicSiteControllerPath,
            $builderServicePath,
            $publicCoreOpenApiPath,
            $portfolioModuleContractTestPath,
            $portfolioFrontendContractPath,
            $cmsModuleRegistryFeatureTestPath,
            $cmsPublicVerticalFeatureTestPath,
            $projectTypeFlagsUnitTestPath,
            $verticalSchemaFeatureTestPath,
            $activationFrontendContractPath,
            $activationUnitTestPath,
            $coverageGapAuditUnitTestPath,
            $runtimeContractTestPath,
            $closureSyncTestPath,
            $minimalOpenApiDeliverableTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $cms = File::get($cmsPath);
        $aliasMap = File::get($aliasMapPath);
        $webRoutes = File::get($webRoutesPath);
        $publicSiteController = File::get($publicSiteControllerPath);
        $builderService = File::get($builderServicePath);
        $publicCoreOpenApi = File::get($publicCoreOpenApiPath);
        $portfolioModuleContractTest = File::get($portfolioModuleContractTestPath);
        $portfolioFrontendContract = File::get($portfolioFrontendContractPath);
        $cmsModuleRegistryFeatureTest = File::get($cmsModuleRegistryFeatureTestPath);
        $cmsPublicVerticalFeatureTest = File::get($cmsPublicVerticalFeatureTestPath);
        $projectTypeFlagsUnitTest = File::get($projectTypeFlagsUnitTestPath);
        $verticalSchemaFeatureTest = File::get($verticalSchemaFeatureTestPath);
        $activationFrontendContract = File::get($activationFrontendContractPath);
        $activationUnitTest = File::get($activationUnitTestPath);
        $coverageGapAuditUnitTest = File::get($coverageGapAuditUnitTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);
        $minimalOpenApiDeliverableTest = File::get($minimalOpenApiDeliverableTestPath);

        foreach ([
            '# 8) PORTFOLIO / CASE STUDY COMPONENTS',
            '## 8.1 port.portfolioGrid',
            'Content: columns responsive, categories filter',
            'Data: GET /portfolio',
            '## 8.2 port.portfolioDetail',
            'Data: GET /portfolio/:slug',
            '## 8.3 port.gallery',
            'Content: masonry/slider, lightbox',
            'Data: from portfolio/media',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-08-01` (`DONE`, `P1`)',
            'UNIVERSAL_COMPONENT_LIBRARY_PORTFOLIO_CASE_STUDY_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_08_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_PORTFOLIO_CASE_STUDY_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_08_01_2026_02_26.md',
            'UniversalComponentLibraryPortfolioCaseStudyComponentsRs0801BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryPortfolioCaseStudyComponentsRs0801ClosureAuditSyncTest.php',
            'CmsPublicVerticalModulesEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            '`✅` baseline parity/gap audit is preserved and superseded by a closure audit with public portfolio endpoints + standalone `window.WebbyPortfolio` runtime hook/selectors evidence',
            '`✅` public portfolio list/detail endpoints are feature-tested (`GET /public/sites/{site}/portfolio`, `GET /public/sites/{site}/portfolio/{slug}`) including detail gallery/media payload (`item.images`) via `CmsPublicVerticalModulesEndpointsTest.php`',
            '`✅` `BuilderService` now exposes standalone `port.portfolioGrid` / `port.portfolioDetail` / `port.gallery` runtime selectors/mounts and `window.WebbyPortfolio` helper APIs (`listProjects`, `getProject`, `mountProjectsWidget`, `mountProjectDetailWidget`, `mountGalleryWidget`) with gallery slider/lightbox runtime smoke markers contract-locked',
            '`✅` DoD closure achieved: all 3 components validated and smoke-tested (portfolio list/detail data checks + gallery masonry/slider/lightbox preview/runtime smoke evidence)',
            '`⚠️` source exactness gaps remain (`portfolioGrid` categories filter control is not modeled as a source-exact filter UI; gallery mode parity is smoke/runtime-equivalent, not a source-exact slider/lightbox UX implementation)',
            '`🧪` RS-08-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '## Scope',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            '## Audit Inputs Reviewed',
            '## What Was Done (This Pass)',
            '## Executive Result (`RS-08-01`)',
            '## Portfolio / Case Study Parity Matrix',
            '### Matrix (`content/style/panel-preview/runtime-data/endpoint/gallery-mode/gating/tests`)',
            '`port.portfolioGrid`',
            '`port.portfolioDetail`',
            '`port.gallery`',
            '`webu_portfolio_projects_grid_01`',
            '`webu_portfolio_project_detail_01`',
            '`webu_portfolio_gallery_01`',
            '## Endpoint Contract Verification (`GET /portfolio`, `GET /portfolio/:slug`, portfolio/media data source)',
            '### Source-to-Current Endpoint Matrix',
            '`gap`',
            '`partial_equivalent_storage_only`',
            '## Gallery Mode Parity Verification (`masonry/slider/lightbox`)',
            '### Source Requirement',
            '### Current Builder Schema / Preview Reality',
            'masonry',
            'slider',
            'lightbox',
            'no lightbox control or lightbox interaction/runtime hook evidenced',
            '## Builder Preview Parity and Source-Control Exactness Findings',
            'source: responsive columns + categories filter',
            'current schema: responsive columns are present, but filter semantics are not modeled (only `show_category`)',
            '## Portfolio Module / Data Baseline (Gating + Storage)',
            'portfolio_items',
            'portfolio_images',
            '## Runtime Widget / Binding Status (`portfolioGrid`, `portfolioDetail`, `gallery`)',
            'no `window.WebbyPortfolio` helper',
            'no `data-webby-portfolio-*` runtime widget mount handling in `BuilderService`',
            '## DoD Verdict (`RS-08-01`)',
            'Conclusion: `RS-08-01` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD + Parity Closure)',
            '## Conclusion',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'webu_portfolio_projects_grid_01',
            'webu_portfolio_project_detail_01',
            'webu_portfolio_gallery_01',
            'data-webby-portfolio-projects',
            'data-webby-portfolio-project-detail',
            'data-webby-portfolio-gallery',
            'projects_count',
            'columns_desktop',
            'columns_mobile',
            'show_category',
            'show_year',
            'project_slug',
            'show_gallery',
            'gallery_items_count',
            'show_metrics',
            'show_cta',
            'items_count',
            "layout: { type: 'string', title: 'Layout', default: 'masonry' }",
            "show_lightbox: { type: 'boolean', title: 'Lightbox', default: true }",
            "if (normalized === 'webu_portfolio_projects_grid_01')",
            "if (normalized === 'webu_portfolio_project_detail_01')",
            "if (normalized === 'webu_portfolio_gallery_01')",
            "if (normalizedSectionType === 'webu_portfolio_projects_grid_01')",
            "if (normalizedSectionType === 'webu_portfolio_project_detail_01')",
            "if (normalizedSectionType === 'webu_portfolio_gallery_01')",
            'applyPortfolioPreviewState',
            'createSyntheticPortfolioPlaceholder',
            'syntheticPortfolioSectionKeySet',
            'requiredModules: [MODULE_PORTFOLIO]',
            "key: 'portfolio'",
            'parseBooleanProp(effectiveProps.show_lightbox, true)',
            "sliderMarker.setAttribute('data-webu-role', 'portfolio-gallery-slider');",
            "lightboxMarker.setAttribute('data-webu-role', 'portfolio-gallery-lightbox');",
            "tile.setAttribute('data-webu-role', showLightbox ? 'portfolio-gallery-lightbox-trigger' : 'portfolio-gallery-tile');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'category_filter',
            'showBio',
            'showServices',
            'show_bio',
            'show_services',
            'data-webby-portfolio-runtime',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $cms);
        }

        foreach ([
            'source_component_key": "port.portfolioGrid"',
            'webu_portfolio_projects_grid_01',
            'source_component_key": "port.portfolioDetail"',
            'webu_portfolio_project_detail_01',
            'source_component_key": "port.gallery"',
            'webu_portfolio_gallery_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            "Route::get('/{site}/assets/{path}', [PublicSiteController::class, 'asset'])",
            "->name('public.sites.assets')",
            "Route::get('/{site}/portfolio', [PublicSiteController::class, 'portfolioItems'])->name('public.sites.portfolio.index');",
            "Route::get('/{site}/portfolio/{slug}', [PublicSiteController::class, 'portfolioItem'])->name('public.sites.portfolio.show');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }

        foreach ([
            'class PublicSiteController extends Controller',
            'public function page(Request $request, Site $site, string $slug): JsonResponse',
            'public function asset(Request $request, Site $site, string $path): BinaryFileResponse|JsonResponse',
            'public function portfolioItems(Request $request, Site $site): JsonResponse',
            'public function portfolioItem(Request $request, Site $site, string $slug): JsonResponse',
            'Public portfolio items list endpoint.',
            'Public portfolio item detail endpoint by slug.',
            '\'images\' => $images,',
            "DB::table('portfolio_images')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicSiteController);
        }
        foreach ([
            'public function caseStudy(',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $publicSiteController);
        }

        foreach ([
            'window.WebbyPortfolio = window.WebbyPortfolio || {};',
            "window.WebbyPortfolio.listProjects = function () { return cmsPublicJson('/portfolio'); };",
            "window.WebbyPortfolio.getProject = function (slug) { return cmsPublicJson('/portfolio/' + encodeURIComponent(String(slug || ''))); };",
            'window.WebbyPortfolio.mountProjectsWidget = mountPortfolioProjectsWidget;',
            'window.WebbyPortfolio.mountProjectDetailWidget = mountPortfolioProjectDetailWidget;',
            'window.WebbyPortfolio.mountGalleryWidget = mountPortfolioGalleryWidget;',
            'function mountPortfolioProjectsWidget(container) {',
            'function mountPortfolioProjectDetailWidget(container) {',
            'function mountPortfolioGalleryWidget(container, options) {',
            'data-webby-portfolio-gallery-grid',
            'data-webby-portfolio-gallery-slider',
            'data-webby-portfolio-gallery-lightbox',
            'data-webby-portfolio-gallery-lightbox-trigger',
            'data-webby-portfolio-lightbox-close',
            'Lightbox preview for media #',
            '[data-webby-portfolio-projects]',
            '[data-webby-portfolio-project-detail]',
            '[data-webby-portfolio-gallery]',
            'data-webby-portfolio-runtime',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'paths:',
            'openapi: 3.0.3',
            '/public/sites/{site}/pages/{slug}:',
            '/public/sites/{site}/assets/{path}:',
            '/public/sites/{site}/portfolio:',
            'summary: Public portfolio projects list',
            '/public/sites/{site}/portfolio/{slug}:',
            'summary: Public portfolio project detail by slug',
            'description: Portfolio item detail',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicCoreOpenApi);
        }

        foreach ([
            'test_p5_f4_01_portfolio_module_and_components_contract_is_locked',
            'MODULE_PORTFOLIO',
            'CmsPortfolioBuilderCoverage.contract.test.ts',
            'data-webby-portfolio-projects',
            'data-webby-portfolio-gallery',
            'requiredModules: [MODULE_PORTFOLIO]',
        ] as $needle) {
            $this->assertStringContainsString($needle, $portfolioModuleContractTest);
        }

        foreach ([
            'CMS portfolio builder component coverage contracts',
            'webu_portfolio_projects_grid_01',
            'webu_portfolio_project_detail_01',
            'webu_portfolio_gallery_01',
            'data-webby-portfolio-projects',
            'data-webby-portfolio-project-detail',
            'data-webby-portfolio-gallery',
            'createSyntheticPortfolioPlaceholder',
            'applyPortfolioPreviewState',
            'requiredModules: [MODULE_PORTFOLIO]',
        ] as $needle) {
            $this->assertStringContainsString($needle, $portfolioFrontendContract);
        }

        foreach ([
            'test_portfolio_module_is_exposed_for_portfolio_project_type_and_blocked_for_ecommerce_override',
            "'slug' => 'portfolio-template-test'",
            "'category' => 'portfolio'",
            "->assertJsonPath('project_type.key', 'portfolio')",
            "->assertJsonPath('modules.portfolio', false)",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsModuleRegistryFeatureTest);
        }

        foreach ([
            'test_public_blog_portfolio_properties_restaurant_and_hotel_endpoints_return_site_scoped_data',
            "route('public.sites.portfolio.index'",
            "route('public.sites.portfolio.show'",
            "->assertJsonPath('item.slug', 'brand-redesign')",
            "->assertJsonPath('item.images.0.media_id', 101)",
            "->assertJsonPath('item.images.1.media_id', 102)",
            "DB::table('portfolio_images')->insert([",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsPublicVerticalFeatureTest);
        }

        foreach ([
            'test_portfolio_project_type_allows_portfolio_module_and_ecommerce_type_denies_it_when_framework_enabled',
            'CmsModuleRegistryService::MODULE_PORTFOLIO',
            '$portfolioSite = $this->makeSiteWithTemplateCategory(\'portfolio\');',
            '$this->assertSame(\'portfolio\', data_get($portfolioEval, \'project_type.key\'));',
            '$this->assertFalse((bool) $ecommerceEval[\'allowed\']);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $projectTypeFlagsUnitTest);
        }

        foreach ([
            'portfolio_items',
            'portfolio_images',
            "Schema::hasColumns('portfolio_items'",
            "Schema::hasColumns('portfolio_images'",
            '$portfolioItemId = DB::table(\'portfolio_items\')->insertGetId([',
            "DB::table('portfolio_images')->insert([",
            '$this->assertDatabaseHas(\'portfolio_items\', [\'id\' => $portfolioItemId, \'slug\' => \'brand-redesign\', \'status\' => \'published\']);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $verticalSchemaFeatureTest);
        }

        foreach ([
            "portfolio: { en: 'Portfolio Components'",
            'requiredModules: [MODULE_PORTFOLIO]',
            "key: 'portfolio'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationFrontendContract);
        }
        $this->assertStringContainsString("key: 'portfolio'", $activationUnitTest);

        foreach ([
            "rowsByKey['port.portfolioDetail']",
            'CmsPortfolioBuilderCoverage.contract.test.ts',
            'assertSame(\'equivalent\', $rowsByKey[\'port.portfolioDetail\'][\'status\']);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $coverageGapAuditUnitTest);
        }

        foreach ([
            'BuilderServicePublicVerticalRuntimeHelpersContractTest',
            'window.WebbyPortfolio = window.WebbyPortfolio || {};',
            'window.WebbyPortfolio.mountProjectsWidget = mountPortfolioProjectsWidget;',
            'window.WebbyPortfolio.mountProjectDetailWidget = mountPortfolioProjectDetailWidget;',
            'data-webby-portfolio-gallery-slider',
            'data-webby-portfolio-gallery-lightbox',
            'data-webby-portfolio-gallery-lightbox-trigger',
            'window.WebbyPortfolio.mountGalleryWidget = mountPortfolioGalleryWidget;',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }

        foreach ([
            'webu-public-core-minimal.v1.openapi.yaml',
            'webu-services-booking-minimal.v1.openapi.yaml',
            '- services + bookings',
        ] as $needle) {
            $this->assertStringContainsString($needle, $minimalOpenApiDeliverableTest);
        }
    }
}
