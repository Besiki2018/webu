<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProjectOperationGuardService
{
    private const IDEMPOTENCY_TTL_MINUTES = 30;

    /**
     * Execute a project operation with lock + optional idempotency replay.
     *
     * @param  callable():JsonResponse  $callback
     */
    public function execute(
        Request $request,
        Project $project,
        string $operation,
        callable $callback,
        ?array $payloadForHash = null,
        int $lockSeconds = 30
    ): JsonResponse {
        $idempotencyKey = $this->extractIdempotencyKey($request);
        $requestHash = null;

        if ($idempotencyKey !== null) {
            $requestHash = $this->hashPayload($payloadForHash ?? $request->all());
            $cacheKey = $this->idempotencyCacheKey($project, $operation, $idempotencyKey);
            $cached = Cache::get($cacheKey);

            if (is_array($cached)) {
                if (($cached['request_hash'] ?? null) !== $requestHash) {
                    return response()->json([
                        'error' => 'Idempotency key reuse detected with different payload.',
                    ], 409);
                }

                return response()
                    ->json($cached['body'] ?? [], (int) ($cached['status'] ?? 200))
                    ->header('X-Idempotent-Replay', 'true');
            }
        }

        $lock = $this->acquireLock($project, $operation, $lockSeconds);

        if ($lock === null) {
            return response()->json([
                'error' => 'Another operation is already in progress. Please retry shortly.',
            ], 409);
        }

        try {
            $response = $callback();

            if ($idempotencyKey !== null && $response->getStatusCode() < 500) {
                Cache::put(
                    $this->idempotencyCacheKey($project, $operation, $idempotencyKey),
                    [
                        'status' => $response->getStatusCode(),
                        'body' => $response->getData(true),
                        'request_hash' => $requestHash,
                        'stored_at' => now()->toIso8601String(),
                    ],
                    now()->addMinutes(self::IDEMPOTENCY_TTL_MINUTES)
                );
            }

            return $response;
        } finally {
            optional($lock)->release();
        }
    }

    private function extractIdempotencyKey(Request $request): ?string
    {
        $raw = $request->header('Idempotency-Key')
            ?? $request->header('X-Idempotency-Key')
            ?? $request->input('idempotency_key');

        if (! is_string($raw)) {
            return null;
        }

        $value = trim($raw);

        return $value === '' ? null : $value;
    }

    private function idempotencyCacheKey(Project $project, string $operation, string $key): string
    {
        return sprintf('project-op:idempotency:%s:%s:%s', $project->id, $operation, sha1($key));
    }

    private function lockKey(Project $project, string $operation): string
    {
        return sprintf('project-op:lock:%s:%s', $project->id, $operation);
    }

    /**
     * @return object|null Laravel lock instance or null when unavailable/acquire failed.
     */
    private function acquireLock(Project $project, string $operation, int $seconds): ?object
    {
        try {
            $lock = Cache::lock($this->lockKey($project, $operation), $seconds);

            return $lock->get() ? $lock : null;
        } catch (\Throwable $e) {
            Log::warning('Operation lock unavailable, continuing without lock.', [
                'project_id' => $project->id,
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);

            return new class
            {
                public function release(): void {}
            };
        }
    }

    private function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($this->normalizePayload($payload)));
    }

    private function normalizePayload(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->normalizePayload($item), $value);
        }

        ksort($value);

        foreach ($value as $key => $item) {
            $value[$key] = $this->normalizePayload($item);
        }

        return $value;
    }
}
