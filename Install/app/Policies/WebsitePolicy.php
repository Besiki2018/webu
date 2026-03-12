<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Website;
use App\Support\TenancyContext;

/**
 * Website belongs to tenant: only allow access when context tenant matches website.tenant_id.
 */
class WebsitePolicy
{
    public function __construct(
        protected TenancyContext $tenancy
    ) {}

    public function view(?User $user, Website $website): bool
    {
        return $this->websiteBelongsToContextTenant($website);
    }

    public function update(?User $user, Website $website): bool
    {
        return $this->websiteBelongsToContextTenant($website);
    }

    public function delete(?User $user, Website $website): bool
    {
        return $this->websiteBelongsToContextTenant($website);
    }

    private function websiteBelongsToContextTenant(Website $website): bool
    {
        $tenantId = $this->tenancy->tenantId();
        if ($tenantId === null) {
            return true;
        }

        return $website->tenant_id === $tenantId;
    }
}
