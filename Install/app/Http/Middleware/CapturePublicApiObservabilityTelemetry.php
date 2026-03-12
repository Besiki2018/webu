<?php

namespace App\Http\Middleware;

use App\Models\Site;
use App\Services\CmsTelemetryEventStorageService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class CapturePublicApiObservabilityTelemetry
{
    public function __construct(
        protected CmsTelemetryEventStorageService $storage
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $startNs = hrtime(true);
        $routeName = (string) ($request->route()?->getName() ?? '');
        $traceId = $this->resolveTraceId($request, $routeName);
        $flow = $this->resolveFlow($routeName);

        try {
            /** @var Response $response */
            $response = $next($request);
            $this->recordApiTelemetry($request, $response->getStatusCode(), $startNs, $routeName, $traceId, $flow, null);

            return $response;
        } catch (\Throwable $exception) {
            $statusCode = $this->resolveExceptionStatusCode($exception);
            $this->recordApiTelemetry($request, $statusCode, $startNs, $routeName, $traceId, $flow, $exception);

            throw $exception;
        }
    }

    private function recordApiTelemetry(
        Request $request,
        int $statusCode,
        int $startNs,
        string $routeName,
        string $traceId,
        ?string $flow,
        ?\Throwable $exception,
    ): void {
        if (! str_starts_with($routeName, 'public.sites.ecommerce.')) {
            return;
        }

        $routeSite = $request->route('site');
        if (! $routeSite instanceof Site) {
            return;
        }

        $durationMs = max(0, (int) round((hrtime(true) - $startNs) / 1_000_000));
        $eventMeta = [
            'trace_id' => $traceId,
            'flow' => $flow,
            'route_name' => $routeName,
            'method' => strtoupper($request->method()),
            'status_code' => $statusCode,
            'duration_ms' => $durationMs,
            'outcome' => $statusCode >= 400 ? 'error' : 'ok',
        ];
        if ($exception !== null) {
            $eventMeta['exception'] = class_basename($exception);
        }

        $route = [
            'path' => '/'.ltrim((string) $request->path(), '/'),
            'slug' => is_string($request->route('slug')) ? $request->route('slug') : null,
            'params' => $this->extractSafeRouteParams($request),
        ];

        Log::info('cms.api.request_completed', [
            'trace_id' => $traceId,
            'flow' => $flow,
            'route_name' => $routeName,
            'site_id' => (string) $routeSite->id,
            'project_id' => (string) $routeSite->project_id,
            'status_code' => $statusCode,
            'duration_ms' => $durationMs,
        ]);

        if (! Schema::hasTable('cms_telemetry_events')) {
            return;
        }

        try {
            $this->storage->storeBatch(
                $routeSite,
                $request,
                'public_api',
                'api',
                $this->resolveSessionId($request),
                $route,
                [
                    'surface' => 'public_api',
                    'module' => 'ecommerce',
                    'route_name' => $routeName,
                    'flow' => $flow,
                ],
                [[
                    'name' => 'cms_api.request_completed',
                    'at' => now()->toISOString(),
                    'page_id' => null,
                    'page_slug' => is_string($request->route('slug')) ? $request->route('slug') : null,
                    'meta' => $eventMeta,
                ]]
            );
        } catch (\Throwable $storageException) {
            Log::warning('cms.api.observability_telemetry_store_failed', [
                'trace_id' => $traceId,
                'route_name' => $routeName,
                'site_id' => (string) $routeSite->id,
                'status_code' => $statusCode,
                'duration_ms' => $durationMs,
                'error' => $storageException->getMessage(),
            ]);
        }
    }

    private function resolveTraceId(Request $request, string $routeName): string
    {
        $provided = trim((string) ($request->headers->get('X-Webu-Trace-Id') ?? $request->headers->get('X-Trace-Id') ?? ''));
        if ($provided !== '') {
            return substr($provided, 0, 120);
        }

        return 'api-'.substr(sha1($routeName.'|'.microtime(true).'|'.random_int(1, PHP_INT_MAX)), 0, 24);
    }

    private function resolveSessionId(Request $request): ?string
    {
        foreach ([
            $request->headers->get('X-Webu-Session-Id'),
            $request->headers->get('X-Session-Id'),
            $request->cookie('webu_session'),
        ] as $candidate) {
            if (is_string($candidate) && trim($candidate) !== '') {
                return substr(trim($candidate), 0, 160);
            }
        }

        return null;
    }

    private function resolveFlow(string $routeName): ?string
    {
        return match (true) {
            str_contains($routeName, '.carts.checkout') => 'checkout',
            str_contains($routeName, '.orders.payment.start') => 'checkout',
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    private function extractSafeRouteParams(Request $request): array
    {
        $params = [];
        foreach ((array) $request->route()?->parameters() as $key => $value) {
            if (! is_string($key) || $key === 'site') {
                continue;
            }

            if (is_string($value) || is_int($value)) {
                $params[$key] = substr((string) $value, 0, 160);
                continue;
            }

            if (is_object($value) && isset($value->id)) {
                $params[$key] = substr((string) $value->id, 0, 160);
            }
        }

        return $params;
    }

    private function resolveExceptionStatusCode(\Throwable $exception): int
    {
        $code = method_exists($exception, 'getStatusCode')
            ? (int) $exception->getStatusCode()
            : (int) $exception->getCode();

        return $code >= 400 && $code <= 599 ? $code : 500;
    }
}

