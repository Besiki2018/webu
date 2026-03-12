<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;

class CmsComponentLibrarySpecEquivalenceAliasMapService
{
    public const MAP_VERSION = 'v1';

    private const MAP_PATH = 'docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json';
    private const MAP_SCHEMA_PATH = 'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map.v1.schema.json';
    private const EXPORT_SCHEMA_PATH = 'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map-export.v1.schema.json';
    private const CI_BASELINE_REQUIRED_SOURCE_KEYS = [
        'auth.security',
        'ecom.productDetail',
    ];
    private const CI_BASELINE_REQUIRED_CANONICAL_KEYS = [
        'webu_ecom_account_security_01',
        'webu_ecom_product_detail_01',
    ];
    private const CI_BASELINE_SOURCE_PRIMARY_ASSERTIONS = [
        'auth.security=webu_ecom_account_security_01',
        'ecom.productDetail=webu_ecom_product_detail_01',
    ];
    private const CI_BASELINE_SOURCE_CONTAINS_ASSERTIONS = [
        'ecom.productDetail=webu_ecom_add_to_cart_button_01',
        'ecom.productDetail=webu_ecom_product_tabs_01',
    ];
    private const CI_BASELINE_SOURCE_CANONICAL_SET_ASSERTIONS = [
        'ecom.productDetail=webu_ecom_product_detail_01|webu_ecom_add_to_cart_button_01|webu_ecom_product_tabs_01',
    ];
    private const CI_BASELINE_CANONICAL_SOURCE_SET_ASSERTIONS = [
        'webu_ecom_account_security_01=auth.security',
        'webu_ecom_product_detail_01=ecom.productDetail',
    ];
    private const CI_BASELINE_SUMMARY_ASSERTIONS = [
        'assert_map_version' => 'v1',
        'assert_coverage_status' => 'complete_exact_plus_equivalent',
        'assert_mapping_count' => 70,
        'assert_map_fingerprint' => 'sha256:92f1328bba173e5b2977039eb59c7898dad82059b117309c768d0b2012fea5b3',
        'assert_artifact_bundle_fingerprint' => 'sha256:e09b828d62137a081eb532657d80c47d9b08d93a5f7596e8505efffb32992826',
        'assert_source_spec_ref_contains' => 'PROJECT_ROADMAP_TASKS_KA.md:6439',
        'assert_gap_audit_ref_contains' => 'UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md',
        'assert_source_spec_ref_regex' => '/PROJECT_ROADMAP_TASKS_KA\\.md:6439-\\d+/',
        'assert_gap_audit_ref_regex' => '/UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE\\.md$/',
    ];
    private const CI_BASELINE_STRICT_SCHEMA_ASSERTIONS = [
        'assert_schema_fingerprint' => 'sha256:5c298742ffa0478586ad71f9ef0e4272f57703ba85566a722bcd37066df6ef2e',
        'assert_schema_path_suffix' => 'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map.v1.schema.json',
        'assert_schema_version_const' => 'v1',
        'assert_schema_coverage_status_const' => 'complete_exact_plus_equivalent',
        'assert_schema_validated_rows' => 70,
    ];
    private const CI_BASELINE_STRICT_EXPORT_SCHEMA_ASSERTIONS = [
        'assert_export_schema_path_suffix' => 'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map-export.v1.schema.json',
        'assert_export_schema_summary_version_const' => 'v1',
        'assert_export_schema_coverage_status_const' => 'complete_exact_plus_equivalent',
        'assert_export_schema_mapping_count_const' => 70,
        'assert_export_schema_stats_rows_const' => 70,
        'assert_export_schema_mappings_min_items' => 70,
        'assert_export_schema_validated_rows' => 70,
    ];
    private const CI_BASELINE_STATS_ASSERTIONS = [
        'assert_max_partial' => 0,
        'assert_max_missing' => 0,
        'assert_max_unknown' => 0,
        'assert_min_covered' => 70,
        'assert_max_composite_alias_rows' => 3,
        'assert_max_canonical_keys_per_row' => 3,
        'assert_min_distinct_canonical_keys' => 74,
    ];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $cache = null;

