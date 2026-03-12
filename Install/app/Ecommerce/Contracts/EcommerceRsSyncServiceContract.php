<?php

namespace App\Ecommerce\Contracts;

use App\Models\EcommerceRsExport;
use App\Models\EcommerceRsSync;
use App\Models\Site;
use App\Models\User;

interface EcommerceRsSyncServiceContract
{
    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function queueExportSync(
        Site $site,
        EcommerceRsExport $export,
        ?User $actor = null,
        array $meta = []
    ): array;

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listSyncs(Site $site, array $filters = []): array;

    /**
     * @return array<string, mixed>
     */
    public function showSync(Site $site, EcommerceRsSync $sync): array;

    /**
     * @param  array<string, mixed>  $meta
     * @return array<string, mixed>
     */
    public function retrySync(
        Site $site,
        EcommerceRsSync $sync,
        ?User $actor = null,
        array $meta = []
    ): array;

    public function processSyncById(int $syncId): void;
}
