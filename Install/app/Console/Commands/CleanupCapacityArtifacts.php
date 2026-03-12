<?php

namespace App\Console\Commands;

use App\Models\CronLog;
use App\Models\OperationLog;
use App\Models\Project;
use App\Models\ProjectFile;
use App\Models\SystemSetting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class CleanupCapacityArtifacts extends Command
{
    protected $signature = 'capacity:cleanup-artifacts
                            {--retention-days= : Override project artifact retention days}
                            {--dry-run : Show what would be deleted without deleting}
                            {--triggered-by=cron : Who triggered this command (cron or manual:user_id)}';

    protected $description = 'Prune artifact/media storage, cleanup orphan records, and resync project storage quotas';

    public function handle(): int
    {
        $cronLog = CronLog::startLog(
            'Capacity Guardrails Cleanup',
            self::class,
            $this->option('triggered-by')
        );

        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('[DRY RUN] No data will be deleted or updated.');
        }

        try {
            $retentionDays = $this->resolveRetentionDays();
            $artifactCutoff = now()->subDays($retentionDays)->getTimestamp();
            $tempCutoff = now()->subDay()->getTimestamp();

            $disk = Storage::disk('local');
            $projectIds = Project::withTrashed()->pluck('id')->all();
            $projectLookup = array_fill_keys($projectIds, true);

            $summary = [
                'retention_days' => $retentionDays,
                'project_count' => count($projectIds),
                'dry_run' => $dryRun,
            ];

            $summary['orphan_preview_dirs'] = $this->cleanupOrphanProjectDirectories(
                $disk,
                'previews',
                $projectLookup,
                $dryRun
            );
            $summary['orphan_published_dirs'] = $this->cleanupOrphanProjectDirectories(
                $disk,
                'published',
                $projectLookup,
                $dryRun
            );
            $summary['orphan_project_file_dirs'] = $this->cleanupOrphanProjectDirectories(
                $disk,
                'project-files',
                $projectLookup,
                $dryRun
            );
            $summary['orphan_build_dirs'] = $this->cleanupOrphanProjectDirectories(
                $disk,
                'builds',
                $projectLookup,
                $dryRun
            );

            $buildPrune = $this->pruneOldFiles($disk, 'builds', $artifactCutoff, $dryRun);
            $summary['build_files_pruned'] = $buildPrune['files'];
            $summary['build_bytes_pruned'] = $buildPrune['bytes'];

            $tempPrune = $this->pruneOldFiles($disk, 'temp', $tempCutoff, $dryRun);
            $summary['temp_files_pruned'] = $tempPrune['files'];
            $summary['temp_bytes_pruned'] = $tempPrune['bytes'];

            $projectFileCleanup = $this->cleanupProjectFileRecords($disk, $dryRun);
            $summary = array_merge($summary, $projectFileCleanup);

            $diskFileOrphans = $this->cleanupProjectFileDiskOrphans($disk, $projectLookup, $dryRun);
            $summary = array_merge($summary, $diskFileOrphans);

            $buildPathSync = $this->syncBuildPaths($disk, $dryRun);
            $summary = array_merge($summary, $buildPathSync);

            $storageSync = $this->syncProjectStorageUsage($dryRun);
            $summary = array_merge($summary, $storageSync);

            $logPrune = $this->pruneOperationLogs($dryRun);
            $summary = array_merge($summary, $logPrune);

            $message = $this->buildSummaryMessage($summary);
            $this->info($message);

            $cronLog->markSuccess($message);
            Log::info('Capacity guardrails cleanup completed', $summary);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Capacity cleanup failed: '.$e->getMessage());
            $cronLog->markFailed($e->getTraceAsString(), $e->getMessage());

            Log::error('Capacity guardrails cleanup failed', [
                'error' => $e->getMessage(),
            ]);

            return self::FAILURE;
        }
    }

    private function resolveRetentionDays(): int
    {
        $override = $this->option('retention-days');
        if ($override !== null && $override !== '') {
            return max(1, (int) $override);
        }

        return max(1, (int) SystemSetting::get('data_retention_days_projects', 90));
    }

    private function cleanupOrphanProjectDirectories(
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        string $basePath,
        array $projectLookup,
        bool $dryRun
    ): int {
        if (! $disk->exists($basePath)) {
            return 0;
        }

        $deleted = 0;
        foreach ($disk->directories($basePath) as $directory) {
            $projectId = basename($directory);
            if ($projectId === '' || isset($projectLookup[$projectId])) {
                continue;
            }

            if (! $dryRun) {
                $disk->deleteDirectory($directory);
            }

            $deleted++;
        }

        return $deleted;
    }

    /**
     * @return array{files: int, bytes: int}
     */
    private function pruneOldFiles(
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        string $basePath,
        int $cutoffTimestamp,
        bool $dryRun
    ): array {
        if (! $disk->exists($basePath)) {
            return ['files' => 0, 'bytes' => 0];
        }

        $filesDeleted = 0;
        $bytesDeleted = 0;

        foreach ($disk->allFiles($basePath) as $filePath) {
            $lastModified = $disk->lastModified($filePath);
            if ($lastModified >= $cutoffTimestamp) {
                continue;
            }

            $fileSize = 0;
            try {
                $fileSize = (int) $disk->size($filePath);
            } catch (\Throwable) {
                $fileSize = 0;
            }

            if (! $dryRun) {
                $disk->delete($filePath);
            }

            $filesDeleted++;
            $bytesDeleted += $fileSize;
        }

        if (! $dryRun) {
            $this->deleteEmptyDirectories($disk, $basePath);
        }

        return [
            'files' => $filesDeleted,
            'bytes' => $bytesDeleted,
        ];
    }

    /**
     * @return array{
     *   orphan_project_file_records_deleted: int,
     *   orphan_project_file_records_bytes: int,
     *   missing_project_file_records_deleted: int,
     *   missing_project_file_records_bytes: int
     * }
     */
    private function cleanupProjectFileRecords(
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        bool $dryRun
    ): array {
        $orphanRecordCount = 0;
        $orphanRecordBytes = 0;

        ProjectFile::withoutTenantProject()
            ->select(['id', 'project_id', 'size'])
            ->whereDoesntHave('project', function ($query) {
                $query->withTrashed();
            })
            ->chunkById(500, function ($rows) use ($dryRun, &$orphanRecordCount, &$orphanRecordBytes): void {
                foreach ($rows as $row) {
                    $orphanRecordCount++;
                    $orphanRecordBytes += (int) $row->size;

                    if (! $dryRun) {
                        $row->delete();
                    }
                }
            });

        $missingRecordCount = 0;
        $missingRecordBytes = 0;

        ProjectFile::withoutTenantProject()
            ->select(['id', 'path', 'size'])
            ->chunkById(500, function ($rows) use ($disk, $dryRun, &$missingRecordCount, &$missingRecordBytes): void {
                foreach ($rows as $row) {
                    if ($disk->exists($row->path)) {
                        continue;
                    }

                    $missingRecordCount++;
                    $missingRecordBytes += (int) $row->size;

                    if (! $dryRun) {
                        $row->delete();
                    }
                }
            });

        return [
            'orphan_project_file_records_deleted' => $orphanRecordCount,
            'orphan_project_file_records_bytes' => $orphanRecordBytes,
            'missing_project_file_records_deleted' => $missingRecordCount,
            'missing_project_file_records_bytes' => $missingRecordBytes,
        ];
    }

    /**
     * @return array{orphan_project_files_deleted: int, orphan_project_files_bytes: int}
     */
    private function cleanupProjectFileDiskOrphans(
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        array $projectLookup,
        bool $dryRun
    ): array {
        if (! $disk->exists('project-files')) {
            return [
                'orphan_project_files_deleted' => 0,
                'orphan_project_files_bytes' => 0,
            ];
        }

        $deletedFiles = 0;
        $deletedBytes = 0;

        foreach ($disk->directories('project-files') as $directory) {
            $projectId = basename($directory);
            if (! isset($projectLookup[$projectId])) {
                continue;
            }

            $knownPaths = ProjectFile::withoutTenantProject()
                ->where('project_id', $projectId)
                ->pluck('path')
                ->all();

            $knownLookup = array_fill_keys($knownPaths, true);

            foreach ($disk->allFiles($directory) as $filePath) {
                if (isset($knownLookup[$filePath])) {
                    continue;
                }

                $fileSize = 0;
                try {
                    $fileSize = (int) $disk->size($filePath);
                } catch (\Throwable) {
                    $fileSize = 0;
                }

                if (! $dryRun) {
                    $disk->delete($filePath);
                }

                $deletedFiles++;
                $deletedBytes += $fileSize;
            }
        }

        if (! $dryRun) {
            $this->deleteEmptyDirectories($disk, 'project-files');
        }

        return [
            'orphan_project_files_deleted' => $deletedFiles,
            'orphan_project_files_bytes' => $deletedBytes,
        ];
    }

    /**
     * @return array{build_paths_nullified: int}
     */
    private function syncBuildPaths(
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        bool $dryRun
    ): array {
        $nullified = 0;

        Project::withTrashed()
            ->whereNotNull('build_path')
            ->select(['id', 'build_path'])
            ->chunk(200, function ($projects) use ($disk, $dryRun, &$nullified): void {
                foreach ($projects as $project) {
                    if ($project->build_path && $disk->exists($project->build_path)) {
                        continue;
                    }

                    $nullified++;
                    if (! $dryRun) {
                        $project->forceFill(['build_path' => null])->saveQuietly();
                    }
                }
            });

        return [
            'build_paths_nullified' => $nullified,
        ];
    }

    /**
     * @return array{projects_storage_resynced: int, storage_drift_bytes: int}
     */
    private function syncProjectStorageUsage(bool $dryRun): array
    {
        $storageByProject = ProjectFile::withoutTenantProject()
            ->selectRaw('project_id, SUM(size) as total_size')
            ->groupBy('project_id')
            ->pluck('total_size', 'project_id');

        $resynced = 0;
        $driftBytes = 0;

        Project::withTrashed()
            ->select(['id', 'storage_used_bytes'])
            ->chunk(200, function ($projects) use ($storageByProject, $dryRun, &$resynced, &$driftBytes): void {
                foreach ($projects as $project) {
                    $actual = (int) ($storageByProject[$project->id] ?? 0);
                    $stored = (int) ($project->storage_used_bytes ?? 0);

                    if ($actual === $stored) {
                        continue;
                    }

                    $resynced++;
                    $driftBytes += abs($actual - $stored);

                    if (! $dryRun) {
                        $project->forceFill(['storage_used_bytes' => $actual])->saveQuietly();
                    }
                }
            });

        return [
            'projects_storage_resynced' => $resynced,
            'storage_drift_bytes' => $driftBytes,
        ];
    }

    /**
     * @return array{operation_logs_pruned: int}
     */
    private function pruneOperationLogs(bool $dryRun): array
    {
        if (! Schema::hasTable('operation_logs')) {
            return ['operation_logs_pruned' => 0];
        }

        $days = max(1, (int) config('ops.log_days', 30));
        $cutoff = now()->subDays($days);

        $query = OperationLog::query()->where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if (! $dryRun && $count > 0) {
            $query->delete();
        }

        return [
            'operation_logs_pruned' => $count,
        ];
    }

    private function deleteEmptyDirectories(
        \Illuminate\Contracts\Filesystem\Filesystem $disk,
        string $basePath
    ): void {
        if (! $disk->exists($basePath)) {
            return;
        }

        $directories = $disk->allDirectories($basePath);
        usort($directories, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($directories as $directory) {
            if (! $this->isDirectoryEmpty($disk, $directory)) {
                continue;
            }

            $disk->deleteDirectory($directory);
        }
    }

    private function isDirectoryEmpty(\Illuminate\Contracts\Filesystem\Filesystem $disk, string $directory): bool
    {
        return $disk->files($directory) === [] && $disk->directories($directory) === [];
    }

    private function buildSummaryMessage(array $summary): string
    {
        return sprintf(
            'Capacity cleanup complete: previews=%d, published=%d, project_file_dirs=%d, build_dirs=%d, build_files=%d, temp_files=%d, db_orphans=%d, db_missing=%d, disk_orphans=%d, build_paths=%d, storage_resynced=%d.',
            (int) $summary['orphan_preview_dirs'],
            (int) $summary['orphan_published_dirs'],
            (int) $summary['orphan_project_file_dirs'],
            (int) $summary['orphan_build_dirs'],
            (int) $summary['build_files_pruned'],
            (int) $summary['temp_files_pruned'],
            (int) $summary['orphan_project_file_records_deleted'],
            (int) $summary['missing_project_file_records_deleted'],
            (int) $summary['orphan_project_files_deleted'],
            (int) $summary['build_paths_nullified'],
            (int) $summary['projects_storage_resynced'],
        );
    }
}
