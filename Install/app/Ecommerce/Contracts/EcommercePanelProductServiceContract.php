<?php

namespace App\Ecommerce\Contracts;

use App\Models\EcommerceProduct;
use App\Models\Site;

interface EcommercePanelProductServiceContract
{
    /**
     * @return array{site_id: string, products: array<int, array<string, mixed>>}
     */
    public function list(Site $site): array;

    /**
     * @return array{site_id: string, product: array<string, mixed>}
     */
    public function show(Site $site, EcommerceProduct $product): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Site $site, array $payload): EcommerceProduct;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Site $site, EcommerceProduct $product, array $payload): EcommerceProduct;

    public function delete(Site $site, EcommerceProduct $product): void;
}

