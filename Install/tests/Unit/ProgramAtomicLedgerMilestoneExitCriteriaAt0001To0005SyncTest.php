<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class ProgramAtomicLedgerMilestoneExitCriteriaAt0001To0005SyncTest extends TestCase
{
    public function test_atomic_milestone_heading_rows_are_closed_with_explicit_gv01_evidence(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $groupedBacklogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $atomicLedgerPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA_ATOMIC_TRANSFER.md');
        $gv01DocPath = base_path('docs/qa/PROGRAM_MILESTONE_EXIT_CRITERIA_RISK_REGISTER_VERIFICATION_PACK_GV_01_2026_02_25.md');
        $atomicClosureDocPath = base_path('docs/qa/PROGRAM_ATOMIC_LEDGER_MILESTONE_EXIT_CRITERIA_HEADINGS_VERIFICATION_AT_0001_0005_2026_02_26.md');
        $milestoneStatusSyncTestPath = base_path('tests/Unit/ProgramMilestoneExitCriteriaStatusSyncTest.php');
        $gv01SyncTestPath = base_path('tests/Unit/ProgramMilestoneExitCriteriaRiskRegisterVerificationPackGv01SyncTest.php');

        foreach ([
            $roadmapPath,
            $groupedBacklogPath,
            $atomicLedgerPath,
            $gv01DocPath,
            $atomicClosureDocPath,
            $milestoneStatusSyncTestPath,
            $gv01SyncTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $groupedBacklog = File::get($groupedBacklogPath);
        $atomicLedger = File::get($atomicLedgerPath);
        $gv01Doc = File::get($gv01DocPath);
        $atomicClosureDoc = File::get($atomicClosureDocPath);

        foreach ([
            '## 3.6 Milestone Exit Criteria (Program-Level)',
            '### Milestone A — Ecommerce Builder Production MVP',
            '### Milestone B — AI Assisted Store Generation',
            '### Milestone C — Universal Industry Platform',
            '### Milestone D — Learning/Optimization Layer',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        $this->assertStringContainsString('- `GV-01` (`DONE`, `P1`)', $groupedBacklog);
        $this->assertStringContainsString('PROGRAM_MILESTONE_EXIT_CRITERIA_RISK_REGISTER_VERIFICATION_PACK_GV_01_2026_02_25.md', $groupedBacklog);

        $evidenceNeedle = 'Evidence: `Install/docs/qa/PROGRAM_ATOMIC_LEDGER_MILESTONE_EXIT_CRITERIA_HEADINGS_VERIFICATION_AT_0001_0005_2026_02_26.md`';

        $atomicRows = [
            'AT-0001' => [
                'line' => 623,
                'heading' => '## 3.6 Milestone Exit Criteria (Program-Level)',
            ],
            'AT-0002' => [
                'line' => 627,
                'heading' => '### Milestone A — Ecommerce Builder Production MVP',
            ],
            'AT-0003' => [
                'line' => 636,
                'heading' => '### Milestone B — AI Assisted Store Generation',
            ],
            'AT-0004' => [
                'line' => 642,
                'heading' => '### Milestone C — Universal Industry Platform',
            ],
            'AT-0005' => [
                'line' => 647,
                'heading' => '### Milestone D — Learning/Optimization Layer',
            ],
        ];

        foreach ($atomicRows as $atomicId => $meta) {
            preg_match('/^- `'.preg_quote($atomicId, '/').'` .*$/m', $atomicLedger, $rowMatch);
            $this->assertNotEmpty($rowMatch, "Missing {$atomicId} row in atomic ledger.");
            $row = $rowMatch[0];

            $this->assertStringContainsString('(`DONE`)', $row);
            $this->assertStringContainsString("`PROJECT_ROADMAP_TASKS_KA.md:{$meta['line']}`", $row);
            $this->assertStringContainsString($meta['heading'], $row);
            $this->assertStringContainsString($evidenceNeedle, $row);
        }

        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:623-647',
            'AT-0001',
            'AT-0002',
            'AT-0003',
            'AT-0004',
            'AT-0005',
            'Install/tests/Unit/ProgramMilestoneExitCriteriaStatusSyncTest.php',
            'Install/tests/Unit/ProgramMilestoneExitCriteriaRiskRegisterVerificationPackGv01SyncTest.php',
            'Install/tests/Unit/ProgramAtomicLedgerMilestoneExitCriteriaAt0001To0005SyncTest.php',
            '`AT-0001..AT-0005` are closed as `DONE` in the atomic ledger',
        ] as $needle) {
            $this->assertStringContainsString($needle, $atomicClosureDoc);
        }

        foreach ([
            '## Milestone Criteria-to-Evidence Matrix (A-D)',
            '### Milestone A — Ecommerce Builder Production MVP',
            '### Milestone B — AI Assisted Store Generation',
            '### Milestone C — Universal Industry Platform',
            '### Milestone D — Learning/Optimization Layer',
        ] as $needle) {
            $this->assertStringContainsString($needle, $gv01Doc);
        }
    }
}
