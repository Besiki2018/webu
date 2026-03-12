<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class CmsRemainingChecklistClosureSyncTest extends TestCase
{
    public function test_remaining_open_checklist_docs_are_closed_with_evidence_links(): void
    {
        $uiChecklistPath = base_path('docs/qa/CMS_UI_REGRESSION_CHECKLIST.md');
        $headerFooterChecklistPath = base_path('docs/qa/CMS_HEADER_FOOTER_PREVIEW_PUBLISH_PARITY_CHECKLIST.md');
        $deployRunbookPath = base_path('docs/qa/CMS_POST_CLOSURE_DEPLOYMENT_RUNBOOK_HC_I1_05.md');
        $uiHeaderFooterEvidencePath = base_path('docs/qa/CMS_UI_HEADER_FOOTER_CHECKLIST_EXECUTION_LOG_2026_02_26.md');
        $deployEvidencePath = base_path('docs/qa/CMS_POST_CLOSURE_DEPLOYMENT_RUNBOOK_EXECUTION_HC_I1_05_2026_02_26.md');

        foreach ([
            $uiChecklistPath,
            $headerFooterChecklistPath,
            $deployRunbookPath,
            $uiHeaderFooterEvidencePath,
            $deployEvidencePath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $uiChecklist = File::get($uiChecklistPath);
        $headerFooterChecklist = File::get($headerFooterChecklistPath);
        $deployRunbook = File::get($deployRunbookPath);
        $uiHeaderFooterEvidence = File::get($uiHeaderFooterEvidencePath);
        $deployEvidence = File::get($deployEvidencePath);

        $this->assertStringNotContainsString('- [ ]', $uiChecklist);
        $this->assertStringNotContainsString('- [ ]', $headerFooterChecklist);
        $this->assertStringNotContainsString('- [ ]', $deployRunbook);

        $this->assertStringContainsString('- [x] Core Pass Criteria all met', $uiChecklist);
        $this->assertStringContainsString('- [x] Header smoke flow passed', $headerFooterChecklist);
        $this->assertStringContainsString('- [x] Code deployed', $deployRunbook);

        $this->assertStringContainsString('CMS_UI_HEADER_FOOTER_CHECKLIST_EXECUTION_LOG_2026_02_26.md', $uiChecklist);
        $this->assertStringContainsString('CMS_UI_HEADER_FOOTER_CHECKLIST_EXECUTION_LOG_2026_02_26.md', $headerFooterChecklist);
        $this->assertStringContainsString('CMS_POST_CLOSURE_DEPLOYMENT_RUNBOOK_EXECUTION_HC_I1_05_2026_02_26.md', $deployRunbook);

        $this->assertStringContainsString('11` tests, all passed', $uiHeaderFooterEvidence);
        $this->assertStringContainsString('192` assertions, all passed', $uiHeaderFooterEvidence);
        $this->assertStringContainsString('npm run build', $deployEvidence);
        $this->assertStringContainsString('8` tests passed (`192` assertions)', $deployEvidence);
    }
}
