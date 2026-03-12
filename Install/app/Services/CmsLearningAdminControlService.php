<?php

namespace App\Services;

use App\Models\CmsExperiment;
use App\Models\CmsExperimentAssignment;
use App\Models\CmsExperimentVariant;
use App\Models\CmsLearnedRule;
use App\Models\Site;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;

class CmsLearningAdminControlService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listLearnedRules(Site $site, array $filters = []): array
    {
        $query = CmsLearnedRule::query()
            ->where('site_id', (string) $site->id)
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $status = $this->safeString($filters['status'] ?? null, 20);
        if ($status !== '') {
            $query->where('status', $status);
        }

        if (array_key_exists('active', $filters)) {
            $active = $this->toBoolean($filters['active']);
            if ($active !== null) {
                $query->where('active', $active);
            }
        }

        $componentType = $this->safeString($filters['component_type'] ?? null, 120);
        if ($componentType !== '') {
            $query->where('conditions_json->component_type', $componentType);
        }

        $limit = max(1, min(200, $this->toInt($filters['limit'] ?? null) ?? 50));

        $rows = $query->limit($limit)->get()->map(fn (CmsLearnedRule $rule): array => $this->serializeLearnedRuleSummary($rule))->values()->all();

        return [
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'filters' => [
                'status' => $status !== '' ? $status : null,
                'active' => $this->toBoolean($filters['active'] ?? null),
                'component_type' => $componentType !== '' ? $componentType : null,
                'limit' => $limit,
            ],
            'summary' => [
                'total' => count($rows),
                'candidate' => count(array_filter($rows, fn (array $r): bool => ($r['status'] ?? null) === 'candidate')),
                'active' => count(array_filter($rows, fn (array $r): bool => (bool) ($r['active'] ?? false))),
                'rolled_back' => count(array_filter($rows, fn (array $r): bool => ($r['status'] ?? null) === 'rolled_back')),
            ],
            'rules' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showLearnedRule(Site $site, CmsLearnedRule $rule): array
    {
        $this->assertBelongsToSite($site, $rule);

        return [
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'rule' => $this->serializeLearnedRuleDetail($rule),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function disableLearnedRule(Site $site, CmsLearnedRule $rule, array $context = []): array
    {
        $this->assertBelongsToSite($site, $rule);

        $clock = $this->normalizeTime($context['now'] ?? null) ?? now();
        $reason = $this->safeString($context['reason'] ?? null, 500);
        $actorId = $this->toInt($context['actor_id'] ?? null);
        $wasActive = (bool) $rule->active;

        $evidence = is_array($rule->evidence_json) ? $rule->evidence_json : [];
        data_set($evidence, 'admin_control.last_disable', [
            'at' => $clock->toISOString(),
            'actor_id' => $actorId,
            'reason' => $reason !== '' ? $reason : null,
            'previous_status' => (string) $rule->status,
            'previous_active' => $wasActive,
        ]);
        $history = is_array(data_get($evidence, 'admin_control.disable_history')) ? data_get($evidence, 'admin_control.disable_history') : [];
        $history[] = data_get($evidence, 'admin_control.last_disable');
        data_set($evidence, 'admin_control.disable_history', array_slice($history, -20));

        $rule->status = 'disabled';
        $rule->active = false;
        $rule->disabled_at = $clock;
        $rule->evidence_json = $evidence;
        $rule->save();

        return [
            'ok' => true,
            'message' => 'Learned rule disabled.',
            'rule' => $this->serializeLearnedRuleDetail($rule),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    public function listExperiments(Site $site, array $filters = []): array
    {
        $query = CmsExperiment::query()
            ->where('site_id', (string) $site->id)
            ->withCount([
                'variants',
                'variants as active_variants_count' => fn (Builder $q) => $q->where('status', 'active'),
                'assignments',
            ])
            ->orderByDesc('updated_at')
            ->orderByDesc('id');

        $status = $this->safeString($filters['status'] ?? null, 20);
        if ($status !== '') {
            $query->where('status', $status);
        }

        $limit = max(1, min(200, $this->toInt($filters['limit'] ?? null) ?? 50));

        $rows = $query->limit($limit)->get()->map(fn (CmsExperiment $experiment): array => $this->serializeExperimentSummary($experiment))->values()->all();

        return [
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'filters' => [
                'status' => $status !== '' ? $status : null,
                'limit' => $limit,
            ],
            'summary' => [
                'total' => count($rows),
                'active' => count(array_filter($rows, fn (array $r): bool => ($r['status'] ?? null) === 'active')),
                'paused' => count(array_filter($rows, fn (array $r): bool => ($r['status'] ?? null) === 'paused')),
                'draft' => count(array_filter($rows, fn (array $r): bool => ($r['status'] ?? null) === 'draft')),
            ],
            'experiments' => $rows,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function showExperiment(Site $site, CmsExperiment $experiment): array
    {
        $this->assertBelongsToSite($site, $experiment);

        $experiment->loadMissing(['variants' => fn ($q) => $q->orderBy('sort_order')->orderBy('id')]);

        $assignmentCounts = CmsExperimentAssignment::query()
            ->selectRaw('variant_key, COUNT(*) as aggregate_count')
            ->where('experiment_id', $experiment->id)
            ->groupBy('variant_key')
            ->pluck('aggregate_count', 'variant_key')
            ->map(fn ($count): int => (int) $count)
            ->all();

        return [
            'site_id' => (string) $site->id,
            'project_id' => (string) $site->project_id,
            'experiment' => $this->serializeExperimentDetail($experiment, $assignmentCounts),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function disableExperiment(Site $site, CmsExperiment $experiment, array $context = []): array
    {
        $this->assertBelongsToSite($site, $experiment);

        $clock = $this->normalizeTime($context['now'] ?? null) ?? now();
        $reason = $this->safeString($context['reason'] ?? null, 500);
        $actorId = $this->toInt($context['actor_id'] ?? null);

        $meta = is_array($experiment->meta_json) ? $experiment->meta_json : [];
        data_set($meta, 'admin_control.last_disable', [
            'at' => $clock->toISOString(),
            'actor_id' => $actorId,
            'reason' => $reason !== '' ? $reason : null,
            'previous_status' => (string) $experiment->status,
        ]);
        $history = is_array(data_get($meta, 'admin_control.disable_history')) ? data_get($meta, 'admin_control.disable_history') : [];
        $history[] = data_get($meta, 'admin_control.last_disable');
        data_set($meta, 'admin_control.disable_history', array_slice($history, -20));

        $experiment->status = 'paused';
        $experiment->ends_at = $experiment->ends_at instanceof Carbon
            ? ($experiment->ends_at->lessThan($clock) ? $experiment->ends_at : $clock)
            : $clock;
        $experiment->meta_json = $meta;
        $experiment->save();

        $fresh = $experiment->fresh();
        if ($fresh instanceof CmsExperiment) {
            $fresh->loadCount([
                'variants',
                'variants as active_variants_count' => fn (Builder $q) => $q->where('status', 'active'),
                'assignments',
            ]);
        }

        return [
            'ok' => true,
            'message' => 'Experiment disabled (paused).',
            'experiment' => $this->serializeExperimentSummary($fresh instanceof CmsExperiment ? $fresh : $experiment),
        ];
    }

    private function assertBelongsToSite(Site $site, object $resource): void
    {
        $resourceSiteId = '';
        if ($resource instanceof Model) {
            $attributes = $resource->getAttributes();
            if (array_key_exists('site_id', $attributes)) {
                $resourceSiteId = (string) ($attributes['site_id'] ?? '');
            }
        }

        if ($resourceSiteId === '' || $resourceSiteId !== (string) $site->id) {
            throw (new ModelNotFoundException())->setModel($resource::class);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLearnedRuleSummary(CmsLearnedRule $rule): array
    {
        return [
            'id' => $rule->id,
            'rule_key' => $rule->rule_key,
            'scope' => $rule->scope,
            'status' => $rule->status,
            'active' => (bool) $rule->active,
            'source' => $rule->source,
            'component_type' => data_get($rule->conditions_json, 'component_type'),
            'store_type' => data_get($rule->conditions_json, 'store_type'),
            'prompt_intent_tags' => data_get($rule->conditions_json, 'prompt_intent_tags', []),
            'sample_size' => (int) $rule->sample_size,
            'delta_count' => (int) $rule->delta_count,
            'confidence' => $rule->confidence !== null ? (float) $rule->confidence : null,
            'promoted_at' => $rule->promoted_at?->toISOString(),
            'disabled_at' => $rule->disabled_at?->toISOString(),
            'last_metric_evaluation' => data_get($rule->evidence_json, 'metric_evaluation.last_result'),
            'updated_at' => $rule->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeLearnedRuleDetail(CmsLearnedRule $rule): array
    {
        return array_merge($this->serializeLearnedRuleSummary($rule), [
            'project_id' => $rule->project_id,
            'site_id' => $rule->site_id,
            'conditions_json' => $rule->conditions_json,
            'patch_json' => $rule->patch_json,
            'evidence_json' => $rule->evidence_json,
            'last_learned_at' => $rule->last_learned_at?->toISOString(),
            'created_at' => $rule->created_at?->toISOString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeExperimentSummary(CmsExperiment $experiment): array
    {
        return [
            'id' => $experiment->id,
            'key' => $experiment->key,
            'name' => $experiment->name,
            'status' => $experiment->status,
            'assignment_unit' => $experiment->assignment_unit,
            'traffic_percent' => (int) $experiment->traffic_percent,
            'starts_at' => $experiment->starts_at?->toISOString(),
            'ends_at' => $experiment->ends_at?->toISOString(),
            'variants_count' => (int) ($experiment->variants_count ?? 0),
            'active_variants_count' => (int) ($experiment->active_variants_count ?? 0),
            'assignments_count' => (int) ($experiment->assignments_count ?? 0),
            'updated_at' => $experiment->updated_at?->toISOString(),
            'admin_control' => data_get($experiment->meta_json, 'admin_control.last_disable'),
        ];
    }

    /**
     * @param  array<string, int>  $assignmentCounts
     * @return array<string, mixed>
     */
    private function serializeExperimentDetail(CmsExperiment $experiment, array $assignmentCounts = []): array
    {
        $summary = $this->serializeExperimentSummary($experiment);

        $variants = $experiment->variants
            ->map(function (CmsExperimentVariant $variant) use ($assignmentCounts): array {
                return [
                    'id' => $variant->id,
                    'variant_key' => $variant->variant_key,
                    'status' => $variant->status,
                    'weight' => (int) $variant->weight,
                    'sort_order' => (int) $variant->sort_order,
                    'assignment_count' => (int) ($assignmentCounts[$variant->variant_key] ?? 0),
                    'payload_json' => $variant->payload_json,
                    'meta_json' => $variant->meta_json,
                ];
            })
            ->values()
            ->all();

        return array_merge($summary, [
            'project_id' => $experiment->project_id,
            'site_id' => $experiment->site_id,
            'targeting_json' => $experiment->targeting_json,
            'meta_json' => $experiment->meta_json,
            'variants' => $variants,
        ]);
    }

    private function normalizeTime(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy();
        }

        if (is_string($value) && trim($value) !== '') {
            try {
                return Carbon::parse($value);
            } catch (\Throwable) {
                return null;
            }
        }

        return null;
    }

    private function toInt(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }
        if (is_string($value) && ctype_digit($value)) {
            return (int) $value;
        }
        if (is_numeric($value)) {
            return (int) $value;
        }

        return null;
    }

    private function toBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            return match ($normalized) {
                '1', 'true', 'yes', 'on' => true,
                '0', 'false', 'no', 'off' => false,
                default => null,
            };
        }
        if (is_int($value)) {
            return match ($value) {
                1 => true,
                0 => false,
                default => null,
            };
        }

        return null;
    }

    private function safeString(mixed $value, int $max): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        $normalized = trim((string) $value);
        if ($normalized === '') {
            return '';
        }

        return mb_substr($normalized, 0, max(1, $max));
    }
}
