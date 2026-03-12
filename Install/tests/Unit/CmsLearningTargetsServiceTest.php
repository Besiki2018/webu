<?php

namespace Tests\Unit;

use App\Services\CmsLearningTargetsService;
use Tests\TestCase;

class CmsLearningTargetsServiceTest extends TestCase
{
    public function test_it_exposes_canonical_learning_target_catalog_for_conversion_engagement_usability_performance_and_builder_friction(): void
    {
        $service = app(CmsLearningTargetsService::class);
        $summary = $service->catalogSummary();

        $this->assertSame('p6-learning-targets.v1', $summary['version']);
        $this->assertSame(5, $summary['count']);
        $this->assertSame(
            ['conversion', 'engagement', 'usability', 'performance', 'builder_friction'],
            $summary['keys']
        );

        $catalogByKey = collect($summary['targets'])->keyBy('key');
        $this->assertSame('maximize', data_get($catalogByKey, 'conversion.objective'));
        $this->assertContains('conversion_rate', (array) data_get($catalogByKey, 'conversion.primary_metrics', []));
        $this->assertContains('builder_publish_per_open_rate', (array) data_get($catalogByKey, 'conversion.fallback_metrics', []));

        $this->assertSame('minimize', data_get($catalogByKey, 'builder_friction.objective'));
        $this->assertContains('builder_save_warnings_per_draft', (array) data_get($catalogByKey, 'builder_friction.primary_metrics', []));
    }

    public function test_it_returns_deterministic_rule_promotion_metric_priority_from_catalog(): void
    {
        $service = app(CmsLearningTargetsService::class);

        $this->assertSame(
            ['conversion_rate', 'builder_publish_per_open_rate', 'runtime_hydrate_success_rate'],
            $service->metricPriorityForRulePromotion()
        );
    }
}
