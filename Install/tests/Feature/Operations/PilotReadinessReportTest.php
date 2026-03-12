<?php

namespace Tests\Feature\Operations;

use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PilotReadinessReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_pilot_feedback_capture_and_readiness_report_pass_with_valid_thresholds(): void
    {
        Storage::fake('local');

        $this->artisan('pilot:feedback-capture', [
            '--tenant' => 'tenant_1',
            '--vertical' => 'restaurant',
            '--plan' => 'pro',
            '--modules' => 'cms,ecommerce',
            '--scenario' => 'checkout flow',
            '--actual' => 'completed with minor delay',
            '--severity' => 'high',
            '--status' => 'resolved',
        ])->assertExitCode(0);

        $this->artisan('pilot:readiness-report', [
            '--min-tenants' => 1,
            '--max-open-critical' => 0,
        ])->assertExitCode(0);

        Storage::disk('local')->assertExists('release/pilot-readiness-latest.json');

        $report = json_decode((string) Storage::disk('local')->get('release/pilot-readiness-latest.json'), true);
        $this->assertTrue((bool) ($report['passed'] ?? false));
        $this->assertSame(1, (int) ($report['totals']['unique_tenants'] ?? 0));
    }

    public function test_pilot_readiness_report_fails_when_open_critical_or_tenant_threshold_is_not_met(): void
    {
        Storage::fake('local');

        $this->artisan('pilot:feedback-capture', [
            '--tenant' => 'tenant_1',
            '--scenario' => 'booking create',
            '--actual' => 'booking API returned 500',
            '--severity' => 'critical',
            '--status' => 'open',
        ])->assertExitCode(0);

        $this->artisan('pilot:readiness-report', [
            '--min-tenants' => 3,
            '--max-open-critical' => 0,
        ])->assertExitCode(1);

        $report = json_decode((string) Storage::disk('local')->get('release/pilot-readiness-latest.json'), true);
        $this->assertFalse((bool) ($report['passed'] ?? true));
        $this->assertSame(1, (int) ($report['open_severity']['critical'] ?? 0));
    }
}
