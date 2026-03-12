<?php

namespace App\Observers;

use App\Ecommerce\Services\EcommerceOrderNotificationService;
use App\Models\EcommerceOrder;

class EcommerceOrderObserver
{
    public function __construct(
        protected EcommerceOrderNotificationService $notifications
    ) {}

    public function created(EcommerceOrder $order): void
    {
        $this->notifications->notifyOrderPlaced($order);
    }

    public function updated(EcommerceOrder $order): void
    {
        if (! $order->wasChanged('payment_status')) {
            return;
        }

        $paymentStatus = strtolower((string) $order->payment_status);

        if ($paymentStatus === 'paid') {
            $this->notifications->notifyOrderPaid($order);

            return;
        }

        if ($paymentStatus === 'failed') {
            $this->notifications->notifyOrderFailed($order);
        }
    }
}

