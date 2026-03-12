<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryRealEstateComponentsRs1001ClosureAuditSyncTest extends TestCase
{
    public function test_rs_10_01_closure_audit_locks_real_estate_runtime_hooks_endpoints_provider_markers_and_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_REAL_ESTATE_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_10_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_REAL_ESTATE_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_10_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $webRoutesPath = base_path('routes/web.php');
        $publicSiteControllerPath = base_path('app/Http/Controllers/Cms/PublicSiteController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $publicCoreOpenApiPath = base_path('docs/openapi/webu-public-core-minimal.v1.openapi.yaml');

        $cmsPublicVerticalFeatureTestPath = base_path('tests/Feature/Cms/CmsPublicVerticalModulesEndpointsTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryRealEstateComponentsRs1001BaselineGapAuditSyncTest.php');
        $moduleLockTestPath = base_path('tests/Unit/UniversalRealEstateModuleComponentsP5F4Test.php');
        $frontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsRealEstateBuilderCoverage.contract.test.ts');
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
            $moduleLockTestPath,
            $frontendContractPath,
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
        $moduleLockTest = File::get($moduleLockTestPath);
        $frontendContract = File::get($frontendContractPath);
        $activationUnitTest = File::get($activationUnitTestPath);
        $minimalOpenApiDeliverableTest = File::get($minimalOpenApiDeliverableTestPath);

        foreach ([
            '# 10) REAL ESTATE COMPONENTS',
            '## 10.1 re.propertyGrid',
            'Data: GET /properties',
            '## 10.2 re.propertyDetail',
            'Data: GET /properties/:slug',
            '## 10.3 re.map',
            'Content: provider (google/mapbox), markers from properties',
            'Data: GET /properties (lat/lng)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-10-01` (`DONE`, `P1`)',
            'UNIVERSAL_COMPONENT_LIBRARY_REAL_ESTATE_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_10_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_REAL_ESTATE_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_10_01_2026_02_26.md',
            'UniversalComponentLibraryRealEstateComponentsRs1001BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryRealEstateComponentsRs1001ClosureAuditSyncTest.php',
            'CmsPublicVerticalModulesEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            '`✅` baseline parity/gap audit is preserved and superseded by a closure audit with public property endpoints + standalone `window.WebbyRealEstate` runtime hook/selectors evidence',
            '`✅` public property list/detail endpoints are feature-tested (`GET /public/sites/{site}/properties`, `GET /public/sites/{site}/properties/{slug}`) including marker-flow fields (`lat`, `lng`) and property list filter semantics (`q`, `min_price`) via `CmsPublicVerticalModulesEndpointsTest.php`',
            '`✅` `BuilderService` now exposes standalone `re.propertyGrid` / `re.propertyDetail` / `re.map` runtime selectors/mounts and `window.WebbyRealEstate` helper APIs (`listProperties`, `getProperty`, `mountPropertiesWidget`, `mountPropertyDetailWidget`, `mountMapWidget`) with explicit map provider/marker runtime contract markers (`data-webby-realestate-map-provider`, `data-webby-realestate-map-marker`)',
            '`✅` DoD closure achieved: property list/detail bindings and map provider/marker data-flow smoke verification are evidenced',
            '`🧪` RS-10-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Goal (`RS-10-01` Closure Pass)',
            '## ✅ What Was Done (Closure Pass)',
            'GET /public/sites/{site}/properties',
            'GET /public/sites/{site}/properties/{slug}',
            'properties(...)',
            'property(...)',
            'q',
            'min_price',
            'max_price',
            'limit',
            'lat',
            'lng',
            'window.WebbyRealEstate',
            'listProperties(...)',
            'getProperty(...)',
            'mountPropertiesWidget',
            'mountPropertyDetailWidget',
            'mountMapWidget',
            'map_provider',
            'google',
            'mapbox',
            'normalizeRealEstateMapProvider(...)',
            'data-webby-realestate-map-provider',
            'data-webby-realestate-map-marker',
            '## Executive Result (`RS-10-01`)',
            '`RS-10-01` is now **DoD-complete** as a real-estate parity runtime verification task.',
            '## Real Estate Runtime Closure Matrix (`re.propertyGrid`, `re.propertyDetail`, `re.map`)',
            'accepted_equivalent_variant',
            '## Endpoint + Provider Integration Closure Matrix (`GET /properties`, `GET /properties/:slug`, provider/markers`)',
            '## Map Provider + Marker Data-Flow Closure (`re.map`)',
            'Builder Preview Provider Contract (`Cms.tsx`)',
            'Runtime Provider + Marker Flow Closure (`BuilderService`)',
            'data-lat',
            'data-lng',
            '## Property List Filters + Marker Fields Verification (`re.propertyGrid` + `re.map` data path)',
            'min_price',
            'meta.query',
            '## Published Runtime Hook Closure (`BuilderService`)',
            '[data-webby-realestate-map]',
            '## DoD Closure Matrix (`RS-10-01`)',
            'map provider integration contract (`google/mapbox`) + marker data flow',
            '## Remaining Exactness / Modeling Gaps (Truthful, Non-Blocking for `RS-10-01` DoD)',
            'webu_realestate_search_filters_01',
            '## DoD Verdict (`RS-10-01`)',
            '`RS-10-01` passes and is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            'webu_realestate_map_01',
            'map_provider',
            'data-webu-map-provider',
            "t('Provider')",
            "if (normalizedSectionType === 'webu_realestate_map_01')",
            "if (normalizedSectionType === 'webu_realestate_property_detail_01')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            "Route::get('/{site}/properties', [PublicSiteController::class, 'properties'])->name('public.sites.properties.index');",
            "Route::get('/{site}/properties/{slug}', [PublicSiteController::class, 'property'])->name('public.sites.properties.show');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }

        foreach ([
            'public function properties(Request $request, Site $site): JsonResponse',
            'public function property(Request $request, Site $site, string $slug): JsonResponse',
            "'q' => ['nullable', 'string', 'max:255']",
            "'min_price' => ['nullable', 'numeric', 'min:0']",
            "'max_price' => ['nullable', 'numeric', 'min:0']",
            '\'lat\' => $row->lat !== null ? (float) $row->lat : null,',
            '\'lng\' => $row->lng !== null ? (float) $row->lng : null,',
            '\'images\' => $images,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicSiteController);
        }

        foreach ([
            'function realEstateListProperties(params) {',
            'function normalizeRealEstateMapProvider(value) {',
            'function mountRealEstatePropertiesWidget(container, options) {',
            'function mountRealEstateMapWidget(container, options) {',
            'window.WebbyRealEstate = window.WebbyRealEstate || {};',
            'window.WebbyRealEstate.listProperties = function (params) { return realEstateListProperties(params); };',
            "window.WebbyRealEstate.getProperty = function (slug) { return cmsPublicJson('/properties/' + encodeURIComponent(String(slug || ''))); };",
            'window.WebbyRealEstate.mountPropertiesWidget = mountRealEstatePropertiesWidget;',
            'window.WebbyRealEstate.mountPropertyDetailWidget = mountRealEstatePropertyDetailWidget;',
            'window.WebbyRealEstate.mountMapWidget = mountRealEstateMapWidget;',
            'data-webby-realestate-map-provider',
            'data-webby-realestate-map-marker',
            'Map provider: ',
            'data-lat="',
            'data-lng="',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            '/public/sites/{site}/properties:',
            'summary: Public real-estate properties list',
            '/public/sites/{site}/properties/{slug}:',
            'summary: Public real-estate property detail by slug',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicCoreOpenApi);
        }

        foreach ([
            'test_public_blog_portfolio_properties_restaurant_and_hotel_endpoints_return_site_scoped_data',
            "route('public.sites.properties.index'",
            "route('public.sites.properties.show'",
            "'min_price' => 200000",
            "'q' => 'suburb'",
            "->assertJsonPath('items.0.lat', 41.7151)",
            "->assertJsonPath('items.0.lng', 44.8271)",
            "->assertJsonPath('meta.query', 'suburb')",
            "->assertJsonPath('items.0.slug', 'suburban-house')",
            "->assertJsonPath('property.lat', 41.7151)",
            "->assertJsonPath('property.lng', 44.8271)",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsPublicVerticalFeatureTest);
        }

        foreach ([
            'BuilderServicePublicVerticalRuntimeHelpersContractTest',
            'window.WebbyRealEstate = window.WebbyRealEstate || {};',
            'window.WebbyRealEstate.listProperties = function (params) { return realEstateListProperties(params); };',
            'function normalizeRealEstateMapProvider(value) {',
            'function mountRealEstateMapWidget(container, options) {',
            'data-webby-realestate-map-provider',
            'data-webby-realestate-map-marker',
            'Map provider: ',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }

        foreach ([
            'test_p5_f4_02_real_estate_module_and_components_contract_is_locked',
            'MODULE_REAL_ESTATE',
            'webu_realestate_map_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $moduleLockTest);
        }

        foreach ([
            'CMS real-estate builder component coverage contracts',
            'webu_realestate_map_01',
            'data-webby-realestate-map',
        ] as $needle) {
            $this->assertStringContainsString($needle, $frontendContract);
        }

        $this->assertStringContainsString("key: 'real_estate'", $activationUnitTest);

        foreach ([
            'webu-public-core-minimal.v1.openapi.yaml',
            'webu-services-booking-minimal.v1.openapi.yaml',
        ] as $needle) {
            $this->assertStringContainsString($needle, $minimalOpenApiDeliverableTest);
        }
    }
}
