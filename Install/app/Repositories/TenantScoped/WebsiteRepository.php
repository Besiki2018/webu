<?php

namespace App\Repositories\TenantScoped;

use App\Models\Website;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * All reads/writes for websites MUST go through this repo with tenant_id.
 * Do not use Website::query() directly from controllers.
 */
class WebsiteRepository
{
    public function get(string $tenantId, string $websiteId): ?Website
    {
        return Website::query()
            ->where('tenant_id', $tenantId)
            ->where('id', $websiteId)
            ->first();
    }

    public function listForTenant(string $tenantId, int $perPage = 20): LengthAwarePaginator
    {
        return Website::query()
            ->where('tenant_id', $tenantId)
            ->withCount('websitePages')
            ->orderBy('name')
            ->paginate($perPage);
    }

    /**
     * @return Collection<int, Website>
     */
    public function allForTenant(string $tenantId): Collection
    {
        return Website::query()
            ->where('tenant_id', $tenantId)
            ->orderBy('name')
            ->get();
    }

    public function updateTenantId(Website $website, string $tenantId): void
    {
        $website->tenant_id = $tenantId;
        $website->save();
    }
}
