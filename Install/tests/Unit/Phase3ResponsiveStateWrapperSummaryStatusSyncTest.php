<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class Phase3ResponsiveStateWrapperSummaryStatusSyncTest extends TestCase
{
    public function test_phase3_wrapper_responsive_and_state_statuses_match_d2_parity_evidence(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $summaryDocPath = base_path('docs/qa/CMS_PHASE3_RESPONSIVE_STATE_WRAPPER_SUMMARY.md');
        $responsiveDocPath = base_path('docs/qa/CMS_RESPONSIVE_OVERRIDES_D2_BASELINE.md');
        $stateDocPath = base_path('docs/qa/CMS_STATE_CONTROLS_D2_BASELINE.md');
        $runtimeOrderDocPath = base_path('docs/qa/CMS_RUNTIME_STYLE_RESOLUTION_ORDER_D2.md');
        $controlGroupStandardsDocPath = base_path('docs/qa/CMS_CONTROL_GROUP_STANDARDS_D3.md');
        $responsiveContractPath = base_path('resources/js/Pages/Project/__tests__/CmsResponsiveTypographyOverrides.contract.test.ts');
        $stateContractPath = base_path('resources/js/Pages/Project/__tests__/CmsStateTypographyOverrides.contract.test.ts');
        $parityContractPath = base_path('resources/js/Pages/Project/__tests__/CmsResponsiveStatePreviewRuntimeParity.contract.test.ts');
        $runtimeOrderContractPath = base_path('resources/js/Pages/Project/__tests__/CmsRuntimeStyleResolutionOrder.contract.test.ts');
        $wrapperContractPath = base_path('resources/js/Pages/Project/__tests__/CmsPhase3ResponsiveStateWrapperSummary.contract.test.ts');

        foreach ([
            $roadmapPath,
            $summaryDocPath,
            $responsiveDocPath,
            $stateDocPath,
            $runtimeOrderDocPath,
            $controlGroupStandardsDocPath,
            $responsiveContractPath,
            $stateContractPath,
            $parityContractPath,
            $runtimeOrderContractPath,
            $wrapperContractPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $summaryDoc = File::get($summaryDocPath);
        $responsiveDoc = File::get($responsiveDocPath);
        $stateDoc = File::get($stateDocPath);
        $runtimeOrderDoc = File::get($runtimeOrderDocPath);
        $controlGroupStandardsDoc = File::get($controlGroupStandardsDocPath);
        $responsiveContract = File::get($responsiveContractPath);
        $stateContract = File::get($stateContractPath);
        $parityContract = File::get($parityContractPath);
        $runtimeOrderContract = File::get($runtimeOrderContractPath);
        $wrapperContract = File::get($wrapperContractPath);

        $this->assertStringContainsString('- ✅ Responsive controls (Desktop/Tablet/Mobile)', $roadmap);
        $this->assertStringContainsString('- ✅ State controls (Normal/Hover/Active/Focus)', $roadmap);

        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:155', $summaryDoc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:156', $summaryDoc);
        $this->assertStringContainsString('base -> responsive -> state', $summaryDoc);
        $this->assertStringContainsString('Related Wrapper Evidence Locks', $summaryDoc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:162', $summaryDoc);
        $this->assertStringContainsString('CMS_PHASE3_PRIMARY_TABS_WRAPPER_SUMMARY.md', $summaryDoc);

        $this->assertStringContainsString('responsive.desktop', $responsiveDoc);
        $this->assertStringContainsString('responsive.tablet', $responsiveDoc);
        $this->assertStringContainsString('responsive.mobile', $responsiveDoc);
        $this->assertStringContainsString('states.hover', $stateDoc);
        $this->assertStringContainsString('states.focus', $stateDoc);
        $this->assertStringContainsString('states.active', $stateDoc);
        $this->assertStringContainsString('base → responsive → state', $runtimeOrderDoc);
        $this->assertStringContainsString('responsive.desktop|tablet|mobile', $controlGroupStandardsDoc);
        $this->assertStringContainsString('states.normal|hover|focus|active', $controlGroupStandardsDoc);

        $this->assertStringContainsString('responsive typography override contracts', strtolower($responsiveContract));
        $this->assertStringContainsString('state override contracts', strtolower($stateContract));
        $this->assertStringContainsString('responsive/state preview-runtime parity contracts', strtolower($parityContract));
        $this->assertStringContainsString('runtime style resolution order', strtolower($runtimeOrderContract));
        $this->assertStringContainsString('responsive/state wrapper summary contracts', strtolower($wrapperContract));
    }
}
