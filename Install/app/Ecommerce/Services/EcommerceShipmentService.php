<?php

namespace App\Ecommerce\Services;

use App\Cms\Contracts\CmsModuleRegistryServiceContract;
use App\Contracts\CourierPlugin;
use App\Ecommerce\Contracts\EcommerceCourierConfigServiceContract;
use App\Ecommerce\Contracts\EcommerceRepositoryContract;
use App\Ecommerce\Contracts\EcommerceShipmentServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Models\EcommerceOrder;
use App\Models\EcommerceShipment;
use App\Models\EcommerceShipmentEvent;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class EcommerceShipmentService implements EcommerceShipmentServiceContract
{
    public function __construct(
        protected EcommerceRepositoryContract $repository,
        protected EcommerceCourierConfigServiceContract $couriers,
        protected CmsModuleRegistryServiceContract $modules
    ) {}

    public function listForOrder(Site $site, EcommerceOrder $order): array
    {
        $targetOrder = $this->assertOrderBelongsToSite($site, $order);
        $shipments = $this->repository->listShipmentsByOrder($site, $targetOrder);

        return [
            'site_id' => $site->id,
            'order_id' => $targetOrder->id,
            'shipments' => $shipments
                ->map(fn (EcommerceShipment $shipment): array => $this->serializeShipment($shipment))
                ->values()
                ->all(),
        ];
    }

    public function createForOrder(Site $site, EcommerceOrder $order, array $payload = [], ?User $actor = null): array
    {
        $targetOrder = $this->assertOrderBelongsToSite($site, $order);
        $providerSlug = $this->nullableString($payload['provider_slug'] ?? null);
        if ($providerSlug === null) {
            throw new EcommerceDomainException('provider_slug is required.', 422);
        }

        $courier = $this->couriers->resolveCourierForSite($site, $providerSlug, true);
        if (! $courier instanceof CourierPlugin) {
            throw new EcommerceDomainException('Selected courier is not available for this site.', 422);
        }

        try {
            $providerResponse = $courier->createShipment([
                'site_id' => $site->id,
                'order_id' => $targetOrder->id,
                'order_number' => $targetOrder->order_number,
                'currency' => $targetOrder->currency,
                'customer_name' => $targetOrder->customer_name,
                'customer_email' => $targetOrder->customer_email,
                'customer_phone' => $targetOrder->customer_phone,
                'shipping_address_json' => $targetOrder->shipping_address_json ?? [],
                'shipment_reference' => $payload['shipment_reference'] ?? null,
                'tracking_number' => $payload['tracking_number'] ?? null,
                'tracking_url' => $payload['tracking_url'] ?? null,
                'meta_json' => is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : [],
            ]);
        } catch (\Throwable $exception) {
            throw new EcommerceDomainException('Failed to create shipment on courier provider.', 422, [
                'provider_slug' => $providerSlug,
            ]);
        }

        $status = $this->normalizeStatus($providerResponse['status'] ?? null, EcommerceShipment::STATUS_CREATED);
        $shipmentReference = $this->ensureUniqueShipmentReference(
            $site,
            $this->normalizeShipmentReference($providerResponse['shipment_reference'] ?? $payload['shipment_reference'] ?? null)
        );

        $shipment = $this->repository->createShipment($targetOrder, [
            'provider_slug' => $providerSlug,
            'shipment_reference' => $shipmentReference,
            'tracking_number' => $this->normalizeTrackingNumber($providerResponse['tracking_number'] ?? $payload['tracking_number'] ?? null),
            'tracking_url' => $this->normalizeTrackingUrl($providerResponse['tracking_url'] ?? $payload['tracking_url'] ?? null),
            'status' => $status,
            'shipped_at' => in_array($status, [EcommerceShipment::STATUS_DISPATCHED, EcommerceShipment::STATUS_IN_TRANSIT, EcommerceShipment::STATUS_DELIVERED], true)
                ? now()
                : null,
            'delivered_at' => $status === EcommerceShipment::STATUS_DELIVERED ? now() : null,
            'cancelled_at' => $status === EcommerceShipment::STATUS_CANCELLED ? now() : null,
            'last_tracked_at' => now(),
            'meta_json' => $this->normalizeMeta($providerResponse['metadata'] ?? null, $payload['meta_json'] ?? null),
            'created_by' => $actor?->id,
            'updated_by' => $actor?->id,
        ]);

        $this->recordEvent(
            $shipment,
            eventType: EcommerceShipmentEvent::TYPE_CREATED,
            status: $status,
            message: 'Shipment created',
            payload: [
                'provider_slug' => $providerSlug,
                'provider_response' => $providerResponse,
            ],
            actor: $actor
        );

        $this->syncOrderFulfillmentStatus($site, $targetOrder);

        $resolved = $this->repository->findShipmentByOrderAndId($site, $targetOrder, $shipment->id) ?? $shipment;

        return [
            'site_id' => $site->id,
            'order_id' => $targetOrder->id,
            'shipment' => $this->serializeShipment($resolved),
        ];
    }

    public function refreshTrackingForOrder(
        Site $site,
        EcommerceOrder $order,
        EcommerceShipment $shipment,
        array $payload = [],
        ?User $actor = null
    ): array {
        $targetOrder = $this->assertOrderBelongsToSite($site, $order);
        $targetShipment = $this->assertShipmentBelongsToOrder($site, $targetOrder, $shipment);

        $courier = $this->couriers->resolveCourierForSite($site, $targetShipment->provider_slug, false);
        if (! $courier instanceof CourierPlugin) {
            throw new EcommerceDomainException('Courier provider is unavailable for tracking.', 422);
        }

        try {
            $providerResponse = $courier->track([
                'site_id' => $site->id,
                'order_id' => $targetOrder->id,
                'order_number' => $targetOrder->order_number,
                'shipment_reference' => $targetShipment->shipment_reference,
                'tracking_number' => $targetShipment->tracking_number,
                'status' => $payload['status_override'] ?? null,
                'meta_json' => is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : [],
            ]);
        } catch (\Throwable) {
            throw new EcommerceDomainException('Failed to refresh shipment tracking.', 422, [
                'provider_slug' => $targetShipment->provider_slug,
            ]);
        }

        $status = $this->normalizeStatus($providerResponse['status'] ?? null, $targetShipment->status);
        $patchedShipment = $this->repository->updateShipment($targetShipment, [
            'status' => $status,
            'tracking_number' => $this->normalizeTrackingNumber($providerResponse['tracking_number'] ?? $targetShipment->tracking_number),
            'tracking_url' => $this->normalizeTrackingUrl($providerResponse['tracking_url'] ?? $targetShipment->tracking_url),
            'shipped_at' => in_array($status, [EcommerceShipment::STATUS_DISPATCHED, EcommerceShipment::STATUS_IN_TRANSIT, EcommerceShipment::STATUS_DELIVERED], true)
                ? ($targetShipment->shipped_at ?? now())
                : $targetShipment->shipped_at,
            'delivered_at' => $status === EcommerceShipment::STATUS_DELIVERED
                ? ($targetShipment->delivered_at ?? now())
                : $targetShipment->delivered_at,
            'cancelled_at' => $status === EcommerceShipment::STATUS_CANCELLED
                ? ($targetShipment->cancelled_at ?? now())
                : $targetShipment->cancelled_at,
            'last_tracked_at' => now(),
            'meta_json' => $this->normalizeMeta($targetShipment->meta_json, $providerResponse['metadata'] ?? null),
            'updated_by' => $actor?->id,
        ]);

        $this->recordEvent(
            $patchedShipment,
            eventType: EcommerceShipmentEvent::TYPE_TRACKING_UPDATE,
            status: $status,
            message: 'Shipment tracking refreshed',
            payload: [
                'provider_response' => $providerResponse,
                'status_override' => $this->nullableString($payload['status_override'] ?? null),
            ],
            actor: $actor
        );

        $this->syncOrderFulfillmentStatus($site, $targetOrder);

        $resolved = $this->repository->findShipmentByOrderAndId($site, $targetOrder, $patchedShipment->id) ?? $patchedShipment;

        return [
            'site_id' => $site->id,
            'order_id' => $targetOrder->id,
            'shipment' => $this->serializeShipment($resolved),
        ];
    }

    public function cancelForOrder(
        Site $site,
        EcommerceOrder $order,
        EcommerceShipment $shipment,
        array $payload = [],
        ?User $actor = null
    ): array {
        $targetOrder = $this->assertOrderBelongsToSite($site, $order);
        $targetShipment = $this->assertShipmentBelongsToOrder($site, $targetOrder, $shipment);

        $courier = $this->couriers->resolveCourierForSite($site, $targetShipment->provider_slug, false);
        if (! $courier instanceof CourierPlugin) {
            throw new EcommerceDomainException('Courier provider is unavailable for cancellation.', 422);
        }

        try {
            $providerResponse = $courier->cancelShipment([
                'site_id' => $site->id,
                'order_id' => $targetOrder->id,
                'order_number' => $targetOrder->order_number,
                'shipment_reference' => $targetShipment->shipment_reference,
                'tracking_number' => $targetShipment->tracking_number,
            ]);
        } catch (\Throwable) {
            throw new EcommerceDomainException('Failed to cancel shipment on courier provider.', 422, [
                'provider_slug' => $targetShipment->provider_slug,
            ]);
        }

        $status = $this->normalizeStatus($providerResponse['status'] ?? null, EcommerceShipment::STATUS_CANCELLED);
        $patchedShipment = $this->repository->updateShipment($targetShipment, [
            'status' => $status,
            'cancelled_at' => now(),
            'last_tracked_at' => now(),
            'meta_json' => $this->normalizeMeta($targetShipment->meta_json, [
                'cancel_reason' => $this->nullableString($payload['reason'] ?? null),
            ]),
            'updated_by' => $actor?->id,
        ]);

        $this->recordEvent(
            $patchedShipment,
            eventType: EcommerceShipmentEvent::TYPE_CANCELLED,
            status: $status,
            message: $this->nullableString($payload['reason'] ?? null) ?? 'Shipment cancelled',
            payload: [
                'provider_response' => $providerResponse,
            ],
            actor: $actor
        );

        $this->syncOrderFulfillmentStatus($site, $targetOrder);

        $resolved = $this->repository->findShipmentByOrderAndId($site, $targetOrder, $patchedShipment->id) ?? $patchedShipment;

        return [
            'site_id' => $site->id,
            'order_id' => $targetOrder->id,
            'shipment' => $this->serializeShipment($resolved),
        ];
    }

    public function trackPublic(Site $site, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array
    {
        $this->assertStorefrontAccessible($site, $viewer, $allowDraftPreview);

        $orderNumber = $this->nullableString($payload['order_number'] ?? null);
        $shipmentReference = $this->nullableString($payload['shipment_reference'] ?? null);
        $trackingNumber = $this->nullableString($payload['tracking_number'] ?? null);
        $customerEmail = $this->nullableString($payload['customer_email'] ?? null);

        if ($orderNumber === null) {
            throw new EcommerceDomainException('order_number is required for shipment tracking.', 422);
        }

        if ($shipmentReference === null && $trackingNumber === null) {
            throw new EcommerceDomainException('shipment_reference or tracking_number is required.', 422);
        }

        $shipment = $shipmentReference
            ? $this->repository->findShipmentBySiteAndReference($site, $shipmentReference)
            : $this->repository->findShipmentBySiteAndTrackingNumber($site, (string) $trackingNumber);

        if (! $shipment || ! $shipment->order) {
            throw new EcommerceDomainException('Shipment not found.', 404);
        }

        if (strtoupper((string) $shipment->order->order_number) !== strtoupper($orderNumber)) {
            throw new EcommerceDomainException('Shipment not found.', 404);
        }

        if (
            $customerEmail !== null
            && $shipment->order->customer_email !== null
            && strtolower($shipment->order->customer_email) !== strtolower($customerEmail)
        ) {
            throw new EcommerceDomainException('Shipment not found.', 404);
        }

        $courier = $this->couriers->resolveCourierForSite($site, $shipment->provider_slug, false);
        if ($courier instanceof CourierPlugin) {
            try {
                $providerResponse = $courier->track([
                    'site_id' => $site->id,
                    'order_id' => $shipment->order_id,
                    'order_number' => $shipment->order->order_number,
                    'shipment_reference' => $shipment->shipment_reference,
                    'tracking_number' => $shipment->tracking_number,
                ]);

                $status = $this->normalizeStatus($providerResponse['status'] ?? null, $shipment->status);
                $shipment = $this->repository->updateShipment($shipment, [
                    'status' => $status,
                    'tracking_number' => $this->normalizeTrackingNumber($providerResponse['tracking_number'] ?? $shipment->tracking_number),
                    'tracking_url' => $this->normalizeTrackingUrl($providerResponse['tracking_url'] ?? $shipment->tracking_url),
                    'shipped_at' => in_array($status, [EcommerceShipment::STATUS_DISPATCHED, EcommerceShipment::STATUS_IN_TRANSIT, EcommerceShipment::STATUS_DELIVERED], true)
                        ? ($shipment->shipped_at ?? now())
                        : $shipment->shipped_at,
                    'delivered_at' => $status === EcommerceShipment::STATUS_DELIVERED
                        ? ($shipment->delivered_at ?? now())
                        : $shipment->delivered_at,
                    'cancelled_at' => $status === EcommerceShipment::STATUS_CANCELLED
                        ? ($shipment->cancelled_at ?? now())
                        : $shipment->cancelled_at,
                    'last_tracked_at' => now(),
                    'meta_json' => $this->normalizeMeta($shipment->meta_json, $providerResponse['metadata'] ?? null),
                ]);

                $this->recordEvent(
                    $shipment,
                    eventType: EcommerceShipmentEvent::TYPE_PUBLIC_TRACK,
                    status: $status,
                    message: 'Public shipment tracking viewed',
                    payload: [
                        'provider_response' => $providerResponse,
                    ],
                    actor: $viewer
                );

                $order = $this->repository->findOrderBySiteAndId($site, $shipment->order_id);
                if ($order) {
                    $this->syncOrderFulfillmentStatus($site, $order);
                }
            } catch (\Throwable) {
                // Public tracking endpoint should still return last persisted status.
            }
        }

        $resolved = $shipmentReference
            ? $this->repository->findShipmentBySiteAndReference($site, $shipment->shipment_reference)
            : $this->repository->findShipmentBySiteAndTrackingNumber($site, (string) $shipment->tracking_number);

        if (! $resolved || ! $resolved->order) {
            throw new EcommerceDomainException('Shipment not found.', 404);
        }

        return [
            'site_id' => $site->id,
            'tracking' => [
                'order_number' => $resolved->order->order_number,
                'customer_email_masked' => $this->maskEmail($resolved->order->customer_email),
                'shipment' => $this->serializeShipment($resolved),
            ],
        ];
    }

    private function assertOrderBelongsToSite(Site $site, EcommerceOrder $order): EcommerceOrder
    {
        $target = $this->repository->findOrderBySiteAndId($site, $order->id);
        if (! $target) {
            throw new EcommerceDomainException('Order not found.', 404);
        }

        return $target;
    }

    private function assertShipmentBelongsToOrder(Site $site, EcommerceOrder $order, EcommerceShipment $shipment): EcommerceShipment
    {
        $target = $this->repository->findShipmentByOrderAndId($site, $order, $shipment->id);
        if (! $target) {
            throw new EcommerceDomainException('Shipment not found.', 404);
        }

        return $target;
    }

    private function assertStorefrontAccessible(Site $site, ?User $viewer, bool $allowDraftPreview = false): void
    {
        if ($site->status === 'archived') {
            throw new EcommerceDomainException('Storefront not found.', 404);
        }

        $site->loadMissing(['project.user', 'project.template']);
        $project = $site->project;
        if (! $project) {
            throw new EcommerceDomainException('Storefront not found.', 404);
        }

        $draftPreviewAllowedForViewer = $allowDraftPreview && $this->canPreviewDraftStorefront($site, $viewer);
        if (! $project->published_at && ! $draftPreviewAllowedForViewer) {
            throw new EcommerceDomainException('Storefront not found.', 404);
        }

        if ($project->published_visibility === 'private' && ! $draftPreviewAllowedForViewer) {
            $isOwner = $viewer && (string) $viewer->id === (string) $project->user_id;
            if (! $isOwner) {
                throw new EcommerceDomainException('Storefront not found.', 404);
            }
        }

        $modulesPayload = $this->modules->modules($site, $project->user);
        $isEnabled = false;
        foreach ($modulesPayload['modules'] ?? [] as $module) {
            if (($module['key'] ?? null) === 'ecommerce') {
                $isEnabled = (bool) ($module['available'] ?? false);
                break;
            }
        }

        if (! $isEnabled) {
            throw new EcommerceDomainException('Ecommerce module is not enabled for this site.', 404);
        }
    }

    private function canPreviewDraftStorefront(Site $site, ?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        if (method_exists($viewer, 'hasAdminBypass') && $viewer->hasAdminBypass()) {
            return true;
        }

        $project = $site->relationLoaded('project')
            ? $site->project
            : $site->project()->first();

        if (! $project) {
            return false;
        }

        return (string) $viewer->id === (string) $project->user_id;
    }

    private function syncOrderFulfillmentStatus(Site $site, EcommerceOrder $order): void
    {
        $shipments = $this->repository->listShipmentsByOrder($site, $order);
        if ($shipments->isEmpty()) {
            return;
        }

        $statuses = $shipments
            ->map(fn (EcommerceShipment $shipment): string => $shipment->status)
            ->values();

        $nextFulfillment = null;

        if ($statuses->every(fn (string $status): bool => $status === EcommerceShipment::STATUS_DELIVERED)) {
            $nextFulfillment = 'fulfilled';
        } elseif ($statuses->every(fn (string $status): bool => $status === EcommerceShipment::STATUS_CANCELLED)) {
            $nextFulfillment = 'cancelled';
        } elseif ($statuses->every(fn (string $status): bool => $status === EcommerceShipment::STATUS_RETURNED)) {
            $nextFulfillment = 'returned';
        } elseif ($statuses->contains(fn (string $status): bool => in_array($status, [
            EcommerceShipment::STATUS_CREATED,
            EcommerceShipment::STATUS_DISPATCHED,
            EcommerceShipment::STATUS_IN_TRANSIT,
            EcommerceShipment::STATUS_DELIVERED,
        ], true))) {
            $nextFulfillment = 'partial';
        }

        if ($nextFulfillment !== null && $order->fulfillment_status !== $nextFulfillment) {
            $this->repository->updateOrder($order, [
                'fulfillment_status' => $nextFulfillment,
            ]);
        }
    }

    private function recordEvent(
        EcommerceShipment $shipment,
        string $eventType,
        ?string $status,
        ?string $message,
        array $payload = [],
        ?User $actor = null
    ): void {
        $this->repository->createShipmentEvent($shipment, [
            'event_type' => $eventType,
            'status' => $status,
            'message' => $message,
            'payload_json' => $payload,
            'occurred_at' => now(),
            'created_by' => $actor?->id,
        ]);
    }

    private function ensureUniqueShipmentReference(Site $site, string $shipmentReference): string
    {
        $candidate = $shipmentReference;
        $attempt = 0;

        while ($attempt < 5) {
            $exists = $this->repository->findShipmentBySiteAndReference($site, $candidate) !== null;
            if (! $exists) {
                return $candidate;
            }

            $attempt++;
            $candidate = substr($shipmentReference, 0, 170).'-'.strtoupper(Str::random(8));
        }

        return substr('SHP-'.strtoupper(Str::random(24)), 0, 191);
    }

    private function normalizeShipmentReference(mixed $value): string
    {
        $reference = strtoupper(trim((string) ($value ?? '')));
        if ($reference === '') {
            $reference = 'SHP-'.now()->format('YmdHis').'-'.strtoupper(Str::random(6));
        }

        return substr($reference, 0, 191);
    }

    private function normalizeTrackingNumber(mixed $value): ?string
    {
        $tracking = trim((string) ($value ?? ''));
        if ($tracking === '') {
            return null;
        }

        return substr($tracking, 0, 191);
    }

    private function normalizeTrackingUrl(mixed $value): ?string
    {
        $url = trim((string) ($value ?? ''));
        if ($url === '') {
            return null;
        }

        return $url;
    }

    private function normalizeStatus(mixed $value, string $fallback): string
    {
        $status = strtolower(trim((string) $value));
        if ($status === '') {
            return $fallback;
        }

        return in_array($status, EcommerceShipment::allowedStatuses(), true)
            ? $status
            : $fallback;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * @param  mixed  ...$values
     * @return array<string,mixed>
     */
    private function normalizeMeta(...$values): array
    {
        $normalized = [];

        foreach ($values as $value) {
            if (! is_array($value)) {
                continue;
            }

            $normalized = array_replace($normalized, $value);
        }

        return $normalized;
    }

    private function maskEmail(?string $email): ?string
    {
        if (! is_string($email) || $email === '' || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);
        if ($local === '' || $domain === '') {
            return null;
        }

        if (strlen($local) <= 2) {
            return substr($local, 0, 1).'*@'.$domain;
        }

        return substr($local, 0, 2).str_repeat('*', max(1, strlen($local) - 2)).'@'.$domain;
    }

    /**
     * @return array<string,mixed>
     */
    private function serializeShipment(EcommerceShipment $shipment): array
    {
        /** @var Collection<int, EcommerceShipmentEvent> $events */
        $events = $shipment->events instanceof Collection ? $shipment->events : collect();

        return [
            'id' => $shipment->id,
            'site_id' => $shipment->site_id,
            'order_id' => $shipment->order_id,
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
            'created_at' => $shipment->created_at?->toISOString(),
            'updated_at' => $shipment->updated_at?->toISOString(),
            'events' => $events
                ->sortByDesc(fn (EcommerceShipmentEvent $event): int => $event->occurred_at?->getTimestamp() ?? 0)
                ->values()
                ->map(fn (EcommerceShipmentEvent $event): array => [
                    'id' => $event->id,
                    'event_type' => $event->event_type,
                    'status' => $event->status,
                    'message' => $event->message,
                    'payload_json' => $event->payload_json ?? [],
                    'occurred_at' => $event->occurred_at?->toISOString(),
                    'created_at' => $event->created_at?->toISOString(),
                    'created_by' => $event->created_by,
                ])
                ->all(),
        ];
    }
}
