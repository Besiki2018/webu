<?php

namespace App\Ecommerce\Services;

use App\Ecommerce\Contracts\EcommerceInventoryServiceContract;
use App\Ecommerce\Contracts\EcommercePanelInventoryServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Models\EcommerceInventoryItem;
use App\Models\EcommerceInventoryLocation;
use App\Models\EcommerceStockMovement;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EcommercePanelInventoryService implements EcommercePanelInventoryServiceContract
{
    public function __construct(
        protected EcommerceInventoryServiceContract $inventory
    ) {}

    public function dashboard(Site $site): array
    {
        $defaultLocation = $this->inventory->ensureDefaultLocation($site);

        EcommerceInventoryItem::query()
            ->where('site_id', $site->id)
            ->whereNull('location_id')
            ->update([
                'location_id' => $defaultLocation->id,
                'updated_at' => now(),
            ]);

        $items = EcommerceInventoryItem::query()
            ->where('site_id', $site->id)
            ->with([
                'product:id,site_id,name,slug,stock_tracking,allow_backorder',
                'variant:id,site_id,product_id,name,sku,stock_tracking,allow_backorder',
                'location:id,site_id,key,name,status,is_default',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get();

        $totalsByLocation = [];
        foreach ($items as $item) {
            $locationId = (int) ($item->location_id ?? $defaultLocation->id);
            if (! isset($totalsByLocation[$locationId])) {
                $totalsByLocation[$locationId] = [
                    'items_count' => 0,
                    'on_hand_total' => 0,
                    'reserved_total' => 0,
                    'available_total' => 0,
                ];
            }

            $onHand = (int) $item->quantity_on_hand;
            $reserved = (int) $item->quantity_reserved;
            $available = $onHand - $reserved;

            $totalsByLocation[$locationId]['items_count']++;
            $totalsByLocation[$locationId]['on_hand_total'] += $onHand;
            $totalsByLocation[$locationId]['reserved_total'] += $reserved;
            $totalsByLocation[$locationId]['available_total'] += $available;
        }

        $serializedItems = $items
            ->map(fn (EcommerceInventoryItem $item): array => $this->serializeInventoryItem($item, $defaultLocation))
            ->values()
            ->all();

        $locations = EcommerceInventoryLocation::query()
            ->where('site_id', $site->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        $serializedLocations = $locations
            ->map(fn (EcommerceInventoryLocation $location): array => $this->serializeLocation($location, $totalsByLocation))
            ->values()
            ->all();

        $summary = [
            'items_count' => count($serializedItems),
            'on_hand_total' => (int) array_sum(array_column($serializedItems, 'quantity_on_hand')),
            'reserved_total' => (int) array_sum(array_column($serializedItems, 'quantity_reserved')),
            'available_total' => (int) array_sum(array_column($serializedItems, 'available_quantity')),
            'low_stock_count' => count(array_filter($serializedItems, fn (array $item): bool => (bool) ($item['is_low_stock'] ?? false))),
        ];

        $movements = EcommerceStockMovement::query()
            ->where('site_id', $site->id)
            ->with([
                'product:id,site_id,name',
                'variant:id,site_id,product_id,name',
                'inventoryItem:id,site_id,sku',
                'creator:id,name',
            ])
            ->latest('id')
            ->limit(50)
            ->get();

        return [
            'site_id' => $site->id,
            'summary' => $summary,
            'locations' => $serializedLocations,
            'inventory_items' => $serializedItems,
            'low_stock_items' => array_values(array_filter($serializedItems, fn (array $item): bool => (bool) ($item['is_low_stock'] ?? false))),
            'recent_movements' => $movements
                ->map(fn (EcommerceStockMovement $movement): array => $this->serializeMovement($movement))
                ->values()
                ->all(),
        ];
    }

    public function createLocation(Site $site, array $payload): array
    {
        return DB::transaction(function () use ($site, $payload): array {
            $name = trim((string) ($payload['name'] ?? ''));
            if ($name === '') {
                throw new EcommerceDomainException('Location name is required.', 422);
            }

            $keySource = $payload['key'] ?? null;
            if (! is_string($keySource) || trim($keySource) === '') {
                $keySource = $name;
            }

            $key = $this->normalizeLocationKey($keySource);

            $status = (string) ($payload['status'] ?? 'active');
            if (! in_array($status, ['active', 'inactive'], true)) {
                throw new EcommerceDomainException('Location status is invalid.', 422);
            }

            $existingWithKey = EcommerceInventoryLocation::query()
                ->where('site_id', $site->id)
                ->where('key', $key)
                ->lockForUpdate()
                ->exists();

            if ($existingWithKey) {
                throw new EcommerceDomainException('Location key already exists for this site.', 422);
            }

            $existingLocations = EcommerceInventoryLocation::query()
                ->where('site_id', $site->id)
                ->lockForUpdate()
                ->get();

            $hasDefault = $existingLocations->contains(fn (EcommerceInventoryLocation $location): bool => (bool) $location->is_default);
            $isDefault = (bool) ($payload['is_default'] ?? false);
            if (! $hasDefault || $existingLocations->isEmpty()) {
                $isDefault = true;
            }

            if ($isDefault && $status !== 'active') {
                throw new EcommerceDomainException('Default inventory location must be active.', 422);
            }

            /** @var EcommerceInventoryLocation $created */
            $created = EcommerceInventoryLocation::query()->create([
                'site_id' => $site->id,
                'key' => $key,
                'name' => $name,
                'status' => $status,
                'is_default' => $isDefault,
                'notes' => $this->normalizeNullableString($payload['notes'] ?? null),
                'meta_json' => is_array($payload['meta_json'] ?? null) ? $payload['meta_json'] : null,
            ]);

            if ($isDefault) {
                EcommerceInventoryLocation::query()
                    ->where('site_id', $site->id)
                    ->where('id', '!=', $created->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return [
                'site_id' => $site->id,
                'location' => $this->serializeLocation($created->fresh()),
            ];
        });
    }

    public function updateLocation(Site $site, EcommerceInventoryLocation $location, array $payload): array
    {
        return DB::transaction(function () use ($site, $location, $payload): array {
            $target = EcommerceInventoryLocation::query()
                ->where('site_id', $site->id)
                ->where('id', $location->id)
                ->lockForUpdate()
                ->first();

            if (! $target) {
                throw new EcommerceDomainException('Inventory location not found.', 404);
            }

            $patch = [];

            if (array_key_exists('key', $payload)) {
                $nextKey = $this->normalizeLocationKey($payload['key']);
                $exists = EcommerceInventoryLocation::query()
                    ->where('site_id', $site->id)
                    ->where('key', $nextKey)
                    ->where('id', '!=', $target->id)
                    ->lockForUpdate()
                    ->exists();

                if ($exists) {
                    throw new EcommerceDomainException('Location key already exists for this site.', 422);
                }

                $patch['key'] = $nextKey;
            }

            if (array_key_exists('name', $payload)) {
                $name = trim((string) $payload['name']);
                if ($name === '') {
                    throw new EcommerceDomainException('Location name is required.', 422);
                }

                $patch['name'] = $name;
            }

            if (array_key_exists('status', $payload)) {
                $status = (string) $payload['status'];
                if (! in_array($status, ['active', 'inactive'], true)) {
                    throw new EcommerceDomainException('Location status is invalid.', 422);
                }

                $patch['status'] = $status;
            }

            if (array_key_exists('notes', $payload)) {
                $patch['notes'] = $this->normalizeNullableString($payload['notes']);
            }

            if (array_key_exists('meta_json', $payload)) {
                $patch['meta_json'] = is_array($payload['meta_json']) ? $payload['meta_json'] : null;
            }

            $currentIsDefault = (bool) $target->is_default;
            $nextIsDefault = array_key_exists('is_default', $payload)
                ? (bool) $payload['is_default']
                : $currentIsDefault;
            $nextStatus = (string) ($patch['status'] ?? $target->status);

            if ($nextIsDefault && $nextStatus !== 'active') {
                throw new EcommerceDomainException('Default inventory location must be active.', 422);
            }

            if (! $nextIsDefault) {
                $hasAnotherDefault = EcommerceInventoryLocation::query()
                    ->where('site_id', $site->id)
                    ->where('is_default', true)
                    ->where('id', '!=', $target->id)
                    ->lockForUpdate()
                    ->exists();

                if (! $hasAnotherDefault) {
                    throw new EcommerceDomainException('At least one default inventory location is required.', 422);
                }
            }

            if ($nextIsDefault !== $currentIsDefault || array_key_exists('is_default', $payload)) {
                $patch['is_default'] = $nextIsDefault;
            }

            if ($patch !== []) {
                $target->update($patch);
            }

            if ($nextIsDefault) {
                EcommerceInventoryLocation::query()
                    ->where('site_id', $site->id)
                    ->where('id', '!=', $target->id)
                    ->where('is_default', true)
                    ->update(['is_default' => false]);
            }

            return [
                'site_id' => $site->id,
                'location' => $this->serializeLocation($target->fresh()),
            ];
        });
    }

    public function updateItemSettings(
        Site $site,
        EcommerceInventoryItem $inventoryItem,
        array $payload,
        ?User $actor = null
    ): array {
        $updated = $this->inventory->updateInventoryItemSettings($site, $inventoryItem, $payload, $actor);

        return [
            'site_id' => $site->id,
            'inventory_item' => $this->serializeInventoryItem($updated),
        ];
    }

    public function adjustItem(
        Site $site,
        EcommerceInventoryItem $inventoryItem,
        int $quantityDelta,
        ?string $reason = null,
        ?User $actor = null
    ): array {
        $updated = $this->inventory->adjustInventoryItem($site, $inventoryItem, $quantityDelta, $reason, $actor);

        return [
            'site_id' => $site->id,
            'inventory_item' => $this->serializeInventoryItem($updated),
        ];
    }

    public function stocktakeItem(
        Site $site,
        EcommerceInventoryItem $inventoryItem,
        int $countedQuantity,
        ?User $actor = null
    ): array {
        $updated = $this->inventory->stocktakeInventoryItem($site, $inventoryItem, $countedQuantity, $actor);

        return [
            'site_id' => $site->id,
            'inventory_item' => $this->serializeInventoryItem($updated),
        ];
    }

    /**
     * @param  array<int, array{items_count:int,on_hand_total:int,reserved_total:int,available_total:int}>  $totalsByLocation
     * @return array<string, mixed>
     */
    private function serializeLocation(EcommerceInventoryLocation $location, array $totalsByLocation = []): array
    {
        $totals = $totalsByLocation[$location->id] ?? [
            'items_count' => 0,
            'on_hand_total' => 0,
            'reserved_total' => 0,
            'available_total' => 0,
        ];

        return [
            'id' => $location->id,
            'site_id' => $location->site_id,
            'key' => $location->key,
            'name' => $location->name,
            'status' => $location->status,
            'is_default' => (bool) $location->is_default,
            'notes' => $location->notes,
            'items_count' => (int) $totals['items_count'],
            'on_hand_total' => (int) $totals['on_hand_total'],
            'reserved_total' => (int) $totals['reserved_total'],
            'available_total' => (int) $totals['available_total'],
            'created_at' => $location->created_at?->toISOString(),
            'updated_at' => $location->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeInventoryItem(
        EcommerceInventoryItem $inventoryItem,
        ?EcommerceInventoryLocation $fallbackLocation = null
    ): array {
        $item = $inventoryItem->loadMissing([
            'product:id,site_id,name,slug,stock_tracking,allow_backorder',
            'variant:id,site_id,product_id,name,sku,stock_tracking,allow_backorder',
            'location:id,site_id,key,name,status,is_default',
        ]);

        $location = $item->location;
        if (! $location && $fallbackLocation) {
            $location = $fallbackLocation;
        }

        $onHand = (int) $item->quantity_on_hand;
        $reserved = (int) $item->quantity_reserved;
        $available = $onHand - $reserved;
        $lowStockThreshold = $item->low_stock_threshold !== null
            ? (int) $item->low_stock_threshold
            : null;

        $stockTracking = $item->variant
            ? (bool) $item->variant->stock_tracking
            : (bool) ($item->product?->stock_tracking ?? true);
        $allowBackorder = $item->variant
            ? (bool) $item->variant->allow_backorder
            : (bool) ($item->product?->allow_backorder ?? false);

        return [
            'id' => $item->id,
            'site_id' => $item->site_id,
            'product_id' => $item->product_id,
            'product_name' => $item->product?->name,
            'product_slug' => $item->product?->slug,
            'variant_id' => $item->variant_id,
            'variant_name' => $item->variant?->name,
            'sku' => $item->sku ?: $item->variant?->sku,
            'location_id' => $location?->id,
            'location_key' => $location?->key,
            'location_name' => $location?->name,
            'location_status' => $location?->status,
            'quantity_on_hand' => $onHand,
            'quantity_reserved' => $reserved,
            'available_quantity' => $available,
            'low_stock_threshold' => $lowStockThreshold,
            'is_low_stock' => $lowStockThreshold !== null && $available <= $lowStockThreshold,
            'stock_tracking' => $stockTracking,
            'allow_backorder' => $allowBackorder,
            'updated_at' => $item->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMovement(EcommerceStockMovement $movement): array
    {
        return [
            'id' => $movement->id,
            'inventory_item_id' => $movement->inventory_item_id,
            'product_id' => $movement->product_id,
            'product_name' => $movement->product?->name,
            'variant_id' => $movement->variant_id,
            'variant_name' => $movement->variant?->name,
            'sku' => $movement->inventoryItem?->sku,
            'movement_type' => $movement->movement_type,
            'reason' => $movement->reason,
            'quantity_delta' => (int) $movement->quantity_delta,
            'reserved_delta' => (int) $movement->reserved_delta,
            'quantity_on_hand_before' => (int) $movement->quantity_on_hand_before,
            'quantity_on_hand_after' => (int) $movement->quantity_on_hand_after,
            'quantity_reserved_before' => (int) $movement->quantity_reserved_before,
            'quantity_reserved_after' => (int) $movement->quantity_reserved_after,
            'meta_json' => $movement->meta_json ?? [],
            'created_by' => $movement->created_by,
            'created_by_name' => $movement->creator?->name,
            'created_at' => $movement->created_at?->toISOString(),
        ];
    }

    private function normalizeLocationKey(mixed $value): string
    {
        $normalized = Str::slug(trim((string) $value));
        if ($normalized === '') {
            throw new EcommerceDomainException('Location key is required.', 422);
        }

        return Str::limit($normalized, 64, '');
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }
}
