<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalRuleMetricPromotionThresholdsP6G2Test extends TestCase
{
    public function test_p6_g2_03_metric_based_rule_promotion_and_rollback_thresholds_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/CMS_RULE_METRIC_PROMOTION_THRESHOLDS_P6_G2_03.md');
        $servicePath = base_path('app/Services/CmsLearnedRuleMetricPromotionService.php');
        $commandPath = base_path('app/Console/Commands/EvaluateCmsLearnedRuleMetrics.php');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([$docPath, $servicePath, $commandPath] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $service = File::get($servicePath);
        $command = File::get($commandPath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('P6-G2-03', $doc);
        $this->assertStringContainsString('compare conversion_rate before/after applying rule', $doc);
        $this->assertStringContainsString('promotion', strtolower($doc));
        $this->assertStringContainsString('rollback', strtolower($doc));
        $this->assertStringContainsString('cms_telemetry_daily_aggregates', $doc);
        $this->assertStringContainsString('P6-G2-04', $doc);

        $this->assertStringContainsString('class CmsLearnedRuleMetricPromotionService', $service);
        $this->assertStringContainsString('evaluateRules(?Site $site = null', $service);
        $this->assertStringContainsString('evaluateRule(CmsLearnedRule $rule', $service);
        $this->assertStringContainsString('conversion_rate', $service);
        $this->assertStringContainsString('builder_publish_per_open_rate', $service);
        $this->assertStringContainsString('rolled_back', $service);
        $this->assertStringContainsString('metric_drop_exceeds_rollback_threshold', $service);

        $this->assertStringContainsString('class EvaluateCmsLearnedRuleMetrics extends Command', $command);
        $this->assertStringContainsString('cms:learned-rules-evaluate-metrics', $command);
        $this->assertStringContainsString('cms.learning.rule_metric_thresholds_evaluated', $command);

        $this->assertStringContainsString('- ✅ Rule learning + metric-based rule promotion', $roadmap);
        $this->assertStringContainsString("`P6-G2-03` (✅ `DONE`) Metric-based rule promotion/rollback thresholds.", $roadmap);
    }
}
