<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\BookingStaffResource;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingPanelCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_owner_can_manage_booking_services_staff_and_bookings(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $serviceResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.services.store', ['site' => $site->id]), [
                'name' => 'Consultation',
                'slug' => 'consultation',
                'status' => 'active',
                'duration_minutes' => 60,
                'max_parallel_bookings' => 3,
                'requires_staff' => true,
                'price' => '80.00',
                'currency' => 'GEL',
            ])
            ->assertCreated()
            ->assertJsonPath('service.site_id', $site->id);

        $serviceId = (int) $serviceResponse->json('service.id');

        $this->actingAs($owner)
            ->putJson(route('panel.sites.booking.services.update', ['site' => $site->id, 'service' => $serviceId]), [
                'duration_minutes' => 45,
            ])
            ->assertOk()
            ->assertJsonPath('service.duration_minutes', 45);

        $staffResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.staff.store', ['site' => $site->id]), [
                'name' => 'Dr Nino',
                'slug' => 'dr-nino',
                'type' => 'staff',
                'status' => 'active',
                'timezone' => 'Asia/Tbilisi',
                'max_parallel_bookings' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('staff_resource.site_id', $site->id);

        $staffId = (int) $staffResponse->json('staff_resource.id');

        $bookingResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.bookings.store', ['site' => $site->id]), [
                'service_id' => $serviceId,
                'staff_resource_id' => $staffId,
                'starts_at' => '2026-03-20 10:00:00',
                'duration_minutes' => 60,
                'customer_name' => 'Customer A',
                'customer_email' => 'a@example.com',
                'source' => 'panel',
            ])
            ->assertCreated()
            ->assertJsonPath('booking.site_id', $site->id)
            ->assertJsonPath('booking.status', 'pending');

        $bookingId = (int) $bookingResponse->json('booking.id');

        $indexResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.bookings.index', ['site' => $site->id]))
            ->assertOk();
        $bookingIds = array_column($indexResponse->json('bookings') ?? [], 'id');
        $this->assertContains($bookingId, $bookingIds, 'Created booking should appear in bookings index.');

        $calendarResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.calendar', [
                'site' => $site->id,
                'from' => '2026-03-01',
                'to' => '2026-03-31',
            ]))
            ->assertOk();
        $eventIds = array_column($calendarResponse->json('events') ?? [], 'id');
        $this->assertContains($bookingId, $eventIds, 'Created booking should appear in calendar events.');

        $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.bookings.status', ['site' => $site->id, 'booking' => $bookingId]), [
                'status' => 'confirmed',
            ])
            ->assertOk()
            ->assertJsonPath('booking.status', 'confirmed');

        $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.bookings.reschedule', ['site' => $site->id, 'booking' => $bookingId]), [
                'starts_at' => '2026-03-20 12:00:00',
                'duration_minutes' => 60,
            ])
            ->assertOk()
            ->assertJsonPath('booking.starts_at', '2026-03-20T12:00:00.000000Z');

        $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.bookings.cancel', ['site' => $site->id, 'booking' => $bookingId]), [
                'reason' => 'Customer cancelled',
            ])
            ->assertOk()
            ->assertJsonPath('booking.status', 'cancelled');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.bookings.show', ['site' => $site->id, 'booking' => $bookingId]))
            ->assertOk()
            ->assertJsonPath('booking.status', 'cancelled');

        $extraService = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Secondary Service',
            'slug' => 'secondary-service',
            'status' => 'active',
            'duration_minutes' => 30,
            'max_parallel_bookings' => 1,
            'requires_staff' => false,
            'price' => '30.00',
            'currency' => 'GEL',
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('panel.sites.booking.services.destroy', ['site' => $site->id, 'service' => $extraService->id]))
            ->assertOk();

        $extraStaff = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Room A',
            'slug' => 'room-a',
            'type' => 'resource',
            'status' => 'active',
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
        ]);

        $this->actingAs($owner)
            ->deleteJson(route('panel.sites.booking.staff.destroy', ['site' => $site->id, 'staffResource' => $extraStaff->id]))
            ->assertOk();
    }

    public function test_cross_site_resource_access_returns_not_found(): void
    {
        $owner = User::factory()->create();
        [, $siteA] = $this->createPublishedProjectWithSite($owner);
        [, $siteB] = $this->createPublishedProjectWithSite($owner);

        $serviceB = BookingService::query()->create([
            'site_id' => $siteB->id,
            'name' => 'Site B Service',
            'slug' => 'site-b-service',
            'status' => 'active',
            'duration_minutes' => 30,
            'max_parallel_bookings' => 1,
            'requires_staff' => false,
            'price' => '40.00',
            'currency' => 'GEL',
        ]);

        $bookingB = Booking::query()->create([
            'site_id' => $siteB->id,
            'service_id' => $serviceB->id,
            'booking_number' => 'BKG-X-1001',
            'status' => 'pending',
            'source' => 'panel',
            'starts_at' => '2026-03-21 10:00:00',
            'ends_at' => '2026-03-21 10:30:00',
            'collision_starts_at' => '2026-03-21 10:00:00',
            'collision_ends_at' => '2026-03-21 10:30:00',
            'duration_minutes' => 30,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'timezone' => 'Asia/Tbilisi',
            'service_fee' => '40.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '40.00',
            'paid_total' => '0.00',
            'outstanding_total' => '40.00',
            'currency' => 'GEL',
        ]);

        $this->actingAs($owner)
            ->putJson(route('panel.sites.booking.services.update', ['site' => $siteA->id, 'service' => $serviceB->id]), [
                'name' => 'Updated',
            ])
            ->assertNotFound();

        $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.bookings.status', ['site' => $siteA->id, 'booking' => $bookingB->id]), [
                'status' => 'confirmed',
            ])
            ->assertNotFound();
    }

    public function test_other_tenant_user_cannot_access_booking_panel_endpoints(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $this->actingAs($intruder)
            ->getJson(route('panel.sites.booking.services.index', ['site' => $site->id]))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->getJson(route('panel.sites.booking.bookings.index', ['site' => $site->id]))
            ->assertForbidden();
    }

    public function test_platform_admin_can_access_booking_inbox_and_calendar_for_any_project(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create([
            'role' => 'admin',
        ]);
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Consultation',
            'slug' => 'consultation',
            'status' => 'active',
            'duration_minutes' => 60,
            'max_parallel_bookings' => 1,
            'requires_staff' => false,
            'price' => '50.00',
            'currency' => 'GEL',
        ]);

        $booking = Booking::query()->create([
            'site_id' => $site->id,
            'service_id' => $service->id,
            'booking_number' => 'BKG-ADMIN-001',
            'status' => 'pending',
            'source' => 'panel',
            'starts_at' => '2026-03-21 10:00:00',
            'ends_at' => '2026-03-21 11:00:00',
            'collision_starts_at' => '2026-03-21 10:00:00',
            'collision_ends_at' => '2026-03-21 11:00:00',
            'duration_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'timezone' => 'Asia/Tbilisi',
            'service_fee' => '50.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '50.00',
            'paid_total' => '0.00',
            'outstanding_total' => '50.00',
            'currency' => 'GEL',
        ]);

        $bookingsResponse = $this->actingAs($admin)
            ->getJson(route('panel.sites.booking.bookings.index', ['site' => $site->id]))
            ->assertOk();
        $bookings = $bookingsResponse->json('bookings') ?? [];
        $this->assertGreaterThanOrEqual(1, count($bookings));
        $this->assertContains($booking->id, array_column($bookings, 'id'));

        $calendarResponse = $this->actingAs($admin)
            ->getJson(route('panel.sites.booking.calendar', [
                'site' => $site->id,
                'from' => '2026-03-01',
                'to' => '2026-03-31',
            ]))
            ->assertOk();
        $events = $calendarResponse->json('events') ?? [];
        $this->assertGreaterThanOrEqual(1, count($events));
        $this->assertContains($booking->id, array_column($events, 'id'));
    }

    public function test_booking_create_endpoint_blocks_colliding_slot(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Consultation',
            'slug' => 'consultation',
            'status' => 'active',
            'duration_minutes' => 60,
            'max_parallel_bookings' => 10,
            'requires_staff' => true,
            'price' => '80.00',
            'currency' => 'GEL',
        ]);

        $staff = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Doctor',
            'slug' => 'doctor',
            'type' => 'staff',
            'status' => 'active',
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
        ]);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.bookings.store', ['site' => $site->id]), [
                'service_id' => $service->id,
                'staff_resource_id' => $staff->id,
                'starts_at' => '2026-03-22 10:00:00',
                'duration_minutes' => 60,
            ])
            ->assertCreated();

        $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.bookings.store', ['site' => $site->id]), [
                'service_id' => $service->id,
                'staff_resource_id' => $staff->id,
                'starts_at' => '2026-03-22 10:30:00',
                'duration_minutes' => 30,
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'slot_collision')
            ->assertJsonPath('scope', 'staff_resource');
    }

    private function createPublishedProjectWithSite(User $owner): array
    {
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }
}
