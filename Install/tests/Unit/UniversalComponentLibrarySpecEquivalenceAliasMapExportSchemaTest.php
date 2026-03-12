<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibrarySpecEquivalenceAliasMapExportSchemaTest extends TestCase
{
    public function test_component_library_equivalence_alias_map_export_v1_schema_exists_and_matches_json_export_shape(): void
    {
        $schemaPath = base_path('docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map-export.v1.schema.json');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP_V1.md');

        $this->assertFileExists($schemaPath);
        $this->assertFileExists($docPath);

        $schema = $this->readJson($schemaPath);
        $doc = File::get($docPath);

        $this->assertSame('object', $schema['type'] ?? null);
        $this->assertFalse((bool) ($schema['additionalProperties'] ?? true));
        $this->assertSame(['summary', 'stats', 'fingerprints', 'mappings'], $schema['required'] ?? null);
        $this->assertSame('v1', data_get($schema, 'properties.summary.properties.version.const'));
        $this->assertSame(
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md',
            data_get($schema, 'properties.summary.properties.gap_audit_ref.const')
        );
        $this->assertSame('complete_exact_plus_equivalent', data_get($schema, 'properties.summary.properties.coverage_status.const'));
        $this->assertSame(70, data_get($schema, 'properties.summary.properties.mapping_count.const'));
        $this->assertSame(70, data_get($schema, 'properties.stats.properties.rows.const'));
        $this->assertSame(['source_component_key', 'coverage', 'canonical_builder_keys'], data_get($schema, '$defs.mappingRow.required'));

        $exitCode = Artisan::call('cms:component-library-alias-map-validate', [
            '--export-json' => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exitCode);
        $exportPayload = json_decode($output, true);
        $this->assertIsArray($exportPayload);

        $this->assertArrayHasKey('summary', $exportPayload);
        $this->assertArrayHasKey('stats', $exportPayload);
        $this->assertArrayHasKey('fingerprints', $exportPayload);
        $this->assertArrayHasKey('mappings', $exportPayload);

        $this->assertSame('v1', data_get($exportPayload, 'summary.version'));
        $this->assertSame('complete_exact_plus_equivalent', data_get($exportPayload, 'summary.coverage_status'));
        $this->assertSame(70, data_get($exportPayload, 'summary.mapping_count'));
        $this->assertSame(70, data_get($exportPayload, 'stats.rows'));
        $this->assertSame(70, count((array) data_get($exportPayload, 'mappings')));
        $this->assertTrue((bool) data_get($exportPayload, 'fingerprints.ok'));
        $this->assertMatchesRegularExpression('/^sha256:[a-f0-9]{64}$/', (string) data_get($exportPayload, 'fingerprints.map_fingerprint'));
        $this->assertMatchesRegularExpression('/^sha256:[a-f0-9]{64}$/', (string) data_get($exportPayload, 'fingerprints.schema_fingerprint'));
        $this->assertMatchesRegularExpression('/^sha256:[a-f0-9]{64}$/', (string) data_get($exportPayload, 'fingerprints.export_schema_fingerprint'));
        $this->assertMatchesRegularExpression('/^sha256:[a-f0-9]{64}$/', (string) data_get($exportPayload, 'fingerprints.export_content_fingerprint'));
        $this->assertMatchesRegularExpression('/^sha256:[a-f0-9]{64}$/', (string) data_get($exportPayload, 'fingerprints.artifact_bundle_fingerprint'));

        foreach ((array) data_get($exportPayload, 'mappings', []) as $row) {
            $this->assertIsArray($row);
            $this->assertMatchesRegularExpression('/^[a-z]+\.[a-zA-Z][\w]*$/', (string) ($row['source_component_key'] ?? ''));
            $this->assertContains((string) ($row['coverage'] ?? ''), ['exact', 'equivalent', 'partial', 'missing']);
            $canonicalKeys = $row['canonical_builder_keys'] ?? null;
            $this->assertIsArray($canonicalKeys);
            $this->assertNotEmpty($canonicalKeys);
        }

        foreach ([
            'cms-component-library-spec-equivalence-alias-map-export.v1.schema.json',
            '--export-json',
            'JSON export report',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function readJson(string $path): array
    {
        $decoded = json_decode(File::get($path), true);

        $this->assertIsArray($decoded, "Invalid JSON: {$path}");

        return $decoded;
    }
}
