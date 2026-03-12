<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalTelemetryStorageRetentionP6G1Test extends TestCase
{
    public function test_p6_g1_02_storage_retention_and_anonymization_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/CMS_TELEMETRY_STORAGE_RETENTION_P6_G1_02.md');
        $migrationPath = base_path('database/migrations/2026_02_24_231000_create_cms_telemetry_events_table.php');
        $modelPath = base_path('app/Models/CmsTelemetryEvent.php');
        $storageServicePath = base_path('app/Services/CmsTelemetryEventStorageService.php');
        $collectorServicePath = base_path('app/Services/CmsTelemetryCollectorService.php');
        $commandPath = base_path('app/Console/Commands/PruneCmsTelemetry.php');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([$docPath, $migrationPath, $modelPath, $storageServicePath, $commandPath] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $migration = File::get($migrationPath);
        $model = File::get($modelPath);
        $storage = File::get($storageServicePath);
        $collector = File::get($collectorServicePath);
        $command = File::get($commandPath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('P6-G1-02', $doc);
        $this->assertStringContainsString('cms_telemetry_events', $doc);
        $this->assertStringContainsString('session_hash', $doc);
        $this->assertStringContainsString('client_ip_hash', $doc);
        $this->assertStringContainsString('retention_expires_at', $doc);
        $this->assertStringContainsString('cms:telemetry-prune', $doc);

        $this->assertStringContainsString("Schema::create('cms_telemetry_events'", $migration);
        $this->assertStringContainsString('$table->char(\'site_id\', 36);', $migration);
        $this->assertStringContainsString('$table->char(\'project_id\', 36);', $migration);
        $this->assertStringContainsString('$table->char(\'session_hash\', 64)', $migration);
        $this->assertStringContainsString('$table->char(\'client_ip_hash\', 64)', $migration);
        $this->assertStringContainsString('$table->timestamp(\'retention_expires_at\')', $migration);

        $this->assertStringContainsString('class CmsTelemetryEvent extends Model', $model);
        $this->assertStringContainsString("'route_params_json' => 'array'", $model);
        $this->assertStringContainsString("'retention_expires_at' => 'datetime'", $model);

        $this->assertStringContainsString('class CmsTelemetryEventStorageService', $storage);
        $this->assertStringContainsString('DEFAULT_RETENTION_DAYS = 30', $storage);
        $this->assertStringContainsString('storeBatch(', $storage);
        $this->assertStringContainsString('pruneExpired(', $storage);
        $this->assertStringContainsString("SystemSetting::get('data_retention_days_cms_telemetry'", $storage);
        $this->assertStringContainsString("hash_hmac('sha256'", $storage);
        $this->assertStringContainsString("'[redacted]'", $storage);

        $this->assertStringContainsString('storage_table_missing', $collector);
        $this->assertStringContainsString('stored', $collector);
        $this->assertStringContainsString('retention_days', $collector);
        $this->assertStringContainsString('privacy', $collector);

        $this->assertStringContainsString('protected $signature = \'cms:telemetry-prune\';', $command);
        $this->assertStringContainsString('cms.telemetry.prune', $command);

        $this->assertStringContainsString('- ✅ Event storage and retention strategy', $roadmap);
        $this->assertStringContainsString("`P6-G1-02` (✅ `DONE`) Event storage, retention, and anonymization rules.", $roadmap);
    }
}
