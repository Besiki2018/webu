<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibrarySpecEquivalenceAliasMapSchemaTest extends TestCase
{
    public function test_component_library_equivalence_alias_map_v1_schema_exists_and_matches_alias_map_artifact_shape(): void
    {
        $schemaPath = base_path('docs/architecture/schemas/cms-component-library-spec-equivalence-alias-map.v1.schema.json');
        $aliasMapPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP_V1.md');

        foreach ([$schemaPath, $aliasMapPath, $docPath] as $path) {
            $this->assertFileExists($path);
        }

        $schema = $this->readJson($schemaPath);
        $aliasMap = $this->readJson($aliasMapPath);
        $doc = File::get($docPath);

        $this->assertSame('object', $schema['type'] ?? null);
        $this->assertFalse((bool) ($schema['additionalProperties'] ?? true));
        $this->assertSame(
            ['version', 'source_spec_ref', 'gap_audit_ref', 'coverage_status', 'mapping_count', 'mappings'],
            $schema['required'] ?? null
        );
        $this->assertSame('v1', data_get($schema, 'properties.version.const'));
        $this->assertSame(
            'Install/docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md',
            data_get($schema, 'properties.gap_audit_ref.const')
        );
        $this->assertContains('complete_exact_plus_equivalent', (array) data_get($schema, 'properties.coverage_status.enum'));
        $this->assertSame(['source_component_key', 'coverage', 'canonical_builder_keys'], data_get($schema, '$defs.mappingRow.required'));
        $this->assertContains('equivalent', (array) data_get($schema, '$defs.mappingRow.properties.coverage.enum'));
        $this->assertSame('^[a-z]+\\.[a-zA-Z][\\w]*$', data_get($schema, '$defs.mappingRow.properties.source_component_key.pattern'));
        $this->assertSame('^[a-z0-9_]+$', data_get($schema, '$defs.mappingRow.properties.canonical_builder_keys.items.pattern'));

        $this->assertSame('v1', $aliasMap['version'] ?? null);
        $this->assertSame('complete_exact_plus_equivalent', $aliasMap['coverage_status'] ?? null);
        $this->assertSame(70, $aliasMap['mapping_count'] ?? null);
        $this->assertCount(70, $aliasMap['mappings'] ?? []);

        foreach ((array) ($aliasMap['mappings'] ?? []) as $row) {
            $this->assertIsArray($row);
            $this->assertMatchesRegularExpression('/^[a-z]+\.[a-zA-Z][\w]*$/', (string) ($row['source_component_key'] ?? ''));
            $this->assertContains((string) ($row['coverage'] ?? ''), ['exact', 'equivalent', 'partial', 'missing']);

            $canonicalKeys = $row['canonical_builder_keys'] ?? null;
            $this->assertIsArray($canonicalKeys);
            $this->assertNotEmpty($canonicalKeys);
            foreach ($canonicalKeys as $canonicalKey) {
                $this->assertMatchesRegularExpression('/^[a-z0-9_]+$/', (string) $canonicalKey);
            }
        }

        foreach ([
            'cms-component-library-spec-equivalence-alias-map.v1.schema.json',
            'JSON alias map',
            'Runtime alias resolver helper',
            'CLI validate/debug command',
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

