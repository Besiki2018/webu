<?php

namespace App\Ecommerce\Contracts;

use App\Models\EcommerceOrder;
use App\Models\EcommerceRsExport;
use App\Models\Site;
use App\Models\User;

interface EcommerceRsReadinessServiceContract
{
    /**
     * @return array<string, mixed>
     */
    public function generateOrderExport(
        Site $site,
        EcommerceOrder $order,
        ?User $actor = null
    ): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listExports(Site $site, array $filters = []): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function readinessSummary(Site $site, array $filters = []): array;

    /**
     * @return array<string, mixed>
     */
    public function showExport(Site $site, EcommerceRsExport $export): array;
}
