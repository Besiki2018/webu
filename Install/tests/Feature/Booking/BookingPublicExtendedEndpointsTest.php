<?php

namespace Tests\Feature\Booking;

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

class BookingPublicExtendedEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_public_booking_extended_endpoints_expose_service_staff_calendar_and_customer_manage_flow(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);
        Notification::fake();

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Therapy Session',
            'slug' => 'therapy-session',
            'status' => BookingService::STATUS_ACTIVE,
            'description' => 'Session',
            'duration_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'slot_step_minutes' => 30,
            'max_parallel_bookings' => 10,
            'requires_staff' => false,
            'price' => '90.00',
            'currency' => 'GEL',
        ]);

        $staff = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Dr. Eka',
            'slug' => 'dr-eka',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
        ]);

        $this->getJson(route('public.sites.booking.services.show', ['site' => $site->id, 'slug' => $service->slug]))
            ->assertOk()
            ->assertJsonPath('service.id', $service->id)
            ->assertJsonPath('service.slug', $service->slug);

        $this->getJson(route('public.sites.booking.staff', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('staff.0.id', $staff->id)
            ->assertJsonPath('staff.0.slug', $staff->slug);

        $createResponse = $this->postJson(route('public.sites.booking.bookings.store', ['site' => $site->id]), [
            'service_id' => $service->id,
            'starts_at' => '2026-04-15 10:00:00',
            'duration_minutes' => 60,
            'timezone' => 'Asia/Tbilisi',
            'customer_name' => 'Customer One',
            'customer_email' => 'customer@example.com',
        ])->assertCreated();

        $bookingId = (int) $createResponse->json('booking.id');

        $this->getJson(route('public.sites.booking.calendar', [
            'site' => $site->id,
            'from' => '2026-04-01',
            'to' => '2026-04-30',
        ]))
            ->assertOk()
            ->assertJsonPath('events.0.id', $bookingId);

        $customerUser = User::factory()->create([
            'email' => 'customer@example.com',
        ]);

        $this->actingAs($customerUser)
            ->getJson(route('public.sites.booking.bookings.my', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('bookings.0.id', $bookingId);

        $this->actingAs($customerUser)
            ->getJson(route('public.sites.booking.bookings.show', ['site' => $site->id, 'booking' => $bookingId]))
            ->assertOk()
            ->assertJsonPath('booking.id', $bookingId);

        $this->actingAs($customerUser)
            ->putJson(route('public.sites.booking.bookings.update', ['site' => $site->id, 'booking' => $bookingId]), [
                'action' => 'reschedule',
                'starts_at' => '2026-04-15 12:00:00',
                'duration_minutes' => 60,
                'timezone' => 'Asia/Tbilisi',
            ])
            ->assertOk()
            ->assertJsonPath('booking.id', $bookingId)
            ->assertJsonPath('booking.status', 'pending');

        $this->actingAs($customerUser)
            ->putJson(route('public.sites.booking.bookings.update', ['site' => $site->id, 'booking' => $bookingId]), [
                'action' => 'cancel',
                'reason' => 'Cannot attend',
            ])
            ->assertOk()
            ->assertJsonPath('booking.id', $bookingId)
            ->assertJsonPath('booking.status', 'cancelled');

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'site_id' => $site->id,
            'status' => 'cancelled',
            'customer_email' => 'customer@example.com',
        ]);
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
        $moduleSettings = is_array($settings['modules'] ?? null) ? $settings['modules'] : [];
        $moduleSettings['booking'] = $enableBooking;
        $settings['modules'] = $moduleSettings;
        $site->update(['theme_settings' => $settings]);

        return [$project, $site];
    }
}
