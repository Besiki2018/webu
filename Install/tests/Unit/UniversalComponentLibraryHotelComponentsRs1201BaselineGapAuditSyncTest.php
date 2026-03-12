<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryHotelComponentsRs1201BaselineGapAuditSyncTest extends TestCase
{
    public function test_rs_12_01_progress_audit_doc_locks_hotel_components_baseline_gap_truth_and_closure_supersession(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_HOTEL_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_12_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_HOTEL_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_12_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $aliasMapPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $webRoutesPath = base_path('routes/web.php');
        $publicSiteControllerPath = base_path('app/Http/Controllers/Cms/PublicSiteController.php');
        $publicBookingControllerPath = base_path('app/Http/Controllers/Booking/PublicBookingController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $publicCoreOpenApiPath = base_path('docs/openapi/webu-public-core-minimal.v1.openapi.yaml');

        $moduleLockTestPath = base_path('tests/Unit/UniversalHotelModuleComponentsP5F4Test.php');
        $frontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsHotelBuilderCoverage.contract.test.ts');
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
        $closureSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryHotelComponentsRs1201ClosureAuditSyncTest.php');
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
            '# 12) HOTEL COMPONENTS',
            '## 12.1 hotel.roomGrid',
            'Data: GET /rooms',
            '## 12.2 hotel.roomDetail',
            'Data: GET /rooms/:id',
            '## 12.3 hotel.reservationForm',
            'Data: POST /room-reservations',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-12-01` (`DONE`, `P1`)',
            'UNIVERSAL_COMPONENT_LIBRARY_HOTEL_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_12_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_HOTEL_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_12_01_2026_02_26.md',
            'UniversalComponentLibraryHotelComponentsRs1201BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryHotelComponentsRs1201ClosureAuditSyncTest.php',
            'CmsPublicVerticalModulesEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            'baseline parity/gap audit is preserved and superseded by a closure audit',
            'public hotel endpoint bindings are feature-tested',
            'room browse filter verification (`q`, `capacity`)',
            'reservation validation error path (`422`)',
            '`BuilderService` now exposes standalone `hotel.roomGrid` / `hotel.roomDetail` / `hotel.reservationForm` runtime selectors/mounts and `window.WebbyHotel` helper APIs',
            'minimal public OpenAPI hotel route coverage is present',
            'DoD closure achieved: room browse → reservation flow validated',
            'builder preview schema still documents `room_slug`/`{{route.params.slug}}` hint while runtime/public detail contract is numeric `:id`',
            'RS-12-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            '## Executive Result (`RS-12-01`)',
            '## Hotel Parity Matrix',
            '## Endpoint Contract Verification (`GET /rooms`, `GET /rooms/:id`, `POST /room-reservations`)',
            '`partial_generic_public_only`',
            '`variant_generic_booking_only`',
            '## Room Browse → Reservation Flow Verification',
            'ID-vs-slug contract drift',
            '`window.WebbyBooking` helper methods (`listServices`, `getSlots`, `createBooking`)',
            '## Runtime Widget / Binding Status (`roomGrid`, `roomDetail`, `reservationForm`)',
            'no `window.WebbyHotel` helper',
            'Conclusion: `RS-12-01` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD + Parity Closure)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'webu_hotel_room_grid_01',
            'webu_hotel_room_detail_01',
            'webu_hotel_room_availability_01',
            'webu_hotel_reservation_form_01',
            'data-webby-hotel-rooms',
            'data-webby-hotel-room',
            'data-webby-hotel-availability',
            'data-webby-hotel-reservation-form',
            'BUILDER_HOTEL_DISCOVERY_LIBRARY_SECTIONS',
            'syntheticHotelSectionKeySet',
            'syntheticHotelReservationSectionKeySet',
            'createSyntheticHotelPlaceholder',
            'applyHotelPreviewState',
            'rooms_count',
            'show_capacity',
            'show_badges',
            'show_amenities',
            'room_slug',
            'show_phone',
            'show_guest_count',
            'show_special_requests',
            'submit_label',
            "if (normalizedSectionType === 'webu_hotel_room_grid_01')",
            "if (normalizedSectionType === 'webu_hotel_room_detail_01')",
            "if (normalizedSectionType === 'webu_hotel_room_availability_01')",
            "if (normalizedSectionType === 'webu_hotel_reservation_form_01')",
            "key: 'hotel'",
            "key: 'hotel_reservation'",
            'requiredModules: [MODULE_HOTEL]',
            'requiredModules: [MODULE_HOTEL, MODULE_BOOKING]',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'source_component_key": "hotel.roomGrid"',
            'webu_hotel_room_grid_01',
            'source_component_key": "hotel.roomDetail"',
            'webu_hotel_room_detail_01',
            'source_component_key": "hotel.reservationForm"',
            'webu_hotel_reservation_form_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            "Route::get('/{site}/booking/services', [PublicBookingController::class, 'services'])",
            "Route::get('/{site}/booking/slots', [PublicBookingController::class, 'slots'])",
            "Route::post('/{site}/booking/bookings', [PublicBookingController::class, 'createBooking'])",
            "Route::get('/{site}/rooms', [PublicSiteController::class, 'rooms'])->name('public.sites.rooms.index');",
            "Route::get('/{site}/rooms/{id}', [PublicSiteController::class, 'roomDetail'])->name('public.sites.rooms.show');",
            "Route::post('/{site}/room-reservations', [PublicSiteController::class, 'roomReservations'])",
            "->name('public.sites.room-reservations.store');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }

        foreach ([
            'public function rooms(Request $request, Site $site): JsonResponse',
            'public function roomDetail(Request $request, Site $site, int $id): JsonResponse',
            'public function roomReservations(Request $request, Site $site): JsonResponse',
            'Public hotel rooms list endpoint.',
            'Public hotel room detail endpoint by numeric id.',
            'Public hotel room reservation submit endpoint.',
            "'q' => ['nullable', 'string', 'max:255']",
            "'capacity' => ['nullable', 'integer', 'min:1']",
            "'limit' => ['nullable', 'integer', 'min:1', 'max:100']",
            "'room_id' => ['required', 'integer', 'min:1']",
            "'checkin_date' => ['required', 'date']",
            "'checkout_date' => ['required', 'date']",
            "'guest_name' => ['nullable', 'string', 'max:255']",
            "'guest_phone' => ['nullable', 'string', 'max:64']",
            "'guest_email' => ['nullable', 'email', 'max:255']",
            'checkout_date must be after checkin_date.',
            "'nights' => \$nights,",
            "'total_price' => \$total,",
            "'currency' => (string) \$room->currency,",
            "'images' => \$images,",
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
            'window.WebbyHotel = window.WebbyHotel || {};',
            'function hotelListRooms(params) {',
            "['q', 'capacity', 'limit'].forEach(function (key) {",
            "container.getAttribute('data-webby-hotel-' + key.replace(/_/g, '-'))",
            'function mountHotelRoomsWidget(container, options) {',
            'function mountHotelRoomWidget(container) {',
            'function mountHotelAvailabilityWidget(container) {',
            'function mountHotelReservationWidget(container) {',
            'window.WebbyHotel.listRooms = function (params) { return hotelListRooms(params); };',
            "window.WebbyHotel.getRoom = function (id) { return cmsPublicJson('/rooms/' + encodeURIComponent(String(id || ''))); };",
            "window.WebbyHotel.createReservation = function (payload) { return cmsPublicJsonPost('/room-reservations', payload); };",
            'window.WebbyHotel.mountRoomsWidget = mountHotelRoomsWidget;',
            'window.WebbyHotel.mountRoomWidget = mountHotelRoomWidget;',
            'window.WebbyHotel.mountAvailabilityWidget = mountHotelAvailabilityWidget;',
            'window.WebbyHotel.mountReservationFormWidget = mountHotelReservationWidget;',
            '[data-webby-hotel-rooms]',
            '[data-webby-hotel-room]',
            '[data-webby-hotel-availability]',
            '[data-webby-hotel-reservation-form]',
            'data-webby-hotel-submit',
            'data-webby-hotel-message',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            '/public/sites/{site}/rooms:',
            'summary: Public hotel rooms list',
            '/public/sites/{site}/rooms/{id}:',
            'summary: Public hotel room detail by id',
            '/public/sites/{site}/room-reservations:',
            'summary: Public hotel room reservation submit',
            "'201':",
            'description: Room reservation created',
            "'422':",
            'description: Validation error',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicCoreOpenApi);
        }

        foreach ([
            'class UniversalHotelModuleComponentsP5F4Test extends TestCase',
            'test_p5_f4_04_hotel_module_and_components_contract_is_locked',
            'MODULE_HOTEL',
            'MODULE_BOOKING',
        ] as $needle) {
            $this->assertStringContainsString($needle, $moduleLockTest);
        }

        foreach ([
            'CMS hotel builder component coverage contracts',
            'webu_hotel_room_grid_01',
            'webu_hotel_room_detail_01',
            'webu_hotel_reservation_form_01',
            'data-webby-hotel-rooms',
            'data-webby-hotel-reservation-form',
            'createSyntheticHotelPlaceholder',
            'applyHotelPreviewState',
            '{{route.params.slug}}',
        ] as $needle) {
            $this->assertStringContainsString($needle, $frontendContract);
        }

        foreach ([
            'test_hotel_module_is_exposed_for_hotel_project_type_and_blocked_for_ecommerce_override',
            'MODULE_HOTEL',
        ] as $needle) {
            $this->assertStringContainsString($needle, $moduleRegistryFeatureTest);
        }

        foreach ([
            'test_hotel_project_type_allows_hotel_module_and_ecommerce_type_denies_it_when_framework_enabled',
            "makeSiteWithTemplateCategory('hotel')",
            'MODULE_HOTEL',
        ] as $needle) {
            $this->assertStringContainsString($needle, $projectTypeFlagsUnitTest);
        }

        foreach ([
            'rooms',
            'room_images',
            'room_reservations',
            'deluxe',
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
            "key: 'hotel'",
            "key: 'hotel_reservation'",
            "key: 'restaurant'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationUnitTest);
        }

        foreach ([
            "rowsByKey['hotel.reservationForm']",
            'CmsHotelBuilderCoverage.contract.test.ts',
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
            "route('public.sites.rooms.index'",
            "route('public.sites.rooms.show'",
            "'q' => 'suite'",
            "'capacity' => 4",
            "->assertJsonPath('rooms.0.name', 'Executive Suite')",
            'test_public_restaurant_and_room_reservations_endpoints_create_pending_rows',
            "route('public.sites.room-reservations.store'",
            "->assertJsonPath('reservation.nights', 3)",
            "->assertJsonPath('reservation.total_price', '660.00')",
            "->assertJsonPath('error', 'checkout_date must be after checkin_date.')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsPublicVerticalFeatureTest);
        }

        foreach ([
            'BuilderServicePublicVerticalRuntimeHelpersContractTest',
            'function hotelListRooms(params) {',
            'function mountHotelRoomsWidget(container, options) {',
            'window.WebbyHotel.listRooms = function (params) { return hotelListRooms(params); };',
            'window.WebbyHotel.mountReservationFormWidget = mountHotelReservationWidget;',
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
