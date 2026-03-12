<?php

namespace App\Support;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Assert tenant (and optional website) scope is set before data access.
 */
class TenantScopeGuard
{
    public function __construct(
        protected TenancyContext $tenancy
    ) {}

    /**
     * Throw if tenant is not set (403).
     */
    public function assertTenantScope(): void
    {
        if (! $this->tenancy->hasTenant()) {
            throw new HttpException(403, 'Tenant scope is required.');
        }
    }

    /**
     * Throw if website is not set (403).
     */
    public function assertWebsiteScope(): void
    {
        $this->assertTenantScope();
        if (! $this->tenancy->hasWebsite()) {
            throw new HttpException(403, 'Website scope is required.');
        }
    }

    /**
     * Verify the given website ID belongs to the current tenant.
     */
    public function assertWebsiteBelongsToTenant(string $websiteId, ?string $tenantId = null): void
    {
        $tenantId = $tenantId ?? $this->tenancy->requireTenantId();
        $website = \App\Models\Website::query()
            ->where('id', $websiteId)
            ->where('tenant_id', $tenantId)
            ->first();

        if ($website === null) {
            throw new HttpException(403, 'Website does not belong to tenant.');
        }
    }
}
