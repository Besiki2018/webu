<?php

namespace Tests\Unit;

use App\Models\CmsTelemetryDailyAggregate;
use App\Models\CmsTelemetryEvent;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Services\CmsTelemetryAggregatedMetricsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsTelemetryAggregatedMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_aggregates_daily_telemetry_metrics_and_upserts_per_site(): void
    {
        [$project, $site] = $this->createPublishedProjectWithSite();
        $targetDate = Carbon::parse('2026-02-24 12:00:00');

        $this->seedTelemetryEvent($site, [
            'source' => 'builder',
            'event_name' => 'cms_builder.open',
            'session_hash' => str_repeat('a', 64),
            'occurred_at' => $targetDate->copy()->setTime(10, 0),
        ]);
        $this->seedTelemetryEvent($site, [
            'source' => 'builder',
            'event_name' => 'cms_builder.save_draft',
            'session_hash' => str_repeat('a', 64),
            'meta_json' => ['warning_count' => 2],
            'occurred_at' => $targetDate->copy()->setTime(10, 10),
        ]);
        $this->seedTelemetryEvent($site, [
            'source' => 'builder',
            'event_name' => 'cms_builder.publish_page',
            'session_hash' => str_repeat('a', 64),
            'meta_json' => ['trace_id' => 'trace-pub-ok-1', 'flow' => 'publish', 'duration_ms' => 520],
            'occurred_at' => $targetDate->copy()->setTime(10, 20),
        ]);
        $this->seedTelemetryEvent($site, [
            'source' => 'builder',
            'event_name' => 'cms_builder.publish_page_failed',
            'session_hash' => str_repeat('a', 64),
            'meta_json' => ['trace_id' => 'trace-pub-fail-1', 'flow' => 'publish', 'duration_ms' => 710, 'status_code' => 500],
            'occurred_at' => $targetDate->copy()->setTime(10, 22),
        ]);
        $this->seedTelemetryEvent($site, [
            'source' => 'builder',
            'event_name' => 'cms_builder.page_detail_loaded',
            'session_hash' => str_repeat('a', 64),
            'meta_json' => [
                'trace_id' => 'trace-builder-page-load-1',
                'flow' => 'builder_page_load',
                'duration_ms' => 432,
                'section_count' => 44,
                'json_node_count' => 2140,
                'size_class' => 'large',
            ],
            'occurred_at' => $targetDate->copy()->setTime(10, 25),
        ]);
        $this->seedTelemetryEvent($site, [
            'source' => 'runtime',
            'event_name' => 'cms_runtime.route_hydrated',
            'session_hash' => str_repeat('b', 64),
            'page_slug' => 'home',
            'route_slug' => 'home',
            'occurred_at' => $targetDate->copy()->setTime(11, 0),
        ]);
        $this->seedTelemetryEvent($site, [
            'source' => 'runtime',
            'event_name' => 'cms_runtime.route_hydrated',
            'session_hash' => str_repeat('c', 64),
            'page_slug' => 'shop',
            'route_slug' => 'shop',
            'occurred_at' => $targetDate->copy()->setTime(11, 5),
        ]);
        $this->seedTelemetryEvent($site, [
            'source' => 'runtime',
            'event_name' => 'cms_runtime.hydrate_failed',
            'session_hash' => str_repeat('c', 64),
            'meta_json' => ['error_code' => 'HTTP_500'],
            'occurred_at' => $targetDate->copy()->setTime(11, 6),
        ]);
        $this->seedTelemetryEvent($site, [
            'source' => 'api',
            'event_name' => 'cms_api.request_completed',
            'session_hash' => str_repeat('f', 64),
            'meta_json' => ['duration_ms' => 187, 'status_code' => 201, 'trace_id' => 'trace-checkout-1', 'flow' => 'checkout'],
            'occurred_at' => $targetDate->copy()->setTime(11, 12),
        ]);
        // Different day should be excluded.
        $this->seedTelemetryEvent($site, [
            'source' => 'runtime',
            'event_name' => 'cms_runtime.route_hydrated',
            'session_hash' => str_repeat('d', 64),
            'occurred_at' => $targetDate->copy()->subDay(),
        ]);

        $service = app(CmsTelemetryAggregatedMetricsService::class);
        $result = $service->aggregateDate($targetDate->toDateString());

        $this->assertSame('2026-02-24', $result['metric_date']);
        $this->assertSame(9, $result['source_events']);
        $this->assertSame(1, $result['site_groups']);
        $this->assertSame(1, $result['upserted']);

        /** @var CmsTelemetryDailyAggregate $row */
        $row = CmsTelemetryDailyAggregate::query()->firstOrFail();
        $this->assertSame((string) $site->id, $row->site_id);
        $this->assertSame((string) $project->id, $row->project_id);
        $this->assertSame(9, (int) $row->total_events);
        $this->assertSame(5, (int) $row->builder_events);
        $this->assertSame(3, (int) $row->runtime_events);
        $this->assertSame(4, (int) $row->unique_sessions_total);
        $this->assertSame(1, (int) $row->unique_sessions_builder);
        $this->assertSame(2, (int) $row->unique_sessions_runtime);
        $this->assertSame(1, (int) $row->builder_open_count);
        $this->assertSame(1, (int) $row->builder_save_draft_count);
        $this->assertSame(1, (int) $row->builder_publish_page_count);
        $this->assertSame(2, (int) $row->builder_save_warning_total);
        $this->assertSame(2, (int) $row->runtime_route_hydrated_count);
        $this->assertSame(1, (int) $row->runtime_hydrate_failed_count);
        $this->assertSame(0.6667, (float) data_get($row->metrics_json, 'derived_rates.runtime_hydrate_success_rate'));
        $this->assertSame(1.0, (float) data_get($row->metrics_json, 'derived_rates.builder_publish_per_open_rate'));
        $this->assertSame(0.5, (float) data_get($row->metrics_json, 'derived_rates.builder_publish_error_rate'));
        $this->assertSame(2.0, (float) data_get($row->metrics_json, 'derived_rates.builder_save_warnings_per_draft'));
        $this->assertSame(187.0, (float) data_get($row->metrics_json, 'derived_rates.api_latency_avg_ms'));
        $this->assertSame(187.0, (float) data_get($row->metrics_json, 'derived_rates.api_latency_p95_ms'));
        $this->assertSame(1, (int) data_get($row->metrics_json, 'error_metrics.builder_publish_failed_count'));
        $this->assertSame(0, (int) data_get($row->metrics_json, 'error_metrics.api_error_count'));
        $this->assertSame(1, (int) data_get($row->metrics_json, 'api_metrics.request_completed_count'));
        $this->assertSame(1, (int) data_get($row->metrics_json, 'api_metrics.latency_samples'));
        $this->assertSame(1, (int) (($row->metrics_json['event_name_counts']['cms_builder.open'] ?? 0)));
        $this->assertSame(1, (int) (($row->metrics_json['event_name_counts']['cms_builder.publish_page_failed'] ?? 0)));
        $this->assertSame(1, (int) (($row->metrics_json['event_name_counts']['cms_builder.page_detail_loaded'] ?? 0)));
        $this->assertSame(1, (int) (($row->metrics_json['event_name_counts']['cms_api.request_completed'] ?? 0)));
        $this->assertSame(1, (int) data_get($row->metrics_json, 'page_view_counts.home'));
        $this->assertSame(1, (int) data_get($row->metrics_json, 'page_view_counts.shop'));
        $this->assertSame(1, (int) data_get($row->metrics_json, 'trace_flow_counts.checkout'));
        $this->assertSame(1, (int) data_get($row->metrics_json, 'trace_flow_counts.builder_page_load'));
        $this->assertSame(2, (int) data_get($row->metrics_json, 'trace_flow_counts.publish'));
        $this->assertSame(1, (int) data_get($row->metrics_json, 'builder_editor_performance.page_detail_load_count'));
        $this->assertSame(432.0, (float) data_get($row->metrics_json, 'builder_editor_performance.page_detail_load_latency_avg_ms'));
        $this->assertSame(432.0, (float) data_get($row->metrics_json, 'builder_editor_performance.page_detail_load_latency_p95_ms'));
        $this->assertSame(1, (int) data_get($row->metrics_json, 'builder_editor_performance.page_detail_load_large_count'));
        $this->assertSame(2140, (int) data_get($row->metrics_json, 'builder_editor_performance.page_detail_load_max_json_node_count'));
        $this->assertSame(44, (int) data_get($row->metrics_json, 'builder_editor_performance.page_detail_load_max_section_count'));

        // Upsert path should update same row instead of duplicating.
        $this->seedTelemetryEvent($site, [
            'source' => 'builder',
            'event_name' => 'cms_builder.save_draft',
            'session_hash' => str_repeat('e', 64),
            'meta_json' => ['warning_count' => 0],
            'occurred_at' => $targetDate->copy()->setTime(12, 0),
        ]);

        $rerun = $service->aggregateDate($targetDate);
        $this->assertSame(1, $rerun['upserted']);
        $this->assertSame(1, CmsTelemetryDailyAggregate::query()->count());
        $this->assertSame(10, (int) CmsTelemetryDailyAggregate::query()->value('total_events'));
        $this->assertSame(2, (int) CmsTelemetryDailyAggregate::query()->value('builder_save_draft_count'));
    }

    public function test_it_returns_site_series_ordered_ascending_with_derived_rates(): void
    {
        [, $site] = $this->createPublishedProjectWithSite();

        CmsTelemetryDailyAggregate::query()->create([
            'metric_date' => '2026-02-23',
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'total_events' => 5,
            'builder_events' => 2,
            'runtime_events' => 3,
            'unique_sessions_total' => 2,
            'metrics_json' => ['derived_rates' => ['runtime_hydrate_success_rate' => 0.5]],
            'generated_at' => now(),
        ]);
        CmsTelemetryDailyAggregate::query()->create([
            'metric_date' => '2026-02-24',
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'total_events' => 8,
            'builder_events' => 3,
            'runtime_events' => 5,
            'metrics_json' => [
                'api_metrics' => ['request_completed_count' => 3],
                'error_metrics' => ['builder_publish_failed_count' => 1, 'api_error_count' => 1],
                'builder_editor_performance' => ['page_detail_load_count' => 2, 'page_detail_load_latency_avg_ms' => 210.5],
                'trace_flow_counts' => ['checkout' => 2],
                'derived_rates' => ['runtime_hydrate_success_rate' => 0.8, 'api_latency_avg_ms' => 120.5],
            ],
            'unique_sessions_total' => 4,
            'runtime_route_hydrated_count' => 4,
            'builder_save_draft_count' => 2,
            'builder_publish_page_count' => 1,
            'generated_at' => now(),
        ]);

        $series = app(CmsTelemetryAggregatedMetricsService::class)->siteSeries($site, 30);

        $this->assertCount(2, $series);
        $this->assertSame('2026-02-23', $series[0]['metric_date']);
        $this->assertSame('2026-02-24', $series[1]['metric_date']);
        $this->assertSame(4, $series[1]['page_views']);
        $this->assertSame(2, $series[1]['builder_saves']);
        $this->assertSame(1, $series[1]['builder_publishes']);
        $this->assertSame(3, $series[1]['api_events']);
        $this->assertSame(1, $series[1]['builder_publish_failures']);
        $this->assertSame(1, $series[1]['api_error_count']);
        $this->assertSame(2, data_get($series[1], 'builder_editor_performance.page_detail_load_count'));
        $this->assertSame(210.5, (float) data_get($series[1], 'builder_editor_performance.page_detail_load_latency_avg_ms'));
        $this->assertSame(2, data_get($series[1], 'trace_flow_counts.checkout'));
        $this->assertSame(0.8, (float) data_get($series[1], 'derived_rates.runtime_hydrate_success_rate'));
        $this->assertSame(120.5, (float) data_get($series[1], 'derived_rates.api_latency_avg_ms'));
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function seedTelemetryEvent(Site $site, array $overrides = []): CmsTelemetryEvent
    {
        return CmsTelemetryEvent::query()->create(array_merge([
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'channel' => 'public',
            'source' => 'runtime',
            'event_name' => 'cms_runtime.route_hydrated',
            'occurred_at' => now(),
            'page_slug' => 'home',
            'route_slug' => 'home',
            'actor_scope' => 'guest',
            'anonymized_at' => now(),
            'retention_expires_at' => now()->addDays(30),
        ], $overrides));
    }

    /**
     * @return array{0: Project, 1: Site}
     */
    private function createPublishedProjectWithSite(): array
    {
        $plan = Plan::factory()->create();
        $owner = User::factory()->withPlan($plan)->create();
        $project = Project::factory()->for($owner)->published(strtolower(Str::random(10)))->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }
}
