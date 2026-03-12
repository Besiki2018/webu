<?php

namespace App\Ecommerce\Contracts;

use App\Models\EcommerceInventoryItem;
use App\Models\EcommerceInventoryLocation;
use App\Models\Site;
use App\Models\User;

interface EcommercePanelInventoryServiceContract
{
    /**
     * @return array<string, mixed>
     */
    public function dashboard(Site $site): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function createLocation(Site $site, array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateLocation(Site $site, EcommerceInventoryLocation $location, array $payload): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function updateItemSettings(
        Site $site,
        EcommerceInventoryItem $inventoryItem,
        array $payload,
        ?User $actor = null
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function adjustItem(
        Site $site,
        EcommerceInventoryItem $inventoryItem,
        int $quantityDelta,
        ?string $reason = null,
        ?User $actor = null
    ): array;

    /**
     * @return array<string, mixed>
     */
    public function stocktakeItem(
        Site $site,
        EcommerceInventoryItem $inventoryItem,
        int $countedQuantity,
        ?User $actor = null
    ): array;
}
