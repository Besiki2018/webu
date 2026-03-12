<?php

namespace App\Services;

use App\Models\CmsTelemetryDailyAggregate;
use App\Models\CmsTelemetryEvent;
use App\Models\Site;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class CmsTelemetryAggregatedMetricsService
{
    /**
     * Aggregate and upsert daily telemetry metrics for the provided date.
     *
     * @return array<string, mixed>
     */
    public function aggregateDate(string|Carbon|null $date = null): array
    {
        $metricDate = $this->normalizeDate($date);
        $start = $metricDate->copy()->startOfDay();
        $end = $metricDate->copy()->endOfDay();

        /** @var Collection<int, CmsTelemetryEvent> $events */
        $events = CmsTelemetryEvent::query()
            ->whereNotNull('occurred_at')
            ->whereBetween('occurred_at', [$start, $end])
            ->orderBy('id')
            ->get();

        $grouped = $events->groupBy(fn (CmsTelemetryEvent $event): string => (string) $event->site_id);
        $rows = [];
        $upserted = 0;
        $metricDateKey = $metricDate->copy()->startOfDay()->toDateTimeString();

        foreach ($grouped as $siteId => $siteEvents) {
            if (! $siteEvents instanceof Collection || $siteEvents->isEmpty()) {
                continue;
            }

            /** @var CmsTelemetryEvent $first */
            $first = $siteEvents->first();
            $aggregatePayload = $this->buildAggregatePayload($metricDate, $siteId, (string) $first->project_id, $siteEvents);

            CmsTelemetryDailyAggregate::query()->updateOrCreate(
                [
                    'metric_date' => $metricDateKey,
                    'site_id' => $siteId,
                ],
                $aggregatePayload
            );

            $rows[] = [
                'site_id' => $siteId,
                'project_id' => (string) $first->project_id,
                'total_events' => (int) $aggregatePayload['total_events'],
                'page_views' => (int) $aggregatePayload['runtime_route_hydrated_count'],
                'builder_saves' => (int) $aggregatePayload['builder_save_draft_count'],
            ];
            $upserted++;
        }

        return [
            'metric_date' => $metricDate->toDateString(),
            'source_events' => $events->count(),
            'site_groups' => $grouped->count(),
            'upserted' => $upserted,
            'rows' => $rows,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function siteSeries(Site $site, int $days = 30): array
    {
        $limit = max(1, min(365, $days));

        return CmsTelemetryDailyAggregate::query()
            ->where('site_id', (string) $site->id)
            ->orderByDesc('metric_date')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(fn (CmsTelemetryDailyAggregate $row): array => [
                'metric_date' => $row->metric_date?->toDateString(),
                'total_events' => (int) $row->total_events,
                'builder_events' => (int) $row->builder_events,
                'runtime_events' => (int) $row->runtime_events,
                'api_events' => (int) data_get($row->metrics_json, 'api_metrics.request_completed_count', 0),
                'page_views' => (int) $row->runtime_route_hydrated_count,
                'builder_saves' => (int) $row->builder_save_draft_count,
                'builder_publishes' => (int) $row->builder_publish_page_count,
                'builder_publish_failures' => (int) data_get($row->metrics_json, 'error_metrics.builder_publish_failed_count', 0),
                'api_error_count' => (int) data_get($row->metrics_json, 'error_metrics.api_error_count', 0),
                'builder_editor_performance' => data_get($row->metrics_json, 'builder_editor_performance', []),
                'unique_sessions_total' => (int) $row->unique_sessions_total,
                'derived_rates' => data_get($row->metrics_json, 'derived_rates', []),
                'trace_flow_counts' => data_get($row->metrics_json, 'trace_flow_counts', []),
            ])
            ->all();
    }

    private function normalizeDate(string|Carbon|null $date): Carbon
    {
        if ($date instanceof Carbon) {
            return $date->copy();
        }

        if (is_string($date) && trim($date) !== '') {
            return Carbon::parse($date);
        }

        return now();
    }

    /**
     * @param  Collection<int, CmsTelemetryEvent>  $events
     * @return array<string, mixed>
     */
    private function buildAggregatePayload(Carbon $metricDate, string $siteId, string $projectId, Collection $events): array
    {
        $totalEvents = $events->count();
        $builderEvents = $events->where('source', 'builder');
        $runtimeEvents = $events->where('source', 'runtime');
        $apiEvents = $events->where('source', 'api');

        $eventNameCounts = $events
            ->groupBy('event_name')
            ->map(fn (Collection $items): int => $items->count())
            ->sortKeys()
            ->all();

        $pageViewCounts = $runtimeEvents
            ->where('event_name', 'cms_runtime.route_hydrated')
            ->map(function (CmsTelemetryEvent $event): string {
                $slug = $event->page_slug ?: $event->route_slug;
                return is_string($slug) && trim($slug) !== '' ? trim($slug) : 'unknown';
            })
            ->countBy()
            ->sortKeys()
            ->all();

        $builderSaveWarningTotal = $builderEvents
            ->where('event_name', 'cms_builder.save_draft')
            ->sum(function (CmsTelemetryEvent $event): int {
                $warningCount = data_get($event->meta_json, 'warning_count');
                if (is_int($warningCount)) {
                    return $warningCount;
                }
                if (is_numeric($warningCount)) {
                    return (int) $warningCount;
                }

                return 0;
            });

        $builderOpenCount = (int) ($eventNameCounts['cms_builder.open'] ?? 0);
        $builderSaveDraftCount = (int) ($eventNameCounts['cms_builder.save_draft'] ?? 0);
        $builderPublishCount = (int) ($eventNameCounts['cms_builder.publish_page'] ?? 0);
        $builderPublishFailedCount = (int) ($eventNameCounts['cms_builder.publish_page_failed'] ?? 0);
        $runtimeRouteHydratedCount = (int) ($eventNameCounts['cms_runtime.route_hydrated'] ?? 0);
        $runtimeHydrateFailedCount = (int) ($eventNameCounts['cms_runtime.hydrate_failed'] ?? 0);
        $apiRequestCompletedCount = (int) ($eventNameCounts['cms_api.request_completed'] ?? 0);

        $uniqueSessionsTotal = $this->uniqueNonNullCount($events, 'session_hash');
        $uniqueSessionsBuilder = $this->uniqueNonNullCount($builderEvents, 'session_hash');
        $uniqueSessionsRuntime = $this->uniqueNonNullCount($runtimeEvents, 'session_hash');

        $runtimeHydrateAttemptCount = $runtimeRouteHydratedCount + $runtimeHydrateFailedCount;
        $runtimeHydrateSuccessRate = $runtimeHydrateAttemptCount > 0
            ? round($runtimeRouteHydratedCount / $runtimeHydrateAttemptCount, 4)
            : null;
        $builderPublishPerOpenRate = $builderOpenCount > 0
            ? round($builderPublishCount / $builderOpenCount, 4)
            : null;
        $builderPublishErrorRate = ($builderPublishCount + $builderPublishFailedCount) > 0
            ? round($builderPublishFailedCount / ($builderPublishCount + $builderPublishFailedCount), 4)
            : null;
        $builderSaveWarningsPerDraft = $builderSaveDraftCount > 0
            ? round($builderSaveWarningTotal / $builderSaveDraftCount, 4)
            : null;

        $apiLatencyValues = $apiEvents
            ->where('event_name', 'cms_api.request_completed')
            ->map(function (CmsTelemetryEvent $event): ?int {
                $duration = data_get($event->meta_json, 'duration_ms');
                if (is_int($duration)) {
                    return max(0, $duration);
                }
                if (is_numeric($duration)) {
                    return max(0, (int) $duration);
                }

                return null;
            })
            ->filter(fn ($value): bool => is_int($value))
            ->values();

        $apiErrorCount = $apiEvents
            ->where('event_name', 'cms_api.request_completed')
            ->filter(function (CmsTelemetryEvent $event): bool {
                $status = data_get($event->meta_json, 'status_code');
                if (is_int($status)) {
                    return $status >= 400;
                }
                if (is_numeric($status)) {
                    return (int) $status >= 400;
                }

                return false;
            })
            ->count();

        $apiLatencyAvgMs = $apiLatencyValues->count() > 0
            ? round(((float) $apiLatencyValues->sum()) / $apiLatencyValues->count(), 2)
            : null;
        $apiLatencyP95Ms = $apiLatencyValues->count() > 0
            ? (float) $apiLatencyValues->sort()->values()->get((int) floor(($apiLatencyValues->count() - 1) * 0.95))
            : null;

        $builderPageDetailLoadEvents = $builderEvents
            ->where('event_name', 'cms_builder.page_detail_loaded')
            ->values();

        $builderPageDetailLatencyValues = $builderPageDetailLoadEvents
            ->map(function (CmsTelemetryEvent $event): ?int {
                $duration = data_get($event->meta_json, 'duration_ms');
                if (is_int($duration)) {
                    return max(0, $duration);
                }
                if (is_numeric($duration)) {
                    return max(0, (int) $duration);
                }

                return null;
            })
            ->filter(fn ($value): bool => is_int($value))
            ->values();

        $builderPageDetailSizeCounts = $builderPageDetailLoadEvents
            ->map(function (CmsTelemetryEvent $event): string {
                $sizeClass = data_get($event->meta_json, 'size_class');

                return is_string($sizeClass) && trim($sizeClass) !== ''
                    ? trim(strtolower($sizeClass))
                    : 'unknown';
            })
            ->countBy()
            ->sortKeys()
            ->all();

        $builderPageDetailMaxNodeCount = $builderPageDetailLoadEvents
            ->map(function (CmsTelemetryEvent $event): int {
                $count = data_get($event->meta_json, 'json_node_count');
                if (is_int($count)) {
                    return max(0, $count);
                }
                if (is_numeric($count)) {
                    return max(0, (int) $count);
                }

                return 0;
            })
            ->max() ?? 0;

        $builderPageDetailMaxSectionCount = $builderPageDetailLoadEvents
            ->map(function (CmsTelemetryEvent $event): int {
                $count = data_get($event->meta_json, 'section_count');
                if (is_int($count)) {
                    return max(0, $count);
                }
                if (is_numeric($count)) {
                    return max(0, (int) $count);
                }

                return 0;
            })
            ->max() ?? 0;

        $builderPageDetailLatencyAvgMs = $builderPageDetailLatencyValues->count() > 0
            ? round(((float) $builderPageDetailLatencyValues->sum()) / $builderPageDetailLatencyValues->count(), 2)
            : null;
        $builderPageDetailLatencyP95Ms = $builderPageDetailLatencyValues->count() > 0
            ? (float) $builderPageDetailLatencyValues->sort()->values()->get((int) floor(($builderPageDetailLatencyValues->count() - 1) * 0.95))
            : null;

        $traceFlowCounts = $events
            ->filter(function (CmsTelemetryEvent $event): bool {
                $flow = data_get($event->meta_json, 'flow');
                $traceId = data_get($event->meta_json, 'trace_id');

                return is_string($flow) && trim($flow) !== '' && is_string($traceId) && trim($traceId) !== '';
            })
            ->groupBy(fn (CmsTelemetryEvent $event): string => (string) data_get($event->meta_json, 'flow'))
            ->map(fn (Collection $items): int => $items->count())
            ->sortKeys()
            ->all();

        return [
            'project_id' => $projectId,
            'total_events' => $totalEvents,
            'builder_events' => $builderEvents->count(),
            'runtime_events' => $runtimeEvents->count(),
            'unique_sessions_total' => $uniqueSessionsTotal,
            'unique_sessions_builder' => $uniqueSessionsBuilder,
            'unique_sessions_runtime' => $uniqueSessionsRuntime,
            'builder_open_count' => $builderOpenCount,
            'builder_save_draft_count' => $builderSaveDraftCount,
            'builder_publish_page_count' => $builderPublishCount,
            'builder_save_warning_total' => $builderSaveWarningTotal,
            'runtime_route_hydrated_count' => $runtimeRouteHydratedCount,
            'runtime_hydrate_failed_count' => $runtimeHydrateFailedCount,
            'metrics_json' => [
                'version' => 'p6-g1-03.v1',
                'event_name_counts' => $eventNameCounts,
                'page_view_counts' => $pageViewCounts,
                'derived_rates' => [
                    'runtime_hydrate_success_rate' => $runtimeHydrateSuccessRate,
                    'builder_publish_per_open_rate' => $builderPublishPerOpenRate,
                    'builder_publish_error_rate' => $builderPublishErrorRate,
                    'builder_save_warnings_per_draft' => $builderSaveWarningsPerDraft,
                    'api_latency_avg_ms' => $apiLatencyAvgMs,
                    'api_latency_p95_ms' => $apiLatencyP95Ms,
                ],
                'error_metrics' => [
                    'builder_publish_failed_count' => $builderPublishFailedCount,
                    'runtime_hydrate_failed_count' => $runtimeHydrateFailedCount,
                    'api_error_count' => $apiErrorCount,
                ],
                'api_metrics' => [
                    'request_completed_count' => $apiRequestCompletedCount,
                    'latency_samples' => $apiLatencyValues->count(),
                ],
                'builder_editor_performance' => [
                    'page_detail_load_count' => $builderPageDetailLoadEvents->count(),
                    'page_detail_load_latency_avg_ms' => $builderPageDetailLatencyAvgMs,
                    'page_detail_load_latency_p95_ms' => $builderPageDetailLatencyP95Ms,
                    'page_detail_load_size_counts' => $builderPageDetailSizeCounts,
                    'page_detail_load_large_count' => (int) ($builderPageDetailSizeCounts['large'] ?? 0),
                    'page_detail_load_max_json_node_count' => (int) $builderPageDetailMaxNodeCount,
                    'page_detail_load_max_section_count' => (int) $builderPageDetailMaxSectionCount,
                ],
                'trace_flow_counts' => $traceFlowCounts,
                'pipeline' => [
                    'metric_date' => $metricDate->toDateString(),
                    'aggregated_at' => now()->toISOString(),
                    'site_id' => $siteId,
                ],
            ],
            'generated_at' => now(),
        ];
    }

    /**
     * @param  Collection<int, CmsTelemetryEvent>  $events
     */
    private function uniqueNonNullCount(Collection $events, string $field): int
    {
        return $events
            ->pluck($field)
            ->filter(fn ($value): bool => is_string($value) && trim($value) !== '')
            ->unique()
            ->count();
    }
}
