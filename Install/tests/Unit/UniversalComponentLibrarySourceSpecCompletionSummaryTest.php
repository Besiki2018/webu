<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibrarySourceSpecCompletionSummaryTest extends TestCase
{
    public function test_component_library_source_spec_block_has_master_completion_evidence_lock_for_sections_0_to_15_and_acceptance(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $summaryDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SOURCE_SPEC_COMPLETION_SUMMARY.md');
        $gapAuditDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md');
        $acceptanceDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_ACCEPTANCE_CRITERIA_COVERAGE.md');
        $aiMappingDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_AI_PROMPT_MAPPING_RULES_COVERAGE.md');
        $equivalenceAliasMapJsonPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $equivalenceAliasMapDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP_V1.md');

        $evidencePaths = [
            base_path('docs/architecture/CMS_CANONICAL_COMPONENT_REGISTRY_SCHEMA_V1.md'),
            base_path('docs/architecture/CMS_CANONICAL_PAGE_NODE_SCHEMA_V1.md'),
            base_path('docs/architecture/CMS_CANONICAL_CONTROL_METADATA_V1.md'),
            base_path('docs/architecture/UNIVERSAL_BINDING_NAMESPACE_COMPATIBILITY_P5_F5_03.md'),
            base_path('docs/qa/CMS_PHASE3_PRIMARY_TABS_WRAPPER_SUMMARY.md'),
            base_path('docs/qa/CMS_PHASE3_RESPONSIVE_STATE_WRAPPER_SUMMARY.md'),
            base_path('docs/qa/CMS_PHASE3_WRAPPER_CONTROL_GROUP_PARITY_SUMMARY.md'),
            base_path('docs/qa/CMS_ELEMENT_PANEL_UX_CLEANUP_PHASE3_WRAPPER_SUMMARY.md'),
            base_path('tests/Unit/CmsCanonicalSchemaContractsTest.php'),
            base_path('tests/Unit/Phase3PrimaryTabsWrapperSummaryStatusSyncTest.php'),
            base_path('tests/Unit/Phase3ResponsiveStateWrapperSummaryStatusSyncTest.php'),
            base_path('tests/Unit/Phase3ControlGroupParitySummaryStatusSyncTest.php'),
            base_path('tests/Unit/Phase3ElementPanelUxCleanupSummaryStatusSyncTest.php'),
            base_path('tests/Unit/UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php'),
            base_path('tests/Unit/UniversalComponentLibrarySpecAcceptanceCriteriaCoverageTest.php'),
            base_path('tests/Unit/UniversalComponentLibrarySpecAiPromptMappingRulesCoverageTest.php'),
            base_path('tests/Unit/CmsAiIndustryComponentMappingServiceTest.php'),
            base_path('tests/Unit/CmsAiPageGenerationServiceTest.php'),
        ];

        foreach (array_merge([
            $roadmapPath,
            $summaryDocPath,
            $gapAuditDocPath,
            $acceptanceDocPath,
            $aiMappingDocPath,
            $equivalenceAliasMapJsonPath,
            $equivalenceAliasMapDocPath,
        ], $evidencePaths) as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $summaryDoc = File::get($summaryDocPath);
        $gapAuditDoc = File::get($gapAuditDocPath);
        $acceptanceDoc = File::get($acceptanceDocPath);
        $aiMappingDoc = File::get($aiMappingDocPath);

        foreach ([
            '# CODEX PROMPT — Webu Universal Component Library Spec (Elementor-level, All Industries)',
            '# 0) Global Standards',
            '# 1) LAYOUT COMPONENTS (Foundation)',
            '# 14) ANALYTICS / TRUST COMPONENTS (Optional v1)',
            '# 15) AI Prompt → Industry Mapping Rules (must)',
            '# Acceptance Criteria',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:6439',
            '0) Global Standards',
            '1) Component Catalog (#1-#14)',
            '15) AI Prompt → Industry Mapping Rules (must)',
            'Acceptance Criteria',
            'UNIVERSAL_COMPONENT_LIBRARY_SPEC_COMPONENT_COVERAGE_GAP_AUDIT_BASELINE.md',
            'UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json',
            'UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP_V1.md',
            'UniversalComponentLibrarySpecEquivalenceAliasMapTest.php',
            'UNIVERSAL_COMPONENT_LIBRARY_SPEC_ACCEPTANCE_CRITERIA_COVERAGE.md',
            'UNIVERSAL_COMPONENT_LIBRARY_SPEC_AI_PROMPT_MAPPING_RULES_COVERAGE.md',
            'CmsCanonicalSchemaContractsTest.php',
            'CmsAiIndustryComponentMappingServiceTest.php',
            'CmsAiPageGenerationServiceTest.php',
            '**COMPLETE as an evidence-locked spec coverage deliverable**',
        ] as $needle) {
            $this->assertStringContainsString($needle, $summaryDoc);
        }

        foreach ([
            '- Total source-spec component keys audited: `70`',
            '- `exact`: `0`',
            '- `equivalent`: `70`',
            '- `partial`: `0`',
            '- `missing`: `0`',
            'Component-library spec implementation coverage: **COMPLETE**',
        ] as $needle) {
            $this->assertStringContainsString($needle, $gapAuditDoc);
        }

        $this->assertStringContainsString('**covered by automated evidence**', $acceptanceDoc);
        $this->assertStringContainsString('**covered by deterministic implementation and automated evidence**', $aiMappingDoc);
    }
}
