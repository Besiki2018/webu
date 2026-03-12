<?php

namespace Tests\Unit;

use App\Services\CmsComponentLibrarySpecEquivalenceAliasMapService;
use Tests\TestCase;

/** @group docs-sync */
class CmsComponentLibrarySpecEquivalenceAliasMapServiceTest extends TestCase
{
    public function test_it_loads_component_library_spec_equivalence_alias_map_summary_and_count(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $summary = $service->summary();
        $mappings = $service->mappings();

        $this->assertSame('v1', $summary['version']);
        $this->assertSame('complete_exact_plus_equivalent', $summary['coverage_status']);
        $this->assertSame(70, $summary['mapping_count']);
        $this->assertCount(70, $mappings);
        $this->assertSame('layout.section', $mappings[0]['source_component_key']);
        $this->assertSame('misc.statsCounter', $mappings[69]['source_component_key']);
    }

    public function test_it_resolves_single_and_composite_source_component_keys_to_canonical_builder_keys(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $this->assertSame(
            ['webu_general_nav_logo_01'],
            $service->resolveCanonicalBuilderKeys('nav.logo')
        );
        $this->assertSame(
            'webu_general_nav_logo_01',
            $service->resolvePrimaryCanonicalBuilderKey('nav.logo')
        );

        $this->assertSame(
            [
                'webu_ecom_product_detail_01',
                'webu_ecom_add_to_cart_button_01',
                'webu_ecom_product_tabs_01',
            ],
            $service->resolveCanonicalBuilderKeys('ecom.productDetail')
        );
        $this->assertSame(
            'webu_ecom_product_detail_01',
            $service->resolvePrimaryCanonicalBuilderKey('ecom.productDetail')
        );
    }

    public function test_it_supports_inverse_lookup_from_canonical_builder_key_to_source_spec_component_keys(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $this->assertSame(
            ['nav.cartIcon'],
            $service->findSourceComponentKeysForCanonicalBuilderKey('webu_ecom_cart_icon_01')
        );

        $this->assertSame(
            ['ecom.productDetail'],
            $service->findSourceComponentKeysForCanonicalBuilderKey('webu_ecom_add_to_cart_button_01')
        );

        $this->assertSame(
            ['auth.security'],
            $service->findSourceComponentKeysForCanonicalBuilderKey('webu_ecom_account_security_01')
        );
    }

    public function test_it_provides_alias_map_stats_snapshot_for_tooling_and_ci_hardening(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $stats = $service->stats();

        $this->assertSame(70, $stats['rows']);
        $this->assertSame(
            [
                'exact' => 0,
                'equivalent' => 70,
                'partial' => 0,
                'missing' => 0,
                'unknown' => 0,
            ],
            $stats['coverage_breakdown']
        );
        $this->assertIsInt($stats['rows_with_multiple_canonical_keys']);
        $this->assertIsInt($stats['max_canonical_keys_per_row']);
        $this->assertIsInt($stats['distinct_canonical_builder_key_count']);
        $this->assertIsInt($stats['composite_alias_rows']);

        $this->assertGreaterThanOrEqual(1, $stats['rows_with_multiple_canonical_keys']);
        $this->assertGreaterThanOrEqual(2, $stats['max_canonical_keys_per_row']);
        $this->assertGreaterThanOrEqual($stats['rows_with_multiple_canonical_keys'], $stats['composite_alias_rows']);
        $this->assertGreaterThan(0, $stats['distinct_canonical_builder_key_count']);
    }

    public function test_it_provides_deterministic_alias_map_fingerprint_snapshot(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $fingerprintA = $service->fingerprint();
        $fingerprintB = $service->fingerprint();

        $this->assertSame($fingerprintA, $fingerprintB);
        $this->assertMatchesRegularExpression('/^sha256:[a-f0-9]{64}$/', $fingerprintA);
    }

    public function test_it_provides_round_trip_consistency_diagnostics_snapshot(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $diagnostics = $service->roundTripConsistencyDiagnostics();

        $this->assertTrue($diagnostics['ok']);
        $this->assertSame(70, $diagnostics['rows']);
        $this->assertSame(0, $diagnostics['duplicate_source_component_keys_count']);
        $this->assertSame([], $diagnostics['duplicate_source_component_keys']);
        $this->assertSame(0, $diagnostics['rows_with_duplicate_canonical_keys']);
        $this->assertSame(0, $diagnostics['inverse_lookup_miss_count']);
        $this->assertSame(0, $diagnostics['primary_key_mismatch_count']);
        $this->assertSame(0, $diagnostics['rows_without_canonical_keys']);
    }

