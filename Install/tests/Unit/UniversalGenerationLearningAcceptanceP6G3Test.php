<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalGenerationLearningAcceptanceP6G3Test extends TestCase
{
    public function test_p6_g3_04_generation_learning_acceptance_suite_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/CMS_GENERATION_LEARNING_ACCEPTANCE_TESTS_P6_G3_04.md');
        $acceptanceTestPath = base_path('tests/Feature/Cms/CmsAiGenerationLearningAcceptanceTest.php');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([$docPath, $acceptanceTestPath] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $acceptance = File::get($acceptanceTestPath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('P6-G3-04', $doc);
        $this->assertStringContainsString('no cross-tenant learned-rule leakage', strtolower($doc));
        $this->assertStringContainsString('deterministic replay', strtolower($doc));
        $this->assertStringContainsString('tenant_opt_out', $doc);
        $this->assertStringContainsString('P6-G3-03', $doc);

        $this->assertStringContainsString('class CmsAiGenerationLearningAcceptanceTest extends TestCase', $acceptance);
        $this->assertStringContainsString('no_cross_tenant_leakage_and_deterministic_replay', $acceptance);
        $this->assertStringContainsString('tenant_opt_out_disables_learning_application_and_replay_metadata', $acceptance);
        $this->assertStringContainsString('replay_key', $acceptance);
        $this->assertStringContainsString('page_plan.reproducibility', $acceptance);

        $this->assertStringContainsString("`P6-G3-04` (✅ `DONE`) Acceptance tests for no cross-tenant leakage / deterministic replay.", $roadmap);
    }
}
