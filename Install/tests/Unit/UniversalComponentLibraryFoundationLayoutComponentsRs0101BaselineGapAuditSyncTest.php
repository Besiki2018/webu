<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryFoundationLayoutComponentsRs0101BaselineGapAuditSyncTest extends TestCase
{
    public function test_rs_01_01_progress_audit_doc_locks_layout_component_parity_baseline_and_nesting_blocker_truth_and_closure_supersession(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_FOUNDATION_LAYOUT_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_01_01_2026_02_25.md');
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
        $closureSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryFoundationLayoutComponentsRs0101ClosureAuditSyncTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
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
            $closureSyncTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
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

        foreach ([
            '# 1) LAYOUT COMPONENTS (Foundation)',
            '## 1.1 layout.section',
            'Content: containerWidth (full/boxed), anchorId',
            'Style: background, overlay, padding, minHeight',
            'Advanced: visibility, customCSS',
            'Nesting: contains containers/grids/columns',
            '## 1.2 layout.container',
            'Content: maxWidth, align (left/center/right)',
            'Style: padding, background, border, radius, shadow',
            'Nesting: any',
            '## 1.3 layout.grid',
            'Content: columnsDesktop/Tablet/Mobile, gap, autoRows',
            'Style: grid styles, item alignment',
            'Nesting: grid items are children (any)',
            '## 1.4 layout.columns (Elementor-like)',
            'Content: columnsCount (1-6), gap, equalWidth toggle',
            'Style: column background, divider',
            'Nesting: columns → children',
            '## 1.5 layout.spacer',
            'Content: height (responsive)',
            'Style: none',
            '## 1.6 layout.divider',
            'Content: type (line/dots), thickness, width',
            'Style: color, margin',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-01-01` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_FOUNDATION_LAYOUT_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_01_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_FOUNDATION_LAYOUT_COMPONENTS_PARITY_NESTING_RESPONSIVE_CLOSURE_AUDIT_RS_01_01_2026_02_26.md',
            'UniversalComponentLibraryFoundationLayoutComponentsRs0101BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryFoundationLayoutComponentsRs0101ClosureAuditSyncTest.php',
            'CMS_BUILDER_CANONICAL_SCHEMA_MAPPING.md',
            'CMS_PHASE3_RESPONSIVE_STATE_WRAPPER_SUMMARY.md',
            'CmsAiOutputValidationEngine.php',
            'CmsAiOutputValidationEngineTest.php',
            'CmsEcommerceBuilderCoverage.contract.test.ts',
            'UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php',
            '`✅` baseline parity/gap checklist completed for all 6 foundation layout components (`content/style/advanced/responsive/states/nesting`)',
            '`✅` current builder equivalents + shared baseline augmentation paths evidenced in `Cms.tsx`',
            '`✅` baseline `RS-01-01` audit is preserved and superseded by a closure audit with responsive evidence consolidation and layout nesting validation enforcement/tests',
            '`✅` layout nesting constraints are now enforced for foundation layout families in canonical nested-node validation (`CmsAiOutputValidationEngine` + `CmsAiOutputValidationEngineTest`) with `invalid_layout_nesting_child` contract evidence',
            '`✅` responsive behavior evidence for desktop/tablet/mobile is now accepted via shared wrapper/state baseline + component preview branch coverage for all 6 layout equivalents',
            '`⚠️` source exactness remains partial for multiple layout controls and builder model still keeps non-universal `children[]` support outside layout-focused validation scope',
            '`🧪` RS-01-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '## Scope',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            '## Audit Inputs Reviewed',
            '## ✅ What Was Done (This Pass)',
            '## Executive Result (`RS-01-01`)',
            '## Component Parity Checklist (By Component)',
            '### Checklist Matrix (`content/style/advanced/responsive/states/nesting`)',
            '`layout.section`',
            '`layout.container`',
            '`layout.grid`',
            '`layout.columns`',
            '`layout.spacer`',
            '`layout.divider`',
            '`webu_general_section_01`',
            '`webu_general_container_01`',
            '`webu_general_grid_01`',
            '`webu_general_columns_01`',
            '`webu_general_spacer_01`',
            '`webu_general_divider_01`',
            '## Responsive Behavior Evidence (Desktop / Tablet / Mobile)',
            'shared baseline',
            'builderPreviewMode === \'mobile\'',
            'columnsDesktop/Tablet/Mobile',
            '## Nesting Constraints Status (Current Blocker)',
            '`children[]` nesting is not universal',
            'not yet satisfied',
            '## Unblocking Plan (To Reach DoD)',
            'DoD line | Current status',
            'nesting constraints enforced/tested | `missing`',
            'task remains `IN_PROGRESS`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'children[]` nesting is not universal for all builder sections',
            'section-block model',
            'foundation-layout nesting rules (`section` → `container/grid/columns`, `spacer/divider` as leaf nodes) are now enforced in `CmsAiOutputValidationEngine`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderSchemaMappingDoc);
        }

        foreach ([
            'GENERAL_FOUNDATION_BASELINE_SCHEMA_PROPERTIES',
            'augmentGeneralSectionSchemaWithFoundationBaseline',
            'isGeneralSectionKey(normalizedKey)',
            'augmentGeneralSectionSchemaWithFoundationBaseline(item.schema_json)',
            'webu_general_spacer_01',
            'webu_general_section_01',
            'webu_general_container_01',
            'webu_general_grid_01',
            'webu_general_columns_01',
            'webu_general_divider_01',
            "if (normalized === 'webu_general_spacer_01')",
            "if (normalized === 'webu_general_section_01')",
            "if (normalized === 'webu_general_container_01')",
            "if (normalized === 'webu_general_grid_01')",
            "if (normalized === 'webu_general_columns_01')",
            "if (normalized === 'webu_general_divider_01')",
            "if (normalizedSectionType === 'webu_general_spacer_01')",
            "if (normalizedSectionType === 'webu_general_section_01')",
            "if (normalizedSectionType === 'webu_general_container_01')",
            "if (normalizedSectionType === 'webu_general_grid_01')",
            "if (normalizedSectionType === 'webu_general_columns_01')",
            "if (normalizedSectionType === 'webu_general_divider_01')",
            'buildGeneralFoundationResponsiveStyleOverrideSchema',
            'buildGeneralFoundationStateStyleOverrideSchema',
            'builderPreviewMode === \'mobile\'',
            'columns_desktop',
            'columns_mobile',
            'stack_on_mobile',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:155',
            'PROJECT_ROADMAP_TASKS_KA.md:156',
            'Desktop / Tablet / Mobile',
            'base -> responsive -> state',
            'CMS_CONTROL_GROUP_STANDARDS_D3.md',
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
            "rowsByKey['layout.section']['status']",
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
            'section',
            'container',
            'grid',
            'columns',
            'spacer',
            'divider',
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
    }
}
