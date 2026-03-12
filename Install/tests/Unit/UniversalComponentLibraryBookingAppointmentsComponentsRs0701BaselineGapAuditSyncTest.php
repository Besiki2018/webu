<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryBookingAppointmentsComponentsRs0701BaselineGapAuditSyncTest extends TestCase
{
    public function test_rs_07_01_progress_audit_doc_locks_booking_appointments_components_parity_endpoint_and_runtime_gap_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BOOKING_APPOINTMENTS_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_07_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BOOKING_APPOINTMENTS_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_07_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $aliasMapPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $webRoutesPath = base_path('routes/web.php');
        $publicBookingControllerPath = base_path('app/Http/Controllers/Booking/PublicBookingController.php');
        $bookingPublicServiceContractPath = base_path('app/Booking/Contracts/BookingPublicServiceContract.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $servicesBookingOpenApiPath = base_path('docs/openapi/webu-services-booking-minimal.v1.openapi.yaml');

        $bookingPublicApiTestPath = base_path('tests/Feature/Booking/BookingPublicApiTest.php');
        $bookingPublicExtendedEndpointsTestPath = base_path('tests/Feature/Booking/BookingPublicExtendedEndpointsTest.php');
        $bookingAcceptanceTestPath = base_path('tests/Feature/Booking/BookingAcceptanceTest.php');
        $bookingTeamSchedulingTestPath = base_path('tests/Feature/Booking/BookingTeamSchedulingTest.php');
        $availabilityBridgeTestPath = base_path('tests/Unit/UniversalBookingAvailabilityApiBridgeServiceTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $servicesBookingContractsTestPath = base_path('tests/Unit/UniversalServicesBookingContractsP5F3Test.php');
        $bookingCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsBookingBuilderCoverage.contract.test.ts');
        $activationFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts');
        $activationUnitTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');
        $minimalOpenApiDeliverableTestPath = base_path('tests/Unit/MinimalOpenApiBaseModulesDeliverableTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $closureDocPath,
            $cmsPath,
            $aliasMapPath,
            $webRoutesPath,
            $publicBookingControllerPath,
            $bookingPublicServiceContractPath,
            $builderServicePath,
            $servicesBookingOpenApiPath,
            $bookingPublicApiTestPath,
            $bookingPublicExtendedEndpointsTestPath,
            $bookingAcceptanceTestPath,
            $bookingTeamSchedulingTestPath,
            $availabilityBridgeTestPath,
            $runtimeContractTestPath,
            $servicesBookingContractsTestPath,
            $bookingCoverageContractPath,
            $activationFrontendContractPath,
            $activationUnitTestPath,
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
        $publicBookingController = File::get($publicBookingControllerPath);
        $bookingPublicServiceContract = File::get($bookingPublicServiceContractPath);
        $builderService = File::get($builderServicePath);
        $servicesBookingOpenApi = File::get($servicesBookingOpenApiPath);
        $bookingPublicApiTest = File::get($bookingPublicApiTestPath);
        $bookingPublicExtendedEndpointsTest = File::get($bookingPublicExtendedEndpointsTestPath);
        $bookingAcceptanceTest = File::get($bookingAcceptanceTestPath);
        $bookingTeamSchedulingTest = File::get($bookingTeamSchedulingTestPath);
        $availabilityBridgeTest = File::get($availabilityBridgeTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);
        $servicesBookingContractsTest = File::get($servicesBookingContractsTestPath);
        $bookingCoverageContract = File::get($bookingCoverageContractPath);
        $activationFrontendContract = File::get($activationFrontendContractPath);
        $activationUnitTest = File::get($activationUnitTestPath);
        $minimalOpenApiDeliverableTest = File::get($minimalOpenApiDeliverableTestPath);

        foreach ([
            '# 7) BOOKING / APPOINTMENTS COMPONENTS (Critical)',
            '## 7.1 book.bookingForm',
            'Content:',
            '- service select on/off',
            '- staff select on/off',
            '- date/time picker',
            '- customer fields (name/phone/email)',
            '- notes',
            'Data:',
            '- GET /services',
            '- GET /staff?service_id=...',
            '- GET /availability/slots?service_id&staff_id&date',
            '- POST /bookings',
            '## 7.2 book.availableSlots',
            'Content: day/week view, slot length',
            'Data: GET /availability/slots',
            '## 7.3 book.calendar',
            'Content: month/week, disable past, highlight availability',
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
            'BookingPublicApiTest.php',
            'BookingPublicExtendedEndpointsTest.php',
            'BookingAcceptanceTest.php',
            'BookingTeamSchedulingTest.php',
            'UniversalBookingAvailabilityApiBridgeServiceTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            'UniversalServicesBookingContractsP5F3Test.php',
            'CmsBookingBuilderCoverage.contract.test.ts',
            'UniversalComponentLibraryActivationP5F5Test.php',
            'MinimalOpenApiBaseModulesDeliverableTest.php',
            '`✅` booking/appointments parity matrix documented for `book.bookingForm`, `book.availableSlots`, `book.calendar` with canonical alias mappings (`webu_book_booking_form_01`, `webu_book_slots_01`, `webu_book_calendar_01`)',
            '`✅` baseline parity/gap audit is preserved and superseded by a closure audit with public staff/calendar endpoint + standalone booking widget-hook runtime evidence',
            '`✅` public booking flow + appointments endpoint coverage now includes staff/calendar (`GET services`, `GET staff`, `GET slots`, `GET calendar`, `POST bookings`) with feature evidence (`BookingPublicApiTest.php`, `BookingPublicExtendedEndpointsTest.php`, `BookingAcceptanceTest.php`)',
            '`✅` slot/calendar sync backend baseline evidenced via panel calendar + scheduling/time-off tests (`BookingTeamSchedulingTest.php`) and availability bridge snapshot (`UniversalBookingAvailabilityApiBridgeServiceTest.php`)',
            '`✅` `BuilderService` now exposes standalone booking widget selectors/mounts for `book.bookingForm` / `book.availableSlots` / `book.calendar` plus `listStaff`/`getCalendar` helpers (`window.WebbyBooking`) with contract locks',
            '`✅` booking availability/create contract drift baseline is aligned and documented (`staff_id` alias + `staff_resource_id` runtime param; OpenAPI create response `201` matches controller/runtime)',
            '`✅` DoD closure achieved (`booking submit happy/error` + `slot/calendar sync behavior` evidenced)',
            '`⚠️` source exactness gaps remain (`bookingForm` service/staff toggles + date/time picker controls, `availableSlots` day/week + slot length controls, `calendar` month/week + disable past + highlight availability controls)',
            '`🧪` RS-07-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '## Scope',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            '## Audit Inputs Reviewed',
            '## What Was Done (This Pass)',
            '## Executive Result (`RS-07-01`)',
            '## Booking / Appointments Parity Matrix',
            '### Matrix (`content/style/panel-preview/runtime-data/endpoint/slot-calendar-ux/gating/tests`)',
            '`book.bookingForm`',
            '`book.availableSlots`',
            '`book.calendar`',
            '`webu_book_booking_form_01`',
            '`webu_book_slots_01`',
            '`webu_book_calendar_01`',
            '## Endpoint Contract Verification (`GET /services`, `GET /staff`, `GET /availability/*`, `POST /bookings`)',
            '### Source-to-Current Endpoint Matrix',
            '`exact_semantics_path_variant`',
            '`partial_equivalent`',
            '`equivalent_panel_only`',
            '`gap`',
            'source public `GET /staff?service_id=...` endpoint does not exist',
            'source public `GET /availability/calendar` endpoint does not exist (only panel calendar endpoint exists)',
            '## Calendar / Slot Availability UX Verification',
            '### Builder Preview UX Baseline',
            '### Generic Runtime Helper UX Baseline (`BuilderService`)',
            '### Backend Slot / Calendar Sync Evidence',
            'booking widget `createPayload` does not include `staff_resource_id`',
            '## Builder Preview Parity and Source-Control Exactness Findings',
            'no dedicated preview update branches were found for `webu_book_booking_form_01` / `webu_book_calendar_01`',
            '## Service/Booking Gating Baseline (Source Critical Vertical)',
            'requiredModules: [MODULE_BOOKING]',
            '## Runtime Widget / Binding Status (`bookingForm`, `availableSlots`, `calendar`)',
            'generic `[data-webby-booking-widget]` selector',
            'no `staff_url` or `calendar_url` booking runtime config fields',
            'no standalone selectors/hook config:',
            '## DoD Verdict (`RS-07-01`)',
            'Conclusion: `RS-07-01` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD + Parity Closure)',
            '## Conclusion',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'webu_book_booking_form_01',
            'webu_book_slots_01',
            'webu_book_calendar_01',
            'data-webby-booking-form',
            'data-webby-booking-slots',
            'data-webby-booking-calendar',
            'show_phone',
            'show_notes',
            'submit_label',
            'month_label',
            'show_staff_filter',
            'slots_count',
            'selected_date',
            "if (normalized === 'webu_book_slots_01')",
            "if (normalized === 'webu_book_booking_form_01')",
            "if (normalized === 'webu_book_calendar_01')",
            "if (normalizedSectionType === 'webu_book_slots_01')",
            'applyBookingPreviewState',
            'builderSectionAvailabilityMatrix',
            'requiredModules: [MODULE_BOOKING]',
            "setAttribute('data-webu-role', 'booking-slot-chip')",
            "setAttribute('data-webu-role', 'booking-calendar-grid')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            "if (normalizedSectionType === 'webu_book_booking_form_01')",
            "if (normalizedSectionType === 'webu_book_calendar_01')",
            'show_service_select',
            'show_staff_select',
            'disable_past',
            'highlight_availability',
            'slot_length',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $cms);
        }

        foreach ([
            'source_component_key": "book.bookingForm"',
            'webu_book_booking_form_01',
            'source_component_key": "book.availableSlots"',
            'webu_book_slots_01',
            'source_component_key": "book.calendar"',
            'webu_book_calendar_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            "Route::get('/{site}/booking/services'",
            "Route::get('/{site}/booking/staff'",
            "Route::get('/{site}/booking/slots'",
            "Route::get('/{site}/booking/calendar'",
            "Route::post('/{site}/booking/bookings'",
            "name('public.sites.booking.staff')",
            "name('public.sites.booking.slots')",
            "name('public.sites.booking.calendar')",
            "name('public.sites.booking.bookings.store')",
            "Route::get('/booking/staff'",
            "name('panel.sites.booking.staff.index')",
            "Route::get('/booking/calendar'",
            "name('panel.sites.booking.calendar')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }

        foreach ([
            'public function services(Request $request, Site $site): JsonResponse',
            'public function staff(Request $request, Site $site): JsonResponse',
            "'search' => \$request->query('search')",
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
            'availabilityCalendar',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $bookingPublicServiceContract);
        }

        foreach ([
            '/public/sites/{site}/booking/services:',
            '/public/sites/{site}/booking/staff:',
            'summary: List public booking services',
            'summary: List public booking staff/resources',
            '/public/sites/{site}/booking/slots:',
            'summary: Query available booking slots',
            '- name: staff_id',
            '- name: staff_resource_id',
            '/public/sites/{site}/booking/calendar:',
            'summary: Public booking availability calendar/events snapshot',
            '/public/sites/{site}/booking/bookings:',
            'description: Booking created',
            "'201':",
            "'422':",
            '/panel/sites/{site}/booking/staff:',
            '/panel/sites/{site}/booking/calendar:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $servicesBookingOpenApi);
        }

        foreach ([
            "route('public.sites.booking.services'",
            "route('public.sites.booking.slots'",
            '\'staff_resource_id\' => $staff->id',
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
            'test_p5_f3_02_availability_slot_apis_contract_doc_and_routes_are_locked',
            "name('public.sites.booking.slots')",
            'site.entitlement:booking_team_scheduling',
            'BookingPublicApiTest',
            'test_p5_f3_04_booking_builder_components_contract_doc_and_cms_builder_hooks_are_locked',
            'webu_book_booking_form_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $servicesBookingContractsTest);
        }

        foreach ([
            'webu_book_booking_form_01',
            'webu_book_slots_01',
            'webu_book_calendar_01',
            'data-webby-booking-form',
            'data-webby-booking-slots',
            'data-webby-booking-calendar',
            'createSyntheticBookingPlaceholder',
            'applyBookingPreviewState',
            'BOOKING_SECTION_CATEGORY',
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingCoverageContract);
        }

        foreach ([
            'builderSectionAvailabilityMatrix',
            'requiredModules: [MODULE_BOOKING]',
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationFrontendContract);
        }
        foreach ([
            'builderSectionAvailabilityMatrix',
            "key: 'booking'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationUnitTest);
        }

        foreach ([
            'webu-services-booking-minimal.v1.openapi.yaml',
            '/public/sites/{site}/booking/services:',
            '/public/sites/{site}/booking/slots:',
            '/panel/sites/{site}/booking/services:',
            '/panel/sites/{site}/booking/staff:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $minimalOpenApiDeliverableTest);
        }

        foreach ([
            '\'services_url\' => $publicPrefix ? "{$publicPrefix}/services" : null',
            '\'staff_url\' => $publicPrefix ? "{$publicPrefix}/staff" : null',
            '\'slots_url\' => $publicPrefix ? "{$publicPrefix}/slots" : null',
            '\'calendar_url\' => $publicPrefix ? "{$publicPrefix}/calendar" : null',
            '\'create_booking_url\' => $publicPrefix ? "{$publicPrefix}/bookings" : null',
            '\'booking_selector\' => \'[data-webby-booking-widget]\'',
            '\'form_selector\' => \'[data-webby-booking-form]\'',
            '\'slots_selector\' => \'[data-webby-booking-slots]\'',
            '\'calendar_selector\' => \'[data-webby-booking-calendar]\'',
            'function listServices(params) {',
            'function listStaff(params) {',
            'function getSlots(params) {',
            'function getCalendar(params) {',
            'function createBooking(payload) {',
            'function mountWidget(container, options) {',
            'function mountSlotsWidget(container, options) {',
            'function mountCalendarWidget(container, options) {',
            'var createPayload = {',
            'service_id: serviceId,',
            'staff_resource_id: staffResourceId || undefined,',
            'data-webby-booking-form',
            'data-webby-booking-slots',
            'data-webby-booking-calendar',
            'window.WebbyBooking = {',
            'listServices: listServices,',
            'listStaff: listStaff,',
            'getSlots: getSlots,',
            'getCalendar: getCalendar,',
            'createBooking: createBooking,',
            'mountWidget: mountWidget,',
            'mountBookingFormWidget: mountWidget,',
            'mountSlotsWidget: mountSlotsWidget,',
            'mountCalendarWidget: mountCalendarWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
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
    }
}
