<?php

namespace App\Ecommerce\Contracts;

use App\Contracts\CourierPlugin;
use App\Models\Site;

interface EcommerceCourierConfigServiceContract
{
    /**
     * @return array{
     *   site_id:string,
     *   couriers:array<int,array<string,mixed>>
     * }
     */
    public function listForSite(Site $site): array;

    /**
     * @param  array<string,mixed>  $payload
     * @return array{
     *   site_id:string,
     *   courier:array<string,mixed>
     * }
     */
    public function updateForSite(Site $site, string $courierSlug, array $payload = []): array;

    /**
     * @return array<int,array{
     *   slug:string,
     *   courier:CourierPlugin
     * }>
     */
    public function enabledCouriersForStorefront(Site $site): array;

    public function resolveCourierForSite(Site $site, string $courierSlug, bool $requireEnabled = true): ?CourierPlugin;
}
