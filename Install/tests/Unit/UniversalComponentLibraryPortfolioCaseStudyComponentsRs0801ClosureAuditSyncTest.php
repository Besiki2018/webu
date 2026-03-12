<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryPortfolioCaseStudyComponentsRs0801ClosureAuditSyncTest extends TestCase
{
    public function test_rs_08_01_closure_audit_locks_portfolio_case_study_runtime_hooks_endpoints_gallery_mode_and_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_PORTFOLIO_CASE_STUDY_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_08_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_PORTFOLIO_CASE_STUDY_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_08_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $webRoutesPath = base_path('routes/web.php');
        $publicSiteControllerPath = base_path('app/Http/Controllers/Cms/PublicSiteController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $publicCoreOpenApiPath = base_path('docs/openapi/webu-public-core-minimal.v1.openapi.yaml');

        $cmsPublicVerticalFeatureTestPath = base_path('tests/Feature/Cms/CmsPublicVerticalModulesEndpointsTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryPortfolioCaseStudyComponentsRs0801BaselineGapAuditSyncTest.php');
        $portfolioModuleContractTestPath = base_path('tests/Unit/UniversalPortfolioModuleComponentsP5F4Test.php');
        $portfolioFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsPortfolioBuilderCoverage.contract.test.ts');
        $activationUnitTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');
        $minimalOpenApiDeliverableTestPath = base_path('tests/Unit/MinimalOpenApiBaseModulesDeliverableTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $baselineDocPath,
            $closureDocPath,
            $cmsPath,
            $webRoutesPath,
            $publicSiteControllerPath,
            $builderServicePath,
            $publicCoreOpenApiPath,
            $cmsPublicVerticalFeatureTestPath,
            $runtimeContractTestPath,
            $baselineSyncTestPath,
            $portfolioModuleContractTestPath,
            $portfolioFrontendContractPath,
            $activationUnitTestPath,
            $minimalOpenApiDeliverableTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $closureDoc = File::get($closureDocPath);
        $cms = File::get($cmsPath);
        $routes = File::get($webRoutesPath);
        $publicSiteController = File::get($publicSiteControllerPath);
        $builderService = File::get($builderServicePath);
        $publicCoreOpenApi = File::get($publicCoreOpenApiPath);
        $cmsPublicVerticalFeatureTest = File::get($cmsPublicVerticalFeatureTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);
        $portfolioModuleContractTest = File::get($portfolioModuleContractTestPath);
        $portfolioFrontendContract = File::get($portfolioFrontendContractPath);
        $activationUnitTest = File::get($activationUnitTestPath);
        $minimalOpenApiDeliverableTest = File::get($minimalOpenApiDeliverableTestPath);

        foreach ([
            '# 8) PORTFOLIO / CASE STUDY COMPONENTS',
            '## 8.1 port.portfolioGrid',
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
            '`🧪` RS-08-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Goal (`RS-08-01` Closure Pass)',
            '## ✅ What Was Done (Closure Pass)',
            'GET /public/sites/{site}/portfolio',
            'GET /public/sites/{site}/portfolio/{slug}',
            'portfolioItems(...)',
            'portfolioItem(...)',
            'item.images',
            'window.WebbyPortfolio',
            'listProjects(...)',
            'getProject(...)',
            'mountProjectsWidget',
            'mountProjectDetailWidget',
            'mountGalleryWidget',
            'show_lightbox',
            'portfolio-gallery-slider',
            'portfolio-gallery-lightbox',
            'data-webby-portfolio-gallery-slider',
            'data-webby-portfolio-gallery-lightbox',
            'data-webby-portfolio-gallery-lightbox-trigger',
            '## Executive Result (`RS-08-01`)',
            '`RS-08-01` is now **DoD-complete** as a portfolio/case-study parity runtime verification task.',
            '## Portfolio / Case Study Runtime Closure Matrix (`port.portfolioGrid`, `port.portfolioDetail`, `port.gallery`)',
            'accepted_equivalent_variant',
            '## Endpoint Integration Closure Matrix (`GET /portfolio`, `GET /portfolio/:slug`, portfolio/media)`',
            'portfolio_images',
            '## Gallery Mode Parity Closure (`masonry/slider/lightbox`)',
            '### Builder Preview Mode/Lightbox Smoke Evidence (`Cms.tsx`)',
            '### Runtime Gallery Mode/Lightbox Smoke Evidence (`BuilderService`)',
            'Lightbox preview for media #',
            '## Feature / Runtime Evidence Added (Closure Pass)',
            'CmsPublicVerticalModulesEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            '## DoD Closure Matrix (`RS-08-01`)',
            'all 3 components validated and smoke-tested',
            '## Remaining Exactness / Modeling Gaps (Truthful, Non-Blocking for `RS-08-01` DoD)',
            'categories filter',
            '## DoD Verdict (`RS-08-01`)',
            '`RS-08-01` passes and is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            "show_lightbox: { type: 'boolean', title: 'Lightbox', default: true }",
            'parseBooleanProp(effectiveProps.show_lightbox, true)',
            "sliderMarker.setAttribute('data-webu-role', 'portfolio-gallery-slider');",
            "lightboxMarker.setAttribute('data-webu-role', 'portfolio-gallery-lightbox');",
            "tile.setAttribute('data-webu-role', showLightbox ? 'portfolio-gallery-lightbox-trigger' : 'portfolio-gallery-tile');",
            "if (normalizedSectionType === 'webu_portfolio_gallery_01')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            "Route::get('/{site}/portfolio', [PublicSiteController::class, 'portfolioItems'])->name('public.sites.portfolio.index');",
            "Route::get('/{site}/portfolio/{slug}', [PublicSiteController::class, 'portfolioItem'])->name('public.sites.portfolio.show');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }

        foreach ([
            'public function portfolioItems(Request $request, Site $site): JsonResponse',
            'public function portfolioItem(Request $request, Site $site, string $slug): JsonResponse',
            'Public portfolio items list endpoint.',
            'Public portfolio item detail endpoint by slug.',
            'DB::table(\'portfolio_images\')',
            '\'images\' => $images,',
            '\'error\' => \'Portfolio item not found.\'',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicSiteController);
        }

        foreach ([
            'function mountPortfolioProjectsWidget(container) {',
            'function mountPortfolioProjectDetailWidget(container) {',
            'function mountPortfolioGalleryWidget(container, options) {',
            'data-webby-portfolio-gallery-grid',
            'data-webby-portfolio-gallery-slider',
            'data-webby-portfolio-gallery-lightbox',
            'data-webby-portfolio-gallery-lightbox-trigger',
            'data-webby-portfolio-lightbox-close',
            'Lightbox preview for media #',
            'window.WebbyPortfolio = window.WebbyPortfolio || {};',
            "window.WebbyPortfolio.listProjects = function () { return cmsPublicJson('/portfolio'); };",
            "window.WebbyPortfolio.getProject = function (slug) { return cmsPublicJson('/portfolio/' + encodeURIComponent(String(slug || ''))); };",
            'window.WebbyPortfolio.mountProjectsWidget = mountPortfolioProjectsWidget;',
            'window.WebbyPortfolio.mountProjectDetailWidget = mountPortfolioProjectDetailWidget;',
            'window.WebbyPortfolio.mountGalleryWidget = mountPortfolioGalleryWidget;',
            '[data-webby-portfolio-projects]',
            '[data-webby-portfolio-project-detail]',
            '[data-webby-portfolio-gallery]',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            '/public/sites/{site}/portfolio:',
            'summary: Public portfolio projects list',
            '/public/sites/{site}/portfolio/{slug}:',
            'summary: Public portfolio project detail by slug',
            'description: Portfolio list',
            'description: Portfolio item detail',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicCoreOpenApi);
        }

        foreach ([
            'test_public_blog_portfolio_properties_restaurant_and_hotel_endpoints_return_site_scoped_data',
            "route('public.sites.portfolio.index'",
            "route('public.sites.portfolio.show'",
            "->assertJsonPath('item.slug', 'brand-redesign')",
            "->assertJsonPath('item.images.0.media_id', 101)",
            "->assertJsonPath('item.images.1.media_id', 102)",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsPublicVerticalFeatureTest);
        }

        foreach ([
            'BuilderServicePublicVerticalRuntimeHelpersContractTest',
            'window.WebbyPortfolio = window.WebbyPortfolio || {};',
            'window.WebbyPortfolio.mountGalleryWidget = mountPortfolioGalleryWidget;',
            'data-webby-portfolio-gallery-slider',
            'data-webby-portfolio-gallery-lightbox',
            'data-webby-portfolio-gallery-lightbox-trigger',
            'data-webby-portfolio-lightbox-close',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }

        foreach ([
            'test_p5_f4_01_portfolio_module_and_components_contract_is_locked',
            'MODULE_PORTFOLIO',
            'webu_portfolio_projects_grid_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $portfolioModuleContractTest);
        }

        foreach ([
            'CMS portfolio builder component coverage contracts',
            'webu_portfolio_gallery_01',
            'applyPortfolioPreviewState',
        ] as $needle) {
            $this->assertStringContainsString($needle, $portfolioFrontendContract);
        }

        $this->assertStringContainsString("key: 'portfolio'", $activationUnitTest);

        foreach ([
            'webu-public-core-minimal.v1.openapi.yaml',
            'webu-services-booking-minimal.v1.openapi.yaml',
        ] as $needle) {
            $this->assertStringContainsString($needle, $minimalOpenApiDeliverableTest);
        }
    }
}
