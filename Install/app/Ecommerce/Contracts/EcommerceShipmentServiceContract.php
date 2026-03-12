<?php

namespace App\Ecommerce\Contracts;

use App\Models\EcommerceOrder;
use App\Models\EcommerceShipment;
use App\Models\Site;
use App\Models\User;

interface EcommerceShipmentServiceContract
{
    /**
     * @return array{
     *   site_id:string,
     *   order_id:int,
     *   shipments:array<int,array<string,mixed>>
     * }
     */
    public function listForOrder(Site $site, EcommerceOrder $order): array;

    /**
     * @param  array<string,mixed>  $payload
     * @return array{
     *   site_id:string,
     *   order_id:int,
     *   shipment:array<string,mixed>
     * }
     */
    public function createForOrder(Site $site, EcommerceOrder $order, array $payload = [], ?User $actor = null): array;

    /**
     * @param  array<string,mixed>  $payload
     * @return array{
     *   site_id:string,
     *   order_id:int,
     *   shipment:array<string,mixed>
     * }
     */
    public function refreshTrackingForOrder(
        Site $site,
        EcommerceOrder $order,
        EcommerceShipment $shipment,
        array $payload = [],
        ?User $actor = null
    ): array;

    /**
     * @param  array<string,mixed>  $payload
     * @return array{
     *   site_id:string,
     *   order_id:int,
     *   shipment:array<string,mixed>
     * }
     */
    public function cancelForOrder(
        Site $site,
        EcommerceOrder $order,
        EcommerceShipment $shipment,
        array $payload = [],
        ?User $actor = null
    ): array;

    /**
     * @param  array<string,mixed>  $payload
     * @return array{
     *   site_id:string,
     *   tracking:array<string,mixed>
     * }
     */
    public function trackPublic(Site $site, array $payload = [], ?User $viewer = null, bool $allowDraftPreview = false): array;
}
