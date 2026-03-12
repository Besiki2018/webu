<?php

namespace App\Repositories\TenantScoped;

use App\Models\WebsiteRevision;
use Illuminate\Database\Eloquent\Collection;

class WebsiteRevisionRepository
{
    /**
     * @return Collection<int, WebsiteRevision>
     */
    public function listForWebsite(string $tenantId, string $websiteId, int $limit = 50): Collection
    {
        return WebsiteRevision::query()
            ->where('tenant_id', $tenantId)
            ->where('website_id', $websiteId)
            ->orderByDesc('version')
            ->limit($limit)
            ->get();
    }

    public function get(string $tenantId, string $websiteId, int $revisionId): ?WebsiteRevision
    {
        return WebsiteRevision::query()
            ->where('tenant_id', $tenantId)
            ->where('website_id', $websiteId)
            ->where('id', $revisionId)
            ->first();
    }
}
