<?php

namespace App\Ecommerce\Services;

use App\Ecommerce\Contracts\EcommerceInventoryServiceContract;
use App\Ecommerce\Exceptions\EcommerceDomainException;
use App\Models\EcommerceCart;
use App\Models\EcommerceCartItem;
use App\Models\EcommerceInventoryItem;
use App\Models\EcommerceInventoryLocation;
use App\Models\EcommerceInventoryReservation;
use App\Models\EcommerceOrder;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductVariant;
use App\Models\EcommerceStockMovement;
use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EcommerceInventoryService implements EcommerceInventoryServiceContract
{
    public function syncInventorySnapshotForProduct(
        Site $site,
        EcommerceProduct $product,
        ?EcommerceProductVariant $variant = null,
        ?User $actor = null,
        string $reason = 'panel_stock_sync'
    ): EcommerceInventoryItem {
        return DB::transaction(function () use ($site, $product, $variant, $actor, $reason): EcommerceInventoryItem {
            [$lockedProduct, $lockedVariant] = $this->lockSource($site, $product, $variant);
            [$stockTracking, $allowBackorder] = $this->sourceFlags($lockedProduct, $lockedVariant);
            $targetQuantity = $this->sourceStockQuantity($lockedProduct, $lockedVariant);
            $inventory = $this->lockOrCreateInventoryItem($site, $lockedProduct, $lockedVariant);

            $onHandBefore = (int) $inventory->quantity_on_hand;
            $reservedBefore = (int) $inventory->quantity_reserved;

            if (! $allowBackorder && $targetQuantity < $reservedBefore) {
                throw new EcommerceDomainException(
                    'Stock quantity cannot be lower than currently reserved quantity.',
                    422,
                    [
                        'reserved_quantity' => $reservedBefore,
                        'requested_quantity' => $targetQuantity,
                    ]
                );
            }

            if ($onHandBefore === $targetQuantity) {
                return $inventory;
            }

            $inventory->update([
                'quantity_on_hand' => $targetQuantity,
            ]);

            $movementType = $onHandBefore === 0 && $targetQuantity >= 0
                ? EcommerceStockMovement::TYPE_INITIALIZE
                : EcommerceStockMovement::TYPE_ADJUST;

            $this->recordMovement(
                $inventory->fresh(),
                movementType: $movementType,
                reason: $reason,
                quantityDelta: $targetQuantity - $onHandBefore,
                reservedDelta: 0,
                quantityOnHandBefore: $onHandBefore,
                quantityOnHandAfter: $targetQuantity,
                quantityReservedBefore: $reservedBefore,
                quantityReservedAfter: $reservedBefore,
                actor: $actor,
                meta: [
                    'stock_tracking' => $stockTracking,
                    'allow_backorder' => $allowBackorder,
                ]
            );

            return $inventory->fresh();
        });
    }

    public function reserveForCartItem(
        Site $site,
        EcommerceCart $cart,
        EcommerceCartItem $cartItem,
        EcommerceProduct $product,
        ?EcommerceProductVariant $variant,
        int $quantity,
        ?User $actor = null
    ): void {
        if ($quantity < 1) {
            throw new EcommerceDomainException('Reservation quantity must be at least 1.', 422);
        }

        DB::transaction(function () use ($site, $cart, $cartItem, $product, $variant, $quantity, $actor): void {
            $this->assertCartItemInSiteCart($site, $cart, $cartItem);
            [$lockedProduct, $lockedVariant] = $this->lockSource($site, $product, $variant);
            [$stockTracking, $allowBackorder] = $this->sourceFlags($lockedProduct, $lockedVariant);
            $inventory = $this->lockOrCreateInventoryItem($site, $lockedProduct, $lockedVariant);
            $reservation = $this->lockReservationByCartItem($site, $cartItem);

            $reservedBefore = (int) $inventory->quantity_reserved;
            $onHandBefore = (int) $inventory->quantity_on_hand;
            $currentReserved = (int) ($reservation?->quantity ?? 0);

            if (! $stockTracking || $allowBackorder) {
                if ($reservation && $currentReserved > 0) {
                    $reservedAfter = max(0, $reservedBefore - $currentReserved);
                    $inventory->update(['quantity_reserved' => $reservedAfter]);
                    $reservation->delete();

                    $this->recordMovement(
                        $inventory->fresh(),
                        movementType: EcommerceStockMovement::TYPE_RELEASE,
                        reason: ! $stockTracking ? 'tracking_disabled' : 'backorder_enabled',
                        quantityDelta: 0,
                        reservedDelta: -$currentReserved,
                        quantityOnHandBefore: $onHandBefore,
                        quantityOnHandAfter: $onHandBefore,
                        quantityReservedBefore: $reservedBefore,
                        quantityReservedAfter: $reservedAfter,
                        actor: $actor,
                        cart: $cart,
                        cartItem: $cartItem
                    );
                }

                return;
            }

            $delta = $quantity - $currentReserved;
            if ($delta === 0) {
                if ($reservation) {
                    $reservation->update([
                        'reserved_until' => $cart->expires_at,
                    ]);
                }

                return;
            }

            if ($delta > 0) {
                $available = $onHandBefore - $reservedBefore;
                if ($delta > $available) {
                    throw new EcommerceDomainException(
                        'Requested quantity exceeds available stock.',
                        422,
                        ['available_quantity' => max(0, $available + $currentReserved)]
                    );
                }
            }

            $reservedAfter = max(0, $reservedBefore + $delta);
            $inventory->update([
                'quantity_reserved' => $reservedAfter,
            ]);

            if ($reservation) {
                $reservation->update([
                    'quantity' => $quantity,
                    'reserved_until' => $cart->expires_at,
                    'meta_json' => [
                        ...($reservation->meta_json ?? []),
                        'updated_at' => now()->toISOString(),
                    ],
                ]);
            } else {
                EcommerceInventoryReservation::query()->create([
                    'site_id' => $site->id,
                    'inventory_item_id' => $inventory->id,
                    'product_id' => $lockedProduct->id,
                    'variant_id' => $lockedVariant?->id,
                    'cart_id' => $cart->id,
                    'cart_item_id' => $cartItem->id,
                    'quantity' => $quantity,
                    'reserved_until' => $cart->expires_at,
                    'meta_json' => [
                        'created_at' => now()->toISOString(),
                    ],
                ]);
            }

            $this->recordMovement(
                $inventory->fresh(),
                movementType: $delta > 0 ? EcommerceStockMovement::TYPE_RESERVE : EcommerceStockMovement::TYPE_RELEASE,
                reason: $delta > 0 ? 'cart_reserve' : 'cart_reservation_reduce',
                quantityDelta: 0,
                reservedDelta: $delta,
                quantityOnHandBefore: $onHandBefore,
                quantityOnHandAfter: $onHandBefore,
                quantityReservedBefore: $reservedBefore,
                quantityReservedAfter: $reservedAfter,
                actor: $actor,
                cart: $cart,
                cartItem: $cartItem
            );
        });
    }

    public function releaseForCartItem(
        Site $site,
        EcommerceCart $cart,
        EcommerceCartItem $cartItem,
        ?User $actor = null,
        string $reason = 'cart_item_removed'
    ): void {
        DB::transaction(function () use ($site, $cart, $cartItem, $actor, $reason): void {
            $this->assertCartItemInSiteCart($site, $cart, $cartItem);
            $reservation = $this->lockReservationByCartItem($site, $cartItem);
            if (! $reservation) {
                return;
            }

            $inventory = EcommerceInventoryItem::query()
                ->where('site_id', $site->id)
                ->where('id', $reservation->inventory_item_id)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                $reservation->delete();

                return;
            }

            $reservedBefore = (int) $inventory->quantity_reserved;
            $onHandBefore = (int) $inventory->quantity_on_hand;
            $releaseQty = min($reservedBefore, (int) $reservation->quantity);
            $reservedAfter = max(0, $reservedBefore - $releaseQty);

            $inventory->update([
                'quantity_reserved' => $reservedAfter,
            ]);

            $reservation->delete();

            $this->recordMovement(
                $inventory->fresh(),
                movementType: EcommerceStockMovement::TYPE_RELEASE,
                reason: $reason,
                quantityDelta: 0,
                reservedDelta: -$releaseQty,
                quantityOnHandBefore: $onHandBefore,
                quantityOnHandAfter: $onHandBefore,
                quantityReservedBefore: $reservedBefore,
                quantityReservedAfter: $reservedAfter,
                actor: $actor,
                cart: $cart,
                cartItem: $cartItem
            );
        });
    }

    public function commitCartForOrder(
        Site $site,
        EcommerceCart $cart,
        EcommerceOrder $order,
        ?User $actor = null
    ): void {
        DB::transaction(function () use ($site, $cart, $order, $actor): void {
            $cartItems = EcommerceCartItem::query()
                ->where('site_id', $site->id)
                ->where('cart_id', $cart->id)
                ->orderBy('id')
                ->get();

            /** @var Collection<int, EcommerceCartItem> $cartItems */
            foreach ($cartItems as $cartItem) {
                if (! $cartItem->product_id) {
                    $this->releaseForCartItem($site, $cart, $cartItem, $actor, 'checkout_cleanup_no_product');

                    continue;
                }

                $product = EcommerceProduct::query()
                    ->where('site_id', $site->id)
                    ->where('id', $cartItem->product_id)
                    ->lockForUpdate()
                    ->first();

                if (! $product) {
                    throw new EcommerceDomainException('Order item product is unavailable for stock commit.', 422);
                }

                $variant = null;
                if ($cartItem->variant_id) {
                    $variant = EcommerceProductVariant::query()
                        ->where('site_id', $site->id)
                        ->where('id', $cartItem->variant_id)
                        ->lockForUpdate()
                        ->first();

                    if (! $variant || (int) $variant->product_id !== (int) $product->id) {
                        throw new EcommerceDomainException('Order item variant is unavailable for stock commit.', 422);
                    }
                }

                [$stockTracking, $allowBackorder] = $this->sourceFlags($product, $variant);
                $inventory = $this->lockOrCreateInventoryItem($site, $product, $variant);
                $reservation = $this->lockReservationByCartItem($site, $cartItem);

                $commitQty = max(0, (int) $cartItem->quantity);
                $onHandBefore = (int) $inventory->quantity_on_hand;
                $reservedBefore = (int) $inventory->quantity_reserved;
                $reservedQty = (int) ($reservation?->quantity ?? 0);

                if (! $stockTracking) {
                    if ($reservation && $reservedQty > 0) {
                        $reservedAfter = max(0, $reservedBefore - $reservedQty);
                        $inventory->update(['quantity_reserved' => $reservedAfter]);
                        $reservation->delete();

                        $this->recordMovement(
                            $inventory->fresh(),
                            movementType: EcommerceStockMovement::TYPE_RELEASE,
                            reason: 'checkout_no_tracking_release',
                            quantityDelta: 0,
                            reservedDelta: -$reservedQty,
                            quantityOnHandBefore: $onHandBefore,
                            quantityOnHandAfter: $onHandBefore,
                            quantityReservedBefore: $reservedBefore,
                            quantityReservedAfter: $reservedAfter,
                            actor: $actor,
                            cart: $cart,
                            cartItem: $cartItem,
                            order: $order
                        );
                    }

                    continue;
                }

                if (! $allowBackorder && $reservedQty < $commitQty) {
                    $additionalReserve = $commitQty - $reservedQty;
                    $available = $onHandBefore - $reservedBefore;
                    if ($additionalReserve > $available) {
                        throw new EcommerceDomainException(
                            'Requested quantity exceeds available stock.',
                            422,
                            ['available_quantity' => max(0, $available + $reservedQty)]
                        );
                    }

                    $reservedAfterAutoReserve = $reservedBefore + $additionalReserve;
                    $inventory->update(['quantity_reserved' => $reservedAfterAutoReserve]);

                    if ($reservation) {
                        $reservation->update([
                            'quantity' => $reservedQty + $additionalReserve,
                            'reserved_until' => $cart->expires_at,
                        ]);
                    } else {
                        $reservation = EcommerceInventoryReservation::query()->create([
                            'site_id' => $site->id,
                            'inventory_item_id' => $inventory->id,
                            'product_id' => $product->id,
                            'variant_id' => $variant?->id,
                            'cart_id' => $cart->id,
                            'cart_item_id' => $cartItem->id,
                            'quantity' => $commitQty,
                            'reserved_until' => $cart->expires_at,
                            'meta_json' => [
                                'source' => 'checkout_auto_reserve',
                            ],
                        ]);
                    }

                    $this->recordMovement(
                        $inventory->fresh(),
                        movementType: EcommerceStockMovement::TYPE_RESERVE,
                        reason: 'checkout_auto_reserve',
                        quantityDelta: 0,
                        reservedDelta: $additionalReserve,
                        quantityOnHandBefore: $onHandBefore,
                        quantityOnHandAfter: $onHandBefore,
                        quantityReservedBefore: $reservedBefore,
                        quantityReservedAfter: $reservedAfterAutoReserve,
                        actor: $actor,
                        cart: $cart,
                        cartItem: $cartItem,
                        order: $order
                    );

                    $reservedBefore = $reservedAfterAutoReserve;
                    $reservedQty += $additionalReserve;
                }

                if ($commitQty === 0) {
                    if ($reservation) {
                        $reservation->delete();
                    }

                    continue;
                }

                $onHandAfter = $onHandBefore - $commitQty;
                $reservedAfter = max(0, $reservedBefore - $commitQty);

                if (! $allowBackorder && $onHandAfter < 0) {
                    throw new EcommerceDomainException(
                        'Requested quantity exceeds available stock.',
                        422,
                        ['available_quantity' => max(0, $onHandBefore)]
                    );
                }

                $inventory->update([
                    'quantity_on_hand' => $onHandAfter,
                    'quantity_reserved' => $reservedAfter,
                ]);

                $this->syncSourceStockQuantity($product, $variant, $onHandAfter);

                if ($reservation) {
                    $reservation->delete();
                }

                $this->recordMovement(
                    $inventory->fresh(),
                    movementType: EcommerceStockMovement::TYPE_COMMIT,
                    reason: 'checkout_commit',
                    quantityDelta: -$commitQty,
                    reservedDelta: -min($commitQty, $reservedBefore),
                    quantityOnHandBefore: $onHandBefore,
                    quantityOnHandAfter: $onHandAfter,
                    quantityReservedBefore: $reservedBefore,
                    quantityReservedAfter: $reservedAfter,
                    actor: $actor,
                    cart: $cart,
                    cartItem: $cartItem,
                    order: $order
                );
            }

            $this->releaseOrphanCartReservations($site, $cart, $actor, $order);
        });
    }

    public function adjustInventoryItem(
        Site $site,
        EcommerceInventoryItem $inventoryItem,
        int $quantityDelta,
        ?string $reason = null,
        ?User $actor = null
    ): EcommerceInventoryItem {
        if ($quantityDelta === 0) {
            throw new EcommerceDomainException('quantity_delta cannot be zero.', 422);
        }

        return DB::transaction(function () use ($site, $inventoryItem, $quantityDelta, $reason, $actor): EcommerceInventoryItem {
            $target = $this->lockInventoryItemBySite($site, $inventoryItem);
            $source = $this->lockSourceByInventory($site, $target);
            [$stockTracking, $allowBackorder] = $this->sourceFlags($source['product'], $source['variant']);

            if (! $stockTracking) {
                throw new EcommerceDomainException('Stock tracking is disabled for this item.', 422);
            }

            $onHandBefore = (int) $target->quantity_on_hand;
            $reservedBefore = (int) $target->quantity_reserved;
            $onHandAfter = $onHandBefore + $quantityDelta;

            if (! $allowBackorder && $onHandAfter < $reservedBefore) {
                throw new EcommerceDomainException(
                    'Stock quantity cannot be lower than currently reserved quantity.',
                    422,
                    [
                        'reserved_quantity' => $reservedBefore,
                        'requested_quantity' => $onHandAfter,
                    ]
                );
            }

            $target->update([
                'quantity_on_hand' => $onHandAfter,
            ]);

            $this->syncSourceStockQuantity($source['product'], $source['variant'], $onHandAfter);

            $this->recordMovement(
                $target->fresh(),
                movementType: EcommerceStockMovement::TYPE_ADJUST,
                reason: $reason ?? 'manual_adjustment',
                quantityDelta: $quantityDelta,
                reservedDelta: 0,
                quantityOnHandBefore: $onHandBefore,
                quantityOnHandAfter: $onHandAfter,
                quantityReservedBefore: $reservedBefore,
                quantityReservedAfter: $reservedBefore,
                actor: $actor,
                meta: [
                    'stock_tracking' => $stockTracking,
                    'allow_backorder' => $allowBackorder,
                ]
            );

            return $target->fresh(['product', 'variant', 'location']);
        });
    }

    public function stocktakeInventoryItem(
        Site $site,
        EcommerceInventoryItem $inventoryItem,
        int $countedQuantity,
        ?User $actor = null
    ): EcommerceInventoryItem {
        if ($countedQuantity < 0) {
            throw new EcommerceDomainException('counted_quantity must be zero or a positive number.', 422);
        }

        return DB::transaction(function () use ($site, $inventoryItem, $countedQuantity, $actor): EcommerceInventoryItem {
            $target = $this->lockInventoryItemBySite($site, $inventoryItem);
            $source = $this->lockSourceByInventory($site, $target);
            [$stockTracking, $allowBackorder] = $this->sourceFlags($source['product'], $source['variant']);

            if (! $stockTracking) {
                throw new EcommerceDomainException('Stock tracking is disabled for this item.', 422);
            }

            $onHandBefore = (int) $target->quantity_on_hand;
            $reservedBefore = (int) $target->quantity_reserved;
            $onHandAfter = $countedQuantity;
            $delta = $onHandAfter - $onHandBefore;

            if (! $allowBackorder && $onHandAfter < $reservedBefore) {
                throw new EcommerceDomainException(
                    'Stock quantity cannot be lower than currently reserved quantity.',
                    422,
                    [
                        'reserved_quantity' => $reservedBefore,
                        'requested_quantity' => $onHandAfter,
                    ]
                );
            }

            if ($delta === 0) {
                return $target->fresh(['product', 'variant', 'location']);
            }

            $target->update([
                'quantity_on_hand' => $onHandAfter,
            ]);

            $this->syncSourceStockQuantity($source['product'], $source['variant'], $onHandAfter);

            $this->recordMovement(
                $target->fresh(),
                movementType: EcommerceStockMovement::TYPE_STOCKTAKE,
                reason: 'stocktake_count',
                quantityDelta: $delta,
                reservedDelta: 0,
                quantityOnHandBefore: $onHandBefore,
                quantityOnHandAfter: $onHandAfter,
                quantityReservedBefore: $reservedBefore,
                quantityReservedAfter: $reservedBefore,
                actor: $actor,
                meta: [
                    'counted_quantity' => $countedQuantity,
                    'stock_tracking' => $stockTracking,
                    'allow_backorder' => $allowBackorder,
                ]
            );

            return $target->fresh(['product', 'variant', 'location']);
        });
    }

    public function updateInventoryItemSettings(
        Site $site,
        EcommerceInventoryItem $inventoryItem,
        array $payload,
        ?User $actor = null
    ): EcommerceInventoryItem {
        return DB::transaction(function () use ($site, $inventoryItem, $payload, $actor): EcommerceInventoryItem {
            $target = $this->lockInventoryItemBySite($site, $inventoryItem);
            $patch = [];

            if (array_key_exists('low_stock_threshold', $payload)) {
                $threshold = $payload['low_stock_threshold'];
                if ($threshold !== null) {
                    $threshold = (int) $threshold;
                    if ($threshold < 0) {
                        throw new EcommerceDomainException('low_stock_threshold must be a non-negative integer.', 422);
                    }
                }

                $patch['low_stock_threshold'] = $threshold;
            }

            if (array_key_exists('location_id', $payload)) {
                $locationId = $payload['location_id'];
                if ($locationId === null) {
                    $location = $this->ensureDefaultLocation($site);
                } else {
                    $location = EcommerceInventoryLocation::query()
                        ->where('site_id', $site->id)
                        ->where('id', (int) $locationId)
                        ->lockForUpdate()
                        ->first();

                    if (! $location) {
                        throw new EcommerceDomainException('Selected inventory location is invalid.', 422);
                    }
                }

                $patch['location_id'] = $location->id;
            }

            if ($patch !== []) {
                $target->update($patch);

                $this->recordMovement(
                    $target->fresh(),
                    movementType: EcommerceStockMovement::TYPE_ADJUST,
                    reason: 'inventory_settings_update',
                    quantityDelta: 0,
                    reservedDelta: 0,
                    quantityOnHandBefore: (int) $target->quantity_on_hand,
                    quantityOnHandAfter: (int) $target->quantity_on_hand,
                    quantityReservedBefore: (int) $target->quantity_reserved,
                    quantityReservedAfter: (int) $target->quantity_reserved,
                    actor: $actor,
                    meta: [
                        'settings_patch' => $patch,
                    ]
                );
            }

            return $target->fresh(['product', 'variant', 'location']);
        });
    }

    public function ensureDefaultLocation(Site $site): EcommerceInventoryLocation
    {
        return DB::transaction(function () use ($site): EcommerceInventoryLocation {
            $existing = EcommerceInventoryLocation::query()
                ->where('site_id', $site->id)
                ->where('is_default', true)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                return $existing;
            }

            $baseKey = 'main';
            $key = $baseKey;
            $suffix = 1;
            while (
                EcommerceInventoryLocation::query()
                    ->where('site_id', $site->id)
                    ->where('key', $key)
                    ->exists()
            ) {
                $suffix++;
                $key = "{$baseKey}-{$suffix}";
            }

            /** @var EcommerceInventoryLocation $location */
            $location = EcommerceInventoryLocation::query()->create([
                'site_id' => $site->id,
                'key' => $key,
                'name' => 'Main Warehouse',
                'status' => 'active',
                'is_default' => true,
                'notes' => null,
                'meta_json' => [
                    'auto_created' => true,
                ],
            ]);

            return $location;
        });
    }

    /**
     * @return array{0:EcommerceProduct,1:EcommerceProductVariant|null}
     */
    private function lockSource(
        Site $site,
        EcommerceProduct $product,
        ?EcommerceProductVariant $variant = null
    ): array {
        $lockedProduct = EcommerceProduct::query()
            ->where('site_id', $site->id)
            ->where('id', $product->id)
            ->lockForUpdate()
            ->first();

        if (! $lockedProduct) {
            throw new EcommerceDomainException('Product not found for inventory operation.', 404);
        }

        if (! $variant) {
            return [$lockedProduct, null];
        }

        $lockedVariant = EcommerceProductVariant::query()
            ->where('site_id', $site->id)
            ->where('id', $variant->id)
            ->lockForUpdate()
            ->first();

        if (! $lockedVariant || (int) $lockedVariant->product_id !== (int) $lockedProduct->id) {
            throw new EcommerceDomainException('Variant not found for inventory operation.', 404);
        }

        return [$lockedProduct, $lockedVariant];
    }

    private function lockOrCreateInventoryItem(
        Site $site,
        EcommerceProduct $product,
        ?EcommerceProductVariant $variant = null
    ): EcommerceInventoryItem {
        $query = EcommerceInventoryItem::query()
            ->where('site_id', $site->id)
            ->where('product_id', $product->id);

        if ($variant) {
            $query->where('variant_id', $variant->id);
        } else {
            $query->whereNull('variant_id');
        }

        $inventory = $query->lockForUpdate()->first();
        if ($inventory) {
            if (! $inventory->location_id) {
                $defaultLocation = $this->ensureDefaultLocation($site);
                $inventory->update([
                    'location_id' => $defaultLocation->id,
                ]);
            }

            return $inventory;
        }

        $defaultLocation = $this->ensureDefaultLocation($site);

        /** @var EcommerceInventoryItem $created */
        $created = EcommerceInventoryItem::query()->create([
            'site_id' => $site->id,
            'product_id' => $product->id,
            'variant_id' => $variant?->id,
            'location_id' => $defaultLocation->id,
            'sku' => $variant?->sku ?: $product->sku,
            'quantity_on_hand' => $this->sourceStockQuantity($product, $variant),
            'quantity_reserved' => 0,
            'low_stock_threshold' => null,
        ]);

        return EcommerceInventoryItem::query()
            ->where('id', $created->id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function lockInventoryItemBySite(Site $site, EcommerceInventoryItem $inventoryItem): EcommerceInventoryItem
    {
        $target = EcommerceInventoryItem::query()
            ->where('site_id', $site->id)
            ->where('id', $inventoryItem->id)
            ->lockForUpdate()
            ->first();

        if (! $target) {
            throw new EcommerceDomainException('Inventory item not found.', 404);
        }

        return $target;
    }

    /**
     * @return array{product:EcommerceProduct, variant:EcommerceProductVariant|null}
     */
    private function lockSourceByInventory(Site $site, EcommerceInventoryItem $inventoryItem): array
    {
        $product = EcommerceProduct::query()
            ->where('site_id', $site->id)
            ->where('id', $inventoryItem->product_id)
            ->lockForUpdate()
            ->first();

        if (! $product) {
            throw new EcommerceDomainException('Inventory product not found.', 404);
        }

        $variant = null;
        if ($inventoryItem->variant_id) {
            $variant = EcommerceProductVariant::query()
                ->where('site_id', $site->id)
                ->where('id', $inventoryItem->variant_id)
                ->lockForUpdate()
                ->first();

            if (! $variant || (int) $variant->product_id !== (int) $product->id) {
                throw new EcommerceDomainException('Inventory variant not found.', 404);
            }
        }

        return [
            'product' => $product,
            'variant' => $variant,
        ];
    }

    private function lockReservationByCartItem(
        Site $site,
        EcommerceCartItem $cartItem
    ): ?EcommerceInventoryReservation {
        return EcommerceInventoryReservation::query()
            ->where('site_id', $site->id)
            ->where('cart_item_id', $cartItem->id)
            ->lockForUpdate()
            ->first();
    }

    private function assertCartItemInSiteCart(Site $site, EcommerceCart $cart, EcommerceCartItem $cartItem): void
    {
        $exists = EcommerceCartItem::query()
            ->where('site_id', $site->id)
            ->where('cart_id', $cart->id)
            ->where('id', $cartItem->id)
            ->exists();

        if (! $exists) {
            throw new EcommerceDomainException('Cart item not found for inventory operation.', 404);
        }
    }

    /**
     * @return array{0:bool,1:bool}
     */
    private function sourceFlags(EcommerceProduct $product, ?EcommerceProductVariant $variant = null): array
    {
        return [
            $variant ? (bool) $variant->stock_tracking : (bool) $product->stock_tracking,
            $variant ? (bool) $variant->allow_backorder : (bool) $product->allow_backorder,
        ];
    }

    private function sourceStockQuantity(EcommerceProduct $product, ?EcommerceProductVariant $variant = null): int
    {
        return (int) ($variant ? $variant->stock_quantity : $product->stock_quantity);
    }

    private function syncSourceStockQuantity(
        EcommerceProduct $product,
        ?EcommerceProductVariant $variant,
        int $quantityOnHand
    ): void {
        if ($variant) {
            $variant->update([
                'stock_quantity' => $quantityOnHand,
            ]);

            return;
        }

        $product->update([
            'stock_quantity' => $quantityOnHand,
        ]);
    }

    private function releaseOrphanCartReservations(
        Site $site,
        EcommerceCart $cart,
        ?User $actor = null,
        ?EcommerceOrder $order = null
    ): void {
        $orphanReservations = EcommerceInventoryReservation::query()
            ->where('site_id', $site->id)
            ->where('cart_id', $cart->id)
            ->lockForUpdate()
            ->get();

        /** @var Collection<int, EcommerceInventoryReservation> $orphanReservations */
        foreach ($orphanReservations as $reservation) {
            $inventory = EcommerceInventoryItem::query()
                ->where('site_id', $site->id)
                ->where('id', $reservation->inventory_item_id)
                ->lockForUpdate()
                ->first();

            if (! $inventory) {
                $reservation->delete();

                continue;
            }

            $reservedBefore = (int) $inventory->quantity_reserved;
            $onHandBefore = (int) $inventory->quantity_on_hand;
            $releaseQty = min($reservedBefore, (int) $reservation->quantity);
            $reservedAfter = max(0, $reservedBefore - $releaseQty);

            $inventory->update([
                'quantity_reserved' => $reservedAfter,
            ]);

            $reservation->delete();

            $this->recordMovement(
                $inventory->fresh(),
                movementType: EcommerceStockMovement::TYPE_RELEASE,
                reason: 'checkout_orphan_cleanup',
                quantityDelta: 0,
                reservedDelta: -$releaseQty,
                quantityOnHandBefore: $onHandBefore,
                quantityOnHandAfter: $onHandBefore,
                quantityReservedBefore: $reservedBefore,
                quantityReservedAfter: $reservedAfter,
                actor: $actor,
                cart: $cart,
                order: $order
            );
        }
    }

    private function recordMovement(
        EcommerceInventoryItem $inventoryItem,
        string $movementType,
        ?string $reason,
        int $quantityDelta,
        int $reservedDelta,
        int $quantityOnHandBefore,
        int $quantityOnHandAfter,
        int $quantityReservedBefore,
        int $quantityReservedAfter,
        ?User $actor = null,
        ?EcommerceCart $cart = null,
        ?EcommerceCartItem $cartItem = null,
        ?EcommerceOrder $order = null,
        ?array $meta = null
    ): void {
        EcommerceStockMovement::query()->create([
            'site_id' => $inventoryItem->site_id,
            'inventory_item_id' => $inventoryItem->id,
            'product_id' => $inventoryItem->product_id,
            'variant_id' => $inventoryItem->variant_id,
            'cart_id' => $cart?->id,
            'cart_item_id' => $cartItem?->id,
            'order_id' => $order?->id,
            'order_item_id' => null,
            'movement_type' => $movementType,
            'reason' => $reason,
            'quantity_delta' => $quantityDelta,
            'reserved_delta' => $reservedDelta,
            'quantity_on_hand_before' => $quantityOnHandBefore,
            'quantity_on_hand_after' => $quantityOnHandAfter,
            'quantity_reserved_before' => $quantityReservedBefore,
            'quantity_reserved_after' => $quantityReservedAfter,
            'meta_json' => $meta ?? [],
            'created_by' => $actor?->id,
        ]);
    }
}
