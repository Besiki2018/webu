<?php

namespace App\Repositories\TenantScoped;

use App\Models\WebsitePage;
use Illuminate\Database\Eloquent\Collection;

/**
 * All reads/writes for website_pages MUST be scoped by tenant_id and website_id.
 */
class WebsitePageRepository
{
    /**
     * @return Collection<int, WebsitePage>
     */
    public function list(string $tenantId, string $websiteId): Collection
    {
        return WebsitePage::query()
            ->where('tenant_id', $tenantId)
            ->where('website_id', $websiteId)
            ->orderBy('order')
            ->get();
    }

    public function get(string $tenantId, string $websiteId, int $pageId): ?WebsitePage
    {
        return WebsitePage::query()
            ->where('tenant_id', $tenantId)
            ->where('website_id', $websiteId)
            ->where('id', $pageId)
            ->first();
    }

    public function getBySlug(string $tenantId, string $websiteId, string $slug): ?WebsitePage
    {
        return WebsitePage::query()
            ->where('tenant_id', $tenantId)
            ->where('website_id', $websiteId)
            ->where('slug', $slug)
            ->first();
    }

    public function store(string $tenantId, string $websiteId, array $data): WebsitePage
    {
        $data['tenant_id'] = $tenantId;
        $data['website_id'] = $websiteId;

        return WebsitePage::query()->create($data);
    }

    public function update(string $tenantId, string $websiteId, int $pageId, array $data): bool
    {
        return WebsitePage::query()
            ->where('tenant_id', $tenantId)
            ->where('website_id', $websiteId)
            ->where('id', $pageId)
            ->update($data) > 0;
    }

    public function delete(string $tenantId, string $websiteId, int $pageId): bool
    {
        return WebsitePage::query()
            ->where('tenant_id', $tenantId)
            ->where('website_id', $websiteId)
            ->where('id', $pageId)
            ->delete() > 0;
    }
}
