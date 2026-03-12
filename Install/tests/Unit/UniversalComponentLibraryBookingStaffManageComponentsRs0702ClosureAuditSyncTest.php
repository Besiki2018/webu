<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryBookingStaffManageComponentsRs0702ClosureAuditSyncTest extends TestCase
{
    public function test_rs_07_02_closure_audit_locks_booking_staff_manage_runtime_hooks_and_customer_endpoint_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BOOKING_STAFF_MANAGE_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_07_02_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BOOKING_STAFF_MANAGE_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_07_02_2026_02_26.md');

        $webRoutesPath = base_path('routes/web.php');
        $publicBookingControllerPath = base_path('app/Http/Controllers/Booking/PublicBookingController.php');
        $bookingPublicServiceContractPath = base_path('app/Booking/Contracts/BookingPublicServiceContract.php');
        $bookingPublicServicePath = base_path('app/Booking/Services/BookingPublicService.php');
        $panelStaffControllerPath = base_path('app/Http/Controllers/Booking/PanelStaffController.php');
        $panelBookingControllerPath = base_path('app/Http/Controllers/Booking/PanelBookingController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $servicesBookingOpenApiPath = base_path('docs/openapi/webu-services-booking-minimal.v1.openapi.yaml');

        $bookingPublicExtendedEndpointsTestPath = base_path('tests/Feature/Booking/BookingPublicExtendedEndpointsTest.php');
        $bookingPanelCrudTestPath = base_path('tests/Feature/Booking/BookingPanelCrudTest.php');
        $bookingRbacPermissionsTestPath = base_path('tests/Feature/Booking/BookingRbacPermissionsTest.php');
        $bookingAcceptanceTestPath = base_path('tests/Feature/Booking/BookingAcceptanceTest.php');
        $bookingAdvancedAcceptanceTestPath = base_path('tests/Feature/Booking/BookingAdvancedAcceptanceTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryBookingStaffManageComponentsRs0702BaselineGapAuditSyncTest.php');
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
            $panelStaffControllerPath,
            $panelBookingControllerPath,
            $builderServicePath,
            $servicesBookingOpenApiPath,
            $bookingPublicExtendedEndpointsTestPath,
            $bookingPanelCrudTestPath,
            $bookingRbacPermissionsTestPath,
            $bookingAcceptanceTestPath,
            $bookingAdvancedAcceptanceTestPath,
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
        $panelStaffController = File::get($panelStaffControllerPath);
        $panelBookingController = File::get($panelBookingControllerPath);
        $builderService = File::get($builderServicePath);
        $servicesBookingOpenApi = File::get($servicesBookingOpenApiPath);
        $bookingPublicExtendedEndpointsTest = File::get($bookingPublicExtendedEndpointsTestPath);
        $bookingPanelCrudTest = File::get($bookingPanelCrudTestPath);
        $bookingRbacPermissionsTest = File::get($bookingRbacPermissionsTestPath);
        $bookingAcceptanceTest = File::get($bookingAcceptanceTestPath);
        $bookingAdvancedAcceptanceTest = File::get($bookingAdvancedAcceptanceTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);
        $servicesBookingContractsTest = File::get($servicesBookingContractsTestPath);

        foreach ([
            '## 7.4 book.staffList',
            'Content: layout, showBio, showServices',
            'Data: GET /staff',
            '## 7.5 book.bookingManage (customer)',
            'Content: cancel/reschedule toggles',
            'Data: GET /bookings/my, PUT /bookings/{id}',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-07-02` (`DONE`, `P1`)',
            'UNIVERSAL_COMPONENT_LIBRARY_BOOKING_STAFF_MANAGE_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_07_02_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_BOOKING_STAFF_MANAGE_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_07_02_2026_02_26.md',
            'UniversalComponentLibraryBookingStaffManageComponentsRs0702BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryBookingStaffManageComponentsRs0702ClosureAuditSyncTest.php',
            'BookingPublicExtendedEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            '`✅` baseline parity/gap audit is preserved and superseded by a closure audit with public/customer endpoint + standalone staff/manage widget-hook runtime evidence',
            '`✅` public/customer source-equivalent booking staff/manage endpoints are now feature-tested (`GET /public/sites/{site}/booking/staff`, `GET /public/sites/{site}/booking/bookings/my`, `GET/PUT /public/sites/{site}/booking/bookings/{booking}`) via `BookingPublicExtendedEndpointsTest.php`',
            '`✅` `BuilderService` now exposes standalone `book.staffList` / `book.bookingManage` runtime selectors/mounts and customer-manage/staff helper APIs (`listStaff`, `getBookings`, `showBooking`, `updateBooking`, `rescheduleBooking`, `cancelBooking`) with contract locks',
            '`✅` DoD closure achieved: reschedule/cancel toggle behavior verified in builder preview and backed by customer runtime API path + panel lifecycle/RBAC evidence',
            '`⚠️` source customer-auth exactness remains a broader follow-up (`RS-13-01`), though `RS-07-02` customer manage endpoints and runtime hooks are now present',
            '`🧪` RS-07-02 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Goal (`RS-07-02` Closure Pass)',
            '## ✅ What Was Done (Closure Pass)',
            'GET /public/sites/{site}/booking/staff',
            'GET /public/sites/{site}/booking/bookings/my',
            'GET /public/sites/{site}/booking/bookings/{booking}',
            'PUT /public/sites/{site}/booking/bookings/{booking}',
            'staff(...)',
            'myBookings(...)',
            'booking(...)',
            'updateBooking(...)',
            'listStaff(...)',
            'listBookings(...)',
            'showBooking(...)',
            'cancelBooking(...)',
            'rescheduleBooking(...)',
            'staff_url',
            'my_bookings_url',
            'booking_url_pattern',
            'staff_selector',
            'manage_selector',
            'mountStaffWidget',
            'mountManageWidget',
            '## Executive Result (`RS-07-02`)',
            '`RS-07-02` is now **DoD-complete** as a booking staff/customer-manage parity/runtime verification task.',
            '## Booking Staff / Manage Runtime Closure Matrix (`book.staffList`, `book.bookingManage`)',
            '## Endpoint Integration Closure Matrix (`GET /staff`, `GET /bookings/my`, `PUT /bookings/{id}`)',
            'accepted_equivalent_variant',
            '## Reschedule / Cancel Toggle Behavior Closure (DoD Slice)',
            '### Builder Preview Toggle Baseline (`book.bookingManage`) (retained)',
            '### Customer Runtime / API Execution Path (new closure evidence)',
            'action=reschedule',
            'action=cancel',
            'Verdict: customer runtime/API toggle execution path is now `pass`.',
            '## Published Runtime Hook Closure (`BuilderService`)',
            '[data-webby-booking-staff]',
            '[data-webby-booking-manage]',
            '## Feature / Runtime Evidence Added (Closure Pass)',
            'BookingPublicExtendedEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            '## DoD Closure Matrix (`RS-07-02`)',
            'reschedule/cancel toggle behavior verified (where enabled)',
            '## Remaining Exactness / Modeling Gaps (Truthful, Non-Blocking for `RS-07-02` DoD)',
            'RS-13-01',
            '## DoD Verdict (`RS-07-02`)',
            '`RS-07-02` passes and is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            "Route::get('/{site}/booking/staff', [PublicBookingController::class, 'staff'])->name('public.sites.booking.staff');",
            "Route::get('/{site}/booking/bookings/my', [PublicBookingController::class, 'myBookings'])->name('public.sites.booking.bookings.my');",
            "Route::get('/{site}/booking/bookings/{booking}', [PublicBookingController::class, 'booking'])->name('public.sites.booking.bookings.show');",
            "Route::put('/{site}/booking/bookings/{booking}', [PublicBookingController::class, 'updateBooking'])",
            "->name('public.sites.booking.bookings.update');",
            "Route::get('/booking/staff', [PanelBookingStaffController::class, 'index'])->name('panel.sites.booking.staff.index');",
            "Route::get('/booking/bookings', [PanelBookingController::class, 'index'])->name('panel.sites.booking.bookings.index');",
            "Route::post('/booking/bookings/{booking}/reschedule', [PanelBookingController::class, 'reschedule'])->name('panel.sites.booking.bookings.reschedule');",
            "Route::post('/booking/bookings/{booking}/cancel', [PanelBookingController::class, 'cancel'])->name('panel.sites.booking.bookings.cancel');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }

        foreach ([
            'public function staff(Request $request, Site $site): JsonResponse',
            'public function myBookings(Request $request, Site $site): JsonResponse',
            'public function booking(Request $request, Site $site, Booking $booking): JsonResponse',
            'public function updateBooking(Request $request, Site $site, Booking $booking): JsonResponse',
            "'action' => ['nullable', 'string', 'in:cancel,reschedule']",
            "->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicBookingController);
        }

        foreach ([
            'public function listStaff(Site $site, array $filters = [], ?User $viewer = null): array;',
            'public function listBookings(Site $site, array $filters = [], ?User $viewer = null): array;',
            'public function showBooking(Site $site, Booking $booking, ?User $viewer = null): array;',
            'public function updateBooking(Site $site, Booking $booking, array $payload = [], ?User $viewer = null): array;',
            'public function cancelBooking(Site $site, Booking $booking, array $payload = [], ?User $viewer = null): array;',
            'public function rescheduleBooking(Site $site, Booking $booking, array $payload = [], ?User $viewer = null): array;',
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingPublicServiceContract);
        }

        foreach ([
            'public function listStaff(Site $site, array $filters = [], ?User $viewer = null): array',
            'public function listBookings(Site $site, array $filters = [], ?User $viewer = null): array',
            'public function showBooking(Site $site, Booking $booking, ?User $viewer = null): array',
            'public function updateBooking(Site $site, Booking $booking, array $payload = [], ?User $viewer = null): array',
            'public function cancelBooking(Site $site, Booking $booking, array $payload = [], ?User $viewer = null): array',
            'public function rescheduleBooking(Site $site, Booking $booking, array $payload = [], ?User $viewer = null): array',
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingPublicService);
        }

        foreach ([
            'class PanelStaffController extends Controller',
            'BookingPermissions::MANAGE_STAFF',
            'class PanelBookingController extends Controller',
            'public function reschedule(Request $request, Site $site, Booking $booking): JsonResponse',
            'public function cancel(Request $request, Site $site, Booking $booking): JsonResponse',
            'BookingPermissions::RESCHEDULE',
            'BookingPermissions::CANCEL',
        ] as $needle) {
            $this->assertStringContainsString($needle, $panelStaffController.$panelBookingController);
        }

        foreach ([
            "'staff_url' =>",
            "'my_bookings_url' =>",
            "'booking_url_pattern' =>",
            "'staff_selector' => '[data-webby-booking-staff]'",
            "'manage_selector' => '[data-webby-booking-manage]'",
            'function listStaff(params) {',
            'function getBookings(params) {',
            'function showBooking(bookingId) {',
            'function updateBooking(bookingId, payload) {',
            'function rescheduleBooking(bookingId, payload) {',
            'function cancelBooking(bookingId, payload) {',
            'function mountStaffWidget(container, options) {',
            'function mountManageWidget(container, options) {',
            'listStaff: listStaff,',
            'getBookings: getBookings,',
            'showBooking: showBooking,',
            'updateBooking: updateBooking,',
            'rescheduleBooking: rescheduleBooking,',
            'cancelBooking: cancelBooking,',
            'mountStaffWidget: mountStaffWidget,',
            'mountManageWidget: mountManageWidget,',
            'data-webby-booking-staff',
            'data-webby-booking-manage',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'BuilderServicePublicVerticalRuntimeHelpersContractTest',
            "'staff_selector' => '[data-webby-booking-staff]'",
            "'manage_selector' => '[data-webby-booking-manage]'",
            'function listStaff(params) {',
            'function getBookings(params) {',
            'function showBooking(bookingId) {',
            'function updateBooking(bookingId, payload) {',
            'function rescheduleBooking(bookingId, payload) {',
            'function cancelBooking(bookingId, payload) {',
            'function mountStaffWidget(container, options) {',
            'function mountManageWidget(container, options) {',
            'showBooking: showBooking,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }

        foreach ([
            '/public/sites/{site}/booking/staff:',
            '/public/sites/{site}/booking/bookings/my:',
            '/public/sites/{site}/booking/bookings/{booking}:',
            '/panel/sites/{site}/booking/staff:',
            '/panel/sites/{site}/booking/bookings:',
            '/panel/sites/{site}/booking/bookings/{booking}:',
            '/panel/sites/{site}/booking/bookings/{booking}/reschedule:',
            '/panel/sites/{site}/booking/bookings/{booking}/cancel:',
            'summary: List public booking staff/resources',
            'summary: List authenticated customer bookings',
            'summary: Show authenticated customer booking',
            'summary: Update authenticated customer booking (cancel/reschedule)',
            'summary: List staff (panel)',
            'summary: Reschedule booking',
            'summary: Cancel booking',
        ] as $needle) {
            $this->assertStringContainsString($needle, $servicesBookingOpenApi);
        }

        foreach ([
            'BookingPublicExtendedEndpointsTest',
            'test_public_booking_extended_endpoints_expose_service_staff_calendar_and_customer_manage_flow',
            "route('public.sites.booking.staff'",
            "route('public.sites.booking.bookings.my'",
            "route('public.sites.booking.bookings.show'",
            "route('public.sites.booking.bookings.update'",
            "'action' => 'reschedule'",
            "'action' => 'cancel'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingPublicExtendedEndpointsTest);
        }

        foreach ([
            'test_owner_can_manage_booking_services_staff_and_bookings',
            "route('panel.sites.booking.staff.store'",
            "route('panel.sites.booking.bookings.index'",
            "route('panel.sites.booking.bookings.reschedule'",
            "route('panel.sites.booking.bookings.cancel'",
            "route('panel.sites.booking.bookings.show'",
            "assertJsonPath('booking.status', 'cancelled')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingPanelCrudTest);
        }

        foreach ([
            'test_receptionist_role_can_handle_booking_lifecycle_but_cannot_manage_configuration',
            'test_staff_role_is_read_only_even_with_edit_project_share_permission',
            "route('panel.sites.booking.bookings.reschedule'",
            "route('panel.sites.booking.bookings.cancel'",
            "route('panel.sites.booking.staff.store'",
            '->assertForbidden();',
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingRbacPermissionsTest);
        }

        foreach ([
            'test_booking_lifecycle_from_public_create_to_panel_timeline',
            "route('public.sites.booking.bookings.store'",
            "route('panel.sites.booking.bookings.index'",
            "route('panel.sites.booking.bookings.reschedule'",
            "route('panel.sites.booking.bookings.show'",
            "assertContains('rescheduled', \$eventTypes)",
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingAcceptanceTest);
        }

        $this->assertStringContainsString('test_advanced_ops_permissions_and_finance_acceptance_flow', $bookingAdvancedAcceptanceTest);

        foreach ([
            'test_p5_f3_01_services_staff_resources_contract_doc_and_routes_are_locked',
            'test_p5_f3_03_booking_flow_events_payments_contract_doc_and_routes_are_locked',
            "name('panel.sites.booking.staff.index')",
            "name('panel.sites.booking.bookings.reschedule')",
            'PanelStaffController',
            'PanelBookingController',
        ] as $needle) {
            $this->assertStringContainsString($needle, $servicesBookingContractsTest);
        }
    }
}
