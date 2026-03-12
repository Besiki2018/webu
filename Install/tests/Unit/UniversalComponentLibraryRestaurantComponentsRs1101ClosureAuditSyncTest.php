<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryRestaurantComponentsRs1101ClosureAuditSyncTest extends TestCase
{
    public function test_rs_11_01_closure_audit_locks_restaurant_runtime_hooks_endpoints_filters_validation_and_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_RESTAURANT_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_11_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_RESTAURANT_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_11_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $webRoutesPath = base_path('routes/web.php');
        $publicSiteControllerPath = base_path('app/Http/Controllers/Cms/PublicSiteController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $publicCoreOpenApiPath = base_path('docs/openapi/webu-public-core-minimal.v1.openapi.yaml');

        $cmsPublicVerticalFeatureTestPath = base_path('tests/Feature/Cms/CmsPublicVerticalModulesEndpointsTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryRestaurantComponentsRs1101BaselineGapAuditSyncTest.php');
        $moduleLockTestPath = base_path('tests/Unit/UniversalRestaurantModuleComponentsP5F4Test.php');
        $frontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsRestaurantBuilderCoverage.contract.test.ts');
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
            '# 11) RESTAURANT COMPONENTS',
            '## 11.1 rest.menuCategories',
            'Data: GET /restaurant/menu',
            '## 11.2 rest.menuList',
            'Data: GET /restaurant/menu/items?category=...',
            '## 11.3 rest.tableReservationForm',
            'Data: POST /restaurant/reservations',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-11-01` (`DONE`, `P1`)',
            'UNIVERSAL_COMPONENT_LIBRARY_RESTAURANT_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_11_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_RESTAURANT_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_11_01_2026_02_26.md',
            'UniversalComponentLibraryRestaurantComponentsRs1101BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryRestaurantComponentsRs1101ClosureAuditSyncTest.php',
            'CmsPublicVerticalModulesEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            'public restaurant endpoint bindings are feature-tested',
            'menu filter verification (`category`, `category_id`)',
            'reservation validation error path (`422`)',
            'DoD closure achieved: menu listing bindings plus reservation submit flow pass smoke and validation checks',
            'RS-11-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Goal (`RS-11-01` Closure Pass)',
            '## ✅ What Was Done (Closure Pass)',
            'GET /public/sites/{site}/restaurant/menu',
            'GET /public/sites/{site}/restaurant/menu/items',
            'POST /public/sites/{site}/restaurant/reservations',
            'restaurantMenu(...)',
            'restaurantMenuItems(...)',
            'restaurantReservations(...)',
            'category',
            'category_id',
            '422',
            'window.WebbyRestaurant',
            'listMenuCategories(...)',
            'listMenuItems(...)',
            'createReservation(...)',
            'mountMenuCategoriesWidget',
            'mountMenuItemsWidget',
            'mountReservationFormWidget',
            'restaurantListMenuItems(params)',
            'data-webby-restaurant-category',
            'data-webby-restaurant-category-id',
            '## Executive Result (`RS-11-01`)',
            '`RS-11-01` is now **DoD-complete** as a restaurant parity runtime verification task.',
            '## Restaurant Runtime Closure Matrix (`rest.menuCategories`, `rest.menuList`, `rest.tableReservationForm`)',
            'accepted_equivalent_variant',
            '## Endpoint Integration Closure Matrix (`GET /restaurant/menu`, `GET /restaurant/menu/items`, `POST /restaurant/reservations`)',
            '## Menu Filter Closure (`rest.menuList`)',
            'Public API Menu Filter Verification (new closure evidence)',
            'category_id',
            'Desserts',
            'Churchkhela',
            '## Reservation Submit Validation Closure (`rest.tableReservationForm`)',
            '201 Created',
            '422 Validation Error',
            'assertJsonValidationErrors',
            '## Published Runtime Hook Closure (`BuilderService`)',
            '[data-webby-restaurant-menu-categories]',
            '[data-webby-restaurant-menu-items]',
            '[data-webby-restaurant-reservation-form]',
            '## Feature / Runtime Evidence Added (Closure Pass)',
            'CmsPublicVerticalModulesEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            '## DoD Closure Matrix (`RS-11-01`)',
            'menu listing bindings plus reservation submit flow smoke + validation checks',
            '## Remaining Exactness / Modeling Gaps (Truthful, Non-Blocking for `RS-11-01` DoD)',
            'slug semantics',
            '## DoD Verdict (`RS-11-01`)',
            '`RS-11-01` passes and is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            'webu_rest_menu_categories_01',
            'webu_rest_menu_items_01',
            'webu_rest_reservation_form_01',
            'data-webby-restaurant-menu-categories',
            'data-webby-restaurant-menu-items',
            'data-webby-restaurant-reservation-form',
            'applyRestaurantPreviewState',
            "if (normalizedSectionType === 'webu_rest_menu_items_01')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            "Route::get('/{site}/restaurant/menu', [PublicSiteController::class, 'restaurantMenu'])->name('public.sites.restaurant.menu');",
            "Route::get('/{site}/restaurant/menu/items', [PublicSiteController::class, 'restaurantMenuItems'])->name('public.sites.restaurant.menu-items');",
            "Route::post('/{site}/restaurant/reservations', [PublicSiteController::class, 'restaurantReservations'])",
            "->name('public.sites.restaurant.reservations.store');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }

        foreach ([
            'public function restaurantMenu(Request $request, Site $site): JsonResponse',
            'public function restaurantMenuItems(Request $request, Site $site): JsonResponse',
            'public function restaurantReservations(Request $request, Site $site): JsonResponse',
            "'category' => ['nullable', 'string', 'max:120']",
            "'category_id' => ['nullable', 'integer', 'min:1']",
            'mb_strtolower($categoryValue)',
            "'customer_name' => ['required', 'string', 'max:255']",
            "'phone' => ['required', 'string', 'max:64']",
            "'guests' => ['required', 'integer', 'min:1', 'max:50']",
            "'starts_at' => ['required', 'date']",
            "'email' => ['nullable', 'email', 'max:255']",
            "'status' => 'pending',",
            '], 201);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicSiteController);
        }

        foreach ([
            'function restaurantListMenuItems(params) {',
            "['category', 'category_id'].forEach(function (key) {",
            "container.getAttribute('data-webby-restaurant-' + key.replace(/_/g, '-'))",
            'function mountRestaurantMenuCategoriesWidget(container) {',
            'function mountRestaurantMenuItemsWidget(container, options) {',
            'function mountRestaurantReservationWidget(container) {',
            'window.WebbyRestaurant = window.WebbyRestaurant || {};',
            "window.WebbyRestaurant.listMenuCategories = function () { return cmsPublicJson('/restaurant/menu'); };",
            'window.WebbyRestaurant.listMenuItems = function (params) { return restaurantListMenuItems(params); };',
            "window.WebbyRestaurant.createReservation = function (payload) { return cmsPublicJsonPost('/restaurant/reservations', payload); };",
            'window.WebbyRestaurant.mountMenuCategoriesWidget = mountRestaurantMenuCategoriesWidget;',
            'window.WebbyRestaurant.mountMenuItemsWidget = mountRestaurantMenuItemsWidget;',
            'window.WebbyRestaurant.mountReservationFormWidget = mountRestaurantReservationWidget;',
            'data-webby-restaurant-submit',
            'data-webby-restaurant-message',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            '/public/sites/{site}/restaurant/menu:',
            'summary: Public restaurant menu categories',
            '/public/sites/{site}/restaurant/menu/items:',
            'summary: Public restaurant menu items',
            '/public/sites/{site}/restaurant/reservations:',
            'summary: Public restaurant table reservation submit',
            "'201':",
            'Reservation created',
            "'422':",
            'Validation error',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicCoreOpenApi);
        }

        foreach ([
            'test_public_blog_portfolio_properties_restaurant_and_hotel_endpoints_return_site_scoped_data',
            "route('public.sites.restaurant.menu'",
            "route('public.sites.restaurant.menu-items'",
            "'category' => 'desserts'",
            "'category_id' => \$restaurantCategoryId",
            "->assertJsonPath('items.0.name', 'Churchkhela')",
            "->assertJsonPath('items.0.category_name', 'Desserts')",
            'test_public_restaurant_and_room_reservations_endpoints_create_pending_rows',
            "route('public.sites.restaurant.reservations.store'",
            '->assertStatus(422)',
            "->assertJsonValidationErrors(['customer_name', 'phone', 'guests', 'starts_at']);",
            '->assertCreated()',
            "->assertJsonPath('reservation.status', 'pending')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsPublicVerticalFeatureTest);
        }

        foreach ([
            'BuilderServicePublicVerticalRuntimeHelpersContractTest',
            'function restaurantListMenuItems(params) {',
            'function mountRestaurantMenuItemsWidget(container, options) {',
            'window.WebbyRestaurant.listMenuItems = function (params) { return restaurantListMenuItems(params); };',
            'window.WebbyRestaurant.mountReservationFormWidget = mountRestaurantReservationWidget;',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }

        foreach ([
            'test_p5_f4_03_restaurant_module_and_components_contract_is_locked',
            'MODULE_RESTAURANT',
            'webu_rest_reservation_form_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $moduleLockTest);
        }

        foreach ([
            'CMS restaurant builder component coverage contracts',
            'webu_rest_menu_categories_01',
            'webu_rest_reservation_form_01',
            'data-webby-restaurant-menu-items',
            'data-webby-restaurant-reservation-form',
        ] as $needle) {
            $this->assertStringContainsString($needle, $frontendContract);
        }

        foreach ([
            "key: 'restaurant'",
            "key: 'restaurant_reservation'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationUnitTest);
        }

        foreach ([
            'webu-public-core-minimal.v1.openapi.yaml',
            'webu-services-booking-minimal.v1.openapi.yaml',
        ] as $needle) {
            $this->assertStringContainsString($needle, $minimalOpenApiDeliverableTest);
        }
    }
}
