<?php

namespace Tests\Feature\Operations;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BackupReadinessCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_backup_readiness_check_fails_when_no_backup_files_exist(): void
    {
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('backups');

        $this->artisan('backup:readiness-check', [
            '--path' => 'backups',
            '--max-age-hours' => 24,
        ])->assertExitCode(1);
    }

    public function test_backup_readiness_check_passes_when_recent_backup_exists(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/db-'.now()->format('Ymd-His').'.sql.gz', 'fake backup payload');

        $this->artisan('backup:readiness-check', [
            '--path' => 'backups',
            '--max-age-hours' => 24,
        ])->assertExitCode(0);
    }
}
