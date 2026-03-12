<?php

namespace App\Services;

use App\Models\OperationLog;
use App\Models\SectionLibrary;
use App\Models\Template;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class TemplateResetService
{
    public function __construct(
        protected OperationLogService $operationLogService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function reset(bool $skipBackup = false, string $backupDirectory = 'backups', bool $wipeSites = true): array
    {
        $backupRelativePath = null;

        if (! $skipBackup) {
            $backupRelativePath = $this->createBackupSnapshot($backupDirectory);
        }

        $before = $this->counts();

        DB::transaction(function () use ($wipeSites): void {
            DB::table('plan_template')->delete();
            // Delete templates via query builder so model events (e.g. is_system guard) are not triggered.
            DB::table('templates')->delete();
            SectionLibrary::query()->delete();

            if ($wipeSites) {
                DB::table('sites')->delete();
            }
        });

        $this->cleanupTemplateFilesystem();

        $after = $this->counts();

        $restoreCommand = $backupRelativePath ? $this->renderRestoreCommand($backupRelativePath) : null;

        $this->operationLogService->log(
            channel: OperationLog::CHANNEL_SYSTEM,
            event: 'templates_reset',
            status: OperationLog::STATUS_SUCCESS,
            message: 'Template/CMS library reset completed.',
            attributes: [
                'context' => [
                    'before' => $before,
                    'after' => $after,
                    'backup' => $backupRelativePath,
                    'wipe_sites' => $wipeSites,
                ],
            ]
        );

        return [
            'backup' => $backupRelativePath,
            'restore_command' => $restoreCommand,
            'before' => $before,
            'after' => $after,
            'wipe_sites' => $wipeSites,
        ];
    }

    /**
     * @return array<string, int>
     */
    private function counts(): array
    {
        return [
            'templates' => Template::query()->count(),
            'sections_library' => SectionLibrary::query()->count(),
            'sites' => DB::table('sites')->count(),
            'pages' => DB::table('pages')->count(),
            'page_revisions' => DB::table('page_revisions')->count(),
            'menus' => DB::table('menus')->count(),
            'global_settings' => DB::table('global_settings')->count(),
            'media' => DB::table('media')->count(),
        ];
    }

    private function cleanupTemplateFilesystem(): void
    {
        $paths = [
            public_path('template-demos'),
            public_path('themes'),
            storage_path('app/templates'),
            base_path('templates'),
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) {
                File::deleteDirectory($path);
            }

            File::ensureDirectoryExists($path);
        }
    }

    private function createBackupSnapshot(string $backupDirectory): string
    {
        $backupDirectory = trim($backupDirectory, '/');
        if ($backupDirectory === '') {
            $backupDirectory = 'backups';
        }

        $targetRelative = $backupDirectory.'/webu-before-template-reset-'.now()->format('Ymd').'.sql.gz';
        $targetAbsolute = storage_path('app/'.$targetRelative);

        File::ensureDirectoryExists(dirname($targetAbsolute));

        $commandExitCode = Artisan::call('backup:create-artifact', [
            '--path' => $backupDirectory,
            '--keep' => 14,
            '--skip-db' => 0,
            '--triggered-by' => 'templates:reset',
        ]);

        $latestBackup = $this->latestBackupDumpPath($backupDirectory);
        if ($commandExitCode === 0 && $latestBackup !== null && is_file($latestBackup)) {
            File::copy($latestBackup, $targetAbsolute);

            return $targetRelative;
        }

        $this->writeFallbackSqlDump($targetAbsolute);

        return $targetRelative;
    }

    private function latestBackupDumpPath(string $backupDirectory): ?string
    {
        $glob = storage_path('app/'.trim($backupDirectory, '/').'/backup-*.sql.gz');
        $files = glob($glob) ?: [];

        if ($files === []) {
            return null;
        }

        usort(
            $files,
            static fn (string $a, string $b): int => filemtime($b) <=> filemtime($a)
        );

        return $files[0] ?? null;
    }

    private function writeFallbackSqlDump(string $targetAbsolutePath): void
    {
        $tables = $this->resolveTables();

        $gz = gzopen($targetAbsolutePath, 'wb9');
        if ($gz === false) {
            throw new \RuntimeException('Unable to create fallback backup gzip file.');
        }

        gzwrite($gz, "-- Webu fallback SQL backup\n");
        gzwrite($gz, '-- generated_at: '.now()->toIso8601String()."\n\n");
        gzwrite($gz, "BEGIN TRANSACTION;\n\n");

        foreach ($tables as $table) {
            $createSql = $this->resolveCreateStatement($table);
            if ($createSql !== null && trim($createSql) !== '') {
                gzwrite($gz, '-- table: '.$table."\n");
                gzwrite($gz, rtrim($createSql, ';').";\n");
            }

            $rows = DB::table($table)->get()->map(fn ($row): array => (array) $row)->all();
            foreach ($rows as $row) {
                $columns = array_keys($row);
                $values = array_map(fn ($value): string => $this->toSqlValue($value), array_values($row));
                $quotedColumns = array_map(static fn (string $column): string => '`'.$column.'`', $columns);

                gzwrite(
                    $gz,
                    sprintf(
                        "INSERT INTO `%s` (%s) VALUES (%s);\n",
                        $table,
                        implode(', ', $quotedColumns),
                        implode(', ', $values)
                    )
                );
            }

            gzwrite($gz, "\n");
        }

        gzwrite($gz, "COMMIT;\n");
        gzclose($gz);
    }

    /**
     * @return array<int, string>
     */
    private function resolveTables(): array
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::select("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'");

            return collect($rows)
                ->map(fn ($row): string => (string) ($row->name ?? ''))
                ->filter(fn (string $table): bool => $table !== '')
                ->values()
                ->all();
        }

        if ($driver === 'mysql') {
            $rows = DB::select('SHOW TABLES');

            return collect($rows)
                ->map(function ($row): string {
                    $values = array_values((array) $row);

                    return (string) ($values[0] ?? '');
                })
                ->filter(fn (string $table): bool => $table !== '')
                ->values()
                ->all();
        }

        throw new \RuntimeException('Unsupported database driver for fallback backup: '.$driver);
    }

    private function resolveCreateStatement(string $table): ?string
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $row = DB::selectOne(
                "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$table]
            );

            return is_object($row) ? (string) ($row->sql ?? '') : null;
        }

        if ($driver === 'mysql') {
            $result = DB::select('SHOW CREATE TABLE `'.$table.'`');
            if ($result === []) {
                return null;
            }

            $record = (array) $result[0];
            foreach ($record as $key => $value) {
                if (str_starts_with((string) $key, 'Create Table')) {
                    return (string) $value;
                }
            }
        }

        return null;
    }

    private function toSqlValue(mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_array($value) || is_object($value)) {
            $value = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $string = str_replace("'", "''", (string) $value);

        return "'{$string}'";
    }

    private function renderRestoreCommand(string $backupRelativePath): string
    {
        $backupAbsolutePath = storage_path('app/'.$backupRelativePath);
        $connection = (string) config('database.default');

        if ($connection === 'mysql') {
            $host = (string) config('database.connections.mysql.host', '127.0.0.1');
            $port = (string) config('database.connections.mysql.port', '3306');
            $database = (string) config('database.connections.mysql.database', 'webu');
            $username = (string) config('database.connections.mysql.username', 'root');

            return sprintf(
                'gunzip -c "%s" | mysql -h%s -P%s -u%s -p %s',
                $backupAbsolutePath,
                $host,
                $port,
                $username,
                $database
            );
        }

        $sqlitePath = (string) config('database.connections.sqlite.database', database_path('database.sqlite'));

        return sprintf(
            'gunzip -c "%s" | sqlite3 "%s"',
            $backupAbsolutePath,
            $sqlitePath
        );
    }
}
