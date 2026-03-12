<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\CmsRuleLearningFromBuilderDeltasService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class LearnCmsRulesFromBuilderDeltas extends Command
{
    protected $signature = 'cms:learn-rules-from-builder-deltas
        {--since= : Lower bound timestamp/date for source deltas}
        {--until= : Upper bound timestamp/date for source deltas}
        {--site= : Optional site UUID scope}
        {--min-occurrences=2 : Minimum repeated occurrences to emit a candidate rule}';

    protected $description = 'Cluster common manual builder fixes from cms_builder_deltas into candidate learned rules';

    public function __construct(
        protected CmsRuleLearningFromBuilderDeltasService $service
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $site = null;
        $siteId = is_string($this->option('site')) ? trim((string) $this->option('site')) : '';
        if ($siteId !== '') {
            $site = Site::query()->whereKey($siteId)->first();
            if (! $site instanceof Site) {
                $this->error(sprintf('Site not found: %s', $siteId));

                return self::INVALID;
            }
        }

        $since = is_string($this->option('since')) ? trim((string) $this->option('since')) : '';
        $until = is_string($this->option('until')) ? trim((string) $this->option('until')) : '';
        $minOccurrencesOption = $this->option('min-occurrences');
        $minOccurrences = is_numeric($minOccurrencesOption) ? (int) $minOccurrencesOption : 2;

        $result = $this->service->learnCandidateRules(
            $since !== '' ? $since : null,
            $until !== '' ? $until : null,
            $site,
            $minOccurrences
        );

        $message = sprintf(
            'Learned CMS rule candidates from builder deltas (source_deltas=%d, eligible_ops=%d, clusters=%d, upserted=%d, min_occurrences=%d).',
            (int) ($result['source_deltas'] ?? 0),
            (int) ($result['eligible_ops'] ?? 0),
            (int) ($result['qualifying_clusters'] ?? $result['clusters'] ?? 0),
            (int) ($result['upserted'] ?? 0),
            (int) ($result['min_occurrences'] ?? $minOccurrences)
        );

        $this->info($message);

        Log::info('cms.learning.cluster_builder_deltas', [
            'site_id' => $site?->id ? (string) $site->id : null,
            'project_id' => $site?->project_id ? (string) $site->project_id : null,
            'since' => $result['since'] ?? null,
            'until' => $result['until'] ?? null,
            'source_deltas' => (int) ($result['source_deltas'] ?? 0),
            'eligible_ops' => (int) ($result['eligible_ops'] ?? 0),
            'clusters' => (int) ($result['clusters'] ?? 0),
            'qualifying_clusters' => (int) ($result['qualifying_clusters'] ?? 0),
            'upserted' => (int) ($result['upserted'] ?? 0),
            'min_occurrences' => (int) ($result['min_occurrences'] ?? $minOccurrences),
        ]);

        return self::SUCCESS;
    }
}
