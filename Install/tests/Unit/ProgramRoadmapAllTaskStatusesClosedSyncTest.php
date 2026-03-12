<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class ProgramRoadmapAllTaskStatusesClosedSyncTest extends TestCase
{
    public function test_wrapper_task_rows_remain_closed_while_optional_hardening_queue_may_carry_open_statuses(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $this->assertFileExists($roadmapPath);

        $roadmap = File::get($roadmapPath);

        // Wrapper execution-board task rows (`P*`) remain fully closed.
        preg_match_all('/^- `P\\d+-[A-Z]\\d+-\\d{2}` \\([^)]*`([A-Z_]+)`\\)/m', $roadmap, $matches);
        $wrapperStatuses = $matches[1] ?? [];

        $this->assertNotEmpty($wrapperStatuses);
        foreach ($wrapperStatuses as $status) {
            $this->assertSame('DONE', $status);
        }

        $lines = preg_split('/\R/', $roadmap) ?: [];
        $openIconLines = array_values(array_filter($lines, static fn (string $line): bool => str_contains($line, '⬜') || str_contains($line, '🟡') || str_contains($line, '🟠') || str_contains($line, '⛔')));

        $allowedLegendLines = [
            '- 🟡 In Progress',
            '- ⬜ Not Started',
            '- 🟠 Stabilize/Hardening',
            '- ⛔ Blocked',
            '- ⬜ `TODO` = not started',
            '- 🟡 `IN_PROGRESS` = actively being built',
            '- 🟠 `STABILIZE` = exists but needs hardening/refactor',
            '- ⛔ `BLOCKED` = dependency missing',
        ];

        foreach ($openIconLines as $line) {
            if (in_array($line, $allowedLegendLines, true)) {
                continue;
            }

            if (preg_match('/^- `HC-[A-Z]\\d+-\\d{2}` \\([^)]*`(?:TODO|IN_PROGRESS|STABILIZE|DONE|BLOCKED)`\\)/', $line) === 1) {
                continue;
            }

            $this->fail("Unexpected open-status roadmap line found: {$line}");
        }
    }
}
