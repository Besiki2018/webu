<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class ProgramManagementTaskTemplateAndCoverageChecklistSyncTest extends TestCase
{
    public function test_source_coverage_register_and_task_template_checklist_lines_match_wrapper_evidence(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $this->assertFileExists($roadmapPath);

        $roadmap = File::get($roadmapPath);

        // Checklist statuses synchronized
        $this->assertStringContainsString('- ✅ Every major lower spec block is present in `Source Coverage Register`', $roadmap);
        $this->assertStringContainsString('- ✅ Task record includes `Current Platform Reuse`', $roadmap);
        $this->assertStringContainsString('- ✅ Task record includes `Change Type`', $roadmap);
        $this->assertStringContainsString('- ✅ Task record includes `No-Duplicate Check`', $roadmap);

        // Task execution template fields (3.3)
        $this->assertStringContainsString('## 3.3 Task Execution Template (Use For Every Real Task)', $roadmap);
        $this->assertStringContainsString('- `Current Platform Reuse` (what existing code/module/API is reused):', $roadmap);
        $this->assertStringContainsString('- `Change Type` (`Extend existing` / `Refactor existing` / `Replace with migration`):', $roadmap);
        $this->assertStringContainsString('- `No-Duplicate Check` (what duplicate implementation is explicitly avoided):', $roadmap);
        $this->assertStringContainsString('This prevents prompt wording from creating parallel systems unintentionally.', $roadmap);

        // Risk/mitigation guardrails reinforce template requirements
        $this->assertStringContainsString('Require `No-Duplicate Check` in every task record.', $roadmap);
        $this->assertStringContainsString('Task template requires "Current Platform Reuse" and "Change Type".', $roadmap);

        // Source coverage register exists and enumerates major lower spec blocks
        $this->assertStringContainsString('## 8.2 Source Coverage Register (All Major Lower Spec Blocks Mapped)', $roadmap);
        $this->assertStringContainsString('### Covered Major Blocks (mapped)', $roadmap);

        foreach ([
            'Webu Builder + E-commerce Components + New Theme (Full Integration)',
            'Webu E-commerce Builder Components: Props Schema + Controls + API Bindings (v1)',
            'Webu Backend → Builder Integration Contract (Exact API Spec v1)',
            'AI website generation pipeline',
            'Webu Component Auto-Generator Engine (Backend → Builder Component Factory)',
            'Webu AI Self-Learning Engine (Feedback → Better Generations)',
            'Webu Universal DB Schema (Multi-tenant, Projects, Any Industry) v1',
            'Webu Universal Component Library Spec (Elementor-level, All Industries)',
        ] as $majorBlockLabel) {
            $this->assertStringContainsString($majorBlockLabel, $roadmap);
        }
    }
}

