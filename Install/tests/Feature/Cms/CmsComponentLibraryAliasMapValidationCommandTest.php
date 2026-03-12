<?php

namespace Tests\Feature\Cms;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** @group docs-sync */
class CmsComponentLibraryAliasMapValidationCommandTest extends TestCase
{
    public function test_command_validates_component_library_alias_map_successfully(): void
    {
        $this->artisan('cms:component-library-alias-map-validate')
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('complete_exact_plus_equivalent')
            ->expectsOutputToContain('70')
            ->assertExitCode(0);
    }

    public function test_command_supports_strict_schema_checks_mode(): void
    {
        $this->artisan('cms:component-library-alias-map-validate', [
            '--strict-schema' => true,
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Strict schema checks:')
            ->expectsOutputToContain('yes')
            ->assertExitCode(0);
    }

    public function test_command_supports_strict_export_schema_checks_mode(): void
    {
        $this->artisan('cms:component-library-alias-map-validate', [
            '--strict-export-schema' => true,
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Export schema checks:')
            ->expectsOutputToContain('Schema Fingerprint')
            ->expectsOutputToContain('Mappings minItems')
            ->expectsOutputToContain('yes')
            ->assertExitCode(0);
    }

    public function test_command_supports_strict_schema_assertions_in_human_output(): void
    {
        $schemaFingerprint = app(\App\Services\CmsComponentLibrarySpecEquivalenceAliasMapService::class)->schemaFingerprint();

        $this->artisan('cms:component-library-alias-map-validate', [
            '--assert-schema-fingerprint' => $schemaFingerprint,
            '--assert-schema-path-suffix' => 'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map.v1.schema.json',
            '--assert-schema-version-const' => 'v1',
            '--assert-schema-coverage-status-const' => 'complete_exact_plus_equivalent',
            '--assert-schema-validated-rows' => '70',
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Strict schema checks:')
            ->expectsOutputToContain('Strict schema assertions:')
            ->expectsOutputToContain('Schema fingerprint')
            ->expectsOutputToContain('Schema version const')
            ->expectsOutputToContain('Schema coverage_status const')
            ->expectsOutputToContain('Schema validated rows')
            ->expectsOutputToContain('yes')
            ->assertExitCode(0);
    }

    public function test_command_supports_strict_export_schema_assertions_in_human_output(): void
    {
        $exportSchemaFingerprint = app(\App\Services\CmsComponentLibrarySpecEquivalenceAliasMapService::class)->exportSchemaFingerprint();

        $this->artisan('cms:component-library-alias-map-validate', [
            '--assert-export-schema-fingerprint' => $exportSchemaFingerprint,
            '--assert-export-schema-path-suffix' => 'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map-export.v1.schema.json',
            '--assert-export-schema-summary-version-const' => 'v1',
            '--assert-export-schema-coverage-status-const' => 'complete_exact_plus_equivalent',
            '--assert-export-schema-mapping-count-const' => '70',
            '--assert-export-schema-stats-rows-const' => '70',
            '--assert-export-schema-mappings-min-items' => '70',
            '--assert-export-schema-validated-rows' => '70',
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Export schema checks:')
            ->expectsOutputToContain('Strict export schema assertions:')
            ->expectsOutputToContain('Schema fingerprint')
            ->expectsOutputToContain('Summary mapping_count const')
            ->expectsOutputToContain('Stats rows const')
            ->expectsOutputToContain('Mappings minItems')
            ->expectsOutputToContain('yes')
            ->assertExitCode(0);
    }

    public function test_command_supports_stats_mode_in_human_output(): void
    {
        $this->artisan('cms:component-library-alias-map-validate', [
            '--stats' => true,
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Alias map stats:')
            ->expectsOutputToContain('Distinct canonical keys')
            ->expectsOutputToContain('equivalent')
            ->assertExitCode(0);
    }

    public function test_command_supports_fingerprints_mode_in_human_output(): void
    {
        $this->artisan('cms:component-library-alias-map-validate', [
            '--fingerprints' => true,
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Fingerprint diagnostics:')
            ->expectsOutputToContain('Map fingerprint')
            ->expectsOutputToContain('Schema fingerprint')
            ->expectsOutputToContain('Export schema fingerprint')
            ->expectsOutputToContain('Export content fingerprint')
            ->expectsOutputToContain('Artifact bundle fingerprint')
            ->assertExitCode(0);
    }

    public function test_command_supports_stats_threshold_assertions_in_human_output(): void
    {
        $this->artisan('cms:component-library-alias-map-validate', [
            '--assert-max-partial' => '0',
            '--assert-max-missing' => '0',
            '--assert-max-unknown' => '0',
            '--assert-min-covered' => '70',
            '--assert-max-composite-alias-rows' => '3',
            '--assert-max-canonical-keys-per-row' => '3',
            '--assert-min-distinct-canonical-keys' => '74',
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Stats threshold checks:')
            ->expectsOutputToContain('Covered rows')
            ->expectsOutputToContain('Composite alias rows')
            ->expectsOutputToContain('Failed assertions')
            ->expectsOutputToContain('yes')
            ->assertExitCode(0);
    }

    public function test_command_supports_summary_assertions_in_human_output(): void
    {
        $service = app(\App\Services\CmsComponentLibrarySpecEquivalenceAliasMapService::class);
        $fingerprint = $service->fingerprint();
        $artifactBundleFingerprint = $service->artifactBundleFingerprint();

        $this->artisan('cms:component-library-alias-map-validate', [
            '--assert-map-version' => 'v1',
            '--assert-coverage-status' => 'complete_exact_plus_equivalent',
            '--assert-mapping-count' => '70',
            '--assert-map-fingerprint' => $fingerprint,
            '--assert-artifact-bundle-fingerprint' => $artifactBundleFingerprint,
            '--assert-source-spec-ref-contains' => 'PROJECT_ROADMAP_TASKS_KA.md:6439',
            '--assert-gap-audit-ref-contains' => 'UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md',
            '--assert-source-spec-ref-regex' => '/PROJECT_ROADMAP_TASKS_KA\\.md:6439-\\d+/',
            '--assert-gap-audit-ref-regex' => '/UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE\\.md$/',
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Summary assertions:')
            ->expectsOutputToContain('Coverage status')
            ->expectsOutputToContain('Summary mapping_count')
            ->expectsOutputToContain('Source spec ref')
            ->expectsOutputToContain('Gap audit ref')
            ->expectsOutputToContain('Map fingerprint')
            ->expectsOutputToContain('Artifact bundle fingerprint')
            ->expectsOutputToContain('yes')
            ->assertExitCode(0);
    }

    public function test_command_supports_required_alias_assertions_in_human_output(): void
    {
        $this->artisan('cms:component-library-alias-map-validate', [
            '--assert-source-key' => ['auth.security', 'ecom.productDetail'],
            '--assert-canonical-key' => ['webu_ecom_account_security_01', 'webu_ecom_product_detail_01'],
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Required alias assertions:')
            ->expectsOutputToContain('Required source keys')
            ->expectsOutputToContain('Required canonical keys')
            ->expectsOutputToContain('yes')
            ->assertExitCode(0);
    }

    public function test_command_supports_source_primary_assertions_in_human_output(): void
    {
        $this->artisan('cms:component-library-alias-map-validate', [
            '--assert-source-primary' => [
                'auth.security=webu_ecom_account_security_01',
                'ecom.productDetail=webu_ecom_product_detail_01',
            ],
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Source primary assertions:')
            ->expectsOutputToContain('Required pairs')
            ->expectsOutputToContain('Evaluated pairs')
            ->expectsOutputToContain('yes')
            ->assertExitCode(0);
    }

    public function test_command_supports_source_contains_canonical_assertions_in_human_output(): void
    {
        $this->artisan('cms:component-library-alias-map-validate', [
            '--assert-source-contains-canonical' => [
                'ecom.productDetail=webu_ecom_add_to_cart_button_01',
                'ecom.productDetail=webu_ecom_product_tabs_01',
            ],
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Source contains canonical assertions:')
            ->expectsOutputToContain('Required pairs')
            ->expectsOutputToContain('Evaluated pairs')
            ->expectsOutputToContain('yes')
            ->assertExitCode(0);
    }

    public function test_command_supports_source_canonical_set_assertions_in_human_output(): void
    {
        $this->artisan('cms:component-library-alias-map-validate', [
            '--assert-source-canonical-set' => [
                'ecom.productDetail=webu_ecom_product_detail_01|webu_ecom_add_to_cart_button_01|webu_ecom_product_tabs_01',
            ],
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Source canonical set assertions:')
            ->expectsOutputToContain('Required pairs')
            ->expectsOutputToContain('Evaluated pairs')
            ->expectsOutputToContain('yes')
            ->assertExitCode(0);
    }

    public function test_command_supports_canonical_source_set_assertions_in_human_output(): void
    {
        $this->artisan('cms:component-library-alias-map-validate', [
            '--assert-canonical-source-set' => [
                'webu_ecom_account_security_01=auth.security',
                'webu_ecom_product_detail_01=ecom.productDetail',
            ],
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Canonical source set assertions:')
            ->expectsOutputToContain('Required pairs')
            ->expectsOutputToContain('Evaluated pairs')
            ->expectsOutputToContain('yes')
            ->assertExitCode(0);
    }

    public function test_command_supports_roundtrip_check_mode_in_human_output(): void
    {
        $this->artisan('cms:component-library-alias-map-validate', [
            '--roundtrip-check' => true,
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Round-trip consistency checks:')
            ->expectsOutputToContain('Inverse lookup misses')
            ->expectsOutputToContain('Primary key mismatches')
            ->expectsOutputToContain('yes')
            ->assertExitCode(0);
    }

    public function test_command_supports_ci_check_preset_in_human_output(): void
    {
        $this->artisan('cms:component-library-alias-map-validate', [
            '--ci-check' => true,
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Fingerprint diagnostics:')
            ->expectsOutputToContain('Strict schema checks:')
            ->expectsOutputToContain('Export schema checks:')
            ->expectsOutputToContain('Round-trip consistency checks:')
            ->expectsOutputToContain('Alias map stats:')
            ->assertExitCode(0);
    }

    public function test_command_supports_ci_baseline_preset_in_human_output(): void
    {
        $this->artisan('cms:component-library-alias-map-validate', [
            '--ci-baseline' => true,
        ])
            ->expectsOutputToContain('Validated component-library spec equivalence alias map (v1).')
            ->expectsOutputToContain('Fingerprint diagnostics:')
            ->expectsOutputToContain('Summary assertions:')
            ->expectsOutputToContain('Required alias assertions:')
            ->expectsOutputToContain('Strict schema checks:')
            ->expectsOutputToContain('Export schema checks:')
            ->expectsOutputToContain('Strict export schema assertions:')
            ->expectsOutputToContain('Round-trip consistency checks:')
            ->expectsOutputToContain('Stats threshold checks:')
            ->expectsOutputToContain('Alias map stats:')
            ->assertExitCode(0);
    }

    public function test_command_resolves_source_spec_component_key_and_composite_aliases_in_json_output(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--source-key' => 'ecom.productDetail',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('source_component_key', $output);
        $this->assertStringContainsString('ecom.productDetail', $output);
        $this->assertStringContainsString('webu_ecom_product_detail_01', $output);
        $this->assertStringContainsString('webu_ecom_add_to_cart_button_01', $output);
        $this->assertStringContainsString('webu_ecom_product_tabs_01', $output);
    }

    public function test_command_resolves_canonical_builder_key_back_to_source_spec_key(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--canonical-key' => 'webu_ecom_account_security_01',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('canonical_builder_key', $output);
        $this->assertStringContainsString('webu_ecom_account_security_01', $output);
        $this->assertStringContainsString('auth.security', $output);
    }

    public function test_command_returns_failure_for_unknown_source_spec_component_key(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--source-key' => 'unknown.component',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('source_component_key_not_found', $output);
    }

    public function test_command_outputs_strict_schema_json_check_payload(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--strict-schema' => true,
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"strict_schema"', $output);
        $this->assertStringContainsString('"validated_rows": 70', $output);
        $this->assertStringContainsString('"error_count": 0', $output);
        $this->assertStringContainsString('cms-component-library-spec-equivalence-alias-map.v1.schema.json', $output);
    }

    public function test_command_outputs_strict_export_schema_json_check_payload(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--strict-export-schema' => true,
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"export_schema"', $output);
        $this->assertStringContainsString('"validated_rows": 70', $output);
        $this->assertStringContainsString('"error_count": 0', $output);
        $this->assertStringContainsString('cms-component-library-spec-equivalence-alias-map-export.v1.schema.json', $output);
    }

    public function test_command_outputs_strict_schema_assertion_payload_in_json_mode(): void
    {
        $schemaFingerprint = app(\App\Services\CmsComponentLibrarySpecEquivalenceAliasMapService::class)->schemaFingerprint();

        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-schema-fingerprint' => $schemaFingerprint,
            '--assert-schema-path-suffix' => 'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map.v1.schema.json',
            '--assert-schema-version-const' => 'v1',
            '--assert-schema-coverage-status-const' => 'complete_exact_plus_equivalent',
            '--assert-schema-validated-rows' => '70',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"strict_schema"', $output);
        $this->assertStringContainsString('"strict_schema_assertions"', $output);
        $this->assertStringContainsString('"assert_schema_fingerprint"', $output);
        $this->assertStringContainsString('"assert_schema_path_suffix"', $output);
        $this->assertStringContainsString('"assert_schema_version_const"', $output);
        $this->assertStringContainsString('"assert_schema_coverage_status_const"', $output);
        $this->assertStringContainsString('"assert_schema_validated_rows": 70', $output);
        $this->assertStringContainsString('"schema_fingerprint"', $output);
        $this->assertStringContainsString('"schema_version_const": "v1"', $output);
        $this->assertStringContainsString('"schema_coverage_status_const": "complete_exact_plus_equivalent"', $output);
        $this->assertStringContainsString('"validated_rows": 70', $output);
        $this->assertStringContainsString('"failed_count": 0', $output);
    }

    public function test_command_outputs_strict_export_schema_assertion_payload_in_json_mode(): void
    {
        $exportSchemaFingerprint = app(\App\Services\CmsComponentLibrarySpecEquivalenceAliasMapService::class)->exportSchemaFingerprint();

        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-export-schema-fingerprint' => $exportSchemaFingerprint,
            '--assert-export-schema-path-suffix' => 'docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map-export.v1.schema.json',
            '--assert-export-schema-summary-version-const' => 'v1',
            '--assert-export-schema-coverage-status-const' => 'complete_exact_plus_equivalent',
            '--assert-export-schema-mapping-count-const' => '70',
            '--assert-export-schema-stats-rows-const' => '70',
            '--assert-export-schema-mappings-min-items' => '70',
            '--assert-export-schema-validated-rows' => '70',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"strict_export_schema_assertions"', $output);
        $this->assertStringContainsString('"assert_export_schema_fingerprint"', $output);
        $this->assertStringContainsString('"assert_export_schema_path_suffix"', $output);
        $this->assertStringContainsString('"assert_export_schema_summary_version_const"', $output);
        $this->assertStringContainsString('"assert_export_schema_coverage_status_const"', $output);
        $this->assertStringContainsString('"assert_export_schema_mapping_count_const": 70', $output);
        $this->assertStringContainsString('"assert_export_schema_stats_rows_const": 70', $output);
        $this->assertStringContainsString('"assert_export_schema_mappings_min_items": 70', $output);
        $this->assertStringContainsString('"assert_export_schema_validated_rows": 70', $output);
        $this->assertStringContainsString('"schema_summary_mapping_count_const": 70', $output);
        $this->assertStringContainsString('"schema_stats_rows_const": 70', $output);
        $this->assertStringContainsString('"schema_mappings_min_items": 70', $output);
        $this->assertStringContainsString('"failed_count": 0', $output);
    }

    public function test_command_outputs_stats_payload_in_json_mode(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--stats' => true,
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"stats"', $output);
        $this->assertStringContainsString('"coverage_breakdown"', $output);
        $this->assertStringContainsString('"equivalent": 70', $output);
        $this->assertStringContainsString('"missing": 0', $output);
    }

    public function test_command_outputs_fingerprints_payload_in_json_mode(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--fingerprints' => true,
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"fingerprints"', $output);
        $this->assertStringContainsString('"map_fingerprint"', $output);
        $this->assertStringContainsString('"schema_fingerprint"', $output);
        $this->assertStringContainsString('"export_schema_fingerprint"', $output);
        $this->assertStringContainsString('"export_content_fingerprint"', $output);
        $this->assertStringContainsString('"artifact_bundle_fingerprint"', $output);
    }

    public function test_command_outputs_stats_threshold_assertion_payload_in_json_mode(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-max-partial' => '0',
            '--assert-max-missing' => '0',
            '--assert-max-unknown' => '0',
            '--assert-min-covered' => '70',
            '--assert-max-composite-alias-rows' => '3',
            '--assert-max-canonical-keys-per-row' => '3',
            '--assert-min-distinct-canonical-keys' => '74',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"stats_thresholds"', $output);
        $this->assertStringContainsString('"failed_count": 0', $output);
        $this->assertStringContainsString('"covered_rows": 70', $output);
        $this->assertStringContainsString('"composite_alias_rows": 3', $output);
        $this->assertStringContainsString('"distinct_canonical_builder_key_count": 74', $output);
    }

    public function test_command_outputs_summary_assertion_payload_in_json_mode(): void
    {
        $service = app(\App\Services\CmsComponentLibrarySpecEquivalenceAliasMapService::class);
        $fingerprint = $service->fingerprint();
        $artifactBundleFingerprint = $service->artifactBundleFingerprint();

        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-map-version' => 'v1',
            '--assert-coverage-status' => 'complete_exact_plus_equivalent',
            '--assert-mapping-count' => '70',
            '--assert-map-fingerprint' => $fingerprint,
            '--assert-artifact-bundle-fingerprint' => $artifactBundleFingerprint,
            '--assert-source-spec-ref-contains' => 'PROJECT_ROADMAP_TASKS_KA.md:6439',
            '--assert-gap-audit-ref-contains' => 'UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md',
            '--assert-source-spec-ref-regex' => '/PROJECT_ROADMAP_TASKS_KA\\.md:6439-\\d+/',
            '--assert-gap-audit-ref-regex' => '/UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE\\.md$/',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"summary_assertions"', $output);
        $this->assertStringContainsString('"failed_count": 0', $output);
        $this->assertStringContainsString('"mapping_count": 70', $output);
        $this->assertStringContainsString('"source_spec_ref"', $output);
        $this->assertStringContainsString('"gap_audit_ref"', $output);
        $this->assertStringContainsString('"assert_source_spec_ref_contains"', $output);
        $this->assertStringContainsString('"assert_gap_audit_ref_contains"', $output);
        $this->assertStringContainsString('"assert_source_spec_ref_regex"', $output);
        $this->assertStringContainsString('"assert_gap_audit_ref_regex"', $output);
        $this->assertStringContainsString('"map_fingerprint"', $output);
        $this->assertStringContainsString('"assert_artifact_bundle_fingerprint"', $output);
        $this->assertStringContainsString('"artifact_bundle_fingerprint"', $output);
    }

    public function test_command_outputs_required_alias_assertion_payload_in_json_mode(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-source-key' => ['auth.security'],
            '--assert-canonical-key' => ['webu_ecom_account_security_01'],
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"required_aliases"', $output);
        $this->assertStringContainsString('"missing_source_key_count": 0', $output);
        $this->assertStringContainsString('"missing_canonical_key_count": 0', $output);
    }

    public function test_command_outputs_roundtrip_check_payload_in_json_mode(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--roundtrip-check' => true,
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"roundtrip"', $output);
        $this->assertStringContainsString('"inverse_lookup_miss_count": 0', $output);
        $this->assertStringContainsString('"primary_key_mismatch_count": 0', $output);
    }

    public function test_command_outputs_ci_check_preset_payload_in_json_mode(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--ci-check' => true,
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"ci_preset": true', $output);
        $this->assertStringContainsString('"fingerprints"', $output);
        $this->assertStringContainsString('"strict_schema"', $output);
        $this->assertStringContainsString('"export_schema"', $output);
        $this->assertStringContainsString('"roundtrip"', $output);
        $this->assertStringContainsString('"stats"', $output);
    }

    public function test_command_outputs_ci_baseline_preset_payload_in_json_mode(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--ci-baseline' => true,
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"ci_baseline_preset": true', $output);
        $this->assertStringContainsString('"ci_preset": true', $output);
        $this->assertStringContainsString('"ci_baseline_profile"', $output);
        $this->assertStringContainsString('"fingerprints"', $output);
        $this->assertStringContainsString('"required_source_keys"', $output);
        $this->assertStringContainsString('"auth.security"', $output);
        $this->assertStringContainsString('"source_primary_assertions"', $output);
        $this->assertStringContainsString('"auth.security=webu_ecom_account_security_01"', $output);
        $this->assertStringContainsString('"source_contains_assertions"', $output);
        $this->assertStringContainsString('"webu_ecom_add_to_cart_button_01"', $output);
        $this->assertStringContainsString('"source_canonical_set_assertions"', $output);
        $this->assertStringContainsString('"expected_canonical_builder_keys"', $output);
        $this->assertStringContainsString('"resolved_canonical_builder_keys_unique"', $output);
        $this->assertStringContainsString('"canonical_source_set_assertions"', $output);
        $this->assertStringContainsString('"webu_ecom_account_security_01=auth.security"', $output);
        $this->assertStringContainsString('"assert_map_fingerprint"', $output);
        $this->assertStringContainsString('"map_fingerprint"', $output);
        $this->assertStringContainsString('"assert_artifact_bundle_fingerprint"', $output);
        $this->assertStringContainsString('"artifact_bundle_fingerprint"', $output);
        $this->assertStringContainsString('"assert_source_spec_ref_regex"', $output);
        $this->assertStringContainsString('"assert_gap_audit_ref_regex"', $output);
        $this->assertStringContainsString('"strict_schema_assertions"', $output);
        $this->assertStringContainsString('"strict_export_schema_assertions"', $output);
        $this->assertStringContainsString('"export_schema"', $output);
        $this->assertStringContainsString('"assert_schema_fingerprint"', $output);
        $this->assertStringContainsString('"assert_schema_path_suffix"', $output);
        $this->assertStringContainsString('"assert_schema_version_const"', $output);
        $this->assertStringContainsString('"assert_schema_coverage_status_const"', $output);
        $this->assertStringContainsString('"assert_schema_validated_rows": 70', $output);
        $this->assertStringContainsString('"assert_export_schema_fingerprint"', $output);
        $this->assertStringContainsString('"assert_export_schema_path_suffix"', $output);
        $this->assertStringContainsString('"assert_export_schema_summary_version_const"', $output);
        $this->assertStringContainsString('"assert_export_schema_coverage_status_const"', $output);
        $this->assertStringContainsString('"assert_export_schema_mapping_count_const": 70', $output);
        $this->assertStringContainsString('"assert_export_schema_stats_rows_const": 70', $output);
        $this->assertStringContainsString('"assert_export_schema_mappings_min_items": 70', $output);
        $this->assertStringContainsString('"assert_export_schema_validated_rows": 70', $output);
        $this->assertStringContainsString('"schema_fingerprint"', $output);
        $this->assertStringContainsString('"export_schema_fingerprint"', $output);
        $this->assertStringContainsString('"export_content_fingerprint"', $output);
        $this->assertStringContainsString('"summary_assertions"', $output);
        $this->assertStringContainsString('"required_aliases"', $output);
        $this->assertStringContainsString('"stats_thresholds"', $output);
    }

    public function test_command_outputs_source_primary_assertion_payload_in_json_mode(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-source-primary' => ['auth.security=webu_ecom_account_security_01'],
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"source_primary_assertions"', $output);
        $this->assertStringContainsString('"invalid_pair_count": 0', $output);
        $this->assertStringContainsString('"failed_count": 0', $output);
    }

    public function test_command_outputs_source_contains_canonical_assertion_payload_in_json_mode(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-source-contains-canonical' => ['ecom.productDetail=webu_ecom_product_tabs_01'],
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"source_contains_canonical_assertions"', $output);
        $this->assertStringContainsString('"invalid_pair_count": 0', $output);
        $this->assertStringContainsString('"failed_count": 0', $output);
    }

    public function test_command_outputs_source_canonical_set_assertion_payload_in_json_mode(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-source-canonical-set' => ['ecom.productDetail=webu_ecom_product_detail_01|webu_ecom_add_to_cart_button_01|webu_ecom_product_tabs_01'],
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"source_canonical_set_assertions"', $output);
        $this->assertStringContainsString('"invalid_pair_count": 0', $output);
        $this->assertStringContainsString('"failed_count": 0', $output);
    }

    public function test_command_outputs_canonical_source_set_assertion_payload_in_json_mode(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-canonical-source-set' => ['webu_ecom_account_security_01=auth.security'],
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"ok": true', $output);
        $this->assertStringContainsString('"canonical_source_set_assertions"', $output);
        $this->assertStringContainsString('"invalid_pair_count": 0', $output);
        $this->assertStringContainsString('"failed_count": 0', $output);
    }

    public function test_command_can_export_alias_map_as_csv_report(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-csv' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('version,v1', $output);
        $this->assertStringContainsString('coverage_status,complete_exact_plus_equivalent', $output);
        $this->assertStringContainsString('source_component_key,coverage,canonical_builder_keys,primary_canonical_builder_key,canonical_builder_key_count', $output);
        $this->assertStringContainsString('ecom.productDetail,equivalent,', $output);
        $this->assertStringContainsString('webu_ecom_product_detail_01', $output);
        $this->assertStringNotContainsString('Validated component-library spec equivalence alias map (v1).', $output);
    }

    public function test_command_can_export_alias_map_as_markdown_report(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-md' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('# Component Library Alias Map Export (v1)', $output);
        $this->assertStringContainsString('| source_component_key | coverage | primary_canonical_builder_key | canonical_builder_keys |', $output);
        $this->assertStringContainsString('| `auth.security` | `equivalent` | `webu_ecom_account_security_01` |', $output);
    }

    public function test_command_can_export_alias_map_as_json_report(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('"summary"', $output);
        $this->assertStringContainsString('"stats"', $output);
        $this->assertStringContainsString('"fingerprints"', $output);
        $this->assertStringContainsString('"mappings"', $output);
        $this->assertStringContainsString('"artifact_bundle_fingerprint"', $output);
        $this->assertStringNotContainsString('Validated component-library spec equivalence alias map (v1).', $output);
    }

    public function test_command_rejects_conflicting_export_modes(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-csv' => true,
            '--export-md' => true,
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('conflicting_export_modes', $output);
    }

    public function test_command_rejects_conflicting_export_modes_when_json_export_is_combined(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-json' => true,
            '--export-md' => true,
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('conflicting_export_modes', $output);
    }

    public function test_command_rejects_json_output_when_export_mode_is_requested(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-md' => true,
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('export_modes_incompatible_with_json', $output);
    }

    public function test_command_rejects_invalid_stats_threshold_values(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-max-missing' => 'abc',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('invalid_threshold_value', $output);
        $this->assertStringContainsString('assert_max_missing', $output);
    }

    public function test_command_rejects_invalid_summary_assertion_values(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-mapping-count' => '70x',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('invalid_assertion_value', $output);
        $this->assertStringContainsString('assert_mapping_count', $output);
    }

    public function test_command_rejects_stats_mode_when_export_mode_is_requested(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-csv' => true,
            '--stats' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Component-library spec equivalence alias map validation failed.', $output);
        $this->assertStringContainsString('stats_incompatible_with_export_modes', $output);
    }

    public function test_command_rejects_fingerprints_mode_when_export_mode_is_requested(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-csv' => true,
            '--fingerprints' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Component-library spec equivalence alias map validation failed.', $output);
        $this->assertStringContainsString('fingerprints_incompatible_with_export_modes', $output);
    }

    public function test_command_rejects_stats_assertions_when_export_mode_is_requested(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-csv' => true,
            '--assert-max-partial' => '0',
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Component-library spec equivalence alias map validation failed.', $output);
        $this->assertStringContainsString('stats_assertions_incompatible_with_export_modes', $output);
    }

    public function test_command_fails_when_stats_threshold_assertion_is_violated(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-max-composite-alias-rows' => '2',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('stats_threshold_assertion_failed', $output);
        $this->assertStringContainsString('"failed_count": 1', $output);
        $this->assertStringContainsString('"assert_max_composite_alias_rows"', $output);
    }

    public function test_command_fails_when_summary_assertion_is_violated(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-coverage-status' => 'not_real_status',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('summary_assertion_failed', $output);
        $this->assertStringContainsString('"failed_count": 1', $output);
        $this->assertStringContainsString('"assert_coverage_status"', $output);
    }

    public function test_command_fails_when_artifact_bundle_fingerprint_summary_assertion_is_violated(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-artifact-bundle-fingerprint' => 'sha256:deadbeef',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('summary_assertion_failed', $output);
        $this->assertStringContainsString('"assert_artifact_bundle_fingerprint"', $output);
        $this->assertStringContainsString('"failed_count": 1', $output);
    }

    public function test_command_fails_when_summary_reference_contains_assertion_is_violated(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-source-spec-ref-contains' => 'PROJECT_ROADMAP_TASKS_KA.md:999999',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('summary_assertion_failed', $output);
        $this->assertStringContainsString('"assert_source_spec_ref_contains"', $output);
        $this->assertStringContainsString('"failed_count": 1', $output);
    }

    public function test_command_fails_when_summary_reference_regex_assertion_is_violated(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-gap-audit-ref-regex' => '/NO_MATCH_GAP_AUDIT_REF/',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('summary_assertion_failed', $output);
        $this->assertStringContainsString('"assert_gap_audit_ref_regex"', $output);
        $this->assertStringContainsString('"failed_count": 1', $output);
    }

    public function test_command_rejects_invalid_summary_reference_regex_assertion_values(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-source-spec-ref-regex' => '/unterminated(',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('invalid_assertion_value', $output);
        $this->assertStringContainsString('assert_source_spec_ref_regex', $output);
    }

    public function test_command_fails_when_strict_schema_assertion_is_violated(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-schema-fingerprint' => 'sha256:deadbeef',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('strict_schema_assertion_failed', $output);
        $this->assertStringContainsString('"strict_schema_assertions"', $output);
        $this->assertStringContainsString('"failed_count": 1', $output);
        $this->assertStringContainsString('"assert_schema_fingerprint"', $output);
    }

    public function test_command_fails_when_strict_schema_path_suffix_assertion_is_violated(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-schema-path-suffix' => 'wrong/path/schema.json',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('strict_schema_assertion_failed', $output);
        $this->assertStringContainsString('"assert_schema_path_suffix"', $output);
        $this->assertStringContainsString('"failed_count": 1', $output);
    }

    public function test_command_fails_when_strict_export_schema_assertion_is_violated(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-export-schema-fingerprint' => 'sha256:deadbeef',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('strict_export_schema_assertion_failed', $output);
        $this->assertStringContainsString('"strict_export_schema_assertions"', $output);
        $this->assertStringContainsString('"failed_count": 1', $output);
        $this->assertStringContainsString('"assert_export_schema_fingerprint"', $output);
    }

    public function test_command_rejects_invalid_strict_schema_assertion_values(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-schema-validated-rows' => '70x',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('invalid_assertion_value', $output);
        $this->assertStringContainsString('assert_schema_validated_rows', $output);
    }

    public function test_command_rejects_invalid_strict_export_schema_assertion_values(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-export-schema-validated-rows' => '70x',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('invalid_assertion_value', $output);
        $this->assertStringContainsString('assert_export_schema_validated_rows', $output);
    }

    public function test_command_fails_when_required_alias_assertion_is_violated(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-source-key' => ['auth.security', 'unknown.component'],
            '--assert-canonical-key' => ['webu_ecom_account_security_01', 'webu_nonexistent_component_01'],
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('required_alias_assertion_failed', $output);
        $this->assertStringContainsString('"missing_source_key_count": 1', $output);
        $this->assertStringContainsString('"missing_canonical_key_count": 1', $output);
        $this->assertStringContainsString('unknown.component', $output);
        $this->assertStringContainsString('webu_nonexistent_component_01', $output);
    }

    public function test_command_fails_when_source_primary_assertion_is_violated(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-source-primary' => ['auth.security=webu_wrong_primary_01'],
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('source_primary_assertion_failed', $output);
        $this->assertStringContainsString('"failed_count": 1', $output);
        $this->assertStringContainsString('webu_wrong_primary_01', $output);
    }

    public function test_command_fails_when_source_primary_assertion_pair_format_is_invalid(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-source-primary' => ['auth.security:webu_ecom_account_security_01'],
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('source_primary_assertion_failed', $output);
        $this->assertStringContainsString('"invalid_pair_count": 1', $output);
        $this->assertStringContainsString('auth.security:webu_ecom_account_security_01', $output);
    }

    public function test_command_fails_when_source_contains_canonical_assertion_is_violated(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-source-contains-canonical' => ['ecom.productDetail=webu_nonexistent_component_01'],
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('source_contains_canonical_assertion_failed', $output);
        $this->assertStringContainsString('"failed_count": 1', $output);
        $this->assertStringContainsString('webu_nonexistent_component_01', $output);
    }

    public function test_command_fails_when_source_contains_canonical_pair_format_is_invalid(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-source-contains-canonical' => ['ecom.productDetail:webu_ecom_product_tabs_01'],
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('source_contains_canonical_assertion_failed', $output);
        $this->assertStringContainsString('"invalid_pair_count": 1', $output);
        $this->assertStringContainsString('ecom.productDetail:webu_ecom_product_tabs_01', $output);
    }

    public function test_command_fails_when_source_canonical_set_assertion_is_violated(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-source-canonical-set' => ['ecom.productDetail=webu_ecom_product_detail_01|webu_ecom_add_to_cart_button_01'],
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('source_canonical_set_assertion_failed', $output);
        $this->assertStringContainsString('"failed_count": 1', $output);
        $this->assertStringContainsString('webu_ecom_product_tabs_01', $output);
    }

    public function test_command_fails_when_source_canonical_set_pair_format_is_invalid(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-source-canonical-set' => ['ecom.productDetail:webu_ecom_product_detail_01|webu_ecom_product_tabs_01'],
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('source_canonical_set_assertion_failed', $output);
        $this->assertStringContainsString('"invalid_pair_count": 1', $output);
        $this->assertStringContainsString('ecom.productDetail:webu_ecom_product_detail_01|webu_ecom_product_tabs_01', $output);
    }

    public function test_command_fails_when_canonical_source_set_assertion_is_violated(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-canonical-source-set' => ['webu_ecom_product_detail_01=auth.security'],
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('canonical_source_set_assertion_failed', $output);
        $this->assertStringContainsString('"failed_count": 1', $output);
        $this->assertStringContainsString('ecom.productDetail', $output);
    }

    public function test_command_fails_when_canonical_source_set_pair_format_is_invalid(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--assert-canonical-source-set' => ['webu_ecom_account_security_01:auth.security'],
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('canonical_source_set_assertion_failed', $output);
        $this->assertStringContainsString('"invalid_pair_count": 1', $output);
        $this->assertStringContainsString('webu_ecom_account_security_01:auth.security', $output);
    }

    public function test_command_rejects_roundtrip_check_when_export_mode_is_requested(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-md' => true,
            '--roundtrip-check' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Component-library spec equivalence alias map validation failed.', $output);
        $this->assertStringContainsString('roundtrip_check_incompatible_with_export_modes', $output);
    }

    public function test_command_rejects_strict_export_schema_when_export_mode_is_requested(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-json' => true,
            '--strict-export-schema' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Component-library spec equivalence alias map validation failed.', $output);
        $this->assertStringContainsString('export_schema_check_incompatible_with_export_modes', $output);
    }

    public function test_command_rejects_ci_check_preset_when_export_mode_is_requested(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-csv' => true,
            '--ci-check' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Component-library spec equivalence alias map validation failed.', $output);
        $this->assertStringContainsString('ci_check_incompatible_with_export_modes', $output);
    }

    public function test_command_rejects_ci_baseline_preset_when_export_mode_is_requested(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-csv' => true,
            '--ci-baseline' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('Component-library spec equivalence alias map validation failed.', $output);
        $this->assertStringContainsString('ci_check_incompatible_with_export_modes', $output);
    }

    public function test_command_can_write_csv_export_to_safe_relative_output_file(): void
    {
        $relativePath = 'feature-tests/alias-map-export.csv';
        $absolutePath = storage_path('app/cms/component-library-alias-map-exports/'.$relativePath);

        File::delete($absolutePath);

        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-csv' => true,
            '--output' => $relativePath,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Exported component-library alias map report.', $output);
        $this->assertStringContainsString('Format: csv', $output);
        $this->assertStringContainsString('Output: '.$relativePath, $output);
        $this->assertTrue(File::exists($absolutePath));

        $fileContents = (string) File::get($absolutePath);
        $this->assertStringContainsString('source_component_key,coverage,canonical_builder_keys', $fileContents);
        $this->assertStringContainsString('auth.security,equivalent,webu_ecom_account_security_01', $fileContents);

        File::delete($absolutePath);
    }

    public function test_command_can_write_json_export_to_safe_relative_output_file(): void
    {
        $relativePath = 'feature-tests/alias-map-export.json';
        $absolutePath = storage_path('app/cms/component-library-alias-map-exports/'.$relativePath);

        File::delete($absolutePath);

        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-json' => true,
            '--output' => $relativePath,
        ]);

        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Exported component-library alias map report.', $output);
        $this->assertStringContainsString('Format: json', $output);
        $this->assertStringContainsString('Output: '.$relativePath, $output);
        $this->assertTrue(File::exists($absolutePath));

        $fileContents = (string) File::get($absolutePath);
        $this->assertStringContainsString('"summary"', $fileContents);
        $this->assertStringContainsString('"stats"', $fileContents);
        $this->assertStringContainsString('"fingerprints"', $fileContents);
        $this->assertStringContainsString('"export_schema_fingerprint"', $fileContents);
        $this->assertStringContainsString('"export_content_fingerprint"', $fileContents);
        $this->assertStringContainsString('"artifact_bundle_fingerprint"', $fileContents);

        File::delete($absolutePath);
    }

    public function test_command_rejects_output_path_without_export_mode(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--output' => 'reports/alias-map.csv',
            '--json' => true,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('output_requires_export_mode', $output);
    }

    public function test_command_rejects_absolute_output_paths(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-md' => true,
            '--output' => '/tmp/alias-map.md',
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('absolute_output_path_not_allowed', $output);
    }

    public function test_command_rejects_output_path_extension_mismatch_for_export_mode(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-csv' => true,
            '--output' => 'reports/alias-map.md',
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('output_extension_mismatch', $output);
    }

    public function test_command_rejects_output_path_traversal_segments(): void
    {
        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-csv' => true,
            '--output' => '../alias-map.csv',
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('output_path_traversal_not_allowed', $output);
    }

    public function test_command_rejects_overwriting_existing_output_file_by_default(): void
    {
        $relativePath = 'feature-tests/existing-alias-map.csv';
        $absolutePath = storage_path('app/cms/component-library-alias-map-exports/'.$relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, "seed-content\n");

        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-csv' => true,
            '--output' => $relativePath,
        ]);

        $output = Artisan::output();

        $this->assertSame(1, $exitCode);
        $this->assertStringContainsString('output_file_exists', $output);
        $this->assertStringContainsString('Output target:', $output);
        $this->assertSame("seed-content\n", (string) File::get($absolutePath));

        File::delete($absolutePath);
    }

    public function test_command_can_overwrite_existing_output_file_when_overwrite_flag_is_enabled(): void
    {
        $relativePath = 'feature-tests/existing-alias-map.md';
        $absolutePath = storage_path('app/cms/component-library-alias-map-exports/'.$relativePath);

        File::ensureDirectoryExists(dirname($absolutePath));
        File::put($absolutePath, "old markdown\n");

        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-md' => true,
            '--output' => $relativePath,
            '--overwrite' => true,
        ]);

        $output = Artisan::output();
        $fileContents = (string) File::get($absolutePath);

        $this->assertSame(0, $exitCode);
        $this->assertStringContainsString('Exported component-library alias map report.', $output);
        $this->assertStringContainsString('Format: md', $output);
        $this->assertStringContainsString('Overwrite: yes', $output);
        $this->assertStringContainsString('# Component Library Alias Map Export (v1)', $fileContents);
        $this->assertStringNotContainsString('old markdown', $fileContents);

        File::delete($absolutePath);
    }
}
