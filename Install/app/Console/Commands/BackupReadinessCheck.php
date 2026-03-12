<?php

namespace App\Console\Commands;

use App\Models\CronLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BackupReadinessCheck extends Command
{
    protected $signature = 'backup:readiness-check
                            {--max-age-hours=24 : Maximum age for the latest backup artifact}
                            {--path=backups : Storage path where backups are written}
                            {--triggered-by=cron : Who triggered this command}';

    protected $description = 'Validate backup artifact freshness to support restore-readiness drills';

    public function handle(): int
    {
        $cronLog = CronLog::startLog(
            'Backup Readiness Check',
            self::class,
            (string) $this->option('triggered-by')
        );

        try {
            $path = trim((string) $this->option('path'));
            $maxAgeHours = max(1, (int) $this->option('max-age-hours'));
            $disk = Storage::disk('local');

            if (! $disk->exists($path)) {
                $message = "Backup path does not exist: {$path}";
                $cronLog->markFailed($message, $message);
                $this->error($message);

                return self::FAILURE;
            }

            $files = $disk->allFiles($path);
            if ($files === []) {
                $message = "No backup files found under {$path}";
                $cronLog->markFailed($message, $message);
                $this->error($message);

                return self::FAILURE;
            }

            $latestPath = null;
            $latestModified = 0;

            foreach ($files as $file) {
                try {
                    $modified = (int) $disk->lastModified($file);
                } catch (\Throwable) {
                    continue;
                }

                if ($modified > $latestModified) {
                    $latestModified = $modified;
                    $latestPath = $file;
                }
            }

            if (! is_string($latestPath) || $latestModified <= 0) {
                $message = 'Failed to resolve latest backup artifact timestamp.';
                $cronLog->markFailed($message, $message);
                $this->error($message);

                return self::FAILURE;
            }

            $latestAgeHours = now()->diffInHours(\Carbon\Carbon::createFromTimestamp($latestModified));
            if ($latestAgeHours > $maxAgeHours) {
                $message = sprintf(
                    'Latest backup is too old (%dh > %dh): %s',
                    $latestAgeHours,
                    $maxAgeHours,
                    $latestPath
                );
                $cronLog->markFailed($message, $message);
                $this->error($message);

                return self::FAILURE;
            }

            $summary = sprintf(
                'Backup readiness OK. Latest artifact: %s (%dh old)',
                $latestPath,
                $latestAgeHours
            );

            $cronLog->markSuccess($summary);
            $this->info($summary);
            Log::info('Backup readiness check passed', [
                'latest_backup' => $latestPath,
                'age_hours' => $latestAgeHours,
                'max_age_hours' => $maxAgeHours,
                'path' => $path,
            ]);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $cronLog->markFailed($e->getTraceAsString(), $e->getMessage());
            $this->error('Backup readiness check failed: '.$e->getMessage());
            Log::error('Backup readiness check failed', [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }
}
