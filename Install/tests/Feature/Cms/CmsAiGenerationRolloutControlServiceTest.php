<?php

namespace Tests\Feature\Cms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @group docs-sync */
class CmsAiGenerationRolloutControlServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
        $this->seedDefaultFlags();
    }

    public function test_it_allows_rollout_when_feature_flags_are_enabled_and_all_gates_pass_and_writes_audit_log(): void
    {
        $owner = User::factory()->create();
        $actor = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $service = app(CmsAiGenerationRolloutControlService::class);
        $result = $service->evaluateAndAudit($project, [
            'validation_report' => ['ok' => true, 'summary' => ['gate_passed' => true]],
            'render_report' => ['ok' => true, 'summary' => ['gate_passed' => true]],
            'quality_report' => ['ok' => true, 'score' => 82, 'verdict' => 'good', 'summary' => ['eligible' => true]],
            'ai_output' => ['meta' => ['request_id' => 'req-rollout-1']],
        ], $actor);

        $this->assertTrue($result['ok']);
        $this->assertTrue((bool) data_get($result, 'decision.allowed'));
        $this->assertSame('allowed', data_get($result, 'decision.status'));
        $this->assertSame([], data_get($result, 'decision.blocking_reasons'));
        $this->assertTrue((bool) data_get($result, 'audit.logged'));
        $this->assertNotNull(data_get($result, 'audit.audit_log_id'));

        $this->assertDatabaseHas('audit_logs', [
            'id' => data_get($result, 'audit.audit_log_id'),
            'action' => CmsAiGenerationRolloutControlService::AUDIT_ACTION,
            'entity_type' => 'project',
            'actor_id' => $actor->id,
        ]);

        $log = AuditLog::query()->findOrFail((int) data_get($result, 'audit.audit_log_id'));
        $this->assertNull($log->entity_id);
        $this->assertSame('allowed', data_get($log->new_values, 'status'));
        $this->assertSame([], data_get($log->metadata, 'blocking_reasons'));
        $this->assertSame((string) $project->id, data_get($log->metadata, 'project_id'));
        $this->assertSame('req-rollout-1', data_get($log->metadata, 'request_id'));
        $this->assertSame(82, data_get($log->metadata, 'gates.quality.score'));
        $this->assertSame('good', data_get($log->metadata, 'gates.quality.verdict'));
    }

    public function test_it_denies_rollout_when_required_gates_fail_and_records_blocking_reasons(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        SystemSetting::set(CmsAiGenerationRolloutControlService::FLAG_MIN_QUALITY_SCORE, 80, 'integer', 'cms_ai_generation');

        $service = app(CmsAiGenerationRolloutControlService::class);
        $result = $service->evaluateAndAudit($project, [
            'validation_report' => ['ok' => false, 'summary' => ['gate_passed' => false]],
            'render_report' => ['ok' => false, 'summary' => ['gate_passed' => false]],
            'quality_report' => ['ok' => true, 'score' => 61, 'verdict' => 'fair', 'summary' => ['eligible' => true]],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertFalse((bool) data_get($result, 'decision.allowed'));
        $this->assertSame('denied', data_get($result, 'decision.status'));

        $blocking = data_get($result, 'decision.blocking_reasons', []);
        $this->assertContains('validation_gate_failed', $blocking);
        $this->assertContains('render_smoke_gate_failed', $blocking);
        $this->assertContains('quality_score_below_threshold', $blocking);

        $notes = implode(' || ', data_get($result, 'decision.notes', []));
        $this->assertStringContainsString('quality_score=61 < min_quality_score=80', $notes);

        $log = AuditLog::query()->findOrFail((int) data_get($result, 'audit.audit_log_id'));
        $this->assertSame('denied', data_get($log->new_values, 'status'));
        $this->assertContains('validation_gate_failed', data_get($log->metadata, 'blocking_reasons', []));
        $this->assertSame(80, data_get($log->metadata, 'flags.min_quality_score'));
    }

    public function test_it_skips_audit_logging_when_audit_flag_is_disabled(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        SystemSetting::set(CmsAiGenerationRolloutControlService::FLAG_AUDIT_LOGGING_ENABLED, false, 'boolean', 'cms_ai_generation');

        $service = app(CmsAiGenerationRolloutControlService::class);
        $result = $service->evaluateAndAudit($project, [
            'validation_report' => ['ok' => true],
            'render_report' => ['ok' => true],
            'quality_report' => ['ok' => true, 'score' => 90, 'verdict' => 'excellent', 'summary' => ['eligible' => true]],
        ]);

        $this->assertTrue($result['ok']);
        $this->assertTrue((bool) data_get($result, 'decision.allowed'));
        $this->assertFalse((bool) data_get($result, 'audit.logged'));
        $this->assertNull(data_get($result, 'audit.audit_log_id'));
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_architecture_doc_documents_rollout_feature_flags_and_audit_logging_contract(): void
    {
        $path = base_path('docs/architecture/CMS_AI_GENERATION_ROLLOUT_CONTROL_V1.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('# CMS AI Generation Rollout Control v1', $doc);
        $this->assertStringContainsString('P4-E3-04', $doc);
        $this->assertStringContainsString('CmsAiGenerationRolloutControlService', $doc);
        $this->assertStringContainsString('SystemSetting', $doc);
        $this->assertStringContainsString('AuditLog', $doc);
        $this->assertStringContainsString('validation_report', $doc);
        $this->assertStringContainsString('render_report', $doc);
        $this->assertStringContainsString('quality_report', $doc);
        $this->assertStringContainsString('fail-closed', strtolower($doc));
    }

    private function seedDefaultFlags(): void
    {
        SystemSetting::set(CmsAiGenerationRolloutControlService::FLAG_ROLLOUT_ENABLED, true, 'boolean', 'cms_ai_generation');
        SystemSetting::set(CmsAiGenerationRolloutControlService::FLAG_REQUIRE_VALIDATION, true, 'boolean', 'cms_ai_generation');
        SystemSetting::set(CmsAiGenerationRolloutControlService::FLAG_REQUIRE_RENDER, true, 'boolean', 'cms_ai_generation');
        SystemSetting::set(CmsAiGenerationRolloutControlService::FLAG_REQUIRE_QUALITY, true, 'boolean', 'cms_ai_generation');
        SystemSetting::set(CmsAiGenerationRolloutControlService::FLAG_MIN_QUALITY_SCORE, 70, 'integer', 'cms_ai_generation');
        SystemSetting::set(CmsAiGenerationRolloutControlService::FLAG_AUDIT_LOGGING_ENABLED, true, 'boolean', 'cms_ai_generation');
    }
}
