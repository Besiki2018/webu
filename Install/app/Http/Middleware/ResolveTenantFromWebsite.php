<?php

namespace App\Http\Middleware;

use App\Models\Website;
use App\Support\TenancyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves tenant (and website) from route-bound Website and attaches to request + TenancyContext.
 * Use on admin CMS routes that have {website} in the path.
 */
class ResolveTenantFromWebsite
{
    public function __construct(
        protected TenancyContext $tenancy
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->tenancy->clear();

        $website = $request->route('website');
        if ($website instanceof Website) {
            $this->tenancy->setWebsite($website);
            $request->attributes->set('tenant_id', $this->tenancy->tenantId());
            $request->attributes->set('website_id', $this->tenancy->websiteId());
        }

        return $next($request);
    }
}
