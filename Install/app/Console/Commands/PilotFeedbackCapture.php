<?php

namespace App\Console\Commands;

use App\Models\CronLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PilotFeedbackCapture extends Command
{
    protected $signature = 'pilot:feedback-capture
                            {--tenant= : Tenant identifier}
                            {--project-id= : Optional project identifier}
                            {--vertical= : Business vertical}
                            {--plan= : Plan name}
                            {--modules= : Comma-separated enabled modules}
                            {--scenario= : Scenario that was tested}
                            {--expected= : Expected behavior}
                            {--actual= : Actual observed behavior}
                            {--severity=medium : critical|high|medium|low}
                            {--frequency=single : Frequency or reproducibility note}
                            {--endpoint= : Endpoint or screen reference}
                            {--operation-log-ids= : Comma-separated operation log IDs}
                            {--evidence= : Evidence link (screenshot/video)}
                            {--proposed-fix= : Proposed fix}
                            {--owner= : Owner}
                            {--eta= : ETA}
                            {--status=open : open|resolved}
                            {--triggered-by=cron : Who triggered this command}';

    protected $description = 'Capture a structured pilot feedback entry for K2 tracking';

    /**
     * @var array<int, string>
     */
    private array $allowedSeverities = ['critical', 'high', 'medium', 'low'];

    /**
     * @var array<int, string>
     */
    private array $allowedStatuses = ['open', 'resolved'];

    public function handle(): int
    {
        $cronLog = CronLog::startLog(
            'Pilot Feedback Capture',
            self::class,
            (string) $this->option('triggered-by')
        );

        try {
            $tenant = trim((string) $this->option('tenant'));
            $scenario = trim((string) $this->option('scenario'));
            $actual = trim((string) $this->option('actual'));

            if ($tenant === '' || $scenario === '' || $actual === '') {
                $message = 'Missing required options: --tenant, --scenario, --actual';
                $cronLog->markFailed($message, $message);
                $this->error($message);

                return self::FAILURE;
            }

            $severity = strtolower(trim((string) $this->option('severity')));
            if (! in_array($severity, $this->allowedSeverities, true)) {
                $message = 'Invalid severity. Allowed: critical, high, medium, low';
                $cronLog->markFailed($message, $message);
                $this->error($message);

                return self::FAILURE;
            }

            $status = strtolower(trim((string) $this->option('status')));
            if (! in_array($status, $this->allowedStatuses, true)) {
                $message = 'Invalid status. Allowed: open, resolved';
                $cronLog->markFailed($message, $message);
                $this->error($message);

                return self::FAILURE;
            }

            $entry = [
                'id' => (string) Str::uuid(),
                'captured_at' => now()->toIso8601String(),
                'tenant_id' => $tenant,
                'project_id' => $this->nullableTrimmedOption('project-id'),
                'business_vertical' => $this->nullableTrimmedOption('vertical'),
                'plan' => $this->nullableTrimmedOption('plan'),
                'modules' => $this->parseCsvOption('modules'),
                'scenario' => $scenario,
                'expected' => $this->nullableTrimmedOption('expected'),
                'actual' => $actual,
                'severity' => $severity,
                'frequency' => $this->nullableTrimmedOption('frequency') ?? 'single',
                'endpoint_or_screen' => $this->nullableTrimmedOption('endpoint'),
                'operation_log_ids' => $this->parseCsvOption('operation-log-ids'),
                'evidence' => $this->nullableTrimmedOption('evidence'),
                'proposed_fix' => $this->nullableTrimmedOption('proposed-fix'),
                'owner' => $this->nullableTrimmedOption('owner'),
                'eta' => $this->nullableTrimmedOption('eta'),
                'status' => $status,
            ];

            $disk = Storage::disk('local');
            $path = 'release/pilot-feedback.jsonl';
            $encoded = json_encode($entry, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

            if (! is_string($encoded)) {
                throw new \RuntimeException('Failed to encode pilot feedback payload.');
            }

            $existing = $disk->exists($path)
                ? rtrim((string) $disk->get($path))
                : '';

            $payload = $existing === ''
                ? $encoded.PHP_EOL
                : $existing.PHP_EOL.$encoded.PHP_EOL;

            $disk->put($path, $payload);
            $disk->put(
                'release/pilot-feedback-latest.json',
                json_encode($entry, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            $summary = sprintf(
                'Pilot feedback captured (tenant=%s severity=%s status=%s).',
                $tenant,
                $severity,
                $status
            );

            $cronLog->markSuccess($summary);
            $this->info($summary);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $cronLog->markFailed($e->getTraceAsString(), $e->getMessage());
            $this->error('Pilot feedback capture failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function nullableTrimmedOption(string $option): ?string
    {
        $value = trim((string) $this->option($option));

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, string>
     */
    private function parseCsvOption(string $option): array
    {
        $value = trim((string) $this->option($option));
        if ($value === '') {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (string $item): string => trim($item),
            explode(',', $value)
        )));
    }
}
