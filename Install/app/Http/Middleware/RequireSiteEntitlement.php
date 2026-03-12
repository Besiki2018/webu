<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Services\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireSiteEntitlement
{
    public function __construct(
        protected EntitlementService $entitlements
    ) {}

    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $requestUser = $request->user();
        if ($requestUser && method_exists($requestUser, 'hasAdminBypass') && $requestUser->hasAdminBypass()) {
            return $next($request);
        }

        $normalizedFeatures = $this->entitlements->normalizeFeatures($features);

        if ($normalizedFeatures === []) {
            return $next($request);
        }

        $site = $request->route('site');
        if (! $site instanceof Site) {
            return response()->json([
                'error' => 'Site context is required for this action.',
                'code' => 'site_context_required',
            ], 400);
        }

        $site->loadMissing('project.user.plan', 'project.user.activeSubscription.plan');
        $siteOwner = $site->project?->user;

        $missingFeature = $this->entitlements->firstMissing($siteOwner, $normalizedFeatures);
        if ($missingFeature === null) {
            return $next($request);
        }

        return response()->json([
            'error' => $this->entitlements->messageFor($missingFeature),
            'feature' => $missingFeature,
            'site_id' => $site->id,
            'code' => 'site_entitlement_required',
        ], 403);
    }
}
