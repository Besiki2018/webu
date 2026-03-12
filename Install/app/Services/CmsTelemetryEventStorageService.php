<?php

namespace App\Services;

use App\Models\CmsTelemetryEvent;
use App\Models\Site;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class CmsTelemetryEventStorageService
{
    public const DEFAULT_RETENTION_DAYS = 30;

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @param  array<string, mixed>|null  $route
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>
     */
    public function storeBatch(
        Site $site,
        Request $request,
        string $channel,
        string $source,
        ?string $sessionId,
        ?array $route,
        ?array $context,
        array $events,
    ): array {
        if ($events === []) {
            return [
                'stored' => 0,
                'retention_days' => $this->resolveRetentionDays(),
                'privacy' => $this->privacySummary(),
            ];
        }

        $now = now();
        $retentionDays = $this->resolveRetentionDays();
        $retentionExpiresAt = (clone $now)->addDays($retentionDays);

        $sessionHash = $this->hashIdentifier($sessionId, 'session');
        $clientIpHash = $this->hashIdentifier($request->ip(), 'ip');
        $actorScope = $request->user() ? 'authenticated' : 'guest';
        $actorHash = $this->hashIdentifier($request->user()?->getAuthIdentifier(), 'actor');
        $userAgentFamily = $this->normalizeUserAgentFamily($request->userAgent());

        $routePath = $this->safeString($route['path'] ?? null, 255);
        $routeSlug = $this->safeString($route['slug'] ?? null, 120);
        $routeParams = $this->sanitizeAssoc($route['params'] ?? null);
        $contextJson = $this->anonymizeStructuredData($this->sanitizeAssoc($context));

        $stored = 0;
        foreach ($events as $event) {
            $meta = $this->anonymizeStructuredData($this->sanitizeAssoc($event['meta'] ?? null));
            $occurredAt = $this->parseTimestamp($event['at'] ?? null) ?? $now;

            CmsTelemetryEvent::query()->create([
                'site_id' => (string) $site->id,
                'project_id' => (string) $site->project_id,
                'channel' => $this->safeString($channel, 20) ?: 'unknown',
                'source' => $this->safeString($source, 20) ?: 'unknown',
                'event_name' => $this->safeString($event['name'] ?? null, 120) ?: 'unknown',
                'occurred_at' => $occurredAt,
                'page_id' => is_int($event['page_id'] ?? null) ? $event['page_id'] : null,
                'page_slug' => $this->safeString($event['page_slug'] ?? null, 120) ?: null,
                'route_path' => $routePath ?: null,
                'route_slug' => $routeSlug ?: null,
                'route_params_json' => $routeParams,
                'context_json' => $contextJson,
                'meta_json' => $meta,
                'session_hash' => $sessionHash,
                'client_ip_hash' => $clientIpHash,
                'user_agent_family' => $userAgentFamily,
                'actor_scope' => $actorScope,
                'actor_hash' => $actorHash,
                'retention_expires_at' => $retentionExpiresAt,
                'anonymized_at' => $now,
            ]);
            $stored++;
        }

        return [
            'stored' => $stored,
            'retention_days' => $retentionDays,
            'privacy' => $this->privacySummary(),
        ];
    }

    /**
     * @return array{deleted:int,cutoff:string,retention_days:int}
     */
    public function pruneExpired(?Carbon $now = null): array
    {
        $clock = $now ? $now->copy() : now();
        $retentionDays = $this->resolveRetentionDays();

        $deleted = CmsTelemetryEvent::query()
            ->whereNotNull('retention_expires_at')
            ->where('retention_expires_at', '<=', $clock)
            ->delete();

        return [
            'deleted' => (int) $deleted,
            'cutoff' => $clock->toISOString(),
            'retention_days' => $retentionDays,
        ];
    }

    public function resolveRetentionDays(): int
    {
        $configured = (int) SystemSetting::get('data_retention_days_cms_telemetry', self::DEFAULT_RETENTION_DAYS);

        return max(1, min(3650, $configured));
    }

    /**
     * @return array<string, mixed>
     */
    public function privacySummary(): array
    {
        return [
            'version' => 'v1',
            'session_id' => 'hashed',
            'client_ip' => 'hashed',
            'user_agent' => 'family_only',
            'sensitive_keys' => ['email', 'phone', 'token', 'password', 'secret', 'authorization', 'cookie'],
        ];
    }

    private function hashIdentifier(mixed $value, string $namespace): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return null;
        }

        $key = (string) config('app.key', 'webu-cms-telemetry');

        return hash_hmac('sha256', $namespace.'|'.$normalized, $key);
    }

    private function normalizeUserAgentFamily(?string $userAgent): ?string
    {
        if (! is_string($userAgent) || trim($userAgent) === '') {
            return null;
        }

        $candidates = ['Chrome/', 'Firefox/', 'Safari/', 'Edg/', 'Opera/', 'PostmanRuntime/', 'curl/'];
        foreach ($candidates as $needle) {
            if (str_contains($userAgent, $needle)) {
                return strtolower(rtrim($needle, '/'));
            }
        }

        if (preg_match('/^([A-Za-z0-9_-]{1,40})\//', $userAgent, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return 'unknown';
    }

    private function parseTimestamp(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function sanitizeAssoc(mixed $value): ?array
    {
        if (! is_array($value)) {
            return null;
        }

        $result = [];
        foreach (array_slice($value, 0, 50, true) as $key => $item) {
            $safeKey = $this->safeString($key, 80);
            if ($safeKey === '') {
                continue;
            }
            $result[$safeKey] = $this->sanitizeValue($item, 0);
        }

        return $result;
    }

    private function sanitizeValue(mixed $value, int $depth): mixed
    {
        if ($depth > 4) {
            return null;
        }

        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $this->safeString($value, 500);
        }

        if (is_array($value)) {
            if ($this->isAssoc($value)) {
                return $this->sanitizeAssoc($value);
            }

            return array_values(array_map(fn ($item) => $this->sanitizeValue($item, $depth + 1), array_slice($value, 0, 25)));
        }

        return null;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>|null
     */
    private function anonymizeStructuredData(?array $payload): ?array
    {
        if (! is_array($payload)) {
            return null;
        }

        $result = [];
        foreach ($payload as $key => $value) {
            $lowerKey = strtolower((string) $key);
            if ($this->isSensitiveKey($lowerKey)) {
                $result[$key] = '[redacted]';
                continue;
            }

            if (is_string($value)) {
                $result[$key] = $this->redactSensitiveStringValue($value, $lowerKey);
                continue;
            }

            if (is_array($value)) {
                if ($this->isAssoc($value)) {
                    $result[$key] = $this->anonymizeStructuredData($this->sanitizeAssoc($value));
                } else {
                    $result[$key] = array_values(array_map(function ($item) {
                        if (is_array($item)) {
                            return $this->anonymizeStructuredData($this->sanitizeAssoc($item));
                        }
                        if (is_string($item)) {
                            return $this->redactSensitiveStringValue($item, '');
                        }

                        return $item;
                    }, $value));
                }
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    private function isSensitiveKey(string $lowerKey): bool
    {
        foreach (['email', 'phone', 'token', 'password', 'secret', 'authorization', 'cookie'] as $needle) {
            if (str_contains($lowerKey, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function redactSensitiveStringValue(string $value, string $lowerKey): string
    {
        if ($value === '') {
            return '';
        }

        if ($this->isSensitiveKey($lowerKey)) {
            return '[redacted]';
        }

        if (preg_match('/^[^@\s]+@[^@\s]+\.[^@\s]+$/', $value) === 1) {
            return '[redacted:email]';
        }

        if (preg_match('/\+?[0-9][0-9\-\s()]{6,}/', $value) === 1) {
            return '[redacted:phone]';
        }

        return $this->safeString($value, 500);
    }

    private function safeString(mixed $value, int $maxLength): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        if (mb_strlen($normalized) > $maxLength) {
            return mb_substr($normalized, 0, $maxLength);
        }

        return $normalized;
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isAssoc(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        return array_keys($value) !== range(0, count($value) - 1);
    }
}
