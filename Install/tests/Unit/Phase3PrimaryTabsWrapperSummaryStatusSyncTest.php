<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class Phase3PrimaryTabsWrapperSummaryStatusSyncTest extends TestCase
{
    public function test_phase3_wrapper_primary_tabs_status_matches_shared_renderer_evidence(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $summaryDocPath = base_path('docs/qa/CMS_PHASE3_PRIMARY_TABS_WRAPPER_SUMMARY.md');
        $auditDocPath = base_path('docs/qa/CMS_BUILDER_CONTROL_PANEL_AUDIT_D1.md');
        $controlMetadataDocPath = base_path('docs/architecture/CMS_CANONICAL_CONTROL_METADATA_V1.md');
        $auditContractPath = base_path('resources/js/Pages/Project/__tests__/CmsControlPanelAudit.contract.test.ts');
        $wrapperContractPath = base_path('resources/js/Pages/Project/__tests__/CmsPhase3PrimaryTabsWrapperSummary.contract.test.ts');

        foreach ([
            $roadmapPath,
            $summaryDocPath,
            $auditDocPath,
            $controlMetadataDocPath,
            $auditContractPath,
            $wrapperContractPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $summaryDoc = File::get($summaryDocPath);
        $auditDoc = File::get($auditDocPath);
        $controlMetadataDoc = File::get($controlMetadataDocPath);
        $auditContract = File::get($auditContractPath);
        $wrapperContract = File::get($wrapperContractPath);

        $this->assertStringContainsString('- ✅ Standardize Content / Style / Advanced tabs for all components', $roadmap);

        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:154', $summaryDoc);
        $this->assertStringContainsString('renderCanonicalControlGroupFieldSets(...)', $summaryDoc);
        $this->assertStringContainsString('buildCanonicalPrimaryPanelTabFieldSetBuckets(...)', $summaryDoc);
        $this->assertStringContainsString('builder-control-panel-primary-tab-trigger', $summaryDoc);
        $this->assertStringContainsString('selected page section editor controls', $summaryDoc);
        $this->assertStringContainsString('fixed header/footer editor controls', $summaryDoc);
        $this->assertStringContainsString('Related Wrapper Evidence Locks', $summaryDoc);

        $this->assertStringContainsString('wrapper line `PROJECT_ROADMAP_TASKS_KA.md:154` completed', $auditDoc);
        $this->assertStringContainsString('renderCanonicalControlGroupFieldSets', $auditDoc);
        $this->assertStringContainsString('display label used by the panel', strtolower($controlMetadataDoc));

        $this->assertStringContainsString('builder-control-panel-primary-tab-trigger', $auditContract);
        $this->assertStringContainsString('primary tabs wrapper summary contracts', strtolower($wrapperContract));
    }
}
