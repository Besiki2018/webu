<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;

class CmsAiGenerationRolloutControlService
{
    public const FLAG_ROLLOUT_ENABLED = 'cms_ai_generation_rollout_enabled';

    public const FLAG_REQUIRE_VALIDATION = 'cms_ai_generation_require_validation_gate';

    public const FLAG_REQUIRE_RENDER = 'cms_ai_generation_require_render_smoke_gate';

    public const FLAG_REQUIRE_QUALITY = 'cms_ai_generation_require_quality_score_gate';

    public const FLAG_MIN_QUALITY_SCORE = 'cms_ai_generation_min_quality_score';

    public const FLAG_AUDIT_LOGGING_ENABLED = 'cms_ai_generation_audit_logging_enabled';

    public const AUDIT_ACTION = 'cms_ai_generation_rollout';

    /**
     * Read rollout feature flags with safe defaults (fail-closed rollout).
     *
     * @return array<string, mixed>
     */
    public function featureFlags(): array
    {
        return [
            'rollout_enabled' => (bool) SystemSetting::get(self::FLAG_ROLLOUT_ENABLED, false),
            'require_validation_gate' => (bool) SystemSetting::get(self::FLAG_REQUIRE_VALIDATION, true),
            'require_render_smoke_gate' => (bool) SystemSetting::get(self::FLAG_REQUIRE_RENDER, true),
            'require_quality_score_gate' => (bool) SystemSetting::get(self::FLAG_REQUIRE_QUALITY, true),
            'min_quality_score' => max(0, (int) SystemSetting::get(self::FLAG_MIN_QUALITY_SCORE, 70)),
            'audit_logging_enabled' => (bool) SystemSetting::get(self::FLAG_AUDIT_LOGGING_ENABLED, true),
        ];
    }