    public function test_it_provides_strict_schema_diagnostics_snapshot_for_reusable_cli_and_ci_checks(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $diagnostics = $service->strictSchemaDiagnostics();

        $this->assertTrue($diagnostics['ok']);
        $this->assertStringContainsString(
            'cms-component-library-spec-equivalence-alias-map.v1.schema.json',
            (string) $diagnostics['schema_path']
        );
        $this->assertSame('v1', $diagnostics['schema_version_const']);
        $this->assertSame('complete_exact_plus_equivalent', $diagnostics['coverage_status_const']);
        $this->assertSame(70, $diagnostics['validated_rows']);
        $this->assertSame(0, $diagnostics['error_count']);
        $this->assertSame([], $diagnostics['errors']);
        $this->assertMatchesRegularExpression('/^sha256:[a-f0-9]{64}$/', (string) $diagnostics['schema_fingerprint']);
    }

    public function test_it_provides_deterministic_alias_map_schema_fingerprint_snapshot(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $fingerprintA = $service->schemaFingerprint();
        $fingerprintB = $service->schemaFingerprint();

        $this->assertSame($fingerprintA, $fingerprintB);
        $this->assertMatchesRegularExpression('/^sha256:[a-f0-9]{64}$/', $fingerprintA);
    }

    public function test_it_provides_export_schema_diagnostics_snapshot_for_reusable_cli_and_ci_checks(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $diagnostics = $service->exportSchemaDiagnostics();

        $this->assertTrue($diagnostics['ok']);
        $this->assertStringContainsString(
            'cms-component-library-spec-equivalence-alias-map-export.v1.schema.json',
            (string) $diagnostics['schema_path']
        );
        $this->assertSame('v1', $diagnostics['schema_summary_version_const']);
        $this->assertSame('complete_exact_plus_equivalent', $diagnostics['schema_summary_coverage_status_const']);
        $this->assertSame(70, $diagnostics['schema_summary_mapping_count_const']);
        $this->assertSame(70, $diagnostics['schema_stats_rows_const']);
        $this->assertSame(70, $diagnostics['schema_mappings_min_items']);
        $this->assertSame(70, $diagnostics['validated_rows']);
        $this->assertSame(0, $diagnostics['error_count']);
        $this->assertSame([], $diagnostics['errors']);
        $this->assertMatchesRegularExpression('/^sha256:[a-f0-9]{64}$/', (string) $diagnostics['schema_fingerprint']);
    }

    public function test_it_provides_fingerprints_diagnostics_snapshot(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $diagnostics = $service->fingerprintsDiagnostics();

        $this->assertTrue($diagnostics['ok']);
        $this->assertSame('v1', $diagnostics['map_version']);
        $this->assertSame('complete_exact_plus_equivalent', $diagnostics['coverage_status']);
        $this->assertSame(70, $diagnostics['mapping_count']);
        $this->assertSame($service->fingerprint(), $diagnostics['map_fingerprint']);
        $this->assertSame($service->schemaFingerprint(), $diagnostics['schema_fingerprint']);
        $this->assertSame($service->exportSchemaFingerprint(), $diagnostics['export_schema_fingerprint']);
        $this->assertSame($service->exportContentFingerprint(), $diagnostics['export_content_fingerprint']);
        $this->assertSame($service->artifactBundleFingerprint(), $diagnostics['artifact_bundle_fingerprint']);
        $this->assertMatchesRegularExpression('/^sha256:[a-f0-9]{64}$/', $service->exportSchemaFingerprint());
        $this->assertMatchesRegularExpression('/^sha256:[a-f0-9]{64}$/', $service->exportContentFingerprint());
    }

