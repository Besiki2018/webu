<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class BackendBuilderComponentAutoGeneratorEngineCoreContractCag01SyncTest extends TestCase
{
    public function test_cag_01_audit_doc_locks_component_auto_generator_core_factory_truth_and_gaps(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_ENGINE_CORE_CONTRACT_AUDIT_CAG_01_2026_02_25.md');

        $factoryServicePath = base_path('app/Services/CmsAiComponentFactoryGenerator.php');
        $genericParserServicePath = base_path('app/Services/CmsAiFeatureSpecParser.php');
        $componentParserServicePath = base_path('app/Services/CmsAiComponentFeatureSpecParser.php');
        $rendererTemplateServicePath = base_path('app/Services/CmsAiRendererTemplateGenerationService.php');
        $securityValidatorServicePath = base_path('app/Services/CmsAiGeneratedComponentSecurityValidationService.php');
        $integrationWorkflowServicePath = base_path('app/Services/CmsAiComponentRegistryIntegrationWorkflowService.php');

        $factoryDocPath = base_path('docs/architecture/CMS_AI_COMPONENT_FACTORY_GENERATOR_V1.md');
        $genericParserDocPath = base_path('docs/architecture/CMS_AI_FEATURE_SPEC_PARSER_V1.md');
        $componentParserDocPath = base_path('docs/architecture/CMS_AI_COMPONENT_FEATURE_SPEC_PARSER_V1.md');
        $rendererDocPath = base_path('docs/architecture/CMS_AI_RENDERER_TEMPLATE_GENERATION_V1.md');
        $securityDocPath = base_path('docs/architecture/CMS_AI_GENERATED_COMPONENT_SECURITY_CONSTRAINTS_V1.md');
        $integrationDocPath = base_path('docs/architecture/CMS_AI_COMPONENT_REGISTRY_INTEGRATION_WORKFLOW_V1.md');

        $registrySchemaPath = base_path('docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json');
        $pageNodeSchemaPath = base_path('docs/architecture/schemas/cms-canonical-page-node.v1.schema.json');
        $featureSpecSchemaPath = base_path('docs/architecture/schemas/cms-ai-feature-spec.v1.schema.json');
        $componentFeatureSpecSchemaPath = base_path('docs/architecture/schemas/cms-ai-component-feature-spec.v1.schema.json');

        $factoryTestPath = base_path('tests/Unit/CmsAiComponentFactoryGeneratorTest.php');
        $genericParserTestPath = base_path('tests/Unit/CmsAiFeatureSpecParserTest.php');
        $componentParserTestPath = base_path('tests/Unit/CmsAiComponentFeatureSpecParserTest.php');
        $rendererTemplateTestPath = base_path('tests/Unit/CmsAiRendererTemplateGenerationServiceTest.php');
        $securityValidatorTestPath = base_path('tests/Unit/CmsAiGeneratedComponentSecurityValidationServiceTest.php');
        $integrationWorkflowTestPath = base_path('tests/Unit/CmsAiComponentRegistryIntegrationWorkflowServiceTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $factoryServicePath,
            $genericParserServicePath,
            $componentParserServicePath,
            $rendererTemplateServicePath,
            $securityValidatorServicePath,
            $integrationWorkflowServicePath,
            $factoryDocPath,
            $genericParserDocPath,
            $componentParserDocPath,
            $rendererDocPath,
            $securityDocPath,
            $integrationDocPath,
            $registrySchemaPath,
            $pageNodeSchemaPath,
            $featureSpecSchemaPath,
            $componentFeatureSpecSchemaPath,
            $factoryTestPath,
            $genericParserTestPath,
            $componentParserTestPath,
            $rendererTemplateTestPath,
            $securityValidatorTestPath,
            $integrationWorkflowTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $factoryService = File::get($factoryServicePath);
        $genericParserService = File::get($genericParserServicePath);
        $componentParserService = File::get($componentParserServicePath);
        $rendererTemplateService = File::get($rendererTemplateServicePath);
        $securityValidatorService = File::get($securityValidatorServicePath);
        $integrationWorkflowService = File::get($integrationWorkflowServicePath);

        $factoryDoc = File::get($factoryDocPath);
        $genericParserDoc = File::get($genericParserDocPath);
        $componentParserDoc = File::get($componentParserDocPath);
        $rendererDoc = File::get($rendererDocPath);
        $securityDoc = File::get($securityDocPath);
        $integrationDoc = File::get($integrationDocPath);

        $registrySchema = File::get($registrySchemaPath);
        $pageNodeSchema = File::get($pageNodeSchemaPath);
        $featureSpecSchema = File::get($featureSpecSchemaPath);
        $componentFeatureSpecSchema = File::get($componentFeatureSpecSchemaPath);

        $factoryTest = File::get($factoryTestPath);
        $genericParserTest = File::get($genericParserTestPath);
        $componentParserTest = File::get($componentParserTestPath);
        $rendererTemplateTest = File::get($rendererTemplateTestPath);
        $securityValidatorTest = File::get($securityValidatorTestPath);
        $integrationWorkflowTest = File::get($integrationWorkflowTestPath);

        // Source prompt anchors (CAG-01 scope).
        foreach ([
            'CODEX PROMPT — Webu Component Auto-Generator Engine (Backend → Builder Component Factory)',
            '0.1 Feature Definition (canonical input)',
            '0.2 Component Library Constraints',
            '1) Outputs',
            '2) Generation Rules',
            '2.8 Renderer generation rules',
            '2.9 Error mapping',
            '2.10 Security constraints',
            '3) Component Templates Autoplacement (optional)',
            '4) Example Generation',
            '5) Validation & Tests (must)',
            '6) Deliverables (must)',
            '7) Acceptance Criteria',
            'webu generate-component --spec feature.json',
            'POST /admin/dev/generate-component (admin-only)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        // Backlog closure + evidence links.
        $this->assertStringContainsString('- `CAG-01` (`DONE`, `P0`)', $backlog);
        $this->assertStringContainsString('WEBU_BACKEND_BUILDER_COMPONENT_AUTO_GENERATOR_ENGINE_CORE_CONTRACT_AUDIT_CAG_01_2026_02_25.md', $backlog);
        $this->assertStringContainsString('BackendBuilderComponentAutoGeneratorEngineCoreContractCag01SyncTest.php', $backlog);

        // Audit doc structure and truthful findings.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:3995',
            'PROJECT_ROADMAP_TASKS_KA.md:4233',
            '## Executive Result (`CAG-01`)',
            '`CAG-01` is **complete as an audit/verification task**',
            'structured artifact arrays',
            'ComponentPackage',
            'index.ts',
            'renderer.tsx',
            'tests.spec.ts',
            'ecom.<feature_key>.<component>',
            'feature-<feature_key>-<component_key>',
            '## Spec `2.9` Error Mapping Audit',
            'UNAUTHORIZED',
            'RATE_LIMITED',
            'Result: `gap`',
            '## Spec `2.10` Security Constraints Audit',
            'pre-activation gate',
            'tenant_id',
            'store_id',
            '## Spec `6` Deliverables (CLI / Admin Endpoint) Audit',
            'no such CLI command or admin endpoint was found',
            '## DoD Verdict (`CAG-01`)',
            'audit/verification task',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        $docAnchors = [
            'Install/app/Services/CmsAiComponentFactoryGenerator.php',
            'Install/app/Services/CmsAiFeatureSpecParser.php',
            'Install/app/Services/CmsAiComponentFeatureSpecParser.php',
            'Install/app/Services/CmsAiRendererTemplateGenerationService.php',
            'Install/app/Services/CmsAiGeneratedComponentSecurityValidationService.php',
            'Install/app/Services/CmsAiComponentRegistryIntegrationWorkflowService.php',
            'Install/docs/architecture/CMS_AI_COMPONENT_FACTORY_GENERATOR_V1.md',
            'Install/docs/architecture/CMS_AI_FEATURE_SPEC_PARSER_V1.md',
            'Install/docs/architecture/CMS_AI_COMPONENT_FEATURE_SPEC_PARSER_V1.md',
            'Install/docs/architecture/CMS_AI_RENDERER_TEMPLATE_GENERATION_V1.md',
            'Install/docs/architecture/CMS_AI_GENERATED_COMPONENT_SECURITY_CONSTRAINTS_V1.md',
            'Install/docs/architecture/CMS_AI_COMPONENT_REGISTRY_INTEGRATION_WORKFLOW_V1.md',
            'Install/docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json',
            'Install/docs/architecture/schemas/cms-canonical-page-node.v1.schema.json',
            'Install/docs/architecture/schemas/cms-ai-feature-spec.v1.schema.json',
            'Install/docs/architecture/schemas/cms-ai-component-feature-spec.v1.schema.json',
            'Install/tests/Unit/CmsAiComponentFactoryGeneratorTest.php',
            'Install/tests/Unit/CmsAiFeatureSpecParserTest.php',
            'Install/tests/Unit/CmsAiComponentFeatureSpecParserTest.php',
            'Install/tests/Unit/CmsAiRendererTemplateGenerationServiceTest.php',
            'Install/tests/Unit/CmsAiGeneratedComponentSecurityValidationServiceTest.php',
            'Install/tests/Unit/CmsAiComponentRegistryIntegrationWorkflowServiceTest.php',
        ];

        foreach ($docAnchors as $relativePath) {
            $this->assertStringContainsString($relativePath, $doc, "Missing CAG-01 doc anchor: {$relativePath}");
            $this->assertFileExists(base_path('../'.$relativePath), "Missing CAG-01 evidence file on disk: {$relativePath}");
        }

        // Core generator truths (artifact-based generator, canonical props buckets, naming/category logic).
        $this->assertStringContainsString('public function generateFromCanonicalSpec(array $featureSpec, array $options = []): array', $factoryService);
        $this->assertStringContainsString('public function generateFromRawSpec(array $rawFeatureSpec, array $options = []): array', $factoryService);
        $this->assertStringContainsString('\'registry_entries\' => $registryEntries', $factoryService);
        $this->assertStringContainsString('\'node_scaffolds\' => $nodeScaffolds', $factoryService);
        $this->assertStringContainsString('\'renderer_scaffolds\' => $rendererScaffolds', $factoryService);
        $this->assertStringContainsString("'required' => ['content', 'data', 'style', 'advanced', 'responsive', 'states']", $factoryService);
        $this->assertStringContainsString('private function registryTypeForComponent(string $featureKey, string $componentKey): string', $factoryService);
        $this->assertStringContainsString("return 'feature-'.trim(", $factoryService);
        $this->assertStringContainsString('private function fallbackCategoryForDomain(string $domain): string', $factoryService);
        $this->assertStringContainsString('\'supports_dynamic_bindings\' => $supportsDynamicBindings', $factoryService);

        // Parser handoff truths (current generic parser + source-spec-focused component parser coexist).
        $this->assertStringContainsString('class CmsAiFeatureSpecParser', $genericParserService);
        $this->assertStringContainsString('public function parse(array $input): array', $genericParserService);
        $this->assertStringContainsString('class CmsAiComponentFeatureSpecParser', $componentParserService);
        $this->assertStringContainsString('public function parse(mixed $payload, array $options = []): array', $componentParserService);
        $this->assertStringContainsString('component_set', $componentParserService);
        $this->assertStringContainsString('generator_hints', $componentParserService);

        // Renderer/security/preflight constraints truth.
        $this->assertStringContainsString('public function generateFromComponentFactoryResult(array $factoryResult, array $options = []): array', $rendererTemplateService);
        $this->assertStringContainsString('data-webu-ai-template-generated="1"', $rendererTemplateService);
        $this->assertStringContainsString('missing_root_marker', $rendererTemplateService);
        $this->assertStringContainsString('missing_binding_marker', $rendererTemplateService);

        $this->assertStringContainsString('public function validateComponentBundle(array $componentArtifact, ?array $rendererTemplate): array', $securityValidatorService);
        $this->assertStringContainsString('unsafe_template_ref_prefix', $securityValidatorService);
        $this->assertStringContainsString('unsafe_binding_value', $securityValidatorService);
        $this->assertStringContainsString('unsafe_custom_css_import', $securityValidatorService);
        $this->assertStringContainsString('unsafe_renderer_html_script_tag', $securityValidatorService);

        $this->assertStringContainsString('public function prepareActivationFromRawFeatureSpec(array $rawFeatureSpec, array $options = []): array', $integrationWorkflowService);
        $this->assertStringContainsString("'activation_mode' => 'preflight_only'", $integrationWorkflowService);
        $this->assertStringContainsString("'ready_for_activation'", $integrationWorkflowService);
        $this->assertStringContainsString("'blocked'", $integrationWorkflowService);

        // Architecture docs and canonical contract references.
        $this->assertStringContainsString('# CMS AI Component Factory Generator v1', $factoryDoc);
        $this->assertStringContainsString('P4-E4-02', $factoryDoc);
        $this->assertStringContainsString('registry_entry', $factoryDoc);
        $this->assertStringContainsString('node_scaffold', $factoryDoc);
        $this->assertStringContainsString('renderer_scaffold', $factoryDoc);
        $this->assertStringContainsString('P4-E4-03', $factoryDoc);
        $this->assertStringContainsString('P4-E4-04', $factoryDoc);

        $this->assertStringContainsString('# CMS AI Feature Spec Parser v1', $genericParserDoc);
        $this->assertStringContainsString('CmsAiFeatureSpecParser::parse', $genericParserDoc);
        $this->assertStringContainsString('P4-E4-02', $genericParserDoc);

        $this->assertStringContainsString('# CMS AI Component Feature Spec Parser v1', $componentParserDoc);
        $this->assertStringContainsString('ecom.<feature_key>.<component>', $componentParserDoc);
        $this->assertStringContainsString('P4-E4-02', $componentParserDoc);

        $this->assertStringContainsString('# CMS AI Renderer Template Generation v1', $rendererDoc);
        $this->assertStringContainsString('data-bind-binding', $rendererDoc);
        $this->assertStringContainsString('data-bind-query-resource', $rendererDoc);

        $this->assertStringContainsString('# CMS AI Generated Component Security Constraints v1', $securityDoc);
        $this->assertStringContainsString('pre-activation gate', $securityDoc);
        $this->assertStringContainsString('not a runtime sanitizer', $securityDoc);

        $this->assertStringContainsString('# CMS AI Component Registry Integration Workflow v1', $integrationDoc);
        $this->assertStringContainsString('ready_for_activation', $integrationDoc);
        $this->assertStringContainsString('blocked', $integrationDoc);
        $this->assertStringContainsString('preflight_only', $integrationDoc);

        foreach (['"type"', '"properties"'] as $needle) {
            $this->assertStringContainsString($needle, $registrySchema);
            $this->assertStringContainsString($needle, $pageNodeSchema);
            $this->assertStringContainsString($needle, $featureSpecSchema);
            $this->assertStringContainsString($needle, $componentFeatureSpecSchema);
        }

        // Unit coverage anchors (truth + drift/gap evidence lives in tests/docs, not only services).
        $this->assertStringContainsString('test_it_generates_registry_entries_node_scaffolds_and_renderer_scaffolds_from_raw_feature_spec', $factoryTest);
        $this->assertStringContainsString('test_it_rejects_invalid_canonical_feature_spec_before_generation', $factoryTest);
        $this->assertStringContainsString('feature-wishlist-', $factoryTest);
        $this->assertStringContainsString("'ui_states' => ['ready', 'loading', 'empty', 'error']", $factoryTest);

        $this->assertStringContainsString('test_it_parses_alias_heavy_wishlist_feature_spec_into_canonical_v1_shape', $genericParserTest);
        $this->assertStringContainsString('CmsAiFeatureSpecParser', $genericParserTest);

        $this->assertStringContainsString('test_it_parses_ui_intent_feature_spec_into_canonical_auto_generator_contract', $componentParserTest);
        $this->assertStringContainsString('ecom.wishlist.list', $componentParserTest);
        $this->assertStringContainsString('ecom.compare.table', $componentParserTest);

        $this->assertStringContainsString('test_it_generates_renderer_templates_and_validation_reports_from_component_factory_result', $rendererTemplateTest);
        $this->assertStringContainsString('missing_binding_marker', $rendererTemplateTest);

        $this->assertStringContainsString('test_it_accepts_safe_generated_component_bundle', $securityValidatorTest);
        $this->assertStringContainsString('unsafe_renderer_html_script_tag', $securityValidatorTest);

        $this->assertStringContainsString('test_it_prepares_ready_for_activation_bundles_only_after_preflight_validators_pass', $integrationWorkflowTest);
        $this->assertStringContainsString('test_it_blocks_activation_when_security_validation_fails_even_if_renderer_markers_validate', $integrationWorkflowTest);
    }
}
