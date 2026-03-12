<?php

namespace App\Services;

use App\Models\CmsLearnedRule;
use App\Models\CmsTelemetryDailyAggregate;
use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CmsLearnedRuleMetricPromotionService
{
    private const DEFAULT_MIN_BEFORE_SAMPLES = 100;

    private const DEFAULT_MIN_AFTER_SAMPLES = 100;

    private const DEFAULT_MIN_PROMOTION_UPLIFT = 0.02;

    private const DEFAULT_ROLLBACK_DROP_THRESHOLD = 0.02;

    private const DEFAULT_BEFORE_DAYS = 14;

    private const DEFAULT_AFTER_DAYS = 14;

    public function __construct(
        protected CmsLearningTargetsService $learningTargets
    ) {}

    /**
     * Evaluate candidate/active learned rules with metric thresholds and apply deterministic promotion/rollback decisions.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function evaluateRules(?Site $site = null, string|Carbon|null $now = null, array $options = []): array
    {
        $clock = $this->normalizeTime($now) ?? now();
        $config = $this->normalizeOptions($options);

        $query = CmsLearnedRule::query()
            ->whereIn('status', ['candidate', 'active'])
            ->orderBy('id');

        if ($site instanceof Site) {
            $query->where('site_id', (string) $site->id);
        }

        /** @var Collection<int, CmsLearnedRule> $rules */
        $rules = $query->get();

        $rows = [];
        $promoted = 0;
        $rolledBack = 0;
        $unchanged = 0;
        $skipped = 0;

        foreach ($rules as $rule) {
            $result = $this->evaluateRule($rule, $clock, $config);
            $rows[] = $result;

            $decision = (string) ($result['decision'] ?? 'skipped');
            if ($decision === 'promoted') {
                $promoted++;
            } elseif ($decision === 'rolled_back') {
                $rolledBack++;
            } elseif ($decision === 'no_change') {
                $unchanged++;
            } else {
                $skipped++;
            }
        }

        return [
            'ok' => true,
            'evaluated_at' => $clock->toISOString(),
            'site_id' => $site?->id ? (string) $site->id : null,
            'project_id' => $site?->project_id ? (string) $site->project_id : null,
            'thresholds' => $config,
            'learning_targets' => [
                'catalog_version' => CmsLearningTargetsService::CATALOG_VERSION,
                'rule_promotion_metric_priority' => $this->learningTargets->metricPriorityForRulePromotion(),
                'catalog_keys' => array_values((array) data_get($this->learningTargets->catalogSummary(), 'keys', [])),
            ],
            'evaluated_rules' => $rules->count(),
            'promoted' => $promoted,
            'rolled_back' => $rolledBack,
            'unchanged' => $unchanged,
            'skipped' => $skipped,
            'rows' => $rows,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function evaluateRule(CmsLearnedRule $rule, Carbon $clock, array $config): array
    {
        if (! in_array((string) $rule->status, ['candidate', 'active'], true)) {
            return $this->resultRow($rule, 'skipped', 'unsupported_status', [
                'status' => (string) $rule->status,
            ]);
        }

        if ((string) $rule->status === 'candidate' && ! $rule->active) {
            $comparison = $this->resolveCandidateMetricComparison($rule);
            if (! is_array($comparison)) {
                $result = $this->resultRow($rule, 'skipped', 'metric_observation_missing');
                $this->persistMetricEvaluationReport($rule, $result, $clock);

                return $result;
            }

            $validation = $this->validateComparisonSamples($comparison, $config);
            if ($validation !== null) {
                $result = $this->resultRow($rule, 'no_change', $validation, ['comparison' => $comparison]);
                $this->persistMetricEvaluationReport($rule, $result, $clock);

                return $result;
            }

            $uplift = (float) ($comparison['uplift'] ?? 0.0);
            if ($uplift >= (float) $config['min_promotion_uplift']) {
                $rule->status = 'active';
                $rule->active = true;
                $rule->promoted_at = $clock->copy();
                $rule->disabled_at = null;
                $rule->save();

                $result = $this->resultRow($rule, 'promoted', 'uplift_threshold_met', ['comparison' => $comparison]);
                $this->persistMetricEvaluationReport($rule, $result, $clock);

                return $result;
            }

            $result = $this->resultRow($rule, 'no_change', 'uplift_below_promotion_threshold', ['comparison' => $comparison]);
            $this->persistMetricEvaluationReport($rule, $result, $clock);

            return $result;
        }

        $comparison = $this->resolveActiveRuleAggregateComparison($rule, $clock, $config);
        if (! is_array($comparison)) {
            $result = $this->resultRow($rule, 'skipped', 'aggregate_comparison_unavailable');
            $this->persistMetricEvaluationReport($rule, $result, $clock);

            return $result;
        }

        $validation = $this->validateComparisonSamples($comparison, $config);
        if ($validation !== null) {
            $result = $this->resultRow($rule, 'no_change', $validation, ['comparison' => $comparison]);
            $this->persistMetricEvaluationReport($rule, $result, $clock);

            return $result;
        }

        $uplift = (float) ($comparison['uplift'] ?? 0.0);
        if ($uplift <= -1 * (float) $config['rollback_drop_threshold']) {
            $rule->status = 'rolled_back';
            $rule->active = false;
            $rule->disabled_at = $clock->copy();
            $rule->save();

            $result = $this->resultRow($rule, 'rolled_back', 'metric_drop_exceeds_rollback_threshold', ['comparison' => $comparison]);
            $this->persistMetricEvaluationReport($rule, $result, $clock);

            return $result;
        }

        $result = $this->resultRow($rule, 'no_change', 'active_rule_within_thresholds', ['comparison' => $comparison]);
        $this->persistMetricEvaluationReport($rule, $result, $clock);

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function resolveCandidateMetricComparison(CmsLearnedRule $rule): ?array
    {
        $evidence = is_array($rule->evidence_json) ? $rule->evidence_json : [];

        $single = data_get($evidence, 'metric_observation');
        if (is_array($single)) {
            return $this->normalizeMetricObservation($single, 'evidence.metric_observation');
        }

        $observations = data_get($evidence, 'metric_observations');
        if (! is_array($observations)) {
            return null;
        }

        $preferredOrder = $this->learningTargets->metricPriorityForRulePromotion();
        $normalized = [];
        foreach ($observations as $row) {
            if (! is_array($row)) {
                continue;
            }
            $candidate = $this->normalizeMetricObservation($row, 'evidence.metric_observations');
            if (! is_array($candidate)) {
                continue;
            }
            $normalized[] = $candidate;
        }

        if ($normalized === []) {
            return null;
        }

        usort($normalized, function (array $a, array $b) use ($preferredOrder): int {
            $idxA = array_search((string) ($a['metric'] ?? ''), $preferredOrder, true);
            $idxB = array_search((string) ($b['metric'] ?? ''), $preferredOrder, true);
            $rankA = is_int($idxA) ? $idxA : 999;
            $rankB = is_int($idxB) ? $idxB : 999;
            if ($rankA !== $rankB) {
                return $rankA <=> $rankB;
            }

            return ((int) ($b['after_samples'] ?? 0)) <=> ((int) ($a['after_samples'] ?? 0));
        });

        return $normalized[0];
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>|null
     */
    private function normalizeMetricObservation(array $raw, string $source): ?array
    {
        $metric = $this->safeString($raw['metric'] ?? null, 80);
        if ($metric === '') {
            return null;
        }

        $before = $this->toFloat($raw['before'] ?? $raw['before_value'] ?? null);
        $after = $this->toFloat($raw['after'] ?? $raw['after_value'] ?? null);
        $beforeSamples = $this->toInt($raw['before_samples'] ?? null);
        $afterSamples = $this->toInt($raw['after_samples'] ?? null);

        if ($before === null || $after === null) {
            return null;
        }

        return [
            'source' => $source,
            'metric' => $metric,
            'before' => round($before, 6),
            'after' => round($after, 6),
            'uplift' => round($after - $before, 6),
            'relative_uplift' => $before != 0.0 ? round(($after - $before) / $before, 6) : null,
            'before_samples' => max(0, $beforeSamples ?? 0),
            'after_samples' => max(0, $afterSamples ?? 0),
            'meta' => is_array($raw['meta'] ?? null) ? $raw['meta'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>|null
     */
    private function resolveActiveRuleAggregateComparison(CmsLearnedRule $rule, Carbon $clock, array $config): ?array
    {
        if (! is_string($rule->site_id) || trim($rule->site_id) === '') {
            return null;
        }

        if (! $rule->promoted_at instanceof Carbon) {
            return null;
        }

        $beforeDays = (int) $config['before_days'];
        $afterDays = (int) $config['after_days'];
        $pivot = $rule->promoted_at->copy();

        $beforeStart = $pivot->copy()->subDays($beforeDays)->startOfDay();
        $beforeEnd = $pivot->copy()->subDay()->endOfDay();
        $afterStart = $pivot->copy()->startOfDay();
        $afterEnd = $clock->copy()->endOfDay();
        $maxAfterEnd = $pivot->copy()->addDays($afterDays)->endOfDay();
        if ($afterEnd->greaterThan($maxAfterEnd)) {
            $afterEnd = $maxAfterEnd;
        }

        if ($beforeEnd->lessThan($beforeStart) || $afterEnd->lessThan($afterStart)) {
            return null;
        }

        /** @var Collection<int, CmsTelemetryDailyAggregate> $rows */
        $rows = CmsTelemetryDailyAggregate::query()
            ->where('site_id', (string) $rule->site_id)
            ->whereBetween('metric_date', [$beforeStart->toDateString(), $afterEnd->toDateString()])
            ->orderBy('metric_date')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $beforeRows = $rows->filter(function (CmsTelemetryDailyAggregate $row) use ($beforeStart, $beforeEnd): bool {
            $date = $row->metric_date;
            if (! $date instanceof Carbon) {
                return false;
            }

            return $date->copy()->startOfDay()->betweenIncluded($beforeStart, $beforeEnd);
        })->values();

        $afterRows = $rows->filter(function (CmsTelemetryDailyAggregate $row) use ($afterStart, $afterEnd): bool {
            $date = $row->metric_date;
            if (! $date instanceof Carbon) {
                return false;
            }

            return $date->copy()->startOfDay()->betweenIncluded($afterStart, $afterEnd);
        })->values();

        if ($beforeRows->isEmpty() || $afterRows->isEmpty()) {
            return null;
        }

        $metricCandidates = $this->learningTargets->metricPriorityForRulePromotion();
        foreach ($metricCandidates as $metric) {
            $beforeAgg = $this->aggregateMetricWindow($beforeRows, $metric);
            $afterAgg = $this->aggregateMetricWindow($afterRows, $metric);

            if ($beforeAgg === null || $afterAgg === null) {
                continue;
            }

            return [
                'source' => 'telemetry_daily_aggregates',
                'metric' => $metric,
                'before' => $beforeAgg['value'],
                'after' => $afterAgg['value'],
                'uplift' => round($afterAgg['value'] - $beforeAgg['value'], 6),
                'relative_uplift' => $beforeAgg['value'] != 0.0
                    ? round(($afterAgg['value'] - $beforeAgg['value']) / $beforeAgg['value'], 6)
                    : null,
                'before_samples' => $beforeAgg['samples'],
                'after_samples' => $afterAgg['samples'],
                'meta' => [
                    'before_window' => [
                        'start' => $beforeStart->toDateString(),
                        'end' => $beforeEnd->toDateString(),
                        'days_present' => $beforeAgg['days_present'],
                    ],
                    'after_window' => [
                        'start' => $afterStart->toDateString(),
                        'end' => $afterEnd->toDateString(),
                        'days_present' => $afterAgg['days_present'],
                    ],
                ],
            ];
        }

        return null;
    }

    /**
     * @param  Collection<int, CmsTelemetryDailyAggregate>  $rows
     * @return array{value:float,samples:int,days_present:int}|null
     */
    private function aggregateMetricWindow(Collection $rows, string $metric): ?array
    {
        $weightedSum = 0.0;
        $sampleSum = 0;
        $daysPresent = 0;

        foreach ($rows as $row) {
            $value = $this->extractAggregateMetricValue($row, $metric);
            if ($value === null) {
                continue;
            }

            $samples = $this->extractAggregateMetricSamples($row, $metric);
            if ($samples <= 0) {
                continue;
            }

            $daysPresent++;
            $sampleSum += $samples;
            $weightedSum += ($value * $samples);
        }

        if ($sampleSum <= 0 || $daysPresent === 0) {
            return null;
        }

        return [
            'value' => round($weightedSum / $sampleSum, 6),
            'samples' => $sampleSum,
            'days_present' => $daysPresent,
        ];
    }

    private function extractAggregateMetricValue(CmsTelemetryDailyAggregate $row, string $metric): ?float
    {
        $derivedRates = is_array($row->metrics_json) ? data_get($row->metrics_json, 'derived_rates', []) : [];

        if ($metric === 'conversion_rate') {
            $value = $this->toFloat(data_get($derivedRates, 'conversion_rate'));
            if ($value !== null) {
                return round($value, 6);
            }

            // Transitional proxy while direct conversion metric is not widely emitted.
            $proxy = $this->toFloat(data_get($derivedRates, 'builder_publish_per_open_rate'));
            if ($proxy !== null) {
                return round($proxy, 6);
            }

            return null;
        }

        return ($this->toFloat(data_get($derivedRates, $metric)) !== null)
            ? round((float) data_get($derivedRates, $metric), 6)
            : null;
    }

    private function extractAggregateMetricSamples(CmsTelemetryDailyAggregate $row, string $metric): int
    {
        $samplesFromJson = $this->toInt(data_get($row->metrics_json, 'derived_rates_samples.'.$metric));
        if ($samplesFromJson !== null && $samplesFromJson > 0) {
            return $samplesFromJson;
        }

        return match ($metric) {
            'conversion_rate' => max(
                (int) $row->runtime_route_hydrated_count,
                (int) $row->unique_sessions_runtime,
                (int) $row->unique_sessions_total
            ),
            'builder_publish_per_open_rate' => max(0, (int) $row->builder_open_count),
            'runtime_hydrate_success_rate' => max(0, (int) $row->runtime_route_hydrated_count + (int) $row->runtime_hydrate_failed_count),
            default => max(0, (int) $row->unique_sessions_total),
        };
    }

    /**
     * @param  array<string, mixed>  $comparison
     * @param  array<string, mixed>  $config
     */
    private function validateComparisonSamples(array $comparison, array $config): ?string
    {
        $beforeSamples = (int) ($comparison['before_samples'] ?? 0);
        $afterSamples = (int) ($comparison['after_samples'] ?? 0);

        if ($beforeSamples < (int) $config['min_before_samples']) {
            return 'before_sample_threshold_not_met';
        }

        if ($afterSamples < (int) $config['min_after_samples']) {
            return 'after_sample_threshold_not_met';
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function persistMetricEvaluationReport(CmsLearnedRule $rule, array $result, Carbon $clock): void
    {
        $evidence = is_array($rule->evidence_json) ? $rule->evidence_json : [];
        $history = is_array(data_get($evidence, 'metric_evaluation.history')) ? data_get($evidence, 'metric_evaluation.history') : [];

        $entry = [
            'evaluated_at' => $clock->toISOString(),
            'decision' => $result['decision'] ?? 'skipped',
            'reason' => $result['reason'] ?? null,
            'metric' => data_get($result, 'comparison.metric'),
            'before' => data_get($result, 'comparison.before'),
            'after' => data_get($result, 'comparison.after'),
            'uplift' => data_get($result, 'comparison.uplift'),
            'before_samples' => data_get($result, 'comparison.before_samples'),
            'after_samples' => data_get($result, 'comparison.after_samples'),
            'source' => data_get($result, 'comparison.source'),
        ];

        $history[] = $entry;
        $history = array_slice($history, -20);

        data_set($evidence, 'metric_evaluation.last_result', $entry);
        data_set($evidence, 'metric_evaluation.history', $history);
        $rule->evidence_json = $evidence;
        $rule->save();
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function resultRow(CmsLearnedRule $rule, string $decision, string $reason, array $extra = []): array
    {
        return array_merge([
            'rule_id' => $rule->id,
            'rule_key' => $rule->rule_key,
            'site_id' => $rule->site_id,
            'project_id' => $rule->project_id,
            'status' => (string) $rule->status,
            'active' => (bool) $rule->active,
            'decision' => $decision,
            'reason' => $reason,
        ], $extra);
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    private function normalizeOptions(array $options): array
    {
        return [
            'min_before_samples' => max(1, $this->toInt($options['min_before_samples'] ?? null) ?? self::DEFAULT_MIN_BEFORE_SAMPLES),
            'min_after_samples' => max(1, $this->toInt($options['min_after_samples'] ?? null) ?? self::DEFAULT_MIN_AFTER_SAMPLES),
            'min_promotion_uplift' => max(0.0, $this->toFloat($options['min_promotion_uplift'] ?? null) ?? self::DEFAULT_MIN_PROMOTION_UPLIFT),
            'rollback_drop_threshold' => max(0.0, $this->toFloat($options['rollback_drop_threshold'] ?? null) ?? self::DEFAULT_ROLLBACK_DROP_THRESHOLD),
            'before_days' => max(1, min(180, $this->toInt($options['before_days'] ?? null) ?? self::DEFAULT_BEFORE_DAYS)),
            'after_days' => max(1, min(180, $this->toInt($options['after_days'] ?? null) ?? self::DEFAULT_AFTER_DAYS)),
        ];
    }

    private function normalizeTime(string|Carbon|null $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if (is_string($value) && trim($value) !== '') {
            return Carbon::parse($value);
        }

        return null;
    }

    private function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function toFloat(mixed $value): ?float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }
        if (is_string($value) && is_numeric(trim($value))) {
            return (float) trim($value);
        }

        return null;
    }

    private function safeString(mixed $value, int $max): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        return mb_substr($normalized, 0, max(1, $max));
    }
}
