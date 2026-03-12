<?php

namespace App\Repositories\TenantScoped;

use App\Models\PageSection;
use Illuminate\Database\Eloquent\Collection;

/**
 * All reads/writes for page_sections MUST be scoped by tenant_id and website_id (and page_id).
 */
class PageSectionRepository
{
    /**
     * @return Collection<int, PageSection>
     */
    public function listByPage(string $tenantId, string $websiteId, int $pageId): Collection
    {
        return PageSection::query()
            ->where('tenant_id', $tenantId)
            ->where('website_id', $websiteId)
            ->where('page_id', $pageId)
            ->orderBy('order')
            ->get();
    }

    public function get(string $tenantId, string $websiteId, int $sectionId): ?PageSection
    {
        return PageSection::query()
            ->where('tenant_id', $tenantId)
            ->where('website_id', $websiteId)
            ->where('id', $sectionId)
            ->first();
    }

    public function update(string $tenantId, string $websiteId, int $sectionId, array $data): bool
    {
        return PageSection::query()
            ->where('tenant_id', $tenantId)
            ->where('website_id', $websiteId)
            ->where('id', $sectionId)
            ->update($data) > 0;
    }

    public function delete(string $tenantId, string $websiteId, int $sectionId): bool
    {
        return PageSection::query()
            ->where('tenant_id', $tenantId)
            ->where('website_id', $websiteId)
            ->where('id', $sectionId)
            ->delete() > 0;
    }
}
