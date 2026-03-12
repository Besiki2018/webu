<?php

namespace App\Ecommerce\Contracts;

use App\Models\EcommerceCart;
use App\Models\EcommerceCartItem;
use App\Models\EcommerceInventoryItem;
use App\Models\EcommerceInventoryLocation;
use App\Models\EcommerceOrder;
use App\Models\EcommerceProduct;
use App\Models\EcommerceProductVariant;
use App\Models\Site;
use App\Models\User;

interface EcommerceInventoryServiceContract
{
    /**
     * Ensure inventory snapshot exists and is aligned with product stock value.
     */
    public function syncInventorySnapshotForProduct(
        Site $site,
        EcommerceProduct $product,
        ?EcommerceProductVariant $variant = null,
        ?User $actor = null,
        string $reason = 'panel_stock_sync'
    ): EcommerceInventoryItem;

    /**
     * Reserve (or release) quantity for a cart item in tenant scope.
     */
    public function reserveForCartItem(
        Site $site,
        EcommerceCart $cart,
        EcommerceCartItem $cartItem,
        EcommerceProduct $product,
        ?EcommerceProductVariant $variant,
        int $quantity,
        ?User $actor = null
    ): void;

    /**
     * Release reservation for a cart item if it exists.
     */
    public function releaseForCartItem(
        Site $site,
        EcommerceCart $cart,
        EcommerceCartItem $cartItem,
        ?User $actor = null,
        string $reason = 'cart_item_removed'
    ): void;

    /**
     * Convert cart reservations to committed stock movement on checkout.
     */
    public function commitCartForOrder(
        Site $site,
        EcommerceCart $cart,
        EcommerceOrder $order,
        ?User $actor = null
    ): void;

    /**
     * Apply manual quantity delta on inventory item and persist ledger movement.
     */
    public function adjustInventoryItem(
        Site $site,
        EcommerceInventoryItem $inventoryItem,
        int $quantityDelta,
        ?string $reason = null,
        ?User $actor = null
    ): EcommerceInventoryItem;

    /**
     * Set counted quantity from stocktake and persist ledger movement.
     */
    public function stocktakeInventoryItem(
        Site $site,
        EcommerceInventoryItem $inventoryItem,
        int $countedQuantity,
        ?User $actor = null
    ): EcommerceInventoryItem;

    /**
     * Update inventory metadata/settings (location, threshold).
     *
     * @param  array<string, mixed>  $payload
     */
    public function updateInventoryItemSettings(
        Site $site,
        EcommerceInventoryItem $inventoryItem,
        array $payload,
        ?User $actor = null
    ): EcommerceInventoryItem;

    /**
     * Ensure site has one default location and return it.
     */
    public function ensureDefaultLocation(Site $site): EcommerceInventoryLocation;
}