    public function test_it_provides_ci_baseline_preset_profile_snapshot(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $profile = $service->ciBaselinePresetProfile();

        $this->assertSame('v1', $profile['version']);
        $this->assertSame(
            [
                'assert_map_version' => 'v1',
                'assert_coverage_status' => 'complete_exact_plus_equivalent',
                'assert_mapping_count' => 70,
                'assert_map_fingerprint' => $service->fingerprint(),
                'assert_artifact_bundle_fingerprint' => $service->artifactBundleFingerprint(),
                'assert_source_spec_ref_contains' => 'PROJECT_ROADMAP_TASKS_KA.md:6439',
                'assert_gap_audit_ref_contains' => 'UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md',
                'assert_source_spec_ref_regex' => '/PROJECT_ROADMAP_TASKS_KA\\.md:6439-\\d+/',
                'assert_gap_audit_ref_regex' => '/UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE\\.md$/',
            ],
            $profile['summary_assertions']
        );
        $this->assertSame(
            [
                'assert_schema_fingerprint' => $service->schemaFingerprint(),
                'assert_schema_path_suffix' => 'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map.v1.schema.json',
                'assert_schema_version_const' => 'v1',
                'assert_schema_coverage_status_const' => 'complete_exact_plus_equivalent',
                'assert_schema_validated_rows' => 70,
            ],
            $profile['strict_schema_assertions']
        );
        $this->assertSame(
            [
                'assert_export_schema_fingerprint' => $service->exportSchemaFingerprint(),
                'assert_export_schema_path_suffix' => 'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map-export.v1.schema.json',
                'assert_export_schema_summary_version_const' => 'v1',
                'assert_export_schema_coverage_status_const' => 'complete_exact_plus_equivalent',
                'assert_export_schema_mapping_count_const' => 70,
                'assert_export_schema_stats_rows_const' => 70,
                'assert_export_schema_mappings_min_items' => 70,
                'assert_export_schema_validated_rows' => 70,
            ],
            $profile['strict_export_schema_assertions']
        );
        $this->assertSame(
            [
                'assert_max_partial' => 0,
                'assert_max_missing' => 0,
                'assert_max_unknown' => 0,
                'assert_min_covered' => 70,
                'assert_max_composite_alias_rows' => 3,
                'assert_max_canonical_keys_per_row' => 3,
                'assert_min_distinct_canonical_keys' => 74,
            ],
            $profile['stats_assertions']
        );
        $this->assertSame(['auth.security', 'ecom.productDetail'], $profile['required_source_keys']);
        $this->assertSame(
            ['webu_ecom_account_security_01', 'webu_ecom_product_detail_01'],
            $profile['required_canonical_keys']
        );
        $this->assertSame(
            ['auth.security=webu_ecom_account_security_01', 'ecom.productDetail=webu_ecom_product_detail_01'],
            $profile['source_primary_assertions']
        );
        $this->assertSame(
            ['ecom.productDetail=webu_ecom_add_to_cart_button_01', 'ecom.productDetail=webu_ecom_product_tabs_01'],
            $profile['source_contains_assertions']
        );
        $this->assertSame(
            ['ecom.productDetail=webu_ecom_product_detail_01|webu_ecom_add_to_cart_button_01|webu_ecom_product_tabs_01'],
            $profile['source_canonical_set_assertions']
        );
        $this->assertSame(
            ['webu_ecom_account_security_01=auth.security', 'webu_ecom_product_detail_01=ecom.productDetail'],
            $profile['canonical_source_set_assertions']
        );
    }

    public function test_it_provides_summary_assertions_diagnostics_snapshot(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $diagnostics = $service->summaryAssertionsDiagnostics([
            'assert_map_version' => 'v1',
            'assert_coverage_status' => 'complete_exact_plus_equivalent',
            'assert_mapping_count' => 70,
            'assert_map_fingerprint' => $service->fingerprint(),
            'assert_artifact_bundle_fingerprint' => $service->artifactBundleFingerprint(),
            'assert_source_spec_ref_contains' => 'PROJECT_ROADMAP_TASKS_KA.md:6439',
            'assert_gap_audit_ref_contains' => 'UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md',
            'assert_source_spec_ref_regex' => '/PROJECT_ROADMAP_TASKS_KA\\.md:6439-\\d+/',
            'assert_gap_audit_ref_regex' => '/UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE\\.md$/',
        ]);

        $this->assertTrue($diagnostics['ok']);
        $this->assertSame([], $diagnostics['failed']);
        $this->assertSame(0, $diagnostics['failed_count']);
        $this->assertSame('v1', $diagnostics['actual']['version']);
        $this->assertSame('complete_exact_plus_equivalent', $diagnostics['actual']['coverage_status']);
        $this->assertSame(70, $diagnostics['actual']['mapping_count']);
        $this->assertSame(70, $diagnostics['actual']['row_count']);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:6439', $diagnostics['actual']['source_spec_ref']);
        $this->assertStringContainsString(
            'UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md',
            $diagnostics['actual']['gap_audit_ref']
        );
        $this->assertSame($service->fingerprint(), $diagnostics['actual']['map_fingerprint']);
        $this->assertSame($service->artifactBundleFingerprint(), $diagnostics['actual']['artifact_bundle_fingerprint']);
    }

    public function test_it_fails_summary_regex_assertions_when_patterns_do_not_match(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $diagnostics = $service->summaryAssertionsDiagnostics([
            'assert_source_spec_ref_regex' => '/NO_MATCH_SOURCE_REF/',
            'assert_gap_audit_ref_regex' => '/NO_MATCH_GAP_AUDIT_REF/',
        ]);

        $this->assertFalse($diagnostics['ok']);
        $this->assertSame(
            ['assert_source_spec_ref_regex', 'assert_gap_audit_ref_regex'],
            $diagnostics['failed']
        );
        $this->assertSame(2, $diagnostics['failed_count']);
    }

