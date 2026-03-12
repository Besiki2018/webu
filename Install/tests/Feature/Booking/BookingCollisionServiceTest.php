<?php

namespace Tests\Feature\Booking;

use App\Booking\Contracts\BookingCollisionServiceContract;
use App\Booking\Exceptions\BookingDomainException;
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

class BookingCollisionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_same_staff_slot_collision_is_blocked(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Consultation',
            'slug' => 'consultation-'.Str::random(6),
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'max_parallel_bookings' => 5,
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

        $bookings = $this->bookingService();

        $bookings->createBooking($site, [
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'starts_at' => '2026-03-10 10:00:00',
            'duration_minutes' => 60,
            'customer_name' => 'Client A',
        ], $owner);

        try {
            $bookings->createBooking($site, [
                'service_id' => $service->id,
                'staff_resource_id' => $staff->id,
                'starts_at' => '2026-03-10 10:30:00',
                'duration_minutes' => 30,
                'customer_name' => 'Client B',
            ], $owner);
            $this->fail('Expected BookingDomainException for overlapping staff slot.');
        } catch (BookingDomainException $exception) {
            $this->assertSame(422, $exception->status());
            $this->assertSame('slot_collision', $exception->context()['reason'] ?? null);
            $this->assertSame('staff_resource', $exception->context()['scope'] ?? null);
        }

        $this->assertGreaterThanOrEqual(1, Booking::query()->where('site_id', $site->id)->count(), 'At least our booking should exist; demo seeding may add more.');
    }

    public function test_overlapping_slots_are_allowed_for_different_staff_when_service_capacity_allows(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Pet Grooming',
            'slug' => 'pet-grooming-'.Str::random(6),
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 45,
            'max_parallel_bookings' => 10,
            'requires_staff' => true,
            'price' => '60.00',
            'currency' => 'GEL',
        ]);

        $staffA = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Stylist A',
            'slug' => 'stylist-a',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'max_parallel_bookings' => 1,
        ]);

        $staffB = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Stylist B',
            'slug' => 'stylist-b',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'max_parallel_bookings' => 1,
        ]);

        $bookings = $this->bookingService();

        $first = $bookings->createBooking($site, [
            'service_id' => $service->id,
            'staff_resource_id' => $staffA->id,
            'starts_at' => '2026-03-11 11:00:00',
            'duration_minutes' => 45,
            'customer_name' => 'Client A',
        ], $owner);

        $second = $bookings->createBooking($site, [
            'service_id' => $service->id,
            'staff_resource_id' => $staffB->id,
            'starts_at' => '2026-03-11 11:15:00',
            'duration_minutes' => 45,
            'customer_name' => 'Client B',
        ], $owner);

        $this->assertNotNull($first->id);
        $this->assertNotNull($second->id);
        $this->assertGreaterThanOrEqual(2, Booking::query()->where('site_id', $site->id)->count());
    }

    public function test_same_staff_cannot_take_overlapping_slots_across_different_services(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $serviceA = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Initial Consultation',
            'slug' => 'initial-consultation-'.Str::random(6),
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'max_parallel_bookings' => 10,
            'requires_staff' => true,
            'price' => '90.00',
            'currency' => 'GEL',
        ]);

        $serviceB = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Follow-up Consultation',
            'slug' => 'follow-up-consultation-'.Str::random(6),
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 30,
            'max_parallel_bookings' => 10,
            'requires_staff' => true,
            'price' => '55.00',
            'currency' => 'GEL',
        ]);

        $staff = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Specialist A',
            'slug' => 'specialist-a',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'max_parallel_bookings' => 1,
        ]);

        $bookings = $this->bookingService();

        $bookings->createBooking($site, [
            'service_id' => $serviceA->id,
            'staff_resource_id' => $staff->id,
            'starts_at' => '2026-03-11 10:00:00',
            'duration_minutes' => 60,
            'customer_name' => 'Client A',
        ], $owner);

        try {
            $bookings->createBooking($site, [
                'service_id' => $serviceB->id,
                'staff_resource_id' => $staff->id,
                'starts_at' => '2026-03-11 10:30:00',
                'duration_minutes' => 30,
                'customer_name' => 'Client B',
            ], $owner);
            $this->fail('Expected BookingDomainException for cross-service staff overlap.');
        } catch (BookingDomainException $exception) {
            $this->assertSame(422, $exception->status());
            $this->assertSame('slot_collision', $exception->context()['reason'] ?? null);
            $this->assertSame('staff_resource', $exception->context()['scope'] ?? null);
        }
    }

    public function test_service_parallel_limit_blocks_overlapping_slots_without_staff_assignment(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Studio Rental',
            'slug' => 'studio-rental-'.Str::random(6),
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 120,
            'max_parallel_bookings' => 1,
            'requires_staff' => false,
            'price' => '120.00',
            'currency' => 'GEL',
        ]);

        $bookings = $this->bookingService();

        $bookings->createBooking($site, [
            'service_id' => $service->id,
            'starts_at' => '2026-03-12 09:00:00',
            'duration_minutes' => 120,
            'customer_name' => 'Client A',
        ], $owner);

        try {
            $bookings->createBooking($site, [
                'service_id' => $service->id,
                'starts_at' => '2026-03-12 10:00:00',
                'duration_minutes' => 30,
                'customer_name' => 'Client B',
            ], $owner);
            $this->fail('Expected BookingDomainException for service parallel limit.');
        } catch (BookingDomainException $exception) {
            $this->assertSame(422, $exception->status());
            $this->assertSame('slot_collision', $exception->context()['reason'] ?? null);
            $this->assertSame('service', $exception->context()['scope'] ?? null);
        }

        $this->assertGreaterThanOrEqual(1, Booking::query()->where('site_id', $site->id)->count());
    }

    public function test_cancelled_booking_does_not_block_same_slot(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Nail Service',
            'slug' => 'nail-service-'.Str::random(6),
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'max_parallel_bookings' => 2,
            'requires_staff' => true,
            'price' => '45.00',
            'currency' => 'GEL',
        ]);

        $staff = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Master A',
            'slug' => 'master-a',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'max_parallel_bookings' => 1,
        ]);

        $bookings = $this->bookingService();

        $first = $bookings->createBooking($site, [
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'starts_at' => '2026-03-13 15:00:00',
            'duration_minutes' => 60,
            'customer_name' => 'Client A',
        ], $owner);

        $first->update([
            'status' => Booking::STATUS_CANCELLED,
            'cancelled_at' => now(),
        ]);

        $second = $bookings->createBooking($site, [
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'starts_at' => '2026-03-13 15:00:00',
            'duration_minutes' => 60,
            'customer_name' => 'Client B',
        ], $owner);

        $this->assertNotNull($second->id);
        $this->assertGreaterThanOrEqual(2, Booking::query()->where('site_id', $site->id)->count());
    }

    public function test_reschedule_reuses_collision_guard_and_blocks_overlap(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Consultation Plus',
            'slug' => 'consultation-plus-'.Str::random(6),
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'max_parallel_bookings' => 10,
            'requires_staff' => true,
            'price' => '90.00',
            'currency' => 'GEL',
        ]);

        $staff = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Specialist',
            'slug' => 'specialist',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'max_parallel_bookings' => 1,
        ]);

        $bookings = $this->bookingService();

        $first = $bookings->createBooking($site, [
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'starts_at' => '2026-03-14 10:00:00',
            'duration_minutes' => 60,
            'customer_name' => 'Client A',
        ], $owner);

        $second = $bookings->createBooking($site, [
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'starts_at' => '2026-03-14 12:00:00',
            'duration_minutes' => 60,
            'customer_name' => 'Client B',
        ], $owner);

        $this->assertNotNull($first->id);
        $this->assertNotNull($second->id);

        try {
            $bookings->rescheduleBooking($site, $second, [
                'starts_at' => '2026-03-14 10:30:00',
                'duration_minutes' => 60,
            ], $owner);
            $this->fail('Expected BookingDomainException during conflicting reschedule.');
        } catch (BookingDomainException $exception) {
            $this->assertSame(422, $exception->status());
            $this->assertSame('slot_collision', $exception->context()['reason'] ?? null);
        }

        $reloadedSecond = Booking::query()->findOrFail($second->id);
        $this->assertSame('2026-03-14 12:00:00', $reloadedSecond->starts_at?->format('Y-m-d H:i:s'));
    }

    private function bookingService(): BookingCollisionServiceContract
    {
        return app(BookingCollisionServiceContract::class);
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
