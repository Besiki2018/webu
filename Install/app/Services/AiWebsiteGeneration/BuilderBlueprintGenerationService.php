<?php

namespace App\Services\AiWebsiteGeneration;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class BuilderBlueprintGenerationService
{
    private const PROCESS_TIMEOUT_SECONDS = 45;

    /**
     * @param  array{projectType?: string|null, brandName?: string|null}  $context
     * @return array{
     *   blueprint: array<string, mixed>,
     *   projectType: string,
     *   project?: array<string, mixed>,
     *   sitePlan: array<string, mixed>,
     *   generationLog: array<int, array<string, mixed>>,
     *   diagnostics: array<string, mixed>|null
     * }
     */
    public function generate(string $prompt, array $context = []): array
    {
        $payload = [
            'prompt' => trim($prompt),
            'projectType' => isset($context['projectType']) && is_string($context['projectType']) && trim((string) $context['projectType']) !== ''
                ? trim((string) $context['projectType'])
                : null,
            'brandName' => isset($context['brandName']) && is_string($context['brandName']) && trim((string) $context['brandName']) !== ''
                ? trim((string) $context['brandName'])
                : null,
        ];

        $process = new Process(
            $this->command(),
            base_path(),
            [
                ...$_ENV,
                ...$_SERVER,
                'NO_COLOR' => '1',
                'NODE_NO_WARNINGS' => '1',
            ],
            json_encode($payload, JSON_THROW_ON_ERROR),
            self::PROCESS_TIMEOUT_SECONDS
        );

        try {
            $process->mustRun();
        } catch (ProcessTimedOutException $e) {
            throw new \RuntimeException('Builder blueprint generation timed out before it could assemble the site.', previous: $e);
        } catch (\Throwable $e) {
            $stderr = trim($process->getErrorOutput());
            $message = $stderr !== ''
                ? 'Builder blueprint generation failed: '.$stderr
                : 'Builder blueprint generation process could not be started.';

            throw new \RuntimeException($message, previous: $e);
        }

        $decoded = $this->decodeJsonPayload($process->getOutput(), $process->getErrorOutput());

        if (! is_array($decoded)) {
            throw new \RuntimeException('Builder blueprint generation returned an unexpected payload.');
        }

        if (($decoded['ok'] ?? false) !== true) {
            $error = isset($decoded['error']) && is_string($decoded['error']) && trim((string) $decoded['error']) !== ''
                ? trim((string) $decoded['error'])
                : 'Builder blueprint generation failed.';
            $rootCause = isset($decoded['diagnostics']['rootCause']) && is_string($decoded['diagnostics']['rootCause'])
                ? trim((string) $decoded['diagnostics']['rootCause'])
                : '';

            if ($rootCause !== '' && $rootCause !== $error) {
                $error .= ' '.$rootCause;
            }

            throw new \RuntimeException($error);
        }

        $sitePlan = is_array($decoded['sitePlan'] ?? null) ? $decoded['sitePlan'] : [];
        $pages = is_array($sitePlan['pages'] ?? null) ? $sitePlan['pages'] : [];
        if ($pages === []) {
            throw new \RuntimeException('Builder blueprint generation returned no pages.');
        }

        return [
            'blueprint' => is_array($decoded['blueprint'] ?? null) ? $decoded['blueprint'] : [],
            'projectType' => is_string($decoded['projectType'] ?? null) ? (string) $decoded['projectType'] : 'landing',
            'project' => is_array($decoded['project'] ?? null) ? $decoded['project'] : [],
            'sitePlan' => $sitePlan,
            'generationLog' => is_array($decoded['generationLog'] ?? null) ? $decoded['generationLog'] : [],
            'diagnostics' => is_array($decoded['diagnostics'] ?? null) ? $decoded['diagnostics'] : null,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function command(): array
    {
        $localTsx = DIRECTORY_SEPARATOR === '\\'
            ? base_path('node_modules/.bin/tsx.cmd')
            : base_path('node_modules/.bin/tsx');

        if (is_file($localTsx)) {
            return ['node', '--no-warnings', $localTsx, 'scripts/builder-blueprint-runner.mts'];
        }

        return ['npx', 'tsx', 'scripts/builder-blueprint-runner.mts'];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeJsonPayload(string $stdout, string $stderr): array
    {
        $candidates = array_values(array_filter([
            $stdout,
            $this->extractJsonObject($stdout),
            $stdout !== $stderr ? $stdout."\n".$stderr : null,
            $this->extractJsonObject($stdout."\n".$stderr),
        ], static fn ($candidate): bool => is_string($candidate) && trim($candidate) !== ''));

        $lastError = null;

        foreach ($candidates as $candidate) {
            try {
                $decoded = json_decode(trim($candidate), true, 4096, JSON_THROW_ON_ERROR);
            } catch (\JsonException $e) {
                $lastError = $e;
                continue;
            }

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $preview = $this->previewProcessOutput($stdout, $stderr);
        $message = $lastError instanceof \JsonException
            ? 'Builder blueprint generation returned invalid JSON: '.$lastError->getMessage()
            : 'Builder blueprint generation returned an unexpected payload.';

        throw new \RuntimeException($message.' Raw output: '.$preview, previous: $lastError);
    }

    private function extractJsonObject(string $output): ?string
    {
        $normalized = $this->stripAnsiSequences($output);
        $normalized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $normalized);
        if (! is_string($normalized) || trim($normalized) === '') {
            return null;
        }

        $start = strpos($normalized, '{"ok":');
        if ($start === false) {
            return null;
        }

        $depth = 0;
        $inString = false;
        $escaped = false;
        $length = strlen($normalized);

        for ($index = $start; $index < $length; $index++) {
            $char = $normalized[$index];

            if ($inString) {
                if ($escaped) {
                    $escaped = false;
                    continue;
                }

                if ($char === '\\') {
                    $escaped = true;
                    continue;
                }

                if ($char === '"') {
                    $inString = false;
                }

                continue;
            }

            if ($char === '"') {
                $inString = true;
                continue;
            }

            if ($char === '{') {
                $depth++;
                continue;
            }

            if ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    return substr($normalized, $start, $index - $start + 1);
                }
            }
        }

        return null;
    }

    private function stripAnsiSequences(string $value): string
    {
        $stripped = preg_replace('/\e\[[\d;?]*[ -\/]*[@-~]/u', '', $value);

        return is_string($stripped) ? $stripped : $value;
    }

    private function previewProcessOutput(string $stdout, string $stderr): string
    {
        $preview = trim($this->stripAnsiSequences($stdout."\n".$stderr));
        $preview = preg_replace('/\s+/u', ' ', $preview);

        return is_string($preview) ? mb_substr($preview, 0, 280) : '';
    }
}
