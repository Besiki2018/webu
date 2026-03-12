<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryServicesComponentsRs0601BaselineGapAuditSyncTest extends TestCase
{
    public function test_rs_06_01_progress_audit_doc_locks_services_components_parity_endpoint_and_runtime_gap_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SERVICES_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_06_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SERVICES_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_06_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $aliasMapPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $webRoutesPath = base_path('routes/web.php');
        $publicBookingControllerPath = base_path('app/Http/Controllers/Booking/PublicBookingController.php');
        $bookingPublicServiceContractPath = base_path('app/Booking/Contracts/BookingPublicServiceContract.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $servicesBookingOpenApiPath = base_path('docs/openapi/webu-services-booking-minimal.v1.openapi.yaml');
        $bookingPublicApiTestPath = base_path('tests/Feature/Booking/BookingPublicApiTest.php');
        $bookingPublicExtendedEndpointsTestPath = base_path('tests/Feature/Booking/BookingPublicExtendedEndpointsTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $servicesBookingContractsTestPath = base_path('tests/Unit/UniversalServicesBookingContractsP5F3Test.php');
        $bookingCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsBookingBuilderCoverage.contract.test.ts');
        $activationFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts');
        $activationUnitTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');

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
            $runtimeContractTestPath,
            $servicesBookingContractsTestPath,
            $bookingCoverageContractPath,
            $activationFrontendContractPath,
            $activationUnitTestPath,
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
        $runtimeContractTest = File::get($runtimeContractTestPath);
        $servicesBookingContractsTest = File::get($servicesBookingContractsTestPath);
        $bookingCoverageContract = File::get($bookingCoverageContractPath);
        $activationFrontendContract = File::get($activationFrontendContractPath);
        $activationUnitTest = File::get($activationUnitTestPath);

        foreach ([
            '# 6) SERVICES COMPONENTS (Service businesses, clinics, salons, agencies)',
            '## 6.1 svc.serviceList',
            'Content: category filter, layout (cards/list), showPrice, showDuration',
            'Data: GET /services',
            '## 6.2 svc.serviceDetail',
            'Content: showBookCTA, showStaff',
            'Data: GET /services/:slug',
            '## 6.3 svc.pricingTable',
            'Content: plans/items (static) or bind from services',
            '## 6.4 svc.faq',
            'Content: items (accordion)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-06-01` (`DONE`, `P1`)',
            'UNIVERSAL_COMPONENT_LIBRARY_SERVICES_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_06_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_SERVICES_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_06_01_2026_02_26.md',
            'UniversalComponentLibraryServicesComponentsRs0601BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryServicesComponentsRs0601ClosureAuditSyncTest.php',
            'BookingPublicApiTest.php',
            'BookingPublicExtendedEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            'UniversalServicesBookingContractsP5F3Test.php',
            'CmsBookingBuilderCoverage.contract.test.ts',
            'CmsUniversalComponentLibraryActivation.contract.test.ts',
            'UniversalComponentLibraryActivationP5F5Test.php',
            '`✅` services components parity matrix documented for `svc.serviceList`, `svc.serviceDetail`, `svc.pricingTable`, `svc.faq` with canonical alias mappings (`webu_svc_services_list_01`, `webu_svc_service_detail_01`, `webu_svc_pricing_table_01`, `webu_svc_faq_01`)',
            '`✅` baseline parity/gap audit is preserved and superseded by a closure audit with service-detail endpoint + standalone widget-hook runtime evidence',
            '`✅` public booking service discovery/detail runtime endpoints are now feature-tested (`GET /public/sites/{site}/booking/services`, `GET /public/sites/{site}/booking/services/{slug}`) with supporting staff/calendar/customer-manage endpoints used by builder booking runtime helpers',
            '`✅` booking builder preview placeholders + component-specific preview branches evidenced for all 4 `svc.*` components; booking-only gating baseline cross-checked via builder coverage + activation tests',
            '`✅` `BuilderService` now exposes standalone `svc.*` runtime selectors/mounts (`serviceList`, `serviceDetail`, `pricingTable`, `faq`) plus service-detail/calendar/staff/customer-booking helper APIs; pricing-table and FAQ bound runtime paths use accepted services-endpoint aliases',
            '`✅` DoD closure achieved (all 4 services components pass parity/runtime-data verification with accepted endpoint/runtime equivalents)',
            '`⚠️` source-control exactness gaps remain (`serviceList` category filter + cards/list layout control, `serviceDetail.showStaff`, FAQ items-array authoring schema exactness)',
            '`🧪` RS-06-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '## Scope',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            '## Audit Inputs Reviewed',
            '## What Was Done (This Pass)',
            '## Executive Result (`RS-06-01`)',
            '## Services Components Parity Matrix',
            '### Matrix (`content/style/panel-preview/runtime-data/endpoint/responsive/gating/tests`)',
            '`svc.serviceList`',
            '`svc.serviceDetail`',
            '`svc.pricingTable`',
            '`svc.faq`',
            '`webu_svc_services_list_01`',
            '`webu_svc_service_detail_01`',
            '`webu_svc_pricing_table_01`',
            '`webu_svc_faq_01`',
            '## Endpoint Contract Verification (`GET /services`, `/services/:slug`)',
            '### Source-to-Current Endpoint Matrix',
            '`exact_semantics_path_variant`',
            '`gap`',
            'PublicBookingController::services(...)',
            'Minimal OpenAPI lists `/public/sites/{site}/booking/services` but does not document a `search` query parameter',
            '## Static-vs-Bound Pricing Table Behavior Validation (`svc.pricingTable`)',
            'static mode baseline is `pass`, bound-from-services mode is `missing` (overall `partial`)',
            '## Builder Preview Parity and Source-Control Exactness Findings',
            'source `showBookCTA` is covered via equivalent `show_book_cta`',
            'source `showStaff` is not modeled in current component schema',
            '## Service/Booking Gating Baseline (Source Vertical Interpretation)',
            'requiredModules: [MODULE_BOOKING]',
            '## Runtime Widget / Binding Status (`serviceList`, `serviceDetail`, `pricingTable`, `faq`)',
            '[data-webby-booking-widget]',
            'window.WebbyBooking',
            'no standalone `services_selector` / `service_detail_selector` / `pricing_table_selector` / `faq_selector`',
            'data-webby-booking-service-detail',
            '## DoD Verdict (`RS-06-01`)',
            'Conclusion: `RS-06-01` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD)',
            '## Conclusion',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'webu_svc_services_list_01',
            'webu_svc_service_detail_01',
            'webu_svc_pricing_table_01',
            'webu_svc_faq_01',
            'data-webby-booking-services',
            'data-webby-booking-service-detail',
            'data-webby-booking-pricing-table',
            'data-webby-booking-faq',
            'services_count',
            'show_price',
            'show_duration',
            'service_slug',
            'show_book_cta',
            'plans_count',
            'show_feature_list',
            'highlight_featured',
            'items_count',
            'expand_first',
            "if (normalized === 'webu_svc_services_list_01')",
            "if (normalized === 'webu_svc_service_detail_01')",
            "if (normalized === 'webu_svc_pricing_table_01')",
            "if (normalized === 'webu_svc_faq_01')",
            "if (normalizedSectionType === 'webu_svc_services_list_01')",
            "if (normalizedSectionType === 'webu_svc_service_detail_01')",
            "if (normalizedSectionType === 'webu_svc_pricing_table_01')",
            "if (normalizedSectionType === 'webu_svc_faq_01')",
            'applyBookingPreviewState',
            'builderSectionAvailabilityMatrix',
            'requiredModules: [MODULE_BOOKING]',
            'builderPreviewMode === \'mobile\'',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'showStaff',
            'showBookCTA',
            'show_staff:',
            'category_filter:',
            'bind_from_services:',
            'mountServicesWidget',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $cms);
        }

        foreach ([
            'source_component_key": "svc.serviceList"',
            'webu_svc_services_list_01',
            'source_component_key": "svc.serviceDetail"',
            'webu_svc_service_detail_01',
            'source_component_key": "svc.pricingTable"',
            'webu_svc_pricing_table_01',
            'source_component_key": "svc.faq"',
            'webu_svc_faq_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            "Route::get('/{site}/booking/services', [PublicBookingController::class, 'services'])->name('public.sites.booking.services');",
            "Route::get('/{site}/booking/services/{slug}', [PublicBookingController::class, 'service'])->name('public.sites.booking.services.show');",
            "Route::get('/{site}/booking/staff', [PublicBookingController::class, 'staff'])->name('public.sites.booking.staff');",
            "Route::get('/{site}/booking/calendar', [PublicBookingController::class, 'calendar'])->name('public.sites.booking.calendar');",
            "Route::get('/{site}/booking/bookings/my', [PublicBookingController::class, 'myBookings'])->name('public.sites.booking.bookings.my');",
            "Route::get('/{site}/booking/bookings/{booking}', [PublicBookingController::class, 'booking'])->name('public.sites.booking.bookings.show');",
            "->name('public.sites.booking.bookings.update');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }

        foreach ([
            'public function services(Request $request, Site $site): JsonResponse',
            'public function service(Request $request, Site $site, string $slug): JsonResponse',
            'public function staff(Request $request, Site $site): JsonResponse',
            "'search' => \$request->query('search')",
            'public function slots(Request $request, Site $site): JsonResponse',
            'public function calendar(Request $request, Site $site): JsonResponse',
            'public function createBooking(Request $request, Site $site): JsonResponse',
            'public function myBookings(Request $request, Site $site): JsonResponse',
            'public function booking(Request $request, Site $site, Booking $booking): JsonResponse',
            'public function updateBooking(Request $request, Site $site, Booking $booking): JsonResponse',
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
            'showService(',
            'serviceBySlug',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $bookingPublicServiceContract);
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
            '- name: search',
            "'201':",
        ] as $needle) {
            $this->assertStringContainsString($needle, $servicesBookingOpenApi);
        }

        foreach ([
            "route('public.sites.booking.services'",
            '->assertJsonPath(\'services.0.id\', $service->id);',
            "route('public.sites.booking.slots'",
            "route('public.sites.booking.bookings.store'",
            '->assertCreated()',
        ] as $needle) {
            $this->assertStringContainsString($needle, $bookingPublicApiTest);
        }
        $this->assertStringNotContainsString('public.sites.booking.services.show', $bookingPublicApiTest);

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
            'test_p5_f3_02_availability_slot_apis_contract_doc_and_routes_are_locked',
            "name('public.sites.booking.slots')",
            'PublicBookingController',
            'BookingPublicApiTest',
            'test_public_booking_services_slots_and_create_flow_is_visible_in_panel',
            'test_p5_f3_04_booking_builder_components_contract_doc_and_cms_builder_hooks_are_locked',
            'svc.*',
            'webu_svc_services_list_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $servicesBookingContractsTest);
        }

        foreach ([
            'webu_svc_services_list_01',
            'webu_svc_service_detail_01',
            'webu_svc_pricing_table_01',
            'webu_svc_faq_01',
            'data-webby-booking-services',
            'data-webby-booking-service-detail',
            'data-webby-booking-pricing-table',
            'data-webby-booking-faq',
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
            '\'services_url\' => $publicPrefix ? "{$publicPrefix}/services" : null',
            '\'service_detail_url_pattern\' => $publicPrefix ? "{$publicPrefix}/services/{slug}" : null',
            '\'staff_url\' => $publicPrefix ? "{$publicPrefix}/staff" : null',
            '\'slots_url\' => $publicPrefix ? "{$publicPrefix}/slots" : null',
            '\'calendar_url\' => $publicPrefix ? "{$publicPrefix}/calendar" : null',
            '\'create_booking_url\' => $publicPrefix ? "{$publicPrefix}/bookings" : null',
            '\'my_bookings_url\' => $publicPrefix ? "{$publicPrefix}/bookings/my" : null',
            '\'booking_url_pattern\' => $publicPrefix ? "{$publicPrefix}/bookings/{booking_id}" : null',
            '\'pricing_url\' => $publicPrefix ? "{$publicPrefix}/services" : null',
            '\'faq_url\' => $publicPrefix ? "{$publicPrefix}/services" : null',
            "'booking_selector' => '[data-webby-booking-widget]'",
            "'services_selector' => '[data-webby-booking-services]'",
            "'service_detail_selector' => '[data-webby-booking-service-detail]'",
            "'pricing_table_selector' => '[data-webby-booking-pricing-table]'",
            "'faq_selector' => '[data-webby-booking-faq]'",
            'function listServices(params) {',
            'function getService(slug) {',
            'function listStaff(params) {',
            'function getSlots(params) {',
            'function getCalendar(params) {',
            'function createBooking(payload) {',
            'function getBookings(params) {',
            'function showBooking(bookingId) {',
            'function updateBooking(bookingId, payload) {',
            'function rescheduleBooking(bookingId, payload) {',
            'function cancelBooking(bookingId, payload) {',
            'function mountWidget(container, options) {',
            'function mountServicesWidget(container, options) {',
            'function mountServiceDetailWidget(container, options) {',
            'function mountPricingTableWidget(container, options) {',
            'function mountFaqWidget(container, options) {',
            'function mountWidgets() {',
            'window.WebbyBooking = {',
            'listServices: listServices,',
            'getService: getService,',
            'listStaff: listStaff,',
            'getSlots: getSlots,',
            'getCalendar: getCalendar,',
            'createBooking: createBooking,',
            'getBookings: getBookings,',
            'showBooking: showBooking,',
            'updateBooking: updateBooking,',
            'rescheduleBooking: rescheduleBooking,',
            'cancelBooking: cancelBooking,',
            'mountWidget: mountWidget,',
            'mountServicesWidget: mountServicesWidget,',
            'mountServiceDetailWidget: mountServiceDetailWidget,',
            'mountPricingTableWidget: mountPricingTableWidget,',
            'mountFaqWidget: mountFaqWidget,',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'data-webby-booking-services',
            'data-webby-booking-service-detail',
            'data-webby-booking-pricing-table',
            'data-webby-booking-faq',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            'BuilderServicePublicVerticalRuntimeHelpersContractTest',
            "'service_detail_url_pattern' =>",
            "'services_selector' => '[data-webby-booking-services]'",
            "'service_detail_selector' => '[data-webby-booking-service-detail]'",
            "'pricing_table_selector' => '[data-webby-booking-pricing-table]'",
            "'faq_selector' => '[data-webby-booking-faq]'",
            'function mountServicesWidget(container, options) {',
            'function mountServiceDetailWidget(container, options) {',
            'function mountPricingTableWidget(container, options) {',
            'function mountFaqWidget(container, options) {',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }
    }
}
