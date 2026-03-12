<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalGenerationTimeLearnedRuleApplicationP6G3Test extends TestCase
{
    public function test_p6_g3_01_generation_time_learned_rule_fetch_apply_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/CMS_GENERATION_TIME_LEARNED_RULE_APPLICATION_P6_G3_01.md');
        $servicePath = base_path('app/Services/CmsAiLearnedRuleApplicationService.php');
        $pageGenPath = base_path('app/Services/CmsAiPageGenerationService.php');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([$docPath, $servicePath, $pageGenPath] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $service = File::get($servicePath);
        $pageGen = File::get($pageGenPath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('P6-G3-01', $doc);
        $this->assertStringContainsString('deterministic order', strtolower($doc));
        $this->assertStringContainsString('generation_version', $doc);
        $this->assertStringContainsString('applied_rules', $doc);
        $this->assertStringContainsString('no conflicts allowed', strtolower($doc));
        $this->assertStringContainsString('P6-G3-02', $doc);

        $this->assertStringContainsString('class CmsAiLearnedRuleApplicationService', $service);
        $this->assertStringContainsString('GENERATION_VERSION', $service);
        $this->assertStringContainsString('applyToGeneratedPages(array $pages, array $aiInput, array $context = [])', $service);
        $this->assertStringContainsString('orderByDesc(\'confidence\')', $service);
        $this->assertStringContainsString('orderByDesc(\'sample_size\')', $service);
        $this->assertStringContainsString('conflict_with_higher_priority_rule', $service);
        $this->assertStringContainsString('generation_version', $service);
        $this->assertStringContainsString('applied_rules', $service);

        $this->assertStringContainsString('CmsAiLearnedRuleApplicationService', $pageGen);
        $this->assertStringContainsString('learnedRuleApplication->applyToGeneratedPages(', $pageGen);
        $this->assertStringContainsString("'learning_generation_version'", $pageGen);
        $this->assertStringContainsString("'applied_rules'", $pageGen);
        $this->assertStringContainsString("'learned_rules_application'", $pageGen);

        $this->assertStringContainsString('- ✅ Generation-time deterministic patch application', $roadmap);
        $this->assertStringContainsString("`P6-G3-01` (✅ `DONE`) Fetch/apply learned rules during generation in deterministic order.", $roadmap);
    }
}
