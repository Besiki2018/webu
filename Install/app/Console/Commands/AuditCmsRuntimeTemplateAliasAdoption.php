<?php

namespace App\Console\Commands;

use App\Services\CmsRuntimeTemplateAliasAdoptionAuditService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class AuditCmsRuntimeTemplateAliasAdoption extends Command
{
    protected $signature = 'cms:runtime-alias-adoption-audit
        {--root=* : Optional root directory override(s) for HTML artifact scan}
        {--top=10 : Number of top markers to include in output}
        {--json : Output JSON payload}
        {--assert-min-markers=0 : Fail if total marker count is below this threshold}
        {--assert-max-unknown=0 : Fail if unknown/unclassified marker count exceeds this threshold}';

    protected $description = 'Audit runtime/template HTML marker adoption (canonical alias-map keys vs legacy/adapted markers) for preview/published outputs';

    public function __construct(
        protected CmsRuntimeTemplateAliasAdoptionAuditService $service
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $roots = array_values(array_filter(
            array_map(fn ($value) => is_string($value) ? trim($value) : '', (array) $this->option('root')),
            static fn (string $value): bool => $value !== ''
        ));

        $top = (int) (is_numeric($this->option('top')) ? $this->option('top') : 10);
        $report = $this->service->audit($roots === [] ? null : $roots, null, $top);

        $errors = [];
        $minMarkers = (int) (is_numeric($this->option('assert-min-markers')) ? $this->option('assert-min-markers') : 0);
        $maxUnknown = (int) (is_numeric($this->option('assert-max-unknown')) ? $this->option('assert-max-unknown') : 0);

        $totalMarkers = (int) data_get($report, 'totals.markers', 0);
        $unknownCount = (int) data_get($report, 'categories.other.count', 0);

        if ($totalMarkers < $minMarkers) {
            $errors[] = sprintf('total markers %d is below assert-min-markers=%d', $totalMarkers, $minMarkers);
        }

        if ($unknownCount > $maxUnknown) {
            $errors[] = sprintf('unknown marker count %d exceeds assert-max-unknown=%d', $unknownCount, $maxUnknown);
        }

        if ((bool) $this->option('json')) {
            $payload = $report;
            $payload['assertions'] = [
                'assert_min_markers' => $minMarkers,
                'assert_max_unknown' => $maxUnknown,
                'errors' => $errors,
                'ok' => $errors === [],
            ];
            $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');
        } else {
            $this->info(sprintf(
                'Runtime alias adoption audit (markers=%d, unique=%d, unknown=%d, html_files=%d).',
                $totalMarkers,
                (int) data_get($report, 'totals.unique_markers', 0),
                $unknownCount,
                (int) ($report['scanned_html_files'] ?? 0)
            ));

            /** @var array<string, array{count:int,percent:float}> $categories */
            $categories = is_array($report['categories'] ?? null) ? $report['categories'] : [];
            foreach ($categories as $category => $row) {
                $this->line(sprintf(
                    '- %s: %d (%.2f%%)',
                    $category,
                    (int) ($row['count'] ?? 0),
                    (float) ($row['percent'] ?? 0.0)
                ));
            }

            if ($errors !== []) {
                $this->newLine();
                foreach ($errors as $error) {
                    $this->error($error);
                }
            }
        }

        Log::info('cms.runtime_alias_adoption_audit', [
            'roots' => $report['roots'] ?? [],
            'markers' => $totalMarkers,
            'unique_markers' => (int) data_get($report, 'totals.unique_markers', 0),
            'unknown_markers' => $unknownCount,
            'html_files' => (int) ($report['scanned_html_files'] ?? 0),
            'assertions_ok' => $errors === [],
            'assertion_errors' => $errors,
        ]);

        return $errors === [] ? self::SUCCESS : self::FAILURE;
    }
}
