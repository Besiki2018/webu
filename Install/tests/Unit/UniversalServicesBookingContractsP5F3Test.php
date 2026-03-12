<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalServicesBookingContractsP5F3Test extends TestCase
{
    public function test_p5_f3_01_services_staff_resources_contract_doc_and_routes_are_locked(): void
    {
        $docPath = base_path('docs/architecture/UNIVERSAL_SERVICES_STAFF_RESOURCES_MODULE_P5_F3_01.md');
        $this->assertFileExists($docPath);

        $doc = File::get($docPath);
        $routes = File::get(base_path('routes/web.php'));
        $panelServiceController = File::get(base_path('app/Http/Controllers/Booking/PanelServiceController.php'));
        $panelStaffController = File::get(base_path('app/Http/Controllers/Booking/PanelStaffController.php'));
        $bookingPanelService = File::get(base_path('app/Booking/Services/BookingPanelService.php'));
        $crudTest = File::get(base_path('tests/Feature/Booking/BookingPanelCrudTest.php'));

        $this->assertStringContainsString('P5-F3-01', $doc);
        $this->assertStringContainsString('BookingService', $doc);
        $this->assertStringContainsString('BookingStaffResource', $doc);
        $this->assertStringContainsString('PanelServiceController', $doc);
        $this->assertStringContainsString('PanelStaffController', $doc);
        $this->assertStringContainsString('tenant.route.scope', $doc);
        $this->assertStringContainsString('BookingPanelCrudTest', $doc);

        $this->assertStringContainsString("name('panel.sites.booking.services.index')", $routes);
        $this->assertStringContainsString("name('panel.sites.booking.staff.index')", $routes);
        $this->assertStringContainsString('site.entitlement:booking', $routes);

        $this->assertStringContainsString('public function store(Request $request, Site $site): JsonResponse', $panelServiceController);
        $this->assertStringContainsString('public function store(Request $request, Site $site): JsonResponse', $panelStaffController);
        $this->assertStringContainsString('public function createService(Site $site, array $payload): BookingService', $bookingPanelService);
        $this->assertStringContainsString('public function createStaff(Site $site, array $payload): BookingStaffResource', $bookingPanelService);
        $this->assertStringContainsString('test_owner_can_manage_booking_services_staff_and_bookings', $crudTest);
    }

    public function test_p5_f3_02_availability_slot_apis_contract_doc_and_routes_are_locked(): void
    {
        $docPath = base_path('docs/architecture/UNIVERSAL_BOOKING_AVAILABILITY_SLOT_APIS_P5_F3_02.md');
        $this->assertFileExists($docPath);

        $doc = File::get($docPath);
        $routes = File::get(base_path('routes/web.php'));
        $panelStaffController = File::get(base_path('app/Http/Controllers/Booking/PanelStaffController.php'));
        $publicBookingController = File::get(base_path('app/Http/Controllers/Booking/PublicBookingController.php'));
        $bookingPanelService = File::get(base_path('app/Booking/Services/BookingPanelService.php'));
        $bookingPublicService = File::get(base_path('app/Booking/Services/BookingPublicService.php'));
        $teamSchedulingTest = File::get(base_path('tests/Feature/Booking/BookingTeamSchedulingTest.php'));
        $publicApiTest = File::get(base_path('tests/Feature/Booking/BookingPublicApiTest.php'));

        $this->assertStringContainsString('P5-F3-02', $doc);
        $this->assertStringContainsString('UniversalBookingAvailabilityApiBridgeService', $doc);
        $this->assertStringContainsString('booking_staff_work_schedules', $doc);
        $this->assertStringContainsString('booking_staff_time_off', $doc);
        $this->assertStringContainsString('BookingPublicService', $doc);
        $this->assertStringContainsString('BookingPanelService', $doc);
        $this->assertStringContainsString('BookingCollisionService', $doc);
        $this->assertStringContainsString('blocked_times', $doc);
        $this->assertStringContainsString('api_surface', $doc);
        $this->assertStringContainsString('Collision Contract Baseline', $doc);

        $this->assertStringContainsString("name('panel.sites.booking.staff.work-schedules.sync')", $routes);
        $this->assertStringContainsString("name('panel.sites.booking.staff.time-off.store')", $routes);
        $this->assertStringContainsString("name('public.sites.booking.slots')", $routes);
        $this->assertStringContainsString('site.entitlement:booking_team_scheduling', $routes);

        $this->assertStringContainsString('public function syncWorkSchedules(Request $request, Site $site, BookingStaffResource $staffResource): JsonResponse', $panelStaffController);
        $this->assertStringContainsString('public function slots(Request $request, Site $site): JsonResponse', $publicBookingController);
        $this->assertStringContainsString('public function syncStaffSchedules(', $bookingPanelService);
        $this->assertStringContainsString('public function slots(Site $site, array $filters = [], ?User $viewer = null): array', $bookingPublicService);
        $this->assertStringContainsString('test_public_booking_services_slots_and_create_flow_is_visible_in_panel', $publicApiTest);
        $this->assertStringContainsString('class BookingTeamSchedulingTest', $teamSchedulingTest);
    }

    public function test_p5_f3_03_booking_flow_events_payments_contract_doc_and_routes_are_locked(): void
    {
        $docPath = base_path('docs/architecture/UNIVERSAL_BOOKING_FLOW_EVENTS_PAYMENTS_P5_F3_03.md');
        $this->assertFileExists($docPath);

        $doc = File::get($docPath);
        $routes = File::get(base_path('routes/web.php'));
        $panelBookingController = File::get(base_path('app/Http/Controllers/Booking/PanelBookingController.php'));
        $panelBookingService = File::get(base_path('app/Booking/Services/BookingPanelService.php'));
        $bookingFinanceService = File::get(base_path('app/Booking/Services/BookingFinanceService.php'));
        $acceptanceTest = File::get(base_path('tests/Feature/Booking/BookingAcceptanceTest.php'));
        $advancedAcceptanceTest = File::get(base_path('tests/Feature/Booking/BookingAdvancedAcceptanceTest.php'));
        $financeLedgerTest = File::get(base_path('tests/Feature/Booking/BookingFinanceLedgerTest.php'));

        $this->assertStringContainsString('P5-F3-03', $doc);
        $this->assertStringContainsString('PublicBookingController', $doc);
        $this->assertStringContainsString('PanelBookingController', $doc);
        $this->assertStringContainsString('PanelFinanceController', $doc);
        $this->assertStringContainsString('BookingEvent', $doc);
        $this->assertStringContainsString('universal_payment', $doc);
        $this->assertStringContainsString('BookingAcceptanceTest', $doc);
        $this->assertStringContainsString('BookingFinanceLedgerTest', $doc);

        $this->assertStringContainsString("name('public.sites.booking.bookings.store')", $routes);
        $this->assertStringContainsString("name('panel.sites.booking.bookings.status')", $routes);
        $this->assertStringContainsString("name('panel.sites.booking.bookings.reschedule')", $routes);
        $this->assertStringContainsString("name('panel.sites.booking.finance.payments.store')", $routes);
        $this->assertStringContainsString('site.entitlement:booking_finance', $routes);

        $this->assertStringContainsString('public function updateStatus(Request $request, Site $site, Booking $booking): JsonResponse', $panelBookingController);
        $this->assertStringContainsString('public function reschedule(Request $request, Site $site, Booking $booking): JsonResponse', $panelBookingController);
        $this->assertStringContainsString('public function cancel(Request $request, Site $site, Booking $booking): JsonResponse', $panelBookingController);
        $this->assertStringContainsString('BookingEvent::query()->create([', $panelBookingService);
        $this->assertStringContainsString("'universal_payment' => \$this->universalPayments->normalizeBookingPayment(\$payment)", $bookingFinanceService);
        $this->assertStringContainsString("assertContains('created', \$eventTypes)", $acceptanceTest);
        $this->assertStringContainsString('test_advanced_ops_permissions_and_finance_acceptance_flow', $advancedAcceptanceTest);
        $this->assertStringContainsString('test_owner_can_issue_invoice_record_payment_refund_and_keep_ledger_balanced', $financeLedgerTest);
    }

    public function test_p5_f3_04_booking_builder_components_contract_doc_and_cms_builder_hooks_are_locked(): void
    {
        $docPath = base_path('docs/architecture/UNIVERSAL_BOOKING_BUILDER_COMPONENTS_P5_F3_04.md');
        $this->assertFileExists($docPath);

        $doc = File::get($docPath);
        $cms = File::get(base_path('resources/js/Pages/Project/Cms.tsx'));
        $featureFlags = File::get(base_path('app/Cms/Services/CmsProjectTypeModuleFeatureFlagService.php'));

        $this->assertStringContainsString('P5-F3-04', $doc);
        $this->assertStringContainsString('book.*', $doc);
        $this->assertStringContainsString('svc.*', $doc);
        $this->assertStringContainsString('MODULE_BOOKING', $doc);
        $this->assertStringContainsString('MODULE_BOOKING_TEAM_SCHEDULING', $doc);
        $this->assertStringContainsString('MODULE_BOOKING_FINANCE', $doc);
        $this->assertStringContainsString('filteredSectionLibrary', $doc);
        $this->assertStringContainsString('isModuleProjectTypeAllowed(...)', $doc);

        $this->assertStringContainsString('BUILDER_BOOKING_DISCOVERY_LIBRARY_SECTIONS', $cms);
        $this->assertStringContainsString('webu_svc_services_list_01', $cms);
        $this->assertStringContainsString('webu_book_booking_form_01', $cms);
        $this->assertStringContainsString('data-webby-booking-slots', $cms);
        $this->assertStringContainsString('data-webby-booking-finance', $cms);
        $this->assertStringContainsString('syntheticBookingSectionKeySet', $cms);
        $this->assertStringContainsString('MODULE_BOOKING_TEAM_SCHEDULING', $cms);
        $this->assertStringContainsString('MODULE_BOOKING_FINANCE', $cms);
        $this->assertStringContainsString('builderSectionAvailabilityMatrix', $cms);
        $this->assertStringContainsString("key: 'booking'", $cms);
        $this->assertStringContainsString("key: 'booking_scheduling'", $cms);
        $this->assertStringContainsString("key: 'booking_finance'", $cms);
        $this->assertStringContainsString('createSyntheticBookingPlaceholder', $cms);
        $this->assertStringContainsString('applyBookingPreviewState', $cms);

        $this->assertStringContainsString("'booking' => [", $featureFlags);
    }
}
