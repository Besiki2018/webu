<?php

namespace App\Services;

use App\Models\SectionLibrary;
use Illuminate\Support\Facades\Schema;
use Throwable;

class CmsComponentLibraryAliasMapExactnessConvergenceService
{
    public const REPORT_SCHEMA_VERSION = 'cms.component-library-alias-map-exactness-convergence-report.v1';
    public const PATCH_PREVIEW_SCHEMA_VERSION = 'cms.component-library-alias-map-exactness-patch-preview.v1';

    public function __construct(
        protected CmsComponentLibrarySpecEquivalenceAliasMapService $aliasMapService
    ) {}

    /**
     * @param  array<int, string>|null  $sourceKeyFilter
     * @return array<string, mixed>
     */
    public function analyze(?array $sourceKeyFilter = null, ?int $limit = 100, int $minConfidence = 0): array
    {
        $filter = $this->normalizeSourceKeyFilter($sourceKeyFilter);
        $allMappings = $this->aliasMapService->mappings();

        /** @var array<int, array{row_index:int,source_component_key:string,coverage:string,canonical_builder_keys:array<int,string>}> $equivalentRows */
        $equivalentRows = [];
        $uniqueCanonicalKeys = [];

        foreach ($allMappings as $rowIndex => $row) {
            if (! is_array($row)) {
                continue;
            }

            $sourceKey = trim((string) ($row['source_component_key'] ?? ''));
            if ($sourceKey === '') {
                continue;
            }

            if ($filter !== [] && ! isset($filter[$sourceKey])) {
                continue;
            }

            $coverage = strtolower(trim((string) ($row['coverage'] ?? '')));
            if ($coverage !== 'equivalent') {
                continue;
            }

            $canonicalKeys = array_values(array_filter(
                array_map(
                    static fn ($value): string => is_string($value) ? trim($value) : (string) $value,
                    (array) ($row['canonical_builder_keys'] ?? [])
                ),
                static fn (string $value): bool => $value !== ''
            ));
            $canonicalKeys = array_values(array_unique($canonicalKeys));

            foreach ($canonicalKeys as $canonicalKey) {
                $uniqueCanonicalKeys[$canonicalKey] = true;
            }

            $equivalentRows[] = [
                'row_index' => (int) $rowIndex,
                'source_component_key' => $sourceKey,
                'coverage' => $coverage,
                'canonical_builder_keys' => $canonicalKeys,
            ];
        }

        $registrySnapshot = $this->loadCanonicalRegistrySnapshot(array_keys($uniqueCanonicalKeys));

        $candidates = [];
        foreach ($equivalentRows as $row) {
            $candidate = $this->buildCandidate($row, $registrySnapshot);
            if ((int) ($candidate['confidence_score'] ?? 0) < $minConfidence) {
                continue;
            }
            $candidates[] = $candidate;
        }

        usort($candidates, function (array $a, array $b): int {
            $scoreCompare = ((int) ($b['confidence_score'] ?? 0)) <=> ((int) ($a['confidence_score'] ?? 0));
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            $hardCompare = ((int) ($a['hard_blocker_count'] ?? 0)) <=> ((int) ($b['hard_blocker_count'] ?? 0));
            if ($hardCompare !== 0) {
                return $hardCompare;
            }

            $softCompare = ((int) ($a['soft_blocker_count'] ?? 0)) <=> ((int) ($b['soft_blocker_count'] ?? 0));
            if ($softCompare !== 0) {
                return $softCompare;
            }

            return strcmp(
                (string) ($a['source_component_key'] ?? ''),
                (string) ($b['source_component_key'] ?? '')
            );
        });

        $totalCandidates = count($candidates);
        if ($limit !== null && $limit >= 0) {
            $candidates = array_slice($candidates, 0, $limit);
        }

        $summary = $this->buildSummary($candidates, $totalCandidates, $equivalentRows);
        $patchPreview = $this->buildPatchPreview($candidates);

        return [
            'schema_version' => self::REPORT_SCHEMA_VERSION,
            'summary' => array_merge($summary, [
                'alias_map' => array_merge($this->aliasMapService->summary(), [
                    'fingerprint' => $this->aliasMapService->fingerprint(),
                    'artifact_bundle_fingerprint' => $this->aliasMapService->artifactBundleFingerprint(),
                ]),
            ]),
            'filters' => [
                'source_keys' => array_values(array_keys($filter)),
                'min_confidence' => $minConfidence,
                'limit' => $limit,
                'sort' => 'confidence_desc_then_blockers_then_source_key',
                'coverage' => 'equivalent_only',
            ],
            'canonical_registry_diagnostics' => $this->registryDiagnosticsSummary($registrySnapshot),
            'candidates' => $candidates,
            'patch_preview' => $patchPreview,
        ];
    }

