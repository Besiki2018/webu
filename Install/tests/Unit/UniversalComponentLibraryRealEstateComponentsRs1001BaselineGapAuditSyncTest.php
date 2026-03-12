<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryRealEstateComponentsRs1001BaselineGapAuditSyncTest extends TestCase
{
    public function test_rs_10_01_progress_audit_doc_locks_real_estate_components_parity_endpoint_provider_and_runtime_gap_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_REAL_ESTATE_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_10_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_REAL_ESTATE_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_10_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $aliasMapPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $webRoutesPath = base_path('routes/web.php');
        $publicSiteControllerPath = base_path('app/Http/Controllers/Cms/PublicSiteController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $publicCoreOpenApiPath = base_path('docs/openapi/webu-public-core-minimal.v1.openapi.yaml');

        $moduleLockTestPath = base_path('tests/Unit/UniversalRealEstateModuleComponentsP5F4Test.php');
        $frontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsRealEstateBuilderCoverage.contract.test.ts');
        $moduleRegistryFeatureTestPath = base_path('tests/Feature/Cms/CmsModuleRegistryTest.php');
        $projectTypeFlagsUnitTestPath = base_path('tests/Unit/CmsProjectTypeModuleFeatureFlagServiceTest.php');
        $verticalSchemaFeatureTestPath = base_path('tests/Feature/Platform/UniversalVerticalModulesNormalizationTablesSchemaTest.php');
        $cmsPublicVerticalFeatureTestPath = base_path('tests/Feature/Cms/CmsPublicVerticalModulesEndpointsTest.php');
        $activationFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts');
        $activationUnitTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');
        $coverageGapAuditUnitTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php');
        $aliasMapUnitTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecEquivalenceAliasMapTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $closureSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryRealEstateComponentsRs1001ClosureAuditSyncTest.php');
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
            $moduleLockTestPath,
            $frontendContractPath,
            $moduleRegistryFeatureTestPath,
            $projectTypeFlagsUnitTestPath,
            $verticalSchemaFeatureTestPath,
            $cmsPublicVerticalFeatureTestPath,
            $activationFrontendContractPath,
            $activationUnitTestPath,
            $coverageGapAuditUnitTestPath,
            $aliasMapUnitTestPath,
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
        $moduleLockTest = File::get($moduleLockTestPath);
        $frontendContract = File::get($frontendContractPath);
        $moduleRegistryFeatureTest = File::get($moduleRegistryFeatureTestPath);
        $projectTypeFlagsUnitTest = File::get($projectTypeFlagsUnitTestPath);
        $verticalSchemaFeatureTest = File::get($verticalSchemaFeatureTestPath);
        $cmsPublicVerticalFeatureTest = File::get($cmsPublicVerticalFeatureTestPath);
        $activationFrontendContract = File::get($activationFrontendContractPath);
        $activationUnitTest = File::get($activationUnitTestPath);
        $coverageGapAuditUnitTest = File::get($coverageGapAuditUnitTestPath);
        $aliasMapUnitTest = File::get($aliasMapUnitTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);
        $minimalOpenApiDeliverableTest = File::get($minimalOpenApiDeliverableTestPath);

        foreach ([
            '# 10) REAL ESTATE COMPONENTS',
            '## 10.1 re.propertyGrid',
            'Content: filters (price/rooms/location), map toggle',
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
            '`⚠️` source exactness gaps remain (source `propertyGrid` filter intent remains split into `webu_realestate_search_filters_01`; provider contract is an accepted explicit runtime/preview config, not a full SDK integration)',
            '`🧪` RS-10-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '## Scope',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            '## Audit Inputs Reviewed',
            '## What Was Done (This Pass)',
            '## Executive Result (`RS-10-01`)',
            '## Real Estate Parity Matrix',
            '### Matrix (`content/style/panel-preview/runtime-data/endpoint/filter-map-toggle-marker-provider/gating/tests`)',
            '`re.propertyGrid`',
            '`re.propertyDetail`',
            '`re.map`',
            '`webu_realestate_property_grid_01`',
            '`webu_realestate_property_detail_01`',
            '`webu_realestate_map_01`',
            '## Endpoint + Map Provider Contract Verification (`GET /properties`, `GET /properties/:slug`, markers/provider`)',
            'provider (google/mapbox)',
            '`partial_generic_public_only`',
            '## Filter / Map Toggle / Marker Rendering Verification',
            '`webu_realestate_search_filters_01`',
            '`show_price_range`',
            '`pins_count`',
            '`zoom`',
            '`map_mode`',
            '## Real Estate Baseline (Module Gating + Storage)',
            '`properties`',
            '`property_images`',
            '## Runtime Widget / Binding Status (`propertyGrid`, `propertyDetail`, `map`)',
            'no `window.WebbyRealEstate` helper',
            '## DoD Verdict (`RS-10-01`)',
            'Conclusion: `RS-10-01` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD + Parity Closure)',
            '## Conclusion',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'webu_realestate_property_grid_01',
            'webu_realestate_property_detail_01',
            'webu_realestate_map_01',
            'webu_realestate_search_filters_01',
            'data-webby-realestate-properties',
            'data-webby-realestate-property-detail',
            'data-webby-realestate-map',
            'data-webby-realestate-search',
            'BUILDER_REAL_ESTATE_DISCOVERY_LIBRARY_SECTIONS',
            'syntheticRealEstateSectionKeySet',
            'createSyntheticRealEstatePlaceholder',
            'applyRealEstatePreviewState',
            'properties_count',
            'columns_desktop',
            'columns_mobile',
            'show_price',
            'show_location',
            'show_specs',
            'show_map_preview',
            'show_inquiry_cta',
            'show_price_range',
            'show_bedrooms',
            'show_property_type',
            'map_provider',
            'map_mode',
            'pins_count',
            'center_lat',
            'center_lng',
            'zoom',
            'data-webu-map-provider',
            "if (normalizedSectionType === 'webu_realestate_property_grid_01')",
            "if (normalizedSectionType === 'webu_realestate_property_detail_01')",
            "if (normalizedSectionType === 'webu_realestate_map_01')",
            "if (normalizedSectionType === 'webu_realestate_search_filters_01')",
            "key: 'real_estate'",
            'requiredModules: [MODULE_REAL_ESTATE]',
            "t('Provider')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'source_component_key": "re.propertyGrid"',
            'webu_realestate_property_grid_01',
            'source_component_key": "re.propertyDetail"',
            'webu_realestate_property_detail_01',
            'source_component_key": "re.map"',
            'webu_realestate_map_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            "Route::get('/{site}/pages/{slug}', [PublicSiteController::class, 'page'])",
            "Route::get('/{site}/assets/{path}', [PublicSiteController::class, 'asset'])",
            "Route::get('/{site}/properties', [PublicSiteController::class, 'properties'])->name('public.sites.properties.index');",
            "Route::get('/{site}/properties/{slug}', [PublicSiteController::class, 'property'])->name('public.sites.properties.show');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }

        foreach ([
            'public function page(Request $request, Site $site, string $slug): JsonResponse',
            'public function asset(Request $request, Site $site, string $path): BinaryFileResponse|JsonResponse',
            'public function properties(Request $request, Site $site): JsonResponse',
            'public function property(Request $request, Site $site, string $slug): JsonResponse',
            'Public real-estate properties list endpoint.',
            'Public real-estate property detail endpoint by slug.',
            "'q' => ['nullable', 'string', 'max:255']",
            "'min_price' => ['nullable', 'numeric', 'min:0']",
            "'max_price' => ['nullable', 'numeric', 'min:0']",
            "'limit' => ['nullable', 'integer', 'min:1', 'max:100']",
            '\'lat\' => $row->lat !== null ? (float) $row->lat : null,',
            '\'lng\' => $row->lng !== null ? (float) $row->lng : null,',
            '\'images\' => $images,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicSiteController);
        }

        foreach ([
            'window.WebbyRealEstate = window.WebbyRealEstate || {};',
            'window.WebbyRealEstate.listProperties = function (params) { return realEstateListProperties(params); };',
            "window.WebbyRealEstate.getProperty = function (slug) { return cmsPublicJson('/properties/' + encodeURIComponent(String(slug || ''))); };",
            'window.WebbyRealEstate.mountPropertiesWidget = mountRealEstatePropertiesWidget;',
            'window.WebbyRealEstate.mountPropertyDetailWidget = mountRealEstatePropertyDetailWidget;',
            'window.WebbyRealEstate.mountMapWidget = mountRealEstateMapWidget;',
            'function realEstateListProperties(params) {',
            'function normalizeRealEstateMapProvider(value) {',
            'function mountRealEstatePropertiesWidget(container, options) {',
            'function mountRealEstatePropertyDetailWidget(container) {',
            'function mountRealEstateMapWidget(container, options) {',
            'data-webby-realestate-map-provider',
            'data-webby-realestate-map-marker',
            'Map provider: ',
            '[data-webby-realestate-properties]',
            '[data-webby-realestate-property-detail]',
            '[data-webby-realestate-map]',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            '/public/sites/{site}/pages/{slug}:',
            '/public/sites/{site}/assets/{path}:',
            '/public/sites/{site}/properties:',
            'summary: Public real-estate properties list',
            '/public/sites/{site}/properties/{slug}:',
            'summary: Public real-estate property detail by slug',
            'paths:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicCoreOpenApi);
        }

        foreach ([
            'class UniversalRealEstateModuleComponentsP5F4Test extends TestCase',
            'test_p5_f4_02_real_estate_module_and_components_contract_is_locked',
            'BUILDER_REAL_ESTATE_DISCOVERY_LIBRARY_SECTIONS',
            'test_real_estate_module_is_exposed_for_real_estate_project_type_and_blocked_for_ecommerce_override',
            'test_real_estate_project_type_allows_real_estate_module_and_ecommerce_type_denies_it_when_framework_enabled',
            'CMS real-estate builder component coverage contracts',
        ] as $needle) {
            $this->assertStringContainsString($needle, $moduleLockTest);
        }

        foreach ([
            'CMS real-estate builder component coverage contracts',
            'webu_realestate_property_grid_01',
            'webu_realestate_property_detail_01',
            'webu_realestate_map_01',
            'data-webby-realestate-properties',
            'data-webby-realestate-map',
            'createSyntheticRealEstatePlaceholder',
            'applyRealEstatePreviewState',
        ] as $needle) {
            $this->assertStringContainsString($needle, $frontendContract);
        }

        foreach ([
            'test_real_estate_module_is_exposed_for_real_estate_project_type_and_blocked_for_ecommerce_override',
            'MODULE_REAL_ESTATE',
        ] as $needle) {
            $this->assertStringContainsString($needle, $moduleRegistryFeatureTest);
        }

        foreach ([
            'test_real_estate_project_type_allows_real_estate_module_and_ecommerce_type_denies_it_when_framework_enabled',
            "makeSiteWithTemplateCategory('real_estate')",
            'MODULE_REAL_ESTATE',
        ] as $needle) {
            $this->assertStringContainsString($needle, $projectTypeFlagsUnitTest);
        }

        foreach ([
            'properties',
            'property_images',
            'description_html',
            'modern-loft',
        ] as $needle) {
            $this->assertStringContainsString($needle, $verticalSchemaFeatureTest);
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
            'CMS universal component library activation contracts',
            'builderSectionAvailabilityMatrix',
            'BUILDER_UNIVERSAL_TAXONOMY_GROUP_ORDER',
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationFrontendContract);
        }
        foreach ([
            "key: 'real_estate'",
            "key: 'restaurant'",
            "key: 'hotel'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationUnitTest);
        }

        foreach ([
            "rowsByKey['re.propertyDetail']",
            "rowsByKey['re.map']",
            'CmsRealEstateBuilderCoverage.contract.test.ts',
        ] as $needle) {
            $this->assertStringContainsString($needle, $coverageGapAuditUnitTest);
        }

        foreach ([
            "rowsByKey['re.propertyDetail']",
            'webu_realestate_property_detail_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMapUnitTest);
        }

        foreach ([
            'BuilderServicePublicVerticalRuntimeHelpersContractTest',
            'window.WebbyRealEstate = window.WebbyRealEstate || {};',
            'window.WebbyRealEstate.listProperties = function (params) { return realEstateListProperties(params); };',
            'function normalizeRealEstateMapProvider(value) {',
            'function mountRealEstateMapWidget(container, options) {',
            'data-webby-realestate-map-provider',
            'data-webby-realestate-map-marker',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }

        foreach ([
            'webu-public-core-minimal.v1.openapi.yaml',
            '/public/sites/{site}/pages/{slug}:',
            '/public/sites/{site}/assets/{path}:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $minimalOpenApiDeliverableTest);
        }
    }
}
