<?php

namespace Tests\Feature\Operations;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupCreateArtifactCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_backup_create_artifact_generates_manifest_and_passes_readiness(): void
    {
        Storage::fake('local');

        $this->artisan('backup:create-artifact', [
            '--path' => 'backups',
            '--skip-db' => 1,
            '--keep' => 7,
        ])->assertExitCode(0);

        $manifestFiles = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $file): bool => str_ends_with($file, '.manifest.json'))
            ->values();

        $this->assertCount(1, $manifestFiles);
        Storage::disk('local')->assertExists($manifestFiles->first());

        $this->artisan('backup:readiness-check', [
            '--path' => 'backups',
            '--max-age-hours' => 24,
        ])->assertExitCode(0);
    }

    public function test_backup_create_artifact_prunes_old_snapshots_by_keep_limit(): void
    {
        Storage::fake('local');

        Storage::disk('local')->put('backups/backup-20250101-000000.manifest.json', '{}');
        Storage::disk('local')->put('backups/backup-20250101-000000.sql.gz', 'old');
        Storage::disk('local')->put('backups/backup-20250102-000000.manifest.json', '{}');
        Storage::disk('local')->put('backups/backup-20250102-000000.sql.gz', 'old');
        Storage::disk('local')->put('backups/backup-20250103-000000.manifest.json', '{}');
        Storage::disk('local')->put('backups/backup-20250103-000000.sql.gz', 'old');

        $this->artisan('backup:create-artifact', [
            '--path' => 'backups',
            '--skip-db' => 1,
            '--keep' => 2,
        ])->assertExitCode(0);

        $manifestFiles = collect(Storage::disk('local')->files('backups'))
            ->filter(fn (string $file): bool => str_ends_with($file, '.manifest.json'))
            ->values();

        $this->assertCount(2, $manifestFiles);
        $this->assertFalse(Storage::disk('local')->exists('backups/backup-20250101-000000.manifest.json'));
        $this->assertFalse(Storage::disk('local')->exists('backups/backup-20250101-000000.sql.gz'));
    }
}

