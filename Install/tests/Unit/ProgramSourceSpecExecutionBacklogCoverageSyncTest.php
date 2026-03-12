<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class ProgramSourceSpecExecutionBacklogCoverageSyncTest extends TestCase
{
    public function test_grouped_backlog_and_atomic_ledger_cover_statusless_source_spec_blocks(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $groupedBacklogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $atomicLedgerPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA_ATOMIC_TRANSFER.md');

        foreach ([$roadmapPath, $groupedBacklogPath, $atomicLedgerPath] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $grouped = File::get($groupedBacklogPath);
        $atomic = File::get($atomicLedgerPath);

        // Grouped backlog must document full-transfer coverage and the atomic companion.
        $this->assertStringContainsString('## Full Transfer Coverage Matrix (Added After Full-Roadmap Audit)', $grouped);
        $this->assertStringContainsString('PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA_ATOMIC_TRANSFER.md', $grouped);
        foreach (['GV-*', 'AR-*', 'ECM-*', 'API-*', 'CAG-*', 'SLE-*', 'UDB-*', 'RS-*'] as $prefix) {
            $this->assertStringContainsString($prefix, $grouped);
        }

        // Atomic ledger structure and category coverage.
        $this->assertStringContainsString('## Atomic Entries (463)', $atomic);
        foreach ([
            '### GV —',
            '### AR —',
            '### ECM —',
            '### API —',
            '### CAG —',
            '### SLE —',
            '### UDB —',
            '### RS —',
        ] as $needle) {
            $this->assertStringContainsString($needle, $atomic);
        }

        preg_match_all('/^- `AT-\\d{4}` .*$/m', $atomic, $atomicRows);
        $atomicEntryLines = $atomicRows[0];
        $this->assertCount(463, $atomicEntryLines, 'Atomic ledger entry count drifted; regenerate/update coverage ledger intentionally.');

        // Tracker rows should not be copied into atomic *entries* (ignore explanatory text).
        foreach ($atomicEntryLines as $entryLine) {
            $this->assertStringNotContainsString('`HC-', $entryLine);
            $this->assertStringNotContainsString('`P0-', $entryLine);
            $this->assertStringNotContainsString('`P1-', $entryLine);
            $this->assertStringNotContainsString('`P2-', $entryLine);
            $this->assertStringNotContainsString('`P3-', $entryLine);
            $this->assertStringNotContainsString('`P4-', $entryLine);
            $this->assertStringNotContainsString('`P5-', $entryLine);
            $this->assertStringNotContainsString('`P6-', $entryLine);
        }

        // Every major CODEX PROMPT source block heading in the roadmap must be represented by line ref in atomic ledger.
        preg_match_all('/^# CODEX PROMPT .*$/m', $roadmap, $promptHeadings, PREG_OFFSET_CAPTURE);
        $this->assertCount(6, $promptHeadings[0]);

        foreach ($promptHeadings[0] as [$headingText, $offset]) {
            $line = substr_count(substr($roadmap, 0, (int) $offset), "\n") + 1;
            $this->assertStringContainsString("PROJECT_ROADMAP_TASKS_KA.md:{$line}", $atomic, "Missing atomic line ref for roadmap heading: {$headingText}");
        }

        // Legacy reference-archive preface (outside CODEX PROMPT blocks) must also be transferred.
        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:1057', $atomic);
        $this->assertStringContainsString('## 9. Original Detailed Specs (Reference Archive) Start Below', $atomic);
    }
}
