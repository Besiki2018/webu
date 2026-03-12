<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryServicesComponentsRs0601ClosureAuditSyncTest extends TestCase
{
    public function test_rs_06_01_closure_audit_locks_services_component_runtime_hooks_and_endpoint_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SERVICES_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_06_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SERVICES_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_06_01_2026_02_26.md');

        $webRoutesPath = base_path('routes/web.php');
        $publicBookingControllerPath = base_path('app/Http/Controllers/Booking/PublicBookingController.php');
        $bookingPublicServiceContractPath = base_path('app/Booking/Contracts/BookingPublicServiceContract.php');
        $bookingPublicServicePath = base_path('app/Booking/Services/BookingPublicService.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $servicesBookingOpenApiPath = base_path('docs/openapi/webu-services-booking-minimal.v1.openapi.yaml');

        $bookingPublicApiTestPath = base_path('tests/Feature/Booking/BookingPublicApiTest.php');
        $bookingPublicExtendedEndpointsTestPath = base_path('tests/Feature/Booking/BookingPublicExtendedEndpointsTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryServicesComponentsRs0601BaselineGapAuditSyncTest.php');
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
            $runtimeContractTestPath,
            $baselineSyncTestPath,
            $servicesBookingContractsTestPath,
            base_path('resources/js/Pages/Project/__tests__/CmsBookingBuilderCoverage.contract.test.ts'),
            base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts'),
            base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php'),
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
        $runtimeContractTest = File::get($runtimeContractTestPath);
        $servicesBookingContractsTest = File::get($servicesBookingContractsTestPath);

        foreach ([
            '# 6) SERVICES COMPONENTS (Service businesses, clinics, salons, agencies)',
            '## 6.1 svc.serviceList',
            'Data: GET /services',
            '## 6.2 svc.serviceDetail',
            'Data: GET /services/:slug',
            '## 6.3 svc.pricingTable',
            'Content: plans/items (static) or bind from services',
            '## 6.4 svc.faq',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-06-01` (`DONE`, `P1`)',
            'UNIVERSAL_COMPONENT_LIBRARY_SERVICES_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_06_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_SERVICES_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_06_01_2026_02_26.md',
            'UniversalComponentLibraryServicesComponentsRs0601BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryServicesComponentsRs0601ClosureAuditSyncTest.php',
            'BookingPublicExtendedEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            '`✅` baseline parity/gap audit is preserved and superseded by a closure audit with service-detail endpoint + standalone widget-hook runtime evidence',
            '`✅` public booking service discovery/detail runtime endpoints are now feature-tested (`GET /public/sites/{site}/booking/services`, `GET /public/sites/{site}/booking/services/{slug}`) with supporting staff/calendar/customer-manage endpoints used by builder booking runtime helpers',
            '`✅` `BuilderService` now exposes standalone `svc.*` runtime selectors/mounts (`serviceList`, `serviceDetail`, `pricingTable`, `faq`) plus service-detail/calendar/staff/customer-booking helper APIs; pricing-table and FAQ bound runtime paths use accepted services-endpoint aliases',
            '`✅` DoD closure achieved (all 4 services components pass parity/runtime-data verification with accepted endpoint/runtime equivalents)',
            '`⚠️` source-control exactness gaps remain (`serviceList` category filter + cards/list layout control, `serviceDetail.showStaff`, FAQ items-array authoring schema exactness)',
            '`🧪` RS-06-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Goal (`RS-06-01` Closure Pass)',
            '## ✅ What Was Done (Closure Pass)',
            'GET /public/sites/{site}/booking/services/{slug}',
            'GET /public/sites/{site}/booking/staff',
            'GET /public/sites/{site}/booking/calendar',
            'GET /public/sites/{site}/booking/bookings/my',
            'GET/PUT /public/sites/{site}/booking/bookings/{booking}',
            'getService`, `listStaff`, `calendar`, `listBookings`, `showBooking`, `updateBooking`, `cancelBooking`, `rescheduleBooking',
            'service_detail_url_pattern',
            'pricing_url',
            'faq_url',
            'mountServicesWidget`, `mountServiceDetailWidget`, `mountPricingTableWidget`, `mountFaqWidget',
            '## Executive Result (`RS-06-01`)',
            '`RS-06-01` is now **DoD-complete** as a services-components parity/runtime verification task.',
            '## Services Components Runtime Closure Matrix (`svc.serviceList`, `svc.serviceDetail`, `svc.pricingTable`, `svc.faq`)',
            'accepted_exact_semantics_path_variant',
            '## Endpoint Integration Closure Matrix (`GET /services`, `GET /services/:slug`)',
            '## Published Runtime Hook Closure (`BuilderService`)',
            'mountServicesWidget(container, options)',
            'mountServiceDetailWidget(container, options)',
            'mountPricingTableWidget(container, options)',
            'mountFaqWidget(container, options)',
            '[data-webby-booking-services]',
            '[data-webby-booking-service-detail]',
            '[data-webby-booking-pricing-table]',
            '[data-webby-booking-faq]',
            '## Static-vs-Bound Pricing Table Closure (`svc.pricingTable`)',
            '`mountPricingTableWidget(container, options)` sets a pricing-table runtime marker and delegates to `mountServicesWidget(container, options)`',
            'Verdict: static mode remains `pass`; bound-from-services mode is now `pass` as an accepted runtime alias/delegation implementation.',
            '## Feature / Runtime Evidence Added (Closure Pass)',
            'BookingPublicExtendedEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            '## DoD Closure Matrix (`RS-06-01`)',
            'all 4 components pass parity matrix and runtime data checks',
            'static-vs-bound pricing table behavior validation',
            '## Remaining Exactness / Modeling Gaps (Truthful, Non-Blocking for `RS-06-01` DoD)',
            'source `showStaff` control is still not modeled as an explicit builder schema flag',
            '## DoD Verdict (`RS-06-01`)',
            '`RS-06-01` passes and is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            "Route::get('/{site}/booking/services', [PublicBookingController::class, 'services'])->name('public.sites.booking.services');",
            "Route::get('/{site}/booking/services/{slug}', [PublicBookingController::class, 'service'])->name('public.sites.booking.services.show');",
            "Route::get('/{site}/booking/staff', [PublicBookingController::class, 'staff'])->name('public.sites.booking.staff');",
            "Route::get('/{site}/booking/slots', [PublicBookingController::class, 'slots'])->name('public.sites.booking.slots');",
            "Route::get('/{site}/booking/calendar', [PublicBookingController::class, 'calendar'])->name('public.sites.booking.calendar');",
            "Route::post('/{site}/booking/bookings', [PublicBookingController::class, 'createBooking'])",
            "->middleware('throttle:public-booking')",
            "->name('public.sites.booking.bookings.store');",
            "Route::get('/{site}/booking/bookings/my', [PublicBookingController::class, 'myBookings'])->name('public.sites.booking.bookings.my');",
            "Route::get('/{site}/booking/bookings/{booking}', [PublicBookingController::class, 'booking'])->name('public.sites.booking.bookings.show');",
            "Route::put('/{site}/booking/bookings/{booking}', [PublicBookingController::class, 'updateBooking'])",
            "->name('public.sites.booking.bookings.update');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }

        foreach ([
            'public function services(Request $request, Site $site): JsonResponse',
            'public function service(Request $request, Site $site, string $slug): JsonResponse',
            'public function staff(Request $request, Site $site): JsonResponse',
            'public function slots(Request $request, Site $site): JsonResponse',
            'public function calendar(Request $request, Site $site): JsonResponse',
            'public function createBooking(Request $request, Site $site): JsonResponse',
            'public function myBookings(Request $request, Site $site): JsonResponse',
            'public function booking(Request $request, Site $site, Booking $booking): JsonResponse',
            'public function updateBooking(Request $request, Site $site, Booking $booking): JsonResponse',
            "'action' => ['nullable', 'string', 'in:cancel,reschedule']",
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicBookingController);
        }

        foreach ([
            'public function listServices(Site $site, array $filters = [], ?User $viewer = null): array;',
            'public function getService(Site $site, string $slug, ?User $viewer = null): array;',
            'public function listStaff(Site $site, array $filters = [], ?User $viewer = null): array;',
            'public function slots(Site $site, array $filters = [], ?User $viewer = null): array;',
            'public function calendar(Site $site, array $filters = [], ?User $viewer = null): array;',
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
            'protected BookingPanelServiceContract $panel',
            'public function getService(Site $site, string $slug, ?User $viewer = null): array',
            'throw new BookingDomainException(\'Booking service not found.\', 404);',
            'public function listStaff(Site $site, array $filters = [], ?User $viewer = null): array',
            'public function calendar(Site $site, array $filters = [], ?User $viewer = null): array',
            'public function listBookings(Site $site, array $filters = [], ?User $viewer = null): array',
            'public function showBooking(Site $site, Booking $booking, ?User $viewer = null): array',
            'public function updateBooking(Site $site, Booking $booking, array $payload = [], ?User $viewer = null): array',
            'public function cancelBooking(Site $site, Booking $booking, array $payload = [], ?User $viewer = null): array',
            'public function rescheduleBooking(Site $site, Booking $booking, array $payload = [], ?User $viewer = null): array',
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingPublicService);
        }

        foreach ([
            '/public/sites/{site}/booking/services:',
            '/public/sites/{site}/booking/services/{slug}:',
            '/public/sites/{site}/booking/staff:',
            '/public/sites/{site}/booking/slots:',
            '/public/sites/{site}/booking/calendar:',
            '/public/sites/{site}/booking/bookings:',
            '/public/sites/{site}/booking/bookings/my:',
            '/public/sites/{site}/booking/bookings/{booking}:',
            'summary: List public booking services',
            'summary: Show public booking service by slug',
            'summary: List public booking staff/resources',
            'summary: Public booking availability calendar/events snapshot',
            'summary: List authenticated customer bookings',
            'summary: Show authenticated customer booking',
            'summary: Update authenticated customer booking (cancel/reschedule)',
            '- name: search',
            '- name: staff_resource_id',
            "'201':",
        ] as $needle) {
            $this->assertStringContainsString($needle, $servicesBookingOpenApi);
        }

        foreach ([
            'BookingPublicApiTest',
            "route('public.sites.booking.services'",
            "route('public.sites.booking.slots'",
            "route('public.sites.booking.bookings.store'",
            'test_public_booking_services_slots_and_create_flow_is_visible_in_panel',
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingPublicApiTest);
        }

        foreach ([
            'BookingPublicExtendedEndpointsTest',
            'test_public_booking_extended_endpoints_expose_service_staff_calendar_and_customer_manage_flow',
            "route('public.sites.booking.services.show'",
            "route('public.sites.booking.staff'",
            "route('public.sites.booking.calendar'",
            "route('public.sites.booking.bookings.my'",
            "route('public.sites.booking.bookings.show'",
            "route('public.sites.booking.bookings.update'",
            "'action' => 'reschedule'",
            "'action' => 'cancel'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingPublicExtendedEndpointsTest);
        }

        foreach ([
            'BuilderServicePublicVerticalRuntimeHelpersContractTest',
            "'service_detail_url_pattern' =>",
            "'pricing_url' =>",
            "'faq_url' =>",
            "'services_selector' => '[data-webby-booking-services]'",
            "'service_detail_selector' => '[data-webby-booking-service-detail]'",
            "'pricing_table_selector' => '[data-webby-booking-pricing-table]'",
            "'faq_selector' => '[data-webby-booking-faq]'",
            'function getService(slug) {',
            'function mountServicesWidget(container, options) {',
            'function mountServiceDetailWidget(container, options) {',
            'function mountPricingTableWidget(container, options) {',
            'function mountFaqWidget(container, options) {',
            'getService: getService,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }

        foreach ([
            "'service_detail_url_pattern' =>",
            "'staff_url' =>",
            "'calendar_url' =>",
            "'my_bookings_url' =>",
            "'booking_url_pattern' =>",
            "'pricing_url' =>",
            "'faq_url' =>",
            "'services_selector' => '[data-webby-booking-services]'",
            "'service_detail_selector' => '[data-webby-booking-service-detail]'",
            "'pricing_table_selector' => '[data-webby-booking-pricing-table]'",
            "'faq_selector' => '[data-webby-booking-faq]'",
            'function getService(slug) {',
            'function listStaff(params) {',
            'function getCalendar(params) {',
            'function getBookings(params) {',
            'function showBooking(bookingId) {',
            'function updateBooking(bookingId, payload) {',
            'function rescheduleBooking(bookingId, payload) {',
            'function cancelBooking(bookingId, payload) {',
            'function mountServicesWidget(container, options) {',
            'function mountServiceDetailWidget(container, options) {',
            'function mountPricingTableWidget(container, options) {',
            'mountServicesWidget(container, options);',
            'function mountFaqWidget(container, options) {',
            'listServices(asObject(options)).then(function (payload) {',
            'mountServicesWidget: mountServicesWidget,',
            'mountServiceDetailWidget: mountServiceDetailWidget,',
            'mountPricingTableWidget: mountPricingTableWidget,',
            'mountFaqWidget: mountFaqWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'test_p5_f3_02_availability_slot_apis_contract_doc_and_routes_are_locked',
            'test_p5_f3_04_booking_builder_components_contract_doc_and_cms_builder_hooks_are_locked',
            'svc.*',
            'BookingPublicApiTest',
            'webu_svc_services_list_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $servicesBookingContractsTest);
        }
    }
}