    /**
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $map = $this->loadMap();

        return [
            'version' => (string) ($map['version'] ?? self::MAP_VERSION),
            'source_spec_ref' => (string) ($map['source_spec_ref'] ?? ''),
            'gap_audit_ref' => (string) ($map['gap_audit_ref'] ?? ''),
            'coverage_status' => (string) ($map['coverage_status'] ?? ''),
            'mapping_count' => (int) ($map['mapping_count'] ?? 0),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function mappings(): array
    {
        $map = $this->loadMap();

        return array_values(array_filter(
            $map['mappings'] ?? [],
            static fn ($row): bool => is_array($row)
        ));
    }

    public function hasSourceComponentKey(string $sourceComponentKey): bool
    {
        return $this->resolveCanonicalBuilderKeys($sourceComponentKey) !== [];
    }

    public function fingerprint(): string
    {
        $summary = $this->summary();
        $payload = [
            'version' => (string) ($summary['version'] ?? self::MAP_VERSION),
            'source_spec_ref' => (string) ($summary['source_spec_ref'] ?? ''),
            'gap_audit_ref' => (string) ($summary['gap_audit_ref'] ?? ''),
            'coverage_status' => (string) ($summary['coverage_status'] ?? ''),
            'mapping_count' => (int) ($summary['mapping_count'] ?? 0),
            'mappings' => $this->mappings(),
        ];

        $normalized = $this->normalizeForFingerprint($payload);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            throw new RuntimeException('Component-library equivalence alias map fingerprint serialization failed');
        }

        return 'sha256:'.hash('sha256', $json);
    }

    public function schemaFingerprint(): string
    {
        $schemaPath = base_path(self::MAP_SCHEMA_PATH);
        if (! File::exists($schemaPath) || ! File::isFile($schemaPath)) {
            throw new RuntimeException('Component-library equivalence alias map schema JSON not found');
        }

        $decoded = json_decode((string) File::get($schemaPath), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Component-library equivalence alias map schema JSON is invalid');
        }

        $normalized = $this->normalizeForFingerprint($decoded);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            throw new RuntimeException('Component-library equivalence alias map schema fingerprint serialization failed');
        }

        return 'sha256:'.hash('sha256', $json);
    }

    public function exportSchemaFingerprint(): string
    {
        $schemaPath = base_path(self::EXPORT_SCHEMA_PATH);
        if (! File::exists($schemaPath) || ! File::isFile($schemaPath)) {
            throw new RuntimeException('Component-library equivalence alias map export schema JSON not found');
        }

        $decoded = json_decode((string) File::get($schemaPath), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Component-library equivalence alias map export schema JSON is invalid');
        }

        $normalized = $this->normalizeForFingerprint($decoded);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            throw new RuntimeException('Component-library equivalence alias map export schema fingerprint serialization failed');
        }

        return 'sha256:'.hash('sha256', $json);
    }

    public function artifactBundleFingerprint(): string
    {
        $summary = $this->summary();
        $payload = [
            'bundle_version' => 'v1',
            'map_version' => (string) ($summary['version'] ?? self::MAP_VERSION),
            'coverage_status' => (string) ($summary['coverage_status'] ?? ''),
            'mapping_count' => (int) ($summary['mapping_count'] ?? 0),
            'map_fingerprint' => $this->fingerprint(),
            'schema_fingerprint' => $this->schemaFingerprint(),
            'export_schema_fingerprint' => $this->exportSchemaFingerprint(),
        ];

        $normalized = $this->normalizeForFingerprint($payload);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            throw new RuntimeException('Component-library equivalence alias artifact bundle fingerprint serialization failed');
        }

        return 'sha256:'.hash('sha256', $json);
    }

    public function exportContentFingerprint(): string
    {
        $payload = [
            'summary' => $this->summary(),
            'stats' => $this->stats(),
            'mappings' => $this->mappings(),
        ];

        $normalized = $this->normalizeForFingerprint($payload);
        $json = json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            throw new RuntimeException('Component-library equivalence alias export content fingerprint serialization failed');
        }

        return 'sha256:'.hash('sha256', $json);
    }

    /**
     * @return array<string, mixed>
     */
    public function fingerprintsDiagnostics(): array
    {
        $summary = $this->summary();

        return [
            'ok' => true,
            'map_version' => (string) ($summary['version'] ?? self::MAP_VERSION),
            'coverage_status' => (string) ($summary['coverage_status'] ?? ''),
            'mapping_count' => (int) ($summary['mapping_count'] ?? 0),
            'map_fingerprint' => $this->fingerprint(),
            'schema_fingerprint' => $this->schemaFingerprint(),
            'export_schema_fingerprint' => $this->exportSchemaFingerprint(),
            'export_content_fingerprint' => $this->exportContentFingerprint(),
            'artifact_bundle_fingerprint' => $this->artifactBundleFingerprint(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function stats(): array
    {
        $mappings = $this->mappings();

        $coverageBreakdown = [
            'exact' => 0,
            'equivalent' => 0,
            'partial' => 0,
            'missing' => 0,
            'unknown' => 0,
        ];

        $distinctCanonicalKeys = [];
        $rowsWithMultipleCanonicalKeys = 0;
        $maxCanonicalKeysPerRow = 0;
        $compositeAliasRows = 0;

        foreach ($mappings as $row) {
            $coverage = (string) ($row['coverage'] ?? '');
            if (! array_key_exists($coverage, $coverageBreakdown)) {
                $coverage = 'unknown';
            }
            $coverageBreakdown[$coverage]++;

            $canonicalKeys = array_values(array_filter(
                array_map('strval', (array) ($row['canonical_builder_keys'] ?? [])),
                static fn (string $value): bool => trim($value) !== ''
            ));

            $count = count($canonicalKeys);
            if ($count > 1) {
                $rowsWithMultipleCanonicalKeys++;
                $compositeAliasRows++;
            }
            if ($count > $maxCanonicalKeysPerRow) {
                $maxCanonicalKeysPerRow = $count;
            }

            foreach ($canonicalKeys as $canonicalKey) {
                $distinctCanonicalKeys[$canonicalKey] = true;
            }
        }

        return [
            'rows' => count($mappings),
            'coverage_breakdown' => $coverageBreakdown,
            'rows_with_multiple_canonical_keys' => $rowsWithMultipleCanonicalKeys,
            'max_canonical_keys_per_row' => $maxCanonicalKeysPerRow,
            'distinct_canonical_builder_key_count' => count($distinctCanonicalKeys),
            'composite_alias_rows' => $compositeAliasRows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function roundTripConsistencyDiagnostics(): array
    {
        $mappings = $this->mappings();

        $seenSourceKeys = [];
        $duplicateSourceKeys = [];
        $rowsWithDuplicateCanonicalKeys = 0;
        $inverseLookupMisses = 0;
        $primaryKeyMismatches = 0;
        $rowsWithoutCanonicalKeys = 0;

        foreach ($mappings as $row) {
            $sourceKey = trim((string) ($row['source_component_key'] ?? ''));
            if ($sourceKey === '') {
                continue;
            }

            if (isset($seenSourceKeys[$sourceKey])) {
                $duplicateSourceKeys[$sourceKey] = true;
            }
            $seenSourceKeys[$sourceKey] = true;

            $canonicalKeys = array_values(array_filter(
                array_map('strval', (array) ($row['canonical_builder_keys'] ?? [])),
                static fn (string $value): bool => trim($value) !== ''
            ));

            if ($canonicalKeys === []) {
                $rowsWithoutCanonicalKeys++;
                continue;
            }

            if (count(array_unique($canonicalKeys)) !== count($canonicalKeys)) {
                $rowsWithDuplicateCanonicalKeys++;
            }

            $primary = $this->resolvePrimaryCanonicalBuilderKey($sourceKey);
            if ($primary !== ($canonicalKeys[0] ?? null)) {
                $primaryKeyMismatches++;
            }

            foreach (array_unique($canonicalKeys) as $canonicalKey) {
                $inverseMatches = $this->findSourceComponentKeysForCanonicalBuilderKey($canonicalKey);
                if (! in_array($sourceKey, $inverseMatches, true)) {
                    $inverseLookupMisses++;
                }
            }
        }

        return [
            'ok' => $duplicateSourceKeys === []
                && $rowsWithDuplicateCanonicalKeys === 0
                && $inverseLookupMisses === 0
                && $primaryKeyMismatches === 0
                && $rowsWithoutCanonicalKeys === 0,
            'rows' => count($mappings),
            'duplicate_source_component_keys_count' => count($duplicateSourceKeys),
            'duplicate_source_component_keys' => array_values(array_keys($duplicateSourceKeys)),
            'rows_with_duplicate_canonical_keys' => $rowsWithDuplicateCanonicalKeys,
            'inverse_lookup_miss_count' => $inverseLookupMisses,
            'primary_key_mismatch_count' => $primaryKeyMismatches,
            'rows_without_canonical_keys' => $rowsWithoutCanonicalKeys,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function strictSchemaDiagnostics(): array
    {
        $summary = $this->summary();
        $mappings = $this->mappings();
        $schemaPath = base_path(self::MAP_SCHEMA_PATH);
        $errors = [];

        if (! File::exists($schemaPath) || ! File::isFile($schemaPath)) {
            return [
                'ok' => false,
                'schema_path' => $schemaPath,
                'schema_fingerprint' => null,
                'error_count' => 1,
                'errors' => ['schema_file_missing'],
            ];
        }

        $decoded = json_decode((string) File::get($schemaPath), true);
        if (! is_array($decoded)) {
            return [
                'ok' => false,
                'schema_path' => $schemaPath,
                'schema_fingerprint' => null,
                'error_count' => 1,
                'errors' => ['schema_json_invalid'],
            ];
        }

        $required = $decoded['required'] ?? null;
        if ($required !== ['version', 'source_spec_ref', 'gap_audit_ref', 'coverage_status', 'mapping_count', 'mappings']) {
            $errors[] = 'schema_required_keys_mismatch';
        }

        if (($decoded['properties']['version']['const'] ?? null) !== self::MAP_VERSION) {
            $errors[] = 'schema_version_const_mismatch';
        }

        if (($decoded['properties']['coverage_status']['enum'][0] ?? null) !== 'complete_exact_plus_equivalent') {
            $errors[] = 'schema_coverage_status_enum_mismatch';
        }

        if (($decoded['properties']['gap_audit_ref']['const'] ?? null) !== 'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md') {
            $errors[] = 'schema_gap_audit_ref_const_mismatch';
        }

        if (($decoded['$defs']['mappingRow']['properties']['source_component_key']['pattern'] ?? null) !== '^[a-z]+\\.[a-zA-Z][\\w]*$') {
            $errors[] = 'schema_source_component_key_pattern_mismatch';
        }

        if (($decoded['$defs']['mappingRow']['properties']['canonical_builder_keys']['items']['pattern'] ?? null) !== '^[a-z0-9_]+$') {
            $errors[] = 'schema_canonical_builder_key_pattern_mismatch';
        }

        if (($summary['version'] ?? null) !== self::MAP_VERSION) {
            $errors[] = 'alias_map_version_mismatch';
        }

        if (($summary['coverage_status'] ?? null) !== 'complete_exact_plus_equivalent') {
            $errors[] = 'alias_map_coverage_status_mismatch';
        }

        $sourcePattern = '/^[a-z]+\.[a-zA-Z][\w]*$/';
        $canonicalPattern = '/^[a-z0-9_]+$/';

        foreach ($mappings as $index => $row) {
            if (! is_array($row)) {
                $errors[] = "mapping_row_{$index}_not_array";
                continue;
            }

            $sourceKey = (string) ($row['source_component_key'] ?? '');
            if ($sourceKey === '' || preg_match($sourcePattern, $sourceKey) !== 1) {
                $errors[] = "mapping_row_{$index}_invalid_source_component_key";
            }

            $coverage = (string) ($row['coverage'] ?? '');
            if (! in_array($coverage, ['exact', 'equivalent', 'partial', 'missing'], true)) {
                $errors[] = "mapping_row_{$index}_invalid_coverage";
            }

            $canonicalKeys = $row['canonical_builder_keys'] ?? null;
            if (! is_array($canonicalKeys) || $canonicalKeys === []) {
                $errors[] = "mapping_row_{$index}_missing_canonical_builder_keys";
                continue;
            }

            foreach ($canonicalKeys as $keyIndex => $canonicalKey) {
                $canonicalKey = (string) $canonicalKey;
                if ($canonicalKey === '' || preg_match($canonicalPattern, $canonicalKey) !== 1) {
                    $errors[] = "mapping_row_{$index}_canonical_key_{$keyIndex}_invalid";
                }
            }
        }

        return [
            'ok' => $errors === [],
            'schema_path' => $schemaPath,
            'schema_fingerprint' => $this->schemaFingerprint(),
            'schema_version_const' => $decoded['properties']['version']['const'] ?? null,
            'coverage_status_const' => $decoded['properties']['coverage_status']['enum'][0] ?? null,
            'validated_rows' => count($mappings),
            'error_count' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exportSchemaDiagnostics(): array
    {
        $schemaPath = base_path(self::EXPORT_SCHEMA_PATH);
        $errors = [];

        if (! File::exists($schemaPath) || ! File::isFile($schemaPath)) {
            return [
                'ok' => false,
                'schema_path' => $schemaPath,
                'schema_fingerprint' => null,
                'error_count' => 1,
                'errors' => ['export_schema_file_missing'],
            ];
        }

        $decoded = json_decode((string) File::get($schemaPath), true);
        if (! is_array($decoded)) {
            return [
                'ok' => false,
                'schema_path' => $schemaPath,
                'schema_fingerprint' => null,
                'error_count' => 1,
                'errors' => ['export_schema_json_invalid'],
            ];
        }

        if (($decoded['required'] ?? null) !== ['summary', 'stats', 'fingerprints', 'mappings']) {
            $errors[] = 'export_schema_required_keys_mismatch';
        }

        if (($decoded['properties']['summary']['properties']['version']['const'] ?? null) !== self::MAP_VERSION) {
            $errors[] = 'export_schema_summary_version_const_mismatch';
        }
        if (($decoded['properties']['summary']['properties']['coverage_status']['const'] ?? null) !== 'complete_exact_plus_equivalent') {
            $errors[] = 'export_schema_summary_coverage_status_const_mismatch';
        }
        if (($decoded['properties']['summary']['properties']['mapping_count']['const'] ?? null) !== 70) {
            $errors[] = 'export_schema_summary_mapping_count_const_mismatch';
        }
        if (($decoded['properties']['stats']['properties']['rows']['const'] ?? null) !== 70) {
            $errors[] = 'export_schema_stats_rows_const_mismatch';
        }
        if (($decoded['properties']['fingerprints']['properties']['ok']['const'] ?? null) !== true) {
            $errors[] = 'export_schema_fingerprints_ok_const_mismatch';
        }
        if (($decoded['properties']['fingerprints']['properties']['export_schema_fingerprint']['pattern'] ?? null) !== '^sha256:[a-f0-9]{64}$') {
            $errors[] = 'export_schema_export_schema_fingerprint_pattern_mismatch';
        }
        if (($decoded['properties']['fingerprints']['properties']['export_content_fingerprint']['pattern'] ?? null) !== '^sha256:[a-f0-9]{64}$') {
            $errors[] = 'export_schema_export_content_fingerprint_pattern_mismatch';
        }
        if (($decoded['properties']['mappings']['minItems'] ?? null) !== 70) {
            $errors[] = 'export_schema_mappings_min_items_mismatch';
        }
        if (($decoded['$defs']['mappingRow']['properties']['source_component_key']['pattern'] ?? null) !== '^[a-z]+\\.[a-zA-Z][\\w]*$') {
            $errors[] = 'export_schema_source_component_key_pattern_mismatch';
        }
        if (($decoded['$defs']['mappingRow']['properties']['canonical_builder_keys']['items']['pattern'] ?? null) !== '^[a-z0-9_]+$') {
            $errors[] = 'export_schema_canonical_builder_key_pattern_mismatch';
        }

        $exportPayload = [
            'summary' => $this->summary(),
            'stats' => $this->stats(),
            'fingerprints' => $this->fingerprintsDiagnostics(),
            'mappings' => $this->mappings(),
        ];

        $summary = is_array($exportPayload['summary'] ?? null) ? (array) $exportPayload['summary'] : [];
        $stats = is_array($exportPayload['stats'] ?? null) ? (array) $exportPayload['stats'] : [];
        $fingerprints = is_array($exportPayload['fingerprints'] ?? null) ? (array) $exportPayload['fingerprints'] : [];
        $mappings = is_array($exportPayload['mappings'] ?? null) ? array_values(array_filter(
            $exportPayload['mappings'],
            static fn ($row): bool => is_array($row)
        )) : [];

        if (($summary['version'] ?? null) !== self::MAP_VERSION) {
            $errors[] = 'export_payload_summary_version_mismatch';
        }
        if (($summary['coverage_status'] ?? null) !== 'complete_exact_plus_equivalent') {
            $errors[] = 'export_payload_summary_coverage_status_mismatch';
        }
        if ((int) ($summary['mapping_count'] ?? 0) !== count($mappings)) {
            $errors[] = 'export_payload_summary_mapping_count_mismatch';
        }
        if ((int) ($stats['rows'] ?? 0) !== count($mappings)) {
            $errors[] = 'export_payload_stats_rows_mismatch';
        }
        if (! (bool) ($fingerprints['ok'] ?? false)) {
            $errors[] = 'export_payload_fingerprints_not_ok';
        }
        foreach (['map_fingerprint', 'schema_fingerprint', 'export_schema_fingerprint', 'export_content_fingerprint', 'artifact_bundle_fingerprint'] as $fingerprintKey) {
            $value = (string) ($fingerprints[$fingerprintKey] ?? '');
            if ($value === '' || preg_match('/^sha256:[a-f0-9]{64}$/', $value) !== 1) {
                $errors[] = 'export_payload_'.$fingerprintKey.'_invalid';
            }
        }

        $sourcePattern = '/^[a-z]+\.[a-zA-Z][\w]*$/';
        $canonicalPattern = '/^[a-z0-9_]+$/';
        foreach ($mappings as $index => $row) {
            $sourceKey = (string) ($row['source_component_key'] ?? '');
            if ($sourceKey === '' || preg_match($sourcePattern, $sourceKey) !== 1) {
                $errors[] = "export_payload_mapping_row_{$index}_invalid_source_component_key";
            }

            $coverage = (string) ($row['coverage'] ?? '');
            if (! in_array($coverage, ['exact', 'equivalent', 'partial', 'missing'], true)) {
                $errors[] = "export_payload_mapping_row_{$index}_invalid_coverage";
            }

            $canonicalKeys = $row['canonical_builder_keys'] ?? null;
            if (! is_array($canonicalKeys) || $canonicalKeys === []) {
                $errors[] = "export_payload_mapping_row_{$index}_missing_canonical_builder_keys";
                continue;
            }

            foreach ($canonicalKeys as $keyIndex => $canonicalKey) {
                $canonicalKey = (string) $canonicalKey;
                if ($canonicalKey === '' || preg_match($canonicalPattern, $canonicalKey) !== 1) {
                    $errors[] = "export_payload_mapping_row_{$index}_canonical_key_{$keyIndex}_invalid";
                }
            }
        }

        return [
            'ok' => $errors === [],
            'schema_path' => $schemaPath,
            'schema_fingerprint' => $this->exportSchemaFingerprint(),
            'schema_summary_version_const' => $decoded['properties']['summary']['properties']['version']['const'] ?? null,
            'schema_summary_coverage_status_const' => $decoded['properties']['summary']['properties']['coverage_status']['const'] ?? null,
            'schema_summary_mapping_count_const' => $decoded['properties']['summary']['properties']['mapping_count']['const'] ?? null,
            'schema_stats_rows_const' => $decoded['properties']['stats']['properties']['rows']['const'] ?? null,
            'schema_mappings_min_items' => $decoded['properties']['mappings']['minItems'] ?? null,
            'validated_rows' => count($mappings),
            'error_count' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function ciBaselinePresetProfile(): array
    {
        $strictExportSchemaAssertions = array_merge(
            ['assert_export_schema_fingerprint' => $this->exportSchemaFingerprint()],
            self::CI_BASELINE_STRICT_EXPORT_SCHEMA_ASSERTIONS
        );

        return [
            'version' => 'v1',
            'summary_assertions' => self::CI_BASELINE_SUMMARY_ASSERTIONS,
            'strict_schema_assertions' => self::CI_BASELINE_STRICT_SCHEMA_ASSERTIONS,
            'strict_export_schema_assertions' => $strictExportSchemaAssertions,
            'stats_assertions' => self::CI_BASELINE_STATS_ASSERTIONS,
            'required_source_keys' => self::CI_BASELINE_REQUIRED_SOURCE_KEYS,
            'required_canonical_keys' => self::CI_BASELINE_REQUIRED_CANONICAL_KEYS,
            'source_primary_assertions' => self::CI_BASELINE_SOURCE_PRIMARY_ASSERTIONS,
            'source_contains_assertions' => self::CI_BASELINE_SOURCE_CONTAINS_ASSERTIONS,
            'source_canonical_set_assertions' => self::CI_BASELINE_SOURCE_CANONICAL_SET_ASSERTIONS,
            'canonical_source_set_assertions' => self::CI_BASELINE_CANONICAL_SOURCE_SET_ASSERTIONS,
        ];
    }

    /**
     * @param  array<string, mixed>  $assertions
     * @return array<string, mixed>
     */
    public function summaryAssertionsDiagnostics(array $assertions): array
    {
        $summary = $this->summary();
        $mappings = $this->mappings();

        $actual = [
            'version' => (string) ($summary['version'] ?? ''),
            'coverage_status' => (string) ($summary['coverage_status'] ?? ''),
            'mapping_count' => (int) ($summary['mapping_count'] ?? 0),
            'row_count' => count($mappings),
            'source_spec_ref' => (string) ($summary['source_spec_ref'] ?? ''),
            'gap_audit_ref' => (string) ($summary['gap_audit_ref'] ?? ''),
            'map_fingerprint' => $this->fingerprint(),
            'artifact_bundle_fingerprint' => $this->artifactBundleFingerprint(),
        ];

        $failed = [];
        if (array_key_exists('assert_map_version', $assertions)
            && $actual['version'] !== (string) $assertions['assert_map_version']) {
            $failed[] = 'assert_map_version';
        }
        if (array_key_exists('assert_coverage_status', $assertions)
            && $actual['coverage_status'] !== (string) $assertions['assert_coverage_status']) {
            $failed[] = 'assert_coverage_status';
        }
        if (array_key_exists('assert_mapping_count', $assertions)
            && $actual['mapping_count'] !== (int) $assertions['assert_mapping_count']) {
            $failed[] = 'assert_mapping_count';
        }
        if (array_key_exists('assert_map_fingerprint', $assertions)
            && $actual['map_fingerprint'] !== (string) $assertions['assert_map_fingerprint']) {
            $failed[] = 'assert_map_fingerprint';
        }
        if (array_key_exists('assert_artifact_bundle_fingerprint', $assertions)
            && $actual['artifact_bundle_fingerprint'] !== (string) $assertions['assert_artifact_bundle_fingerprint']) {
            $failed[] = 'assert_artifact_bundle_fingerprint';
        }
        if (array_key_exists('assert_source_spec_ref_contains', $assertions)
            && ! str_contains($actual['source_spec_ref'], (string) $assertions['assert_source_spec_ref_contains'])) {
            $failed[] = 'assert_source_spec_ref_contains';
        }
        if (array_key_exists('assert_gap_audit_ref_contains', $assertions)
            && ! str_contains($actual['gap_audit_ref'], (string) $assertions['assert_gap_audit_ref_contains'])) {
            $failed[] = 'assert_gap_audit_ref_contains';
        }
        if (array_key_exists('assert_source_spec_ref_regex', $assertions)) {
            $match = @preg_match((string) $assertions['assert_source_spec_ref_regex'], $actual['source_spec_ref']);
            if ($match !== 1) {
                $failed[] = 'assert_source_spec_ref_regex';
            }
        }
        if (array_key_exists('assert_gap_audit_ref_regex', $assertions)) {
            $match = @preg_match((string) $assertions['assert_gap_audit_ref_regex'], $actual['gap_audit_ref']);
            if ($match !== 1) {
                $failed[] = 'assert_gap_audit_ref_regex';
            }
        }

        return [
            'ok' => $failed === [],
            'assertions' => $assertions,
            'actual' => $actual,
            'failed' => $failed,
            'failed_count' => count($failed),
        ];
    }

    /**
     * @param  array<string, mixed>  $assertions
     * @return array<string, mixed>
     */
    public function strictSchemaAssertionsDiagnostics(array $assertions): array
    {
        $strictSchema = $this->strictSchemaDiagnostics();
        $actual = [
            'strict_schema_ok' => (bool) ($strictSchema['ok'] ?? false),
            'schema_fingerprint' => (string) ($strictSchema['schema_fingerprint'] ?? ''),
            'schema_path' => (string) ($strictSchema['schema_path'] ?? ''),
            'schema_version_const' => (string) ($strictSchema['schema_version_const'] ?? ''),
            'schema_coverage_status_const' => (string) ($strictSchema['coverage_status_const'] ?? ''),
            'validated_rows' => (int) ($strictSchema['validated_rows'] ?? 0),
            'error_count' => (int) ($strictSchema['error_count'] ?? 0),
        ];

        $failed = [];
        if (array_key_exists('assert_schema_fingerprint', $assertions)
            && $actual['schema_fingerprint'] !== (string) $assertions['assert_schema_fingerprint']) {
            $failed[] = 'assert_schema_fingerprint';
        }
        if (array_key_exists('assert_schema_path_suffix', $assertions)
            && ! str_ends_with($actual['schema_path'], (string) $assertions['assert_schema_path_suffix'])) {
            $failed[] = 'assert_schema_path_suffix';
        }
        if (array_key_exists('assert_schema_version_const', $assertions)
            && $actual['schema_version_const'] !== (string) $assertions['assert_schema_version_const']) {
            $failed[] = 'assert_schema_version_const';
        }
        if (array_key_exists('assert_schema_coverage_status_const', $assertions)
            && $actual['schema_coverage_status_const'] !== (string) $assertions['assert_schema_coverage_status_const']) {
            $failed[] = 'assert_schema_coverage_status_const';
        }
        if (array_key_exists('assert_schema_validated_rows', $assertions)
            && $actual['validated_rows'] !== (int) $assertions['assert_schema_validated_rows']) {
            $failed[] = 'assert_schema_validated_rows';
        }

        if (! $actual['strict_schema_ok']) {
            $failed[] = 'strict_schema_not_ok';
        }

        return [
            'ok' => $failed === [],
            'assertions' => $assertions,
            'actual' => $actual,
            'strict_schema' => $strictSchema,
            'failed' => array_values(array_unique($failed)),
            'failed_count' => count(array_values(array_unique($failed))),
        ];
    }

    /**
     * @param  array<string, mixed>  $assertions
     * @return array<string, mixed>
     */
    public function strictExportSchemaAssertionsDiagnostics(array $assertions): array
    {
        $exportSchema = $this->exportSchemaDiagnostics();
        $actual = [
            'export_schema_ok' => (bool) ($exportSchema['ok'] ?? false),
            'schema_fingerprint' => (string) ($exportSchema['schema_fingerprint'] ?? ''),
            'schema_path' => (string) ($exportSchema['schema_path'] ?? ''),
            'schema_summary_version_const' => (string) ($exportSchema['schema_summary_version_const'] ?? ''),
            'schema_summary_coverage_status_const' => (string) ($exportSchema['schema_summary_coverage_status_const'] ?? ''),
            'schema_summary_mapping_count_const' => (int) ($exportSchema['schema_summary_mapping_count_const'] ?? 0),
            'schema_stats_rows_const' => (int) ($exportSchema['schema_stats_rows_const'] ?? 0),
            'schema_mappings_min_items' => (int) ($exportSchema['schema_mappings_min_items'] ?? 0),
            'validated_rows' => (int) ($exportSchema['validated_rows'] ?? 0),
            'error_count' => (int) ($exportSchema['error_count'] ?? 0),
        ];

        $failed = [];
        if (array_key_exists('assert_export_schema_fingerprint', $assertions)
            && $actual['schema_fingerprint'] !== (string) $assertions['assert_export_schema_fingerprint']) {
            $failed[] = 'assert_export_schema_fingerprint';
        }
        if (array_key_exists('assert_export_schema_path_suffix', $assertions)
            && ! str_ends_with($actual['schema_path'], (string) $assertions['assert_export_schema_path_suffix'])) {
            $failed[] = 'assert_export_schema_path_suffix';
        }
        if (array_key_exists('assert_export_schema_summary_version_const', $assertions)
            && $actual['schema_summary_version_const'] !== (string) $assertions['assert_export_schema_summary_version_const']) {
            $failed[] = 'assert_export_schema_summary_version_const';
        }
        if (array_key_exists('assert_export_schema_coverage_status_const', $assertions)
            && $actual['schema_summary_coverage_status_const'] !== (string) $assertions['assert_export_schema_coverage_status_const']) {
            $failed[] = 'assert_export_schema_coverage_status_const';
        }
        if (array_key_exists('assert_export_schema_mapping_count_const', $assertions)
            && $actual['schema_summary_mapping_count_const'] !== (int) $assertions['assert_export_schema_mapping_count_const']) {
            $failed[] = 'assert_export_schema_mapping_count_const';
        }
        if (array_key_exists('assert_export_schema_stats_rows_const', $assertions)
            && $actual['schema_stats_rows_const'] !== (int) $assertions['assert_export_schema_stats_rows_const']) {
            $failed[] = 'assert_export_schema_stats_rows_const';
        }
        if (array_key_exists('assert_export_schema_mappings_min_items', $assertions)
            && $actual['schema_mappings_min_items'] !== (int) $assertions['assert_export_schema_mappings_min_items']) {
            $failed[] = 'assert_export_schema_mappings_min_items';
        }
        if (array_key_exists('assert_export_schema_validated_rows', $assertions)
            && $actual['validated_rows'] !== (int) $assertions['assert_export_schema_validated_rows']) {
            $failed[] = 'assert_export_schema_validated_rows';
        }

        if (! $actual['export_schema_ok']) {
            $failed[] = 'export_schema_not_ok';
        }

        $failed = array_values(array_unique($failed));

        return [
            'ok' => $failed === [],
            'assertions' => $assertions,
            'actual' => $actual,
            'export_schema' => $exportSchema,
            'failed' => $failed,
            'failed_count' => count($failed),
        ];
    }

    /**
     * @param  array<int, string>  $requiredSourceKeys
     * @param  array<int, string>  $requiredCanonicalKeys
     * @return array<string, mixed>
     */
    public function requiredAliasAssertionsDiagnostics(array $requiredSourceKeys, array $requiredCanonicalKeys): array
    {
        $requiredSourceKeys = array_values(array_unique(array_map('strval', $requiredSourceKeys)));
        $requiredCanonicalKeys = array_values(array_unique(array_map('strval', $requiredCanonicalKeys)));

        $missingSourceKeys = [];
        foreach ($requiredSourceKeys as $sourceKey) {
            if (! $this->hasSourceComponentKey($sourceKey)) {
                $missingSourceKeys[] = $sourceKey;
            }
        }

        $missingCanonicalKeys = [];
        foreach ($requiredCanonicalKeys as $canonicalKey) {
            if ($this->findSourceComponentKeysForCanonicalBuilderKey($canonicalKey) === []) {
                $missingCanonicalKeys[] = $canonicalKey;
            }
        }

        $failed = [];
        if ($missingSourceKeys !== []) {
            $failed[] = 'assert_source_key';
        }
        if ($missingCanonicalKeys !== []) {
            $failed[] = 'assert_canonical_key';
        }

        return [
            'ok' => $failed === [],
            'required_source_keys' => $requiredSourceKeys,
            'required_source_key_count' => count($requiredSourceKeys),
            'missing_source_keys' => $missingSourceKeys,
            'missing_source_key_count' => count($missingSourceKeys),
            'required_canonical_keys' => $requiredCanonicalKeys,
            'required_canonical_key_count' => count($requiredCanonicalKeys),
            'missing_canonical_keys' => $missingCanonicalKeys,
            'missing_canonical_key_count' => count($missingCanonicalKeys),
            'failed' => $failed,
            'failed_count' => count($failed),
        ];
    }

    /**
     * @param  array<string, int>  $thresholds
     * @return array<string, mixed>
     */
    public function statsThresholdAssertionsDiagnostics(array $thresholds): array
    {
        $stats = $this->stats();
        $coverageBreakdown = is_array($stats['coverage_breakdown'] ?? null)
            ? (array) ($stats['coverage_breakdown'] ?? [])
            : [];

        $actual = [
            'exact' => (int) ($coverageBreakdown['exact'] ?? 0),
            'equivalent' => (int) ($coverageBreakdown['equivalent'] ?? 0),
            'partial' => (int) ($coverageBreakdown['partial'] ?? 0),
            'missing' => (int) ($coverageBreakdown['missing'] ?? 0),
            'unknown' => (int) ($coverageBreakdown['unknown'] ?? 0),
            'rows_with_multiple_canonical_keys' => (int) ($stats['rows_with_multiple_canonical_keys'] ?? 0),
            'max_canonical_keys_per_row' => (int) ($stats['max_canonical_keys_per_row'] ?? 0),
            'distinct_canonical_builder_key_count' => (int) ($stats['distinct_canonical_builder_key_count'] ?? 0),
            'composite_alias_rows' => (int) ($stats['composite_alias_rows'] ?? 0),
        ];
        $actual['covered_rows'] = $actual['exact'] + $actual['equivalent'];

        $failed = [];
        foreach ($thresholds as $key => $threshold) {
            if ($key === 'assert_max_partial' && $actual['partial'] > $threshold) {
                $failed[] = $key;
            }
            if ($key === 'assert_max_missing' && $actual['missing'] > $threshold) {
                $failed[] = $key;
            }
            if ($key === 'assert_max_unknown' && $actual['unknown'] > $threshold) {
                $failed[] = $key;
            }
            if ($key === 'assert_min_covered' && $actual['covered_rows'] < $threshold) {
                $failed[] = $key;
            }
            if ($key === 'assert_max_composite_alias_rows' && $actual['composite_alias_rows'] > $threshold) {
                $failed[] = $key;
            }
            if ($key === 'assert_max_canonical_keys_per_row' && $actual['max_canonical_keys_per_row'] > $threshold) {
                $failed[] = $key;
            }
            if ($key === 'assert_min_distinct_canonical_keys' && $actual['distinct_canonical_builder_key_count'] < $threshold) {
                $failed[] = $key;
            }
        }

        return [
            'ok' => $failed === [],
            'thresholds' => $thresholds,
            'actual' => $actual,
            'failed' => $failed,
            'failed_count' => count($failed),
        ];
    }

    /**
     * @param  array<int, string>  $pairs
     * @return array<string, mixed>
     */
    public function sourcePrimaryAssertionsDiagnostics(array $pairs): array
    {
        $pairs = array_values(array_unique(array_map('strval', $pairs)));

        $evaluated = [];
        $invalidPairs = [];
        $failed = [];

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            if (! str_contains($pair, '=')) {
                $invalidPairs[] = $pair;
                continue;
            }

            [$sourceKey, $expectedPrimary] = array_pad(explode('=', $pair, 2), 2, '');
            $sourceKey = trim($sourceKey);
            $expectedPrimary = trim($expectedPrimary);

            if ($sourceKey === '' || $expectedPrimary === '') {
                $invalidPairs[] = $pair;
                continue;
            }

            $actualPrimary = $this->resolvePrimaryCanonicalBuilderKey($sourceKey);
            $ok = $actualPrimary !== null && $actualPrimary === $expectedPrimary;
            if (! $ok) {
                $failed[] = $pair;
            }

            $evaluated[] = [
                'pair' => $pair,
                'source_component_key' => $sourceKey,
                'expected_primary_canonical_builder_key' => $expectedPrimary,
                'actual_primary_canonical_builder_key' => $actualPrimary,
                'ok' => $ok,
            ];
        }

        if ($invalidPairs !== []) {
            $failed = array_values(array_unique(array_merge($failed, $invalidPairs)));
        }

        return [
            'ok' => $failed === [] && $invalidPairs === [],
            'required_pair_count' => count($pairs),
            'evaluated_count' => count($evaluated),
            'invalid_pair_count' => count($invalidPairs),
            'invalid_pairs' => $invalidPairs,
            'evaluated' => $evaluated,
            'failed' => $failed,
            'failed_count' => count($failed),
        ];
    }

    /**
     * @param  array<int, string>  $pairs
     * @return array<string, mixed>
     */
    public function sourceContainsCanonicalAssertionsDiagnostics(array $pairs): array
    {
        $pairs = array_values(array_unique(array_map('strval', $pairs)));

        $evaluated = [];
        $invalidPairs = [];
        $failed = [];

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            if (! str_contains($pair, '=')) {
                $invalidPairs[] = $pair;
                continue;
            }

            [$sourceKey, $expectedCanonical] = array_pad(explode('=', $pair, 2), 2, '');
            $sourceKey = trim($sourceKey);
            $expectedCanonical = trim($expectedCanonical);

            if ($sourceKey === '' || $expectedCanonical === '') {
                $invalidPairs[] = $pair;
                continue;
            }

            $resolved = $this->resolveCanonicalBuilderKeys($sourceKey);
            $ok = in_array($expectedCanonical, $resolved, true);
            if (! $ok) {
                $failed[] = $pair;
            }

            $evaluated[] = [
                'pair' => $pair,
                'source_component_key' => $sourceKey,
                'expected_canonical_builder_key' => $expectedCanonical,
                'resolved_canonical_builder_keys' => $resolved,
                'ok' => $ok,
            ];
        }

        if ($invalidPairs !== []) {
            $failed = array_values(array_unique(array_merge($failed, $invalidPairs)));
        }

        return [
            'ok' => $failed === [] && $invalidPairs === [],
            'required_pair_count' => count($pairs),
            'evaluated_count' => count($evaluated),
            'invalid_pair_count' => count($invalidPairs),
            'invalid_pairs' => $invalidPairs,
            'evaluated' => $evaluated,
            'failed' => $failed,
            'failed_count' => count($failed),
        ];
    }

    /**
     * @param  array<int, string>  $pairs
     * @return array<string, mixed>
     */
    public function sourceCanonicalSetAssertionsDiagnostics(array $pairs): array
    {
        $pairs = array_values(array_unique(array_map('strval', $pairs)));

        $evaluated = [];
        $invalidPairs = [];
        $failed = [];

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            if (! str_contains($pair, '=')) {
                $invalidPairs[] = $pair;
                continue;
            }

            [$sourceKey, $expectedSetRaw] = array_pad(explode('=', $pair, 2), 2, '');
            $sourceKey = trim($sourceKey);
            $expectedSetRaw = trim($expectedSetRaw);

            if ($sourceKey === '' || $expectedSetRaw === '') {
                $invalidPairs[] = $pair;
                continue;
            }

            $expectedCanonicalKeys = array_values(array_filter(
                array_map('trim', explode('|', $expectedSetRaw)),
                static fn (string $value): bool => $value !== ''
            ));
            $expectedCanonicalKeys = array_values(array_unique($expectedCanonicalKeys));

            if ($expectedCanonicalKeys === []) {
                $invalidPairs[] = $pair;
                continue;
            }

            $resolved = $this->resolveCanonicalBuilderKeys($sourceKey);
            $resolvedUnique = array_values(array_unique($resolved));

            $expectedSorted = $expectedCanonicalKeys;
            sort($expectedSorted);
            $resolvedSorted = $resolvedUnique;
            sort($resolvedSorted);

            $missingExpected = array_values(array_diff($expectedSorted, $resolvedSorted));
            $unexpectedResolved = array_values(array_diff($resolvedSorted, $expectedSorted));
            $ok = $missingExpected === [] && $unexpectedResolved === [];
            if (! $ok) {
                $failed[] = $pair;
            }

            $evaluated[] = [
                'pair' => $pair,
                'source_component_key' => $sourceKey,
                'expected_canonical_builder_keys' => $expectedCanonicalKeys,
                'resolved_canonical_builder_keys' => $resolved,
                'resolved_canonical_builder_keys_unique' => $resolvedUnique,
                'missing_expected_canonical_builder_keys' => $missingExpected,
                'unexpected_resolved_canonical_builder_keys' => $unexpectedResolved,
                'ok' => $ok,
            ];
        }

        if ($invalidPairs !== []) {
            $failed = array_values(array_unique(array_merge($failed, $invalidPairs)));
        }

        return [
            'ok' => $failed === [] && $invalidPairs === [],
            'required_pair_count' => count($pairs),
            'evaluated_count' => count($evaluated),
            'invalid_pair_count' => count($invalidPairs),
            'invalid_pairs' => $invalidPairs,
            'evaluated' => $evaluated,
            'failed' => $failed,
            'failed_count' => count($failed),
        ];
    }

    /**
     * @param  array<int, string>  $pairs
     * @return array<string, mixed>
     */
    public function canonicalSourceSetAssertionsDiagnostics(array $pairs): array
    {
        $pairs = array_values(array_unique(array_map('strval', $pairs)));

        $evaluated = [];
        $invalidPairs = [];
        $failed = [];

        foreach ($pairs as $pair) {
            $pair = trim($pair);
            if ($pair === '') {
                continue;
            }

            if (! str_contains($pair, '=')) {
                $invalidPairs[] = $pair;
                continue;
            }

            [$canonicalKey, $expectedSetRaw] = array_pad(explode('=', $pair, 2), 2, '');
            $canonicalKey = trim($canonicalKey);
            $expectedSetRaw = trim($expectedSetRaw);

            if ($canonicalKey === '' || $expectedSetRaw === '') {
                $invalidPairs[] = $pair;
                continue;
            }

            $expectedSourceKeys = array_values(array_filter(
                array_map('trim', explode('|', $expectedSetRaw)),
                static fn (string $value): bool => $value !== ''
            ));
            $expectedSourceKeys = array_values(array_unique($expectedSourceKeys));

            if ($expectedSourceKeys === []) {
                $invalidPairs[] = $pair;
                continue;
            }

            $resolved = $this->findSourceComponentKeysForCanonicalBuilderKey($canonicalKey);
            $resolvedUnique = array_values(array_unique($resolved));

            $expectedSorted = $expectedSourceKeys;
            sort($expectedSorted);
            $resolvedSorted = $resolvedUnique;
            sort($resolvedSorted);

            $missingExpected = array_values(array_diff($expectedSorted, $resolvedSorted));
            $unexpectedResolved = array_values(array_diff($resolvedSorted, $expectedSorted));
            $ok = $missingExpected === [] && $unexpectedResolved === [];
            if (! $ok) {
                $failed[] = $pair;
            }

            $evaluated[] = [
                'pair' => $pair,
                'canonical_builder_key' => $canonicalKey,
                'expected_source_component_keys' => $expectedSourceKeys,
                'resolved_source_component_keys' => $resolved,
                'resolved_source_component_keys_unique' => $resolvedUnique,
                'missing_expected_source_component_keys' => $missingExpected,
                'unexpected_resolved_source_component_keys' => $unexpectedResolved,
                'ok' => $ok,
            ];
        }

        if ($invalidPairs !== []) {
            $failed = array_values(array_unique(array_merge($failed, $invalidPairs)));
        }

        return [
            'ok' => $failed === [] && $invalidPairs === [],
            'required_pair_count' => count($pairs),
            'evaluated_count' => count($evaluated),
            'invalid_pair_count' => count($invalidPairs),
            'invalid_pairs' => $invalidPairs,
            'evaluated' => $evaluated,
            'failed' => $failed,
            'failed_count' => count($failed),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function resolveCanonicalBuilderKeys(string $sourceComponentKey): array
    {
        $sourceComponentKey = trim($sourceComponentKey);
        if ($sourceComponentKey === '') {
            return [];
        }

        foreach ($this->mappings() as $row) {
            if (($row['source_component_key'] ?? null) !== $sourceComponentKey) {
                continue;
            }

            $keys = $row['canonical_builder_keys'] ?? [];
            if (! is_array($keys)) {
                return [];
            }

            $normalized = [];
            foreach ($keys as $key) {
                if (! is_string($key)) {
                    continue;
                }
                $key = trim($key);
                if ($key === '') {
                    continue;
                }
                $normalized[] = $key;
            }

            return array_values(array_unique($normalized));
        }

        return [];
    }

    public function resolvePrimaryCanonicalBuilderKey(string $sourceComponentKey): ?string
    {
        $keys = $this->resolveCanonicalBuilderKeys($sourceComponentKey);

        return $keys[0] ?? null;
    }

    /**
     * @return array<int, string>
     */
    public function findSourceComponentKeysForCanonicalBuilderKey(string $canonicalBuilderKey): array
    {
        $canonicalBuilderKey = trim($canonicalBuilderKey);
        if ($canonicalBuilderKey === '') {
            return [];
        }

        $matches = [];
        foreach ($this->mappings() as $row) {
            $sourceKey = $row['source_component_key'] ?? null;
            $keys = $row['canonical_builder_keys'] ?? [];

            if (! is_string($sourceKey) || ! is_array($keys)) {
                continue;
            }

            foreach ($keys as $key) {
                if (is_string($key) && trim($key) === $canonicalBuilderKey) {
                    $matches[] = $sourceKey;
                    break;
                }
            }
        }

        return array_values(array_unique($matches));
    }

    /**
     * @return array<string, mixed>
     */
    private function loadMap(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $path = base_path(self::MAP_PATH);
        if (! File::exists($path)) {
            throw new RuntimeException('Component-library equivalence alias map JSON not found: '.self::MAP_PATH);
        }

        $decoded = json_decode(File::get($path), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Component-library equivalence alias map JSON is invalid');
        }

        $version = $decoded['version'] ?? null;
        if (! is_string($version) || trim($version) !== self::MAP_VERSION) {
            throw new RuntimeException('Unsupported component-library equivalence alias map version');
        }

        $mappings = $decoded['mappings'] ?? null;
        if (! is_array($mappings)) {
            throw new RuntimeException('Component-library equivalence alias map missing mappings[]');
        }

        $mappingCount = (int) ($decoded['mapping_count'] ?? -1);
        if ($mappingCount !== count($mappings)) {
            throw new RuntimeException('Component-library equivalence alias map mapping_count mismatch');
        }

        return $this->cache = $decoded;
    }

    /**
     * @return mixed
     */
    private function normalizeForFingerprint(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map(fn ($item) => $this->normalizeForFingerprint($item), $value);
        }

        ksort($value);

        $normalized = [];
        foreach ($value as $key => $item) {
            $normalized[$key] = $this->normalizeForFingerprint($item);
        }

        return $normalized;
    }
}
