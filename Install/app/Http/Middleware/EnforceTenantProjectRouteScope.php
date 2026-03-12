<?php

namespace App\Http\Middleware;

use App\Contracts\TenantProjectRouteScopeValidatorContract;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceTenantProjectRouteScope
{
    public function __construct(
        protected TenantProjectRouteScopeValidatorContract $scopeValidator
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $report = $this->scopeValidator->validate($request);

        if ((bool) ($report['ok'] ?? false)) {
            $request->attributes->set('tenant_scope_validation', $report);

            return $next($request);
        }

        return $this->scopeMismatchResponse($request, $report);
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function scopeMismatchResponse(Request $request, array $report): Response
    {
        $payload = [
            'error' => 'Route scope mismatch detected.',
            'code' => 'tenant_scope_route_binding_mismatch',
            'violations' => is_array($report['errors'] ?? null) ? $report['errors'] : [],
        ];

        if ($request->expectsJson() || $request->is('panel/*') || $request->is('public/sites/*')) {
            return new JsonResponse($payload, 404);
        }

        abort(404, 'Route scope mismatch detected.');
    }
}

