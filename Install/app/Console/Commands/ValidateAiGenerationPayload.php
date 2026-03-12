<?php

namespace App\Console\Commands;

use App\Services\CmsAiSchemaValidationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ValidateAiGenerationPayload extends Command
{
    protected $signature = 'cms:ai-validate-payload
        {contract : input|output}
        {file : Path to JSON payload file}
        {--json : Print full machine-readable validation report}';

    protected $description = 'Validate AI generation input/output payloads against canonical CMS JSON schemas.';

    public function __construct(
        protected CmsAiSchemaValidationService $validator
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $contract = strtolower(trim((string) $this->argument('contract')));
        $fileArgument = trim((string) $this->argument('file'));

        $path = $this->resolvePayloadPath($fileArgument);
        if (! File::exists($path) || ! File::isFile($path)) {
            $this->error('Payload file not found: '.$path);

            return self::FAILURE;
        }

        $rawJson = (string) File::get($path);
        $report = match ($contract) {
            'input' => $this->validator->validateInputJsonString($rawJson),
            'output' => $this->validator->validateOutputJsonString($rawJson),
            default => [
                'valid' => false,
                'schema' => null,
                'error_count' => 1,
                'errors' => [[
                    'code' => 'unknown_contract',
                    'path' => '$',
                    'message' => 'Unknown contract. Use input or output.',
                    'expected' => ['input', 'output'],
                    'actual' => $contract,
                ]],
            ],
        };

        if ((bool) $this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->renderHumanReadableReport($path, $report);
        }

        return (bool) ($report['valid'] ?? false) ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $report
     */
    private function renderHumanReadableReport(string $path, array $report): void
    {
        $valid = (bool) ($report['valid'] ?? false);
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        $errorCount = (int) ($report['error_count'] ?? ($summary['error_count'] ?? 0));
        $warningCount = (int) ($summary['warning_count'] ?? 0);
        $contract = (string) ($report['contract'] ?? '');
        if ($contract === '') {
            $contract = 'unknown';
        }
        $schemaFile = (string) ($report['schema_file'] ?? ($report['schema'] ?? ''));

        if ($valid) {
            $this->info("AI payload is valid [contract={$contract}]");
        } else {
            $this->error("AI payload validation failed [contract={$contract}]");
        }

        $this->table(
            ['Field', 'Value'],
            [
                ['Payload', $path],
                ['Schema', $schemaFile],
                ['Valid', $valid ? 'yes' : 'no'],
                ['Errors', (string) $errorCount],
                ['Warnings', (string) $warningCount],
            ]
        );

        $errors = is_array($report['errors'] ?? null) ? array_slice($report['errors'], 0, 12) : [];
        if ($errors !== []) {
            $this->newLine();
            $this->line('Top errors:');
            $this->table(
                ['Path', 'Code', 'Keyword', 'Message'],
                array_map(
                    fn (array $error): array => [
                        (string) ($error['path'] ?? ''),
                        (string) ($error['code'] ?? ''),
                        (string) ($error['keyword'] ?? ''),
                        (string) ($error['message'] ?? ''),
                    ],
                    $errors
                )
            );
        }

        $warnings = is_array($report['warnings'] ?? null) ? array_slice($report['warnings'], 0, 8) : [];
        if ($warnings !== []) {
            $this->newLine();
            $this->line('Warnings:');
            $this->table(
                ['Keyword', 'Schema', 'Message'],
                array_map(
                    fn (array $warning): array => [
                        (string) ($warning['keyword'] ?? ''),
                        (string) ($warning['schema'] ?? ''),
                        (string) ($warning['message'] ?? ''),
                    ],
                    $warnings
                )
            );
        }
    }

    private function resolvePayloadPath(string $value): string
    {
        if ($value === '') {
            return $value;
        }

        if (str_starts_with($value, '/') || preg_match('/^[A-Za-z]:\\\\/', $value) === 1) {
            return $value;
        }

        return base_path($value);
    }
}