    /**
     * Evaluate rollout gating using validation/render/quality reports.
     *
     * @param  array<string, mixed>  $reports
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function evaluateForProject(Project $project, array $reports = [], array $options = []): array
    {
        $flags = $this->featureFlags();
        $flagOverrides = is_array($options['flag_overrides'] ?? null) ? $options['flag_overrides'] : [];
        if ($flagOverrides !== []) {
            $flags = array_merge($flags, $flagOverrides);
            $flags['min_quality_score'] = max(0, (int) ($flags['min_quality_score'] ?? 70));
        }

        $validation = is_array($reports['validation_report'] ?? null) ? $reports['validation_report'] : null;
        $render = is_array($reports['render_report'] ?? null) ? $reports['render_report'] : null;
        $quality = is_array($reports['quality_report'] ?? null) ? $reports['quality_report'] : null;

        $reasons = [];
        $blockingReasons = [];

        if (! (bool) ($flags['rollout_enabled'] ?? false)) {
            $blockingReasons[] = 'rollout_disabled';
        }

        if ((bool) ($flags['require_validation_gate'] ?? true)) {
            if ($validation === null) {
                $blockingReasons[] = 'missing_validation_report';
            } elseif (! (bool) ($validation['ok'] ?? false)) {
                $blockingReasons[] = 'validation_gate_failed';
            }
        }

        if ((bool) ($flags['require_render_smoke_gate'] ?? true)) {
            if ($render === null) {
                $blockingReasons[] = 'missing_render_report';
            } elseif (! (bool) ($render['ok'] ?? false)) {
                $blockingReasons[] = 'render_smoke_gate_failed';
            }
        }

        if ((bool) ($flags['require_quality_score_gate'] ?? true)) {
            if ($quality === null) {
                $blockingReasons[] = 'missing_quality_report';
            } else {
                $score = is_numeric($quality['score'] ?? null) ? (int) $quality['score'] : null;
                $eligible = data_get($quality, 'summary.eligible');
                $verdict = is_string($quality['verdict'] ?? null) ? (string) $quality['verdict'] : null;
                $minScore = (int) ($flags['min_quality_score'] ?? 0);

                if ($score === null) {
                    $blockingReasons[] = 'quality_score_missing';
                } elseif ($score < $minScore) {
                    $blockingReasons[] = 'quality_score_below_threshold';
                    $reasons[] = "quality_score={$score} < min_quality_score={$minScore}";
                }

                if ($eligible === false || $verdict === 'ineligible') {
                    $blockingReasons[] = 'quality_ineligible';
                }
            }
        }

        $allowed = $blockingReasons === [];
        $status = $allowed ? 'allowed' : 'denied';
        $siteId = $project->site()->value('id');

        return [
            'ok' => true,
            'decision' => [
                'status' => $status,
                'allowed' => $allowed,
                'blocking_reasons' => array_values(array_unique($blockingReasons)),
                'notes' => array_values(array_unique($reasons)),
                'project_id' => (string) $project->id,
                'site_id' => $siteId !== null ? (string) $siteId : null,
                'flags' => $flags,
                'gates' => [
                    'validation' => $this->compactGateReport($validation),
                    'render' => $this->compactGateReport($render),
                    'quality' => $this->compactQualityReport($quality),
                ],
            ],
        ];
    }

    /**
     * Evaluate and optionally write an audit log entry.
     *
     * @param  array<string, mixed>  $reports
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function evaluateAndAudit(Project $project, array $reports = [], ?User $actor = null, array $options = []): array
    {
        $evaluation = $this->evaluateForProject($project, $reports, $options);
        $decision = is_array($evaluation['decision'] ?? null) ? $evaluation['decision'] : [];
        $flags = is_array($decision['flags'] ?? null) ? $decision['flags'] : $this->featureFlags();

        $audit = [
            'logged' => false,
            'audit_log_id' => null,
        ];

        if ((bool) ($flags['audit_logging_enabled'] ?? true)) {
            $site = $project->site()->first();
            $log = AuditLog::log(
                action: self::AUDIT_ACTION,
                user: $project->user,
                actor: $actor,
                entityType: 'project',
                // audit_logs.entity_id is bigint; project UUID is preserved in metadata.project_id.
                entityId: null,
                oldValues: null,
                newValues: [
                    'status' => $decision['status'] ?? 'denied',
                    'allowed' => (bool) ($decision['allowed'] ?? false),
                ],
                metadata: [
                    'project_id' => (string) $project->id,
                    'site_id' => $site ? (string) $site->id : null,
                    'blocking_reasons' => array_values((array) ($decision['blocking_reasons'] ?? [])),
                    'notes' => array_values((array) ($decision['notes'] ?? [])),
                    'flags' => $flags,
                    'gates' => $decision['gates'] ?? [],
                    'request_id' => is_string(data_get($reports, 'ai_output.meta.request_id')) ? data_get($reports, 'ai_output.meta.request_id') : null,
                ],
            );

            $audit = [
                'logged' => true,
                'audit_log_id' => $log->id,
            ];
        }

        return [
            'ok' => true,
            'decision' => $decision,
            'audit' => $audit,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $report
     * @return array<string, mixed>|null
     */
    private function compactGateReport(?array $report): ?array
    {
        if ($report === null) {
            return null;
        }

        return [
            'ok' => (bool) ($report['ok'] ?? false),
            'summary' => is_array($report['summary'] ?? null) ? $report['summary'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $report
     * @return array<string, mixed>|null
     */
    private function compactQualityReport(?array $report): ?array
    {
        if ($report === null) {
            return null;
        }

        return [
            'ok' => (bool) ($report['ok'] ?? false),
            'score' => is_numeric($report['score'] ?? null) ? (int) $report['score'] : null,
            'verdict' => is_string($report['verdict'] ?? null) ? (string) $report['verdict'] : null,
            'eligible' => is_bool(data_get($report, 'summary.eligible')) ? (bool) data_get($report, 'summary.eligible') : null,
            'summary' => is_array($report['summary'] ?? null) ? $report['summary'] : null,
        ];
    }
}
