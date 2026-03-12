<?php

namespace App\Ecommerce\Contracts;

interface EcommercePaymentWebhookServiceContract
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     handled: bool,
     *     idempotent: bool,
     *     reason: string|null,
     *     provider: string,
     *     normalized_status: string|null,
     *     payment_id: int|null,
     *     order_id: int|null,
     *     project_id: string|null
     * }
     */
    public function synchronize(string $provider, array $payload, ?string $webhookEventId = null): array;
}

