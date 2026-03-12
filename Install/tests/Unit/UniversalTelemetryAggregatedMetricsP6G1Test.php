<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalTelemetryAggregatedMetricsP6G1Test extends TestCase
{
    public function test_p6_g1_03_aggregated_metrics_pipeline_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/CMS_TELEMETRY_AGGREGATED_METRICS_P6_G1_03.md');
        $migrationPath = base_path('database/migrations/2026_02_24_232000_create_cms_telemetry_daily_aggregates_table.php');
        $modelPath = base_path('app/Models/CmsTelemetryDailyAggregate.php');
        $servicePath = base_path('app/Services/CmsTelemetryAggregatedMetricsService.php');
        $commandPath = base_path('app/Console/Commands/AggregateCmsTelemetry.php');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([$docPath, $migrationPath, $modelPath, $servicePath, $commandPath] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $migration = File::get($migrationPath);
        $model = File::get($modelPath);
        $service = File::get($servicePath);
        $command = File::get($commandPath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('P6-G1-03', $doc);
        $this->assertStringContainsString('cms_telemetry_daily_aggregates', $doc);
        $this->assertStringContainsString('CmsTelemetryAggregatedMetricsService', $doc);
        $this->assertStringContainsString('cms:telemetry-aggregate', $doc);
        $this->assertStringContainsString('runtime_hydrate_success_rate', $doc);
        $this->assertStringContainsString('builder_publish_per_open_rate', $doc);
        $this->assertStringContainsString('P6-G1-04', $doc);

        $this->assertStringContainsString("Schema::create('cms_telemetry_daily_aggregates'", $migration);
        $this->assertStringContainsString('$table->date(\'metric_date\')', $migration);
        $this->assertStringContainsString('$table->json(\'metrics_json\')', $migration);
        $this->assertStringContainsString('cms_telemetry_daily_aggregates_date_site_unique', $migration);

        $this->assertStringContainsString('class CmsTelemetryDailyAggregate extends Model', $model);
        $this->assertStringContainsString("'metrics_json' => 'array'", $model);
        $this->assertStringContainsString("'metric_date' => 'date'", $model);

        $this->assertStringContainsString('class CmsTelemetryAggregatedMetricsService', $service);
        $this->assertStringContainsString('aggregateDate(string|Carbon|null $date = null)', $service);
        $this->assertStringContainsString('siteSeries(Site $site, int $days = 30)', $service);
        $this->assertStringContainsString('cms_runtime.route_hydrated', $service);
        $this->assertStringContainsString('cms_builder.save_draft', $service);
        $this->assertStringContainsString('updateOrCreate', $service);
        $this->assertStringContainsString('derived_rates', $service);
        $this->assertStringContainsString('builder_save_warnings_per_draft', $service);

        $this->assertStringContainsString('class AggregateCmsTelemetry extends Command', $command);
        $this->assertStringContainsString('cms:telemetry-aggregate', $command);
        $this->assertStringContainsString('cms.telemetry.aggregate', $command);

        $this->assertStringContainsString('- ✅ Aggregated metrics layer', $roadmap);
        $this->assertStringContainsString("`P6-G1-03` (✅ `DONE`) Aggregated metrics pipeline.", $roadmap);
    }
}
