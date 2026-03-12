<?php

namespace App\Console\Commands;

use App\Booking\Services\BookingNotificationService;
use App\Models\Booking;
use App\Models\CronLog;
use App\Models\OperationLog;
use App\Services\OperationLogService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendBookingReminders extends Command
{
    protected $signature = 'bookings:send-reminders
                            {--minutes-before= : Override reminder offset in minutes}
                            {--window-minutes= : Override dispatch window size in minutes}
                            {--limit= : Override max bookings processed per run}
                            {--triggered-by=cron : Who triggered this command (cron or manual:user_id)}';

    protected $description = 'Send booking reminder emails for upcoming bookings';

    public function __construct(
        protected BookingNotificationService $notifications,
        protected OperationLogService $operationLogs
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $cronLog = CronLog::startLog(
            'Send Booking Reminders',
            self::class,
            (string) $this->option('triggered-by')
        );

        $minutesBefore = $this->resolvePositiveInt(
            $this->option('minutes-before'),
            (int) config('booking.reminders.minutes_before_start', 120)
        );
        $windowMinutes = $this->resolvePositiveInt(
            $this->option('window-minutes'),
            (int) config('booking.reminders.dispatch_window_minutes', 10)
        );
        $limit = max(1, min(
            $this->resolvePositiveInt($this->option('limit'), (int) config('booking.reminders.batch_limit', 500)),
            5000
        ));

        $windowStart = now()->addMinutes($minutesBefore);
        $windowEnd = $windowStart->copy()->addMinutes($windowMinutes);

        try {
            $bookings = Booking::query()
                ->whereIn('status', [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED])
                ->whereNotNull('customer_email')
                ->where('starts_at', '>=', $windowStart)
                ->where('starts_at', '<=', $windowEnd)
                ->with(['site.project', 'service', 'staffResource'])
                ->orderBy('starts_at')
                ->limit($limit)
                ->get();

            $this->info(sprintf(
                'Processing %d booking reminders (window: %s -> %s, offset: %d min).',
                $bookings->count(),
                $windowStart->toDateTimeString(),
                $windowEnd->toDateTimeString(),
                $minutesBefore
            ));

            $sent = 0;
            $skipped = 0;
            $reasons = [];

            foreach ($bookings as $booking) {
                $result = $this->notifications->sendReminder($booking, $minutesBefore);
                $reason = (string) ($result['reason'] ?? 'unknown');

                if ((bool) ($result['sent'] ?? false)) {
                    $sent++;
                    $this->line("  sent: {$booking->booking_number}");

                    continue;
                }

                $skipped++;
                $reasons[$reason] = (int) ($reasons[$reason] ?? 0) + 1;
                $this->line("  skipped: {$booking->booking_number} ({$reason})");
            }

            $message = sprintf(
                'Targeted: %d, Sent: %d, Skipped: %d',
                $bookings->count(),
                $sent,
                $skipped
            );

            if ($reasons !== []) {
                $message .= ', Reasons: '.json_encode($reasons);
            }

            $cronLog->markSuccess($message);

            Log::info('Booking reminders command completed', [
                'targeted' => $bookings->count(),
                'sent' => $sent,
                'skipped' => $skipped,
                'minutes_before' => $minutesBefore,
                'window_minutes' => $windowMinutes,
                'limit' => $limit,
                'reasons' => $reasons,
            ]);

            $this->operationLogs->log(
                channel: OperationLog::CHANNEL_BOOKING,
                event: 'booking_reminder_command_completed',
                status: OperationLog::STATUS_INFO,
                message: $message,
                attributes: [
                    'source' => self::class,
                    'context' => [
                        'targeted' => $bookings->count(),
                        'sent' => $sent,
                        'skipped' => $skipped,
                        'minutes_before' => $minutesBefore,
                        'window_minutes' => $windowMinutes,
                        'limit' => $limit,
                        'reasons' => $reasons,
                    ],
                ]
            );

            $this->info("Completed. {$message}");

            return self::SUCCESS;
        } catch (\Throwable $exception) {
            $cronLog->markFailed($exception->getTraceAsString(), $exception->getMessage());

            Log::error('Booking reminders command failed', [
                'error' => $exception->getMessage(),
            ]);

            $this->operationLogs->log(
                channel: OperationLog::CHANNEL_BOOKING,
                event: 'booking_reminder_command_failed',
                status: OperationLog::STATUS_ERROR,
                message: $exception->getMessage(),
                attributes: [
                    'source' => self::class,
                ]
            );

            $this->error('Booking reminders command failed: '.$exception->getMessage());

            return self::FAILURE;
        }
    }

    private function resolvePositiveInt(mixed $value, int $fallback): int
    {
        if ($value === null || $value === '') {
            return max(1, $fallback);
        }

        if (! is_numeric($value)) {
            return max(1, $fallback);
        }

        return max(1, (int) $value);
    }
}
