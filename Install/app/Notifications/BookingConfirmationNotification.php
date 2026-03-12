<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Services\EmailThemeService;
use App\Traits\HandlesLocale;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class BookingConfirmationNotification extends Notification implements ShouldQueue
{
    use HandlesLocale, Queueable;

    public function __construct(
        public Booking $booking
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->withLocale($this->getNotifiableLocale($notifiable), function () {
            $this->booking->loadMissing([
                'service',
                'staffResource',
                'site',
            ]);

            $emailData = EmailThemeService::getEmailData();
            $subject = __('Booking Confirmation #:booking - :appName', [
                'booking' => $this->booking->booking_number,
                'appName' => $emailData['appName'],
            ]);

            return (new MailMessage)
                ->subject($subject)
                ->view('emails.user.booking-customer-notification', array_merge($emailData, [
                    'subject' => $subject,
                    'title' => __('Your Booking Is Confirmed'),
                    'intro' => __('Thank you. Your booking request has been received and scheduled.'),
                    'details' => $this->buildDetails(),
                    'actionUrl' => $this->resolveActionUrl(),
                    'actionText' => __('Open Website'),
                ]));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'booking_id' => $this->booking->id,
            'booking_number' => $this->booking->booking_number,
            'site_id' => $this->booking->site_id,
            'status' => $this->booking->status,
            'type' => 'confirmation',
        ];
    }

    /**
     * @return array<string, string>
     */
    private function buildDetails(): array
    {
        return [
            __('Booking Number') => (string) $this->booking->booking_number,
            __('Service') => (string) ($this->booking->service?->name ?: '-'),
            __('Staff') => (string) ($this->booking->staffResource?->name ?: '-'),
            __('Start Time') => $this->formatDateTime($this->booking->starts_at),
            __('End Time') => $this->formatDateTime($this->booking->ends_at),
            __('Status') => (string) Str::headline((string) $this->booking->status),
            __('Outstanding Balance') => (string) ($this->booking->outstanding_total.' '.$this->booking->currency),
        ];
    }

    private function formatDateTime(?CarbonInterface $value): string
    {
        if (! $value) {
            return '-';
        }

        $timezone = is_string($this->booking->timezone) && trim($this->booking->timezone) !== ''
            ? $this->booking->timezone
            : config('app.timezone', 'UTC');

        return $value->copy()->setTimezone($timezone)->format('Y-m-d H:i');
    }

    private function resolveActionUrl(): ?string
    {
        $domain = (string) ($this->booking->site?->primary_domain ?? '');
        if ($domain !== '') {
            return str_starts_with($domain, 'http://') || str_starts_with($domain, 'https://')
                ? $domain
                : 'https://'.$domain;
        }

        return null;
    }
}
