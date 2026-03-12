<?php

namespace App\Services;

class CmsLearningTargetsService
{
    public const CATALOG_VERSION = 'p6-learning-targets.v1';

    /**
     * Canonical learning targets used by telemetry, experimentation and rule promotion.
     *
     * @return array<int, array<string, mixed>>
     */
    public function catalog(): array
    {
        return [
            [
                'key' => 'conversion',
                'label' => 'Conversion',
                'objective' => 'maximize',
                'status' => 'active',
                'source' => 'cms_telemetry_daily_aggregates.metrics_json.derived_rates',
                'primary_metrics' => ['conversion_rate'],
                'fallback_metrics' => ['builder_publish_per_open_rate', 'runtime_hydrate_success_rate'],
                'sample_dimensions' => ['runtime_route_hydrated_count', 'unique_sessions_runtime', 'unique_sessions_total'],
                'notes' => [
                    'Preferred target for learned rule promotion and rollback thresholds.',
                    'Supports roadmap baseline: compare conversion_rate before/after applying rule.',
                ],
            ],
            [
                'key' => 'engagement',
                'label' => 'Engagement',
                'objective' => 'maximize',
                'status' => 'baseline',
                'source' => 'cms_telemetry_daily_aggregates',
                'primary_metrics' => ['runtime_route_hydrated_count'],
                'fallback_metrics' => ['unique_sessions_runtime', 'total_events'],
                'sample_dimensions' => ['unique_sessions_runtime', 'unique_sessions_total'],
                'notes' => [
                    'Tracks interaction depth and content consumption proxies until richer event funnels land.',
                ],
            ],
            [
                'key' => 'usability',
                'label' => 'Usability',
                'objective' => 'maximize',
                'status' => 'active',
                'source' => 'cms_telemetry_daily_aggregates.metrics_json.derived_rates',
                'primary_metrics' => ['runtime_hydrate_success_rate'],
                'fallback_metrics' => [],
                'sample_dimensions' => ['runtime_route_hydrated_count', 'runtime_hydrate_failed_count'],
                'notes' => [
                    'Runtime hydration success is a direct usability/stability proxy for generated pages.',
                ],
            ],
            [
                'key' => 'performance',
                'label' => 'Performance',
                'objective' => 'maximize',
                'status' => 'baseline_proxy',
                'source' => 'cms_telemetry_daily_aggregates.metrics_json.derived_rates',
                'primary_metrics' => ['runtime_hydrate_success_rate'],
                'fallback_metrics' => ['builder_publish_per_open_rate'],
                'sample_dimensions' => ['runtime_route_hydrated_count', 'runtime_hydrate_failed_count'],
                'notes' => [
                    'Latency p95 targets are deferred; success-rate proxies are used in current baseline.',
                ],
            ],
            [
                'key' => 'builder_friction',
                'label' => 'Builder Friction',
                'objective' => 'minimize',
                'status' => 'active',
                'source' => 'cms_telemetry_daily_aggregates.metrics_json.derived_rates',
                'primary_metrics' => ['builder_save_warnings_per_draft'],
                'fallback_metrics' => [],
                'sample_dimensions' => ['builder_save_draft_count', 'builder_save_warning_total'],
                'notes' => [
                    'Used to identify recurrent UI/validation pain after AI generation when editors refine pages.',
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function catalogSummary(): array
    {
        $catalog = $this->catalog();
        $keys = array_values(array_map(
            static fn (array $target): string => (string) ($target['key'] ?? ''),
            $catalog
        ));

        return [
            'version' => self::CATALOG_VERSION,
            'count' => count($catalog),
            'keys' => $keys,
            'targets' => $catalog,
        ];
    }

    /**
     * Deterministic metric priority used by rule promotion/rollback comparisons.
     *
     * @return array<int, string>
     */
    public function metricPriorityForRulePromotion(): array
    {
        return $this->uniqueMetricsFromTargets([
            'conversion',
            'usability',
            'performance',
        ]);
    }

    /**
     * @param  array<int, string>  $targetKeys
     * @return array<int, string>
     */
    public function uniqueMetricsFromTargets(array $targetKeys): array
    {
        $catalogByKey = [];
        foreach ($this->catalog() as $target) {
            if (! is_array($target) || ! is_string($target['key'] ?? null)) {
                continue;
            }
            $catalogByKey[(string) $target['key']] = $target;
        }

        $metrics = [];
        foreach ($targetKeys as $key) {
            if (! is_string($key) || ! isset($catalogByKey[$key])) {
                continue;
            }

            $target = $catalogByKey[$key];
            foreach (['primary_metrics', 'fallback_metrics'] as $listKey) {
                $list = $target[$listKey] ?? [];
                if (! is_array($list)) {
                    continue;
                }
                foreach ($list as $metric) {
                    if (! is_string($metric) || trim($metric) === '') {
                        continue;
                    }
                    $metrics[] = trim($metric);
                }
            }
        }

        $metrics = array_values(array_unique($metrics));

        return $metrics;
    }
}
