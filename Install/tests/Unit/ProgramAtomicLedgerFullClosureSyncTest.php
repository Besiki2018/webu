<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class ProgramAtomicLedgerFullClosureSyncTest extends TestCase
{
    public function test_atomic_ledger_is_fully_closed_and_evidence_linked(): void
    {
        $groupedBacklogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $atomicLedgerPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA_ATOMIC_TRANSFER.md');
        $fullClosureDocPath = base_path('docs/qa/PROGRAM_ATOMIC_LEDGER_FULL_CLOSURE_VERIFICATION_2026_02_26.md');
        $milestoneBatchDocPath = base_path('docs/qa/PROGRAM_ATOMIC_LEDGER_MILESTONE_EXIT_CRITERIA_HEADINGS_VERIFICATION_AT_0001_0005_2026_02_26.md');

        foreach ([
            $groupedBacklogPath,
            $atomicLedgerPath,
            $fullClosureDocPath,
            $milestoneBatchDocPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $groupedBacklog = File::get($groupedBacklogPath);
        $atomicLedger = File::get($atomicLedgerPath);
        $fullClosureDoc = File::get($fullClosureDocPath);

        $this->assertStringContainsString('სტატუსი: `DONE` atomic ledger', $atomicLedger);
        $this->assertStringContainsString('all `AT-0001..AT-0463` rows are now `DONE`', $atomicLedger);

        preg_match_all('/^- `AT-\\d{4}` .*$/m', $atomicLedger, $entryRows);
        $atomicEntryLines = $entryRows[0];

        $this->assertCount(463, $atomicEntryLines);
        $this->assertStringNotContainsString('(`READY_UNVERIFIED`)', $atomicLedger);

        $fullClosureEvidence = 'Evidence: `Install/docs/qa/PROGRAM_ATOMIC_LEDGER_FULL_CLOSURE_VERIFICATION_2026_02_26.md`';
        $milestoneBatchEvidence = 'Evidence: `Install/docs/qa/PROGRAM_ATOMIC_LEDGER_MILESTONE_EXIT_CRITERIA_HEADINGS_VERIFICATION_AT_0001_0005_2026_02_26.md`';

        $fullClosureEvidenceCount = 0;
        $milestoneBatchEvidenceCount = 0;

        foreach ($atomicEntryLines as $row) {
            $this->assertStringContainsString('(`DONE`)', $row);
            $this->assertStringContainsString(' | Evidence: `Install/docs/qa/', $row);

            if (str_contains($row, $fullClosureEvidence)) {
                $fullClosureEvidenceCount++;
            }

            if (str_contains($row, $milestoneBatchEvidence)) {
                $milestoneBatchEvidenceCount++;
            }
        }

        $this->assertSame(458, $fullClosureEvidenceCount);
        $this->assertSame(5, $milestoneBatchEvidenceCount);

        $this->assertStringContainsString(
            '`PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA_ATOMIC_TRANSFER.md` (463 atomic entries; `AT-0001..AT-0463` currently `DONE` with evidence links)',
            $groupedBacklog
        );

        foreach ([
            '# Program Atomic Ledger Full Closure Verification (`AT-0001..AT-0463`)',
            'Atomic rows total: `463`',
            '`DONE` rows: `463`',
            '`READY_UNVERIFIED` rows: `0`',
            'Evidence-tagged rows: `463`',
            '`Install/tests/Unit/ProgramAtomicLedgerFullClosureSyncTest.php`',
            'Atomic transfer ledger closure is complete: every row `AT-0001..AT-0463` is `DONE` and evidence-linked.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $fullClosureDoc);
        }
    }
}
