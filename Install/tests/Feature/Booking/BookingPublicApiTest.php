<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\BookingStaffResource;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\BookingConfirmationNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingPublicApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_public_booking_services_slots_and_create_flow_is_visible_in_panel(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);
        Notification::fake();

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
            'max_parallel_bookings' => 10,
            'requires_staff' => true,
            'price' => '80.00',
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
        ]);

        $this->getJson(route('public.sites.booking.services', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('site_id', $site->id)
            ->assertJsonPath('services.0.id', $service->id);

        $slotsResponse = $this->getJson(route('public.sites.booking.slots', [
            'site' => $site->id,
            'service_id' => $service->id,
            'date' => '2026-04-05',
            'staff_resource_id' => $staff->id,
            'timezone' => 'Asia/Tbilisi',
        ]))
            ->assertOk()
            ->assertJsonPath('service.id', $service->id)
            ->assertJsonPath('slots.0.staff_resource.id', $staff->id);

        $startsAt = (string) $slotsResponse->json('slots.0.starts_at');
        $endsAt = (string) $slotsResponse->json('slots.0.ends_at');

        $createResponse = $this->postJson(route('public.sites.booking.bookings.store', ['site' => $site->id]), [
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => 'Asia/Tbilisi',
            'customer_name' => 'Customer One',
            'customer_email' => 'customer@example.com',
            'customer_notes' => 'Please call before visit.',
        ])
            ->assertCreated()
            ->assertJsonPath('site_id', $site->id)
            ->assertJsonPath('booking.service.id', $service->id)
            ->assertJsonPath('booking.staff_resource.id', $staff->id)
            ->assertJsonPath('booking.status', 'pending')
            ->assertJsonPath('booking.source', 'public_widget');

        $bookingId = (int) $createResponse->json('booking.id');
        $this->assertGreaterThan(0, $bookingId);

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'site_id' => $site->id,
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'status' => 'pending',
            'source' => 'public_widget',
            'customer_email' => 'customer@example.com',
        ]);

        Notification::assertSentOnDemand(BookingConfirmationNotification::class, function (
            BookingConfirmationNotification $notification,
            array $channels,
            object $notifiable
        ) use ($bookingId): bool {
            $route = $notifiable->routeNotificationFor('mail');
            $emails = is_array($route) ? $route : [$route];

            return in_array('mail', $channels, true)
                && (int) $notification->booking->id === $bookingId
                && in_array('customer@example.com', $emails, true);
        });

        $indexResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.bookings.index', ['site' => $site->id]))
            ->assertOk();
        $this->assertContains($bookingId, array_column($indexResponse->json('bookings') ?? [], 'id'));
    }

    public function test_private_booking_storefront_is_hidden_for_guest_but_visible_for_owner(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true, true);

        BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Private Service',
            'slug' => 'private-service',
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 45,
            'max_parallel_bookings' => 1,
            'requires_staff' => false,
            'price' => '50.00',
            'currency' => 'GEL',
        ]);

        $this->getJson(route('public.sites.booking.services', ['site' => $site->id]))
            ->assertNotFound();

        $servicesResponse = $this->actingAs($owner)
            ->getJson(route('public.sites.booking.services', ['site' => $site->id]))
            ->assertOk();
        $services = $servicesResponse->json('services') ?? [];
        $this->assertGreaterThanOrEqual(1, count($services));
    }

    public function test_booking_storefront_returns_not_found_when_module_is_disabled(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, false);

        BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Disabled Module Service',
            'slug' => 'disabled-module-service',
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 30,
            'max_parallel_bookings' => 1,
            'requires_staff' => false,
            'price' => '30.00',
            'currency' => 'GEL',
        ]);

        $this->getJson(route('public.sites.booking.services', ['site' => $site->id]))
            ->assertNotFound();
    }

    public function test_panel_booking_endpoints_are_blocked_when_plan_disables_booking(): void
    {
        $plan = Plan::factory()->withBooking(false)->create();
        $owner = User::factory()->withPlan($plan)->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.services.index', ['site' => $site->id]))
            ->assertForbidden()
            ->assertJsonPath('code', 'site_entitlement_required')
            ->assertJsonPath('feature', 'booking');
    }

    public function test_public_booking_create_blocks_colliding_slots(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Collision Test Service',
            'slug' => 'collision-test-service',
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'max_parallel_bookings' => 10,
            'requires_staff' => true,
            'price' => '75.00',
            'currency' => 'GEL',
        ]);

        $staff = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Doctor',
            'slug' => 'doctor',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
        ]);

        $this->postJson(route('public.sites.booking.bookings.store', ['site' => $site->id]), [
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'starts_at' => '2026-04-10 10:00:00',
            'duration_minutes' => 60,
            'timezone' => 'Asia/Tbilisi',
            'customer_email' => 'a@example.com',
        ])->assertCreated();

        $this->postJson(route('public.sites.booking.bookings.store', ['site' => $site->id]), [
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'starts_at' => '2026-04-10 10:30:00',
            'duration_minutes' => 30,
            'timezone' => 'Asia/Tbilisi',
            'customer_email' => 'b@example.com',
        ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'slot_collision')
            ->assertJsonPath('scope', 'staff_resource');

        $this->assertGreaterThanOrEqual(1, Booking::query()->where('site_id', $site->id)->count());
    }

    public function test_prepayment_is_rejected_when_plan_flag_is_disabled(): void
    {
        $plan = Plan::factory()->create([
            'enable_booking_prepayment' => false,
        ]);
        $owner = User::factory()->withPlan($plan)->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Paid Consultation',
            'slug' => 'paid-consultation',
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'max_parallel_bookings' => 1,
            'requires_staff' => false,
            'allow_online_payment' => true,
            'price' => '80.00',
            'currency' => 'GEL',
        ]);

        $this->postJson(route('public.sites.booking.bookings.store', ['site' => $site->id]), [
            'service_id' => $service->id,
            'starts_at' => '2026-04-11 10:00:00',
            'duration_minutes' => 60,
            'timezone' => 'Asia/Tbilisi',
            'customer_email' => 'customer@example.com',
            'prepayment_amount' => 30,
            'prepayment_currency' => 'GEL',
        ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'prepayment_not_enabled');
    }

    public function test_prepayment_is_applied_when_plan_flag_is_enabled(): void
    {
        $plan = Plan::factory()->create([
            'enable_booking_prepayment' => true,
        ]);
        $owner = User::factory()->withPlan($plan)->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Paid Consultation',
            'slug' => 'paid-consultation',
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'max_parallel_bookings' => 1,
            'requires_staff' => false,
            'allow_online_payment' => true,
            'price' => '80.00',
            'currency' => 'GEL',
        ]);

        $response = $this->postJson(route('public.sites.booking.bookings.store', ['site' => $site->id]), [
            'service_id' => $service->id,
            'starts_at' => '2026-04-12 10:00:00',
            'duration_minutes' => 60,
            'timezone' => 'Asia/Tbilisi',
            'customer_email' => 'customer@example.com',
            'prepayment_amount' => 30,
            'prepayment_currency' => 'GEL',
        ])
            ->assertCreated()
            ->assertJsonPath('booking.paid_total', '30.00')
            ->assertJsonPath('booking.outstanding_total', '50.00')
            ->assertJsonPath('booking.prepayment.status', 'paid');

        $bookingId = (int) $response->json('booking.id');

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'site_id' => $site->id,
            'paid_total' => '30.00',
            'outstanding_total' => '50.00',
            'grand_total' => '80.00',
            'currency' => 'GEL',
        ]);
    }

    public function test_monthly_booking_limit_is_enforced_for_public_booking_creation(): void
    {
        $plan = Plan::factory()
            ->withBooking(true)
            ->withMonthlyBookingLimit(1)
            ->create();
        $owner = User::factory()->withPlan($plan)->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, true);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Monthly Limit Service',
            'slug' => 'monthly-limit-service',
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 45,
            'max_parallel_bookings' => 10,
            'requires_staff' => false,
            'price' => '40.00',
            'currency' => 'GEL',
        ]);

        Booking::query()->create([
            'site_id' => $site->id,
            'service_id' => $service->id,
            'booking_number' => 'BKG-LIMIT-001',
            'status' => Booking::STATUS_PENDING,
            'source' => 'panel',
            'customer_email' => 'existing@example.com',
            'starts_at' => now()->addHours(2),
            'ends_at' => now()->addHours(3),
            'collision_starts_at' => now()->addHours(2),
            'collision_ends_at' => now()->addHours(3),
            'duration_minutes' => 60,
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

        $this->postJson(route('public.sites.booking.bookings.store', ['site' => $site->id]), [
            'service_id' => $service->id,
            'starts_at' => now()->addDay()->setTime(10, 0)->toISOString(),
            'duration_minutes' => 45,
            'timezone' => 'Asia/Tbilisi',
            'customer_email' => 'new@example.com',
        ])
            ->assertStatus(422)
            ->assertJsonPath('reason', 'monthly_bookings_limit_reached');
    }

    private function createPublishedProjectWithSite(User $owner, bool $enableBooking, bool $private = false): array
    {
        $project = Project::factory()
            ->for($owner)
            ->when(
                $private,
                fn ($factory) => $factory->privatePublished(strtolower(Str::random(10))),
                fn ($factory) => $factory->published(strtolower(Str::random(10)))
            )
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
