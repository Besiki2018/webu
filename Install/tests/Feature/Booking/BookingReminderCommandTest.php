<?php

namespace Tests\Feature\Booking;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\BookingService;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Notifications\BookingReminderNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class BookingReminderCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_booking_reminder_command_sends_notification_once_per_booking_window(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Clinic Consultation',
            'slug' => 'clinic-consultation',
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'max_parallel_bookings' => 1,
            'requires_staff' => false,
            'price' => '80.00',
            'currency' => 'GEL',
        ]);

        $startsAt = now()->addMinutes(122)->startOfMinute();

        $booking = Booking::query()->create([
            'site_id' => $site->id,
            'service_id' => $service->id,
            'booking_number' => 'BKG-REM-1001',
            'status' => Booking::STATUS_PENDING,
            'source' => 'public_widget',
            'customer_name' => 'Customer Reminder',
            'customer_email' => 'customer@example.com',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addMinutes(60),
            'collision_starts_at' => $startsAt,
            'collision_ends_at' => $startsAt->copy()->addMinutes(60),
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
        ]);

        Notification::fake();

        $this->artisan('bookings:send-reminders', [
            '--minutes-before' => 120,
            '--window-minutes' => 10,
        ])->assertSuccessful();

        Notification::assertSentOnDemand(BookingReminderNotification::class, function (
            BookingReminderNotification $notification,
            array $channels,
            object $notifiable
        ) use ($booking): bool {
            $route = $notifiable->routeNotificationFor('mail');
            $emails = is_array($route) ? $route : [$route];

            return in_array('mail', $channels, true)
                && (int) $notification->booking->id === (int) $booking->id
                && in_array('customer@example.com', $emails, true)
                && (int) $notification->minutesBeforeStart === 120;
        });

        $this->artisan('bookings:send-reminders', [
            '--minutes-before' => 120,
            '--window-minutes' => 10,
        ])->assertSuccessful();

        Notification::assertSentOnDemandTimes(BookingReminderNotification::class, 1);

        $this->assertSame(1, BookingEvent::query()
            ->where('site_id', $site->id)
            ->where('booking_id', $booking->id)
            ->where('event_type', 'notification_reminder_sent')
            ->count());
    }

    public function test_booking_reminder_command_skips_missing_email_and_outside_window_bookings(): void
    {
        $owner = User::factory()->create();
        [, $site] = $this->createPublishedProjectWithSite($owner);

        $service = BookingService::query()->create([
            'site_id' => $site->id,
            'name' => 'Therapy Session',
            'slug' => 'therapy-session',
            'status' => BookingService::STATUS_ACTIVE,
            'duration_minutes' => 60,
            'max_parallel_bookings' => 1,
            'requires_staff' => false,
            'price' => '90.00',
            'currency' => 'GEL',
        ]);

        $insideWindow = now()->addMinutes(123)->startOfMinute();
        $outsideWindow = now()->addMinutes(260)->startOfMinute();

        Booking::query()->create([
            'site_id' => $site->id,
            'service_id' => $service->id,
            'booking_number' => 'BKG-REM-2001',
            'status' => Booking::STATUS_PENDING,
            'source' => 'public_widget',
            'customer_name' => 'No Email Customer',
            'customer_email' => null,
            'starts_at' => $insideWindow,
            'ends_at' => $insideWindow->copy()->addMinutes(60),
            'collision_starts_at' => $insideWindow,
            'collision_ends_at' => $insideWindow->copy()->addMinutes(60),
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
        ]);

        Booking::query()->create([
            'site_id' => $site->id,
            'service_id' => $service->id,
            'booking_number' => 'BKG-REM-2002',
            'status' => Booking::STATUS_PENDING,
            'source' => 'public_widget',
            'customer_name' => 'Outside Window',
            'customer_email' => 'outside@example.com',
            'starts_at' => $outsideWindow,
            'ends_at' => $outsideWindow->copy()->addMinutes(60),
            'collision_starts_at' => $outsideWindow,
            'collision_ends_at' => $outsideWindow->copy()->addMinutes(60),
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
        ]);

        Notification::fake();

        $this->artisan('bookings:send-reminders', [
            '--minutes-before' => 120,
            '--window-minutes' => 10,
        ])->assertSuccessful();

        Notification::assertSentOnDemandTimes(BookingReminderNotification::class, 0);

        $this->assertSame(0, BookingEvent::query()
            ->where('site_id', $site->id)
            ->where('event_type', 'notification_reminder_sent')
            ->count());
    }

    /**
     * @return array{0: Project, 1: Site}
     */
    private function createPublishedProjectWithSite(User $owner): array
    {
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        $settings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $moduleSettings = is_array($settings['modules'] ?? null) ? $settings['modules'] : [];
        $moduleSettings['booking'] = true;
        $settings['modules'] = $moduleSettings;
        $site->update(['theme_settings' => $settings]);

        return [$project, $site];
    }
}
