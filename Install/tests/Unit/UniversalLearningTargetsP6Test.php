<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalLearningTargetsP6Test extends TestCase
{
    public function test_phase6_learning_targets_catalog_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/CMS_LEARNING_TARGETS_P6_BASELINE.md');
        $targetsServicePath = base_path('app/Services/CmsLearningTargetsService.php');
        $promotionServicePath = base_path('app/Services/CmsLearnedRuleMetricPromotionService.php');
        $targetsTestPath = base_path('tests/Unit/CmsLearningTargetsServiceTest.php');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([$docPath, $targetsServicePath, $promotionServicePath, $targetsTestPath] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $targetsService = File::get($targetsServicePath);
        $promotionService = File::get($promotionServicePath);
        $targetsTest = File::get($targetsTestPath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('conversion, engagement, usability, performance, builder friction', strtolower($doc));
        $this->assertStringContainsString('CmsLearningTargetsService', $doc);
        $this->assertStringContainsString('metricPriorityForRulePromotion()', $doc);
        $this->assertStringContainsString('builder_friction', $doc);

        $this->assertStringContainsString('class CmsLearningTargetsService', $targetsService);
        $this->assertStringContainsString('CATALOG_VERSION', $targetsService);
        $this->assertStringContainsString("'conversion'", $targetsService);
        $this->assertStringContainsString("'engagement'", $targetsService);
        $this->assertStringContainsString("'usability'", $targetsService);
        $this->assertStringContainsString("'performance'", $targetsService);
        $this->assertStringContainsString("'builder_friction'", $targetsService);
        $this->assertStringContainsString('metricPriorityForRulePromotion()', $targetsService);

        $this->assertStringContainsString('CmsLearningTargetsService', $promotionService);
        $this->assertStringContainsString('metricPriorityForRulePromotion()', $promotionService);
        $this->assertStringContainsString("'learning_targets' =>", $promotionService);

        $this->assertStringContainsString('CmsLearningTargetsServiceTest', $targetsTest);
        $this->assertStringContainsString('builder_friction', $targetsTest);

        $this->assertStringContainsString('- ✅ Learning targets (conversion, engagement, usability, performance, builder friction)', $roadmap);
    }
}
