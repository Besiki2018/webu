<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryRestaurantComponentsRs1101BaselineGapAuditSyncTest extends TestCase
{
    public function test_rs_11_01_progress_audit_doc_locks_restaurant_components_baseline_gap_truth_and_closure_supersession(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_RESTAURANT_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_11_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_RESTAURANT_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_11_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $aliasMapPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $webRoutesPath = base_path('routes/web.php');
        $publicSiteControllerPath = base_path('app/Http/Controllers/Cms/PublicSiteController.php');
        $publicBookingControllerPath = base_path('app/Http/Controllers/Booking/PublicBookingController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $publicCoreOpenApiPath = base_path('docs/openapi/webu-public-core-minimal.v1.openapi.yaml');

        $moduleLockTestPath = base_path('tests/Unit/UniversalRestaurantModuleComponentsP5F4Test.php');
        $frontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsRestaurantBuilderCoverage.contract.test.ts');
        $moduleRegistryFeatureTestPath = base_path('tests/Feature/Cms/CmsModuleRegistryTest.php');
        $projectTypeFlagsUnitTestPath = base_path('tests/Unit/CmsProjectTypeModuleFeatureFlagServiceTest.php');
        $verticalSchemaFeatureTestPath = base_path('tests/Feature/Platform/UniversalVerticalModulesNormalizationTablesSchemaTest.php');
        $cmsPublicVerticalFeatureTestPath = base_path('tests/Feature/Cms/CmsPublicVerticalModulesEndpointsTest.php');
        $activationFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts');
        $activationUnitTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');
        $coverageGapAuditUnitTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php');
        $aliasMapUnitTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecEquivalenceAliasMapTest.php');
        $bookingPublicApiTestPath = base_path('tests/Feature/Booking/BookingPublicApiTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $closureSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryRestaurantComponentsRs1101ClosureAuditSyncTest.php');
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
            $publicBookingControllerPath,
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
            $bookingPublicApiTestPath,
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
        $publicBookingController = File::get($publicBookingControllerPath);
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
        $bookingPublicApiTest = File::get($bookingPublicApiTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);
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
            'baseline parity/gap audit is preserved and superseded by a closure audit',
            'public restaurant endpoint bindings are feature-tested',
            'menu filter verification (`category`, `category_id`)',
            'reservation validation error path (`422`)',
            '`BuilderService` now exposes standalone `rest.menuCategories` / `rest.menuList` / `rest.tableReservationForm` runtime selectors/mounts and `window.WebbyRestaurant` helper APIs',
            'minimal public OpenAPI restaurant route coverage is present',
            'DoD closure achieved: menu listing bindings plus reservation submit flow pass smoke and validation checks',
            'source exactness gap remains (source menu filter slug semantics are modeled via current name/case-insensitive `category` and numeric `category_id` contracts; accepted runtime-equivalent for `RS-11-01`)',
            'RS-11-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            '## Executive Result (`RS-11-01`)',
            '## Restaurant Parity Matrix',
            '## Endpoint Contract Verification (`GET /restaurant/menu`, `GET /restaurant/menu/items`, `POST /restaurant/reservations`)',
            '`variant_generic_booking_only`',
            '## Menu + Reservation Flow Verification',
            '`window.WebbyBooking` helper methods (`listServices`, `getSlots`, `createBooking`)',
            '## Runtime Widget / Binding Status (`menuCategories`, `menuList`, `tableReservationForm`)',
            'no `window.WebbyRestaurant` helper',
            'Conclusion: `RS-11-01` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD + Parity Closure)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'webu_rest_menu_categories_01',
            'webu_rest_menu_items_01',
            'webu_rest_reservation_slots_01',
            'webu_rest_reservation_form_01',
            'data-webby-restaurant-menu-categories',
            'data-webby-restaurant-menu-items',
            'data-webby-restaurant-reservation-form',
            'BUILDER_RESTAURANT_DISCOVERY_LIBRARY_SECTIONS',
            'syntheticRestaurantSectionKeySet',
            'syntheticRestaurantReservationSectionKeySet',
            'createSyntheticRestaurantPlaceholder',
            'applyRestaurantPreviewState',
            'categories_count',
            'variant',
            'show_icons',
            'items_count',
            'show_price',
            'show_description',
            'show_badges',
            'show_phone',
            'show_email',
            'show_notes',
            'submit_label',
            "if (normalizedSectionType === 'webu_rest_menu_categories_01')",
            "if (normalizedSectionType === 'webu_rest_menu_items_01')",
            "if (normalizedSectionType === 'webu_rest_reservation_form_01')",
            "key: 'restaurant'",
            "key: 'restaurant_reservation'",
            'requiredModules: [MODULE_RESTAURANT]',
            'requiredModules: [MODULE_RESTAURANT, MODULE_BOOKING]',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'source_component_key": "rest.menuCategories"',
            'webu_rest_menu_categories_01',
            'source_component_key": "rest.menuList"',
            'webu_rest_menu_items_01',
            'source_component_key": "rest.tableReservationForm"',
            'webu_rest_reservation_form_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            "Route::get('/{site}/booking/services', [PublicBookingController::class, 'services'])",
            "Route::get('/{site}/booking/slots', [PublicBookingController::class, 'slots'])",
            "Route::post('/{site}/booking/bookings', [PublicBookingController::class, 'createBooking'])",
            "Route::get('/{site}/restaurant/menu', [PublicSiteController::class, 'restaurantMenu'])->name('public.sites.restaurant.menu');",
            "Route::get('/{site}/restaurant/menu/items', [PublicSiteController::class, 'restaurantMenuItems'])->name('public.sites.restaurant.menu-items');",
            "Route::post('/{site}/restaurant/reservations', [PublicSiteController::class, 'restaurantReservations'])",
            "->name('public.sites.restaurant.reservations.store');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }

        foreach ([
            'public function restaurantMenu(Request $request, Site $site): JsonResponse',
            'public function restaurantMenuItems(Request $request, Site $site): JsonResponse',
            'public function restaurantReservations(Request $request, Site $site): JsonResponse',
            'Public restaurant menu categories endpoint.',
            'Public restaurant menu items endpoint (optional category filter).',
            'Public restaurant table-reservation submit endpoint.',
            "'category' => ['nullable', 'string', 'max:120']",
            "'category_id' => ['nullable', 'integer', 'min:1']",
            "'customer_name' => ['required', 'string', 'max:255']",
            "'phone' => ['required', 'string', 'max:64']",
            "'guests' => ['required', 'integer', 'min:1', 'max:50']",
            "'starts_at' => ['required', 'date']",
            "'email' => ['nullable', 'email', 'max:255']",
            "'notes' => ['nullable', 'string', 'max:5000']",
            "'category_name' => (string) \$row->category_name,",
            "'status' => 'pending',",
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicSiteController);
        }

        foreach ([
            'class PublicBookingController extends Controller',
            'public function services(Request $request, Site $site): JsonResponse',
            'public function slots(Request $request, Site $site): JsonResponse',
            'public function createBooking(Request $request, Site $site): JsonResponse',
            'return $this->corsJson($payload, 201);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicBookingController);
        }

        foreach ([
            'window.WebbyBooking',
            '[data-webby-booking-widget]',
            'listServices',
            'getSlots',
            'createBooking',
            'window.WebbyRestaurant = window.WebbyRestaurant || {};',
            'function restaurantListMenuItems(params) {',
            "['category', 'category_id'].forEach(function (key) {",
            "container.getAttribute('data-webby-restaurant-' + key.replace(/_/g, '-'))",
            'function mountRestaurantMenuCategoriesWidget(container) {',
            'function mountRestaurantMenuItemsWidget(container, options) {',
            'function mountRestaurantReservationWidget(container) {',
            "window.WebbyRestaurant.listMenuCategories = function () { return cmsPublicJson('/restaurant/menu'); };",
            'window.WebbyRestaurant.listMenuItems = function (params) { return restaurantListMenuItems(params); };',
            "window.WebbyRestaurant.createReservation = function (payload) { return cmsPublicJsonPost('/restaurant/reservations', payload); };",
            'window.WebbyRestaurant.mountMenuCategoriesWidget = mountRestaurantMenuCategoriesWidget;',
            'window.WebbyRestaurant.mountMenuItemsWidget = mountRestaurantMenuItemsWidget;',
            'window.WebbyRestaurant.mountReservationFormWidget = mountRestaurantReservationWidget;',
            '[data-webby-restaurant-menu-categories]',
            '[data-webby-restaurant-menu-items]',
            '[data-webby-restaurant-reservation-form]',
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
            'description: Reservation created',
            "'422':",
            'description: Validation error',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicCoreOpenApi);
        }

        foreach ([
            'class UniversalRestaurantModuleComponentsP5F4Test extends TestCase',
            'test_p5_f4_03_restaurant_module_and_components_contract_is_locked',
            'MODULE_RESTAURANT',
            'MODULE_BOOKING',
        ] as $needle) {
            $this->assertStringContainsString($needle, $moduleLockTest);
        }

        foreach ([
            'CMS restaurant builder component coverage contracts',
            'webu_rest_menu_categories_01',
            'webu_rest_menu_items_01',
            'webu_rest_reservation_form_01',
            'data-webby-restaurant-menu-items',
            'data-webby-restaurant-reservation-form',
            'createSyntheticRestaurantPlaceholder',
            'applyRestaurantPreviewState',
        ] as $needle) {
            $this->assertStringContainsString($needle, $frontendContract);
        }

        foreach ([
            'test_restaurant_module_is_exposed_for_restaurant_project_type_and_blocked_for_ecommerce_override',
            'MODULE_RESTAURANT',
        ] as $needle) {
            $this->assertStringContainsString($needle, $moduleRegistryFeatureTest);
        }

        foreach ([
            'test_restaurant_project_type_allows_restaurant_module_and_ecommerce_type_denies_it_when_framework_enabled',
            "makeSiteWithTemplateCategory('restaurant')",
            'MODULE_RESTAURANT',
        ] as $needle) {
            $this->assertStringContainsString($needle, $projectTypeFlagsUnitTest);
        }

        foreach ([
            'restaurant_menu_categories',
            'restaurant_menu_items',
            'table_reservations',
            'Main Dishes',
        ] as $needle) {
            $this->assertStringContainsString($needle, $verticalSchemaFeatureTest);
        }

        foreach ([
            'CMS universal component library activation contracts',
            'builderSectionAvailabilityMatrix',
            'BUILDER_UNIVERSAL_TAXONOMY_GROUP_ORDER',
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationFrontendContract);
        }

        foreach ([
            "key: 'restaurant'",
            "key: 'restaurant_reservation'",
            "key: 'hotel'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationUnitTest);
        }

        foreach ([
            "rowsByKey['rest.tableReservationForm']",
            'CmsRestaurantBuilderCoverage.contract.test.ts',
        ] as $needle) {
            $this->assertStringContainsString($needle, $coverageGapAuditUnitTest);
        }

        foreach ([
            'webu_blog_post_detail_01',
            "rowsByKey['blog.postDetail']",
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMapUnitTest);
        }

        foreach ([
            'test_public_booking_services_slots_and_create_flow_is_visible_in_panel',
            "route('public.sites.booking.services'",
            "route('public.sites.booking.slots'",
            "route('public.sites.booking.bookings.store'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingPublicApiTest);
        }

        foreach ([
            'test_public_blog_portfolio_properties_restaurant_and_hotel_endpoints_return_site_scoped_data',
            "route('public.sites.restaurant.menu'",
            "route('public.sites.restaurant.menu-items'",
            "'category' => 'desserts'",
            "'category_id' => \$restaurantCategoryId",
            "->assertJsonPath('items.0.name', 'Churchkhela')",
            'test_public_restaurant_and_room_reservations_endpoints_create_pending_rows',
            "route('public.sites.restaurant.reservations.store'",
            '->assertStatus(422)',
            "->assertJsonValidationErrors(['customer_name', 'phone', 'guests', 'starts_at']);",
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
            'webu-public-core-minimal.v1.openapi.yaml',
            'webu-services-booking-minimal.v1.openapi.yaml',
        ] as $needle) {
            $this->assertStringContainsString($needle, $minimalOpenApiDeliverableTest);
        }
    }
}
