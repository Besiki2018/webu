<?php

namespace App\Ecommerce\Services;

use App\Ecommerce\Contracts\EcommercePanelOrderServiceContract;
use App\Ecommerce\Contracts\EcommerceRepositoryContract;
use App\Models\EcommerceOrder;
use App\Models\Site;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EcommercePanelOrderService implements EcommercePanelOrderServiceContract
{
    public function __construct(
        protected EcommerceRepositoryContract $repository
    ) {}

    public function list(Site $site): array
    {
        $orders = $this->repository->listOrders($site)
            ->map(fn (EcommerceOrder $order): array => $this->serializeOrderSummary($order))
            ->values()
            ->all();

        return [
            'site_id' => $site->id,
            'orders' => $orders,
        ];
    }

    public function show(Site $site, EcommerceOrder $order): array
    {
        $target = $this->repository->findOrderBySiteAndId($site, $order->id);
        if (! $target) {
            throw (new ModelNotFoundException)->setModel(EcommerceOrder::class, [$order->id]);
        }

        return [
            'site_id' => $site->id,
            'order' => $this->serializeOrderDetails($target),
        ];
    }

    public function update(Site $site, EcommerceOrder $order, array $payload): EcommerceOrder
    {
        $target = $this->repository->findOrderBySiteAndId($site, $order->id);
        if (! $target) {
            throw (new ModelNotFoundException)->setModel(EcommerceOrder::class, [$order->id]);
        }

        if (($payload['payment_status'] ?? null) === 'paid' && ! $target->paid_at) {
            $payload['paid_at'] = now();
        }

        if (($payload['status'] ?? null) === 'cancelled' && ! $target->cancelled_at) {
            $payload['cancelled_at'] = now();
        }

        return $this->repository->updateOrder($target, $payload);
    }

    public function delete(Site $site, EcommerceOrder $order): bool
    {
        $target = $this->repository->findOrderBySiteAndId($site, $order->id);
        if (! $target) {
            throw (new ModelNotFoundException)->setModel(EcommerceOrder::class, [$order->id]);
        }

        return $this->repository->deleteOrder($target);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderSummary(EcommerceOrder $order): array
    {
        return [
            'id' => $order->id,
            'site_id' => $order->site_id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'fulfillment_status' => $order->fulfillment_status,
            'currency' => $order->currency,
            'customer_name' => $order->customer_name,
            'customer_email' => $order->customer_email,
            'customer_phone' => $order->customer_phone,
            'subtotal' => (string) $order->subtotal,
            'tax_total' => (string) $order->tax_total,
            'shipping_total' => (string) $order->shipping_total,
            'discount_total' => (string) $order->discount_total,
            'grand_total' => (string) $order->grand_total,
            'paid_total' => (string) $order->paid_total,
            'outstanding_total' => (string) $order->outstanding_total,
            'placed_at' => $order->placed_at?->toISOString(),
            'paid_at' => $order->paid_at?->toISOString(),
            'cancelled_at' => $order->cancelled_at?->toISOString(),
            'items_count' => (int) ($order->items_count ?? 0),
            'shipments_count' => (int) ($order->shipments_count ?? $order->shipments?->count() ?? 0),
            'payments' => $order->payments
                ->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'provider' => $payment->provider,
                    'status' => $payment->status,
                    'amount' => (string) $payment->amount,
                    'currency' => $payment->currency,
                    'is_installment' => (bool) $payment->is_installment,
                    'created_at' => $payment->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'created_at' => $order->created_at?->toISOString(),
            'updated_at' => $order->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrderDetails(EcommerceOrder $order): array
    {
        return [
            ...$this->serializeOrderSummary($order),
            'billing_address_json' => $order->billing_address_json ?? [],
            'shipping_address_json' => $order->shipping_address_json ?? [],
            'notes' => $order->notes,
            'meta_json' => $order->meta_json ?? [],
            'items' => $order->items
                ->map(fn ($item): array => [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'variant_id' => $item->variant_id,
                    'name' => $item->name,
                    'sku' => $item->sku,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => (string) $item->unit_price,
                    'tax_amount' => (string) $item->tax_amount,
                    'discount_amount' => (string) $item->discount_amount,
                    'line_total' => (string) $item->line_total,
                    'options_json' => $item->options_json ?? [],
                    'meta_json' => $item->meta_json ?? [],
                ])
                ->values()
                ->all(),
            'payments' => $order->payments
                ->map(fn ($payment): array => [
                    'id' => $payment->id,
                    'provider' => $payment->provider,
                    'status' => $payment->status,
                    'method' => $payment->method,
                    'transaction_reference' => $payment->transaction_reference,
                    'amount' => (string) $payment->amount,
                    'currency' => $payment->currency,
                    'is_installment' => (bool) $payment->is_installment,
                    'installment_plan_json' => $payment->installment_plan_json ?? [],
                    'raw_payload_json' => $payment->raw_payload_json ?? [],
                    'processed_at' => $payment->processed_at?->toISOString(),
                    'created_at' => $payment->created_at?->toISOString(),
                ])
                ->values()
                ->all(),
            'shipments' => $order->shipments
                ->map(function ($shipment): array {
                    return [
                        'id' => $shipment->id,
                        'provider_slug' => $shipment->provider_slug,
                        'shipment_reference' => $shipment->shipment_reference,
                        'tracking_number' => $shipment->tracking_number,
                        'tracking_url' => $shipment->tracking_url,
                        'status' => $shipment->status,
                        'shipped_at' => $shipment->shipped_at?->toISOString(),
                        'delivered_at' => $shipment->delivered_at?->toISOString(),
                        'cancelled_at' => $shipment->cancelled_at?->toISOString(),
                        'last_tracked_at' => $shipment->last_tracked_at?->toISOString(),
                        'meta_json' => $shipment->meta_json ?? [],
                        'events' => $shipment->events
                            ->sortByDesc(fn ($event): int => $event->occurred_at?->getTimestamp() ?? 0)
                            ->values()
                            ->map(fn ($event): array => [
                                'id' => $event->id,
                                'event_type' => $event->event_type,
                                'status' => $event->status,
                                'message' => $event->message,
                                'payload_json' => $event->payload_json ?? [],
                                'occurred_at' => $event->occurred_at?->toISOString(),
                                'created_at' => $event->created_at?->toISOString(),
                            ])
                            ->all(),
                    ];
                })
                ->values()
                ->all(),
        ];
    }
}
