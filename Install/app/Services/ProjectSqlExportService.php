<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectSqlExport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class ProjectSqlExportService
{
    /**
     * Tables that may contain project-scoped data.
     *
     * @var array<int, string>
     */
    private array $candidateTables = [
        'projects',
        'sites',
        'global_settings',
        'menus',
        'pages',
        'page_revisions',
        'media',
        'project_files',
        'operation_logs',
        'build_credit_usages',
        'site_payment_gateway_settings',
        'site_courier_settings',
        'ecommerce_categories',
        'ecommerce_products',
        'ecommerce_product_images',
        'ecommerce_product_variants',
        'ecommerce_inventory_items',
        'ecommerce_inventory_locations',
        'ecommerce_inventory_reservations',
        'ecommerce_stock_movements',
        'ecommerce_carts',
        'ecommerce_cart_items',
        'ecommerce_orders',
        'ecommerce_order_items',
        'ecommerce_order_payments',
        'ecommerce_shipments',
        'ecommerce_shipment_events',
        'ecommerce_accounting_entries',
        'ecommerce_accounting_entry_lines',
        'ecommerce_rs_exports',
        'ecommerce_rs_syncs',
        'ecommerce_rs_sync_attempts',
        'booking_services',
        'booking_staff_resources',
        'booking_staff_roles',
        'booking_staff_role_permissions',
        'booking_staff_role_assignments',
        'booking_staff_work_schedules',
        'booking_staff_time_off',
        'booking_availability_rules',
        'bookings',
        'booking_events',
        'booking_assignments',
        'booking_payments',
        'booking_invoices',
        'booking_refunds',
        'booking_financial_entries',
        'booking_financial_entry_lines',
    ];

    public function export(
        Project $project,
        ?int $requestedBy = null,
        string $disk = 'local',
        string $basePath = 'project-sql-exports'
    ): ProjectSqlExport {
        $export = ProjectSqlExport::query()->create([
            'project_id' => $project->id,
            'requested_by' => $requestedBy,
            'status' => ProjectSqlExport::STATUS_PROCESSING,
            'storage_disk' => $disk,
            'meta_json' => [
                'started_at' => now()->toISOString(),
            ],
        ]);

        try {
            $snapshot = $this->collectSnapshot($project);
            $timestamp = now()->format('Ymd-His');
            $folder = trim($basePath, '/').'/'.$project->id;
            $exportKey = 'project-'.$project->id.'-'.$timestamp;

            $sql = $this->renderSql($project, $snapshot);
            $checksum = hash('sha256', $sql);

            $sqlPath = "{$folder}/{$exportKey}.sql";
            Storage::disk($disk)->put($sqlPath, $sql);
            $size = (int) Storage::disk($disk)->size($sqlPath);

            $manifest = [
                'version' => 'project-sql-export.v1',
                'generated_at' => now()->toIso8601String(),
                'project_id' => (string) $project->id,
                'project_name' => $project->name,
                'sql_path' => $sqlPath,
                'checksum_sha256' => $checksum,
                'storage_disk' => $disk,
                'tables' => collect($snapshot)->map(fn (array $table): array => [
                    'table' => $table['table'],
                    'scope_column' => $table['scope_column'],
                    'rows' => count($table['rows']),
                ])->values()->all(),
                'notes' => [
                    'tenant_scope' => 'project/site scoped rows only',
                    'restore_mode' => 'dry-run validator must pass before restore',
                ],
            ];

            $manifestPath = "{$folder}/{$exportKey}.manifest.json";
            Storage::disk($disk)->put(
                $manifestPath,
                json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );

            $export->update([
                'status' => ProjectSqlExport::STATUS_COMPLETED,
                'sql_path' => $sqlPath,
                'manifest_path' => $manifestPath,
                'checksum' => $checksum,
                'file_size_bytes' => $size,
                'tables_json' => $manifest['tables'],
                'meta_json' => [
                    'started_at' => $export->created_at?->toISOString(),
                    'completed_at' => now()->toISOString(),
                ],
                'error_message' => null,
                'exported_at' => now(),
            ]);
        } catch (Throwable $e) {
            $export->update([
                'status' => ProjectSqlExport::STATUS_FAILED,
                'error_message' => $e->getMessage(),
                'meta_json' => [
                    'started_at' => $export->created_at?->toISOString(),
                    'failed_at' => now()->toISOString(),
                ],
            ]);
        }

        return $export->fresh();
    }

    /**
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>, export: array<string, mixed>|null}
     */
    public function dryRun(Project $project, ?ProjectSqlExport $export = null): array
    {
        $errors = [];
        $warnings = [];

        $resolved = $export;
        if (! $resolved) {
            $resolved = ProjectSqlExport::query()
                ->where('project_id', $project->id)
                ->where('status', ProjectSqlExport::STATUS_COMPLETED)
                ->latest('id')
                ->first();
        }

        if (! $resolved) {
            return [
                'valid' => false,
                'errors' => ['No completed SQL export found for this project.'],
                'warnings' => [],
                'export' => null,
            ];
        }

        $disk = (string) ($resolved->storage_disk ?: 'local');
        if (! Storage::disk($disk)->exists((string) $resolved->sql_path)) {
            $errors[] = 'SQL file is missing from storage.';
        }

        if (! Storage::disk($disk)->exists((string) $resolved->manifest_path)) {
            $errors[] = 'Manifest file is missing from storage.';
        }

        $manifest = null;
        if ($errors === []) {
            $rawManifest = (string) Storage::disk($disk)->get((string) $resolved->manifest_path);
            $decoded = json_decode($rawManifest, true);
            if (! is_array($decoded)) {
                $errors[] = 'Manifest JSON is invalid.';
            } else {
                $manifest = $decoded;
            }
        }

        if (is_array($manifest)) {
            $manifestProjectId = (string) ($manifest['project_id'] ?? '');
            if ($manifestProjectId !== (string) $project->id) {
                $errors[] = 'Manifest project_id does not match requested project.';
            }

            $expectedChecksum = (string) ($manifest['checksum_sha256'] ?? '');
            $sqlPayload = (string) Storage::disk($disk)->get((string) $resolved->sql_path);
            $actualChecksum = hash('sha256', $sqlPayload);
            if ($expectedChecksum !== '' && ! hash_equals($expectedChecksum, $actualChecksum)) {
                $errors[] = 'SQL checksum mismatch detected.';
            }

            $tables = is_array($manifest['tables'] ?? null) ? $manifest['tables'] : [];
            foreach ($tables as $table) {
                $name = (string) ($table['table'] ?? '');
                if ($name === '') {
                    continue;
                }

                if (! Schema::hasTable($name)) {
                    $errors[] = "Target table [{$name}] does not exist in the current database.";
                    continue;
                }

                $rows = (int) ($table['rows'] ?? 0);
                if ($rows === 0) {
                    $warnings[] = "Table [{$name}] has no scoped rows in the package.";
                }
            }
        }

        return [
            'valid' => $errors === [],
            'errors' => $errors,
            'warnings' => array_values(array_unique($warnings)),
            'export' => [
                'id' => $resolved->id,
                'project_id' => $resolved->project_id,
                'status' => $resolved->status,
                'sql_path' => $resolved->sql_path,
                'manifest_path' => $resolved->manifest_path,
                'checksum' => $resolved->checksum,
                'exported_at' => $resolved->exported_at?->toISOString(),
            ],
        ];
    }

    /**
     * @return array<int, array{table: string, scope_column: string, rows: array<int, array<string, mixed>>}>
     */
    private function collectSnapshot(Project $project): array
    {
        $siteIds = [];
        if (Schema::hasTable('sites')) {
            $siteIds = DB::table('sites')
                ->where('project_id', $project->id)
                ->pluck('id')
                ->map(fn ($id): string => (string) $id)
                ->all();
        }

        $snapshot = [];

        foreach ($this->candidateTables as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            [$scopeColumn, $rows] = $this->scopedRows($table, $project, $siteIds);
            if ($scopeColumn === null || $rows === []) {
                continue;
            }

            $snapshot[] = [
                'table' => $table,
                'scope_column' => $scopeColumn,
                'rows' => $rows,
            ];
        }

        return $snapshot;
    }

    /**
     * @param  array<int, string>  $siteIds
     * @return array{0: string|null, 1: array<int, array<string, mixed>>}
     */
    private function scopedRows(string $table, Project $project, array $siteIds): array
    {
        $columns = Schema::getColumnListing($table);

        if ($table === 'projects') {
            $rows = DB::table($table)->where('id', $project->id)->get();

            return ['id', $rows->map(fn ($row): array => (array) $row)->all()];
        }

        if (in_array('project_id', $columns, true)) {
            $query = DB::table($table)->where('project_id', $project->id);

            if (in_array('id', $columns, true)) {
                $query->orderBy('id');
            }

            $rows = $query->get()->map(fn ($row): array => (array) $row)->all();

            return ['project_id', $rows];
        }

        if (in_array('site_id', $columns, true) && $siteIds !== []) {
            $query = DB::table($table)->whereIn('site_id', $siteIds);

            if (in_array('id', $columns, true)) {
                $query->orderBy('id');
            }

            $rows = $query->get()->map(fn ($row): array => (array) $row)->all();

            return ['site_id', $rows];
        }

        return [null, []];
    }

    /**
     * @param  array<int, array{table: string, scope_column: string, rows: array<int, array<string, mixed>>}>  $snapshot
     */
    private function renderSql(Project $project, array $snapshot): string
    {
        $lines = [];
        $lines[] = '-- Webby project SQL export';
        $lines[] = '-- generated_at: '.Carbon::now()->toIso8601String();
        $lines[] = '-- project_id: '.(string) $project->id;
        $lines[] = 'BEGIN TRANSACTION;';
        $lines[] = '';

        foreach ($snapshot as $tableDump) {
            $table = $tableDump['table'];
            $rows = $tableDump['rows'];

            $lines[] = '-- table: '.$table.' (rows: '.count($rows).')';
            $createSql = $this->resolveCreateStatement($table);
            if ($createSql !== null) {
                $lines[] = '-- schema snapshot';
                $lines[] = '-- '.str_replace("\n", "\n-- ", trim($createSql));
            }

            foreach ($rows as $row) {
                $columns = array_keys($row);
                $values = array_map(fn ($value): string => $this->toSqlValue($value), array_values($row));
                $quotedColumns = array_map(fn (string $column): string => '`'.$column.'`', $columns);

                $lines[] = sprintf(
                    'INSERT INTO `%s` (%s) VALUES (%s);',
                    $table,
                    implode(', ', $quotedColumns),
                    implode(', ', $values)
                );
            }

            $lines[] = '';
        }

        $lines[] = 'COMMIT;';
        $lines[] = '';

        return implode("\n", $lines);
    }

    private function resolveCreateStatement(string $table): ?string
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            $row = DB::selectOne(
                "SELECT sql FROM sqlite_master WHERE type = 'table' AND name = ?",
                [$table]
            );
            $sql = is_object($row) ? (string) ($row->sql ?? '') : '';

            return $sql !== '' ? rtrim($sql, ';').';' : null;
        }

        if ($driver === 'mysql') {
            $result = DB::select('SHOW CREATE TABLE `'.$table.'`');
            if ($result === []) {
                return null;
            }

            $row = (array) $result[0];
            foreach ($row as $key => $value) {
                if (Str::startsWith((string) $key, 'Create Table')) {
                    $sql = (string) $value;

                    return $sql !== '' ? rtrim($sql, ';').';' : null;
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

        $string = (string) $value;
        $string = str_replace("'", "''", $string);

        return "'{$string}'";
    }
}
