<?php

namespace App\Services;

use App\Models\CmsBuilderDelta;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Site;

class CmsBuilderDeltaCaptureService
{
    public function captureAfterManualRevisionSave(
        Site $site,
        Page $page,
        ?PageRevision $previousRevision,
        PageRevision $newRevision,
        ?int $actorId = null,
        ?string $requestedLocale = null,
        ?string $siteLocale = null,
    ): ?CmsBuilderDelta {
        $normalizedRequestedLocale = $this->normalizeLocale($requestedLocale);
        $normalizedSiteLocale = $this->normalizeLocale($siteLocale ?: (is_string($site->locale) ? $site->locale : null));

        // G1 baseline: capture only primary site-locale edits to avoid mixing translation edits into learning deltas.
        if ($normalizedRequestedLocale !== null && $normalizedSiteLocale !== null && $normalizedRequestedLocale !== $normalizedSiteLocale) {
            return null;
        }

        $newContent = is_array($newRevision->content_json) ? $newRevision->content_json : null;
        if (! is_array($newContent)) {
            return null;
        }

        $previousContent = is_array($previousRevision?->content_json) ? $previousRevision->content_json : null;
        $aiGeneration = $this->resolveAiGenerationPayload($newContent, $previousContent);
        if (! is_array($aiGeneration)) {
            return null;
        }

        [$generationId, $generationIdSource] = $this->resolveGenerationId($aiGeneration);
        if ($generationId === null) {
            return null;
        }

        $baselineRevision = $this->resolveBaselineRevisionForGeneration($site, $page, $generationId) ?? $previousRevision;
        if (! $baselineRevision || ! is_array($baselineRevision->content_json)) {
            return null;
        }

        $baselineSnapshot = $this->normalizeSnapshotDocument($baselineRevision->content_json);
        $currentSnapshot = $this->normalizeSnapshotDocument($newContent);

        if ($this->valuesEquivalent($baselineSnapshot, $currentSnapshot)) {
            return null;
        }

        $patchOps = [];
        $this->buildJsonPatchOps($baselineSnapshot, $currentSnapshot, '', $patchOps);
        if ($patchOps === []) {
            return null;
        }

        $patchStats = $this->buildPatchStats($patchOps, $baselineRevision, $newRevision, $generationIdSource, $normalizedRequestedLocale, $normalizedSiteLocale);

        return CmsBuilderDelta::query()->create([
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'page_id' => $page->id,
            'baseline_revision_id' => $baselineRevision->id,
            'target_revision_id' => $newRevision->id,
            'generation_id' => $generationId,
            'locale' => $normalizedRequestedLocale ?: $normalizedSiteLocale,
            'captured_from' => 'panel_revision_save',
            'patch_ops' => $patchOps,
            'patch_stats_json' => $patchStats,
            'created_by' => $actorId,
        ]);
    }

