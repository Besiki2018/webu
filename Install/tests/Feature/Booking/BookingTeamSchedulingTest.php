<?php

namespace Tests\Feature\Booking;

use App\Models\BookingStaffResource;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingTeamSchedulingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_owner_can_manage_work_hours_and_time_off_and_calendar_reflects_it(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $staff = BookingStaffResource::query()->create([
            'site_id' => $site->id,
            'name' => 'Doctor Nino',
            'slug' => 'doctor-nino',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
        ]);

        $this->actingAs($owner)
            ->putJson(route('panel.sites.booking.staff.work-schedules.sync', [
                'site' => $site->id,
                'staffResource' => $staff->id,
            ]), [
                'schedules' => [
                    [
                        'day_of_week' => 1,
                        'start_time' => '10:00',
                        'end_time' => '16:00',
                        'is_available' => true,
                        'timezone' => 'Asia/Tbilisi',
                    ],
                    [
                        'day_of_week' => 2,
                        'start_time' => '10:00',
                        'end_time' => '16:00',
                        'is_available' => true,
                        'timezone' => 'Asia/Tbilisi',
                    ],
                    [
                        'day_of_week' => 0,
                        'start_time' => '00:00',
                        'end_time' => '00:30',
                        'is_available' => false,
                        'timezone' => 'Asia/Tbilisi',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('schedules.0.staff_resource_id', $staff->id);

        $this->assertDatabaseHas('booking_staff_work_schedules', [
            'site_id' => $site->id,
            'staff_resource_id' => $staff->id,
            'day_of_week' => 1,
            'start_time' => '10:00',
            'end_time' => '16:00',
            'is_available' => 1,
        ]);

        $timeOffResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.staff.time-off.store', [
                'site' => $site->id,
                'staffResource' => $staff->id,
            ]), [
                'starts_at' => '2026-07-14 12:00:00',
                'ends_at' => '2026-07-14 14:00:00',
                'status' => 'approved',
                'reason' => 'Medical leave',
            ])
            ->assertCreated()
            ->assertJsonPath('time_off.staff_resource_id', $staff->id)
            ->assertJsonPath('time_off.status', 'approved');

        $timeOffId = (int) $timeOffResponse->json('time_off.id');
        $this->assertGreaterThan(0, $timeOffId);

        $timeOffIndexResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.staff.time-off.index', [
                'site' => $site->id,
                'staffResource' => $staff->id,
            ]))
            ->assertOk();
        $timeOffList = $timeOffIndexResponse->json('time_off');
        $timeOffList = is_array($timeOffList) ? $timeOffList : [];
        $this->assertContains($timeOffId, array_column($timeOffList, 'id'));

        $calendarResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.calendar', [
                'site' => $site->id,
                'from' => '2026-07-01',
                'to' => '2026-07-31',
            ]))
            ->assertOk();
        $timeOffBlocks = $calendarResponse->json('time_off_blocks') ?? [];
        $staffScheduleBlocks = $calendarResponse->json('staff_schedule_blocks') ?? [];
        $this->assertContains($timeOffId, array_column($timeOffBlocks, 'id'));
        $staffIds = array_column(array_column($staffScheduleBlocks, 'staff_resource'), 'id');
        $this->assertContains($staff->id, $staffIds);

        $this->actingAs($owner)
            ->putJson(route('panel.sites.booking.staff.time-off.update', [
                'site' => $site->id,
                'staffResource' => $staff->id,
                'timeOff' => $timeOffId,
            ]), [
                'status' => 'cancelled',
            ])
            ->assertOk()
            ->assertJsonPath('time_off.status', 'cancelled');

        $this->actingAs($owner)
            ->deleteJson(route('panel.sites.booking.staff.time-off.destroy', [
                'site' => $site->id,
                'staffResource' => $staff->id,
                'timeOff' => $timeOffId,
            ]))
            ->assertOk();

        $this->assertDatabaseMissing('booking_staff_time_off', [
            'id' => $timeOffId,
        ]);
    }

    public function test_cross_site_schedule_and_time_off_access_returns_not_found(): void
    {
        $owner = User::factory()->create();
        [, $siteA] = $this->createPublishedProjectWithSite($owner);
        [, $siteB] = $this->createPublishedProjectWithSite($owner);

        $staffB = BookingStaffResource::query()->create([
            'site_id' => $siteB->id,
            'name' => 'Staff B',
            'slug' => 'staff-b',
            'type' => BookingStaffResource::TYPE_STAFF,
            'status' => BookingStaffResource::STATUS_ACTIVE,
            'timezone' => 'Asia/Tbilisi',
            'max_parallel_bookings' => 1,
        ]);

        $this->actingAs($owner)
            ->getJson(route('panel.sites.booking.staff.work-schedules.index', [
                'site' => $siteA->id,
                'staffResource' => $staffB->id,
            ]))
            ->assertNotFound();

        $this->actingAs($owner)
            ->postJson(route('panel.sites.booking.staff.time-off.store', [
                'site' => $siteA->id,
                'staffResource' => $staffB->id,
            ]), [
                'starts_at' => '2026-07-10 09:00:00',
                'ends_at' => '2026-07-10 10:00:00',
            ])
            ->assertNotFound();
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
}
