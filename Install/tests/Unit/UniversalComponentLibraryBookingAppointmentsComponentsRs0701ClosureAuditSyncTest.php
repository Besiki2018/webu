<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryBookingAppointmentsComponentsRs0701ClosureAuditSyncTest extends TestCase
{
    public function test_rs_07_01_closure_audit_locks_booking_appointments_runtime_hooks_and_endpoint_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BOOKING_APPOINTMENTS_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_07_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BOOKING_APPOINTMENTS_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_07_01_2026_02_26.md');

        $webRoutesPath = base_path('routes/web.php');
        $publicBookingControllerPath = base_path('app/Http/Controllers/Booking/PublicBookingController.php');
        $bookingPublicServiceContractPath = base_path('app/Booking/Contracts/BookingPublicServiceContract.php');
        $bookingPublicServicePath = base_path('app/Booking/Services/BookingPublicService.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $servicesBookingOpenApiPath = base_path('docs/openapi/webu-services-booking-minimal.v1.openapi.yaml');

        $bookingPublicApiTestPath = base_path('tests/Feature/Booking/BookingPublicApiTest.php');
        $bookingPublicExtendedEndpointsTestPath = base_path('tests/Feature/Booking/BookingPublicExtendedEndpointsTest.php');
        $bookingAcceptanceTestPath = base_path('tests/Feature/Booking/BookingAcceptanceTest.php');
        $bookingTeamSchedulingTestPath = base_path('tests/Feature/Booking/BookingTeamSchedulingTest.php');
        $availabilityBridgeTestPath = base_path('tests/Unit/UniversalBookingAvailabilityApiBridgeServiceTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryBookingAppointmentsComponentsRs0701BaselineGapAuditSyncTest.php');
        $servicesBookingContractsTestPath = base_path('tests/Unit/UniversalServicesBookingContractsP5F3Test.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $baselineDocPath,
            $closureDocPath,
            $webRoutesPath,
            $publicBookingControllerPath,
            $bookingPublicServiceContractPath,
            $bookingPublicServicePath,
            $builderServicePath,
            $servicesBookingOpenApiPath,
            $bookingPublicApiTestPath,
            $bookingPublicExtendedEndpointsTestPath,
            $bookingAcceptanceTestPath,
            $bookingTeamSchedulingTestPath,
            $availabilityBridgeTestPath,
            $runtimeContractTestPath,
            $baselineSyncTestPath,
            $servicesBookingContractsTestPath,
            base_path('resources/js/Pages/Project/__tests__/CmsBookingBuilderCoverage.contract.test.ts'),
            base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts'),
            base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php'),
            base_path('tests/Unit/MinimalOpenApiBaseModulesDeliverableTest.php'),
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $closureDoc = File::get($closureDocPath);
        $routes = File::get($webRoutesPath);
        $publicBookingController = File::get($publicBookingControllerPath);
        $bookingPublicServiceContract = File::get($bookingPublicServiceContractPath);
        $bookingPublicService = File::get($bookingPublicServicePath);
        $builderService = File::get($builderServicePath);
        $servicesBookingOpenApi = File::get($servicesBookingOpenApiPath);
        $bookingPublicApiTest = File::get($bookingPublicApiTestPath);
        $bookingPublicExtendedEndpointsTest = File::get($bookingPublicExtendedEndpointsTestPath);
        $bookingAcceptanceTest = File::get($bookingAcceptanceTestPath);
        $bookingTeamSchedulingTest = File::get($bookingTeamSchedulingTestPath);
        $availabilityBridgeTest = File::get($availabilityBridgeTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);
        $servicesBookingContractsTest = File::get($servicesBookingContractsTestPath);

        foreach ([
            '# 7) BOOKING / APPOINTMENTS COMPONENTS (Critical)',
            '## 7.1 book.bookingForm',
            '- GET /services',
            '- GET /staff?service_id=...',
            '- GET /availability/slots?service_id&staff_id&date',
            '- POST /bookings',
            '## 7.2 book.availableSlots',
            'Data: GET /availability/slots',
            '## 7.3 book.calendar',
            'Data: GET /availability/calendar',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-07-01` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_BOOKING_APPOINTMENTS_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_07_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_BOOKING_APPOINTMENTS_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_07_01_2026_02_26.md',
            'UniversalComponentLibraryBookingAppointmentsComponentsRs0701BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryBookingAppointmentsComponentsRs0701ClosureAuditSyncTest.php',
            'BookingPublicExtendedEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            '`✅` baseline parity/gap audit is preserved and superseded by a closure audit with public staff/calendar endpoint + standalone booking widget-hook runtime evidence',
            '`✅` public booking flow + appointments endpoint coverage now includes staff/calendar (`GET services`, `GET staff`, `GET slots`, `GET calendar`, `POST bookings`) with feature evidence (`BookingPublicApiTest.php`, `BookingPublicExtendedEndpointsTest.php`, `BookingAcceptanceTest.php`)',
            '`✅` `BuilderService` now exposes standalone booking widget selectors/mounts for `book.bookingForm` / `book.availableSlots` / `book.calendar` plus `listStaff`/`getCalendar` helpers (`window.WebbyBooking`) with contract locks',
            '`✅` booking availability/create contract drift baseline is aligned and documented (`staff_id` alias + `staff_resource_id` runtime param; OpenAPI create response `201` matches controller/runtime)',
            '`✅` DoD closure achieved (`booking submit happy/error` + `slot/calendar sync behavior` evidenced)',
            '`⚠️` source exactness gaps remain (`bookingForm` service/staff toggles + date/time picker controls, `availableSlots` day/week + slot length controls, `calendar` month/week + disable past + highlight availability controls)',
            '`🧪` RS-07-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Goal (`RS-07-01` Closure Pass)',
            '## ✅ What Was Done (Closure Pass)',
            'GET /public/sites/{site}/booking/staff',
            'GET /public/sites/{site}/booking/calendar',
            'staff_url',
            'calendar_url',
            'form_selector',
            'slots_selector',
            'calendar_selector',
            'mountBookingFormWidget',
            'mountSlotsWidget',
            'mountCalendarWidget',
            'listStaff(...)',
            'getCalendar(...)',
            '## Executive Result (`RS-07-01`)',
            '`RS-07-01` is now **DoD-complete** as a booking/appointments parity/runtime verification task.',
            '## Booking / Appointments Runtime Closure Matrix (`book.bookingForm`, `book.availableSlots`, `book.calendar`)',
            'accepted_equivalent_variant',
            '## Endpoint Integration Closure Matrix (`GET /services`, `GET /staff`, `GET /availability/*`, `POST /bookings`)',
            '## Contract Drift Alignment Closure (`slots` / `create booking`)',
            'staff_resource_id',
            '201 Created',
            '## Calendar / Slot Availability UX Closure (`RS-07-01` DoD Line)',
            'collision error path (`422`, `slot_collision`)',
            'Standalone runtime widget UX hooks now published',
            '[data-webby-booking-form]',
            '[data-webby-booking-slots]',
            '[data-webby-booking-calendar]',
            '## Feature / Runtime Evidence Added (Closure Pass)',
            'BookingPublicExtendedEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            '## DoD Closure Matrix (`RS-07-01`)',
            'booking submit happy/error paths tested',
            'slot/calendar sync behavior evidenced',
            '## Remaining Exactness / Modeling Gaps (Truthful, Non-Blocking for `RS-07-01` DoD)',
            'source example includes `service_id` filter semantics',
            '## DoD Verdict (`RS-07-01`)',
            '`RS-07-01` passes and is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            "Route::get('/{site}/booking/services', [PublicBookingController::class, 'services'])->name('public.sites.booking.services');",
            "Route::get('/{site}/booking/staff', [PublicBookingController::class, 'staff'])->name('public.sites.booking.staff');",
            "Route::get('/{site}/booking/slots', [PublicBookingController::class, 'slots'])->name('public.sites.booking.slots');",
            "Route::get('/{site}/booking/calendar', [PublicBookingController::class, 'calendar'])->name('public.sites.booking.calendar');",
            "Route::post('/{site}/booking/bookings', [PublicBookingController::class, 'createBooking'])",
            "->name('public.sites.booking.bookings.store');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }

        foreach ([
            'public function services(Request $request, Site $site): JsonResponse',
            'public function staff(Request $request, Site $site): JsonResponse',
            'public function slots(Request $request, Site $site): JsonResponse',
            "'staff_id' => ['nullable', 'integer', 'min:1']",
            "'staff_resource_id' => ['nullable', 'integer', 'min:1']",
            'public function calendar(Request $request, Site $site): JsonResponse',
            'public function createBooking(Request $request, Site $site): JsonResponse',
            'return $this->corsJson($payload, 201);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicBookingController);
        }

        foreach ([
            'public function listServices(Site $site, array $filters = [], ?User $viewer = null): array;',
            'public function listStaff(Site $site, array $filters = [], ?User $viewer = null): array;',
            'public function slots(Site $site, array $filters = [], ?User $viewer = null): array;',
            'public function calendar(Site $site, array $filters = [], ?User $viewer = null): array;',
            'public function createBooking(Site $site, array $payload = [], ?User $viewer = null): array;',
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingPublicServiceContract);
        }

        foreach ([
            'public function listStaff(Site $site, array $filters = [], ?User $viewer = null): array',
            'public function calendar(Site $site, array $filters = [], ?User $viewer = null): array',
            'public function slots(Site $site, array $filters = [], ?User $viewer = null): array',
            'public function createBooking(Site $site, array $payload = [], ?User $viewer = null): array',
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingPublicService);
        }

        foreach ([
            '/public/sites/{site}/booking/services:',
            '/public/sites/{site}/booking/staff:',
            '/public/sites/{site}/booking/slots:',
            '/public/sites/{site}/booking/calendar:',
            '/public/sites/{site}/booking/bookings:',
            'summary: List public booking services',
            'summary: List public booking staff/resources',
            'summary: Query available booking slots',
            'summary: Public booking availability calendar/events snapshot',
            '- name: staff_id',
            '- name: staff_resource_id',
            "'201':",
            "'422':",
        ] as $needle) {
            $this->assertStringContainsString($needle, $servicesBookingOpenApi);
        }

        foreach ([
            'BookingPublicApiTest',
            "route('public.sites.booking.services'",
            "route('public.sites.booking.slots'",
            "'staff_resource_id' => \$staff->id",
            "route('public.sites.booking.bookings.store'",
            '->assertCreated()',
            '->assertStatus(422)',
            "->assertJsonPath('reason', 'slot_collision')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingPublicApiTest);
        }

        foreach ([
            'BookingPublicExtendedEndpointsTest',
            'test_public_booking_extended_endpoints_expose_service_staff_calendar_and_customer_manage_flow',
            "route('public.sites.booking.staff'",
            "route('public.sites.booking.calendar'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingPublicExtendedEndpointsTest);
        }

        foreach ([
            'test_booking_lifecycle_from_public_create_to_panel_timeline',
            "route('public.sites.booking.bookings.store'",
            "route('panel.sites.booking.calendar'",
            '->assertJsonPath(\'events.0.id\', $bookingId)',
            'test_booking_collision_failure_path_blocks_second_overlapping_create',
            "->assertJsonPath('scope', 'staff_resource')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingAcceptanceTest);
        }

        foreach ([
            'test_owner_can_manage_work_hours_and_time_off_and_calendar_reflects_it',
            "route('panel.sites.booking.staff.work-schedules.sync'",
            "route('panel.sites.booking.calendar'",
            '->assertJsonPath(\'time_off_blocks.0.id\', $timeOffId)',
            '->assertJsonPath(\'staff_schedule_blocks.0.staff_resource.id\', $staff->id)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingTeamSchedulingTest);
        }

        foreach ([
            'test_it_builds_read_only_universal_snapshot_for_availability_blocked_times_slots_and_collision_contract',
            'samples.public_slots',
            'samples.panel_calendar',
            'public.sites.booking.slots',
            'panel.sites.booking.calendar',
            'slot_collision',
        ] as $needle) {
            $this->assertStringContainsString($needle, $availabilityBridgeTest);
        }

        foreach ([
            'BuilderServicePublicVerticalRuntimeHelpersContractTest',
            "'staff_url' =>",
            "'calendar_url' =>",
            "'form_selector' => '[data-webby-booking-form]'",
            "'slots_selector' => '[data-webby-booking-slots]'",
            "'calendar_selector' => '[data-webby-booking-calendar]'",
            'function listStaff(params) {',
            'function getCalendar(params) {',
            'function mountSlotsWidget(container, options) {',
            'function mountCalendarWidget(container, options) {',
            'mountBookingFormWidget: mountWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }

        foreach ([
            '\'staff_url\' => $publicPrefix ? "{$publicPrefix}/staff" : null',
            '\'calendar_url\' => $publicPrefix ? "{$publicPrefix}/calendar" : null',
            '\'form_selector\' => \'[data-webby-booking-form]\'',
            '\'slots_selector\' => \'[data-webby-booking-slots]\'',
            '\'calendar_selector\' => \'[data-webby-booking-calendar]\'',
            'function listStaff(params) {',
            'function getCalendar(params) {',
            'function mountSlotsWidget(container, options) {',
            'function mountCalendarWidget(container, options) {',
            'mountBookingFormWidget: mountWidget,',
            'mountSlotsWidget: mountSlotsWidget,',
            'mountCalendarWidget: mountCalendarWidget,',
            'listStaff: listStaff,',
            'getCalendar: getCalendar,',
            'data-webby-booking-form',
            'data-webby-booking-slots',
            'data-webby-booking-calendar',
            'staff_resource_id: staffResourceId || undefined,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'test_p5_f3_02_availability_slot_apis_contract_doc_and_routes_are_locked',
            "name('public.sites.booking.slots')",
            'site.entitlement:booking_team_scheduling',
            'BookingPublicApiTest',
            'test_p5_f3_04_booking_builder_components_contract_doc_and_cms_builder_hooks_are_locked',
            'webu_book_booking_form_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $servicesBookingContractsTest);
        }
    }
}
