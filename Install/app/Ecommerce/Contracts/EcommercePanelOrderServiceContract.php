<?php

namespace App\Ecommerce\Contracts;

use App\Models\EcommerceOrder;
use App\Models\Site;

interface EcommercePanelOrderServiceContract
{
    /**
     * @return array{site_id: string, orders: array<int, array<string, mixed>>}
     */
    public function list(Site $site): array;

    /**
     * @return array{site_id: string, order: array<string, mixed>}
     */
    public function show(Site $site, EcommerceOrder $order): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Site $site, EcommerceOrder $order, array $payload): EcommerceOrder;

    public function delete(Site $site, EcommerceOrder $order): bool;
}
