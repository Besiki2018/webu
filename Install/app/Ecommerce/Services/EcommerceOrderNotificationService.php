<?php

namespace App\Ecommerce\Services;

use App\Models\EcommerceOrder;
use App\Models\OperationLog;
use App\Notifications\EcommerceOrderMerchantNotification;
use App\Services\OperationLogService;
use Illuminate\Support\Facades\Notification;

class EcommerceOrderNotificationService
{
    public function __construct(
        protected OperationLogService $operationLogs
    ) {}

    public function notifyOrderPlaced(EcommerceOrder $order): void
    {
        $this->dispatch($order, EcommerceOrderMerchantNotification::EVENT_ORDER_PLACED);
    }

    public function notifyOrderPaid(EcommerceOrder $order): void
    {
        $this->dispatch($order, EcommerceOrderMerchantNotification::EVENT_ORDER_PAID);
    }

    public function notifyOrderFailed(EcommerceOrder $order): void
    {
        $this->dispatch($order, EcommerceOrderMerchantNotification::EVENT_ORDER_FAILED);
    }

    private function dispatch(EcommerceOrder $order, string $eventType): void
    {
        $order->loadMissing([
            'site.globalSettings',
            'site.project.user',
        ]);

        $emails = $this->resolveRecipientEmails($order);
        if ($emails === []) {
            $this->operationLogs->logPayment(
                event: 'ecommerce_notification_skipped',
                status: OperationLog::STATUS_WARNING,
                message: 'Ecommerce merchant notification skipped because no recipient email was resolved.',
                attributes: [
                    'source' => self::class,
                    'project_id' => $order->site?->project_id,
                    'identifier' => (string) $order->id,
                    'context' => [
                        'order_id' => $order->id,
                        'event_type' => $eventType,
                        'reason' => 'missing_recipient',
                    ],
                ]
            );

            return;
        }

        Notification::route('mail', count($emails) === 1 ? $emails[0] : $emails)
            ->notify(new EcommerceOrderMerchantNotification($order, $eventType));

        $this->operationLogs->logPayment(
            event: 'ecommerce_notification_dispatched',
            status: OperationLog::STATUS_SUCCESS,
            message: "Ecommerce merchant notification dispatched ({$eventType}).",
            attributes: [
                'source' => self::class,
                'project_id' => $order->site?->project_id,
                'identifier' => (string) $order->id,
                'context' => [
                    'order_id' => $order->id,
                    'event_type' => $eventType,
                    'recipient_count' => count($emails),
                ],
            ]
        );
    }

    /**
     * @return array<int, string>
     */
    private function resolveRecipientEmails(EcommerceOrder $order): array
    {
        $contact = $order->site?->globalSettings?->contact_json ?? [];
        $emails = [];

        $single = $this->sanitizeEmail($contact['email'] ?? null);
        if ($single !== null) {
            $emails[] = $single;
        }

        $multi = $contact['emails'] ?? [];
        if (is_array($multi)) {
            foreach ($multi as $candidate) {
                $email = $this->sanitizeEmail($candidate);
                if ($email !== null) {
                    $emails[] = $email;
                }
            }
        }

        $ownerEmail = $this->sanitizeEmail($order->site?->project?->user?->email);
        if ($ownerEmail !== null) {
            $emails[] = $ownerEmail;
        }

        return array_values(array_unique($emails));
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
}

