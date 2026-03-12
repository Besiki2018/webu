<?php

namespace App\Notifications;

use App\Models\EcommerceOrder;
use App\Services\EmailThemeService;
use App\Traits\HandlesLocale;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EcommerceOrderMerchantNotification extends Notification implements ShouldQueue
{
    use HandlesLocale, Queueable;

    public const EVENT_ORDER_PLACED = 'order_placed';

    public const EVENT_ORDER_PAID = 'order_paid';

    public const EVENT_ORDER_FAILED = 'order_failed';

    public function __construct(
        public EcommerceOrder $order,
        public string $eventType
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
            $this->order->loadMissing([
                'site.project',
            ]);

            $emailData = EmailThemeService::getEmailData();
            $subject = $this->resolveSubject($emailData['appName']);
            $title = $this->resolveTitle();
            $intro = $this->resolveIntro();

            return (new MailMessage)
                ->subject($subject)
                ->view('emails.user.ecommerce-order-merchant', array_merge($emailData, [
                    'subject' => $subject,
                    'title' => $title,
                    'intro' => $intro,
                    'eventType' => $this->eventType,
                    'details' => $this->resolveDetails(),
                    'actionUrl' => $this->resolveActionUrl(),
                    'actionText' => __('Open Project CMS'),
                ]));
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'event_type' => $this->eventType,
            'order_id' => $this->order->id,
            'order_number' => $this->order->order_number,
            'site_id' => $this->order->site_id,
            'payment_status' => $this->order->payment_status,
            'status' => $this->order->status,
        ];
    }

    private function resolveSubject(string $appName): string
    {
        return match ($this->eventType) {
            self::EVENT_ORDER_PAID => __('Order Paid #:order - :appName', [
                'order' => $this->order->order_number,
                'appName' => $appName,
            ]),
            self::EVENT_ORDER_FAILED => __('Order Payment Failed #:order - :appName', [
                'order' => $this->order->order_number,
                'appName' => $appName,
            ]),
            default => __('New Order Received #:order - :appName', [
                'order' => $this->order->order_number,
                'appName' => $appName,
            ]),
        };
    }

    private function resolveTitle(): string
    {
        return match ($this->eventType) {
            self::EVENT_ORDER_PAID => __('Order Payment Confirmed'),
            self::EVENT_ORDER_FAILED => __('Order Payment Failed'),
            default => __('New Order Placed'),
        };
    }

    private function resolveIntro(): string
    {
        return match ($this->eventType) {
            self::EVENT_ORDER_PAID => __('A customer payment was completed successfully for this order.'),
            self::EVENT_ORDER_FAILED => __('A customer payment attempt failed for this order.'),
            default => __('You have received a new order from your storefront.'),
        };
    }

    /**
     * @return array<string, string>
     */
    private function resolveDetails(): array
    {
        return [
            __('Order Number') => (string) $this->order->order_number,
            __('Customer Name') => (string) ($this->order->customer_name ?: '-'),
            __('Customer Email') => (string) ($this->order->customer_email ?: '-'),
            __('Customer Phone') => (string) ($this->order->customer_phone ?: '-'),
            __('Order Status') => (string) $this->order->status,
            __('Payment Status') => (string) $this->order->payment_status,
            __('Grand Total') => (string) ($this->order->grand_total.' '.$this->order->currency),
            __('Paid Total') => (string) ($this->order->paid_total.' '.$this->order->currency),
            __('Outstanding') => (string) ($this->order->outstanding_total.' '.$this->order->currency),
            __('Placed At') => (string) ($this->order->placed_at?->format('Y-m-d H:i') ?? '-'),
        ];
    }

    private function resolveActionUrl(): string
    {
        $projectId = $this->order->site?->project?->id;
        if (is_string($projectId) && $projectId !== '') {
            return route('project.cms', ['project' => $projectId]);
        }

        return url('/projects');
    }
}

