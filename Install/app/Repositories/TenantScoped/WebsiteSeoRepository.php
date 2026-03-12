<?php

namespace App\Repositories\TenantScoped;

use App\Models\WebsiteSeo;
use Illuminate\Database\Eloquent\Collection;

class WebsiteSeoRepository
{
    /**
     * @return Collection<int, WebsiteSeo>
     */
    public function listForWebsite(string $tenantId, string $websiteId): Collection
    {
        return WebsiteSeo::query()
            ->where('tenant_id', $tenantId)
            ->where('website_id', $websiteId)
            ->get();
    }

    public function get(string $tenantId, string $websiteId, int $seoId): ?WebsiteSeo
    {
        return WebsiteSeo::query()
            ->where('tenant_id', $tenantId)
            ->where('website_id', $websiteId)
            ->where('id', $seoId)
            ->first();
    }
}