    /**
     * @param  array<string, mixed>  $contentJson
     * @return array<string, mixed>
     */
    private function normalizeSnapshotDocument(array $contentJson): array
    {
        $snapshot = $this->deepSortAndNormalize($contentJson);
        unset($snapshot['ai_generation']);

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>|null  $newContent
     * @param  array<string, mixed>|null  $previousContent
     * @return array<string, mixed>|null
     */
    private function resolveAiGenerationPayload(?array $newContent, ?array $previousContent): ?array
    {
        $newAi = is_array($newContent['ai_generation'] ?? null) ? $newContent['ai_generation'] : null;
        if ($newAi !== null) {
            return $newAi;
        }

        return is_array($previousContent['ai_generation'] ?? null) ? $previousContent['ai_generation'] : null;
    }

    /**
     * @param  array<string, mixed>  $aiGeneration
     * @return array{0:?string,1:string}
     */
    private function resolveGenerationId(array $aiGeneration): array
    {
        $explicit = $this->safeString($aiGeneration['generation_id'] ?? null, 96);
        if ($explicit !== '') {
            return [$explicit, 'explicit'];
        }

        $fingerprintPayload = [
            'schema_version' => $aiGeneration['schema_version'] ?? null,
            'saved_via' => $aiGeneration['saved_via'] ?? null,
            'builder_nodes' => is_array($aiGeneration['builder_nodes'] ?? null) ? $aiGeneration['builder_nodes'] : [],
            'page_css' => is_string($aiGeneration['page_css'] ?? null) ? $aiGeneration['page_css'] : '',
            'route' => is_array($aiGeneration['route'] ?? null) ? $aiGeneration['route'] : [],
            'meta' => is_array($aiGeneration['meta'] ?? null) ? $aiGeneration['meta'] : [],
        ];

        $encoded = json_encode($fingerprintPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (! is_string($encoded) || $encoded === '') {
            return [null, 'none'];
        }

        return ['gen_'.substr(hash('sha256', $encoded), 0, 40), 'fingerprint'];
    }

    private function resolveBaselineRevisionForGeneration(Site $site, Page $page, string $generationId): ?PageRevision
    {
        /** @var \Illuminate\Support\Collection<int, PageRevision> $revisions */
        $revisions = PageRevision::query()
            ->where('site_id', (string) $site->id)
            ->where('page_id', $page->id)
            ->orderBy('version')
            ->get();

        foreach ($revisions as $revision) {
            $content = is_array($revision->content_json) ? $revision->content_json : null;
            if (! is_array($content)) {
                continue;
            }

            $aiGeneration = is_array($content['ai_generation'] ?? null) ? $content['ai_generation'] : null;
            if (! is_array($aiGeneration)) {
                continue;
            }

            [$candidateId] = $this->resolveGenerationId($aiGeneration);
            if ($candidateId === $generationId) {
                return $revision;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $ops
     * @return array<string, mixed>
     */
    private function buildPatchStats(array $ops, PageRevision $baselineRevision, PageRevision $targetRevision, string $generationIdSource, ?string $requestedLocale, ?string $siteLocale): array
    {
        $opTypeCounts = [];
        $paths = [];

        foreach ($ops as $op) {
            $type = is_string($op['op'] ?? null) ? $op['op'] : 'unknown';
            $opTypeCounts[$type] = (int) ($opTypeCounts[$type] ?? 0) + 1;

            $path = is_string($op['path'] ?? null) ? $op['path'] : '';
            if ($path !== '') {
                $paths[] = $path;
            }
        }

        $touchedSectionIndexes = array_values(array_unique(array_filter(array_map(function (string $path): ?int {
            if (preg_match('#^/sections/(\d+)#', $path, $matches) !== 1) {
                return null;
            }

            return (int) $matches[1];
        }, $paths), static fn ($value): bool => is_int($value))));

        return [
            'ops_count' => count($ops),
            'op_type_counts' => $opTypeCounts,
            'paths' => array_values(array_slice($paths, 0, 200)),
            'touched_sections_count' => count($touchedSectionIndexes),
            'touched_section_indexes' => $touchedSectionIndexes,
            'baseline_revision_id' => $baselineRevision->id,
            'baseline_revision_version' => $baselineRevision->version,
            'target_revision_id' => $targetRevision->id,
            'target_revision_version' => $targetRevision->version,
            'generation_id_source' => $generationIdSource,
            'requested_locale' => $requestedLocale,
            'site_locale' => $siteLocale,
            'is_primary_locale_capture' => $requestedLocale === null || $requestedLocale === $siteLocale,
        ];
    }

    /**
     * @param  mixed  $before
     * @param  mixed  $after
     * @param  list<array<string, mixed>>  $ops
     */
    private function buildJsonPatchOps(mixed $before, mixed $after, string $path, array &$ops): void
    {
        if ($this->valuesEquivalent($before, $after)) {
            return;
        }

        if (is_array($before) && is_array($after)) {
            $beforeAssoc = $this->isAssoc($before);
            $afterAssoc = $this->isAssoc($after);

            if ($beforeAssoc !== $afterAssoc) {
                if ($path !== '') {
                    $ops[] = ['op' => 'replace', 'path' => $path, 'value' => $after];
                }
                return;
            }

            if ($beforeAssoc) {
                $keys = array_values(array_unique(array_merge(array_keys($before), array_keys($after))));
                foreach ($keys as $key) {
                    $keyString = (string) $key;
                    $childPath = $path.'/'.self::encodeJsonPointerToken($keyString);
                    $hasBefore = array_key_exists($key, $before);
                    $hasAfter = array_key_exists($key, $after);

                    if (! $hasBefore && $hasAfter) {
                        $ops[] = ['op' => 'add', 'path' => $childPath, 'value' => $after[$key]];
                        continue;
                    }

                    if ($hasBefore && ! $hasAfter) {
                        $ops[] = ['op' => 'remove', 'path' => $childPath];
                        continue;
                    }

                    $this->buildJsonPatchOps($before[$key], $after[$key], $childPath, $ops);
                }

                return;
            }

            $max = max(count($before), count($after));
            for ($i = 0; $i < $max; $i++) {
                $childPath = $path.'/'.(string) $i;
                $hasBefore = array_key_exists($i, $before);
                $hasAfter = array_key_exists($i, $after);

                if (! $hasBefore && $hasAfter) {
                    $ops[] = ['op' => 'add', 'path' => $childPath, 'value' => $after[$i]];
                    continue;
                }

                if ($hasBefore && ! $hasAfter) {
                    $ops[] = ['op' => 'remove', 'path' => $childPath];
                    continue;
                }

                $this->buildJsonPatchOps($before[$i], $after[$i], $childPath, $ops);
            }

            return;
        }

        if ($path !== '') {
            $ops[] = ['op' => 'replace', 'path' => $path, 'value' => $after];
        }
    }

    private function valuesEquivalent(mixed $a, mixed $b): bool
    {
        if (is_array($a) && is_array($b)) {
            return json_encode($a, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) === json_encode($b, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $a === $b;
    }

    /**
     * @param  mixed  $value
     */
    private function deepSortAndNormalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if ($this->isAssoc($value)) {
            $normalized = [];
            $keys = array_keys($value);
            sort($keys, SORT_STRING);
            foreach ($keys as $key) {
                $normalized[$key] = $this->deepSortAndNormalize($value[$key]);
            }

            return $normalized;
        }

        return array_map(fn ($item) => $this->deepSortAndNormalize($item), $value);
    }

    private function normalizeLocale(?string $locale): ?string
    {
        if (! is_string($locale)) {
            return null;
        }

        $normalized = strtolower(trim($locale));
        return $normalized === '' ? null : $normalized;
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

    private static function encodeJsonPointerToken(string $token): string
    {
        return str_replace(['~', '/'], ['~0', '~1'], $token);
    }
}
