<?php

namespace App\Console\Commands;

use App\Models\OperationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class StagingStabilityGate extends Command
{
    protected $signature = 'staging:stability-gate
                            {--hours=48 : Lookback window in hours}
                            {--max-critical=0 : Maximum allowed critical errors in lookback window}
                            {--max-failed-jobs=0 : Maximum allowed failed jobs}';

    protected $description = 'Evaluate staging stability gate for release readiness';

    public function handle(): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $maxCritical = max(0, (int) $this->option('max-critical'));
        $maxFailedJobs = max(0, (int) $this->option('max-failed-jobs'));
        $windowStart = now()->subHours($hours);

        $databaseOk = true;
        $databaseError = null;

        try {
            DB::connection()->getPdo();
        } catch (\Throwable $exception) {
            $databaseOk = false;
            $databaseError = $exception->getMessage();
        }

        $criticalErrors = Schema::hasTable('operation_logs')
            ? OperationLog::query()
                ->where('status', OperationLog::STATUS_ERROR)
                ->where('occurred_at', '>=', $windowStart)
                ->count()
            : 0;

        $failedJobs = Schema::hasTable('failed_jobs')
            ? (int) DB::table('failed_jobs')->count()
            : 0;

        $pendingJobs = Schema::hasTable('jobs')
            ? (int) DB::table('jobs')->count()
            : 0;

        $report = [
            'generated_at' => now()->toIso8601String(),
            'window' => [
                'hours' => $hours,
                'from' => $windowStart->toIso8601String(),
            ],
            'thresholds' => [
                'max_critical' => $maxCritical,
                'max_failed_jobs' => $maxFailedJobs,
            ],
            'checks' => [
                'database_ok' => $databaseOk,
                'critical_errors' => $criticalErrors,
                'failed_jobs' => $failedJobs,
                'pending_jobs' => $pendingJobs,
            ],
            'database_error' => $databaseError,
        ];

        $report['passed'] = $databaseOk
            && $criticalErrors <= $maxCritical
            && $failedJobs <= $maxFailedJobs;

        Storage::disk('local')->put(
            'release/staging-gate-latest.json',
            json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        $this->table(
            ['Check', 'Value', 'Threshold', 'Status'],
            [
                [
                    'Database',
                    $databaseOk ? 'ok' : 'failed',
                    'must be ok',
                    $databaseOk ? 'PASS' : 'FAIL',
                ],
                [
                    'Critical errors',
                    (string) $criticalErrors,
                    '<= '.$maxCritical,
                    $criticalErrors <= $maxCritical ? 'PASS' : 'FAIL',
                ],
                [
                    'Failed jobs',
                    (string) $failedJobs,
                    '<= '.$maxFailedJobs,
                    $failedJobs <= $maxFailedJobs ? 'PASS' : 'FAIL',
                ],
                [
                    'Pending jobs',
                    (string) $pendingJobs,
                    'informational',
                    'INFO',
                ],
            ]
        );

        if (! $report['passed']) {
            $this->error('Staging stability gate failed.');
            if ($databaseError) {
                $this->line('Database error: '.$databaseError);
            }

            return self::FAILURE;
        }

        $this->info('Staging stability gate passed.');

        return self::SUCCESS;
    }
}
