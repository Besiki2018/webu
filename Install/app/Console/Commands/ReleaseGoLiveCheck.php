<?php

namespace App\Console\Commands;

use App\Models\CronLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class ReleaseGoLiveCheck extends Command
{
    protected $signature = 'release:go-live-check
                            {--stability-hours=48 : Lookback window for staging stability gate}
                            {--max-critical=0 : Maximum critical operation errors in staging gate}
                            {--max-failed-jobs=0 : Maximum failed jobs in staging gate}
                            {--backup-path=backups : Backup storage path}
                            {--backup-max-age-hours=24 : Maximum backup age in hours}
                            {--require-pilot=1 : Require pilot readiness gate (true/false)}
                            {--min-pilot-tenants=3 : Minimum unique pilot tenants when pilot gate is enabled}
                            {--triggered-by=cron : Who triggered this command}';

    protected $description = 'Run production go-live preflight checks and persist a release readiness report';

    public function handle(): int
    {
        $cronLog = CronLog::startLog(
            'Release Go-Live Check',
            self::class,
            (string) $this->option('triggered-by')
        );

        try {
            $stagingHours = max(1, (int) $this->option('stability-hours'));
            $maxCritical = max(0, (int) $this->option('max-critical'));
            $maxFailedJobs = max(0, (int) $this->option('max-failed-jobs'));
            $backupPath = trim((string) $this->option('backup-path'));
            $backupMaxAge = max(1, (int) $this->option('backup-max-age-hours'));
            $requirePilot = $this->toBool($this->option('require-pilot'));
            $minPilotTenants = max(1, (int) $this->option('min-pilot-tenants'));

            $stagingExitCode = Artisan::call('staging:stability-gate', [
                '--hours' => $stagingHours,
                '--max-critical' => $maxCritical,
                '--max-failed-jobs' => $maxFailedJobs,
            ]);
            $stagingOutput = trim((string) Artisan::output());

            $backupExitCode = Artisan::call('backup:readiness-check', [
                '--path' => $backupPath,
                '--max-age-hours' => $backupMaxAge,
                '--triggered-by' => (string) $this->option('triggered-by'),
            ]);
            $backupOutput = trim((string) Artisan::output());

            $pilotExitCode = null;
            $pilotOutput = null;

            if ($requirePilot) {
                $pilotExitCode = Artisan::call('pilot:readiness-report', [
                    '--min-tenants' => $minPilotTenants,
                    '--max-open-critical' => 0,
                    '--triggered-by' => (string) $this->option('triggered-by'),
                ]);
                $pilotOutput = trim((string) Artisan::output());
            }

            $pendingMigrations = $this->pendingMigrationFiles();
            $releaseRunbookExists = File::exists(base_path('docs/release-runbook.md'));
            $databaseReachable = $this->databaseReachable();

            $report = [
                'generated_at' => now()->toIso8601String(),
                'checks' => [
                    'database_reachable' => $databaseReachable,
                    'staging_stability_gate' => [
                        'passed' => $stagingExitCode === self::SUCCESS,
                        'exit_code' => $stagingExitCode,
                        'output' => $stagingOutput,
                    ],
                    'backup_readiness' => [
                        'passed' => $backupExitCode === self::SUCCESS,
                        'exit_code' => $backupExitCode,
                        'output' => $backupOutput,
                    ],
                    'pilot_readiness' => [
                        'required' => $requirePilot,
                        'passed' => $requirePilot ? $pilotExitCode === self::SUCCESS : true,
                        'exit_code' => $pilotExitCode,
                        'output' => $pilotOutput,
                    ],
                    'migrations' => [
                        'pending_count' => count($pendingMigrations),
                        'pending_files' => $pendingMigrations,
                    ],
                    'release_runbook_present' => $releaseRunbookExists,
                ],
            ];

            $report['passed'] = $databaseReachable
                && $report['checks']['staging_stability_gate']['passed']
                && $report['checks']['backup_readiness']['passed']
                && $report['checks']['pilot_readiness']['passed']
                && $report['checks']['migrations']['pending_count'] === 0
                && $releaseRunbookExists;

            Storage::disk('local')->put(
                'release/go-live-check-latest.json',
                json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            $this->table(
                ['Check', 'Status'],
                [
                    ['Database', $databaseReachable ? 'PASS' : 'FAIL'],
                    ['Staging gate', $report['checks']['staging_stability_gate']['passed'] ? 'PASS' : 'FAIL'],
                    ['Backup readiness', $report['checks']['backup_readiness']['passed'] ? 'PASS' : 'FAIL'],
                    ['Pilot readiness', $report['checks']['pilot_readiness']['passed'] ? 'PASS' : 'FAIL'],
                    ['Pending migrations', (string) $report['checks']['migrations']['pending_count']],
                    ['Release runbook', $releaseRunbookExists ? 'PASS' : 'FAIL'],
                ]
            );

            $summary = sprintf(
                'Go-live check %s (pending_migrations=%d)',
                $report['passed'] ? 'passed' : 'failed',
                $report['checks']['migrations']['pending_count']
            );

            if (! $report['passed']) {
                $cronLog->markFailed($summary, $summary);
                $this->error($summary);

                return self::FAILURE;
            }

            $cronLog->markSuccess($summary);
            $this->info($summary);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $cronLog->markFailed($e->getTraceAsString(), $e->getMessage());
            $this->error('Release go-live check failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        $normalized = strtolower(trim((string) $value));

        if ($normalized === '' || in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
            return false;
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    private function pendingMigrationFiles(): array
    {
        $migrationFiles = collect(File::allFiles(database_path('migrations')))
            ->map(fn (\SplFileInfo $file): string => pathinfo($file->getFilename(), PATHINFO_FILENAME))
            ->values()
            ->all();

        if (! Schema::hasTable('migrations')) {
            sort($migrationFiles);

            return $migrationFiles;
        }

        $ranMigrations = DB::table('migrations')->pluck('migration')->map(fn ($migration): string => (string) $migration)->all();
        $pending = array_values(array_diff($migrationFiles, $ranMigrations));
        sort($pending);

        return $pending;
    }

    private function databaseReachable(): bool
    {
        try {
            DB::connection()->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
