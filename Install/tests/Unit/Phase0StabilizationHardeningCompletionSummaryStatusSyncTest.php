<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class Phase0StabilizationHardeningCompletionSummaryStatusSyncTest extends TestCase
{
    public function test_phase0_stabilization_and_hardening_lines_are_closed_with_regression_and_ui_evidence(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $summaryDocPath = base_path('docs/qa/CMS_PHASE0_STABILIZATION_HARDENING_COMPLETION_SUMMARY.md');
        $layoutContractPath = base_path('resources/js/Pages/Project/__tests__/CmsLayoutStability.contract.test.ts');
        $elementUxContractPath = base_path('resources/js/Pages/Project/__tests__/CmsElementPanelUxCleanupPhase3Wrapper.contract.test.ts');
        $previewPublishAlignmentPath = base_path('tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php');
        $templateImportContractPath = base_path('tests/Feature/Templates/TemplateImportContractServiceTest.php');
        $templatePreviewSmokePath = base_path('tests/Feature/Templates/TemplatePreviewRenderSmokeTest.php');
        $templateAppPreviewSmokePath = base_path('tests/Feature/Templates/TemplateAppPreviewRenderSmokeTest.php');
        $templatePublishedSmokePath = base_path('tests/Feature/Templates/TemplatePublishedRenderSmokeTest.php');
        $templateRuntimeLockDocPath = base_path('docs/qa/CMS_TEMPLATE_RUNTIME_CONTRACT_LOCK.md');
        $uiChecklistDocPath = base_path('docs/qa/CMS_UI_REGRESSION_CHECKLIST.md');
        $ciWorkflowContractPath = base_path('tests/Unit/CmsTemplateSmokesCiWorkflowContractTest.php');

        foreach ([
            $roadmapPath,
            $summaryDocPath,
            $layoutContractPath,
            $elementUxContractPath,
            $previewPublishAlignmentPath,
            $templateImportContractPath,
            $templatePreviewSmokePath,
            $templateAppPreviewSmokePath,
            $templatePublishedSmokePath,
            $templateRuntimeLockDocPath,
            $uiChecklistDocPath,
            $ciWorkflowContractPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $summaryDoc = File::get($summaryDocPath);
        $layoutContract = File::get($layoutContractPath);
        $elementUxContract = File::get($elementUxContractPath);
        $previewPublishAlignment = File::get($previewPublishAlignmentPath);
        $templateImportContract = File::get($templateImportContractPath);
        $templatePreviewSmoke = File::get($templatePreviewSmokePath);
        $templateAppPreviewSmoke = File::get($templateAppPreviewSmokePath);
        $templatePublishedSmoke = File::get($templatePublishedSmokePath);
        $templateRuntimeLockDoc = File::get($templateRuntimeLockDocPath);
        $uiChecklistDoc = File::get($uiChecklistDocPath);
        $ciWorkflowContract = File::get($ciWorkflowContractPath);

        // Phase 0 summary lines are closed.
        $this->assertStringContainsString('- ✅ Keep builder preview and published runtime behavior aligned', $roadmap);
        $this->assertStringContainsString('- ✅ Add/maintain template validation quality gates', $roadmap);
        $this->assertStringContainsString('- ✅ Maintain smoke tests for:', $roadmap);
        $this->assertStringContainsString('- ✅ Keep admin/CMS UI layout stable', $roadmap);

        // Remaining execution-board lines are now DONE.
        $this->assertStringContainsString("`P0-A1-04` (✅ `DONE`)", $roadmap);
        $this->assertStringContainsString("`P0-A2-02` (✅ `DONE`)", $roadmap);
        $this->assertStringContainsString("`P0-A3-01` (✅ `DONE`)", $roadmap);
        $this->assertStringContainsString("`P0-A3-02` (✅ `DONE`)", $roadmap);
        $this->assertStringContainsString("`P0-A3-03` (✅ `DONE`)", $roadmap);

        // Summary doc references all targeted roadmap lines.
        foreach ([':294', ':302', ':310', ':311', ':312', ':29', ':30', ':31', ':36'] as $lineSuffix) {
            $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md'.$lineSuffix, $summaryDoc);
        }

        // A1/A2 evidence: import/runtime regression + preview/publish alignment.
        $this->assertStringContainsString('TemplateImportContractServiceTest', $summaryDoc);
        $this->assertStringContainsString('TemplatePreviewRenderSmokeTest', $summaryDoc);
        $this->assertStringContainsString('TemplateAppPreviewRenderSmokeTest', $summaryDoc);
        $this->assertStringContainsString('TemplatePublishedRenderSmokeTest', $summaryDoc);
        $this->assertStringContainsString('CMS_TEMPLATE_RUNTIME_CONTRACT_LOCK.md', $summaryDoc);
        $this->assertStringContainsString('CmsPreviewPublishAlignmentTest', $summaryDoc);
        $this->assertStringContainsString('class CmsPreviewPublishAlignmentTest', $previewPublishAlignment);
        $this->assertStringContainsString('class TemplateImportContractServiceTest', $templateImportContract);
        $this->assertStringContainsString('class TemplatePreviewRenderSmokeTest', $templatePreviewSmoke);
        $this->assertStringContainsString('class TemplateAppPreviewRenderSmokeTest', $templateAppPreviewSmoke);
        $this->assertStringContainsString('class TemplatePublishedRenderSmokeTest', $templatePublishedSmoke);
        $this->assertStringContainsString('template import/runtime binding contract', $templateRuntimeLockDoc);
        $this->assertStringContainsString('CmsPreviewPublishAlignmentTest', $ciWorkflowContract);

        // A3 evidence: overflow guards, modal/media picker layering, semantic labels.
        $this->assertStringContainsString('overflow-x-hidden', $layoutContract);
        $this->assertStringContainsString('media picker dialog full-screen-safe overflow constraints', $layoutContract);
        $this->assertStringContainsString('modal z-index layering', $layoutContract);
        $this->assertStringContainsString('semantic labels', $elementUxContract);
        $this->assertStringContainsString('renderSidebarMediaFieldControls', $elementUxContract);
        $this->assertStringContainsString('media picker, and preview behavior', $uiChecklistDoc);
        $this->assertStringContainsString('Select existing image -> confirm field updates', $uiChecklistDoc);
        $this->assertStringContainsString('Remove image -> confirm field clears', $uiChecklistDoc);
        $this->assertStringContainsString('use upload action', $uiChecklistDoc);

        $this->assertStringContainsString('Scope Note', $summaryDoc);
        $this->assertStringContainsString('shared shell-level overflow guards', $summaryDoc);
    }
}
