<?php

namespace Tests\Feature\Operations;

use App\Models\OperationLog;
use App\Models\SystemSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ReleasePostLaunchReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_post_launch_review_generates_slo_snapshot_from_operation_logs(): void
    {
        Storage::fake('local');

        OperationLog::query()->create([
            'channel' => OperationLog::CHANNEL_PUBLISH,
            'event' => 'publish_completed',
            'status' => OperationLog::STATUS_SUCCESS,
            'occurred_at' => now()->subHour(),
        ]);
        OperationLog::query()->create([
            'channel' => OperationLog::CHANNEL_PUBLISH,
            'event' => 'publish_completed',
            'status' => OperationLog::STATUS_SUCCESS,
            'occurred_at' => now()->subHour(),
        ]);
        OperationLog::query()->create([
            'channel' => OperationLog::CHANNEL_PUBLISH,
            'event' => 'publish_failed',
            'status' => OperationLog::STATUS_ERROR,
            'occurred_at' => now()->subHour(),
        ]);

        OperationLog::query()->create([
            'channel' => OperationLog::CHANNEL_PAYMENT,
            'event' => 'webhook_processed',
            'status' => OperationLog::STATUS_SUCCESS,
            'occurred_at' => now()->subHour(),
        ]);
        OperationLog::query()->create([
            'channel' => OperationLog::CHANNEL_PAYMENT,
            'event' => 'webhook_exception',
            'status' => OperationLog::STATUS_ERROR,
            'occurred_at' => now()->subHour(),
        ]);

        OperationLog::query()->create([
            'channel' => OperationLog::CHANNEL_BOOKING,
            'event' => 'booking_created',
            'status' => OperationLog::STATUS_SUCCESS,
            'occurred_at' => now()->subHour(),
        ]);

        $this->artisan('release:post-launch-review', [
            '--hours' => 24,
            '--top-failures' => 5,
        ])->assertExitCode(0);

        Storage::disk('local')->assertExists('release/post-launch-review-latest.json');
        $report = json_decode((string) Storage::disk('local')->get('release/post-launch-review-latest.json'), true);

        $this->assertSame(66.67, (float) ($report['slo_snapshot']['publish_success_rate'] ?? 0));
        $this->assertSame(50.0, (float) ($report['slo_snapshot']['checkout_success_rate'] ?? 0));
        $this->assertSame(100.0, (float) ($report['slo_snapshot']['booking_creation_success_rate'] ?? 0));
        $this->assertNotEmpty($report['top_failures'] ?? []);
    }
}
