<?php

namespace App\Ecommerce\Services;

use App\Ecommerce\Contracts\EcommercePaymentWebhookServiceContract;
use App\Ecommerce\Contracts\EcommerceRepositoryContract;
use App\Ecommerce\Contracts\EcommerceAccountingServiceContract;
use App\Models\EcommerceOrder;
use App\Models\EcommerceOrderPayment;
use Illuminate\Support\Facades\DB;

class EcommercePaymentWebhookService implements EcommercePaymentWebhookServiceContract
{
    public function __construct(
        protected EcommerceRepositoryContract $repository,
        protected EcommerceAccountingServiceContract $accounting
    ) {}

    public function synchronize(string $provider, array $payload, ?string $webhookEventId = null): array
    {
        $context = $this->extractContext($payload);

        if (! $context['is_ecommerce']) {
            return $this->result(
                handled: false,
                idempotent: false,
                reason: 'not_ecommerce_event',
                provider: $provider,
                normalizedStatus: null,
                payment: null
            );
        }

        $normalizedStatus = $this->normalizeStatus($context['status'], $context['event_type']);
        if ($normalizedStatus === null) {
            return $this->result(
                handled: false,
                idempotent: false,
                reason: 'status_not_supported',
                provider: $provider,
                normalizedStatus: null,
                payment: null
            );
        }

        $payment = $this->resolvePaymentTarget(
            provider: $provider,
            paymentId: $context['payment_id'],
            orderId: $context['order_id'],
            transactionReference: $context['transaction_reference']
        );

        if (! $payment) {
            return $this->result(
                handled: false,
                idempotent: false,
                reason: 'payment_not_found',
                provider: $provider,
                normalizedStatus: $normalizedStatus,
                payment: null
            );
        }

        $order = $payment->order;
        if (! $order) {
            return $this->result(
                handled: false,
                idempotent: false,
                reason: 'order_not_found',
                provider: $provider,
                normalizedStatus: $normalizedStatus,
                payment: $payment
            );
        }

        return DB::transaction(function () use (
            $provider,
            $payload,
            $webhookEventId,
            $normalizedStatus,
            $context,
            $payment,
            $order
        ): array {
            $currentPaymentStatus = strtolower((string) $payment->status);
            $isIdempotent = in_array($currentPaymentStatus, $this->terminalStatusesFor($normalizedStatus), true);

            if (! $isIdempotent) {
                $amount = $this->resolveAmount($normalizedStatus, $context);

                $paymentPayload = [
                    'provider' => $payment->provider ?: $provider,
                    'status' => $normalizedStatus,
                    'processed_at' => now(),
                ];

                if (($context['transaction_reference'] ?? null) && ! $payment->transaction_reference) {
                    $paymentPayload['transaction_reference'] = $context['transaction_reference'];
                }

                $paymentPayload['raw_payload_json'] = $this->mergePaymentWebhookMeta(
                    existing: $payment->raw_payload_json,
                    provider: $provider,
                    normalizedStatus: $normalizedStatus,
                    webhookEventId: $webhookEventId,
                    payload: $payload
                );

                $orderPayload = $this->buildOrderPayloadForStatus(
                    order: $order,
                    normalizedStatus: $normalizedStatus,
                    amount: $amount
                );

                if (
                    in_array($normalizedStatus, ['refunded', 'partially_refunded'], true)
                    && in_array(($orderPayload['payment_status'] ?? null), ['refunded', 'partially_refunded'], true)
                ) {
                    $paymentPayload['status'] = (string) $orderPayload['payment_status'];
                }

                $orderPayload['meta_json'] = $this->mergeOrderWebhookMeta(
                    existing: $order->meta_json,
                    provider: $provider,
                    normalizedStatus: $normalizedStatus,
                    webhookEventId: $webhookEventId
                );

                $updatedPayment = $this->repository->updateOrderPayment($payment, $paymentPayload);
                $updatedOrder = $this->repository->updateOrder($order, $orderPayload);

                $orderSite = $updatedOrder->site;
                if (! $orderSite) {
                    return $this->result(
                        handled: false,
                        idempotent: false,
                        reason: 'order_site_not_found',
                        provider: $provider,
                        normalizedStatus: $normalizedStatus,
                        payment: $updatedPayment
                    );
                }

                $resolvedAmount = $this->moneyFloat($amount ?? $updatedPayment->amount ?? 0);
                $webhookMeta = [
                    'source' => 'payment_webhook',
                    'provider' => $provider,
                    'normalized_status' => $normalizedStatus,
                    'webhook_event_id' => $webhookEventId,
                ];

                if ($normalizedStatus === 'paid' && $resolvedAmount > 0) {
                    $this->accounting->recordPaymentSettled(
                        site: $orderSite,
                        order: $updatedOrder,
                        payment: $updatedPayment,
                        amount: $resolvedAmount,
                        eventKey: sprintf('payment:%d:settled', $updatedPayment->id),
                        meta: $webhookMeta
                    );
                }

                if (in_array($normalizedStatus, ['refunded', 'partially_refunded'], true) && $resolvedAmount > 0) {
                    $this->accounting->recordRefund(
                        site: $orderSite,
                        order: $updatedOrder,
                        payment: $updatedPayment,
                        amount: $resolvedAmount,
                        eventKey: $this->buildRefundEventKey($updatedPayment, $webhookEventId, $payload, $resolvedAmount),
                        meta: [
                            ...$webhookMeta,
                            'reference' => $webhookEventId ?: sha1((string) json_encode($payload)),
                        ]
                    );
                }
            }

            $resolvedPayment = $this->repository->findOrderPaymentById((int) $payment->id) ?? $payment;

            return $this->result(
                handled: true,
                idempotent: $isIdempotent,
                reason: $isIdempotent ? 'already_processed' : null,
                provider: $provider,
                normalizedStatus: $normalizedStatus,
                payment: $resolvedPayment
            );
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *     is_ecommerce: bool,
     *     event_type: string|null,
     *     status: string|null,
     *     payment_id: int|null,
     *     order_id: int|null,
     *     transaction_reference: string|null,
     *     amount: float|null,
     *     refund_amount: float|null
     * }
     */
    private function extractContext(array $payload): array
    {
        $ecommerce = data_get($payload, 'ecommerce');
        $ecommercePayload = is_array($ecommerce) ? $ecommerce : [];

        $paymentId = $this->parsePositiveInt(
            $ecommercePayload['payment_id']
                ?? $payload['payment_id']
                ?? $payload['ecommerce_payment_id']
                ?? data_get($payload, 'payment.id')
                ?? data_get($payload, 'transaction.id')
                ?? data_get($payload, 'data.payment.id')
                ?? data_get($payload, 'data.transaction.id')
                ?? data_get($payload, 'body.payment_detail.transaction_id')
                ?? data_get($payload, 'data.ecommerce.payment_id')
        );

        $orderId = $this->parsePositiveInt(
            $ecommercePayload['order_id']
                ?? $payload['order_id']
                ?? $payload['ecommerce_order_id']
                ?? data_get($payload, 'payment.order_id')
                ?? data_get($payload, 'transaction.order_id')
                ?? data_get($payload, 'data.payment.order_id')
                ?? data_get($payload, 'data.transaction.order_id')
                ?? data_get($payload, 'body.external_order_id')
                ?? data_get($payload, 'data.ecommerce.order_id')
        );

        $transactionReference = $this->nullableString(
            $ecommercePayload['transaction_reference']
                ?? $payload['transaction_reference']
                ?? $payload['ecommerce_transaction_reference']
                ?? $payload['merchant_reference']
                ?? $payload['merchant_order_id']
                ?? data_get($payload, 'payment.transaction_reference')
                ?? data_get($payload, 'payment.merchant_reference')
                ?? data_get($payload, 'payment.merchant_order_id')
                ?? data_get($payload, 'transaction.reference')
                ?? data_get($payload, 'transaction.transaction_reference')
                ?? data_get($payload, 'body.order_id')
                ?? data_get($payload, 'body.external_order_id')
                ?? data_get($payload, 'body.payment_detail.transaction_id')
                ?? data_get($payload, 'data.payment.transaction_reference')
                ?? data_get($payload, 'data.payment.merchant_reference')
                ?? data_get($payload, 'data.payment.merchant_order_id')
                ?? data_get($payload, 'data.transaction.reference')
                ?? data_get($payload, 'data.transaction.transaction_reference')
                ?? data_get($payload, 'data.ecommerce.transaction_reference')
                ?? data_get($payload, 'data.transaction_reference')
                ?? data_get($payload, 'data.merchant_reference')
        );

        $status = $this->nullableString(
            $ecommercePayload['status']
                ?? $payload['ecommerce_status']
                ?? $payload['payment_status']
                ?? data_get($payload, 'payment.status')
                ?? data_get($payload, 'transaction.status')
                ?? data_get($payload, 'data.payment.status')
                ?? data_get($payload, 'data.transaction.status')
                ?? data_get($payload, 'body.order_status.key')
                ?? data_get($payload, 'data.ecommerce.status')
                ?? data_get($payload, 'data.payment_status')
                ?? $payload['status']
                ?? data_get($payload, 'data.status')
        );

        $eventType = $this->nullableString(
            $payload['event_type']
                ?? $payload['event']
                ?? $payload['type']
                ?? $payload['action']
                ?? data_get($payload, 'data.event')
                ?? data_get($payload, 'data.type')
                ?? data_get($payload, 'data.action')
                ?? data_get($payload, 'body.action')
        );

        $amount = $this->parseMoney(
            $ecommercePayload['amount']
                ?? $payload['amount']
                ?? data_get($payload, 'payment.amount')
                ?? data_get($payload, 'transaction.amount')
                ?? data_get($payload, 'data.payment.amount')
                ?? data_get($payload, 'data.transaction.amount')
                ?? data_get($payload, 'body.purchase_units.transfer_amount')
                ?? data_get($payload, 'body.purchase_units.request_amount')
                ?? data_get($payload, 'data.ecommerce.amount')
                ?? data_get($payload, 'data.amount')
                ?? data_get($payload, 'resource.amount.total')
                ?? data_get($payload, 'resource.amount.value')
        );

        $refundAmount = $this->parseMoney(
            $ecommercePayload['refund_amount']
                ?? $payload['refund_amount']
                ?? $payload['refunded_amount']
                ?? data_get($payload, 'data.ecommerce.refund_amount')
                ?? data_get($payload, 'data.refund_amount')
                ?? data_get($payload, 'data.refunded_amount')
                ?? data_get($payload, 'body.purchase_units.refund_amount')
        );

        $providerByPayload = strtolower((string) ($payload['provider'] ?? $ecommercePayload['provider'] ?? ''));
        $isEcommerce = ! empty($ecommercePayload)
            || $paymentId !== null
            || $orderId !== null
            || $transactionReference !== null
            || array_key_exists('ecommerce_status', $payload)
            || array_key_exists('merchant_order_id', $payload)
            || is_array($payload['payment'] ?? null)
            || is_array($payload['transaction'] ?? null)
            || is_array($payload['body'] ?? null)
            || str_starts_with($providerByPayload, 'ecommerce.');

        return [
            'is_ecommerce' => $isEcommerce,
            'event_type' => $eventType,
            'status' => $status,
            'payment_id' => $paymentId,
            'order_id' => $orderId,
            'transaction_reference' => $transactionReference,
            'amount' => $amount,
            'refund_amount' => $refundAmount,
        ];
    }

    private function resolvePaymentTarget(
        string $provider,
        ?int $paymentId,
        ?int $orderId,
        ?string $transactionReference
    ): ?EcommerceOrderPayment {
        if ($paymentId !== null) {
            $byId = $this->repository->findOrderPaymentById($paymentId);
            if ($byId) {
                return $byId;
            }
        }

        if ($transactionReference !== null) {
            $byProvider = $this->repository->findOrderPaymentByTransactionReference($transactionReference, $provider);
            if ($byProvider) {
                return $byProvider;
            }

            return $this->repository->findOrderPaymentByTransactionReference($transactionReference);
        }

        if ($orderId !== null) {
            $order = $this->repository->findOrderById($orderId);
            if (! $order) {
                return null;
            }

            $byProvider = $this->repository->findLatestOrderPaymentForOrder($order, $provider);
            if ($byProvider) {
                return $byProvider;
            }

            return $this->repository->findLatestOrderPaymentForOrder($order);
        }

        return null;
    }

    private function normalizeStatus(?string $status, ?string $eventType): ?string
    {
        $candidate = strtolower(trim((string) $status));
        $event = strtolower(trim((string) $eventType));

        if ($candidate !== '') {
            if (str_contains($candidate, 'partial') && str_contains($candidate, 'refund')) {
                return 'partially_refunded';
            }

            if (in_array($candidate, [
                'success',
                'succeeded',
                'paid',
                'completed',
                'captured',
                'authorized',
                'settled',
                'reverse_completed',
            ], true)) {
                return 'paid';
            }

            if (in_array($candidate, [
                'failed',
                'declined',
                'cancelled',
                'canceled',
                'expired',
                'error',
                'voided',
                'rejected',
            ], true)) {
                return 'failed';
            }

            if (in_array($candidate, [
                'refunded',
                'refund',
                'chargeback',
                'reverse_success',
            ], true)) {
                return 'refunded';
            }

            if (in_array($candidate, ['partially_refunded'], true)) {
                return 'partially_refunded';
            }

            if (in_array($candidate, ['refunded_partially', 'partial_refund'], true)) {
                return 'partially_refunded';
            }
        }

        if ($event !== '') {
            if (str_contains($event, 'refund')) {
                return str_contains($event, 'partial') ? 'partially_refunded' : 'refunded';
            }

            if (
                str_contains($event, 'success')
                || str_contains($event, 'succeed')
                || str_contains($event, 'complete')
                || str_contains($event, 'paid')
                || str_contains($event, 'capture')
                || str_contains($event, 'settle')
            ) {
                return 'paid';
            }

            if (
                str_contains($event, 'fail')
                || str_contains($event, 'declin')
                || str_contains($event, 'cancel')
                || str_contains($event, 'void')
                || str_contains($event, 'expire')
            ) {
                return 'failed';
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function resolveAmount(string $normalizedStatus, array $context): ?float
    {
        if (in_array($normalizedStatus, ['refunded', 'partially_refunded'], true)) {
            return $context['refund_amount'] ?? $context['amount'] ?? null;
        }

        return $context['amount'] ?? null;
    }

    private function buildOrderPayloadForStatus(EcommerceOrder $order, string $normalizedStatus, ?float $amount = null): array
    {
        $grandTotal = $this->moneyFloat($order->grand_total);
        $currentPaid = $this->moneyFloat($order->paid_total);

        return match ($normalizedStatus) {
            'paid' => $this->buildPaidOrderPayload($order, $grandTotal, $currentPaid, $amount),
            'failed' => $this->buildFailedOrderPayload($order, $grandTotal, $currentPaid),
            'refunded', 'partially_refunded' => $this->buildRefundOrderPayload($order, $currentPaid, $amount),
            default => [],
        };
    }

    private function buildPaidOrderPayload(
        EcommerceOrder $order,
        float $grandTotal,
        float $currentPaid,
        ?float $amount = null
    ): array {
        $appliedAmount = $amount ?? $this->moneyFloat($order->outstanding_total);
        if ($appliedAmount <= 0) {
            $appliedAmount = $grandTotal;
        }

        $nextPaid = min($grandTotal, $currentPaid + $appliedAmount);
        $remaining = max(0, $grandTotal - $nextPaid);

        $payload = [
            'paid_total' => $this->moneyString($nextPaid),
            'outstanding_total' => $this->moneyString($remaining),
            'payment_status' => $remaining <= 0.00001 ? 'paid' : 'unpaid',
        ];

        if ($remaining <= 0.00001) {
            $payload['paid_at'] = $order->paid_at ?: now();

            if (in_array((string) $order->status, ['pending', 'failed'], true)) {
                $payload['status'] = 'paid';
            }
        }

        return $payload;
    }

    private function buildFailedOrderPayload(EcommerceOrder $order, float $grandTotal, float $currentPaid): array
    {
        if ($currentPaid > 0.00001) {
            return [];
        }

        $payload = [
            'payment_status' => 'failed',
            'paid_total' => $this->moneyString(0),
            'outstanding_total' => $this->moneyString(max(0, $grandTotal)),
        ];

        if (in_array((string) $order->status, ['pending', 'processing'], true)) {
            $payload['status'] = 'failed';
        }

        return $payload;
    }

    private function buildRefundOrderPayload(EcommerceOrder $order, float $currentPaid, ?float $amount = null): array
    {
        $appliedRefund = $amount ?? $this->moneyFloat($order->paid_total);
        if ($appliedRefund <= 0) {
            $appliedRefund = $this->moneyFloat($order->grand_total);
        }

        $nextPaid = max(0, $currentPaid - $appliedRefund);
        $isFullRefund = $nextPaid <= 0.00001;

        $payload = [
            'paid_total' => $this->moneyString($nextPaid),
            'outstanding_total' => $this->moneyString(0),
            'payment_status' => $isFullRefund ? 'refunded' : 'partially_refunded',
        ];

        if ($isFullRefund) {
            $payload['status'] = 'refunded';
        } elseif ((string) $order->status === 'refunded') {
            $payload['status'] = 'paid';
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function buildRefundEventKey(
        EcommerceOrderPayment $payment,
        ?string $webhookEventId,
        array $payload,
        float $amount
    ): string {
        if (is_string($webhookEventId) && trim($webhookEventId) !== '') {
            return sprintf('payment:%d:refund:%s', $payment->id, trim($webhookEventId));
        }

        return sprintf(
            'payment:%d:refund:%s',
            $payment->id,
            sha1($this->moneyString($amount).'|'.(string) json_encode($payload))
        );
    }

    /**
     * @param  array<string, mixed>|null  $existing
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function mergePaymentWebhookMeta(
        ?array $existing,
        string $provider,
        string $normalizedStatus,
        ?string $webhookEventId,
        array $payload
    ): array {
        $meta = is_array($existing) ? $existing : [];
        $events = is_array($meta['webhook_events'] ?? null) ? $meta['webhook_events'] : [];

        $events[] = [
            'provider' => $provider,
            'status' => $normalizedStatus,
            'event_id' => $webhookEventId,
            'received_at' => now()->toISOString(),
            'payload_hash' => sha1((string) json_encode($payload)),
        ];

        if (count($events) > 10) {
            $events = array_slice($events, -10);
        }

        $meta['webhook_events'] = $events;
        $meta['last_webhook_status'] = $normalizedStatus;
        $meta['last_webhook_event_id'] = $webhookEventId;
        $meta['last_webhook_provider'] = $provider;
        $meta['last_webhook_at'] = now()->toISOString();

        return $meta;
    }

    /**
     * @param  array<string, mixed>|null  $existing
     * @return array<string, mixed>
     */
    private function mergeOrderWebhookMeta(
        ?array $existing,
        string $provider,
        string $normalizedStatus,
        ?string $webhookEventId
    ): array {
        $meta = is_array($existing) ? $existing : [];
        $meta['payment_sync'] = [
            'provider' => $provider,
            'status' => $normalizedStatus,
            'event_id' => $webhookEventId,
            'synced_at' => now()->toISOString(),
        ];

        return $meta;
    }

    /**
     * @return array<int, string>
     */
    private function terminalStatusesFor(string $normalizedStatus): array
    {
        return match ($normalizedStatus) {
            'paid' => ['paid'],
            'failed' => ['failed', 'paid', 'refunded', 'partially_refunded'],
            'refunded' => ['refunded'],
            'partially_refunded' => ['partially_refunded', 'refunded'],
            default => [],
        };
    }

    /**
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
    private function result(
        bool $handled,
        bool $idempotent,
        ?string $reason,
        string $provider,
        ?string $normalizedStatus,
        ?EcommerceOrderPayment $payment
    ): array {
        return [
            'handled' => $handled,
            'idempotent' => $idempotent,
            'reason' => $reason,
            'provider' => $provider,
            'normalized_status' => $normalizedStatus,
            'payment_id' => $payment?->id ? (int) $payment->id : null,
            'order_id' => $payment?->order_id ? (int) $payment->order_id : null,
            'project_id' => $payment?->order?->site?->project_id,
        ];
    }

    private function moneyFloat(mixed $value): float
    {
        return round((float) $value, 2);
    }

    private function moneyString(mixed $value): string
    {
        return number_format($this->moneyFloat($value), 2, '.', '');
    }

    private function parsePositiveInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            $int = (int) $value;

            return $int > 0 ? $int : null;
        }

        return null;
    }

    private function parseMoney(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return $this->moneyFloat($value);
        }

        if (is_string($value)) {
            $normalized = trim(str_replace(',', '.', $value));
            if ($normalized !== '' && is_numeric($normalized)) {
                return $this->moneyFloat($normalized);
            }
        }

        return null;
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
