<?php

namespace App\Ecommerce\Contracts;

use App\Models\EcommerceCategory;
use App\Models\Site;

interface EcommercePanelCategoryServiceContract
{
    /**
     * @return array{site_id: string, categories: array<int, array<string, mixed>>}
     */
    public function list(Site $site): array;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Site $site, array $payload): EcommerceCategory;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(Site $site, EcommerceCategory $category, array $payload): EcommerceCategory;

    public function delete(Site $site, EcommerceCategory $category): void;
}

