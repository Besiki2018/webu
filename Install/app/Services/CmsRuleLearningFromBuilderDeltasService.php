<?php

namespace App\Services;

use App\Models\CmsBuilderDelta;
use App\Models\CmsLearnedRule;
use App\Models\PageRevision;
use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CmsRuleLearningFromBuilderDeltasService
{
    private const DEFAULT_MIN_OCCURRENCES = 2;

    /**
     * Cluster repeat manual builder fixes from cms_builder_deltas into deterministic candidate learned rules.
     *
     * @return array<string, mixed>
     */
    public function learnCandidateRules(
        string|Carbon|null $since = null,
        string|Carbon|null $until = null,
        ?Site $site = null,
        int $minOccurrences = self::DEFAULT_MIN_OCCURRENCES,
    ): array {
        $window = $this->normalizeWindow($since, $until);
        $threshold = max(2, min(1000, $minOccurrences));

        $query = CmsBuilderDelta::query()
            ->orderBy('id');

        if ($site instanceof Site) {
            $query->where('site_id', (string) $site->id);
        }

        $query->whereBetween('created_at', [$window['since'], $window['until']]);

        /** @var Collection<int, CmsBuilderDelta> $deltas */
        $deltas = $query->get();
        if ($deltas->isEmpty()) {
            return [
                'ok' => true,
                'since' => $window['since']->toISOString(),
                'until' => $window['until']->toISOString(),
                'source_deltas' => 0,
                'eligible_ops' => 0,
                'clusters' => 0,
                'upserted' => 0,
                'min_occurrences' => $threshold,
                'rows' => [],
            ];
        }

        $baselineRevisionIds = $deltas
            ->pluck('baseline_revision_id')
            ->filter(fn ($value): bool => is_int($value) || (is_string($value) && ctype_digit($value)))
            ->map(fn ($value): int => (int) $value)
            ->unique()
            ->values();

        /** @var Collection<int, PageRevision> $baselineRevisions */
        $baselineRevisions = PageRevision::query()
            ->whereIn('id', $baselineRevisionIds)
            ->get()
            ->keyBy('id');

        $clusters = [];
        $eligibleOps = 0;

        foreach ($deltas as $delta) {
            $baselineRevision = $baselineRevisions->get((int) $delta->baseline_revision_id);
            $baselineContent = is_array($baselineRevision?->content_json) ? $baselineRevision->content_json : null;
            if (! is_array($baselineContent)) {
                continue;
            }

            $aiGeneration = is_array($baselineContent['ai_generation'] ?? null) ? $baselineContent['ai_generation'] : [];
            $sections = is_array($baselineContent['sections'] ?? null) ? $baselineContent['sections'] : [];
            $storeType = $this->resolveStoreType($delta, $baselineContent);
            $promptTags = $this->normalizePromptTags(data_get($aiGeneration, 'meta.prompt_tags'));
            $pageTemplateKey = $this->safeString(data_get($aiGeneration, 'route.template_key'), 80) ?: null;
            $locale = $this->safeString($delta->locale, 10) ?: null;
            $patchOps = is_array($delta->patch_ops) ? $delta->patch_ops : [];

            foreach ($patchOps as $op) {
                $candidate = $this->normalizePatchOpCandidate($op, $sections);
                if ($candidate === null) {
                    continue;
                }

                $eligibleOps++;
                $signature = [
                    'scope' => 'tenant',
                    'site_id' => (string) $delta->site_id,
                    'project_id' => (string) $delta->project_id,
                    'store_type' => $storeType,
                    'prompt_intent_tags' => $promptTags,
                    'page_template_key' => $pageTemplateKey,
                    'component_type' => $candidate['component_type'],
                    'op' => $candidate['op'],
                    'path_pattern' => $candidate['path_pattern'],
                    'path_suffix' => $candidate['path_suffix'],
                    'value' => $candidate['value'],
                ];

                $signatureEncoded = $this->canonicalJsonEncode($signature);
                if ($signatureEncoded === null) {
                    continue;
                }

                $clusterKey = 'lr_'.substr(hash('sha256', $signatureEncoded), 0, 40);
                if (! isset($clusters[$clusterKey])) {
                    $clusters[$clusterKey] = [
                        'rule_key' => $clusterKey,
                        'site_id' => (string) $delta->site_id,
                        'project_id' => (string) $delta->project_id,
                        'scope' => 'tenant',
                        'signature' => $signature,
                        'patch_template' => [
                            'version' => 'p6-g2-02.v1',
                            'format' => 'json_patch_template',
                            'strategy' => 'component_type_path_suffix',
                            'op' => $candidate['op'],
                            'path_pattern' => $candidate['path_pattern'],
                            'path_suffix' => $candidate['path_suffix'],
                            'component_type' => $candidate['component_type'],
                            'value' => $candidate['value'],
                        ],
                        'evidence' => [
                            'delta_ids' => [],
                            'page_ids' => [],
                            'generation_ids' => [],
                            'example_paths' => [],
                            'prompt_tags_union' => [],
                        ],
                        'count' => 0,
                    ];
                }

                $clusters[$clusterKey]['count']++;
                $clusters[$clusterKey]['evidence']['delta_ids'][] = (int) $delta->id;
                if (is_int($delta->page_id)) {
                    $clusters[$clusterKey]['evidence']['page_ids'][] = (int) $delta->page_id;
                }
                $generationId = $this->safeString($delta->generation_id, 96);
                if ($generationId !== '') {
                    $clusters[$clusterKey]['evidence']['generation_ids'][] = $generationId;
                }
                $clusters[$clusterKey]['evidence']['example_paths'][] = $candidate['path'];
                $clusters[$clusterKey]['evidence']['prompt_tags_union'] = array_values(array_unique(array_merge(
                    $clusters[$clusterKey]['evidence']['prompt_tags_union'],
                    $promptTags
                )));
                sort($clusters[$clusterKey]['evidence']['prompt_tags_union']);
            }
        }

        ksort($clusters);

        $rows = [];
        $upserted = 0;
        $qualifyingClusters = 0;

        foreach ($clusters as $cluster) {
            if (($cluster['count'] ?? 0) < $threshold) {
                continue;
            }

            $qualifyingClusters++;
            $count = (int) $cluster['count'];
            $evidence = $cluster['evidence'];
            $uniquePageIds = array_values(array_unique(array_filter($evidence['page_ids'], fn ($v): bool => is_int($v))));
            sort($uniquePageIds);
            $uniqueGenerationIds = array_values(array_unique(array_filter($evidence['generation_ids'], fn ($v): bool => is_string($v) && $v !== '')));
            sort($uniqueGenerationIds);
            $examplePaths = array_values(array_unique(array_filter($evidence['example_paths'], fn ($v): bool => is_string($v) && $v !== '')));
            sort($examplePaths);
            $examplePaths = array_slice($examplePaths, 0, 10);

            $confidence = $this->deriveInitialConfidence($count, count($uniquePageIds));
            $conditions = [
                'version' => 'p6-g2-02.v1',
                'source' => 'builder_delta_cluster',
                'store_type' => $cluster['signature']['store_type'],
                'prompt_intent_tags' => $cluster['signature']['prompt_intent_tags'],
                'page_template_key' => $cluster['signature']['page_template_key'],
                'component_type' => $cluster['signature']['component_type'],
            ];

            $rule = CmsLearnedRule::query()->updateOrCreate(
                [
                    'scope' => (string) $cluster['scope'],
                    'site_id' => (string) $cluster['site_id'],
                    'rule_key' => (string) $cluster['rule_key'],
                ],
                [
                    'project_id' => (string) $cluster['project_id'],
                    'status' => 'candidate',
                    'active' => false,
                    'source' => 'builder_delta_cluster',
                    'conditions_json' => $conditions,
                    'patch_json' => $cluster['patch_template'],
                    'evidence_json' => [
                        'version' => 'p6-g2-02.v1',
                        'delta_count' => $count,
                        'unique_pages' => count($uniquePageIds),
                        'unique_generations' => count($uniqueGenerationIds),
                        'delta_ids_sample' => array_slice(array_values(array_unique($evidence['delta_ids'])), 0, 20),
                        'page_ids' => $uniquePageIds,
                        'generation_ids_sample' => array_slice($uniqueGenerationIds, 0, 20),
                        'example_paths' => $examplePaths,
                        'prompt_tags_union' => $evidence['prompt_tags_union'],
                    ],
                    'confidence' => $confidence,
                    'sample_size' => $count,
                    'delta_count' => $count,
                    'last_learned_at' => now(),
                ]
            );

            $rows[] = [
                'rule_id' => $rule->id,
                'rule_key' => $rule->rule_key,
                'site_id' => $rule->site_id,
                'component_type' => data_get($rule->conditions_json, 'component_type'),
                'store_type' => data_get($rule->conditions_json, 'store_type'),
                'prompt_intent_tags' => data_get($rule->conditions_json, 'prompt_intent_tags', []),
                'sample_size' => (int) $rule->sample_size,
                'confidence' => (float) $rule->confidence,
                'status' => (string) $rule->status,
            ];
            $upserted++;
        }

        return [
            'ok' => true,
            'since' => $window['since']->toISOString(),
            'until' => $window['until']->toISOString(),
            'source_deltas' => $deltas->count(),
            'eligible_ops' => $eligibleOps,
            'clusters' => count($clusters),
            'qualifying_clusters' => $qualifyingClusters,
            'upserted' => $upserted,
            'min_occurrences' => $threshold,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $op
     * @param  array<int, mixed>  $sections
     * @return array<string, mixed>|null
     */
    private function normalizePatchOpCandidate(array $op, array $sections): ?array
    {
        $operation = $this->safeString($op['op'] ?? null, 16);
        if (! in_array($operation, ['add', 'replace', 'remove'], true)) {
            return null;
        }

        $path = $this->safeString($op['path'] ?? null, 500);
        if ($path === '' || ! str_starts_with($path, '/sections/')) {
            return null;
        }

        // G2-02 baseline learns style/layout-focused fixes only; skip content copy/text edits.
        $isStyleLike = str_contains($path, '/props/style/')
            || str_contains($path, '/props/advanced/')
            || str_contains($path, '/props/responsive/')
            || str_contains($path, '/props/states/');
        if (! $isStyleLike) {
            return null;
        }

        $matches = [];
        if (preg_match('#^/sections/(\d+)(/.*)$#', $path, $matches) !== 1) {
            return null;
        }

        $sectionIndex = (int) $matches[1];
        $pathSuffix = (string) $matches[2];
        $componentType = $this->resolveSectionType($sections, $sectionIndex);
        if ($componentType === null) {
            return null;
        }

        $valuePresent = array_key_exists('value', $op);
        $value = $valuePresent ? $this->normalizeLearnableValue($op['value']) : null;

        if ($operation !== 'remove' && $valuePresent && $value === null) {
            return null;
        }

        if ($operation !== 'remove' && ! $valuePresent) {
            return null;
        }

        return [
            'op' => $operation,
            'path' => $path,
            'path_pattern' => '/sections/*'.$pathSuffix,
            'path_suffix' => $pathSuffix,
            'component_type' => $componentType,
            'value' => $operation === 'remove' ? null : $value,
        ];
    }

    /**
     * @param  array<int, mixed>  $sections
     */
    private function resolveSectionType(array $sections, int $index): ?string
    {
        $section = $sections[$index] ?? null;
        if (! is_array($section)) {
            return null;
        }

        $type = $this->safeString($section['type'] ?? null, 120);

        return $type !== '' ? $type : null;
    }

    private function resolveStoreType(CmsBuilderDelta $delta, array $baselineContent): string
    {
        $aiStoreType = $this->safeString(data_get($baselineContent, 'ai_generation.meta.family'), 50);
        if ($aiStoreType !== '') {
            return strtolower($aiStoreType);
        }

        $siteProjectType = $this->safeString(data_get($baselineContent, 'ai_generation.meta.project_type'), 50);
        if ($siteProjectType !== '') {
            return strtolower($siteProjectType);
        }

        return 'unknown';
    }

    /**
     * @return list<string>
     */
    private function normalizePromptTags(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $tags = [];
        foreach (array_slice($value, 0, 10) as $tag) {
            $safe = strtolower($this->safeString($tag, 40));
            if ($safe === '' || ! preg_match('/^[a-z0-9._:-]{1,40}$/', $safe)) {
                continue;
            }
            $tags[] = $safe;
        }

        $tags = array_values(array_unique($tags));
        sort($tags);

        return $tags;
    }

    private function normalizeLearnableValue(mixed $value): mixed
    {
        if (is_null($value) || is_bool($value)) {
            return $value;
        }

        if (is_int($value)) {
            return $value;
        }

        if (is_float($value)) {
            return round($value, 4);
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '' || mb_strlen($trimmed) > 120) {
                return null;
            }

            // Avoid learning arbitrary copy; only keep compact enum/token values or simple CSS-safe atoms.
            if (! preg_match('/^[a-zA-Z0-9#%().,_: \\-\\/]{1,120}$/', $trimmed)) {
                return null;
            }

            return $trimmed;
        }

        if (is_array($value)) {
            if (count($value) > 10) {
                return null;
            }

            if ($this->isAssoc($value)) {
                $normalized = [];
                foreach ($value as $key => $item) {
                    $safeKey = $this->safeString($key, 60);
                    if ($safeKey === '') {
                        continue;
                    }
                    $normalizedValue = $this->normalizeLearnableValue($item);
                    if ($normalizedValue === null && ! is_null($item)) {
                        return null;
                    }
                    $normalized[$safeKey] = $normalizedValue;
                }
                ksort($normalized);

                return $normalized;
            }

            $normalizedList = [];
            foreach ($value as $item) {
                $normalizedValue = $this->normalizeLearnableValue($item);
                if ($normalizedValue === null && ! is_null($item)) {
                    return null;
                }
                $normalizedList[] = $normalizedValue;
            }

            return array_values($normalizedList);
        }

        return null;
    }

    private function deriveInitialConfidence(int $count, int $uniquePages): float
    {
        $base = 0.25 + min(0.5, $count * 0.08);
        $pageBoost = min(0.2, $uniquePages * 0.05);

        return round(min(0.99, $base + $pageBoost), 4);
    }

    /**
     * @return array{since:Carbon,until:Carbon}
     */
    private function normalizeWindow(string|Carbon|null $since, string|Carbon|null $until): array
    {
        $untilAt = $this->normalizeTime($until) ?? now();
        $sinceAt = $this->normalizeTime($since) ?? $untilAt->copy()->subDays(30);

        if ($sinceAt->greaterThan($untilAt)) {
            [$sinceAt, $untilAt] = [$untilAt->copy(), $sinceAt->copy()];
        }

        return [
            'since' => $sinceAt->copy(),
            'until' => $untilAt->copy(),
        ];
    }

    private function normalizeTime(string|Carbon|null $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    private function canonicalJsonEncode(array $payload): ?string
    {
        $normalized = $this->deepSort($payload);
        $encoded = json_encode($normalized, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return is_string($encoded) && $encoded !== '' ? $encoded : null;
    }

    private function deepSort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! $this->isAssoc($value)) {
            return array_values(array_map(fn ($item) => $this->deepSort($item), $value));
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[(string) $key] = $this->deepSort($item);
        }
        ksort($result);

        return $result;
    }

    private function isAssoc(array $value): bool
    {
        return array_keys($value) !== range(0, count($value) - 1);
    }

    private function safeString(mixed $value, int $max): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        return mb_substr($normalized, 0, max(1, $max));
    }
}
