<?php

namespace App\Console\Commands;

use App\Models\Site;
use App\Services\CmsLearnedRuleMetricPromotionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class EvaluateCmsLearnedRuleMetrics extends Command
{
    protected $signature = 'cms:learned-rules-evaluate-metrics
        {--site= : Optional site UUID scope}
        {--now= : Evaluation clock override (ISO datetime)}
        {--min-before-samples=100 : Minimum samples required in the before window}
        {--min-after-samples=100 : Minimum samples required in the after window}
        {--promote-uplift=0.02 : Minimum positive uplift for candidate rule promotion}
        {--rollback-drop=0.02 : Minimum drop threshold (absolute) to rollback active rules}
        {--before-days=14 : Days to include in before window}
        {--after-days=14 : Days to include in after window}';

    protected $description = 'Evaluate learned rules against metric thresholds and apply deterministic promotion/rollback decisions';

    public function __construct(
        protected CmsLearnedRuleMetricPromotionService $service
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

        $result = $this->service->evaluateRules(
            $site,
            is_string($this->option('now')) ? trim((string) $this->option('now')) : null,
            [
                'min_before_samples' => $this->option('min-before-samples'),
                'min_after_samples' => $this->option('min-after-samples'),
                'min_promotion_uplift' => $this->option('promote-uplift'),
                'rollback_drop_threshold' => $this->option('rollback-drop'),
                'before_days' => $this->option('before-days'),
                'after_days' => $this->option('after-days'),
            ]
        );

        $message = sprintf(
            'Evaluated learned rule metric thresholds (evaluated=%d, promoted=%d, rolled_back=%d, unchanged=%d, skipped=%d).',
            (int) ($result['evaluated_rules'] ?? 0),
            (int) ($result['promoted'] ?? 0),
            (int) ($result['rolled_back'] ?? 0),
            (int) ($result['unchanged'] ?? 0),
            (int) ($result['skipped'] ?? 0)
        );

        $this->info($message);

        Log::info('cms.learning.rule_metric_thresholds_evaluated', [
            'site_id' => $site?->id ? (string) $site->id : null,
            'project_id' => $site?->project_id ? (string) $site->project_id : null,
            'evaluated_at' => $result['evaluated_at'] ?? null,
            'evaluated_rules' => (int) ($result['evaluated_rules'] ?? 0),
            'promoted' => (int) ($result['promoted'] ?? 0),
            'rolled_back' => (int) ($result['rolled_back'] ?? 0),
            'unchanged' => (int) ($result['unchanged'] ?? 0),
            'skipped' => (int) ($result['skipped'] ?? 0),
            'thresholds' => $result['thresholds'] ?? [],
        ]);

        return self::SUCCESS;
    }
}
