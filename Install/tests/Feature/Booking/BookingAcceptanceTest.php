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
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_booking_lifecycle_from_public_create_to_panel_timeline(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Consultation',
            'slug' => 'consultation',
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'max_parallel_bookings' => 5,
            'requires_staff' => true,
            'price' => '90.00',
            'currency' => 'GEL',
        ]);

        $staff = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Doctor Nino',
            'slug' => 'doctor-nino',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
        ]);

        $createResponse = $this->postJson(route('public.sites.booking.bookings.store', ['site' => $site->id]), [
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'starts_at' => '2026-05-10 10:00:00',
            'duration_minutes' => 60,
            'timezone' => 'Asia/Tbilisi',
            'customer_name' => 'Lika',
            'customer_email' => 'lika@example.com',
            'customer_phone' => '+995555111222',
        ])
            ->assertCreated()
            ->assertJsonPath('booking.status', 'pending');

        $bookingId = (int) $createResponse->json('booking.id');
        $this->assertGreaterThan(0, $bookingId);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.bookings.index', [
                'site' => $site->id,
                'status' => Booking::STATUS_PENDING,
                'search' => 'lika@example.com',
                'date_from' => '2026-05-01',
                'date_to' => '2026-05-31',
                'limit' => 10,
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'bookings')
            ->assertJsonPath('bookings.0.id', $bookingId)
            ->assertJsonPath('bookings.0.customer_email', 'lika@example.com');

        $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.bookings.status', ['site' => $site->id, 'booking' => $bookingId]), [
                'status' => Booking::STATUS_CONFIRMED,
            ])
            ->assertOk()
            ->assertJsonPath('booking.status', Booking::STATUS_CONFIRMED);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.bookings.reschedule', ['site' => $site->id, 'booking' => $bookingId]), [
                'starts_at' => '2026-05-10 12:00:00',
                'duration_minutes' => 60,
                'timezone' => 'Asia/Tbilisi',
            ])
            ->assertOk()
            ->assertJsonPath('booking.starts_at', '2026-05-10T12:00:00.000000Z');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.calendar', [
                'site' => $site->id,
                'from' => '2026-05-01',
                'to' => '2026-05-31',
            ]))
            ->assertOk()
            ->assertJsonPath('events.0.id', $bookingId);

        $detailsResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.bookings.show', ['site' => $site->id, 'booking' => $bookingId]))
            ->assertOk()
            ->assertJsonPath('booking.id', $bookingId)
            ->assertJsonPath('booking.status', Booking::STATUS_CONFIRMED);

        $eventTypes = collect($detailsResponse->json('booking.events'))
            ->pluck('event_type')
            ->values()
            ->all();

        $this->assertContains('created', $eventTypes);
        $this->assertContains('status_updated', $eventTypes);
        $this->assertContains('rescheduled', $eventTypes);
    }

    public function test_booking_collision_failure_path_blocks_second_overlapping_create(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Diagnostics',
            'slug' => 'diagnostics',
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'max_parallel_bookings' => 10,
            'requires_staff' => true,
            'price' => '120.00',
            'currency' => 'GEL',
        ]);

        $staff = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Doctor Giorgi',
            'slug' => 'doctor-giorgi',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
        ]);

        $this->postJson(route('public.sites.booking.bookings.store', ['site' => $site->id]), [
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'starts_at' => '2026-05-12 09:00:00',
            'duration_minutes' => 60,
            'timezone' => 'Asia/Tbilisi',
            'customer_email' => 'first@example.com',
        ])->assertCreated();

        $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.bookings.store', ['site' => $site->id]), [
                'service_id' => $service->id,
                'staff_resource_id' => $staff->id,
                'starts_at' => '2026-05-12 09:30:00',
                'duration_minutes' => 30,
                'timezone' => 'Asia/Tbilisi',
                'customer_email' => 'second@example.com',
            ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'slot_collision')
            ->assertJsonPath('scope', 'staff_resource');

        $this->assertGreaterThanOrEqual(1, Booking::query()->where('site_id', $site->id)->count());
    }

    private function createPublishedProjectWithSite(User $owner, bool $enableBooking): array
    {
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        $settings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $modules = is_array($settings['modules'] ?? null) ? $settings['modules'] : [];
        $modules['booking'] = $enableBooking;
        $settings['modules'] = $modules;

        $site->update([
            'theme_settings' => $settings,
        ]);

        return [$project, $site];
    }
}