    public function test_it_provides_strict_schema_assertions_diagnostics_snapshot(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $diagnostics = $service->strictSchemaAssertionsDiagnostics([
            'assert_schema_fingerprint' => $service->schemaFingerprint(),
            'assert_schema_path_suffix' => 'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map.v1.schema.json',
            'assert_schema_version_const' => 'v1',
            'assert_schema_coverage_status_const' => 'complete_exact_plus_equivalent',
            'assert_schema_validated_rows' => 70,
        ]);

        $this->assertTrue($diagnostics['ok']);
        $this->assertSame([], $diagnostics['failed']);
        $this->assertSame(0, $diagnostics['failed_count']);
        $this->assertTrue((bool) ($diagnostics['actual']['strict_schema_ok'] ?? false));
        $this->assertStringEndsWith(
            'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map.v1.schema.json',
            (string) $diagnostics['actual']['schema_path']
        );
        $this->assertSame($service->schemaFingerprint(), $diagnostics['actual']['schema_fingerprint']);
        $this->assertSame('v1', $diagnostics['actual']['schema_version_const']);
        $this->assertSame('complete_exact_plus_equivalent', $diagnostics['actual']['schema_coverage_status_const']);
        $this->assertSame(70, $diagnostics['actual']['validated_rows']);
        $this->assertSame(0, (int) ($diagnostics['actual']['error_count'] ?? 0));
    }

    public function test_it_provides_strict_export_schema_assertions_diagnostics_snapshot(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $diagnostics = $service->strictExportSchemaAssertionsDiagnostics([
            'assert_export_schema_fingerprint' => $service->exportSchemaFingerprint(),
            'assert_export_schema_path_suffix' => 'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map-export.v1.schema.json',
            'assert_export_schema_summary_version_const' => 'v1',
            'assert_export_schema_coverage_status_const' => 'complete_exact_plus_equivalent',
            'assert_export_schema_mapping_count_const' => 70,
            'assert_export_schema_stats_rows_const' => 70,
            'assert_export_schema_mappings_min_items' => 70,
            'assert_export_schema_validated_rows' => 70,
        ]);

        $this->assertTrue($diagnostics['ok']);
        $this->assertSame([], $diagnostics['failed']);
        $this->assertSame(0, $diagnostics['failed_count']);
        $this->assertTrue((bool) ($diagnostics['actual']['export_schema_ok'] ?? false));
        $this->assertStringEndsWith(
            'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map-export.v1.schema.json',
            (string) $diagnostics['actual']['schema_path']
        );
        $this->assertSame($service->exportSchemaFingerprint(), $diagnostics['actual']['schema_fingerprint']);
        $this->assertSame('v1', $diagnostics['actual']['schema_summary_version_const']);
        $this->assertSame('complete_exact_plus_equivalent', $diagnostics['actual']['schema_summary_coverage_status_const']);
        $this->assertSame(70, $diagnostics['actual']['schema_summary_mapping_count_const']);
        $this->assertSame(70, $diagnostics['actual']['schema_stats_rows_const']);
        $this->assertSame(70, $diagnostics['actual']['schema_mappings_min_items']);
        $this->assertSame(70, $diagnostics['actual']['validated_rows']);
        $this->assertSame(0, (int) ($diagnostics['actual']['error_count'] ?? 0));
    }

    public function test_it_provides_required_alias_assertions_diagnostics_snapshot(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $diagnostics = $service->requiredAliasAssertionsDiagnostics(
            ['auth.security', 'ecom.productDetail'],
            ['webu_ecom_account_security_01', 'webu_ecom_product_detail_01']
        );

        $this->assertTrue($diagnostics['ok']);
        $this->assertSame(2, $diagnostics['required_source_key_count']);
        $this->assertSame(0, $diagnostics['missing_source_key_count']);
        $this->assertSame(2, $diagnostics['required_canonical_key_count']);
        $this->assertSame(0, $diagnostics['missing_canonical_key_count']);
        $this->assertSame([], $diagnostics['failed']);
        $this->assertSame(0, $diagnostics['failed_count']);
    }

