<?php

namespace App\Console\Commands;

use App\Models\CronLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PilotReadinessReport extends Command
{
    protected $signature = 'pilot:readiness-report
                            {--path=release/pilot-feedback.jsonl : Storage path to feedback JSONL file}
                            {--min-tenants=3 : Minimum unique tenants required for pilot gate}
                            {--max-open-critical=0 : Maximum allowed open critical findings}
                            {--triggered-by=cron : Who triggered this command}';

    protected $description = 'Generate pilot readiness report (K2) from structured feedback entries';

    public function handle(): int
    {
        $cronLog = CronLog::startLog(
            'Pilot Readiness Report',
            self::class,
            (string) $this->option('triggered-by')
        );

        try {
            $path = trim((string) $this->option('path'));
            $minTenants = max(1, (int) $this->option('min-tenants'));
            $maxOpenCritical = max(0, (int) $this->option('max-open-critical'));
            $disk = Storage::disk('local');

            if (! $disk->exists($path)) {
                $message = "Pilot feedback file not found: {$path}";
                $cronLog->markFailed($message, $message);
                $this->error($message);

                return self::FAILURE;
            }

            $entries = $this->parseJsonLines((string) $disk->get($path));
            $uniqueTenants = collect($entries)
                ->pluck('tenant_id')
                ->filter(fn ($tenant): bool => is_string($tenant) && trim($tenant) !== '')
                ->unique()
                ->values();

            $openFindings = collect($entries)
                ->filter(function (array $entry): bool {
                    $status = strtolower((string) ($entry['status'] ?? 'open'));

                    return $status !== 'resolved';
                });

            $severityCounts = collect(['critical', 'high', 'medium', 'low'])
                ->mapWithKeys(function (string $severity) use ($openFindings): array {
                    return [$severity => $openFindings->where('severity', $severity)->count()];
                })
                ->all();

            $report = [
                'generated_at' => now()->toIso8601String(),
                'source_path' => $path,
                'thresholds' => [
                    'min_tenants' => $minTenants,
                    'max_open_critical' => $maxOpenCritical,
                ],
                'totals' => [
                    'entries' => count($entries),
                    'unique_tenants' => $uniqueTenants->count(),
                    'open_findings' => $openFindings->count(),
                ],
                'open_severity' => $severityCounts,
                'tenant_ids' => $uniqueTenants->all(),
            ];

            $passed = $report['totals']['unique_tenants'] >= $minTenants
                && $report['open_severity']['critical'] <= $maxOpenCritical;

            $report['passed'] = $passed;

            $disk->put(
                'release/pilot-readiness-latest.json',
                json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );

            $summary = sprintf(
                'Pilot readiness %s: tenants=%d/%d, open_critical=%d/%d',
                $passed ? 'passed' : 'failed',
                $report['totals']['unique_tenants'],
                $minTenants,
                $report['open_severity']['critical'],
                $maxOpenCritical
            );

            $this->table(
                ['Metric', 'Value'],
                [
                    ['Unique tenants', (string) $report['totals']['unique_tenants']],
                    ['Open findings', (string) $report['totals']['open_findings']],
                    ['Open critical', (string) $report['open_severity']['critical']],
                    ['Open high', (string) $report['open_severity']['high']],
                    ['Open medium', (string) $report['open_severity']['medium']],
                    ['Open low', (string) $report['open_severity']['low']],
                    ['Gate', $passed ? 'PASS' : 'FAIL'],
                ]
            );

            if (! $passed) {
                $cronLog->markFailed($summary, $summary);
                $this->error($summary);

                return self::FAILURE;
            }

            $cronLog->markSuccess($summary);
            $this->info($summary);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $cronLog->markFailed($e->getTraceAsString(), $e->getMessage());
            $this->error('Pilot readiness report failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseJsonLines(string $raw): array
    {
        $entries = [];
        $lines = preg_split('/\r\n|\r|\n/', $raw) ?: [];

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }

            $decoded = json_decode($trimmed, true);
            if (! is_array($decoded)) {
                continue;
            }

            $decoded['severity'] = strtolower((string) ($decoded['severity'] ?? 'medium'));
            $decoded['status'] = strtolower((string) ($decoded['status'] ?? 'open'));
            $entries[] = $decoded;
        }

        return $entries;
    }
}
