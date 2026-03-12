<?php

namespace App\Console\Commands;

use App\Models\CronLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

class CreateBackupArtifact extends Command
{
    protected $signature = 'backup:create-artifact
                            {--path=backups : Storage path where backup artifacts are stored}
                            {--keep=7 : Number of backup snapshots to keep}
                            {--skip-db=0 : Skip database dump generation (true/false)}
                            {--triggered-by=cron : Who triggered this command}';

    protected $description = 'Create backup artifact (manifest + database dump) for restore readiness';

    public function handle(): int
    {
        $cronLog = CronLog::startLog(
            'Create Backup Artifact',
            self::class,
            (string) $this->option('triggered-by')
        );

        try {
            $path = trim((string) $this->option('path'));
            if ($path === '') {
                $path = 'backups';
            }

            $keep = max(1, (int) $this->option('keep'));
            $skipDb = $this->toBool($this->option('skip-db'));
            $disk = Storage::disk('local');

            $disk->makeDirectory($path);

            $timestamp = now()->format('Ymd-His');
            $artifactKey = "backup-{$timestamp}";

            $manifest = [
                'generated_at' => now()->toIso8601String(),
                'app_name' => config('app.name'),
                'app_env' => config('app.env'),
                'app_version' => app()->version(),
                'database' => [
                    'connection' => config('database.default'),
                    'status' => $skipDb ? 'skipped' : 'pending',
                ],
            ];

            $dbArtifactPath = null;
            if (! $skipDb) {
                $dbResult = $this->createDatabaseDump($path, $artifactKey);
                $manifest['database'] = array_merge($manifest['database'], $dbResult['meta']);

                if (! $dbResult['ok']) {
                    $summary = 'Backup artifact failed: '.($dbResult['meta']['error'] ?? 'database dump failed');
                    $cronLog->markFailed($summary, $summary);
                    $this->error($summary);

                    return self::FAILURE;
                }

                $dbArtifactPath = (string) ($dbResult['path'] ?? '');
            }

            $manifestPath = "{$path}/{$artifactKey}.manifest.json";
            $disk->put(
                $manifestPath,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            $deletedArtifacts = $this->pruneOldSnapshots($path, $keep);

            $summary = sprintf(
                'Backup artifact created (%s)%s. Pruned old snapshots: %d',
                $manifestPath,
                $dbArtifactPath ? ", db dump: {$dbArtifactPath}" : '',
                $deletedArtifacts
            );

            $cronLog->markSuccess($summary);
            Log::info('Backup artifact created', [
                'manifest' => $manifestPath,
                'db_dump' => $dbArtifactPath,
                'deleted' => $deletedArtifacts,
                'path' => $path,
                'keep' => $keep,
                'skip_db' => $skipDb,
            ]);

            $this->info($summary);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $cronLog->markFailed($e->getTraceAsString(), $e->getMessage());
            Log::error('Backup artifact creation failed', [
                'error' => $e->getMessage(),
            ]);
            $this->error('Backup artifact creation failed: '.$e->getMessage());

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
     * @return array{ok: bool, path?: string, meta: array<string, mixed>}
     */
    private function createDatabaseDump(string $path, string $artifactKey): array
    {
        $connectionName = (string) config('database.default');
        $connection = config("database.connections.{$connectionName}");

        if (! is_array($connection)) {
            return [
                'ok' => false,
                'meta' => [
                    'status' => 'failed',
                    'error' => "Missing database connection config: {$connectionName}",
                ],
            ];
        }

        $driver = (string) ($connection['driver'] ?? '');
        if ($driver !== 'mysql') {
            return [
                'ok' => false,
                'meta' => [
                    'status' => 'failed',
                    'error' => "Unsupported database driver for dump: {$driver}",
                ],
            ];
        }

        $host = (string) ($connection['host'] ?? '127.0.0.1');
        $port = (string) ($connection['port'] ?? 3306);
        $database = (string) ($connection['database'] ?? '');
        $username = (string) ($connection['username'] ?? '');
        $password = (string) ($connection['password'] ?? '');

        if ($database === '' || $username === '') {
            return [
                'ok' => false,
                'meta' => [
                    'status' => 'failed',
                    'error' => 'Database name/username is not configured for backup dump.',
                ],
            ];
        }

        $tempSqlPath = tempnam(sys_get_temp_dir(), 'webby-backup-sql-');
        if (! is_string($tempSqlPath) || $tempSqlPath === '') {
            return [
                'ok' => false,
                'meta' => [
                    'status' => 'failed',
                    'error' => 'Failed to allocate temporary file for SQL dump.',
                ],
            ];
        }

        $sqlHandle = fopen($tempSqlPath, 'wb');
        if ($sqlHandle === false) {
            @unlink($tempSqlPath);

            return [
                'ok' => false,
                'meta' => [
                    'status' => 'failed',
                    'error' => 'Failed to open temporary SQL dump file for writing.',
                ],
            ];
        }

        try {
            $process = new Process([
                'mysqldump',
                '--single-transaction',
                '--quick',
                '--skip-lock-tables',
                '--routines',
                '--triggers',
                '--default-character-set=utf8mb4',
                "--host={$host}",
                "--port={$port}",
                "--user={$username}",
                $database,
            ]);

            $process->setTimeout(600);
            $process->setIdleTimeout(120);
            $process->setEnv($password !== '' ? ['MYSQL_PWD' => $password] : []);
            $process->run(function (string $type, string $buffer) use ($sqlHandle): void {
                if ($type === Process::ERR) {
                    return;
                }

                fwrite($sqlHandle, $buffer);
            });

            fflush($sqlHandle);
            fclose($sqlHandle);

            if (! $process->isSuccessful()) {
                @unlink($tempSqlPath);

                return [
                    'ok' => false,
                    'meta' => [
                        'status' => 'failed',
                        'error' => trim($process->getErrorOutput()) ?: 'mysqldump failed',
                    ],
                ];
            }

            if (! file_exists($tempSqlPath) || filesize($tempSqlPath) === 0) {
                @unlink($tempSqlPath);

                return [
                    'ok' => false,
                    'meta' => [
                        'status' => 'failed',
                        'error' => 'mysqldump produced empty output.',
                    ],
                ];
            }

            $compressedPath = tempnam(sys_get_temp_dir(), 'webby-backup-gz-');
            if (! is_string($compressedPath) || $compressedPath === '') {
                @unlink($tempSqlPath);

                return [
                    'ok' => false,
                    'meta' => [
                        'status' => 'failed',
                        'error' => 'Failed to allocate temporary file for compressed dump.',
                    ],
                ];
            }

            $compressedOk = $this->gzipFile($tempSqlPath, $compressedPath);
            @unlink($tempSqlPath);

            if (! $compressedOk) {
                @unlink($compressedPath);

                return [
                    'ok' => false,
                    'meta' => [
                        'status' => 'failed',
                        'error' => 'Failed to gzip SQL dump.',
                    ],
                ];
            }

            $artifactPath = "{$path}/{$artifactKey}.sql.gz";
            $stream = fopen($compressedPath, 'rb');
            if ($stream === false) {
                @unlink($compressedPath);

                return [
                    'ok' => false,
                    'meta' => [
                        'status' => 'failed',
                        'error' => 'Failed to open compressed dump for storage write.',
                    ],
                ];
            }

            $written = Storage::disk('local')->writeStream($artifactPath, $stream);
            fclose($stream);
            @unlink($compressedPath);

            if ($written === false) {
                return [
                    'ok' => false,
                    'meta' => [
                        'status' => 'failed',
                        'error' => 'Failed to persist compressed dump to storage.',
                    ],
                ];
            }

            return [
                'ok' => true,
                'path' => $artifactPath,
                'meta' => [
                    'status' => 'ok',
                    'driver' => $driver,
                    'host' => $host,
                    'port' => $port,
                    'database' => $database,
                    'artifact' => $artifactPath,
                ],
            ];
        } catch (\Throwable $e) {
            if (is_resource($sqlHandle)) {
                fclose($sqlHandle);
            }

            @unlink($tempSqlPath);

            return [
                'ok' => false,
                'meta' => [
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                ],
            ];
        }
    }

    private function gzipFile(string $sourcePath, string $targetPath): bool
    {
        $source = fopen($sourcePath, 'rb');
        if ($source === false) {
            return false;
        }

        $target = gzopen($targetPath, 'wb9');
        if ($target === false) {
            fclose($source);

            return false;
        }

        try {
            while (! feof($source)) {
                $chunk = fread($source, 1024 * 512);
                if ($chunk === false) {
                    return false;
                }

                if ($chunk === '') {
                    continue;
                }

                if (gzwrite($target, $chunk) === false) {
                    return false;
                }
            }
        } finally {
            fclose($source);
            gzclose($target);
        }

        return true;
    }

    private function pruneOldSnapshots(string $path, int $keep): int
    {
        $disk = Storage::disk('local');
        $allFiles = collect($disk->files($path));

        $manifests = $allFiles
            ->filter(fn (string $file): bool => str_ends_with($file, '.manifest.json'))
            ->sortDesc()
            ->values();

        if ($manifests->count() <= $keep) {
            return 0;
        }

        $deleted = 0;

        foreach ($manifests->slice($keep) as $manifestPath) {
            $prefix = substr($manifestPath, 0, -strlen('.manifest.json'));
            $candidateFiles = [
                $manifestPath,
                "{$prefix}.sql.gz",
            ];

            foreach ($candidateFiles as $candidate) {
                if ($disk->exists($candidate) && $disk->delete($candidate)) {
                    $deleted++;
                }
            }
        }

        return $deleted;
    }
}

