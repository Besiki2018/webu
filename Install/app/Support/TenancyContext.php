<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\Website;
use LogicException;

/**
 * Holds tenant and optional website scope for the current request.
 * Used by CMS (Universal) routes where scope is (tenant_id, website_id).
 */
class TenancyContext
{
    protected ?string $tenantId = null;

    protected ?string $websiteId = null;

    protected ?Tenant $tenant = null;

    protected ?Website $website = null;

    public function clear(): void
    {
        $this->tenantId = null;
        $this->websiteId = null;
        $this->tenant = null;
        $this->website = null;
    }

    public function setTenantId(string $tenantId): void
    {
        $this->tenantId = $tenantId;
        $this->tenant = null;
    }

    public function setWebsite(?Website $website): void
    {
        if ($website === null) {
            $this->website = null;
            $this->websiteId = null;

            return;
        }

        if ($this->tenantId !== null && $website->tenant_id !== null && $website->tenant_id !== $this->tenantId) {
            throw new LogicException(sprintf(
                'Tenancy context mismatch: website tenant [%s] does not match current tenant [%s].',
                $website->tenant_id,
                $this->tenantId
            ));
        }

        $this->website = $website;
        $this->websiteId = $website->id;
        if ($website->tenant_id !== null) {
            $this->tenantId = $website->tenant_id;
        }
    }

    public function tenantId(): ?string
    {
        return $this->tenantId;
    }

    public function websiteId(): ?string
    {
        return $this->websiteId;
    }

    public function tenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function website(): ?Website
    {
        return $this->website;
    }

    public function hasTenant(): bool
    {
        return $this->tenantId !== null;
    }

    public function hasWebsite(): bool
    {
        return $this->websiteId !== null;
    }

    /**
     * Require tenant scope; throw if missing.
     */
    public function requireTenantId(): string
    {
        if ($this->tenantId === null) {
            throw new LogicException('Tenant scope is required but not set.');
        }

        return $this->tenantId;
    }

    /**
     * Require website scope; throw if missing.
     */
    public function requireWebsiteId(): string
    {
        if ($this->websiteId === null) {
            throw new LogicException('Website scope is required but not set.');
        }

        return $this->websiteId;
    }
}
