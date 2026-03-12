<?php

namespace App\Console\Commands;

use App\Models\CronLog;
use App\Models\OperationLog;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class ReleasePostLaunchReview extends Command
{
    protected $signature = 'release:post-launch-review
                            {--hours=72 : Lookback window in hours}
                            {--top-failures=10 : Number of top failure buckets to include}
                            {--triggered-by=cron : Who triggered this command}';

    protected $description = 'Generate post-launch review report (K4) with SLO snapshot and top failures';

    public function handle(): int
    {
        $cronLog = CronLog::startLog(
            'Release Post-Launch Review',
            self::class,
            (string) $this->option('triggered-by')
        );

        try {
            $hours = max(1, (int) $this->option('hours'));
            $topFailures = max(1, min(50, (int) $this->option('top-failures')));
            $windowStart = now()->subHours($hours);

            $publishMetric = $this->channelMetric(OperationLog::CHANNEL_PUBLISH, $windowStart);
            $checkoutMetric = $this->channelMetric(OperationLog::CHANNEL_PAYMENT, $windowStart);
            $bookingMetric = $this->channelMetric(OperationLog::CHANNEL_BOOKING, $windowStart);

            $failureBuckets = OperationLog::query()
                ->where('status', OperationLog::STATUS_ERROR)
                ->where('occurred_at', '>=', $windowStart)
                ->selectRaw('channel, event, COUNT(*) as failures')
                ->groupBy('channel', 'event')
                ->orderByDesc('failures')
                ->limit($topFailures)
                ->get()
                ->map(fn (OperationLog $row): array => [
                    'channel' => $row->channel,
                    'event' => $row->event,
                    'failures' => (int) $row->failures,
                ])
                ->values()
                ->all();

            $report = [
                'generated_at' => now()->toIso8601String(),
                'window' => [
                    'hours' => $hours,
                    'from' => $windowStart->toIso8601String(),
                ],
                'slo_snapshot' => [
                    'publish_success_rate' => $publishMetric['success_rate'],
                    'checkout_success_rate' => $checkoutMetric['success_rate'],
                    'booking_creation_success_rate' => $bookingMetric['success_rate'],
                ],
                'event_totals' => [
                    'publish' => $publishMetric,
                    'checkout' => $checkoutMetric,
                    'booking' => $bookingMetric,
                ],
                'top_failures' => $failureBuckets,
            ];

            Storage::disk('local')->put(
                'release/post-launch-review-latest.json',
                json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Publish success rate', $this->formatRate($publishMetric['success_rate'])],
                    ['Checkout success rate', $this->formatRate($checkoutMetric['success_rate'])],
                    ['Booking success rate', $this->formatRate($bookingMetric['success_rate'])],
                    ['Top failure buckets', (string) count($failureBuckets)],
                ]
            );

            $summary = sprintf(
                'Post-launch review generated (%dh window, top_failures=%d).',
                $hours,
                count($failureBuckets)
            );

            $cronLog->markSuccess($summary);
            $this->info($summary);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $cronLog->markFailed($e->getTraceAsString(), $e->getMessage());
            $this->error('Post-launch review generation failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array{success: int, failed: int, total: int, success_rate: float|null}
     */
    private function channelMetric(string $channel, Carbon $windowStart): array
    {
        $base = OperationLog::query()
            ->where('channel', $channel)
            ->where('occurred_at', '>=', $windowStart);

        $success = (clone $base)->where('status', OperationLog::STATUS_SUCCESS)->count();
        $failed = (clone $base)->where('status', OperationLog::STATUS_ERROR)->count();
        $total = $success + $failed;

        return [
            'success' => (int) $success,
            'failed' => (int) $failed,
            'total' => (int) $total,
            'success_rate' => $total > 0
                ? round(($success / $total) * 100, 2)
                : null,
        ];
    }

    private function formatRate(?float $rate): string
    {
        if ($rate === null) {
            return 'n/a';
        }

        return number_format($rate, 2).'%';
    }
}
