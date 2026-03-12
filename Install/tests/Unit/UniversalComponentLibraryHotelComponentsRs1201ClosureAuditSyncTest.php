<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryHotelComponentsRs1201ClosureAuditSyncTest extends TestCase
{
    public function test_rs_12_01_closure_audit_locks_hotel_runtime_hooks_endpoints_browse_filters_validation_and_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_HOTEL_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_12_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_HOTEL_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_12_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $webRoutesPath = base_path('routes/web.php');
        $publicSiteControllerPath = base_path('app/Http/Controllers/Cms/PublicSiteController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $publicCoreOpenApiPath = base_path('docs/openapi/webu-public-core-minimal.v1.openapi.yaml');

        $cmsPublicVerticalFeatureTestPath = base_path('tests/Feature/Cms/CmsPublicVerticalModulesEndpointsTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryHotelComponentsRs1201BaselineGapAuditSyncTest.php');
        $moduleLockTestPath = base_path('tests/Unit/UniversalHotelModuleComponentsP5F4Test.php');
        $frontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsHotelBuilderCoverage.contract.test.ts');
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
            'public hotel endpoint bindings are feature-tested',
            'room browse filter verification (`q`, `capacity`)',
            'reservation validation error path (`422`)',
            'DoD closure achieved: room browse → reservation flow validated',
            'RS-12-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Goal (`RS-12-01` Closure Pass)',
            '## ✅ What Was Done (Closure Pass)',
            'GET /public/sites/{site}/rooms',
            'GET /public/sites/{site}/rooms/{id}',
            'POST /public/sites/{site}/room-reservations',
            'rooms(...)',
            'roomDetail(...)',
            'roomReservations(...)',
            'q',
            'capacity',
            'limit',
            '201 Created',
            '422 Validation Error',
            'window.WebbyHotel',
            'listRooms(...)',
            'getRoom(...)',
            'createReservation(...)',
            'mountRoomsWidget',
            'mountRoomWidget',
            'mountAvailabilityWidget',
            'mountReservationFormWidget',
            'hotelListRooms(params)',
            'data-webby-hotel-q',
            'data-webby-hotel-capacity',
            'data-webby-hotel-limit',
            '## Executive Result (`RS-12-01`)',
            '`RS-12-01` is now **DoD-complete** as a hotel parity runtime verification task.',
            '## Hotel Runtime Closure Matrix (`hotel.roomGrid`, `hotel.roomDetail`, `hotel.reservationForm`)',
            'accepted_equivalent_variant',
            '## Endpoint Integration Closure Matrix (`GET /rooms`, `GET /rooms/:id`, `POST /room-reservations`)',
            '## Room Browse Filters Closure (`hotel.roomGrid`)',
            'Public API Room Browse Filter Verification (new closure evidence)',
            'Executive Suite',
            '## Room Browse → Reservation Flow Validation Closure (`RS-12-01`)',
            'checkout_date must be after checkin_date.',
            '## Reservation Submit Validation Closure (`hotel.reservationForm`)',
            'reservation.nights',
            'reservation.total_price',
            '## Published Runtime Hook Closure (`BuilderService`)',
            '[data-webby-hotel-rooms]',
            '[data-webby-hotel-room]',
            '[data-webby-hotel-availability]',
            '[data-webby-hotel-reservation-form]',
            '## Feature / Runtime Evidence Added (Closure Pass)',
            'CmsPublicVerticalModulesEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            '## DoD Closure Matrix (`RS-12-01`)',
            'room browse → reservation flow validated (smoke + validation checks)',
            '## Remaining Exactness / Modeling Gaps (Truthful, Non-Blocking for `RS-12-01` DoD)',
            '`{{route.params.slug}}`',
            'numeric `:id`',
            '## DoD Verdict (`RS-12-01`)',
            '`RS-12-01` passes and is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
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
            'applyHotelPreviewState',
            'room_slug',
            '{{route.params.slug}}',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            "Route::get('/{site}/rooms', [PublicSiteController::class, 'rooms'])->name('public.sites.rooms.index');",
            "Route::get('/{site}/rooms/{id}', [PublicSiteController::class, 'roomDetail'])->name('public.sites.rooms.show');",
            "Route::post('/{site}/room-reservations', [PublicSiteController::class, 'roomReservations'])",
            "->name('public.sites.room-reservations.store');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }

        foreach ([
            'public function rooms(Request $request, Site $site): JsonResponse',
            'public function roomDetail(Request $request, Site $site, int $id): JsonResponse',
            'public function roomReservations(Request $request, Site $site): JsonResponse',
            "'q' => ['nullable', 'string', 'max:255']",
            "'capacity' => ['nullable', 'integer', 'min:1']",
            "'limit' => ['nullable', 'integer', 'min:1', 'max:100']",
            "'room_id' => ['required', 'integer', 'min:1']",
            "'checkin_date' => ['required', 'date']",
            "'checkout_date' => ['required', 'date']",
            'checkout_date must be after checkin_date.',
            "'nights' => \$nights,",
            "'total_price' => \$total,",
            "'currency' => (string) \$room->currency,",
            "'images' => \$images,",
            '], 201);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicSiteController);
        }

        foreach ([
            'function hotelListRooms(params) {',
            "['q', 'capacity', 'limit'].forEach(function (key) {",
            "container.getAttribute('data-webby-hotel-' + key.replace(/_/g, '-'))",
            'function mountHotelRoomsWidget(container, options) {',
            'function mountHotelRoomWidget(container) {',
            'function mountHotelAvailabilityWidget(container) {',
            'function mountHotelReservationWidget(container) {',
            'window.WebbyHotel = window.WebbyHotel || {};',
            'window.WebbyHotel.listRooms = function (params) { return hotelListRooms(params); };',
            "window.WebbyHotel.getRoom = function (id) { return cmsPublicJson('/rooms/' + encodeURIComponent(String(id || ''))); };",
            "window.WebbyHotel.createReservation = function (payload) { return cmsPublicJsonPost('/room-reservations', payload); };",
            'window.WebbyHotel.mountRoomsWidget = mountHotelRoomsWidget;',
            'window.WebbyHotel.mountRoomWidget = mountHotelRoomWidget;',
            'window.WebbyHotel.mountAvailabilityWidget = mountHotelAvailabilityWidget;',
            'window.WebbyHotel.mountReservationFormWidget = mountHotelReservationWidget;',
            'data-webby-hotel-room-id-input',
            'data-webby-hotel-checkin',
            'data-webby-hotel-checkout',
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
            'Room reservation created',
            "'422':",
            'Validation error',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicCoreOpenApi);
        }

        foreach ([
            'test_public_blog_portfolio_properties_restaurant_and_hotel_endpoints_return_site_scoped_data',
            "route('public.sites.rooms.index'",
            "route('public.sites.rooms.show'",
            "'q' => 'suite'",
            "'capacity' => 4",
            "->assertJsonPath('rooms.0.name', 'Executive Suite')",
            "->assertJsonPath('rooms.0.capacity', 5)",
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
            'test_p5_f4_04_hotel_module_and_components_contract_is_locked',
            'MODULE_HOTEL',
            'webu_hotel_room_grid_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $moduleLockTest);
        }

        foreach ([
            'CMS hotel builder component coverage contracts',
            'webu_hotel_room_detail_01',
            'webu_hotel_reservation_form_01',
            'data-webby-hotel-room',
            'data-webby-hotel-reservation-form',
            '{{route.params.slug}}',
        ] as $needle) {
            $this->assertStringContainsString($needle, $frontendContract);
        }

        foreach ([
            "key: 'hotel'",
            "key: 'hotel_reservation'",
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
