<?php

namespace App\Console\Commands;

use App\Services\CmsComponentLibrarySpecEquivalenceAliasMapService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ValidateComponentLibraryAliasMap extends Command
{
    protected $signature = 'cms:component-library-alias-map-validate
        {--source-key= : Resolve canonical builder key(s) for a source-spec component key}
        {--canonical-key= : Resolve source-spec component key(s) for a canonical builder key}
        {--assert-source-key=* : Assert that each source-spec component key exists in alias map}
        {--assert-canonical-key=* : Assert that each canonical builder key exists in alias map inverse lookup}
        {--assert-source-primary=* : Assert source-spec primary mapping using source=canonical format}
        {--assert-source-contains-canonical=* : Assert source-spec mapping contains canonical key using source=canonical format}
        {--assert-source-canonical-set=* : Assert source-spec canonical key set using source=key1|key2|... format}
        {--assert-canonical-source-set=* : Assert canonical builder inverse source-spec set using canonical=source1|source2|... format}
        {--export-csv : Export all alias-map rows as CSV report}
        {--export-md : Export all alias-map rows as Markdown report}
        {--export-json : Export alias-map report as JSON (summary + stats + fingerprints + mappings)}
        {--output= : Write export report to a relative file path under storage/app/cms/component-library-alias-map-exports}
        {--overwrite : Allow overwriting an existing export file when used with --output}
        {--ci-baseline : Run CI baseline preset (ci-check + baseline summary/stats/required-alias assertions)}
        {--ci-check : Run CI preset checks (strict-schema + strict-export-schema + roundtrip-check + stats + fingerprints)}
        {--fingerprints : Include deterministic map/schema/artifact-bundle fingerprints in command output}
        {--stats : Include coverage breakdown and alias fanout stats in command output}
        {--assert-max-partial= : Fail when partial coverage rows exceed this threshold}
        {--assert-max-missing= : Fail when missing coverage rows exceed this threshold}
        {--assert-max-unknown= : Fail when unknown coverage rows exceed this threshold}
        {--assert-min-covered= : Fail when exact+equivalent coverage rows are below this threshold}
        {--assert-max-composite-alias-rows= : Fail when composite alias rows exceed this threshold}
        {--assert-max-canonical-keys-per-row= : Fail when max canonical keys per row exceeds this threshold}
        {--assert-min-distinct-canonical-keys= : Fail when distinct canonical builder key count is below this threshold}
        {--assert-coverage-status= : Fail when summary coverage_status does not match expected value}
        {--assert-mapping-count= : Fail when summary mapping_count does not match expected integer}
        {--assert-map-version= : Fail when summary version does not match expected value}
        {--assert-map-fingerprint= : Fail when summary deterministic map fingerprint does not match expected value}
        {--assert-artifact-bundle-fingerprint= : Fail when alias-map+schema artifact bundle fingerprint does not match expected value}
        {--assert-source-spec-ref-contains= : Fail when summary source_spec_ref does not contain expected substring}
        {--assert-gap-audit-ref-contains= : Fail when summary gap_audit_ref does not contain expected substring}
        {--assert-source-spec-ref-regex= : Fail when summary source_spec_ref does not match expected PCRE pattern}
        {--assert-gap-audit-ref-regex= : Fail when summary gap_audit_ref does not match expected PCRE pattern}
        {--assert-schema-fingerprint= : Fail when strict schema deterministic fingerprint does not match expected value}
        {--assert-schema-path-suffix= : Fail when strict schema path does not end with expected suffix}
        {--assert-schema-version-const= : Fail when strict schema version const does not match expected value}
        {--assert-schema-coverage-status-const= : Fail when strict schema coverage_status const does not match expected value}
        {--assert-schema-validated-rows= : Fail when strict schema validated_rows does not match expected integer}
        {--assert-export-schema-fingerprint= : Fail when strict export schema deterministic fingerprint does not match expected value}
        {--assert-export-schema-path-suffix= : Fail when strict export schema path does not end with expected suffix}
        {--assert-export-schema-summary-version-const= : Fail when strict export schema summary.version const does not match expected value}
        {--assert-export-schema-coverage-status-const= : Fail when strict export schema summary.coverage_status const does not match expected value}
        {--assert-export-schema-mapping-count-const= : Fail when strict export schema summary.mapping_count const does not match expected integer}
        {--assert-export-schema-stats-rows-const= : Fail when strict export schema stats.rows const does not match expected integer}
        {--assert-export-schema-mappings-min-items= : Fail when strict export schema mappings.minItems does not match expected integer}
        {--assert-export-schema-validated-rows= : Fail when strict export schema validated_rows does not match expected integer}
        {--strict-export-schema : Validate alias-map JSON export schema artifact contract checks}
        {--roundtrip-check : Include source<->canonical round-trip consistency diagnostics}
        {--strict-schema : Validate alias-map JSON against schema artifact contract checks}
        {--json : Print machine-readable output}';

    protected $description = 'Validate and inspect the component-library source-spec equivalence alias map (v1).';

    public function __construct(
        protected CmsComponentLibrarySpecEquivalenceAliasMapService $aliasMapService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sourceKey = trim((string) ($this->option('source-key') ?? ''));
        $canonicalKey = trim((string) ($this->option('canonical-key') ?? ''));
        $assertSourceKeys = array_values(array_filter(
            array_map(
                static fn ($value): string => trim((string) $value),
                (array) ($this->option('assert-source-key') ?? [])
            ),
            static fn (string $value): bool => $value !== ''
        ));
        $assertCanonicalKeys = array_values(array_filter(
            array_map(
                static fn ($value): string => trim((string) $value),
                (array) ($this->option('assert-canonical-key') ?? [])
            ),
            static fn (string $value): bool => $value !== ''
        ));
        $assertSourcePrimaryPairs = array_values(array_filter(
            array_map(
                static fn ($value): string => trim((string) $value),
                (array) ($this->option('assert-source-primary') ?? [])
            ),
            static fn (string $value): bool => $value !== ''
        ));
        $assertSourceContainsPairs = array_values(array_filter(
            array_map(
                static fn ($value): string => trim((string) $value),
                (array) ($this->option('assert-source-contains-canonical') ?? [])
            ),
            static fn (string $value): bool => $value !== ''
        ));
        $assertSourceCanonicalSetPairs = array_values(array_filter(
            array_map(
                static fn ($value): string => trim((string) $value),
                (array) ($this->option('assert-source-canonical-set') ?? [])
            ),
            static fn (string $value): bool => $value !== ''
        ));
        $assertCanonicalSourceSetPairs = array_values(array_filter(
            array_map(
                static fn ($value): string => trim((string) $value),
                (array) ($this->option('assert-canonical-source-set') ?? [])
            ),
            static fn (string $value): bool => $value !== ''
        ));
        $exportCsv = (bool) $this->option('export-csv');
        $exportMd = (bool) $this->option('export-md');
        $exportJson = (bool) $this->option('export-json');
        $outputPath = trim((string) ($this->option('output') ?? ''));
        $overwrite = (bool) $this->option('overwrite');
        $ciBaselinePreset = (bool) $this->option('ci-baseline');
        $ciCheckPreset = (bool) $this->option('ci-check');
        $includeFingerprints = (bool) $this->option('fingerprints');
        $includeStats = (bool) $this->option('stats');
        $assertMaxPartialRaw = trim((string) ($this->option('assert-max-partial') ?? ''));
        $assertMaxMissingRaw = trim((string) ($this->option('assert-max-missing') ?? ''));
        $assertMaxUnknownRaw = trim((string) ($this->option('assert-max-unknown') ?? ''));
        $assertMinCoveredRaw = trim((string) ($this->option('assert-min-covered') ?? ''));
        $assertMaxCompositeAliasRowsRaw = trim((string) ($this->option('assert-max-composite-alias-rows') ?? ''));
        $assertMaxCanonicalKeysPerRowRaw = trim((string) ($this->option('assert-max-canonical-keys-per-row') ?? ''));
        $assertMinDistinctCanonicalKeysRaw = trim((string) ($this->option('assert-min-distinct-canonical-keys') ?? ''));
        $assertCoverageStatus = trim((string) ($this->option('assert-coverage-status') ?? ''));
        $assertMappingCountRaw = trim((string) ($this->option('assert-mapping-count') ?? ''));
        $assertMapVersion = trim((string) ($this->option('assert-map-version') ?? ''));
        $assertMapFingerprint = trim((string) ($this->option('assert-map-fingerprint') ?? ''));
        $assertArtifactBundleFingerprint = trim((string) ($this->option('assert-artifact-bundle-fingerprint') ?? ''));
        $assertSourceSpecRefContains = trim((string) ($this->option('assert-source-spec-ref-contains') ?? ''));
        $assertGapAuditRefContains = trim((string) ($this->option('assert-gap-audit-ref-contains') ?? ''));
        $assertSourceSpecRefRegex = trim((string) ($this->option('assert-source-spec-ref-regex') ?? ''));
        $assertGapAuditRefRegex = trim((string) ($this->option('assert-gap-audit-ref-regex') ?? ''));
        $assertSchemaFingerprint = trim((string) ($this->option('assert-schema-fingerprint') ?? ''));
        $assertSchemaPathSuffix = trim((string) ($this->option('assert-schema-path-suffix') ?? ''));
        $assertSchemaVersionConst = trim((string) ($this->option('assert-schema-version-const') ?? ''));
        $assertSchemaCoverageStatusConst = trim((string) ($this->option('assert-schema-coverage-status-const') ?? ''));
        $assertSchemaValidatedRowsRaw = trim((string) ($this->option('assert-schema-validated-rows') ?? ''));
        $assertExportSchemaFingerprint = trim((string) ($this->option('assert-export-schema-fingerprint') ?? ''));
        $assertExportSchemaPathSuffix = trim((string) ($this->option('assert-export-schema-path-suffix') ?? ''));
        $assertExportSchemaSummaryVersionConst = trim((string) ($this->option('assert-export-schema-summary-version-const') ?? ''));
        $assertExportSchemaCoverageStatusConst = trim((string) ($this->option('assert-export-schema-coverage-status-const') ?? ''));
        $assertExportSchemaMappingCountConstRaw = trim((string) ($this->option('assert-export-schema-mapping-count-const') ?? ''));
        $assertExportSchemaStatsRowsConstRaw = trim((string) ($this->option('assert-export-schema-stats-rows-const') ?? ''));
        $assertExportSchemaMappingsMinItemsRaw = trim((string) ($this->option('assert-export-schema-mappings-min-items') ?? ''));
        $assertExportSchemaValidatedRowsRaw = trim((string) ($this->option('assert-export-schema-validated-rows') ?? ''));
        $strictExportSchema = (bool) $this->option('strict-export-schema');
        $roundTripCheck = (bool) $this->option('roundtrip-check');
        $strictSchema = (bool) $this->option('strict-schema');
        $jsonOutput = (bool) $this->option('json');
        $selectedExportModes = array_values(array_filter([
            $exportCsv ? 'csv' : null,
            $exportMd ? 'md' : null,
            $exportJson ? 'json' : null,
        ]));
        $exportMode = $selectedExportModes[0] ?? null;
        $statsAssertionsRequested = $assertMaxPartialRaw !== ''
            || $assertMaxMissingRaw !== ''
            || $assertMaxUnknownRaw !== ''
            || $assertMinCoveredRaw !== ''
            || $assertMaxCompositeAliasRowsRaw !== ''
            || $assertMaxCanonicalKeysPerRowRaw !== ''
            || $assertMinDistinctCanonicalKeysRaw !== '';
        $summaryAssertionsRequested = $assertCoverageStatus !== ''
            || $assertMappingCountRaw !== ''
            || $assertMapVersion !== ''
            || $assertMapFingerprint !== ''
            || $assertArtifactBundleFingerprint !== ''
            || $assertSourceSpecRefContains !== ''
            || $assertGapAuditRefContains !== ''
            || $assertSourceSpecRefRegex !== ''
            || $assertGapAuditRefRegex !== '';
        $strictSchemaAssertionsRequested = $assertSchemaFingerprint !== '';
        $strictSchemaAssertionsRequested = $strictSchemaAssertionsRequested
            || $assertSchemaPathSuffix !== ''
            || $assertSchemaVersionConst !== ''
            || $assertSchemaCoverageStatusConst !== ''
            || $assertSchemaValidatedRowsRaw !== '';
        $strictExportSchemaAssertionsRequested = $assertExportSchemaFingerprint !== '';
        $strictExportSchemaAssertionsRequested = $strictExportSchemaAssertionsRequested
            || $assertExportSchemaPathSuffix !== ''
            || $assertExportSchemaSummaryVersionConst !== ''
            || $assertExportSchemaCoverageStatusConst !== ''
            || $assertExportSchemaMappingCountConstRaw !== ''
            || $assertExportSchemaStatsRowsConstRaw !== ''
            || $assertExportSchemaMappingsMinItemsRaw !== ''
            || $assertExportSchemaValidatedRowsRaw !== '';
        $ciBaselineProfile = null;

        if ($ciBaselinePreset) {
            $ciBaselineProfile = $this->aliasMapService->ciBaselinePresetProfile();
            $ciCheckPreset = true;
            $profileSummary = is_array($ciBaselineProfile['summary_assertions'] ?? null)
                ? (array) $ciBaselineProfile['summary_assertions']
                : [];
            $profileStrictSchema = is_array($ciBaselineProfile['strict_schema_assertions'] ?? null)
                ? (array) $ciBaselineProfile['strict_schema_assertions']
                : [];
            $profileStrictExportSchema = is_array($ciBaselineProfile['strict_export_schema_assertions'] ?? null)
                ? (array) $ciBaselineProfile['strict_export_schema_assertions']
                : [];
            $profileStats = is_array($ciBaselineProfile['stats_assertions'] ?? null)
                ? (array) $ciBaselineProfile['stats_assertions']
                : [];
            $profileSourceKeys = is_array($ciBaselineProfile['required_source_keys'] ?? null)
                ? array_values(array_map('strval', (array) $ciBaselineProfile['required_source_keys']))
                : [];
            $profileCanonicalKeys = is_array($ciBaselineProfile['required_canonical_keys'] ?? null)
                ? array_values(array_map('strval', (array) $ciBaselineProfile['required_canonical_keys']))
                : [];
            $profileSourcePrimaryPairs = is_array($ciBaselineProfile['source_primary_assertions'] ?? null)
                ? array_values(array_map('strval', (array) $ciBaselineProfile['source_primary_assertions']))
                : [];
            $profileSourceContainsPairs = is_array($ciBaselineProfile['source_contains_assertions'] ?? null)
                ? array_values(array_map('strval', (array) $ciBaselineProfile['source_contains_assertions']))
                : [];
            $profileSourceCanonicalSetPairs = is_array($ciBaselineProfile['source_canonical_set_assertions'] ?? null)
                ? array_values(array_map('strval', (array) $ciBaselineProfile['source_canonical_set_assertions']))
                : [];
            $profileCanonicalSourceSetPairs = is_array($ciBaselineProfile['canonical_source_set_assertions'] ?? null)
                ? array_values(array_map('strval', (array) $ciBaselineProfile['canonical_source_set_assertions']))
                : [];

            $assertMapVersion = $assertMapVersion !== '' ? $assertMapVersion : (string) ($profileSummary['assert_map_version'] ?? 'v1');
            $assertCoverageStatus = $assertCoverageStatus !== '' ? $assertCoverageStatus : (string) ($profileSummary['assert_coverage_status'] ?? 'complete_exact_plus_equivalent');
            $assertMappingCountRaw = $assertMappingCountRaw !== '' ? $assertMappingCountRaw : (string) (int) ($profileSummary['assert_mapping_count'] ?? 70);
            $assertMapFingerprint = $assertMapFingerprint !== '' ? $assertMapFingerprint : (string) ($profileSummary['assert_map_fingerprint'] ?? '');
            $assertArtifactBundleFingerprint = $assertArtifactBundleFingerprint !== '' ? $assertArtifactBundleFingerprint : (string) ($profileSummary['assert_artifact_bundle_fingerprint'] ?? '');
            $assertSourceSpecRefContains = $assertSourceSpecRefContains !== '' ? $assertSourceSpecRefContains : (string) ($profileSummary['assert_source_spec_ref_contains'] ?? '');
            $assertGapAuditRefContains = $assertGapAuditRefContains !== '' ? $assertGapAuditRefContains : (string) ($profileSummary['assert_gap_audit_ref_contains'] ?? '');
            $assertSourceSpecRefRegex = $assertSourceSpecRefRegex !== '' ? $assertSourceSpecRefRegex : (string) ($profileSummary['assert_source_spec_ref_regex'] ?? '');
            $assertGapAuditRefRegex = $assertGapAuditRefRegex !== '' ? $assertGapAuditRefRegex : (string) ($profileSummary['assert_gap_audit_ref_regex'] ?? '');
            $assertSchemaFingerprint = $assertSchemaFingerprint !== '' ? $assertSchemaFingerprint : (string) ($profileStrictSchema['assert_schema_fingerprint'] ?? '');
            $assertSchemaPathSuffix = $assertSchemaPathSuffix !== '' ? $assertSchemaPathSuffix : (string) ($profileStrictSchema['assert_schema_path_suffix'] ?? '');
            $assertSchemaVersionConst = $assertSchemaVersionConst !== '' ? $assertSchemaVersionConst : (string) ($profileStrictSchema['assert_schema_version_const'] ?? 'v1');
            $assertSchemaCoverageStatusConst = $assertSchemaCoverageStatusConst !== '' ? $assertSchemaCoverageStatusConst : (string) ($profileStrictSchema['assert_schema_coverage_status_const'] ?? 'complete_exact_plus_equivalent');
            $assertSchemaValidatedRowsRaw = $assertSchemaValidatedRowsRaw !== '' ? $assertSchemaValidatedRowsRaw : (string) (int) ($profileStrictSchema['assert_schema_validated_rows'] ?? 0);
            $assertExportSchemaFingerprint = $assertExportSchemaFingerprint !== '' ? $assertExportSchemaFingerprint : (string) ($profileStrictExportSchema['assert_export_schema_fingerprint'] ?? '');
            $assertExportSchemaPathSuffix = $assertExportSchemaPathSuffix !== '' ? $assertExportSchemaPathSuffix : (string) ($profileStrictExportSchema['assert_export_schema_path_suffix'] ?? '');
            $assertExportSchemaSummaryVersionConst = $assertExportSchemaSummaryVersionConst !== '' ? $assertExportSchemaSummaryVersionConst : (string) ($profileStrictExportSchema['assert_export_schema_summary_version_const'] ?? 'v1');
            $assertExportSchemaCoverageStatusConst = $assertExportSchemaCoverageStatusConst !== '' ? $assertExportSchemaCoverageStatusConst : (string) ($profileStrictExportSchema['assert_export_schema_coverage_status_const'] ?? 'complete_exact_plus_equivalent');
            $assertExportSchemaMappingCountConstRaw = $assertExportSchemaMappingCountConstRaw !== '' ? $assertExportSchemaMappingCountConstRaw : (string) (int) ($profileStrictExportSchema['assert_export_schema_mapping_count_const'] ?? 0);
            $assertExportSchemaStatsRowsConstRaw = $assertExportSchemaStatsRowsConstRaw !== '' ? $assertExportSchemaStatsRowsConstRaw : (string) (int) ($profileStrictExportSchema['assert_export_schema_stats_rows_const'] ?? 0);
            $assertExportSchemaMappingsMinItemsRaw = $assertExportSchemaMappingsMinItemsRaw !== '' ? $assertExportSchemaMappingsMinItemsRaw : (string) (int) ($profileStrictExportSchema['assert_export_schema_mappings_min_items'] ?? 0);
            $assertExportSchemaValidatedRowsRaw = $assertExportSchemaValidatedRowsRaw !== '' ? $assertExportSchemaValidatedRowsRaw : (string) (int) ($profileStrictExportSchema['assert_export_schema_validated_rows'] ?? 0);
            $assertMaxPartialRaw = $assertMaxPartialRaw !== '' ? $assertMaxPartialRaw : (string) (int) ($profileStats['assert_max_partial'] ?? 0);
            $assertMaxMissingRaw = $assertMaxMissingRaw !== '' ? $assertMaxMissingRaw : (string) (int) ($profileStats['assert_max_missing'] ?? 0);
            $assertMaxUnknownRaw = $assertMaxUnknownRaw !== '' ? $assertMaxUnknownRaw : (string) (int) ($profileStats['assert_max_unknown'] ?? 0);
            $assertMinCoveredRaw = $assertMinCoveredRaw !== '' ? $assertMinCoveredRaw : (string) (int) ($profileStats['assert_min_covered'] ?? 70);
            $assertMaxCompositeAliasRowsRaw = $assertMaxCompositeAliasRowsRaw !== '' ? $assertMaxCompositeAliasRowsRaw : (string) (int) ($profileStats['assert_max_composite_alias_rows'] ?? PHP_INT_MAX);
            $assertMaxCanonicalKeysPerRowRaw = $assertMaxCanonicalKeysPerRowRaw !== '' ? $assertMaxCanonicalKeysPerRowRaw : (string) (int) ($profileStats['assert_max_canonical_keys_per_row'] ?? PHP_INT_MAX);
            $assertMinDistinctCanonicalKeysRaw = $assertMinDistinctCanonicalKeysRaw !== '' ? $assertMinDistinctCanonicalKeysRaw : (string) (int) ($profileStats['assert_min_distinct_canonical_keys'] ?? 0);

            $assertSourceKeys = array_values(array_unique(array_merge(
                $assertSourceKeys,
                $profileSourceKeys
            )));
            $assertCanonicalKeys = array_values(array_unique(array_merge(
                $assertCanonicalKeys,
                $profileCanonicalKeys
            )));
            $assertSourcePrimaryPairs = array_values(array_unique(array_merge(
                $assertSourcePrimaryPairs,
                $profileSourcePrimaryPairs
            )));
            $assertSourceContainsPairs = array_values(array_unique(array_merge(
                $assertSourceContainsPairs,
                $profileSourceContainsPairs
            )));
            $assertSourceCanonicalSetPairs = array_values(array_unique(array_merge(
                $assertSourceCanonicalSetPairs,
                $profileSourceCanonicalSetPairs
            )));
            $assertCanonicalSourceSetPairs = array_values(array_unique(array_merge(
                $assertCanonicalSourceSetPairs,
                $profileCanonicalSourceSetPairs
            )));

            $statsAssertionsRequested = true;
            $summaryAssertionsRequested = true;
            $strictSchemaAssertionsRequested = true;
            $strictExportSchemaAssertionsRequested = true;
        }

        if ($ciCheckPreset) {
            $includeFingerprints = true;
            $includeStats = true;
            $roundTripCheck = true;
            $strictSchema = true;
            $strictExportSchema = true;
        }
        if ($strictSchemaAssertionsRequested) {
            $strictSchema = true;
        }
        if ($strictExportSchemaAssertionsRequested) {
            $strictExportSchema = true;
        }

        if (count($selectedExportModes) > 1) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'conflicting_export_modes',
            ], self::FAILURE, $jsonOutput);
        }

        if ($exportMode !== null && $jsonOutput) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'export_modes_incompatible_with_json',
            ], self::FAILURE, true);
        }

        if ($exportMode !== null && $ciCheckPreset) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'ci_check_incompatible_with_export_modes',
            ], self::FAILURE, $jsonOutput);
        }

        if ($exportMode !== null && $includeFingerprints) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'fingerprints_incompatible_with_export_modes',
            ], self::FAILURE, $jsonOutput);
        }

        if ($exportMode !== null && $statsAssertionsRequested) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'stats_assertions_incompatible_with_export_modes',
            ], self::FAILURE, $jsonOutput);
        }

        if ($exportMode !== null && $includeStats) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'stats_incompatible_with_export_modes',
            ], self::FAILURE, $jsonOutput);
        }

        if ($exportMode !== null && $roundTripCheck) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'roundtrip_check_incompatible_with_export_modes',
            ], self::FAILURE, $jsonOutput);
        }

        if ($exportMode !== null && $strictExportSchema) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'export_schema_check_incompatible_with_export_modes',
            ], self::FAILURE, $jsonOutput);
        }

        if ($outputPath !== '' && $exportMode === null) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'output_requires_export_mode',
            ], self::FAILURE, $jsonOutput);
        }

        $statsAssertions = [];
        foreach ([
            'assert_max_partial' => $assertMaxPartialRaw,
            'assert_max_missing' => $assertMaxMissingRaw,
            'assert_max_unknown' => $assertMaxUnknownRaw,
            'assert_min_covered' => $assertMinCoveredRaw,
            'assert_max_composite_alias_rows' => $assertMaxCompositeAliasRowsRaw,
            'assert_max_canonical_keys_per_row' => $assertMaxCanonicalKeysPerRowRaw,
            'assert_min_distinct_canonical_keys' => $assertMinDistinctCanonicalKeysRaw,
        ] as $key => $rawValue) {
            if ($rawValue === '') {
                continue;
            }

            if (! ctype_digit($rawValue)) {
                return $this->renderAndExit([
                    'ok' => false,
                    'error' => 'invalid_threshold_value',
                    'threshold' => [
                        'key' => $key,
                        'value' => $rawValue,
                    ],
                ], self::FAILURE, $jsonOutput);
            }

            $statsAssertions[$key] = (int) $rawValue;
        }

        if ($statsAssertions !== []) {
            $includeStats = true;
        }

        $summaryAssertions = [];
        if ($assertCoverageStatus !== '') {
            $summaryAssertions['assert_coverage_status'] = $assertCoverageStatus;
        }
        if ($assertMapVersion !== '') {
            $summaryAssertions['assert_map_version'] = $assertMapVersion;
        }
        if ($assertMapFingerprint !== '') {
            $summaryAssertions['assert_map_fingerprint'] = $assertMapFingerprint;
        }
        if ($assertArtifactBundleFingerprint !== '') {
            $summaryAssertions['assert_artifact_bundle_fingerprint'] = $assertArtifactBundleFingerprint;
        }
        if ($assertSourceSpecRefContains !== '') {
            $summaryAssertions['assert_source_spec_ref_contains'] = $assertSourceSpecRefContains;
        }
        if ($assertGapAuditRefContains !== '') {
            $summaryAssertions['assert_gap_audit_ref_contains'] = $assertGapAuditRefContains;
        }
        foreach ([
            'assert_source_spec_ref_regex' => $assertSourceSpecRefRegex,
            'assert_gap_audit_ref_regex' => $assertGapAuditRefRegex,
        ] as $key => $pattern) {
            if ($pattern === '') {
                continue;
            }
            if (@preg_match($pattern, '') === false) {
                return $this->renderAndExit([
                    'ok' => false,
                    'error' => 'invalid_assertion_value',
                    'assertion' => [
                        'key' => $key,
                        'value' => $pattern,
                    ],
                ], self::FAILURE, $jsonOutput);
            }
            $summaryAssertions[$key] = $pattern;
        }
        if ($assertMappingCountRaw !== '') {
            if (! ctype_digit($assertMappingCountRaw)) {
                return $this->renderAndExit([
                    'ok' => false,
                    'error' => 'invalid_assertion_value',
                    'assertion' => [
                        'key' => 'assert_mapping_count',
                        'value' => $assertMappingCountRaw,
                    ],
                ], self::FAILURE, $jsonOutput);
            }

            $summaryAssertions['assert_mapping_count'] = (int) $assertMappingCountRaw;
        }

        $strictSchemaAssertions = [];
        if ($assertSchemaFingerprint !== '') {
            $strictSchemaAssertions['assert_schema_fingerprint'] = $assertSchemaFingerprint;
        }
        if ($assertSchemaPathSuffix !== '') {
            $strictSchemaAssertions['assert_schema_path_suffix'] = $assertSchemaPathSuffix;
        }
        if ($assertSchemaVersionConst !== '') {
            $strictSchemaAssertions['assert_schema_version_const'] = $assertSchemaVersionConst;
        }
        if ($assertSchemaCoverageStatusConst !== '') {
            $strictSchemaAssertions['assert_schema_coverage_status_const'] = $assertSchemaCoverageStatusConst;
        }
        if ($assertSchemaValidatedRowsRaw !== '') {
            if (! ctype_digit($assertSchemaValidatedRowsRaw)) {
                return $this->renderAndExit([
                    'ok' => false,
                    'error' => 'invalid_assertion_value',
                    'assertion' => [
                        'key' => 'assert_schema_validated_rows',
                        'value' => $assertSchemaValidatedRowsRaw,
                    ],
                ], self::FAILURE, $jsonOutput);
            }

            $strictSchemaAssertions['assert_schema_validated_rows'] = (int) $assertSchemaValidatedRowsRaw;
        }

        $strictExportSchemaAssertions = [];
        if ($assertExportSchemaFingerprint !== '') {
            $strictExportSchemaAssertions['assert_export_schema_fingerprint'] = $assertExportSchemaFingerprint;
        }
        if ($assertExportSchemaPathSuffix !== '') {
            $strictExportSchemaAssertions['assert_export_schema_path_suffix'] = $assertExportSchemaPathSuffix;
        }
        if ($assertExportSchemaSummaryVersionConst !== '') {
            $strictExportSchemaAssertions['assert_export_schema_summary_version_const'] = $assertExportSchemaSummaryVersionConst;
        }
        if ($assertExportSchemaCoverageStatusConst !== '') {
            $strictExportSchemaAssertions['assert_export_schema_coverage_status_const'] = $assertExportSchemaCoverageStatusConst;
        }
        foreach ([
            'assert_export_schema_mapping_count_const' => $assertExportSchemaMappingCountConstRaw,
            'assert_export_schema_stats_rows_const' => $assertExportSchemaStatsRowsConstRaw,
            'assert_export_schema_mappings_min_items' => $assertExportSchemaMappingsMinItemsRaw,
            'assert_export_schema_validated_rows' => $assertExportSchemaValidatedRowsRaw,
        ] as $key => $rawValue) {
            if ($rawValue === '') {
                continue;
            }
            if (! ctype_digit($rawValue)) {
                return $this->renderAndExit([
                    'ok' => false,
                    'error' => 'invalid_assertion_value',
                    'assertion' => [
                        'key' => $key,
                        'value' => $rawValue,
                    ],
                ], self::FAILURE, $jsonOutput);
            }
            $strictExportSchemaAssertions[$key] = (int) $rawValue;
        }

        $summary = $this->aliasMapService->summary();
        $mappings = $this->aliasMapService->mappings();

        $payload = [
            'ok' => true,
            'summary' => $summary,
            'queries' => [],
            'checks' => [],
        ];

        if ($ciBaselinePreset) {
            $payload['checks']['ci_baseline_preset'] = true;
            $payload['checks']['ci_baseline_profile'] = $ciBaselineProfile ?? $this->aliasMapService->ciBaselinePresetProfile();
        }
        if ($ciCheckPreset) {
            $payload['checks']['ci_preset'] = true;
        }

        if ($includeFingerprints) {
            $payload['checks']['fingerprints'] = $this->aliasMapService->fingerprintsDiagnostics();
        }

        if ($includeStats) {
            $payload['stats'] = $this->aliasMapService->stats();
        }

        if ($statsAssertions !== []) {
            $payload['checks']['stats_thresholds'] = $this->aliasMapService->statsThresholdAssertionsDiagnostics($statsAssertions);
        }

        if ($roundTripCheck) {
            $payload['checks']['roundtrip'] = $this->aliasMapService->roundTripConsistencyDiagnostics();
        }

        if ($sourceKey !== '') {
            $resolved = $this->aliasMapService->resolveCanonicalBuilderKeys($sourceKey);
            $payload['queries']['source_key'] = [
                'source_component_key' => $sourceKey,
                'canonical_builder_keys' => $resolved,
                'found' => $resolved !== [],
            ];

            if ($resolved === []) {
                return $this->renderAndExit([
                    'ok' => false,
                    'error' => 'source_component_key_not_found',
                    'summary' => $summary,
                    'queries' => $payload['queries'],
                ], self::FAILURE, $jsonOutput);
            }
        }

        if ($canonicalKey !== '') {
            $resolved = $this->aliasMapService->findSourceComponentKeysForCanonicalBuilderKey($canonicalKey);
            $payload['queries']['canonical_key'] = [
                'canonical_builder_key' => $canonicalKey,
                'source_component_keys' => $resolved,
                'found' => $resolved !== [],
            ];

            if ($resolved === []) {
                return $this->renderAndExit([
                    'ok' => false,
                    'error' => 'canonical_builder_key_not_found',
                    'summary' => $summary,
                    'queries' => $payload['queries'],
                ], self::FAILURE, $jsonOutput);
            }
        }

        if ($assertSourceKeys !== [] || $assertCanonicalKeys !== []) {
            $payload['checks']['required_aliases'] = $this->aliasMapService->requiredAliasAssertionsDiagnostics(
                $assertSourceKeys,
                $assertCanonicalKeys
            );
        }

        if ($assertSourcePrimaryPairs !== []) {
            $payload['checks']['source_primary_assertions'] = $this->aliasMapService->sourcePrimaryAssertionsDiagnostics(
                $assertSourcePrimaryPairs
            );
        }

        if ($assertSourceContainsPairs !== []) {
            $payload['checks']['source_contains_canonical_assertions'] = $this->aliasMapService->sourceContainsCanonicalAssertionsDiagnostics(
                $assertSourceContainsPairs
            );
        }
        if ($assertSourceCanonicalSetPairs !== []) {
            $payload['checks']['source_canonical_set_assertions'] = $this->aliasMapService->sourceCanonicalSetAssertionsDiagnostics(
                $assertSourceCanonicalSetPairs
            );
        }
        if ($assertCanonicalSourceSetPairs !== []) {
            $payload['checks']['canonical_source_set_assertions'] = $this->aliasMapService->canonicalSourceSetAssertionsDiagnostics(
                $assertCanonicalSourceSetPairs
            );
        }

        $payload['checks']['mapping_count_matches_rows'] = ((int) ($summary['mapping_count'] ?? 0)) === count($mappings);

        if ($summaryAssertionsRequested) {
            $payload['checks']['summary_assertions'] = $this->aliasMapService->summaryAssertionsDiagnostics($summaryAssertions);
        }

        if ($strictSchema) {
            $payload['checks']['strict_schema'] = $this->aliasMapService->strictSchemaDiagnostics();
        }
        if ($strictExportSchema) {
            $payload['checks']['export_schema'] = $this->aliasMapService->exportSchemaDiagnostics();
        }
        if ($strictSchemaAssertionsRequested) {
            $payload['checks']['strict_schema_assertions'] = $this->aliasMapService->strictSchemaAssertionsDiagnostics($strictSchemaAssertions);
        }
        if ($strictExportSchemaAssertionsRequested) {
            $payload['checks']['strict_export_schema_assertions'] = $this->aliasMapService->strictExportSchemaAssertionsDiagnostics($strictExportSchemaAssertions);
        }

        if (! (bool) $payload['checks']['mapping_count_matches_rows']) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'mapping_count_mismatch',
                'summary' => $summary,
                'checks' => $payload['checks'],
            ], self::FAILURE, $jsonOutput);
        }

        if (($assertSourceKeys !== [] || $assertCanonicalKeys !== [])
            && ! (bool) data_get($payload, 'checks.required_aliases.ok')) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'required_alias_assertion_failed',
                'summary' => $summary,
                'checks' => $payload['checks'],
                'queries' => $payload['queries'],
            ], self::FAILURE, $jsonOutput);
        }

        if ($assertSourcePrimaryPairs !== []
            && ! (bool) data_get($payload, 'checks.source_primary_assertions.ok')) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'source_primary_assertion_failed',
                'summary' => $summary,
                'checks' => $payload['checks'],
                'queries' => $payload['queries'],
            ], self::FAILURE, $jsonOutput);
        }

        if ($assertSourceContainsPairs !== []
            && ! (bool) data_get($payload, 'checks.source_contains_canonical_assertions.ok')) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'source_contains_canonical_assertion_failed',
                'summary' => $summary,
                'checks' => $payload['checks'],
                'queries' => $payload['queries'],
            ], self::FAILURE, $jsonOutput);
        }
        if ($assertSourceCanonicalSetPairs !== []
            && ! (bool) data_get($payload, 'checks.source_canonical_set_assertions.ok')) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'source_canonical_set_assertion_failed',
                'summary' => $summary,
                'checks' => $payload['checks'],
                'queries' => $payload['queries'],
            ], self::FAILURE, $jsonOutput);
        }
        if ($assertCanonicalSourceSetPairs !== []
            && ! (bool) data_get($payload, 'checks.canonical_source_set_assertions.ok')) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'canonical_source_set_assertion_failed',
                'summary' => $summary,
                'checks' => $payload['checks'],
                'queries' => $payload['queries'],
            ], self::FAILURE, $jsonOutput);
        }

        if ($summaryAssertionsRequested && ! (bool) data_get($payload, 'checks.summary_assertions.ok')) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'summary_assertion_failed',
                'summary' => $summary,
                'checks' => $payload['checks'],
                'queries' => $payload['queries'],
            ], self::FAILURE, $jsonOutput);
        }

        if ($strictSchema && ! (bool) data_get($payload, 'checks.strict_schema.ok')) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'strict_schema_validation_failed',
                'summary' => $summary,
                'checks' => $payload['checks'],
                'queries' => $payload['queries'],
            ], self::FAILURE, $jsonOutput);
        }

        if ($strictExportSchema && ! (bool) data_get($payload, 'checks.export_schema.ok')) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'export_schema_validation_failed',
                'summary' => $summary,
                'checks' => $payload['checks'],
                'queries' => $payload['queries'],
            ], self::FAILURE, $jsonOutput);
        }

        if ($strictSchemaAssertionsRequested && ! (bool) data_get($payload, 'checks.strict_schema_assertions.ok')) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'strict_schema_assertion_failed',
                'summary' => $summary,
                'checks' => $payload['checks'],
                'queries' => $payload['queries'],
            ], self::FAILURE, $jsonOutput);
        }

        if ($strictExportSchemaAssertionsRequested && ! (bool) data_get($payload, 'checks.strict_export_schema_assertions.ok')) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'strict_export_schema_assertion_failed',
                'summary' => $summary,
                'checks' => $payload['checks'],
                'queries' => $payload['queries'],
            ], self::FAILURE, $jsonOutput);
        }

        if ($roundTripCheck && ! (bool) data_get($payload, 'checks.roundtrip.ok')) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'roundtrip_consistency_check_failed',
                'summary' => $summary,
                'checks' => $payload['checks'],
                'queries' => $payload['queries'],
            ], self::FAILURE, $jsonOutput);
        }

        if ($statsAssertions !== [] && ! (bool) data_get($payload, 'checks.stats_thresholds.ok')) {
            return $this->renderAndExit([
                'ok' => false,
                'error' => 'stats_threshold_assertion_failed',
                'summary' => $summary,
                'checks' => $payload['checks'],
                'queries' => $payload['queries'],
            ], self::FAILURE, $jsonOutput);
        }

        if ($exportMode !== null) {
            return $this->renderExportAndExit($exportMode, $summary, $mappings, $outputPath, $overwrite);
        }

        return $this->renderAndExit($payload, self::SUCCESS, $jsonOutput);
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<int, array<string, mixed>>  $mappings
     */
    private function renderExportAndExit(string $mode, array $summary, array $mappings, string $outputPath = '', bool $overwrite = false): int
    {
        $content = $mode === 'csv'
            ? $this->buildCsvExport($summary, $mappings)
            : ($mode === 'md'
                ? $this->buildMarkdownExport($summary, $mappings)
                : $this->buildJsonExport($summary, $mappings));

        if ($outputPath !== '') {
            $resolved = $this->resolveExportOutputPath($mode, $outputPath);
            if (! (bool) ($resolved['ok'] ?? false)) {
                return $this->renderAndExit([
                    'ok' => false,
                    'error' => (string) ($resolved['error'] ?? 'invalid_output_path'),
                    'summary' => $summary,
                    'output' => $resolved,
                ], self::FAILURE, false);
            }

            $absolutePath = (string) ($resolved['absolute_path'] ?? '');
            if (File::exists($absolutePath) && ! $overwrite) {
                return $this->renderAndExit([
                    'ok' => false,
                    'error' => 'output_file_exists',
                    'summary' => $summary,
                    'output' => $resolved + ['exists' => true],
                ], self::FAILURE, false);
            }

            $bytesWritten = File::put($absolutePath, $content);
            if ($bytesWritten === false) {
                return $this->renderAndExit([
                    'ok' => false,
                    'error' => 'export_output_write_failed',
                    'summary' => $summary,
                    'output' => $resolved,
                ], self::FAILURE, false);
            }

            $this->info('Exported component-library alias map report.');
            $this->line('Format: '.$mode);
            $this->line('Output: '.(string) ($resolved['relative_path'] ?? $outputPath));
            $this->line('Overwrite: '.($overwrite ? 'yes' : 'no'));
            $this->line('Bytes: '.(string) (int) $bytesWritten);

            return self::SUCCESS;
        }

        $this->output->write($content);

        if (! str_ends_with($content, "\n")) {
            $this->newLine();
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveExportOutputPath(string $mode, string $requestedPath): array
    {
        $requestedPath = trim($requestedPath);
        if ($requestedPath === '') {
            return ['ok' => false, 'error' => 'invalid_output_path'];
        }

        if ($this->isAbsolutePath($requestedPath)) {
            return ['ok' => false, 'error' => 'absolute_output_path_not_allowed'];
        }

        $normalized = str_replace('\\', '/', $requestedPath);
        $segments = array_values(array_filter(explode('/', $normalized), static fn (string $segment): bool => $segment !== ''));
        if ($segments === []) {
            return ['ok' => false, 'error' => 'invalid_output_path'];
        }

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '..') {
                return ['ok' => false, 'error' => 'output_path_traversal_not_allowed'];
            }
        }

        $relativePath = implode('/', $segments);
        $expectedExtension = match ($mode) {
            'csv' => '.csv',
            'md' => '.md',
            default => '.json',
        };
        if (! str_ends_with(strtolower($relativePath), $expectedExtension)) {
            return [
                'ok' => false,
                'error' => 'output_extension_mismatch',
                'expected_extension' => $expectedExtension,
            ];
        }

        $baseDirectory = storage_path('app/cms/component-library-alias-map-exports');
        File::ensureDirectoryExists($baseDirectory);

        $absolutePath = $baseDirectory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $parentDirectory = dirname($absolutePath);
        if ($parentDirectory !== '' && $parentDirectory !== '.') {
            File::ensureDirectoryExists($parentDirectory);
        }

        return [
            'ok' => true,
            'mode' => $mode,
            'base_directory' => $baseDirectory,
            'relative_path' => $relativePath,
            'absolute_path' => $absolutePath,
        ];
    }

    private function isAbsolutePath(string $path): bool
    {
        if ($path === '') {
            return false;
        }

        if (str_starts_with($path, '/') || str_starts_with($path, '\\')) {
            return true;
        }

        return preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<int, array<string, mixed>>  $mappings
     */
    private function buildCsvExport(array $summary, array $mappings): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, ['version', (string) ($summary['version'] ?? '')]);
        fputcsv($handle, ['coverage_status', (string) ($summary['coverage_status'] ?? '')]);
        fputcsv($handle, ['mapping_count', (string) (int) ($summary['mapping_count'] ?? 0)]);
        fputcsv($handle, []);
        fputcsv($handle, ['source_component_key', 'coverage', 'canonical_builder_keys', 'primary_canonical_builder_key', 'canonical_builder_key_count']);

        foreach ($mappings as $row) {
            $canonicalKeys = array_values(array_map('strval', (array) ($row['canonical_builder_keys'] ?? [])));

            fputcsv($handle, [
                (string) ($row['source_component_key'] ?? ''),
                (string) ($row['coverage'] ?? ''),
                implode('|', $canonicalKeys),
                (string) ($canonicalKeys[0] ?? ''),
                (string) count($canonicalKeys),
            ]);
        }

        rewind($handle);
        $contents = stream_get_contents($handle);
        fclose($handle);

        return is_string($contents) ? $contents : '';
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<int, array<string, mixed>>  $mappings
     */
    private function buildMarkdownExport(array $summary, array $mappings): string
    {
        $lines = [
            '# Component Library Alias Map Export (v1)',
            '',
            '- `version`: `'.(string) ($summary['version'] ?? '').'`',
            '- `coverage_status`: `'.(string) ($summary['coverage_status'] ?? '').'`',
            '- `mapping_count`: `'.(string) (int) ($summary['mapping_count'] ?? 0).'`',
            '',
            '| source_component_key | coverage | primary_canonical_builder_key | canonical_builder_keys |',
            '| --- | --- | --- | --- |',
        ];

        foreach ($mappings as $row) {
            $canonicalKeys = array_values(array_map('strval', (array) ($row['canonical_builder_keys'] ?? [])));
            $canonicalList = implode('<br>', array_map(
                static fn (string $value): string => str_replace('|', '\\|', $value),
                $canonicalKeys
            ));

            $lines[] = '| `'.str_replace('|', '\\|', (string) ($row['source_component_key'] ?? '')).'`'
                .' | `'.str_replace('|', '\\|', (string) ($row['coverage'] ?? '')).'`'
                .' | `'.str_replace('|', '\\|', (string) ($canonicalKeys[0] ?? '')).'`'
                .' | '.$canonicalList.' |';
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  array<string, mixed>  $summary
     * @param  array<int, array<string, mixed>>  $mappings
     */
    private function buildJsonExport(array $summary, array $mappings): string
    {
        $payload = [
            'summary' => $summary,
            'stats' => $this->aliasMapService->stats(),
            'fingerprints' => $this->aliasMapService->fingerprintsDiagnostics(),
            'mappings' => $mappings,
        ];

        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)."\n";
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function renderAndExit(array $payload, int $exitCode, bool $jsonOutput): int
    {
        if ($jsonOutput) {
            $this->line((string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return $exitCode;
        }

        if ((bool) ($payload['ok'] ?? false)) {
            $this->info('Validated component-library spec equivalence alias map (v1).');
        } else {
            $this->error('Component-library spec equivalence alias map validation failed.');
            $error = (string) ($payload['error'] ?? 'unknown_error');
            $this->line('Error: '.$error);
        }

        $summary = is_array($payload['summary'] ?? null) ? $payload['summary'] : [];
        if ($summary !== []) {
            $this->table(
                ['Field', 'Value'],
                [
                    ['Version', (string) ($summary['version'] ?? '')],
                    ['Coverage', (string) ($summary['coverage_status'] ?? '')],
                    ['Mappings', (string) (int) ($summary['mapping_count'] ?? 0)],
                    ['Source Spec', (string) ($summary['source_spec_ref'] ?? '')],
                ]
            );
        }

        $fingerprints = is_array(data_get($payload, 'checks.fingerprints'))
            ? (array) data_get($payload, 'checks.fingerprints')
            : [];
        if ($fingerprints !== []) {
            $this->newLine();
            $this->line('Fingerprint diagnostics:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['OK', (bool) ($fingerprints['ok'] ?? false) ? 'yes' : 'no'],
                    ['Map version', (string) ($fingerprints['map_version'] ?? '')],
                    ['Coverage status', (string) ($fingerprints['coverage_status'] ?? '')],
                    ['Mapping count', (string) (int) ($fingerprints['mapping_count'] ?? 0)],
                    ['Map fingerprint', (string) ($fingerprints['map_fingerprint'] ?? '')],
                    ['Schema fingerprint', (string) ($fingerprints['schema_fingerprint'] ?? '')],
                    ['Export schema fingerprint', (string) ($fingerprints['export_schema_fingerprint'] ?? '')],
                    ['Export content fingerprint', (string) ($fingerprints['export_content_fingerprint'] ?? '')],
                    ['Artifact bundle fingerprint', (string) ($fingerprints['artifact_bundle_fingerprint'] ?? '')],
                ]
            );
        }

        $summaryAssertions = is_array(data_get($payload, 'checks.summary_assertions'))
            ? (array) data_get($payload, 'checks.summary_assertions')
            : [];
        if ($summaryAssertions !== []) {
            $this->newLine();
            $this->line('Summary assertions:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['OK', (bool) ($summaryAssertions['ok'] ?? false) ? 'yes' : 'no'],
                    ['Failed assertions', (string) (int) ($summaryAssertions['failed_count'] ?? 0)],
                    ['Version', (string) ($summaryAssertions['actual']['version'] ?? '')],
                    ['Coverage status', (string) ($summaryAssertions['actual']['coverage_status'] ?? '')],
                    ['Summary mapping_count', (string) (int) ($summaryAssertions['actual']['mapping_count'] ?? 0)],
                    ['Row count', (string) (int) ($summaryAssertions['actual']['row_count'] ?? 0)],
                    ['Source spec ref', (string) ($summaryAssertions['actual']['source_spec_ref'] ?? '')],
                    ['Gap audit ref', (string) ($summaryAssertions['actual']['gap_audit_ref'] ?? '')],
                    ['Map fingerprint', (string) ($summaryAssertions['actual']['map_fingerprint'] ?? '')],
                    ['Artifact bundle fingerprint', (string) ($summaryAssertions['actual']['artifact_bundle_fingerprint'] ?? '')],
                ]
            );
        }

        $requiredAliases = is_array(data_get($payload, 'checks.required_aliases'))
            ? (array) data_get($payload, 'checks.required_aliases')
            : [];
        if ($requiredAliases !== []) {
            $this->newLine();
            $this->line('Required alias assertions:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['OK', (bool) ($requiredAliases['ok'] ?? false) ? 'yes' : 'no'],
                    ['Required source keys', (string) (int) ($requiredAliases['required_source_key_count'] ?? 0)],
                    ['Missing source keys', (string) (int) ($requiredAliases['missing_source_key_count'] ?? 0)],
                    ['Required canonical keys', (string) (int) ($requiredAliases['required_canonical_key_count'] ?? 0)],
                    ['Missing canonical keys', (string) (int) ($requiredAliases['missing_canonical_key_count'] ?? 0)],
                    ['Failed assertions', (string) (int) ($requiredAliases['failed_count'] ?? 0)],
                ]
            );
        }

        $sourcePrimaryAssertions = is_array(data_get($payload, 'checks.source_primary_assertions'))
            ? (array) data_get($payload, 'checks.source_primary_assertions')
            : [];
        if ($sourcePrimaryAssertions !== []) {
            $this->newLine();
            $this->line('Source primary assertions:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['OK', (bool) ($sourcePrimaryAssertions['ok'] ?? false) ? 'yes' : 'no'],
                    ['Required pairs', (string) (int) ($sourcePrimaryAssertions['required_pair_count'] ?? 0)],
                    ['Evaluated pairs', (string) (int) ($sourcePrimaryAssertions['evaluated_count'] ?? 0)],
                    ['Invalid pairs', (string) (int) ($sourcePrimaryAssertions['invalid_pair_count'] ?? 0)],
                    ['Failed assertions', (string) (int) ($sourcePrimaryAssertions['failed_count'] ?? 0)],
                ]
            );
        }

        $sourceContainsAssertions = is_array(data_get($payload, 'checks.source_contains_canonical_assertions'))
            ? (array) data_get($payload, 'checks.source_contains_canonical_assertions')
            : [];
        if ($sourceContainsAssertions !== []) {
            $this->newLine();
            $this->line('Source contains canonical assertions:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['OK', (bool) ($sourceContainsAssertions['ok'] ?? false) ? 'yes' : 'no'],
                    ['Required pairs', (string) (int) ($sourceContainsAssertions['required_pair_count'] ?? 0)],
                    ['Evaluated pairs', (string) (int) ($sourceContainsAssertions['evaluated_count'] ?? 0)],
                    ['Invalid pairs', (string) (int) ($sourceContainsAssertions['invalid_pair_count'] ?? 0)],
                    ['Failed assertions', (string) (int) ($sourceContainsAssertions['failed_count'] ?? 0)],
                ]
            );
        }

        $sourceCanonicalSetAssertions = is_array(data_get($payload, 'checks.source_canonical_set_assertions'))
            ? (array) data_get($payload, 'checks.source_canonical_set_assertions')
            : [];
        if ($sourceCanonicalSetAssertions !== []) {
            $this->newLine();
            $this->line('Source canonical set assertions:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['OK', (bool) ($sourceCanonicalSetAssertions['ok'] ?? false) ? 'yes' : 'no'],
                    ['Required pairs', (string) (int) ($sourceCanonicalSetAssertions['required_pair_count'] ?? 0)],
                    ['Evaluated pairs', (string) (int) ($sourceCanonicalSetAssertions['evaluated_count'] ?? 0)],
                    ['Invalid pairs', (string) (int) ($sourceCanonicalSetAssertions['invalid_pair_count'] ?? 0)],
                    ['Failed assertions', (string) (int) ($sourceCanonicalSetAssertions['failed_count'] ?? 0)],
                ]
            );
        }

        $canonicalSourceSetAssertions = is_array(data_get($payload, 'checks.canonical_source_set_assertions'))
            ? (array) data_get($payload, 'checks.canonical_source_set_assertions')
            : [];
        if ($canonicalSourceSetAssertions !== []) {
            $this->newLine();
            $this->line('Canonical source set assertions:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['OK', (bool) ($canonicalSourceSetAssertions['ok'] ?? false) ? 'yes' : 'no'],
                    ['Required pairs', (string) (int) ($canonicalSourceSetAssertions['required_pair_count'] ?? 0)],
                    ['Evaluated pairs', (string) (int) ($canonicalSourceSetAssertions['evaluated_count'] ?? 0)],
                    ['Invalid pairs', (string) (int) ($canonicalSourceSetAssertions['invalid_pair_count'] ?? 0)],
                    ['Failed assertions', (string) (int) ($canonicalSourceSetAssertions['failed_count'] ?? 0)],
                ]
            );
        }

        $strictSchema = is_array(data_get($payload, 'checks.strict_schema'))
            ? (array) data_get($payload, 'checks.strict_schema')
            : [];
        if ($strictSchema !== []) {
            $this->newLine();
            $this->line('Strict schema checks:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['OK', (bool) ($strictSchema['ok'] ?? false) ? 'yes' : 'no'],
                    ['Validated Rows', (string) (int) ($strictSchema['validated_rows'] ?? 0)],
                    ['Errors', (string) (int) ($strictSchema['error_count'] ?? 0)],
                    ['Schema Path', (string) ($strictSchema['schema_path'] ?? '')],
                ]
            );
        }

        $strictSchemaAssertions = is_array(data_get($payload, 'checks.strict_schema_assertions'))
            ? (array) data_get($payload, 'checks.strict_schema_assertions')
            : [];
        if ($strictSchemaAssertions !== []) {
            $this->newLine();
            $this->line('Strict schema assertions:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['OK', (bool) ($strictSchemaAssertions['ok'] ?? false) ? 'yes' : 'no'],
                    ['Failed assertions', (string) (int) ($strictSchemaAssertions['failed_count'] ?? 0)],
                    ['Strict schema OK', (bool) ($strictSchemaAssertions['actual']['strict_schema_ok'] ?? false) ? 'yes' : 'no'],
                    ['Schema path', (string) ($strictSchemaAssertions['actual']['schema_path'] ?? '')],
                    ['Schema fingerprint', (string) ($strictSchemaAssertions['actual']['schema_fingerprint'] ?? '')],
                    ['Schema version const', (string) ($strictSchemaAssertions['actual']['schema_version_const'] ?? '')],
                    ['Schema coverage_status const', (string) ($strictSchemaAssertions['actual']['schema_coverage_status_const'] ?? '')],
                    ['Schema validated rows', (string) (int) ($strictSchemaAssertions['actual']['validated_rows'] ?? 0)],
                ]
            );
        }

        $strictExportSchemaAssertions = is_array(data_get($payload, 'checks.strict_export_schema_assertions'))
            ? (array) data_get($payload, 'checks.strict_export_schema_assertions')
            : [];
        if ($strictExportSchemaAssertions !== []) {
            $this->newLine();
            $this->line('Strict export schema assertions:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['OK', (bool) ($strictExportSchemaAssertions['ok'] ?? false) ? 'yes' : 'no'],
                    ['Failed assertions', (string) (int) ($strictExportSchemaAssertions['failed_count'] ?? 0)],
                    ['Export schema OK', (bool) ($strictExportSchemaAssertions['actual']['export_schema_ok'] ?? false) ? 'yes' : 'no'],
                    ['Schema path', (string) ($strictExportSchemaAssertions['actual']['schema_path'] ?? '')],
                    ['Schema fingerprint', (string) ($strictExportSchemaAssertions['actual']['schema_fingerprint'] ?? '')],
                    ['Summary version const', (string) ($strictExportSchemaAssertions['actual']['schema_summary_version_const'] ?? '')],
                    ['Summary coverage_status const', (string) ($strictExportSchemaAssertions['actual']['schema_summary_coverage_status_const'] ?? '')],
                    ['Summary mapping_count const', (string) (int) ($strictExportSchemaAssertions['actual']['schema_summary_mapping_count_const'] ?? 0)],
                    ['Stats rows const', (string) (int) ($strictExportSchemaAssertions['actual']['schema_stats_rows_const'] ?? 0)],
                    ['Mappings minItems', (string) (int) ($strictExportSchemaAssertions['actual']['schema_mappings_min_items'] ?? 0)],
                    ['Validated rows', (string) (int) ($strictExportSchemaAssertions['actual']['validated_rows'] ?? 0)],
                ]
            );
        }

        $exportSchema = is_array(data_get($payload, 'checks.export_schema'))
            ? (array) data_get($payload, 'checks.export_schema')
            : [];
        if ($exportSchema !== []) {
            $this->newLine();
            $this->line('Export schema checks:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['OK', (bool) ($exportSchema['ok'] ?? false) ? 'yes' : 'no'],
                    ['Validated Rows', (string) (int) ($exportSchema['validated_rows'] ?? 0)],
                    ['Errors', (string) (int) ($exportSchema['error_count'] ?? 0)],
                    ['Schema Path', (string) ($exportSchema['schema_path'] ?? '')],
                    ['Schema Fingerprint', (string) ($exportSchema['schema_fingerprint'] ?? '')],
                    ['Stats rows const', (string) ($exportSchema['schema_stats_rows_const'] ?? '')],
                    ['Mappings minItems', (string) ($exportSchema['schema_mappings_min_items'] ?? '')],
                ]
            );
        }

        $statsThresholds = is_array(data_get($payload, 'checks.stats_thresholds'))
            ? (array) data_get($payload, 'checks.stats_thresholds')
            : [];
        if ($statsThresholds !== []) {
            $this->newLine();
            $this->line('Stats threshold checks:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['OK', (bool) ($statsThresholds['ok'] ?? false) ? 'yes' : 'no'],
                    ['Covered rows', (string) (int) ($statsThresholds['actual']['covered_rows'] ?? 0)],
                    ['Partial rows', (string) (int) ($statsThresholds['actual']['partial'] ?? 0)],
                    ['Missing rows', (string) (int) ($statsThresholds['actual']['missing'] ?? 0)],
                    ['Unknown rows', (string) (int) ($statsThresholds['actual']['unknown'] ?? 0)],
                    ['Composite alias rows', (string) (int) ($statsThresholds['actual']['composite_alias_rows'] ?? 0)],
                    ['Max canonical keys / row', (string) (int) ($statsThresholds['actual']['max_canonical_keys_per_row'] ?? 0)],
                    ['Distinct canonical keys', (string) (int) ($statsThresholds['actual']['distinct_canonical_builder_key_count'] ?? 0)],
                    ['Failed assertions', (string) (int) ($statsThresholds['failed_count'] ?? 0)],
                ]
            );
        }

        $roundTrip = is_array(data_get($payload, 'checks.roundtrip'))
            ? (array) data_get($payload, 'checks.roundtrip')
            : [];
        if ($roundTrip !== []) {
            $this->newLine();
            $this->line('Round-trip consistency checks:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['OK', (bool) ($roundTrip['ok'] ?? false) ? 'yes' : 'no'],
                    ['Rows', (string) (int) ($roundTrip['rows'] ?? 0)],
                    ['Duplicate source keys', (string) (int) ($roundTrip['duplicate_source_component_keys_count'] ?? 0)],
                    ['Rows with duplicate canonical keys', (string) (int) ($roundTrip['rows_with_duplicate_canonical_keys'] ?? 0)],
                    ['Inverse lookup misses', (string) (int) ($roundTrip['inverse_lookup_miss_count'] ?? 0)],
                    ['Primary key mismatches', (string) (int) ($roundTrip['primary_key_mismatch_count'] ?? 0)],
                    ['Rows without canonical keys', (string) (int) ($roundTrip['rows_without_canonical_keys'] ?? 0)],
                ]
            );
        }

        $stats = is_array($payload['stats'] ?? null) ? (array) ($payload['stats'] ?? []) : [];
        if ($stats !== []) {
            $coverageBreakdown = is_array($stats['coverage_breakdown'] ?? null)
                ? (array) ($stats['coverage_breakdown'] ?? [])
                : [];

            $this->newLine();
            $this->line('Alias map stats:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Rows', (string) (int) ($stats['rows'] ?? 0)],
                    ['Distinct canonical keys', (string) (int) ($stats['distinct_canonical_builder_key_count'] ?? 0)],
                    ['Rows with multi-canonical aliases', (string) (int) ($stats['rows_with_multiple_canonical_keys'] ?? 0)],
                    ['Max canonical keys per row', (string) (int) ($stats['max_canonical_keys_per_row'] ?? 0)],
                    ['Composite alias rows', (string) (int) ($stats['composite_alias_rows'] ?? 0)],
                ]
            );

            if ($coverageBreakdown !== []) {
                $this->table(
                    ['Coverage', 'Count'],
                    [
                        ['exact', (string) (int) ($coverageBreakdown['exact'] ?? 0)],
                        ['equivalent', (string) (int) ($coverageBreakdown['equivalent'] ?? 0)],
                        ['partial', (string) (int) ($coverageBreakdown['partial'] ?? 0)],
                        ['missing', (string) (int) ($coverageBreakdown['missing'] ?? 0)],
                        ['unknown', (string) (int) ($coverageBreakdown['unknown'] ?? 0)],
                    ]
                );
            }
        }

        $queries = is_array($payload['queries'] ?? null) ? $payload['queries'] : [];
        if ($queries !== []) {
            $this->newLine();
            $this->line('Lookup results:');
            $rows = [];
            if (is_array($queries['source_key'] ?? null)) {
                $rows[] = [
                    'Query' => 'source-key',
                    'Input' => (string) ($queries['source_key']['source_component_key'] ?? ''),
                    'Found' => (bool) ($queries['source_key']['found'] ?? false) ? 'yes' : 'no',
                    'Result' => implode(', ', (array) ($queries['source_key']['canonical_builder_keys'] ?? [])),
                ];
            }
            if (is_array($queries['canonical_key'] ?? null)) {
                $rows[] = [
                    'Query' => 'canonical-key',
                    'Input' => (string) ($queries['canonical_key']['canonical_builder_key'] ?? ''),
                    'Found' => (bool) ($queries['canonical_key']['found'] ?? false) ? 'yes' : 'no',
                    'Result' => implode(', ', (array) ($queries['canonical_key']['source_component_keys'] ?? [])),
                ];
            }

            if ($rows !== []) {
                $this->table(['Query', 'Input', 'Found', 'Result'], $rows);
            }
        }

        $output = is_array($payload['output'] ?? null) ? $payload['output'] : [];
        if ($output !== []) {
            $this->newLine();
            $this->line('Output target:');
            $this->table(
                ['Field', 'Value'],
                [
                    ['Relative Path', (string) ($output['relative_path'] ?? '')],
                    ['Base Directory', (string) ($output['base_directory'] ?? '')],
                    ['Expected Ext', (string) ($output['expected_extension'] ?? '')],
                    ['Exists', (bool) ($output['exists'] ?? false) ? 'yes' : 'no'],
                ]
            );
        }

        return $exitCode;
    }

}
