<?php

namespace App\Contracts;

use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderPayment;

interface EcommercePaymentGatewayPlugin extends PaymentGatewayPlugin
{
    /**
     * Whether this provider can create installment payment sessions.
     */
    public function supportsInstallments(): bool;

    /**
     * Initialize an ecommerce payment session for a specific order payment.
     *
     * @param  array<string, mixed>  $payload
     * @return array{
     *     payment?: array<string, mixed>,
     *     payment_session?: array<string, mixed>
     * }
     */
    public function initEcommercePayment(
        EcommerceOrder $order,
        EcommerceOrderPayment $payment,
        array $payload = []
    ): array;
}
