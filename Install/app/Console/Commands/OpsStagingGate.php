<?php

namespace App\Console\Commands;

use App\Models\CronLog;
use App\Models\OperationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OpsStagingGate extends Command
{
    protected $signature = 'ops:staging-gate
                            {--hours=48 : Observation window in hours}
                            {--max-critical-errors=0 : Maximum allowed critical operation errors}
                            {--max-failed-jobs=0 : Maximum allowed queue failed jobs in the same window}
                            {--triggered-by=cron : Who triggered this command}';

    protected $description = 'Evaluate staging stability gate before production rollout';

    public function handle(): int
    {
        $cronLog = CronLog::startLog(
            'Staging Stability Gate',
            self::class,
            (string) $this->option('triggered-by')
        );

        try {
            $hours = max(1, (int) $this->option('hours'));
            $maxCriticalErrors = max(0, (int) $this->option('max-critical-errors'));
            $maxFailedJobs = max(0, (int) $this->option('max-failed-jobs'));
            $windowStart = now()->subHours($hours);

            $criticalEvents = $this->criticalEventList();
            $criticalErrors = OperationLog::query()
                ->where('status', OperationLog::STATUS_ERROR)
                ->where('created_at', '>=', $windowStart)
                ->when($criticalEvents !== [], fn ($query) => $query->whereIn('event', $criticalEvents))
                ->count();

            $failedJobs = Schema::hasTable('failed_jobs')
                ? DB::table('failed_jobs')->where('failed_at', '>=', $windowStart)->count()
                : 0;

            $gatePassed = $criticalErrors <= $maxCriticalErrors && $failedJobs <= $maxFailedJobs;
            $summary = sprintf(
                'window=%dh critical_errors=%d/%d failed_jobs=%d/%d',
                $hours,
                $criticalErrors,
                $maxCriticalErrors,
                $failedJobs,
                $maxFailedJobs
            );

            if (! $gatePassed) {
                $cronLog->markFailed($summary, 'Staging stability gate failed.');
                $this->error('Staging gate failed: '.$summary);

                return self::FAILURE;
            }

            $cronLog->markSuccess('Staging gate passed: '.$summary);
            $this->info('Staging gate passed: '.$summary);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $cronLog->markFailed($e->getTraceAsString(), $e->getMessage());
            $this->error('Staging gate execution failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array<int, string>
     */
    private function criticalEventList(): array
    {
        $raw = (string) config('ops.alert_critical_events', '');
        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $event): string => trim($event),
            explode(',', $raw)
        )));
    }
}
