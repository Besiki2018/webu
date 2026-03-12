<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class BackendBuilderComponentAutoGeneratorFeatureSpecStrictFormatCag02SyncTest extends TestCase
{
    public function test_cag_02_audit_doc_locks_feature_spec_strict_format_parser_compatibility_truth_and_gaps(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_FEATURE_SPEC_STRICT_FORMAT_COMPATIBILITY_AUDIT_CAG_02_2026_02_25.md');

        $parserServicePath = base_path('app/Services/CmsAiComponentFeatureSpecParser.php');
        $factoryServicePath = base_path('app/Services/CmsAiComponentFactoryGenerator.php');
        $schemaPath = base_path('docs/architecture/schemas/cms-ai-component-feature-spec.v1.schema.json');
        $registrySchemaPath = base_path('docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json');
        $pageNodeSchemaPath = base_path('docs/architecture/schemas/cms-canonical-page-node.v1.schema.json');
        $parserDocPath = base_path('docs/architecture/CMS_AI_COMPONENT_FEATURE_SPEC_PARSER_V1.md');
        $factoryDocPath = base_path('docs/architecture/CMS_AI_COMPONENT_FACTORY_GENERATOR_V1.md');

        $parserTestPath = base_path('tests/Unit/CmsAiComponentFeatureSpecParserTest.php');
        $schemaContractsTestPath = base_path('tests/Unit/CmsCanonicalSchemaContractsTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $parserServicePath,
            $factoryServicePath,
            $schemaPath,
            $registrySchemaPath,
            $pageNodeSchemaPath,
            $parserDocPath,
            $factoryDocPath,
            $parserTestPath,
            $schemaContractsTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $parserService = File::get($parserServicePath);
        $factoryService = File::get($factoryServicePath);
        $schema = File::get($schemaPath);
        $parserDoc = File::get($parserDocPath);
        $factoryDoc = File::get($factoryDocPath);

        $parserTest = File::get($parserTestPath);
        $schemaContractsTest = File::get($schemaContractsTestPath);

        // Source-spec anchors for CAG-02 slice.
        foreach ([
            'Any backend feature described using this spec can be automatically converted into:',
            '1. FEATURE SPEC STRUCTURE (STRICT FORMAT)',
            '2. FIELD EXPLANATION',
            'feature_key',
            'category',
            'context',
            'endpoints',
            'entities',
            'ui',
            'permissions',
            'events',
            'ecom.wishlist.list',
            '3. FEATURE SPEC EXAMPLE #1 — WISHLIST',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        // Backlog closure + evidence.
        $this->assertStringContainsString('- `CAG-02` (`DONE`, `P1`)', $backlog);
        $this->assertStringContainsString('WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_FEATURE_SPEC_STRICT_FORMAT_COMPATIBILITY_AUDIT_CAG_02_2026_02_25.md', $backlog);
        $this->assertStringContainsString('BackendBuilderComponentAutoGeneratorFeatureSpecStrictFormatCag02SyncTest.php', $backlog);

        // Audit doc structure + truthful findings.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:4336',
            'PROJECT_ROADMAP_TASKS_KA.md:4479',
            'Out of scope for `CAG-02`',
            '`CAG-03`',
            '## Executive Result (`CAG-02`)',
            '`CAG-02` is **complete as an audit/verification task**',
            'A strict JSON schema exists',
            'additionalProperties: false',
            'Parser compatibility is intentionally broader than the strict input example',
            'Invalid/partial payloads return explicit structured errors',
            'does not perform runtime JSON-schema validation',
            '## Capability Matrix (Source Spec Sections `1`-`2`)',
            'strict validation behavior',
            'gap / not implemented in parser',
            '## Strict-Format Validator Test Cases (Deliverable)',
            'test_it_reports_explicit_errors_for_partial_strict_format_field_types',
            '$.endpoints[0].response_shape',
            '## DoD Verdict (`CAG-02`)',
            'audit/verification task',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'Install/app/Services/CmsAiComponentFeatureSpecParser.php',
            'Install/app/Services/CmsAiComponentFactoryGenerator.php',
            'Install/docs/architecture/schemas/cms-ai-component-feature-spec.v1.schema.json',
            'Install/docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json',
            'Install/docs/architecture/schemas/cms-canonical-page-node.v1.schema.json',
            'Install/docs/architecture/CMS_AI_COMPONENT_FEATURE_SPEC_PARSER_V1.md',
            'Install/docs/architecture/CMS_AI_COMPONENT_FACTORY_GENERATOR_V1.md',
            'Install/tests/Unit/CmsAiComponentFeatureSpecParserTest.php',
            'Install/tests/Unit/CmsCanonicalSchemaContractsTest.php',
        ] as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Missing CAG-02 doc anchor: {$relativePath}");
            $this->assertFileExists(base_path('../'.$relativePath), "Missing CAG-02 evidence file on disk: {$relativePath}");
        }

        // Schema strictness and contract anchors.
        $this->assertStringContainsString('"additionalProperties": false', $schema);
        $this->assertStringContainsString('"required": [', $schema);
        $this->assertStringContainsString('"feature_key"', $schema);
        $this->assertStringContainsString('"ui_intent"', $schema);
        $this->assertStringContainsString('"generator_hints"', $schema);
        $this->assertStringContainsString('"meta"', $schema);
        $this->assertStringContainsString('"minItems": 1', $schema);
        $this->assertStringContainsString('"CmsAiComponentFeatureSpecParser"', $schema);
        $this->assertStringContainsString('"canonical_component_registry_schema"', $schema);
        $this->assertStringContainsString('"canonical_page_node_schema"', $schema);

        // Parser behavior and explicit error paths.
        $this->assertStringContainsString('public function parseJsonString(string $json, array $options = []): array', $parserService);
        $this->assertStringContainsString('public function parse(mixed $payload, array $options = []): array', $parserService);
        $this->assertStringContainsString('ui_intent.primary_component / ui_intent.secondary_components', $parserService);
        $this->assertStringContainsString('ui.primary / ui.secondary', $parserService);
        $this->assertStringContainsString('code: \'invalid_json\'', $parserService);
        $this->assertStringContainsString('code: \'invalid_type\'', $parserService);
        $this->assertStringContainsString('code: \'missing_required_key\'', $parserService);
        $this->assertStringContainsString('code: \'min_items\'', $parserService);
        $this->assertStringContainsString('path: \'$.endpoints\'', $parserService);
        $this->assertStringContainsString('At least one endpoint is required.', $parserService);
        $this->assertStringContainsString('Primary component intent is required', $parserService);
        $this->assertStringContainsString('\'code\' => \'invalid_feature_spec\'', $parserService);
        $this->assertStringContainsString('component_set', $parserService);
        $this->assertStringContainsString('generator_hints', $parserService);
        $this->assertStringContainsString('\'type\' => "{$namespace}.{$featureKey}.{$typeSegment}"', $parserService);

        // Downstream handoff and docs state.
        $this->assertStringContainsString('CmsAiFeatureSpecParser', $factoryService);
        $this->assertStringContainsString('# CMS AI Component Feature Spec Parser v1', $parserDoc);
        $this->assertStringContainsString('ui_intent', $parserDoc);
        $this->assertStringContainsString('`ui` alias style (legacy examples)', $parserDoc);
        $this->assertStringContainsString('structured `errors[]`', $parserDoc);
        $this->assertStringContainsString('ecom.<feature_key>.<component>', $parserDoc);
        $this->assertStringContainsString('P4-E4-02', $parserDoc);
        $this->assertStringContainsString('# CMS AI Component Factory Generator v1', $factoryDoc);
        $this->assertStringContainsString('P4-E4-02', $factoryDoc);

        // Test coverage anchors.
        $this->assertStringContainsString('test_it_parses_ui_intent_feature_spec_into_canonical_auto_generator_contract', $parserTest);
        $this->assertStringContainsString('test_it_parses_legacy_ui_alias_format_and_derives_endpoint_defaults_for_generator', $parserTest);
        $this->assertStringContainsString('test_it_reports_invalid_feature_specs_with_structured_errors', $parserTest);
        $this->assertStringContainsString('test_it_reports_invalid_json_when_parsing_json_strings', $parserTest);
        $this->assertStringContainsString('test_it_reports_explicit_errors_for_partial_strict_format_field_types', $parserTest);
        $this->assertStringContainsString('$.endpoints[0].query', $parserTest);
        $this->assertStringContainsString('$.ui_intent.primary_component', $parserTest);

        $this->assertStringContainsString('test_ai_component_feature_spec_v1_schema_exists_and_requires_endpoints_ui_intent_and_generator_hints', $schemaContractsTest);
        $this->assertStringContainsString('cms-ai-component-feature-spec.v1.schema.json', $schemaContractsTest);
        $this->assertStringContainsString('additionalProperties', $schemaContractsTest);
    }
}
