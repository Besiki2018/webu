<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @group docs-sync */
class UniversalBookingAvailabilityApiBridgeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::clearCache();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_it_builds_read_only_universal_snapshot_for_availability_blocked_times_slots_and_collision_contract(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $site->forceFill([
            'locale' => 'en',
            'theme_settings' => array_merge((array) ($site->theme_settings ?? []), [
                'project_type' => 'service',
            ]),
        ])->save();

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Consultation',
            'slug' => 'consultation',
            'status' => BookingService::STATUS_ACTIVE,
            'description' => 'General consultation',
            'duration_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'slot_step_minutes' => 30,
            'max_parallel_bookings' => 1,
            'requires_staff' => true,
            'allow_online_payment' => true,
            'price' => '90.00',
            'currency' => 'GEL',
        ]);

        $staff = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Dr. Nino',
            'slug' => 'dr-nino',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
            'buffer_minutes' => 0,
            'meta_json' => ['specialty' => 'therapy'],
        ]);

        BookingStaffWorkSchedule::query()->create([
            'site_id' => $site->id,
            'staff_resource_id' => $staff->id,
            'day_of_week' => 2,
            'start_time' => '09:00',
            'end_time' => '12:00',
            'is_available' => true,
            'timezone' => 'Asia/Tbilisi',
            'meta_json' => ['source' => 'panel'],
        ]);

        BookingStaffWorkSchedule::query()->create([
            'site_id' => $site->id,
            'staff_resource_id' => $staff->id,
            'day_of_week' => 2,
            'start_time' => '12:00',
            'end_time' => '13:00',
            'is_available' => false,
            'timezone' => 'Asia/Tbilisi',
            'meta_json' => ['source' => 'panel'],
        ]);

        BookingStaffTimeOff::query()->create([
            'site_id' => $site->id,
            'staff_resource_id' => $staff->id,
            'starts_at' => '2026-07-14 10:00:00',
            'ends_at' => '2026-07-14 11:00:00',
            'status' => 'approved',
            'reason' => 'Break',
            'meta_json' => ['ticket' => 'TO-2001'],
        ]);

        BookingAvailabilityRule::query()->create([
            'site_id' => $site->id,
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'day_of_week' => 2,
            'start_time' => '18:00',
            'end_time' => '19:00',
            'rule_type' => 'exclude',
            'priority' => 50,
            'effective_from' => '2026-07-01',
            'effective_to' => '2026-07-31',
            'meta_json' => ['note' => 'after-hours blocked'],
        ]);

        Booking::query()->create([
            'site_id' => $site->id,
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'booking_number' => 'BKG-1001',
            'status' => Booking::STATUS_CONFIRMED,
            'source' => 'panel',
            'starts_at' => '2026-07-14 09:00:00',
            'ends_at' => '2026-07-14 10:00:00',
            'collision_starts_at' => '2026-07-14 09:00:00',
            'collision_ends_at' => '2026-07-14 10:00:00',
            'duration_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'timezone' => 'Asia/Tbilisi',
            'service_fee' => '90.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '90.00',
            'paid_total' => '0.00',
            'outstanding_total' => '90.00',
            'currency' => 'GEL',
            'customer_name' => 'Customer One',
            'customer_email' => 'customer@example.test',
            'confirmed_at' => '2026-07-10 09:00:00',
            'meta_json' => ['source' => 'seed'],
        ]);

        $countsBefore = $this->tableCounts();
        $themeBefore = $site->fresh()->theme_settings;

        $bridge = app(UniversalBookingAvailabilityApiBridgeService::class);
        $snapshot = $bridge->snapshot($site->fresh(), [
            'slot_request' => [
                'service_id' => $service->id,
                'date' => '2026-07-14',
                'staff_resource_id' => $staff->id,
                'timezone' => 'Asia/Tbilisi',
            ],
            'calendar_range' => [
                'from' => '2026-07-14',
                'to' => '2026-07-14',
            ],
        ]);

        $this->assertSame('universal_booking_availability_api_bridge', data_get($snapshot, 'schema.name'));
        $this->assertSame(1, data_get($snapshot, 'schema.version'));
        $this->assertSame('P5-F3-02', data_get($snapshot, 'schema.task'));
        $this->assertTrue((bool) data_get($snapshot, 'schema.read_only'));

        $this->assertContains('booking_availability_rules', data_get($snapshot, 'sources.availability_rules'));
        $this->assertContains('booking_staff_work_schedules', data_get($snapshot, 'sources.work_schedules'));
        $this->assertContains('booking_staff_time_off', data_get($snapshot, 'sources.time_off'));
        $this->assertContains('bookings', data_get($snapshot, 'sources.bookings'));

        $this->assertSame(1, data_get($snapshot, 'counts.availability_rules'));
        $this->assertSame(1, data_get($snapshot, 'counts.calendar_events'));
        $this->assertSame(2, data_get($snapshot, 'counts.calendar_staff_schedule_blocks'));
        $this->assertSame(1, data_get($snapshot, 'counts.calendar_time_off_blocks'));
        $this->assertSame(2, data_get($snapshot, 'counts.blocked_times'));

        $this->assertSame($service->id, data_get($snapshot, 'samples.public_slots.service.id'));
        $this->assertSame($staff->id, data_get($snapshot, 'samples.public_slots.sample_slots.0.staff_resource.id'));
        $this->assertSame('2026-07-14', data_get($snapshot, 'samples.panel_calendar.from'));
        $this->assertSame('2026-07-14', data_get($snapshot, 'samples.panel_calendar.to'));
        $this->assertNull(data_get($snapshot, 'samples.public_slots_error'));
        $this->assertNull(data_get($snapshot, 'samples.panel_calendar_error'));

        $this->assertTrue(collect(data_get($snapshot, 'blocked_times', []))
            ->contains(fn (array $row): bool => ($row['kind'] ?? null) === 'time_off'));
        $this->assertTrue(collect(data_get($snapshot, 'blocked_times', []))
            ->contains(fn (array $row): bool => ($row['kind'] ?? null) === 'staff_unavailable_schedule'));

        $rule = data_get($snapshot, 'availability_rules.0');
        $this->assertSame('exclude', data_get($rule, 'rule_type'));
        $this->assertSame($service->id, data_get($rule, 'service.id'));
        $this->assertSame($staff->id, data_get($rule, 'staff_resource.id'));
        $this->assertSame('after-hours blocked', data_get($rule, 'meta_json.note'));

        $this->assertContains('slot_collision', data_get($snapshot, 'collision_contract.reason_codes'));
        $this->assertContains('service', data_get($snapshot, 'collision_contract.scopes'));
        $this->assertContains('staff_resource', data_get($snapshot, 'collision_contract.scopes'));
        $this->assertSame('public.sites.booking.slots', data_get($snapshot, 'api_surface.public.routes.1.name'));
        $this->assertSame('panel.sites.booking.calendar', data_get($snapshot, 'api_surface.panel.routes.0.name'));
        $this->assertContains('optional payment linkage', data_get($snapshot, 'api_surface.deferred_to_next_tasks.P5-F3-03'));

        $redacted = $bridge->snapshot($site->fresh(), [
            'slot_request' => [
                'service_id' => $service->id,
                'date' => '2026-07-14',
                'staff_resource_id' => $staff->id,
            ],
            'calendar_range' => [
                'from' => '2026-07-14',
                'to' => '2026-07-14',
            ],
            'include_meta' => false,
            'include_api_surface' => false,
            'include_samples' => false,
        ]);

        $this->assertNull(data_get($redacted, 'availability_rules.0.meta_json'));
        $this->assertNull(data_get($redacted, 'samples.public_slots'));
        $this->assertNull(data_get($redacted, 'samples.panel_calendar'));
        $this->assertNull(data_get($redacted, 'api_surface'));

        $this->assertSame($countsBefore, $this->tableCounts());
        $this->assertSame($themeBefore, $site->fresh()->theme_settings);
    }

    public function test_architecture_doc_locks_p5_f3_02_availability_slot_api_bridge_contract(): void
    {
        $path = base_path('docs/architecture/UNIVERSAL_BOOKING_AVAILABILITY_SLOT_APIS_P5_F3_02.md');
        $this->assertFileExists($path);

        $doc = File::get($path);
        $routes = File::get(base_path('routes/web.php'));
        $publicController = File::get(base_path('app/Http/Controllers/Booking/PublicBookingController.php'));
        $panelController = File::get(base_path('app/Http/Controllers/Booking/PanelBookingController.php'));
        $publicService = File::get(base_path('app/Booking/Services/BookingPublicService.php'));
        $panelService = File::get(base_path('app/Booking/Services/BookingPanelService.php'));
        $collisionService = File::get(base_path('app/Booking/Services/BookingCollisionService.php'));

        $this->assertStringContainsString('P5-F3-02', $doc);
        $this->assertStringContainsString('UniversalBookingAvailabilityApiBridgeService', $doc);
        $this->assertStringContainsString('BookingPublicService', $doc);
        $this->assertStringContainsString('BookingPanelService', $doc);
        $this->assertStringContainsString('BookingCollisionService', $doc);
        $this->assertStringContainsString('booking_availability_rules', $doc);
        $this->assertStringContainsString('public.sites.booking.slots', $doc);
        $this->assertStringContainsString('panel.sites.booking.calendar', $doc);
        $this->assertStringContainsString('P5-F3-03', $doc);
        $this->assertStringContainsString('P5-F3-04', $doc);

        $this->assertStringContainsString("Route::get('/{site}/booking/slots'", $routes);
        $this->assertStringContainsString("Route::get('/booking/calendar'", $routes);
        $this->assertStringContainsString("Route::get('/booking/calendar/advanced'", $routes);
        $this->assertStringContainsString("Route::post('/booking/bookings/{booking}/reschedule'", $routes);

        $this->assertStringContainsString('function slots', $publicController);
        $this->assertStringContainsString('function createBooking', $publicController);
        $this->assertStringContainsString('function calendar', $panelController);
        $this->assertStringContainsString('function reschedule', $panelController);

        $this->assertStringContainsString('function slots', $publicService);
        $this->assertStringContainsString('function calendar', $panelService);
        $this->assertStringContainsString('assertNoCollision', $collisionService);
    }

    /**
     * @return array{0:Project,1:Site}
     */
    private function createPublishedProjectWithSite(User $owner, bool $enableBooking): array
    {
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        $settings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $moduleSettings = is_array($settings['modules'] ?? null) ? $settings['modules'] : [];
        $moduleSettings['booking'] = $enableBooking;
        $settings['modules'] = $moduleSettings;
        $site->update(['theme_settings' => $settings]);

        return [$project, $site->fresh()];
    }

    /**
     * @return array<string, int>
     */
    private function tableCounts(): array
    {
        return [
            'booking_availability_rules' => DB::table('booking_availability_rules')->count(),
            'booking_staff_work_schedules' => DB::table('booking_staff_work_schedules')->count(),
            'booking_staff_time_off' => DB::table('booking_staff_time_off')->count(),
            'bookings' => DB::table('bookings')->count(),
        ];
    }
}
