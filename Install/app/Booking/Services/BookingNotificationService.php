<?php

namespace App\Booking\Services;

use App\Models\Booking;
use App\Models\BookingEvent;
use App\Models\OperationLog;
use App\Notifications\BookingConfirmationNotification;
use App\Notifications\BookingReminderNotification;
use App\Services\OperationLogService;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Notification as NotificationFacade;

class BookingNotificationService
{
    private const EVENT_CONFIRMATION_SENT = 'notification_confirmation_sent';

    private const EVENT_REMINDER_SENT = 'notification_reminder_sent';

    /**
     * @return array{sent: bool, reason: string}
     */
    public function sendConfirmation(Booking $booking): array
    {
        return $this->dispatch(
            booking: $booking,
            eventType: self::EVENT_CONFIRMATION_SENT,
            eventKey: $this->buildEventKey($booking, 'confirmation'),
            payload: [
                'notification' => 'confirmation',
            ],
            notification: new BookingConfirmationNotification($booking)
        );
    }

    /**
     * @return array{sent: bool, reason: string}
     */
    public function sendReminder(Booking $booking, int $minutesBeforeStart): array
    {
        $booking->loadMissing(['site.project']);

        if (! in_array((string) $booking->status, [Booking::STATUS_PENDING, Booking::STATUS_CONFIRMED], true)) {
            $this->log(
                booking: $booking,
                event: 'booking_reminder_skipped',
                status: OperationLog::STATUS_INFO,
                message: 'Booking reminder skipped due to booking status.',
                context: [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'status' => $booking->status,
                ]
            );

            return [
                'sent' => false,
                'reason' => 'invalid_status',
            ];
        }

        if (! $booking->starts_at) {
            $this->log(
                booking: $booking,
                event: 'booking_reminder_skipped',
                status: OperationLog::STATUS_WARNING,
                message: 'Booking reminder skipped because starts_at is missing.',
                context: [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                ]
            );

            return [
                'sent' => false,
                'reason' => 'missing_start_time',
            ];
        }

        return $this->dispatch(
            booking: $booking,
            eventType: self::EVENT_REMINDER_SENT,
            eventKey: $this->buildEventKey(
                booking: $booking,
                notificationType: 'reminder',
                suffix: (string) $minutesBeforeStart.'|'.$booking->starts_at->toISOString()
            ),
            payload: [
                'notification' => 'reminder',
                'minutes_before_start' => $minutesBeforeStart,
                'starts_at' => $booking->starts_at->toISOString(),
            ],
            notification: new BookingReminderNotification($booking, $minutesBeforeStart)
        );
    }

    public function __construct(
        protected OperationLogService $operationLogs
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{sent: bool, reason: string}
     */
    private function dispatch(
        Booking $booking,
        string $eventType,
        string $eventKey,
        array $payload,
        Notification $notification
    ): array {
        $booking->loadMissing([
            'site.project',
            'service',
            'staffResource',
        ]);

        $recipient = $this->sanitizeEmail($booking->customer_email);
        if ($recipient === null) {
            $this->log(
                booking: $booking,
                event: 'booking_notification_skipped',
                status: OperationLog::STATUS_WARNING,
                message: 'Booking notification skipped because customer email is missing.',
                context: [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'event_type' => $eventType,
                    'reason' => 'missing_customer_email',
                ]
            );

            return [
                'sent' => false,
                'reason' => 'missing_customer_email',
            ];
        }

        $event = BookingEvent::query()->firstOrCreate(
            [
                'site_id' => $booking->site_id,
                'event_key' => $eventKey,
            ],
            [
                'booking_id' => $booking->id,
                'event_type' => $eventType,
                'payload_json' => [
                    ...$payload,
                    'channel' => 'mail',
                    'recipient' => $recipient,
                ],
                'occurred_at' => now(),
            ]
        );

        if (! $event->wasRecentlyCreated) {
            $this->log(
                booking: $booking,
                event: 'booking_notification_duplicate_ignored',
                status: OperationLog::STATUS_INFO,
                message: 'Booking notification duplicate ignored (idempotent).',
                context: [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'event_type' => $eventType,
                    'event_key' => $eventKey,
                ]
            );

            return [
                'sent' => false,
                'reason' => 'duplicate',
            ];
        }

        $queue = config('booking.notifications.queue');
        if (is_string($queue) && trim($queue) !== '') {
            $notification->onQueue(trim($queue));
        }

        try {
            NotificationFacade::route('mail', $recipient)->notify($notification);
        } catch (\Throwable $exception) {
            $event->delete();

            $this->log(
                booking: $booking,
                event: 'booking_notification_failed',
                status: OperationLog::STATUS_ERROR,
                message: $exception->getMessage(),
                context: [
                    'booking_id' => $booking->id,
                    'booking_number' => $booking->booking_number,
                    'event_type' => $eventType,
                    'event_key' => $eventKey,
                    'recipient' => $recipient,
                ]
            );

            return [
                'sent' => false,
                'reason' => 'dispatch_failed',
            ];
        }

        $this->log(
            booking: $booking,
            event: 'booking_notification_dispatched',
            status: OperationLog::STATUS_SUCCESS,
            message: 'Booking notification dispatched.',
            context: [
                'booking_id' => $booking->id,
                'booking_number' => $booking->booking_number,
                'event_type' => $eventType,
                'event_key' => $eventKey,
                'recipient' => $recipient,
            ]
        );

        return [
            'sent' => true,
            'reason' => 'sent',
        ];
    }

    private function buildEventKey(Booking $booking, string $notificationType, ?string $suffix = null): string
    {
        $seed = implode('|', array_filter([
            (string) $booking->id,
            (string) $booking->site_id,
            $notificationType,
            $suffix,
        ], fn (?string $value): bool => $value !== null && $value !== ''));

        return sprintf('booking:%d:%s:%s', $booking->id, $notificationType, sha1($seed));
    }

    private function sanitizeEmail(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $email = strtolower(trim($value));
        if ($email === '') {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function log(
        Booking $booking,
        string $event,
        string $status,
        string $message,
        array $context = []
    ): void {
        $project = $booking->site?->project;
        if (! $project) {
            return;
        }

        $this->operationLogs->logProject(
            project: $project,
            channel: OperationLog::CHANNEL_BOOKING,
            event: $event,
            status: $status,
            message: $message,
            attributes: [
                'identifier' => (string) $booking->booking_number,
                'context' => $context,
            ]
        );
    }
}
