<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @group docs-sync */
class UniversalBookingServicesSchemaBridgeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::clearCache();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_it_builds_read_only_universal_snapshot_for_booking_services_staff_resources_and_scheduling_metadata(): void
    {
        $site = $this->makeSite();
        $site->forceFill([
            'locale' => 'en',
            'theme_settings' => [
                'project_type' => 'service',
            ],
        ])->save();

        $serviceA = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Consultation',
            'slug' => 'consultation',
            'status' => BookingService::STATUS_ACTIVE,
            'description' => 'Initial consultation',
            'duration_minutes' => 60,
            'buffer_before_minutes' => 10,
            'buffer_after_minutes' => 5,
            'slot_step_minutes' => 15,
            'max_parallel_bookings' => 2,
            'requires_staff' => true,
            'allow_online_payment' => true,
            'price' => '90.00',
            'currency' => 'GEL',
            'meta_json' => ['category' => 'medical'],
        ]);

        BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Room Rental',
            'slug' => 'room-rental',
            'status' => BookingService::STATUS_INACTIVE,
            'duration_minutes' => 30,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'slot_step_minutes' => null,
            'max_parallel_bookings' => 1,
            'requires_staff' => false,
            'allow_online_payment' => false,
            'price' => '30.00',
            'currency' => 'GEL',
            'meta_json' => ['category' => 'resource'],
        ]);

        $staff = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Dr Nino',
            'slug' => 'dr-nino',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'email' => 'nino@example.test',
            'phone' => '+995555000111',
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
            'buffer_minutes' => 10,
            'meta_json' => ['specialty' => 'therapy'],
        ]);

        $resource = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Treatment Room A',
            'slug' => 'room-a',
            'type' => BookingStaffResource::TYPE_RESOURCE,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
            'buffer_minutes' => 0,
            'meta_json' => ['floor' => '2'],
        ]);

        BookingStaffWorkSchedule::query()->create([
            'site_id' => $site->id,
            'staff_resource_id' => $staff->id,
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '17:00',
            'is_available' => true,
            'timezone' => 'Asia/Tbilisi',
            'effective_from' => '2026-03-01',
            'effective_to' => '2026-12-31',
            'meta_json' => ['source' => 'panel'],
        ]);

        BookingStaffWorkSchedule::query()->create([
            'site_id' => $site->id,
            'staff_resource_id' => $resource->id,
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '18:00',
            'is_available' => true,
            'timezone' => 'Asia/Tbilisi',
            'meta_json' => ['source' => 'panel'],
        ]);

        BookingStaffTimeOff::query()->create([
            'site_id' => $site->id,
            'staff_resource_id' => $staff->id,
            'starts_at' => '2026-07-14 12:00:00',
            'ends_at' => '2026-07-14 14:00:00',
            'status' => 'approved',
            'reason' => 'Medical leave',
            'meta_json' => ['ticket' => 'TO-1001'],
        ]);

        $countsBefore = $this->tableCounts();
        $themeBefore = $site->fresh()->theme_settings;

        $service = app(UniversalBookingServicesSchemaBridgeService::class);
        $snapshot = $service->snapshot($site->fresh());

        $this->assertSame('universal_booking_services_schema_bridge', data_get($snapshot, 'schema.name'));
        $this->assertSame(1, data_get($snapshot, 'schema.version'));
        $this->assertSame('P5-F3-01', data_get($snapshot, 'schema.task'));
        $this->assertSame((string) $site->id, data_get($snapshot, 'site.id'));
        $this->assertContains('booking_services', data_get($snapshot, 'sources.services'));
        $this->assertContains('booking_staff_resources', data_get($snapshot, 'sources.staff_resources'));
        $this->assertSame(2, data_get($snapshot, 'counts.services'));
        $this->assertSame(2, data_get($snapshot, 'counts.staff_resources'));
        $this->assertSame(2, data_get($snapshot, 'counts.work_schedules'));
        $this->assertSame(1, data_get($snapshot, 'counts.time_off'));

        $consultation = collect(data_get($snapshot, 'catalog.services', []))->firstWhere('slug', 'consultation');
        $this->assertNotNull($consultation);
        $this->assertTrue((bool) data_get($consultation, 'requires_staff'));
        $this->assertTrue((bool) data_get($consultation, 'allow_online_payment'));
        $this->assertSame('90.00', data_get($consultation, 'price'));
        $this->assertSame('medical', data_get($consultation, 'meta_json.category'));

        $staffRow = collect(data_get($snapshot, 'catalog.staff_resources', []))->firstWhere('slug', 'dr-nino');
        $this->assertNotNull($staffRow);
        $this->assertSame('staff', data_get($staffRow, 'type'));
        $this->assertSame(1, data_get($staffRow, 'schedule_count'));
        $this->assertSame(1, data_get($staffRow, 'time_off_count'));
        $this->assertSame('10:00', data_get($staffRow, 'schedules.0.start_time'));
        $this->assertSame('approved', data_get($staffRow, 'time_off.0.status'));

        $resourceRow = collect(data_get($snapshot, 'catalog.staff_resources', []))->firstWhere('slug', 'room-a');
        $this->assertNotNull($resourceRow);
        $this->assertSame('resource', data_get($resourceRow, 'type'));
        $this->assertSame(1, data_get($resourceRow, 'schedule_count'));
        $this->assertSame(0, data_get($resourceRow, 'time_off_count'));

        $apiSurface = data_get($snapshot, 'api_surface.panel.routes', []);
        $this->assertTrue(collect($apiSurface)->contains(fn (array $row): bool => ($row['name'] ?? null) === 'panel.sites.booking.services.index'));
        $this->assertTrue(collect($apiSurface)->contains(fn (array $row): bool => ($row['name'] ?? null) === 'panel.sites.booking.staff.work-schedules.sync'));
        $this->assertSame('App\\Http\\Controllers\\Booking\\PanelServiceController', data_get($snapshot, 'api_surface.panel.controllers.services'));
        $this->assertContains('availability/slots APIs', data_get($snapshot, 'api_surface.deferred_to_next_tasks.P5-F3-02'));

        $redacted = $service->snapshot($site->fresh(), ['include_meta' => false, 'include_api_surface' => false]);
        $redactedService = collect(data_get($redacted, 'catalog.services', []))->firstWhere('slug', 'consultation');
        $this->assertNull(data_get($redactedService, 'meta_json'));
        $this->assertNull(data_get($redacted, 'api_surface'));

        $this->assertSame($countsBefore, $this->tableCounts());
        $this->assertSame($themeBefore, $site->fresh()->theme_settings);

        $this->assertSame($serviceA->id, data_get($consultation, 'id'));
    }

    public function test_architecture_doc_locks_p5_f3_01_booking_services_staff_resources_schema_api_contract(): void
    {
        $path = base_path('docs/architecture/UNIVERSAL_BOOKING_SERVICES_SCHEMA_APIS_P5_F3_01.md');
        $this->assertFileExists($path);

        $doc = File::get($path);
        $routes = File::get(base_path('routes/web.php'));
        $panelServiceController = File::get(base_path('app/Http/Controllers/Booking/PanelServiceController.php'));
        $panelStaffController = File::get(base_path('app/Http/Controllers/Booking/PanelStaffController.php'));

        $this->assertStringContainsString('P5-F3-01', $doc);
        $this->assertStringContainsString('UniversalBookingServicesSchemaBridgeService', $doc);
        $this->assertStringContainsString('PanelServiceController', $doc);
        $this->assertStringContainsString('PanelStaffController', $doc);
        $this->assertStringContainsString('booking_services', $doc);
        $this->assertStringContainsString('booking_staff_resources', $doc);
        $this->assertStringContainsString('booking_staff_work_schedules', $doc);
        $this->assertStringContainsString('booking_staff_time_off', $doc);
        $this->assertStringContainsString('P5-F3-02', $doc);
        $this->assertStringContainsString('P5-F3-03', $doc);

        $this->assertStringContainsString("Route::get('/booking/services'", $routes);
        $this->assertStringContainsString("Route::get('/booking/staff'", $routes);
        $this->assertStringContainsString("Route::get('/booking/staff/{staffResource}/work-schedules'", $routes);
        $this->assertStringContainsString("Route::post('/booking/staff/{staffResource}/time-off'", $routes);

        $this->assertStringContainsString('function index', $panelServiceController);
        $this->assertStringContainsString('function store', $panelServiceController);
        $this->assertStringContainsString('function indexWorkSchedules', $panelStaffController);
        $this->assertStringContainsString('function storeTimeOff', $panelStaffController);
    }

    private function makeSite(): Site
    {
        $project = Project::factory()->create();

        $site = $project->fresh()->site;
        $this->assertInstanceOf(Site::class, $site);

        return $site->fresh();
    }

    /**
     * @return array<string,int>
     */
    private function tableCounts(): array
    {
        return [
            'booking_services' => DB::table('booking_services')->count(),
            'booking_staff_resources' => DB::table('booking_staff_resources')->count(),
            'booking_staff_work_schedules' => DB::table('booking_staff_work_schedules')->count(),
            'booking_staff_time_off' => DB::table('booking_staff_time_off')->count(),
        ];
    }
}
