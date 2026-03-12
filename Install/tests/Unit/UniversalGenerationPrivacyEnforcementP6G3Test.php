<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalGenerationPrivacyEnforcementP6G3Test extends TestCase
{
    public function test_p6_g3_03_generation_privacy_enforcement_and_tenant_opt_out_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/CMS_GENERATION_PRIVACY_ENFORCEMENT_P6_G3_03.md');
        $policyServicePath = base_path('app/Services/CmsAiLearningPrivacyPolicyService.php');
        $learnedRuleServicePath = base_path('app/Services/CmsAiLearnedRuleApplicationService.php');
        $pageGenServicePath = base_path('app/Services/CmsAiPageGenerationService.php');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([$docPath, $policyServicePath, $learnedRuleServicePath, $pageGenServicePath] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $policyService = File::get($policyServicePath);
        $learnedRuleService = File::get($learnedRuleServicePath);
        $pageGenService = File::get($pageGenServicePath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('P6-G3-03', $doc);
        $this->assertStringContainsString('tenant/site opt-out', strtolower($doc));
        $this->assertStringContainsString('cms_ai_learning_generation_enabled', $doc);
        $this->assertStringContainsString('cms_ai_learning_allow_global_rules', $doc);
        $this->assertStringContainsString('cms_ai_learning_reproducibility_enabled', $doc);
        $this->assertStringContainsString('P6-G3-04', $doc);

        $this->assertStringContainsString('class CmsAiLearningPrivacyPolicyService', $policyService);
        $this->assertStringContainsString('FLAG_GENERATION_LEARNING_ENABLED', $policyService);
        $this->assertStringContainsString('FLAG_ALLOW_GLOBAL_LEARNED_RULES', $policyService);
        $this->assertStringContainsString('FLAG_REPRODUCIBILITY_ENABLED', $policyService);
        $this->assertStringContainsString('resolveForAiInput(array $aiInput)', $policyService);

        $this->assertStringContainsString("'privacy_enforcement' =>", $learnedRuleService);
        $this->assertStringContainsString('tenant_opt_out', $learnedRuleService);
        $this->assertStringContainsString('allow_global_learned_rules', $learnedRuleService);
        $this->assertStringContainsString('system_learning_generation_disabled', $learnedRuleService);

        $this->assertStringContainsString('CmsAiLearningPrivacyPolicyService', $pageGenService);
        $this->assertStringContainsString("'learning_privacy' =>", $pageGenService);
        $this->assertStringContainsString("'enabled' => false", $pageGenService);
        $this->assertStringContainsString("'reason' =>", $pageGenService);

        $this->assertStringContainsString("`P6-G3-03` (✅ `DONE`) Tenant opt-out and privacy enforcement paths.", $roadmap);
    }
}