    /**
     * @param  array<int, string>|null  $sourceKeyFilter
     * @return array<string, bool>
     */
    private function normalizeSourceKeyFilter(?array $sourceKeyFilter): array
    {
        $result = [];

        foreach ((array) $sourceKeyFilter as $value) {
            $key = is_string($value) ? trim($value) : (string) $value;
            if ($key === '') {
                continue;
            }

            $result[$key] = true;
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $canonicalKeys
     * @return array<string, mixed>
     */
    private function loadCanonicalRegistrySnapshot(array $canonicalKeys): array
    {
        $canonicalKeys = array_values(array_unique(array_filter(
            array_map('strval', $canonicalKeys),
            static fn (string $value): bool => $value !== ''
        )));

        $snapshot = [
            'mode' => 'sections_library',
            'available' => false,
            'table_exists' => false,
            'error' => null,
            'rows' => [],
            'requested_keys' => $canonicalKeys,
        ];

        try {
            if (! Schema::hasTable('sections_library')) {
                $snapshot['error'] = 'sections_library_table_missing';

                return $snapshot;
            }

            $snapshot['table_exists'] = true;

            /** @var array<string, array<string, mixed>> $rows */
            $rows = SectionLibrary::query()
                ->whereIn('key', $canonicalKeys)
                ->get(['key', 'category', 'enabled', 'schema_json'])
                ->mapWithKeys(static function (SectionLibrary $row): array {
                    $schema = $row->schema_json;
                    $schemaPresent = is_array($schema) && $schema !== [];

                    return [
                        (string) $row->key => [
                            'key' => (string) $row->key,
                            'category' => (string) $row->category,
                            'enabled' => (bool) $row->enabled,
                            'schema_json_present' => $schemaPresent,
                        ],
                    ];
                })
                ->all();

            $snapshot['rows'] = $rows;
            $snapshot['available'] = true;
        } catch (Throwable $e) {
            $snapshot['error'] = class_basename($e).': '.$e->getMessage();
        }

        return $snapshot;
    }

    /**
     * @param  array{row_index:int,source_component_key:string,coverage:string,canonical_builder_keys:array<int,string>}  $row
     * @param  array<string, mixed>  $registrySnapshot
     * @return array<string, mixed>
     */
    private function buildCandidate(array $row, array $registrySnapshot): array
    {
        $canonicalKeys = array_values(array_unique(array_filter(
            array_map('strval', $row['canonical_builder_keys']),
            static fn (string $value): bool => $value !== ''
        )));

        $primaryCanonicalKey = $canonicalKeys[0] ?? null;
        $registryAvailable = (bool) ($registrySnapshot['available'] ?? false);
        /** @var array<string, array<string, mixed>> $registryRows */
        $registryRows = is_array($registrySnapshot['rows'] ?? null) ? $registrySnapshot['rows'] : [];

        $presentKeys = [];
        $missingKeys = [];
        $disabledKeys = [];
        $schemaJsonMissingKeys = [];

        foreach ($canonicalKeys as $canonicalKey) {
            if (! isset($registryRows[$canonicalKey])) {
                $missingKeys[] = $canonicalKey;
                continue;
            }

            $presentKeys[] = $canonicalKey;

            if (! (bool) ($registryRows[$canonicalKey]['enabled'] ?? false)) {
                $disabledKeys[] = $canonicalKey;
            }

            if (! (bool) ($registryRows[$canonicalKey]['schema_json_present'] ?? false)) {
                $schemaJsonMissingKeys[] = $canonicalKey;
            }
        }

        $hardBlockers = [];
        $softBlockers = [];

        if (! $registryAvailable) {
            $hardBlockers[] = 'canonical_registry_unavailable';
        }

        if (count($canonicalKeys) !== 1) {
            $hardBlockers[] = 'composite_alias_multiple_canonical_keys';
        }

        if ($primaryCanonicalKey !== null && in_array($primaryCanonicalKey, $missingKeys, true)) {
            $hardBlockers[] = 'missing_primary_canonical_in_registry';
        }

        if ($missingKeys !== []) {
            $hardBlockers[] = 'missing_canonical_registry_keys';
        }

        if ($disabledKeys !== []) {
            $hardBlockers[] = 'canonical_registry_keys_disabled';
        }

        if (str_starts_with($row['source_component_key'], 'layout.')) {
            $softBlockers[] = 'layout_primitive_semantic_review';
        }

        if ($schemaJsonMissingKeys !== []) {
            $softBlockers[] = 'canonical_registry_schema_json_missing';
        }

        $hardBlockers = array_values(array_unique($hardBlockers));
        $softBlockers = array_values(array_unique($softBlockers));
        sort($hardBlockers);
        sort($softBlockers);

        $confidenceScore = $this->calculateConfidenceScore(
            sourceComponentKey: $row['source_component_key'],
            canonicalKeyCount: count($canonicalKeys),
            registryAvailable: $registryAvailable,
            missingKeys: $missingKeys,
            disabledKeys: $disabledKeys,
            schemaJsonMissingKeys: $schemaJsonMissingKeys,
            hardBlockerCount: count($hardBlockers),
            softBlockerCount: count($softBlockers),
        );

        $readyForPatchPreview = $hardBlockers === [] && $softBlockers === [];

        $status = 'blocked';
        if ($readyForPatchPreview) {
            $status = 'ready_for_exact_patch_preview';
        } elseif ($hardBlockers === []) {
            $status = 'needs_review';
        }

        return [
            'alias_map_row_index' => (int) $row['row_index'],
            'source_component_key' => $row['source_component_key'],
            'current_coverage' => $row['coverage'],
            'canonical_builder_keys' => $canonicalKeys,
            'primary_canonical_builder_key' => $primaryCanonicalKey,
            'candidate_status' => $status,
            'ready_for_patch_preview' => $readyForPatchPreview,
            'confidence_score' => $confidenceScore,
            'confidence_band' => $this->confidenceBand($confidenceScore),
            'hard_blocker_count' => count($hardBlockers),
            'soft_blocker_count' => count($softBlockers),
            'blocking_reasons' => array_values(array_unique(array_merge($hardBlockers, $softBlockers))),
            'hard_blockers' => $hardBlockers,
            'soft_blockers' => $softBlockers,
            'canonical_registry_diagnostics' => [
                'mode' => (string) ($registrySnapshot['mode'] ?? 'sections_library'),
                'available' => $registryAvailable,
                'table_exists' => (bool) ($registrySnapshot['table_exists'] ?? false),
                'error' => $registryAvailable ? null : ($registrySnapshot['error'] ?? null),
                'present_keys' => $presentKeys,
                'missing_keys' => $missingKeys,
                'disabled_keys' => $disabledKeys,
                'schema_json_missing_keys' => $schemaJsonMissingKeys,
                'resolved_rows' => array_values(array_filter(array_map(
                    static fn (string $key): ?array => isset($registryRows[$key]) ? (array) $registryRows[$key] : null,
                    $canonicalKeys
                ))),
            ],
            'proposed_exactness_patch_operations' => $readyForPatchPreview ? [[
                'op' => 'replace',
                'path' => '/mappings/'.$row['row_index'].'/coverage',
                'from' => $row['coverage'],
                'value' => 'exact',
            ]] : [],
        ];
    }

    /**
     * @param  array<int, string>  $missingKeys
     * @param  array<int, string>  $disabledKeys
     * @param  array<int, string>  $schemaJsonMissingKeys
     */
    private function calculateConfidenceScore(
        string $sourceComponentKey,
        int $canonicalKeyCount,
        bool $registryAvailable,
        array $missingKeys,
        array $disabledKeys,
        array $schemaJsonMissingKeys,
        int $hardBlockerCount,
        int $softBlockerCount,
    ): int {
        $score = 100;

        if (! $registryAvailable) {
            $score -= 60;
        }

        if ($canonicalKeyCount > 1) {
            $score -= 30;
            $score -= (($canonicalKeyCount - 1) * 5);
        } elseif ($canonicalKeyCount === 0) {
            $score -= 50;
        }

        $score -= count($missingKeys) * 15;
        $score -= count($disabledKeys) * 10;
        $score -= count($schemaJsonMissingKeys) * 4;
        $score -= $hardBlockerCount * 6;
        $score -= $softBlockerCount * 3;

        if (str_starts_with($sourceComponentKey, 'layout.')) {
            $score -= 8;
        }

        return max(0, min(100, $score));
    }

    private function confidenceBand(int $score): string
    {
        if ($score >= 85) {
            return 'high';
        }

        if ($score >= 60) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @param  array<int, array<string, mixed>>  $equivalentRows
     * @return array<string, mixed>
     */
    private function buildSummary(array $candidates, int $totalCandidatesBeforeLimit, array $equivalentRows): array
    {
        $counts = [
            'ready_for_exact_patch_preview' => 0,
            'needs_review' => 0,
            'blocked' => 0,
        ];

        $confidence = [
            'high' => 0,
            'medium' => 0,
            'low' => 0,
        ];

        foreach ($candidates as $candidate) {
            $status = (string) ($candidate['candidate_status'] ?? '');
            if (isset($counts[$status])) {
                $counts[$status]++;
            }

            $band = (string) ($candidate['confidence_band'] ?? '');
            if (isset($confidence[$band])) {
                $confidence[$band]++;
            }
        }

        return [
            'analyzed_equivalent_rows_total' => count($equivalentRows),
            'candidates_total_before_limit' => $totalCandidatesBeforeLimit,
            'returned_candidates' => count($candidates),
            'status_breakdown' => $counts,
            'confidence_breakdown' => $confidence,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $candidates
     * @return array<string, mixed>
     */
    private function buildPatchPreview(array $candidates): array
    {
        $operations = [];
        $readyCandidates = [];
        $blockedCandidates = [];
        $needsReviewCandidates = [];

        foreach ($candidates as $candidate) {
            $status = (string) ($candidate['candidate_status'] ?? 'blocked');

            if ($status === 'ready_for_exact_patch_preview') {
                $readyCandidates[] = (string) ($candidate['source_component_key'] ?? '');
                foreach ((array) ($candidate['proposed_exactness_patch_operations'] ?? []) as $op) {
                    if (is_array($op)) {
                        $operations[] = $op;
                    }
                }
                continue;
            }

            if ($status === 'needs_review') {
                $needsReviewCandidates[] = (string) ($candidate['source_component_key'] ?? '');
            } else {
                $blockedCandidates[] = (string) ($candidate['source_component_key'] ?? '');
            }
        }

        return [
            'schema_version' => self::PATCH_PREVIEW_SCHEMA_VERSION,
            'non_destructive' => true,
            'review_first' => true,
            'registry_rewrites_included' => false,
            'operations_count' => count($operations),
            'operations' => $operations,
            'candidate_lists' => [
                'ready_for_exact_patch_preview' => $readyCandidates,
                'needs_review' => $needsReviewCandidates,
                'blocked' => $blockedCandidates,
            ],
            'safety_constraints' => [
                'No alias-map JSON file mutations are performed by this command.',
                'No canonical registry/sections_library rows are rewritten by this command.',
                'Patch operations are previews only and require explicit human review/application.',
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $registrySnapshot
     * @return array<string, mixed>
     */
    private function registryDiagnosticsSummary(array $registrySnapshot): array
    {
        /** @var array<string, array<string, mixed>> $rows */
        $rows = is_array($registrySnapshot['rows'] ?? null) ? $registrySnapshot['rows'] : [];
        /** @var array<int, string> $requestedKeys */
        $requestedKeys = array_values(array_filter(
            array_map('strval', (array) ($registrySnapshot['requested_keys'] ?? [])),
            static fn (string $value): bool => $value !== ''
        ));

        $presentKeys = array_keys($rows);
        sort($presentKeys);

        $missingKeys = array_values(array_diff($requestedKeys, $presentKeys));
        sort($missingKeys);

        $disabledKeys = [];
        foreach ($rows as $key => $row) {
            if (! (bool) ($row['enabled'] ?? false)) {
                $disabledKeys[] = $key;
            }
        }
        sort($disabledKeys);

        return [
            'mode' => (string) ($registrySnapshot['mode'] ?? 'sections_library'),
            'available' => (bool) ($registrySnapshot['available'] ?? false),
            'table_exists' => (bool) ($registrySnapshot['table_exists'] ?? false),
            'error' => (bool) ($registrySnapshot['available'] ?? false) ? null : ($registrySnapshot['error'] ?? null),
            'requested_canonical_key_count' => count($requestedKeys),
            'matched_registry_key_count' => count($presentKeys),
            'missing_registry_key_count' => count($missingKeys),
            'disabled_registry_key_count' => count($disabledKeys),
            'sample_missing_registry_keys' => array_slice($missingKeys, 0, 10),
        ];
    }
}
