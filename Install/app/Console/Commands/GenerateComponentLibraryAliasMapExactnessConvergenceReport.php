<?php

namespace App\Console\Commands;

use App\Services\CmsComponentLibraryAliasMapExactnessConvergenceService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GenerateComponentLibraryAliasMapExactnessConvergenceReport extends Command
{
    protected $signature = 'cms:component-library-alias-map-convergence
        {--source-key=* : Limit report to one or more source component keys}
        {--limit=100 : Maximum number of candidates to return after sorting}
        {--top=15 : Number of candidates to print in non-JSON mode}
        {--min-confidence=0 : Minimum confidence score (0-100) to include}
        {--json : Output JSON payload}
        {--export-json : Export full convergence report JSON under storage/app/cms/component-library-alias-map-convergence-exports}
        {--export-patch-preview : Export patch-preview JSON only (non-destructive exactness patch ops)}
        {--output= : Relative output file path under convergence export base dir}
        {--overwrite : Allow overwriting an existing export file}
        {--assert-min-ready=0 : Fail if ready_for_exact_patch_preview count is below this threshold}
        {--assert-max-blocked=-1 : Fail if blocked count exceeds this threshold (-1 disables)}';

    protected $description = 'Generate equivalent->exact convergence candidates (confidence + blockers) and non-destructive patch previews for the component-library alias map';

    private const EXPORT_BASE_DIRECTORY = 'app/cms/component-library-alias-map-convergence-exports';

    public function __construct(
        protected CmsComponentLibraryAliasMapExactnessConvergenceService $service
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $sourceKeys = array_values(array_filter(
            array_map(static fn ($value): string => is_string($value) ? trim($value) : (string) $value, (array) $this->option('source-key')),
            static fn (string $value): bool => $value !== ''
        ));

        $limit = $this->parseIntOption('limit', 100, 0);
        $top = $this->parseIntOption('top', 15, 0);
        $minConfidence = $this->parseIntOption('min-confidence', 0, 0);

        $payload = $this->service->analyze($sourceKeys === [] ? null : $sourceKeys, $limit, $minConfidence);

        $assertionErrors = $this->assertions($payload);

        $exports = [];
        try {
            if ((bool) $this->option('export-json')) {
                $path = $this->writeExport(
                    $this->resolveOutputName('convergence-report.json', (bool) $this->option('export-patch-preview')),
                    json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
                );
                $exports['report_json'] = $path;
            }

            if ((bool) $this->option('export-patch-preview')) {
                $patchPreview = (array) ($payload['patch_preview'] ?? []);
                $path = $this->writeExport(
                    $this->resolveOutputName('exactness-patch-preview.json', (bool) $this->option('export-json')),
                    json_encode($patchPreview, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
                );
                $exports['patch_preview_json'] = $path;
            }
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ((bool) $this->option('json')) {
            $jsonPayload = $payload;
            $jsonPayload['assertions'] = [
                'assert_min_ready' => $this->parseIntOption('assert-min-ready', 0, 0),
                'assert_max_blocked' => $this->parseIntOption('assert-max-blocked', -1, -1),
                'errors' => $assertionErrors,
                'ok' => $assertionErrors === [],
            ];
            $jsonPayload['exports'] = $exports;
            $this->line(json_encode($jsonPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        } else {
            $this->renderHumanSummary($payload, $top, $exports, $assertionErrors);
        }

        Log::info('cms.component_library_alias_map_convergence', [
            'filters' => $payload['filters'] ?? [],
            'summary' => $payload['summary'] ?? [],
            'registry' => $payload['canonical_registry_diagnostics'] ?? [],
            'exports' => $exports,
            'assertions_ok' => $assertionErrors === [],
            'assertion_errors' => $assertionErrors,
        ]);

        return $assertionErrors === [] ? self::SUCCESS : self::FAILURE;
    }

    private function parseIntOption(string $name, int $default, int $min): int
    {
        $raw = $this->option($name);
        $value = is_numeric($raw) ? (int) $raw : $default;

        return max($min, $value);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    private function assertions(array $payload): array
    {
        $errors = [];

        $statusBreakdown = is_array(data_get($payload, 'summary.status_breakdown'))
            ? (array) data_get($payload, 'summary.status_breakdown')
            : [];

        $ready = (int) ($statusBreakdown['ready_for_exact_patch_preview'] ?? 0);
        $blocked = (int) ($statusBreakdown['blocked'] ?? 0);

        $assertMinReady = $this->parseIntOption('assert-min-ready', 0, 0);
        $assertMaxBlocked = is_numeric($this->option('assert-max-blocked'))
            ? (int) $this->option('assert-max-blocked')
            : -1;

        if ($ready < $assertMinReady) {
            $errors[] = sprintf('ready_for_exact_patch_preview count %d is below assert-min-ready=%d', $ready, $assertMinReady);
        }

        if ($assertMaxBlocked >= 0 && $blocked > $assertMaxBlocked) {
            $errors[] = sprintf('blocked count %d exceeds assert-max-blocked=%d', $blocked, $assertMaxBlocked);
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, string>  $exports
     * @param  array<int, string>  $assertionErrors
     */
    private function renderHumanSummary(array $payload, int $top, array $exports, array $assertionErrors): void
    {
        $summary = is_array($payload['summary'] ?? null) ? (array) $payload['summary'] : [];
        $status = is_array($summary['status_breakdown'] ?? null) ? (array) ($summary['status_breakdown'] ?? []) : [];
        $registry = is_array($payload['canonical_registry_diagnostics'] ?? null)
            ? (array) ($payload['canonical_registry_diagnostics'] ?? [])
            : [];

        $this->info(sprintf(
            'Alias-map exactness convergence report (returned=%d, total_equivalent=%d, ready=%d, needs_review=%d, blocked=%d).',
            (int) ($summary['returned_candidates'] ?? 0),
            (int) ($summary['analyzed_equivalent_rows_total'] ?? 0),
            (int) ($status['ready_for_exact_patch_preview'] ?? 0),
            (int) ($status['needs_review'] ?? 0),
            (int) ($status['blocked'] ?? 0)
        ));

        $this->line(sprintf(
            'Canonical registry diagnostics: available=%s, matched=%d/%d, missing=%d, disabled=%d',
            ((bool) ($registry['available'] ?? false)) ? 'yes' : 'no',
            (int) ($registry['matched_registry_key_count'] ?? 0),
            (int) ($registry['requested_canonical_key_count'] ?? 0),
            (int) ($registry['missing_registry_key_count'] ?? 0),
            (int) ($registry['disabled_registry_key_count'] ?? 0)
        ));

        if (! (bool) ($registry['available'] ?? false) && is_string($registry['error'] ?? null)) {
            $this->line('Registry error: '.(string) $registry['error']);
        }

        $candidates = array_values(array_filter(
            (array) ($payload['candidates'] ?? []),
            static fn ($value): bool => is_array($value)
        ));

        if ($candidates !== []) {
            $this->newLine();
            $this->line('Top candidates:');

            foreach (array_slice($candidates, 0, $top) as $candidate) {
                $source = (string) ($candidate['source_component_key'] ?? '');
                $score = (int) ($candidate['confidence_score'] ?? 0);
                $statusLabel = (string) ($candidate['candidate_status'] ?? 'blocked');
                $blockers = implode(', ', array_map('strval', (array) ($candidate['blocking_reasons'] ?? [])));
                if ($blockers === '') {
                    $blockers = 'none';
                }

                $this->line(sprintf('- %s | %s | score=%d | blockers=%s', $source, $statusLabel, $score, $blockers));
            }
        }

        if ($exports !== []) {
            $this->newLine();
            foreach ($exports as $kind => $path) {
                $this->line(sprintf('Exported %s: %s', $kind, $path));
            }
        }

        if ($assertionErrors !== []) {
            $this->newLine();
            foreach ($assertionErrors as $error) {
                $this->error($error);
            }
        }
    }

    private function resolveOutputName(string $defaultName, bool $anotherExportAlsoRequested): string
    {
        $output = trim((string) ($this->option('output') ?? ''));
        if ($output === '') {
            return $defaultName;
        }

        if ($anotherExportAlsoRequested) {
            throw new RuntimeException('--output can only be used when exporting a single artifact (report JSON or patch preview JSON).');
        }

        return $output;
    }

    private function writeExport(string $relativePath, string|false $content): string
    {
        if (! is_string($content)) {
            throw new RuntimeException('JSON serialization failed while generating export.');
        }

        $relativePath = trim(str_replace('\\', '/', $relativePath));
        $relativePath = ltrim($relativePath, '/');

        if ($relativePath === '') {
            throw new RuntimeException('Export output path cannot be empty.');
        }

        if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            throw new RuntimeException('Export output path must be a safe relative path.');
        }

        $baseDirectory = storage_path(self::EXPORT_BASE_DIRECTORY);
        File::ensureDirectoryExists($baseDirectory);

        $absolutePath = $baseDirectory.'/'.$relativePath;
        $directory = dirname($absolutePath);
        File::ensureDirectoryExists($directory);

        if (File::exists($absolutePath) && ! (bool) $this->option('overwrite')) {
            throw new RuntimeException('Export file already exists. Re-run with --overwrite to replace it.');
        }

        File::put($absolutePath, $content);

        return $absolutePath;
    }
}
