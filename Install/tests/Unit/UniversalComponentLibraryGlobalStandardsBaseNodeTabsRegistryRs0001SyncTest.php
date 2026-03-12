<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryGlobalStandardsBaseNodeTabsRegistryRs0001SyncTest extends TestCase
{
    public function test_rs_00_01_audit_doc_locks_global_base_node_tabs_and_registry_ai_availability_baseline_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_BASE_NODE_TABS_REGISTRY_AUDIT_RS_00_01_2026_02_25.md');

        $pageNodeDocPath = base_path('docs/architecture/CMS_CANONICAL_PAGE_NODE_SCHEMA_V1.md');
        $registryDocPath = base_path('docs/architecture/CMS_CANONICAL_COMPONENT_REGISTRY_SCHEMA_V1.md');
        $controlMetadataDocPath = base_path('docs/architecture/CMS_CANONICAL_CONTROL_METADATA_V1.md');
        $activationDocPath = base_path('docs/architecture/UNIVERSAL_COMPONENT_LIBRARY_ACTIVATION_P5_F5_01_F5_02.md');
        $aiMappingDocPath = base_path('docs/architecture/UNIVERSAL_AI_INDUSTRY_COMPONENT_MAPPING_P5_F5_04.md');
        $tabsSummaryDocPath = base_path('docs/qa/CMS_PHASE3_PRIMARY_TABS_WRAPPER_SUMMARY.md');

        $pageNodeSchemaPath = base_path('docs/architecture/schemas/cms-canonical-page-node.v1.schema.json');
        $registrySchemaPath = base_path('docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json');
        $aiInputSchemaPath = base_path('docs/architecture/schemas/cms-ai-generation-input.v1.schema.json');
        $aiOutputSchemaPath = base_path('docs/architecture/schemas/cms-ai-generation-output.v1.schema.json');

        $canonicalSchemaContractsTestPath = base_path('tests/Unit/CmsCanonicalSchemaContractsTest.php');
        $tabsSummarySyncTestPath = base_path('tests/Unit/Phase3PrimaryTabsWrapperSummaryStatusSyncTest.php');
        $tabsFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsPhase3PrimaryTabsWrapperSummary.contract.test.ts');
        $activationFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts');
        $activationUnitLockPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');
        $bindingCompatFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsUniversalBindingNamespaceCompatibility.contract.test.ts');
        $aiMappingServiceTestPath = base_path('tests/Unit/CmsAiIndustryComponentMappingServiceTest.php');
        $aiPageGenerationServiceTestPath = base_path('tests/Unit/CmsAiPageGenerationServiceTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $pageNodeDocPath,
            $registryDocPath,
            $controlMetadataDocPath,
            $activationDocPath,
            $aiMappingDocPath,
            $tabsSummaryDocPath,
            $pageNodeSchemaPath,
            $registrySchemaPath,
            $aiInputSchemaPath,
            $aiOutputSchemaPath,
            $canonicalSchemaContractsTestPath,
            $tabsSummarySyncTestPath,
            $tabsFrontendContractPath,
            $activationFrontendContractPath,
            $activationUnitLockPath,
            $bindingCompatFrontendContractPath,
            $aiMappingServiceTestPath,
            $aiPageGenerationServiceTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $pageNodeDoc = File::get($pageNodeDocPath);
        $registryDoc = File::get($registryDocPath);
        $controlMetadataDoc = File::get($controlMetadataDocPath);
        $activationDoc = File::get($activationDocPath);
        $aiMappingDoc = File::get($aiMappingDocPath);
        $tabsSummaryDoc = File::get($tabsSummaryDocPath);

        $pageNodeSchema = File::get($pageNodeSchemaPath);
        $registrySchema = File::get($registrySchemaPath);
        $aiInputSchema = File::get($aiInputSchemaPath);
        $aiOutputSchema = File::get($aiOutputSchemaPath);

        $canonicalSchemaContractsTest = File::get($canonicalSchemaContractsTestPath);
        $tabsSummarySyncTest = File::get($tabsSummarySyncTestPath);
        $tabsFrontendContract = File::get($tabsFrontendContractPath);
        $activationFrontendContract = File::get($activationFrontendContractPath);
        $activationUnitLock = File::get($activationUnitLockPath);
        $bindingCompatFrontendContract = File::get($bindingCompatFrontendContractPath);
        $aiMappingServiceTest = File::get($aiMappingServiceTestPath);
        $aiPageGenerationServiceTest = File::get($aiPageGenerationServiceTestPath);

        foreach ([
            '# 0) Global Standards (applies to ALL components)',
            '## 0.1 Base Node Schema',
            '## 0.2 Default Tabs (Elementor-like)',
            'Every component node:',
            '- id (uuid)',
            '- type (string)',
            '- children (array)',
            '- props:',
            '- bindings {} (optional)',
            '- meta { locked, hidden, name }',
            'Every component panel has:',
            '- Content',
            '- Style',
            '- Advanced',
            'All components must be generated/registered in Builder Library and available to AI Generator.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        // Backlog closure + evidence/icon notes.
        foreach ([
            '- `RS-00-01` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_BASE_NODE_TABS_REGISTRY_AUDIT_RS_00_01_2026_02_25.md',
            'UniversalComponentLibraryGlobalStandardsBaseNodeTabsRegistryRs0001SyncTest.php',
            'CMS_CANONICAL_PAGE_NODE_SCHEMA_V1.md',
            'CMS_PHASE3_PRIMARY_TABS_WRAPPER_SUMMARY.md',
            'CmsCanonicalSchemaContractsTest.php',
            'CmsUniversalComponentLibraryActivation.contract.test.ts',
            'CmsAiPageGenerationServiceTest.php',
            '`✅` base node schema source contract audited against canonical page-node schema (with explicit drift notes)',
            '`✅` default `Content/Style/Advanced` panel tabs verified via shared wrapper evidence lock',
            '`✅` builder library registration baseline and AI generator availability path evidenced',
            '`✅` field-level gap list documented for `props.*`, `bindings`, and `meta`',
            '`🧪` targeted RS-00-01 sync lock added',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        // Doc structure and truth claims.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:6452',
            'PROJECT_ROADMAP_TASKS_KA.md:6456',
            'PROJECT_ROADMAP_TASKS_KA.md:6477',
            '## ✅ What Was Done (Icon Summary)',
            '## Executive Result (`RS-00-01`)',
            '`RS-00-01` is **complete as an audit/verification task**',
            '## Base Node Schema Contract Audit (Source `0.1`)',
            '### Required Node Fields / Tabs Pass-Fail Matrix',
            '### Gap List by Field (Deliverable Requirement)',
            '## Builder Panel Default Tabs Parity Verification (Source `0.2`)',
            '## Builder Library Registration + AI Generator Availability Baseline',
            '### Builder Library Registration Baseline (Builder-side)',
            '### AI Generator Availability Path Baseline (AI-side)',
            '## DoD Verdict (`RS-00-01`)',
            '## Follow-up (Non-Blocking, Outside `RS-00-01` Audit Completion)',
            '## Conclusion',
            'id` is optional and not UUID-only',
            'children` is optional',
            'meta.name` is represented as `meta.label`',
            'bindings` key is required as an object',
            'global base node schema is mostly implemented with explicit exactness notes',
            'default `Content / Style / Advanced` tabs parity is evidence-locked',
            'builder library registration + AI generator availability baseline is evidenced end-to-end',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        // Base-node matrix rows must explicitly mention all requested grouped fields plus bindings/meta.
        foreach ([
            'props.content',
            'props.data',
            'props.style',
            'props.advanced',
            'props.responsive',
            'props.states',
            'bindings',
            'meta',
            'pass',
            'equivalent',
            'partial',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        // Page-node schema anchors for the audit truth.
        foreach ([
            '"required": [',
            '"type",',
            '"props",',
            '"bindings",',
            '"meta"',
            '"id": {',
            '"type": [',
            '"string",',
            '"integer"',
            '"children": {',
            '"items": {',
            '"$ref": "#"',
            '"content",',
            '"data",',
            '"style",',
            '"advanced",',
            '"responsive",',
            '"states"',
            '"schema_version"',
            '"label"',
            '"locked"',
            '"hidden"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $pageNodeSchema);
        }

        // Registry schema / AI schema contract anchors support builder+AI baseline statements.
        $this->assertStringContainsString('"controls_config"', $registrySchema);
        $this->assertStringContainsString('canonical_component_registry_schema', $aiInputSchema);
        $this->assertStringContainsString('canonical_page_node_schema', $aiInputSchema);
        $this->assertStringContainsString('cms-canonical-page-node.v1.schema.json', $aiOutputSchema);

        // Existing docs and tests referenced by the audit must expose the claimed anchors.
        $this->assertStringContainsString('Canonical v1 Shape', $pageNodeDoc);
        $this->assertStringContainsString('bindings', $pageNodeDoc);
        $this->assertStringContainsString('meta', $pageNodeDoc);

        $this->assertStringContainsString('Required Fields (v1)', $registryDoc);
        $this->assertStringContainsString('controls_config', $registryDoc);

        $this->assertStringContainsString('Canonical Fields', $controlMetadataDoc);
        $this->assertStringContainsString('bindings', $controlMetadataDoc);
        $this->assertStringContainsString('meta', $controlMetadataDoc);

        foreach ([
            'buildCanonicalPrimaryPanelTabFieldSetBuckets(...)',
            'renderCanonicalControlGroupFieldSets(...)',
            'builder-control-panel-primary-tab-trigger',
            'selected page section editor controls',
            'fixed header/footer editor controls',
        ] as $needle) {
            $this->assertStringContainsString($needle, $tabsSummaryDoc);
        }

        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:154', $tabsSummarySyncTest);
        $this->assertStringContainsString('builder-control-panel-primary-tab-trigger', $tabsFrontendContract);

        foreach ([
            'BUILDER_UNIVERSAL_TAXONOMY_GROUP_ORDER',
            'builderSectionAvailabilityMatrix',
            'isBuilderSectionAllowedByProjectTypeAvailabilityMatrix',
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationDoc);
            $this->assertStringContainsString($needle, $activationFrontendContract);
            $this->assertStringContainsString($needle, $activationUnitLock);
        }

        foreach ([
            'CmsAiIndustryComponentMappingService',
            'CmsAiPageGenerationService',
            'taxonomy_groups',
            'component_keys',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aiMappingDoc);
        }

        // Core schema/test anchors for node props + AI schema chain.
        foreach ([
            'test_page_node_v1_schema_exists_and_requires_canonical_prop_groups',
            "['type', 'props', 'bindings', 'meta']",
            "['content', 'data', 'style', 'advanced', 'responsive', 'states']",
            'test_ai_generation_output_v1_schema_exists_and_requires_strict_builder_native_artifacts',
            'canonical_component_registry_schema',
            'canonical_page_node_schema',
        ] as $needle) {
            $this->assertStringContainsString($needle, $canonicalSchemaContractsTest);
        }

        // bindings/meta builder surface contract anchor.
        $this->assertStringContainsString("'bindings' | 'meta'", $bindingCompatFrontendContract);

        // AI mapping/page-generation baseline anchors.
        foreach ([
            'source_spec_component_keys',
            'source_spec_alias_coverage',
            'test_it_covers_component_library_source_spec_prompt_to_industry_mapping_examples',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aiMappingServiceTest);
        }

        foreach ([
            'page_plan.ai_industry_component_mapping',
            "['content', 'data', 'style', 'advanced', 'responsive', 'states']",
            '{{route.params.slug}}',
            'validateOutputPayload',
            'cms-canonical-page-node.v1',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aiPageGenerationServiceTest);
        }
    }
}
