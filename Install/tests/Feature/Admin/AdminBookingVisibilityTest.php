<?php

namespace Tests\Feature\Admin;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingService;
use App\Models\BookingStaffResource;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminBookingVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_admin_can_view_filtered_bookings_with_timeline(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();

        [, $site] = $this->createPublishedProjectWithSite($owner);

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
            'name' => 'Doctor',
            'slug' => 'doctor',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
        ]);

        $pendingBooking = Booking::query()->create([
            'site_id' => $site->id,
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'booking_number' => 'BKG-ADM-1001',
            'status' => Booking::STATUS_PENDING,
            'source' => 'public_widget',
            'starts_at' => '2026-06-01 10:00:00',
            'ends_at' => '2026-06-01 11:00:00',
            'collision_starts_at' => '2026-06-01 10:00:00',
            'collision_ends_at' => '2026-06-01 11:00:00',
            'duration_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'timezone' => 'Asia/Tbilisi',
            'service_fee' => '80.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '80.00',
            'paid_total' => '0.00',
            'outstanding_total' => '80.00',
            'currency' => 'GEL',
            'customer_name' => 'Nino',
            'customer_email' => 'nino@example.com',
        ]);

        BookingEvent::query()->create([
            'site_id' => $site->id,
            'booking_id' => $pendingBooking->id,
            'event_type' => 'created',
            'event_key' => (string) Str::uuid(),
            'payload_json' => ['source' => 'public_widget'],
            'occurred_at' => now()->subMinutes(2),
            'created_by' => $owner->id,
        ]);

        BookingEvent::query()->create([
            'site_id' => $site->id,
            'booking_id' => $pendingBooking->id,
            'event_type' => 'status_updated',
            'event_key' => (string) Str::uuid(),
            'payload_json' => ['old_status' => 'pending', 'new_status' => 'confirmed'],
            'occurred_at' => now()->subMinute(),
            'created_by' => $owner->id,
        ]);

        Booking::query()->create([
            'site_id' => $site->id,
            'service_id' => $service->id,
            'staff_resource_id' => $staff->id,
            'booking_number' => 'BKG-ADM-2002',
            'status' => Booking::STATUS_COMPLETED,
            'source' => 'panel',
            'starts_at' => '2026-06-02 10:00:00',
            'ends_at' => '2026-06-02 11:00:00',
            'collision_starts_at' => '2026-06-02 10:00:00',
            'collision_ends_at' => '2026-06-02 11:00:00',
            'duration_minutes' => 60,
            'buffer_before_minutes' => 0,
            'buffer_after_minutes' => 0,
            'timezone' => 'Asia/Tbilisi',
            'service_fee' => '80.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '80.00',
            'paid_total' => '80.00',
            'outstanding_total' => '0.00',
            'currency' => 'GEL',
            'customer_name' => 'Gio',
            'customer_email' => 'gio@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->withHeaders($this->inertiaHeaders())
            ->get(route('admin.bookings', [
                'status' => Booking::STATUS_PENDING,
                'source' => 'public_widget',
                'search' => 'BKG-ADM-1001',
            ]))
            ->assertOk();

        $this->assertSame('Admin/Bookings', $response->json('component'));
        $this->assertCount(1, $response->json('props.bookings.data'));
        $this->assertSame('BKG-ADM-1001', $response->json('props.bookings.data.0.booking_number'));
        $this->assertSame('status_updated', $response->json('props.bookings.data.0.timeline.0.event_type'));
        $this->assertSame(Booking::STATUS_PENDING, $response->json('props.filters.status'));
        $this->assertSame('public_widget', $response->json('props.filters.source'));
    }

    public function test_non_admin_cannot_access_admin_bookings_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.bookings'))
            ->assertForbidden();
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

    /**
     * @return array<string, string>
     */
    private function inertiaHeaders(): array
    {
        $middleware = app(\App\Http\Middleware\HandleInertiaRequests::class);
        $version = (string) ($middleware->version(Request::create('/')) ?? '');

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => $version,
            'X-Requested-With' => 'XMLHttpRequest',
        ];
    }
}

