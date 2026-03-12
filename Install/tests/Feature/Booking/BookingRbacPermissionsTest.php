<?php

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingAuthorizationServiceContract;
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

class BookingRbacPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_receptionist_role_can_handle_booking_lifecycle_but_cannot_manage_configuration(): void
    {
        $owner = User::factory()->create();
        $receptionist = User::factory()->create();
        [$project, $site] = $this->createPublishedProjectWithSite($owner);
        $project->sharedWith()->syncWithoutDetaching([$receptionist->id => ['permission' => 'edit']]);
        $this->assignRole($site, $receptionist, 'receptionist', $owner);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Consultation',
            'slug' => 'consultation',
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'max_parallel_bookings' => 3,
            'requires_staff' => true,
            'price' => '80.00',
            'currency' => 'GEL',
        ]);

        $staff = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Dr Nino',
            'slug' => 'dr-nino',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
        ]);

        $bookingResponse = $this->actingAs($receptionist)
            ->postJson(route('panel.sites.booking.bookings.store', ['site' => $site->id]), [
                'service_id' => $service->id,
                'staff_resource_id' => $staff->id,
                'starts_at' => '2026-08-10 10:00:00',
                'duration_minutes' => 60,
                'customer_name' => 'Customer A',
                'customer_email' => 'customer@example.com',
                'source' => 'panel',
            ])
            ->assertCreated()
            ->assertJsonPath('booking.status', Booking::STATUS_PENDING);

        $bookingId = (int) $bookingResponse->json('booking.id');
        $this->assertGreaterThan(0, $bookingId);

        $this->actingAs($receptionist)
            ->postJson(route('panel.sites.booking.bookings.status', ['site' => $site->id, 'booking' => $bookingId]), [
                'status' => Booking::STATUS_CONFIRMED,
            ])
            ->assertOk()
            ->assertJsonPath('booking.status', Booking::STATUS_CONFIRMED);

        $this->actingAs($receptionist)
            ->postJson(route('panel.sites.booking.bookings.reschedule', ['site' => $site->id, 'booking' => $bookingId]), [
                'starts_at' => '2026-08-10 12:00:00',
                'duration_minutes' => 60,
            ])
            ->assertOk()
            ->assertJsonPath('booking.id', $bookingId);

        $this->actingAs($receptionist)
            ->postJson(route('panel.sites.booking.bookings.cancel', ['site' => $site->id, 'booking' => $bookingId]), [
                'reason' => 'Customer cancelled',
            ])
            ->assertOk()
            ->assertJsonPath('booking.status', Booking::STATUS_CANCELLED);

        $this->actingAs($receptionist)
            ->postJson(route('panel.sites.booking.services.store', ['site' => $site->id]), [
                'name' => 'Blocked Service',
                'slug' => 'blocked-service',
                'status' => BookingService::STATUS_ACTIVE,
                'duration_minutes' => 30,
                'max_parallel_bookings' => 1,
                'requires_staff' => false,
                'price' => '40.00',
                'currency' => 'GEL',
            ])
            ->assertForbidden();

        $this->actingAs($receptionist)
            ->postJson(route('panel.sites.booking.staff.store', ['site' => $site->id]), [
                'name' => 'Blocked Staff',
                'slug' => 'blocked-staff',
                'type' => BookingStaffResource::TYPE_STAFF,
                'status' => BookingStaffResource::STATUS_ACTIVE,
                'timezone' => 'Asia/Tbilisi',
                'max_parallel_bookings' => 1,
            ])
            ->assertForbidden();

        $this->actingAs($receptionist)
            ->putJson(route('panel.sites.booking.staff.work-schedules.sync', [
                'site' => $site->id,
                'staffResource' => $staff->id,
            ]), [
                'schedules' => [
                    [
                        'day_of_week' => 1,
                        'start_time' => '10:00',
                        'end_time' => '15:00',
                        'is_available' => true,
                    ],
                ],
            ])
            ->assertForbidden();

        $this->actingAs($receptionist)
            ->postJson(route('panel.sites.booking.staff.time-off.store', [
                'site' => $site->id,
                'staffResource' => $staff->id,
            ]), [
                'starts_at' => '2026-08-12 10:00:00',
                'ends_at' => '2026-08-12 11:00:00',
                'status' => 'approved',
            ])
            ->assertForbidden();
    }

    public function test_staff_role_is_read_only_even_with_edit_project_share_permission(): void
    {
        $owner = User::factory()->create();
        $staffUser = User::factory()->create();
        [$project, $site] = $this->createPublishedProjectWithSite($owner);
        $project->sharedWith()->syncWithoutDetaching([$staffUser->id => ['permission' => 'edit']]);
        $this->assignRole($site, $staffUser, 'staff', $owner);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Diagnostics',
            'slug' => 'diagnostics',
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 45,
            'max_parallel_bookings' => 2,
            'requires_staff' => true,
            'price' => '55.00',
            'currency' => 'GEL',
        ]);

        $staff = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Doctor Staff',
            'slug' => 'doctor-staff',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
        ]);

        $booking = Booking::query()->create([
            'site_id' => $site->id,
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'booking_number' => 'BKG-RBAC-1001',
            'status' => Booking::STATUS_PENDING,
            'source' => 'panel',
            'starts_at' => '2026-08-20 10:00:00',
            'ends_at' => '2026-08-20 10:45:00',
            'collision_starts_at' => '2026-08-20 10:00:00',
            'collision_ends_at' => '2026-08-20 10:45:00',
            'duration_minutes' => 45,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'timezone' => 'Asia/Tbilisi',
            'service_fee' => '55.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '55.00',
            'paid_total' => '0.00',
            'outstanding_total' => '55.00',
            'currency' => 'GEL',
            'customer_name' => 'Read Only',
            'customer_email' => 'readonly@example.com',
        ]);

        $bookingsResponse = $this->actingAs($staffUser)
            ->getJson(route('panel.sites.booking.bookings.index', ['site' => $site->id]))
            ->assertOk();
        $this->assertContains($booking->id, array_column($bookingsResponse->json('bookings') ?? [], 'id'));

        $this->actingAs($staffUser)
            ->getJson(route('panel.sites.booking.calendar', [
                'site' => $site->id,
                'from' => '2026-08-01',
                'to' => '2026-08-31',
            ]))
            ->assertOk();

        $this->actingAs($staffUser)
            ->getJson(route('panel.sites.booking.bookings.show', ['site' => $site->id, 'booking' => $booking->id]))
            ->assertOk()
            ->assertJsonPath('booking.id', $booking->id);

        $this->actingAs($staffUser)
            ->postJson(route('panel.sites.booking.bookings.store', ['site' => $site->id]), [
                'service_id' => $service->id,
                'staff_resource_id' => $staff->id,
                'starts_at' => '2026-08-20 12:00:00',
                'duration_minutes' => 45,
            ])
            ->assertForbidden();

        $this->actingAs($staffUser)
            ->postJson(route('panel.sites.booking.bookings.status', ['site' => $site->id, 'booking' => $booking->id]), [
                'status' => Booking::STATUS_CONFIRMED,
            ])
            ->assertForbidden();

        $this->actingAs($staffUser)
            ->postJson(route('panel.sites.booking.bookings.reschedule', ['site' => $site->id, 'booking' => $booking->id]), [
                'starts_at' => '2026-08-20 12:30:00',
                'duration_minutes' => 45,
            ])
            ->assertForbidden();

        $this->actingAs($staffUser)
            ->postJson(route('panel.sites.booking.bookings.cancel', ['site' => $site->id, 'booking' => $booking->id]), [
                'reason' => 'Not allowed',
            ])
            ->assertForbidden();
    }

    public function test_manager_role_can_manage_services_staff_and_team_scheduling(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();
        [$project, $site] = $this->createPublishedProjectWithSite($owner);
        $project->sharedWith()->syncWithoutDetaching([$manager->id => ['permission' => 'edit']]);
        $this->assignRole($site, $manager, 'manager', $owner);

        $serviceResponse = $this->actingAs($manager)
            ->postJson(route('panel.sites.booking.services.store', ['site' => $site->id]), [
                'name' => 'Manager Service',
                'slug' => 'manager-service',
                'status' => BookingService::STATUS_ACTIVE,
                'duration_minutes' => 50,
                'max_parallel_bookings' => 2,
                'requires_staff' => true,
                'price' => '60.00',
                'currency' => 'GEL',
            ])
            ->assertCreated();

        $staffResponse = $this->actingAs($manager)
            ->postJson(route('panel.sites.booking.staff.store', ['site' => $site->id]), [
                'name' => 'Manager Staff',
                'slug' => 'manager-staff',
                'type' => BookingStaffResource::TYPE_STAFF,
                'status' => BookingStaffResource::STATUS_ACTIVE,
                'timezone' => 'Asia/Tbilisi',
                'max_parallel_bookings' => 1,
            ])
            ->assertCreated();

        $staffId = (int) $staffResponse->json('staff_resource.id');
        $this->assertGreaterThan(0, $staffId);

        $this->actingAs($manager)
            ->putJson(route('panel.sites.booking.staff.work-schedules.sync', [
                'site' => $site->id,
                'staffResource' => $staffId,
            ]), [
                'schedules' => [
                    [
                        'day_of_week' => 1,
                        'start_time' => '09:00',
                        'end_time' => '17:00',
                        'is_available' => true,
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('schedules.0.staff_resource_id', $staffId);

        $this->actingAs($manager)
            ->postJson(route('panel.sites.booking.staff.time-off.store', [
                'site' => $site->id,
                'staffResource' => $staffId,
            ]), [
                'starts_at' => '2026-08-22 10:00:00',
                'ends_at' => '2026-08-22 11:00:00',
                'status' => 'approved',
            ])
            ->assertCreated()
            ->assertJsonPath('time_off.staff_resource_id', $staffId);

        $this->assertGreaterThan(0, (int) $serviceResponse->json('service.id'));
    }

    /**
     * @return array{0:Project,1:Site}
     */
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

    private function assignRole(Site $site, User $user, string $roleKey, User $actor): void
    {
        app(BookingAuthorizationServiceContract::class)->assignRole($site, $user, $roleKey, $actor);
    }
}
