<?php

namespace App\Http\Middleware;

use App\Services\TenantProjectRequestResolver;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantContext
{
    public function __construct(
        protected TenantProjectRequestResolver $projectResolver
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $routeProjectIdentifier = $this->projectResolver->extractRouteProjectIdentifier($request);

        if (! $this->projectResolver->requestNeedsTenantContext($request)) {
            return $next($request);
        }

        $context = app(TenantContext::class);
        $routeProject = $this->projectResolver->extractRouteProject($request);

        if (! $context->hasProject() && $routeProject !== null) {
            $context->setProject($routeProject);
            $request->attributes->set('tenant_project', $routeProject);
        }

        if (! $context->hasProject()) {
            if ($routeProjectIdentifier !== null) {
                abort(404, 'Project not found.');
            }

            abort(400, 'Tenant context is required for this request.');
        }

        if ($routeProject !== null && $routeProject->id !== $context->projectId()) {
            abort(409, 'Tenant context mismatch detected.');
        }

        return $next($request);
    }
}
