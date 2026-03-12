<?php

namespace App\Services;

use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class CmsTelemetryCollectorService
{
    public const SCHEMA_VERSION = 'cms.telemetry.event.v1';

    private const MAX_EVENTS_PER_REQUEST = 25;

    public function __construct(
        protected ?CmsTelemetryEventStorageService $storage = null
    ) {}

    /**
     * Collect and normalize telemetry events from builder/runtime clients.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function collectFromRequest(Site $site, Request $request, array $context = []): array
    {
        $payload = $request->all();
        if (! is_array($payload)) {
            $payload = [];
        }

        return $this->collect($site, $payload, $request, $context);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function collect(Site $site, array $payload, Request $request, array $context = []): array
    {
        $warnings = [];
        $schemaVersion = $this->sanitizeString($payload['schema_version'] ?? null, 80) ?: self::SCHEMA_VERSION;
        $source = $this->normalizeSource($payload['source'] ?? null);
        if ($source === 'unknown') {
            $warnings[] = [
                'code' => 'unsupported_source',
                'path' => 'source',
                'message' => 'Telemetry source must be builder or runtime.',
            ];
        }

        $sessionId = $this->sanitizeString($payload['session_id'] ?? null, 160);
        $route = $this->normalizeRoute($payload['route'] ?? null, $request);
        $clientContext = $this->sanitizeObject($payload['context'] ?? null);

        $eventsRaw = is_array($payload['events'] ?? null) ? $payload['events'] : [];
        if ($eventsRaw === []) {
            $warnings[] = [
                'code' => 'events_required',
                'path' => 'events',
                'message' => 'Telemetry payload must include a non-empty events array.',
            ];
        }

        $acceptedEvents = [];
        $rejected = 0;

        foreach (array_slice($eventsRaw, 0, self::MAX_EVENTS_PER_REQUEST) as $index => $eventRaw) {
            $normalized = $this->normalizeEvent($eventRaw, $index, $warnings);
            if ($normalized === null) {
                $rejected++;
                continue;
            }

            $acceptedEvents[] = $normalized;
        }

        if (count($eventsRaw) > self::MAX_EVENTS_PER_REQUEST) {
            $warnings[] = [
                'code' => 'events_truncated',
                'path' => 'events',
                'message' => 'Telemetry events were truncated to the maximum allowed batch size.',
                'expected' => self::MAX_EVENTS_PER_REQUEST,
                'actual' => count($eventsRaw),
            ];
        }

        if ($acceptedEvents !== []) {
            Log::info('cms.telemetry.collector', [
                'schema_version' => self::SCHEMA_VERSION,
                'request_schema_version' => $schemaVersion,
                'collector_version' => 'v1',
                'channel' => $this->sanitizeString($context['channel'] ?? null, 32) ?: 'unknown',
                'source' => $source,
                'site_id' => (string) $site->id,
                'project_id' => (string) $site->project_id,
                'session_id' => $sessionId,
                'route' => $route,
                'context' => $clientContext,
                'request_meta' => [
                    'path' => $request->path(),
                    'method' => $request->method(),
                    'origin_present' => $request->headers->has('Origin'),
                    'referer_present' => $request->headers->has('Referer'),
                    'user_agent_present' => $request->userAgent() !== null,
                    'auth_user_present' => $request->user() !== null,
                ],
                'events' => $acceptedEvents,
            ]);
        }

        $storageSummary = [
            'stored' => 0,
            'retention_days' => 0,
            'privacy' => null,
        ];
        if ($acceptedEvents !== []) {
            try {
                if (Schema::hasTable('cms_telemetry_events')) {
                    $storageService = $this->storage ?? app(CmsTelemetryEventStorageService::class);
                    $storageSummary = $storageService->storeBatch(
                        $site,
                        $request,
                        $this->sanitizeString($context['channel'] ?? null, 32) ?: 'unknown',
                        $source,
                        $sessionId !== '' ? $sessionId : null,
                        is_array($route) ? $route : null,
                        $clientContext,
                        $acceptedEvents,
                    );
                } else {
                    $warnings[] = [
                        'code' => 'storage_table_missing',
                        'path' => 'storage',
                        'message' => 'Telemetry storage table is not available yet; events were accepted but not persisted.',
                    ];
                }
            } catch (\Throwable $exception) {
                $warnings[] = [
                    'code' => 'storage_failed',
                    'path' => 'storage',
                    'message' => 'Telemetry storage write failed; events were accepted but not persisted.',
                ];
                Log::warning('cms.telemetry.storage_failed', [
                    'site_id' => (string) $site->id,
                    'project_id' => (string) $site->project_id,
                    'source' => $source,
                    'channel' => $context['channel'] ?? null,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return [
            'ok' => true,
            'schema_version' => self::SCHEMA_VERSION,
            'collector' => 'cms_telemetry_collector_v1',
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'channel' => $this->sanitizeString($context['channel'] ?? null, 32) ?: 'unknown',
            'source' => $source,
            'accepted' => count($acceptedEvents),
            'rejected' => $rejected,
            'stored' => (int) ($storageSummary['stored'] ?? 0),
            'retention_days' => (int) ($storageSummary['retention_days'] ?? 0),
            'privacy' => $storageSummary['privacy'] ?? null,
            'warnings' => $warnings,
        ];
    }

    private function normalizeSource(mixed $value): string
    {
        $normalized = strtolower($this->sanitizeString($value, 32));

        return in_array($normalized, ['builder', 'runtime'], true)
            ? $normalized
            : 'unknown';
    }

    /**
     * @param  mixed  $value
     * @return array<string, mixed>|null
     */
    private function normalizeEvent(mixed $value, int $index, array &$warnings): ?array
    {
        if (! is_array($value)) {
            $warnings[] = [
                'code' => 'invalid_event_payload',
                'path' => "events.{$index}",
                'message' => 'Telemetry event must be an object.',
            ];

            return null;
        }

        $name = $this->sanitizeString($value['name'] ?? $value['event'] ?? $value['type'] ?? null, 120);
        if ($name === '' || ! preg_match('/^[a-z0-9._:-]{1,120}$/', $name)) {
            $warnings[] = [
                'code' => 'invalid_event_name',
                'path' => "events.{$index}.name",
                'message' => 'Telemetry event name is required and must use a safe canonical token format.',
                'actual' => is_scalar($value['name'] ?? null) ? (string) $value['name'] : null,
            ];

            return null;
        }

        $timestamp = $this->normalizeTimestamp($value['at'] ?? $value['timestamp'] ?? null);
        if ($timestamp === null) {
            $warnings[] = [
                'code' => 'invalid_event_timestamp',
                'path' => "events.{$index}.at",
                'message' => 'Invalid event timestamp provided; collector used current time.',
            ];
            $timestamp = now()->toISOString();
        }

        $pageIdRaw = $value['page_id'] ?? null;
        $pageId = null;
        if (is_int($pageIdRaw)) {
            $pageId = $pageIdRaw;
        } elseif (is_string($pageIdRaw) && ctype_digit($pageIdRaw)) {
            $pageId = (int) $pageIdRaw;
        }

        return [
            'name' => $name,
            'at' => $timestamp,
            'page_id' => $pageId,
            'page_slug' => $this->sanitizeString($value['page_slug'] ?? $value['slug'] ?? null, 120) ?: null,
            'meta' => $this->sanitizeObject($value['meta'] ?? null),
        ];
    }

    private function normalizeTimestamp(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->toISOString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizeRoute(mixed $value, Request $request): ?array
    {
        if (! is_array($value)) {
            return [
                'path' => $this->sanitizeString('/'.$request->path(), 255) ?: '/',
                'slug' => null,
                'params' => [],
            ];
        }

        $params = [];
        $rawParams = $value['params'] ?? null;
        if (is_array($rawParams)) {
            foreach ($rawParams as $key => $paramValue) {
                $safeKey = $this->sanitizeString($key, 80);
                if ($safeKey === '') {
                    continue;
                }

                if (is_string($paramValue) || is_int($paramValue) || is_float($paramValue) || is_bool($paramValue)) {
                    $params[$safeKey] = $this->sanitizeString((string) $paramValue, 160);
                }
            }
        }

        return [
            'path' => $this->sanitizeString($value['path'] ?? $value['route_path'] ?? ('/'.$request->path()), 255) ?: '/',
            'slug' => $this->sanitizeString($value['slug'] ?? $value['requested_slug'] ?? null, 120) ?: null,
            'params' => $params,
        ];
    }

    private function sanitizeString(mixed $value, int $maxLength): string
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
     * @return array<string, mixed>|null
     */
    private function sanitizeObject(mixed $value, int $depth = 0): ?array
    {
        if (! is_array($value) || $depth > 3) {
            return null;
        }

        $result = [];
        foreach (array_slice($value, 0, 40, true) as $key => $item) {
            $safeKey = $this->sanitizeString($key, 80);
            if ($safeKey === '') {
                continue;
            }

            $result[$safeKey] = $this->sanitizeMetaValue($item, $depth + 1);
        }

        return $result;
    }

    private function sanitizeMetaValue(mixed $value, int $depth): mixed
    {
        if ($depth > 4) {
            return null;
        }

        if (is_null($value) || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $this->sanitizeString($value, 500);
        }

        if (is_array($value)) {
            if ($this->isAssoc($value)) {
                return $this->sanitizeObject($value, $depth);
            }

            return array_values(array_map(fn ($item) => $this->sanitizeMetaValue($item, $depth + 1), array_slice($value, 0, 25)));
        }

        return null;
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
