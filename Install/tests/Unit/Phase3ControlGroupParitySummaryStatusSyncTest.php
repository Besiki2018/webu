<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class Phase3ControlGroupParitySummaryStatusSyncTest extends TestCase
{
    public function test_phase3_wrapper_control_group_subline_statuses_match_existing_d2_d3_evidence(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $summaryDocPath = base_path('docs/qa/CMS_PHASE3_WRAPPER_CONTROL_GROUP_PARITY_SUMMARY.md');
        $controlGroupStandardsDocPath = base_path('docs/qa/CMS_CONTROL_GROUP_STANDARDS_D3.md');
        $advancedDocPath = base_path('docs/qa/CMS_ADVANCED_CONTROLS_D3_BASELINE.md');
        $presetsDocPath = base_path('docs/qa/CMS_REUSABLE_STYLE_PRESETS_D3_BASELINE.md');
        $runtimeOrderDocPath = base_path('docs/qa/CMS_RUNTIME_STYLE_RESOLUTION_ORDER_D2.md');
        $responsiveDocPath = base_path('docs/qa/CMS_RESPONSIVE_OVERRIDES_D2_BASELINE.md');
        $stateDocPath = base_path('docs/qa/CMS_STATE_CONTROLS_D2_BASELINE.md');
        $tokenModelDocPath = base_path('docs/architecture/CMS_THEME_TOKEN_MODEL_VERSIONING.md');
        $summaryContractPath = base_path('resources/js/Pages/Project/__tests__/CmsPhase3ControlGroupParitySummary.contract.test.ts');

        foreach ([
            $roadmapPath,
            $summaryDocPath,
            $controlGroupStandardsDocPath,
            $advancedDocPath,
            $presetsDocPath,
            $runtimeOrderDocPath,
            $responsiveDocPath,
            $stateDocPath,
            $tokenModelDocPath,
            $summaryContractPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $summaryDoc = File::get($summaryDocPath);
        $controlGroupStandardsDoc = File::get($controlGroupStandardsDocPath);
        $advancedDoc = File::get($advancedDocPath);
        $presetsDoc = File::get($presetsDocPath);
        $runtimeOrderDoc = File::get($runtimeOrderDocPath);
        $responsiveDoc = File::get($responsiveDocPath);
        $stateDoc = File::get($stateDocPath);
        $tokenModelDoc = File::get($tokenModelDocPath);
        $summaryContract = File::get($summaryContractPath);

        // Newly closed wrapper sub-lines
        $this->assertStringContainsString('- ✅ Control groups parity:', $roadmap);
        $this->assertStringContainsString('- ✅ typography', $roadmap);
        $this->assertStringContainsString('- ✅ spacing', $roadmap);
        $this->assertStringContainsString('- ✅ border/radius/shadow', $roadmap);
        $this->assertStringContainsString('- ✅ background/overlay', $roadmap);
        $this->assertStringContainsString('- ✅ layout/display/position', $roadmap);
        $this->assertStringContainsString('- ✅ visibility', $roadmap);
        $this->assertStringContainsString('- ✅ Global design tokens + per-component override interaction rules', $roadmap);

        // Wrapper tabs line is now closed separately via a dedicated evidence lock
        $this->assertStringContainsString('- ✅ Standardize Content / Style / Advanced tabs for all components', $roadmap);

        // Summary doc explicitly maps closures + open lines
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:158', $summaryDoc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:159', $summaryDoc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:162', $summaryDoc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:165', $summaryDoc);
        $this->assertStringContainsString('Related Wrapper Evidence Locks (Tracked Separately)', $summaryDoc);
        $this->assertStringContainsString('CMS_PHASE3_PRIMARY_TABS_WRAPPER_SUMMARY.md', $summaryDoc);
        $this->assertStringNotContainsString('overlay-specific parity not yet explicitly locked', $summaryDoc);
        $this->assertStringContainsString('data-webu-builder-background-overlay', $summaryDoc);
        $this->assertStringContainsString('data-webu-runtime-background-overlay', $summaryDoc);

        // D2 / D3 evidence anchors
        $this->assertStringContainsString('content` → `style` → `advanced`', $controlGroupStandardsDoc);
        $this->assertStringContainsString('responsive.desktop|tablet|mobile', $controlGroupStandardsDoc);
        $this->assertStringContainsString('states.normal|hover|focus|active', $controlGroupStandardsDoc);
        $this->assertStringContainsString('visibility', $advancedDoc);
        $this->assertStringContainsString('positioning', $advancedDoc);
        $this->assertStringContainsString('--webu-token-space-*', $presetsDoc);
        $this->assertStringContainsString('--webu-token-shadow-*', $presetsDoc);
        $this->assertStringContainsString('base → responsive → state', $runtimeOrderDoc);
        $this->assertStringContainsString('responsive.desktop', $responsiveDoc);
        $this->assertStringContainsString('states.hover', $stateDoc);
        $this->assertStringContainsString('deterministic layering', strtolower($tokenModelDoc));

        // Contract lock exists
        $this->assertStringContainsString('Phase 3 wrapper control-group parity summary contracts', $summaryContract);
    }
}
