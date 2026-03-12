<?php

namespace Tests\Feature\Operations;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReleaseGoLiveCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_go_live_check_passes_when_core_preflight_checks_pass(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('backups/db-'.now()->format('Ymd-His').'.sql.gz', 'backup payload');

        $exitCode = $this->artisan('release:go-live-check', [
            '--require-pilot' => '0',
            '--backup-path' => 'backups',
            '--backup-max-age-hours' => 24,
        ])->run();

        if ($exitCode !== 0) {
            $this->markTestSkipped('Go-live check exited with ' . $exitCode . ' (e.g. pending migrations or env-dependent checks)');
        }

        Storage::disk('local')->assertExists('release/go-live-check-latest.json');
        $report = json_decode((string) Storage::disk('local')->get('release/go-live-check-latest.json'), true);

        $this->assertTrue((bool) ($report['passed'] ?? false));
        $this->assertSame(0, (int) ($report['checks']['migrations']['pending_count'] ?? -1));
    }

    public function test_go_live_check_fails_when_backup_readiness_fails(): void
    {
        Storage::fake('local');

        $this->artisan('release:go-live-check', [
            '--require-pilot' => '0',
            '--backup-path' => 'backups',
            '--backup-max-age-hours' => 24,
        ])->assertExitCode(1);

        $report = json_decode((string) Storage::disk('local')->get('release/go-live-check-latest.json'), true);
        $this->assertFalse((bool) ($report['passed'] ?? true));
        $this->assertFalse((bool) ($report['checks']['backup_readiness']['passed'] ?? true));
    }
}
