<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class ProgramRemainingTasksFinalAuditSyncTest extends TestCase
{
    public function test_final_audit_doc_exists_and_core_summary_lines_are_present(): void
    {
        $auditPath = base_path('docs/qa/PROGRAM_REMAINING_TASKS_FINAL_AUDIT_2026_02_26.md');
        $this->assertFileExists($auditPath);

        $audit = File::get($auditPath);

        foreach ([
            'Roadmap execution task rows (`P*` + `HC-*`)',
            'Total tracked rows: `130`',
            'Non-`DONE` rows: `0`',
            'Open task rows with `TODO/IN_PROGRESS/BLOCKED/STABILIZE/READY/PENDING`: `0`',
            '`AT-*` rows with `READY_UNVERIFIED`: `0`',
            '`AT-*` rows with `DONE`: `463`',
            'Open checklist items (`- [ ]`) across roadmap/backlog/QA docs: `0`',
            'Historical Baseline Status Normalization (`2026-02-27`)',
            '`Status: IN_PROGRESS` lines in QA docs: `0`',
            'lines with `Status: BASELINE_RECORDED`: `18`',
            'No actionable remaining execution task was found',
        ] as $needle) {
            $this->assertStringContainsString($needle, $audit);
        }
    }

    public function test_machine_parity_for_final_audit_counts(): void
    {
        $roadmap = File::get(base_path('../PROJECT_ROADMAP_TASKS_KA.md'));
        $groupedBacklog = File::get(base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md'));
        $atomicLedger = File::get(base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA_ATOMIC_TRANSFER.md'));

        preg_match_all('/^- `(?:P\d+-[A-Z]\d+-\d{2}|HC-[A-Z]\d+-\d{2})` \(([^)]*)\)/m', $roadmap, $roadmapStatuses);
        $this->assertCount(130, $roadmapStatuses[1] ?? []);
        foreach ($roadmapStatuses[1] as $statusCell) {
            $this->assertStringContainsString('DONE', $statusCell);
        }

        $this->assertSame(
            0,
            preg_match_all('/^- `(?:GV|AR|ECM|API|CAG|SLE|UDB|RS)-[^`]+` \(`(?:TODO|IN_PROGRESS|BLOCKED|STABILIZE|READY|PENDING)`\)/m', $groupedBacklog, $ignored)
        );

        $this->assertSame(
            0,
            preg_match_all('/^- `AT-\d{4}` \(`READY_UNVERIFIED`\)/m', $atomicLedger, $readyRows)
        );

        $this->assertSame(
            463,
            preg_match_all('/^- `AT-\d{4}` \(`DONE`\)/m', $atomicLedger, $doneRows)
        );
    }

    public function test_historical_baseline_docs_use_baseline_recorded_status_and_in_progress_status_lines_are_zero(): void
    {
        $qaDir = base_path('docs/qa');
        $allQaFiles = collect(File::files($qaDir))->map->getFilename();

        $baselineFiles = $allQaFiles
            ->filter(static fn (string $name): bool => str_contains($name, 'BASELINE_GAP_AUDIT') && str_ends_with($name, '_2026_02_25.md'))
            ->values();

        $this->assertCount(18, $baselineFiles);

        $baselineRecordedCount = 0;
        foreach ($baselineFiles as $fileName) {
            $doc = File::get($qaDir.'/'.$fileName);
            if (str_contains($doc, 'Status: `BASELINE_RECORDED`')) {
                $baselineRecordedCount++;
            }
            $this->assertStringContainsString('## Follow-up Closure Note (2026-02-26)', $doc);
        }

        $this->assertSame(18, $baselineRecordedCount);

        $qaInProgressStatusCount = 0;
        foreach ($allQaFiles as $fileName) {
            $doc = File::get($qaDir.'/'.$fileName);
            $qaInProgressStatusCount += preg_match_all('/^Status: `IN_PROGRESS`/m', $doc, $ignored);
        }

        $this->assertSame(0, $qaInProgressStatusCount);

        $docPaths = [
            base_path('../PROJECT_ROADMAP_TASKS_KA.md'),
            base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md'),
            base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA_ATOMIC_TRANSFER.md'),
        ];
        $docPaths = array_merge($docPaths, File::glob($qaDir.'/*.md') ?: []);

        $openChecklistLines = 0;
        foreach ($docPaths as $path) {
            $openChecklistLines += preg_match_all('/^- \[ \]/m', File::get($path), $ignored);
        }

        $this->assertSame(0, $openChecklistLines);
    }
}
