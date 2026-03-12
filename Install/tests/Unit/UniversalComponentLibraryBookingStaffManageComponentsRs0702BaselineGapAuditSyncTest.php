<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryBookingStaffManageComponentsRs0702BaselineGapAuditSyncTest extends TestCase
{
    public function test_rs_07_02_progress_audit_doc_locks_booking_staff_and_manage_components_parity_endpoint_and_runtime_gap_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BOOKING_STAFF_MANAGE_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_07_02_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BOOKING_STAFF_MANAGE_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_07_02_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $aliasMapPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
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
            $servicesBookingContractsTestPath,
            $bookingCoverageContractPath,
            $activationFrontendContractPath,
            $activationUnitTestPath,
            $minimalOpenApiDeliverableTestPath,
            base_path('tests/Unit/UniversalComponentLibraryBookingStaffManageComponentsRs0702ClosureAuditSyncTest.php'),
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
        $bookingCoverageContract = File::get($bookingCoverageContractPath);
        $activationFrontendContract = File::get($activationFrontendContractPath);
        $activationUnitTest = File::get($activationUnitTestPath);
        $minimalOpenApiDeliverableTest = File::get($minimalOpenApiDeliverableTestPath);

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
            'BookingPanelCrudTest.php',
            'BookingRbacPermissionsTest.php',
            'BookingAcceptanceTest.php',
            'BookingAdvancedAcceptanceTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            'PanelStaffController.php',
            'PanelBookingController.php',
            '`✅` booking staff/manage parity matrix documented for `book.staffList` and `book.bookingManage` with canonical alias mappings (`webu_svc_staff_grid_01`, `webu_book_booking_manage_01`)',
            '`✅` builder preview placeholders + component-specific preview-update branches evidenced for both components; `bookingManage` reschedule/cancel toggle behavior verified in preview branch (`show_cancel_action`, `show_reschedule_action`)',
            '`✅` baseline parity/gap audit is preserved and superseded by a closure audit with public/customer endpoint + standalone staff/manage widget-hook runtime evidence',
            '`✅` public/customer source-equivalent booking staff/manage endpoints are now feature-tested (`GET /public/sites/{site}/booking/staff`, `GET /public/sites/{site}/booking/bookings/my`, `GET/PUT /public/sites/{site}/booking/bookings/{booking}`) via `BookingPublicExtendedEndpointsTest.php`',
            '`✅` panel-equivalent staff/booking management baseline + permissions/reschedule/cancel flows evidenced via `PanelStaffController.php`, `PanelBookingController.php`, `BookingPanelCrudTest.php`, `BookingRbacPermissionsTest.php`, and `BookingAcceptanceTest.php`',
            '`✅` `BuilderService` now exposes standalone `book.staffList` / `book.bookingManage` runtime selectors/mounts and customer-manage/staff helper APIs (`listStaff`, `getBookings`, `showBooking`, `updateBooking`, `rescheduleBooking`, `cancelBooking`) with contract locks',
            '`✅` DoD closure achieved: reschedule/cancel toggle behavior verified in builder preview and backed by customer runtime API path + panel lifecycle/RBAC evidence',
            '`⚠️` source exactness gaps remain (`staffList.layout/showBio/showServices` source controls not modeled exactly; current equivalents are `show_role` / `show_schedule_hint`)',
            '`⚠️` source customer-auth exactness remains a broader follow-up (`RS-13-01`), though `RS-07-02` customer manage endpoints and runtime hooks are now present',
            '`🧪` RS-07-02 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '## Scope',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            '## Audit Inputs Reviewed',
            '## What Was Done (This Pass)',
            '## Executive Result (`RS-07-02`)',
            '## Booking Staff / Manage Parity Matrix',
            '### Matrix (`content/style/panel-preview/runtime-data/endpoint/toggle-behavior/gating/tests`)',
            '`book.staffList`',
            '`book.bookingManage`',
            '`webu_svc_staff_grid_01`',
            '`webu_book_booking_manage_01`',
            '## Endpoint Contract Verification (`GET /staff`, `GET /bookings/my`, `PUT /bookings/{id}`)',
            '### Source-to-Current Endpoint Matrix',
            '`equivalent_panel_only`',
            '`partial_equivalent_panel_only`',
            'PublicBookingController` exposes only:',
            'No public/customer `listStaff`, `listBookings`, `showBooking`, `updateBooking`, `cancelBooking`, or `rescheduleBooking` APIs are present',
            '## Reschedule / Cancel Toggle Behavior Verification (DoD Slice)',
            '### Builder Preview Toggle Baseline (`book.bookingManage`)',
            'show_cancel_action',
            'show_reschedule_action',
            'booking-manage-actions',
            '### Panel Booking Lifecycle / Permissions Baseline (Operational Equivalent)',
            '### Customer Runtime / API Gap',
            '## Builder Preview Parity and Source-Control Exactness Findings',
            'source: `layout`, `showBio`, `showServices`',
            'current schema: `staff_count`, `show_role`, `show_schedule_hint`',
            '## Service/Booking Gating Baseline (Source Critical Vertical)',
            'requiredModules: [MODULE_BOOKING]',
            '## Runtime Widget / Binding Status (`staffList`, `bookingManage`)',
            '[data-webby-booking-widget]',
            'window.WebbyBooking',
            'no `listStaff`',
            'no `getBookings`',
            'no `updateBooking`',
            '## DoD Verdict (`RS-07-02`)',
            'Conclusion: `RS-07-02` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD + Parity Closure)',
            '## Conclusion',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'webu_svc_staff_grid_01',
            'webu_book_booking_manage_01',
            'data-webby-booking-staff',
            'data-webby-booking-manage',
            'staff_count',
            'show_role',
            'show_schedule_hint',
            'bookings_count',
            'show_status',
            'show_cancel_action',
            'show_reschedule_action',
            'show_manage_notes',
            "if (normalized === 'webu_svc_staff_grid_01')",
            "if (normalized === 'webu_book_booking_manage_01')",
            "if (normalizedSectionType === 'webu_svc_staff_grid_01')",
            "if (normalizedSectionType === 'webu_book_booking_manage_01')",
            'booking-staff-grid',
            'booking-manage-list',
            'booking-manage-actions',
            'booking-manage-notes',
            "t('Reschedule')",
            "t('Cancel')",
            'builderSectionAvailabilityMatrix',
            'requiredModules: [MODULE_BOOKING]',
            "builderPreviewMode === 'mobile'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'showBio',
            'showServices',
            'show_bio',
            'show_services',
            'bookings/my',
            'data-webby-booking-manage-runtime',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $cms);
        }

        foreach ([
            'source_component_key": "book.staffList"',
            'webu_svc_staff_grid_01',
            'source_component_key": "book.bookingManage"',
            'webu_book_booking_manage_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            'use App\Http\Controllers\Booking\PanelStaffController as PanelBookingStaffController;',
            'use App\Http\Controllers\Booking\PanelBookingController as PanelBookingController;',
            "Route::get('/{site}/booking/services', [PublicBookingController::class, 'services'])->name('public.sites.booking.services');",
            "Route::get('/{site}/booking/staff', [PublicBookingController::class, 'staff'])->name('public.sites.booking.staff');",
            "Route::get('/{site}/booking/slots', [PublicBookingController::class, 'slots'])->name('public.sites.booking.slots');",
            "Route::post('/{site}/booking/bookings', [PublicBookingController::class, 'createBooking'])",
            "Route::get('/{site}/booking/bookings/my', [PublicBookingController::class, 'myBookings'])->name('public.sites.booking.bookings.my');",
            "Route::get('/{site}/booking/bookings/{booking}', [PublicBookingController::class, 'booking'])->name('public.sites.booking.bookings.show');",
            "Route::put('/{site}/booking/bookings/{booking}', [PublicBookingController::class, 'updateBooking'])",
            "->name('public.sites.booking.bookings.update');",
            "Route::get('/booking/staff', [PanelBookingStaffController::class, 'index'])->name('panel.sites.booking.staff.index');",
            "Route::post('/booking/staff', [PanelBookingStaffController::class, 'store'])->name('panel.sites.booking.staff.store');",
            "Route::get('/booking/bookings', [PanelBookingController::class, 'index'])->name('panel.sites.booking.bookings.index');",
            "Route::post('/booking/bookings/{booking}/reschedule', [PanelBookingController::class, 'reschedule'])->name('panel.sites.booking.bookings.reschedule');",
            "Route::post('/booking/bookings/{booking}/cancel', [PanelBookingController::class, 'cancel'])->name('panel.sites.booking.bookings.cancel');",
            "Route::get('/booking/bookings/{booking}', [PanelBookingController::class, 'show'])->name('panel.sites.booking.bookings.show');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }

        foreach ([
            'public function services(Request $request, Site $site): JsonResponse',
            'public function staff(Request $request, Site $site): JsonResponse',
            'public function slots(Request $request, Site $site): JsonResponse',
            'public function createBooking(Request $request, Site $site): JsonResponse',
            'public function myBookings(Request $request, Site $site): JsonResponse',
            'public function booking(Request $request, Site $site, Booking $booking): JsonResponse',
            'public function updateBooking(Request $request, Site $site, Booking $booking): JsonResponse',
            "'staff_resource_id' => ['nullable', 'integer', 'min:1']",
            "->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicBookingController);
        }

        foreach ([
            'public function listServices(Site $site, array $filters = [], ?User $viewer = null): array;',
            'public function listStaff(Site $site, array $filters = [], ?User $viewer = null): array;',
            'public function slots(Site $site, array $filters = [], ?User $viewer = null): array;',
            'public function createBooking(Site $site, array $payload = [], ?User $viewer = null): array;',
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
            'public function index(Request $request, Site $site): JsonResponse',
            'public function store(Request $request, Site $site): JsonResponse',
            'public function update(Request $request, Site $site, BookingStaffResource $staffResource): JsonResponse',
            'public function destroy(Request $request, Site $site, BookingStaffResource $staffResource): JsonResponse',
            'return response()->json($this->booking->listStaff($site));',
            'BookingPermissions::MANAGE_STAFF',
        ] as $needle) {
            $this->assertStringContainsString($needle, $panelStaffController);
        }

        foreach ([
            'class PanelBookingController extends Controller',
            'public function index(Request $request, Site $site): JsonResponse',
            'public function show(Request $request, Site $site, Booking $booking): JsonResponse',
            'public function reschedule(Request $request, Site $site, Booking $booking): JsonResponse',
            'public function cancel(Request $request, Site $site, Booking $booking): JsonResponse',
            'BookingPermissions::RESCHEDULE',
            'BookingPermissions::CANCEL',
            "return response()->json(\$this->booking->listBookings(\$site, [",
        ] as $needle) {
            $this->assertStringContainsString($needle, $panelBookingController);
        }

        foreach ([
            'window.WebbyBooking',
            'booking_selector',
            '[data-webby-booking-widget]',
            'staff_url',
            'my_bookings_url',
            'booking_url_pattern',
            'staff_selector',
            'manage_selector',
            'listServices: listServices',
            'listStaff: listStaff',
            'getSlots: getSlots',
            'createBooking: createBooking',
            'getBookings: getBookings',
            'showBooking: showBooking',
            'updateBooking: updateBooking',
            'rescheduleBooking: rescheduleBooking',
            'cancelBooking: cancelBooking',
            'mountWidget: mountWidget',
            'mountStaffWidget: mountStaffWidget',
            'mountManageWidget: mountManageWidget',
            'data-webby-booking-staff',
            'data-webby-booking-manage',
            'staff_resource_id: staffResourceId || undefined,',
            'function mountWidgets() {',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'BuilderServicePublicVerticalRuntimeHelpersContractTest',
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
            'showBooking: showBooking,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }

        foreach ([
            '/public/sites/{site}/booking/services:',
            '/public/sites/{site}/booking/staff:',
            '/public/sites/{site}/booking/slots:',
            '/public/sites/{site}/booking/bookings:',
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

        foreach ([
            'webu_svc_staff_grid_01',
            'webu_book_booking_manage_01',
            'data-webby-booking-staff',
            'data-webby-booking-manage',
            'requiredModules: [MODULE_BOOKING]',
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingCoverageContract);
        }

        $this->assertStringContainsString('requiredModules: [MODULE_BOOKING]', $activationFrontendContract);
        $this->assertStringContainsString("key: 'booking'", $activationUnitTest);

        foreach ([
            'webu-services-booking-minimal.v1.openapi.yaml',
            '/panel/sites/{site}/booking/staff:',
            '/panel/sites/{site}/booking/bookings/{booking}/reschedule:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $minimalOpenApiDeliverableTest);
        }
    }
}
