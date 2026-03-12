<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;

class BugfixerProcessLogsCommand extends Command
{
    protected $signature = 'bugfixer:process-logs
                            {--log= : Path to log file (default: storage/logs/laravel.log) }
                            {--threshold=3 : Min frequency for high/critical to auto-fix }
                            {--dry-run : Only intake, do not run auto-fix }';

    protected $description = 'Fetch ERROR logs, normalize, dedup, optionally run auto-fix for high/critical';

    public function handle(): int
    {
        $base = base_path();
        $logPath = $this->option('log') ?: storage_path('logs/laravel.log');
        $threshold = (int) $this->option('threshold');
        $dryRun = (bool) $this->option('dry-run');

        $configPath = $base.DIRECTORY_SEPARATOR.'audit'.DIRECTORY_SEPARATOR.'bugfixer'.DIRECTORY_SEPARATOR.'config.json';
        $config = [
            'autoFixEnabled' => true,
            'severityThreshold' => 'high',
        ];
        if (file_exists($configPath)) {
            $decoded = json_decode((string) file_get_contents($configPath), true);
            if (is_array($decoded)) {
                $config = array_merge($config, $decoded);
            }
        }
        if (! ($config['autoFixEnabled'] ?? true)) {
            $this->info('Auto-fix is disabled. Exiting.');

            return 0;
        }

        $humanApprovalRequired = (bool) ($config['humanApprovalRequired'] ?? false);

        $severityThreshold = $config['severityThreshold'] ?? 'high';
        $minSeverity = $severityThreshold === 'critical' ? 4 : ($severityThreshold === 'high' ? 3 : 2);

        $errors = $this->collectErrors($logPath);
        $list = [];

        if (! empty($errors)) {
            $tmpFile = tempnam(sys_get_temp_dir(), 'bugfixer_intake_');
            if ($tmpFile === false) {
                $this->error('Failed to create temp file.');

                return 1;
            }
            $tmp = fopen($tmpFile, 'w');
            if ($tmp === false) {
                @unlink($tmpFile);
                $this->error('Failed to open temp file.');

                return 1;
            }
            foreach ($errors as $err) {
                fwrite($tmp, json_encode($err)."\n");
            }
            fclose($tmp);

            $intakeResult = Process::timeout(60)->path($base)->run(
                'npx tsx src/bugfixer/cli/intakeFromFile.ts '.escapeshellarg($tmpFile)
            );
            @unlink($tmpFile);

            if (! $intakeResult->successful()) {
                $this->error('Intake failed: '.$intakeResult->errorOutput());

                return 1;
            }

            $output = trim($intakeResult->output());
            $decoded = json_decode($output, true);
            if (is_array($decoded)) {
                $list = $decoded;
            }
        }

        // Ingest AI errors from audit/ai-errors/ into bugfixer events (Tab 9 A3 → pipeline)
        $aiList = $this->ingestAiErrors($base);
        $list = array_merge($list, $aiList);

        if (empty($list)) {
            $this->info('No bug events to process.');

            return 0;
        }

        $severityOrder = ['low' => 1, 'medium' => 2, 'high' => 3, 'critical' => 4];
        $toFix = [];
        $toTicketOnly = [];
        foreach ($list as $item) {
            $sev = $item['severity'] ?? 'low';
            $num = $severityOrder[$sev] ?? 0;
            $bugId = $item['bugId'] ?? null;
            if (! $bugId) {
                continue;
            }
            if ($num >= $minSeverity && (int) ($item['frequency'] ?? 0) >= $threshold) {
                if (! $humanApprovalRequired) {
                    $toFix[] = $bugId;
                }
            } elseif ($sev === 'medium') {
                $toTicketOnly[] = $bugId;
            }
        }

        foreach ($toTicketOnly as $bid) {
            $this->info("Creating ticket only (medium): {$bid}");
            Process::timeout(30)->path($base)->run(
                'npx tsx src/bugfixer/cli/createTicket.ts '.escapeshellarg($bid)
            );
        }

        if ($dryRun) {
            $this->info('Dry run: would run auto-fix for: '.(empty($toFix) ? '(none)' : implode(', ', $toFix)));

            return 0;
        }

        foreach ($toFix as $bugId) {
            $this->info("Running auto-fix for {$bugId}...");
            $fixResult = Process::timeout(300)->path($base)->run(
                'npm run bugfixer:fix -- '.escapeshellarg($bugId)
            );
            if (! $fixResult->successful()) {
                $this->warn("Auto-fix failed for {$bugId}: ".$fixResult->errorOutput());
            }
        }

        return 0;
    }

    /**
     * Collect recent ERROR lines from log file into raw error shape for intake.
     */
    private function collectErrors(string $logPath): array
    {
        if (! file_exists($logPath)) {
            return [];
        }
        $content = file_get_contents($logPath);
        if ($content === false) {
            return [];
        }
        $lines = explode("\n", $content);
        $errors = [];
        $seen = [];
        foreach (array_reverse($lines) as $line) {
            if (stripos($line, 'ERROR') === false && stripos($line, 'error') === false) {
                continue;
            }
            $key = md5($line);
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $errors[] = [
                'type' => 'system_log',
                'level' => 'ERROR',
                'message' => strlen($line) > 500 ? substr($line, 0, 500) : $line,
            ];
            if (count($errors) >= 50) {
                break;
            }
        }
        return array_reverse($errors);
    }

    /**
     * Ingest audit/ai-errors/*.json into bugfixer events (type: ai). Returns same shape as intake output.
     */
    private function ingestAiErrors(string $base): array
    {
        $dir = $base.DIRECTORY_SEPARATOR.'audit'.DIRECTORY_SEPARATOR.'ai-errors';
        if (! is_dir($dir)) {
            return [];
        }
        $files = glob($dir.DIRECTORY_SEPARATOR.'*.json');
        if ($files === false) {
            return [];
        }
        usort($files, fn ($a, $b) => filemtime($b) <=> filemtime($a));
        $files = array_slice($files, 0, 30);
        $lines = [];
        foreach ($files as $path) {
            $content = @file_get_contents($path);
            if ($content === false) {
                continue;
            }
            $data = json_decode($content, true);
            if (! is_array($data) || empty($data['message'] ?? null)) {
                continue;
            }
            $raw = [
                'type' => 'ai',
                'message' => $data['message'],
                'validationErrors' => $data['context'] ?? null,
                'output' => isset($data['exception']['message']) ? substr($data['exception']['message'], 0, 500) : null,
            ];
            $lines[] = json_encode($raw);
        }
        if (empty($lines)) {
            return [];
        }
        $tmpFile = tempnam(sys_get_temp_dir(), 'bugfixer_ai_');
        if ($tmpFile === false) {
            return [];
        }
        file_put_contents($tmpFile, implode("\n", $lines));
        $result = Process::timeout(30)->path($base)->run(
            'npx tsx src/bugfixer/cli/intakeFromFile.ts '.escapeshellarg($tmpFile)
        );
        @unlink($tmpFile);
        if (! $result->successful()) {
            return [];
        }
        $out = trim($result->output());
        $decoded = json_decode($out, true);

        return is_array($decoded) ? $decoded : [];
    }
}
