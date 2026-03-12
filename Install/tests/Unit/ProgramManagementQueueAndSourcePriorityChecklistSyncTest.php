<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class ProgramManagementQueueAndSourcePriorityChecklistSyncTest extends TestCase
{
    public function test_queue_status_hygiene_and_source_priority_map_checklist_lines_are_synced(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $paths = [
            base_path('docs/architecture/CMS_PUBLIC_API_CONTRACT_VERSIONING_BASELINE.md'),
            base_path('docs/architecture/CMS_TEMPLATE_IMPORT_BACKWARD_COMPATIBILITY_STRATEGY_BASELINE.md'),
            base_path('docs/architecture/CMS_AI_PAGE_GENERATION_ENGINE_V1.md'),
            base_path('docs/architecture/CMS_TELEMETRY_AGGREGATED_METRICS_P6_G1_03.md'),
            base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts'),
            base_path('tests/Unit/CmsCanonicalSchemaContractsTest.php'),
            base_path('tests/Unit/CmsAiPageGenerationServiceTest.php'),
        ];

        foreach (array_merge([$roadmapPath], $paths) as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $apiVersioningDoc = File::get(base_path('docs/architecture/CMS_PUBLIC_API_CONTRACT_VERSIONING_BASELINE.md'));
        $templateCompatDoc = File::get(base_path('docs/architecture/CMS_TEMPLATE_IMPORT_BACKWARD_COMPATIBILITY_STRATEGY_BASELINE.md'));
        $aiPageGenDoc = File::get(base_path('docs/architecture/CMS_AI_PAGE_GENERATION_ENGINE_V1.md'));
        $telemetryAggDoc = File::get(base_path('docs/architecture/CMS_TELEMETRY_AGGREGATED_METRICS_P6_G1_03.md'));
        $universalLibraryActivationContract = File::get(base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts'));
        $schemaContractsTest = File::get(base_path('tests/Unit/CmsCanonicalSchemaContractsTest.php'));
        $aiPageGenerationTest = File::get(base_path('tests/Unit/CmsAiPageGenerationServiceTest.php'));

        // Checklist statuses synchronized (8.3)
        $this->assertStringContainsString('- ✅ Every active implementation task references a source spec block', $roadmap);
        $this->assertStringContainsString('- ✅ No task is implemented from prompt wording without wrapper dedup mapping check', $roadmap);
        $this->assertStringContainsString('- ✅ Current execution-state section reflects real status (all explicit wrapper tasks closed, optional hardening separated)', $roadmap);
        $this->assertStringContainsString('- ✅ Sprint task statuses updated (`TODO/IN_PROGRESS/STABILIZE/DONE/BLOCKED`)', $roadmap);
        $this->assertStringContainsString('- ✅ Source Priority Map still matches actual implementation behavior', $roadmap);

        // Task template + dedup guardrails (backing 1033/1034)
        $this->assertStringContainsString('## 3.3 Task Execution Template (Use For Every Real Task)', $roadmap);
        $this->assertStringContainsString('- `Source Spec Reference` (section title from lower document):', $roadmap);
        $this->assertStringContainsString('- `No-Duplicate Check` (what duplicate implementation is explicitly avoided):', $roadmap);
        $this->assertStringContainsString('## 6.1 Explicit Deduplication / Replace-Mapping (Applied to This Document)', $roadmap);
        $this->assertStringContainsString('Wrapper dedup mapping is mandatory.', $roadmap);
        $this->assertStringContainsString('When a prompt says "create new X", first check if Webu already has X', $roadmap);

        // Current execution-state block updated to closure + optional hardening mode.
        $this->assertStringContainsString('## 3.8 Current Execution State (Wrapper Tasks Closed)', $roadmap);
        $this->assertStringContainsString('All explicit wrapper phase tasks and sprint task rows in the tracker are closed (`✅`).', $roadmap);
        $this->assertStringContainsString('### Post-Closure Optional Hardening Queue (Not Required for Roadmap Closure)', $roadmap);
        $this->assertStringContainsString('equivalent -> exact` convergence automation', $roadmap);
        $this->assertStringContainsString('Runtime adoption/backfill improvements', $roadmap);
        $this->assertStringContainsString('Alias-map tooling/reporting enhancements', $roadmap);
        $this->assertStringContainsString('### Reopen Rule', $roadmap);
        $this->assertStringContainsString('Do not reopen closed wrapper tasks unless a new explicit scope/phase is added to this document.', $roadmap);

        // Sprint task status hygiene (1054): execution board task lines all use valid status tokens.
        preg_match_all('/^- `P\\d+-[A-Z]\\d+-\\d{2}` \\([^)]*`([A-Z_]+)`\\)/m', $roadmap, $matches);
        $statuses = $matches[1] ?? [];
        $this->assertNotEmpty($statuses);
        $this->assertGreaterThan(80, count($statuses), 'Expected the execution board sprint task list to be present.');

        $allowed = ['TODO', 'IN_PROGRESS', 'STABILIZE', 'DONE', 'BLOCKED'];
        foreach ($statuses as $status) {
            $this->assertContains($status, $allowed);
        }

        // Source Priority Map (1055) still present and aligned to actual implementation evidence.
        $this->assertStringContainsString('## 8.1 Source Priority Map (Which Lower Spec Wins For What)', $roadmap);
        $this->assertStringContainsString('### A. Ecommerce Builder MVP (canonical sources)', $roadmap);
        $this->assertStringContainsString('### B. Universal Builder / Multi-Industry (canonical sources)', $roadmap);
        $this->assertStringContainsString('### C. AI Generation / Automation (canonical sources)', $roadmap);
        $this->assertStringContainsString('### D. In Case of Duplication', $roadmap);
        $this->assertStringContainsString('API endpoint naming conflict: Wrapper dedup mapping + current platform API strategy wins', $roadmap);

        $this->assertStringContainsString('public api contract versioning baseline', strtolower($apiVersioningDoc));
        $this->assertStringContainsString('resolveVersion', $templateCompatDoc);
        $this->assertStringContainsString('page generation', strtolower($aiPageGenDoc));
        $this->assertStringContainsString('aggregated metrics', strtolower($telemetryAggDoc));
        $this->assertStringContainsString('universal component library activation', strtolower($universalLibraryActivationContract));
        $this->assertStringContainsString('cms-ai-generation-output.v1', strtolower($schemaContractsTest));
        $this->assertStringContainsString('CmsAiPageGenerationService', $aiPageGenerationTest);
    }
}
