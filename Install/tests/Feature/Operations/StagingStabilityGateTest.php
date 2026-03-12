<?php

namespace Tests\Feature\Operations;

use App\Models\OperationLog;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StagingStabilityGateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_stability_gate_passes_when_no_critical_errors_exist(): void
    {
        Storage::fake('local');

        $this->artisan('staging:stability-gate', [
            '--hours' => 48,
            '--max-critical' => 0,
            '--max-failed-jobs' => 0,
        ])->assertExitCode(0);

        Storage::disk('local')->assertExists('release/staging-gate-latest.json');
    }

    public function test_stability_gate_fails_when_critical_errors_exceed_threshold(): void
    {
        Storage::fake('local');

        OperationLog::query()->create([
            'channel' => OperationLog::CHANNEL_SYSTEM,
            'event' => 'critical_failure',
            'status' => OperationLog::STATUS_ERROR,
            'message' => 'Simulated critical failure',
            'occurred_at' => now()->subMinutes(10),
        ]);

        $this->artisan('staging:stability-gate', [
            '--hours' => 48,
            '--max-critical' => 0,
            '--max-failed-jobs' => 0,
        ])->assertExitCode(1);
    }
}
