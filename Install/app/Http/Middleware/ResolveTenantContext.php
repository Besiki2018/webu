<?php

namespace App\Http\Middleware;

use App\Services\TenantProjectRequestResolver;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    public function __construct(
        protected TenantProjectRequestResolver $projectResolver
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $context = app(TenantContext::class);
        $context->clear();

        $project = $this->projectResolver->resolveProject($request);
        if ($project !== null) {
            $context->setProject($project);
            $request->attributes->set('tenant_project', $project);
        }

        return $next($request);
    }
}
