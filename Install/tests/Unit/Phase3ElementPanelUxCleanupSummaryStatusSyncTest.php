<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class Phase3ElementPanelUxCleanupSummaryStatusSyncTest extends TestCase
{
    public function test_phase3_wrapper_element_panel_ux_cleanup_status_matches_existing_panel_editor_evidence(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $summaryDocPath = base_path('docs/qa/CMS_ELEMENT_PANEL_UX_CLEANUP_PHASE3_WRAPPER_SUMMARY.md');
        $auditDocPath = base_path('docs/qa/CMS_BUILDER_CONTROL_PANEL_AUDIT_D1.md');
        $controlMetadataDocPath = base_path('docs/architecture/CMS_CANONICAL_CONTROL_METADATA_V1.md');
        $layoutContractPath = base_path('resources/js/Pages/Project/__tests__/CmsLayoutStability.contract.test.ts');
        $auditContractPath = base_path('resources/js/Pages/Project/__tests__/CmsControlPanelAudit.contract.test.ts');
        $wrapperContractPath = base_path('resources/js/Pages/Project/__tests__/CmsElementPanelUxCleanupPhase3Wrapper.contract.test.ts');

        foreach ([
            $roadmapPath,
            $summaryDocPath,
            $auditDocPath,
            $controlMetadataDocPath,
            $layoutContractPath,
            $auditContractPath,
            $wrapperContractPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $summaryDoc = File::get($summaryDocPath);
        $auditDoc = File::get($auditDocPath);
        $controlMetadataDoc = File::get($controlMetadataDocPath);
        $layoutContract = File::get($layoutContractPath);
        $auditContract = File::get($auditContractPath);
        $wrapperContract = File::get($wrapperContractPath);

        $this->assertStringContainsString('- ✅ Element panel UX cleanup (clear labels, semantic fields, image/link editors, nested object editors)', $roadmap);

        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:166', $summaryDoc);
        $this->assertStringContainsString('semantic labels', strtolower($summaryDoc));
        $this->assertStringContainsString('image editors', strtolower($summaryDoc));
        $this->assertStringContainsString('link editors', strtolower($summaryDoc));
        $this->assertStringContainsString('nested object editor fallback', strtolower($summaryDoc));
        $this->assertStringContainsString('Explicitly Separate From This Wrapper Line', $summaryDoc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:154', $summaryDoc);
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:162', $summaryDoc);

        $this->assertStringContainsString('label', strtolower($auditDoc));
        $this->assertStringContainsString('renderCanonicalControlGroupFieldSets', $auditDoc);
        $this->assertStringContainsString('display label used by the panel', strtolower($controlMetadataDoc));

        $this->assertStringContainsString('media picker dialog full-screen-safe overflow constraints', strtolower($layoutContract));
        $this->assertStringContainsString('CMS builder control panel audit contracts', $auditContract);
        $this->assertStringContainsString('element panel UX cleanup wrapper summary contracts', $wrapperContract);
    }
}
