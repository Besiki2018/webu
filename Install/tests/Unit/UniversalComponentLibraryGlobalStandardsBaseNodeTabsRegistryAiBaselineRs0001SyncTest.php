<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryGlobalStandardsBaseNodeTabsRegistryAiBaselineRs0001SyncTest extends TestCase
{
    public function test_rs_00_01_audit_doc_locks_base_node_tabs_and_builder_registry_ai_baseline_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_BASE_NODE_TABS_REGISTRY_AI_BASELINE_AUDIT_RS_00_01_2026_02_25.md');

        $pageNodeDocPath = base_path('docs/architecture/CMS_CANONICAL_PAGE_NODE_SCHEMA_V1.md');
        $registryDocPath = base_path('docs/architecture/CMS_CANONICAL_COMPONENT_REGISTRY_SCHEMA_V1.md');
        $controlMetaDocPath = base_path('docs/architecture/CMS_CANONICAL_CONTROL_METADATA_V1.md');
        $pageNodeSchemaPath = base_path('docs/architecture/schemas/cms-canonical-page-node.v1.schema.json');
        $registrySchemaPath = base_path('docs/architecture/schemas/cms-canonical-component-registry-entry.v1.schema.json');
        $schemaContractsTestPath = base_path('tests/Unit/CmsCanonicalSchemaContractsTest.php');

        $tabsSummaryDocPath = base_path('docs/qa/CMS_PHASE3_PRIMARY_TABS_WRAPPER_SUMMARY.md');
        $tabsSummarySyncTestPath = base_path('tests/Unit/Phase3PrimaryTabsWrapperSummaryStatusSyncTest.php');
        $tabsSummaryFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsPhase3PrimaryTabsWrapperSummary.contract.test.ts');

        $activationDocPath = base_path('docs/architecture/UNIVERSAL_COMPONENT_LIBRARY_ACTIVATION_P5_F5_01_F5_02.md');
        $activationUnitTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');
        $activationFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts');

        $aiMappingDocPath = base_path('docs/architecture/UNIVERSAL_AI_INDUSTRY_COMPONENT_MAPPING_P5_F5_04.md');
        $aiMappingServiceTestPath = base_path('tests/Unit/CmsAiIndustryComponentMappingServiceTest.php');
        $aiPageGenerationServiceTestPath = base_path('tests/Unit/CmsAiPageGenerationServiceTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $pageNodeDocPath,
            $registryDocPath,
            $controlMetaDocPath,
            $pageNodeSchemaPath,
            $registrySchemaPath,
            $schemaContractsTestPath,
            $tabsSummaryDocPath,
            $tabsSummarySyncTestPath,
            $tabsSummaryFrontendContractPath,
            $activationDocPath,
            $activationUnitTestPath,
            $activationFrontendContractPath,
            $aiMappingDocPath,
            $aiMappingServiceTestPath,
            $aiPageGenerationServiceTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $pageNodeSchema = json_decode(File::get($pageNodeSchemaPath), true, flags: JSON_THROW_ON_ERROR);
        $registrySchema = json_decode(File::get($registrySchemaPath), true, flags: JSON_THROW_ON_ERROR);

        $schemaContractsTest = File::get($schemaContractsTestPath);
        $tabsSummaryDoc = File::get($tabsSummaryDocPath);
        $tabsSummarySyncTest = File::get($tabsSummarySyncTestPath);
        $tabsSummaryFrontendContract = File::get($tabsSummaryFrontendContractPath);
        $activationDoc = File::get($activationDocPath);
        $activationUnitTest = File::get($activationUnitTestPath);
        $activationFrontendContract = File::get($activationFrontendContractPath);
        $aiMappingDoc = File::get($aiMappingDocPath);
        $aiMappingServiceTest = File::get($aiMappingServiceTestPath);
        $aiPageGenerationServiceTest = File::get($aiPageGenerationServiceTestPath);

        foreach ([
            'All components must be generated/registered in Builder Library and available to AI Generator.',
            '# 0) Global Standards (applies to ALL components)',
            '## 0.1 Base Node Schema',
            'Every component node:',
            '- id (uuid)',
            '- type (string)',
            '- children (array)',
            '- bindings {} (optional)',
            '- meta { locked, hidden, name }',
            '## 0.2 Default Tabs (Elementor-like)',
            '- Content',
            '- Style',
            '- Advanced',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-00-01` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_BASE_NODE_TABS_REGISTRY_AI_BASELINE_AUDIT_RS_00_01_2026_02_25.md',
            'UniversalComponentLibraryGlobalStandardsBaseNodeTabsRegistryAiBaselineRs0001SyncTest.php',
            'CmsCanonicalSchemaContractsTest.php',
            'CMS_PHASE3_PRIMARY_TABS_WRAPPER_SUMMARY.md',
            'Phase3PrimaryTabsWrapperSummaryStatusSyncTest.php',
            'UniversalComponentLibraryActivationP5F5Test.php',
            'CmsUniversalComponentLibraryActivation.contract.test.ts',
            'CmsAiIndustryComponentMappingServiceTest.php',
            'CmsAiPageGenerationServiceTest.php',
            '`✅` base node schema source contract audited against canonical page-node schema (with explicit drift notes)',
            '`✅` default `Content/Style/Advanced` panel tabs verified via shared wrapper evidence lock',
            '`✅` builder library registration baseline and AI generator availability path evidenced',
            '`✅` field-level gap list documented for `props.*`, `bindings`, and `meta`',
            '`🧪` targeted RS-00-01 sync lock added',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:6452',
            'PROJECT_ROADMAP_TASKS_KA.md:6456',
            'PROJECT_ROADMAP_TASKS_KA.md:6477',
            '## ✅ What Was Done (Icon Summary)',
            '## Executive Result (`RS-00-01`)',
            '`RS-00-01` is **complete as an audit/verification task**.',
            '## 0.1 Base Node Schema — Pass/Partial Matrix (Source vs Canonical)',
            '## 0.2 Default Tabs (`Content / Style / Advanced`) — Parity Verification',
            '## Builder Library Registration + AI Generator Availability Baseline',
            '## DoD Verdict (`RS-00-01`)',
            '## Follow-up Notes (For Next Tasks)',
            '## Conclusion',
            '`id` strict UUID requirement',
            '`children` required-vs-optional semantics',
            '`meta.name` source wording vs current canonical `meta.label`',
            'pass (stricter)',
            'props.content',
            'props.data',
            'props.style',
            'props.advanced',
            'props.responsive',
            'props.states',
            'bindings',
            'meta',
            'CMS_PHASE3_PRIMARY_TABS_WRAPPER_SUMMARY.md',
            'UniversalComponentLibraryActivationP5F5Test.php',
            'CmsAiIndustryComponentMappingServiceTest.php',
            'CmsAiPageGenerationServiceTest.php',
            'builder-native page generation',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        // Canonical page-node schema truth used by the RS-00-01 matrix.
        $this->assertSame(['type', 'props', 'bindings', 'meta'], $pageNodeSchema['required'] ?? null);
        $this->assertSame(
            ['content', 'data', 'style', 'advanced', 'responsive', 'states'],
            data_get($pageNodeSchema, 'properties.props.required')
        );
        $this->assertSame(['string', 'integer'], data_get($pageNodeSchema, 'properties.id.type'));
        $this->assertNull(data_get($pageNodeSchema, 'properties.id.format'));
        $this->assertSame('array', data_get($pageNodeSchema, 'properties.children.type'));
        $this->assertSame(['schema_version'], data_get($pageNodeSchema, 'properties.meta.required'));
        $this->assertArrayHasKey('locked', data_get($pageNodeSchema, 'properties.meta.properties', []));
        $this->assertArrayHasKey('hidden', data_get($pageNodeSchema, 'properties.meta.properties', []));
        $this->assertArrayHasKey('label', data_get($pageNodeSchema, 'properties.meta.properties', []));
        $this->assertArrayNotHasKey('name', data_get($pageNodeSchema, 'properties.meta.properties', []));

        // Canonical registry schema baseline exists for builder registration contract context.
        $this->assertSame(
            ['type', 'category', 'props_schema', 'default_props', 'renderer', 'controls_config'],
            $registrySchema['required'] ?? null
        );
        $this->assertContains('frontend_builtin', data_get($registrySchema, 'properties.renderer.properties.kind.enum', []));
        $this->assertContains('adapter', data_get($registrySchema, 'properties.renderer.properties.kind.enum', []));

        // Existing contract locks should still contain the anchors this audit reuses.
        foreach ([
            "['type', 'props', 'bindings', 'meta']",
            "['content', 'data', 'style', 'advanced', 'responsive', 'states']",
            "properties.meta.properties.schema_version.type",
        ] as $needle) {
            $this->assertStringContainsString($needle, $schemaContractsTest);
        }

        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:154',
            'buildCanonicalPrimaryPanelTabFieldSetBuckets(...)',
            'renderCanonicalControlGroupFieldSets(...)',
            'builder-control-panel-primary-tab-trigger',
            'selected page section editor controls',
            'fixed header/footer editor controls',
        ] as $needle) {
            $this->assertStringContainsString($needle, $tabsSummaryDoc);
        }
        $this->assertStringContainsString('phase3_wrapper_primary_tabs_status_matches_shared_renderer_evidence', $tabsSummarySyncTest);
        $this->assertStringContainsString("type CanonicalPrimaryPanelTab = 'content' | 'style' | 'advanced';", $tabsSummaryFrontendContract);

        foreach ([
            'P5-F5-01',
            'P5-F5-02',
            'BuilderUniversalTaxonomyGroupKey',
            'BUILDER_UNIVERSAL_TAXONOMY_GROUP_ORDER',
            'builderSectionAvailabilityMatrix',
            'isBuilderSectionAllowedByProjectTypeAvailabilityMatrix',
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationDoc);
            $this->assertStringContainsString($needle, $activationUnitTest);
        }
        $this->assertStringContainsString('CMS universal component library activation contracts', $activationFrontendContract);

        foreach ([
            'P5-F5-04',
            'CmsAiIndustryComponentMappingService',
            'CmsAiPageGenerationService',
            'taxonomy_groups',
            'component_keys',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aiMappingDoc);
        }

        foreach ([
            'builder_component_mapping.taxonomy_groups',
            'builder_component_mapping.component_keys',
            'source_spec_component_keys',
            'source_spec_alias_coverage.ok',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aiMappingServiceTest);
        }

        foreach ([
            'page_plan.ai_industry_component_mapping',
            'builder_nodes',
            "['content', 'data', 'style', 'advanced', 'responsive', 'states']",
            "['bindings']['props.data.slug']",
            'meta.ai_slot',
            'cms-canonical-page-node.v1',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aiPageGenerationServiceTest);
        }
    }
}