    public function test_it_provides_stats_threshold_assertions_diagnostics_snapshot(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $diagnostics = $service->statsThresholdAssertionsDiagnostics([
            'assert_max_partial' => 0,
            'assert_max_missing' => 0,
            'assert_max_unknown' => 0,
            'assert_min_covered' => 70,
        ]);

        $this->assertTrue($diagnostics['ok']);
        $this->assertSame([], $diagnostics['failed']);
        $this->assertSame(0, $diagnostics['failed_count']);
        $this->assertSame(70, $diagnostics['actual']['covered_rows']);
        $this->assertSame(0, $diagnostics['actual']['partial']);
        $this->assertSame(0, $diagnostics['actual']['missing']);
        $this->assertSame(0, $diagnostics['actual']['unknown']);
        $this->assertSame(3, $diagnostics['actual']['composite_alias_rows']);
        $this->assertSame(3, $diagnostics['actual']['max_canonical_keys_per_row']);
        $this->assertSame(74, $diagnostics['actual']['distinct_canonical_builder_key_count']);
    }

    public function test_it_provides_source_primary_assertions_diagnostics_snapshot(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $diagnostics = $service->sourcePrimaryAssertionsDiagnostics([
            'auth.security=webu_ecom_account_security_01',
            'ecom.productDetail=webu_ecom_product_detail_01',
        ]);

        $this->assertTrue($diagnostics['ok']);
        $this->assertSame(2, $diagnostics['required_pair_count']);
        $this->assertSame(2, $diagnostics['evaluated_count']);
        $this->assertSame(0, $diagnostics['invalid_pair_count']);
        $this->assertSame([], $diagnostics['invalid_pairs']);
        $this->assertSame([], $diagnostics['failed']);
        $this->assertSame(0, $diagnostics['failed_count']);
    }

    public function test_it_provides_source_contains_canonical_assertions_diagnostics_snapshot(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $diagnostics = $service->sourceContainsCanonicalAssertionsDiagnostics([
            'ecom.productDetail=webu_ecom_add_to_cart_button_01',
            'ecom.productDetail=webu_ecom_product_tabs_01',
        ]);

        $this->assertTrue($diagnostics['ok']);
        $this->assertSame(2, $diagnostics['required_pair_count']);
        $this->assertSame(2, $diagnostics['evaluated_count']);
        $this->assertSame(0, $diagnostics['invalid_pair_count']);
        $this->assertSame([], $diagnostics['invalid_pairs']);
        $this->assertSame([], $diagnostics['failed']);
        $this->assertSame(0, $diagnostics['failed_count']);
    }

    public function test_it_provides_source_canonical_set_assertions_diagnostics_snapshot(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $diagnostics = $service->sourceCanonicalSetAssertionsDiagnostics([
            'ecom.productDetail=webu_ecom_product_detail_01|webu_ecom_add_to_cart_button_01|webu_ecom_product_tabs_01',
        ]);

        $this->assertTrue($diagnostics['ok']);
        $this->assertSame(1, $diagnostics['required_pair_count']);
        $this->assertSame(1, $diagnostics['evaluated_count']);
        $this->assertSame(0, $diagnostics['invalid_pair_count']);
        $this->assertSame([], $diagnostics['invalid_pairs']);
        $this->assertSame([], $diagnostics['failed']);
        $this->assertSame(0, $diagnostics['failed_count']);
    }

    public function test_it_provides_canonical_source_set_assertions_diagnostics_snapshot(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $diagnostics = $service->canonicalSourceSetAssertionsDiagnostics([
            'webu_ecom_account_security_01=auth.security',
            'webu_ecom_product_detail_01=ecom.productDetail',
        ]);

        $this->assertTrue($diagnostics['ok']);
        $this->assertSame(2, $diagnostics['required_pair_count']);
        $this->assertSame(2, $diagnostics['evaluated_count']);
        $this->assertSame(0, $diagnostics['invalid_pair_count']);
        $this->assertSame([], $diagnostics['invalid_pairs']);
        $this->assertSame([], $diagnostics['failed']);
        $this->assertSame(0, $diagnostics['failed_count']);
    }

    public function test_it_returns_empty_results_for_unknown_or_blank_keys(): void
    {
        $service = app(CmsComponentLibrarySpecEquivalenceAliasMapService::class);

        $this->assertFalse($service->hasSourceComponentKey(''));
        $this->assertFalse($service->hasSourceComponentKey('unknown.key'));
        $this->assertSame([], $service->resolveCanonicalBuilderKeys('unknown.key'));
        $this->assertNull($service->resolvePrimaryCanonicalBuilderKey('unknown.key'));
        $this->assertSame([], $service->findSourceComponentKeysForCanonicalBuilderKey(''));
        $this->assertSame([], $service->findSourceComponentKeysForCanonicalBuilderKey('webu_unknown_component_01'));
    }
}
