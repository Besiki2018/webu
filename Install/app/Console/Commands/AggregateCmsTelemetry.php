<?php

namespace App\Console\Commands;

use App\Services\CmsTelemetryAggregatedMetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AggregateCmsTelemetry extends Command
{
    protected $signature = 'cms:telemetry-aggregate {--date= : Aggregate metrics for a specific date (YYYY-MM-DD)}';

    protected $description = 'Build daily aggregated CMS telemetry metrics from cms_telemetry_events';

    public function __construct(
        protected CmsTelemetryAggregatedMetricsService $aggregator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $date = is_string($this->option('date')) ? trim((string) $this->option('date')) : '';
        $result = $this->aggregator->aggregateDate($date !== '' ? $date : null);

        $message = sprintf(
            'Aggregated CMS telemetry metrics for %s (events=%d, site_groups=%d, upserted=%d).',
            (string) ($result['metric_date'] ?? now()->toDateString()),
            (int) ($result['source_events'] ?? 0),
            (int) ($result['site_groups'] ?? 0),
            (int) ($result['upserted'] ?? 0)
        );

        $this->info($message);

        Log::info('cms.telemetry.aggregate', [
            'metric_date' => $result['metric_date'] ?? null,
            'source_events' => (int) ($result['source_events'] ?? 0),
            'site_groups' => (int) ($result['site_groups'] ?? 0),
            'upserted' => (int) ($result['upserted'] ?? 0),
        ]);

        return self::SUCCESS;
    }
}
