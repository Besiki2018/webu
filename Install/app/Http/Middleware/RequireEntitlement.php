<?php

namespace App\Http\Middleware;

use App\Services\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireEntitlement
{
    public function __construct(
        protected EntitlementService $entitlements
    ) {}

    public function handle(Request $request, Closure $next, string ...$features): Response
    {
        $normalizedFeatures = $this->entitlements->normalizeFeatures($features);

        if ($normalizedFeatures === []) {
            return $next($request);
        }

        $missingFeature = $this->entitlements->firstMissing($request->user(), $normalizedFeatures);

        if ($missingFeature === null) {
            return $next($request);
        }

        $message = $this->entitlements->messageFor($missingFeature);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'error' => $message,
                'feature' => $missingFeature,
                'code' => 'entitlement_required',
            ], 403);
        }

        return redirect()
            ->route('billing.plans')
            ->with('error', $message);
    }
}

