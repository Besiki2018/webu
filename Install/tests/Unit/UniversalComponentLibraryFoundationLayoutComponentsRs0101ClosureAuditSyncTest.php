<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryFoundationLayoutComponentsRs0101ClosureAuditSyncTest extends TestCase
{
    public function test_rs_01_01_closure_audit_locks_foundation_layout_parity_responsive_evidence_nesting_enforcement_and_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_FOUNDATION_LAYOUT_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_01_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_FOUNDATION_LAYOUT_COMPONENTS_PARITY_NESTING_RESPONSIVE_CLOSURE_AUDIT_RS_01_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $builderSchemaMappingDocPath = base_path('docs/architecture/CMS_BUILDER_CANONICAL_SCHEMA_MAPPING.md');
        $responsiveStateWrapperDocPath = base_path('docs/qa/CMS_PHASE3_RESPONSIVE_STATE_WRAPPER_SUMMARY.md');
        $responsiveStateWrapperSyncTestPath = base_path('tests/Unit/Phase3ResponsiveStateWrapperSummaryStatusSyncTest.php');
        $responsiveStateWrapperContractPath = base_path('resources/js/Pages/Project/__tests__/CmsPhase3ResponsiveStateWrapperSummary.contract.test.ts');
        $builderCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts');
        $componentGapAuditTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php');
        $aliasMapTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecEquivalenceAliasMapTest.php');
        $aiOutputValidationEnginePath = base_path('app/Services/CmsAiOutputValidationEngine.php');
        $aiOutputValidationFeatureTestPath = base_path('tests/Feature/Cms/CmsAiOutputValidationEngineTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryFoundationLayoutComponentsRs0101BaselineGapAuditSyncTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $baselineDocPath,
            $closureDocPath,
            $cmsPath,
            $builderSchemaMappingDocPath,
            $responsiveStateWrapperDocPath,
            $responsiveStateWrapperSyncTestPath,
            $responsiveStateWrapperContractPath,
            $builderCoverageContractPath,
            $componentGapAuditTestPath,
            $aliasMapTestPath,
            $aiOutputValidationEnginePath,
            $aiOutputValidationFeatureTestPath,
            $baselineSyncTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $closureDoc = File::get($closureDocPath);
        $cms = File::get($cmsPath);
        $builderSchemaMappingDoc = File::get($builderSchemaMappingDocPath);
        $responsiveStateWrapperDoc = File::get($responsiveStateWrapperDocPath);
        $responsiveStateWrapperSyncTest = File::get($responsiveStateWrapperSyncTestPath);
        $responsiveStateWrapperContract = File::get($responsiveStateWrapperContractPath);
        $builderCoverageContract = File::get($builderCoverageContractPath);
        $componentGapAuditTest = File::get($componentGapAuditTestPath);
        $aliasMapTest = File::get($aliasMapTestPath);
        $aiOutputValidationEngine = File::get($aiOutputValidationEnginePath);
        $aiOutputValidationFeatureTest = File::get($aiOutputValidationFeatureTestPath);
        $baselineSyncTest = File::get($baselineSyncTestPath);

        foreach ([
            '# 1) LAYOUT COMPONENTS (Foundation)',
            '## 1.1 layout.section',
            '## 1.2 layout.container',
            '## 1.3 layout.grid',
            '## 1.4 layout.columns (Elementor-like)',
            '## 1.5 layout.spacer',
            '## 1.6 layout.divider',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-01-01` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_FOUNDATION_LAYOUT_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_01_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_FOUNDATION_LAYOUT_COMPONENTS_PARITY_NESTING_RESPONSIVE_CLOSURE_AUDIT_RS_01_01_2026_02_26.md',
            'UniversalComponentLibraryFoundationLayoutComponentsRs0101BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryFoundationLayoutComponentsRs0101ClosureAuditSyncTest.php',
            'CmsAiOutputValidationEngine.php',
            'CmsAiOutputValidationEngineTest.php',
            '`✅` baseline `RS-01-01` audit is preserved and superseded by a closure audit with responsive evidence consolidation and layout nesting validation enforcement/tests',
            '`✅` layout nesting constraints are now enforced for foundation layout families in canonical nested-node validation (`CmsAiOutputValidationEngine` + `CmsAiOutputValidationEngineTest`) with `invalid_layout_nesting_child` contract evidence',
            '`✅` responsive behavior evidence for desktop/tablet/mobile is now accepted via shared wrapper/state baseline + component preview branch coverage for all 6 layout equivalents',
            '`⚠️` source exactness remains partial for multiple layout controls and builder model still keeps non-universal `children[]` support outside layout-focused validation scope',
            '`🧪` RS-01-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Closure Rationale (Why `RS-01-01` Can Be `DONE`)',
            '## What Changed Since Baseline (Closure Delta)',
            'Layout Nesting Constraints Are Now Enforced in Validation Gate',
            'invalid_layout_nesting_child',
            'resolveLayoutNestingRole(...)',
            'appendLayoutNestingFinding(...)',
            'section',
            'container',
            'grid',
            'columns',
            'spacer',
            'divider',
            'Layout Nesting Enforcement Is Tested',
            'test_it_enforces_foundation_layout_nesting_rules_for_nested_builder_nodes',
            'Responsive Evidence Consolidated for All Six Layout Equivalents',
            '## DoD Closure Matrix (`RS-01-01`)',
            '| all 6 components validated against spec fields | `pass` |',
            '| responsive behavior evidence (desktop/tablet/mobile) | `pass` |',
            '| nesting constraints enforced/tested | `pass` |',
            '## Remaining Exactness Gaps (Truthful, Non-Blocking for `RS-01-01`)',
            'builder model remains non-universal for `children[]` across all section families',
            '## DoD Verdict (`RS-01-01`)',
            'Conclusion: `RS-01-01` is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            'webu_general_section_01',
            'webu_general_container_01',
            'webu_general_grid_01',
            'webu_general_columns_01',
            'webu_general_spacer_01',
            'webu_general_divider_01',
            "if (normalizedSectionType === 'webu_general_section_01')",
            "if (normalizedSectionType === 'webu_general_container_01')",
            "if (normalizedSectionType === 'webu_general_grid_01')",
            "if (normalizedSectionType === 'webu_general_columns_01')",
            "if (normalizedSectionType === 'webu_general_spacer_01')",
            "if (normalizedSectionType === 'webu_general_divider_01')",
            'builderPreviewMode === \'mobile\'',
            'columns_desktop',
            'columns_mobile',
            'stack_on_mobile',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'children[]` nesting is not universal for all builder sections',
            'section-block model',
            'foundation-layout nesting rules (`section` → `container/grid/columns`, `spacer/divider` as leaf nodes) are now enforced in `CmsAiOutputValidationEngine`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderSchemaMappingDoc);
        }

        foreach ([
            'Desktop / Tablet / Mobile',
            'base -> responsive -> state',
        ] as $needle) {
            $this->assertStringContainsString($needle, $responsiveStateWrapperDoc);
        }
        $this->assertStringContainsString('responsive.desktop', $responsiveStateWrapperSyncTest);
        $this->assertStringContainsString('responsive/state wrapper summary contracts', strtolower($responsiveStateWrapperContract));

        foreach ([
            'webu_general_section_01',
            'webu_general_container_01',
            'webu_general_grid_01',
            'webu_general_columns_01',
            'webu_general_spacer_01',
            'webu_general_divider_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderCoverageContract);
        }

        foreach ([
            "rowsByKey['layout.section']",
            '`webu_general_section_01`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $componentGapAuditTest);
        }
        foreach ([
            "rowsByKey['layout.section']",
            'webu_general_section_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMapTest);
        }

        foreach ([
            'invalid_layout_nesting_child',
            'resolveLayoutNestingRole',
            'appendLayoutNestingFinding',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aiOutputValidationEngine);
        }

        foreach ([
            'test_it_enforces_foundation_layout_nesting_rules_for_nested_builder_nodes',
            'webu_general_section_01',
            'webu_general_container_01',
            'webu_general_grid_01',
            'webu_general_columns_01',
            'webu_general_spacer_01',
            'webu_general_divider_01',
            'invalid_layout_nesting_child',
            'parent_layout_role',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aiOutputValidationFeatureTest);
        }

        foreach ([
            'closure_supersession',
            'UNIVERSAL_COMPONENT_LIBRARY_FOUNDATION_LAYOUT_COMPONENTS_PARITY_NESTING_RESPONSIVE_CLOSURE_AUDIT_RS_01_01_2026_02_26.md',
            'CmsAiOutputValidationEngine.php',
            'CmsAiOutputValidationEngineTest.php',
        ] as $needle) {
            $this->assertStringContainsString($needle, $baselineSyncTest);
        }
    }
}
