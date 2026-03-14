<?php

namespace App\Services;

use App\Models\ProjectGenerationRun;
use App\Services\AiWebsiteGeneration\GenerateWebsiteProjectService;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use Illuminate\Support\Facades\Log;

class ProjectGenerationRunner
{
    public function __construct(
        protected GenerateWebsiteProjectService $generationService,
        protected SiteProvisioningService $siteProvisioning,
        protected ProjectWorkspaceService $projectWorkspace,
    ) {}

    public function run(ProjectGenerationRun $run): void
    {
        $run = $run->fresh(['project']);
        if (! $run || ! $run->project) {
            return;
        }

        if ($run->isReady()) {
            return;
        }

        $project = $run->project;
        $this->registerFatalFailureFallback($run);

        $this->markStarted($run, ProjectGenerationRun::STATUS_ANALYZING_PROMPT, 'Analyzing your prompt.');
        $this->projectWorkspace->syncInitialGenerationState($project, [
            'active_generation_run_id' => (string) $run->id,
            'phase' => ProjectGenerationRun::STATUS_ANALYZING_PROMPT,
        ]);

        try {
            $result = $this->generationService->generateIntoProject($project, [
                'userPrompt' => $run->requested_prompt,
                'language' => $run->requested_language,
                'style' => $run->requested_style,
                'websiteType' => $run->requested_website_type,
                'generationRunId' => (string) $run->id,
                'user_id' => $run->user_id ?: $project->user_id,
                'ultra_cheap_mode' => (bool) data_get($run->requested_input, 'ultra_cheap_mode', true),
            ], function (string $status, ?string $message = null) use ($run, $project): void {
                $this->updateProgress($run->fresh() ?? $run, $status, $message);
                $this->projectWorkspace->syncInitialGenerationState($project, [
                    'active_generation_run_id' => (string) $run->id,
                    'phase' => $status,
                ]);
            });

            $this->siteProvisioning->provisionForProject($project->fresh());
            $this->projectWorkspace->syncInitialGenerationState($project, [
                'phase' => ProjectGenerationRun::STATUS_READY,
            ]);

            $run->forceFill([
                'status' => ProjectGenerationRun::STATUS_READY,
                'progress_message' => 'Website ready.',
                'error_message' => null,
                'completed_at' => now(),
                'failed_at' => null,
                'result_payload' => [
                    'project_id' => (string) $project->id,
                    'site_id' => (string) ($result['site']->id ?? ''),
                    'website_id' => (string) ($result['website']->id ?? ''),
                    'builder_generation' => is_array($result['builder_generation'] ?? null)
                        ? $result['builder_generation']
                        : null,
                ],
            ])->save();
        } catch (\Throwable $e) {
            Log::warning('project_generation.failed', [
                'project_id' => $project->id,
                'generation_run_id' => $run->id,
                'message' => $e->getMessage(),
            ]);

            $run->forceFill([
                'status' => ProjectGenerationRun::STATUS_FAILED,
                'progress_message' => null,
                'error_message' => $e->getMessage(),
                'failed_at' => now(),
                'completed_at' => null,
            ])->save();
            $this->projectWorkspace->syncInitialGenerationState($project, [
                'phase' => ProjectGenerationRun::STATUS_FAILED,
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    private function markStarted(ProjectGenerationRun $run, string $status, ?string $message = null): void
    {
        $run->forceFill([
            'status' => $status,
            'progress_message' => $message,
            'started_at' => $run->started_at ?? now(),
            'failed_at' => null,
            'completed_at' => null,
            'error_message' => null,
        ])->save();
    }

    private function updateProgress(ProjectGenerationRun $run, string $status, ?string $message = null): void
    {
        if (! $run->exists || $run->isReady()) {
            return;
        }

        $run->forceFill([
            'status' => $status,
            'progress_message' => $message,
            'started_at' => $run->started_at ?? now(),
            'error_message' => null,
            'failed_at' => null,
        ])->save();
    }

    private function registerFatalFailureFallback(ProjectGenerationRun $run): void
    {
        $runId = (string) $run->id;

        register_shutdown_function(function () use ($runId): void {
            $error = error_get_last();
            if (! is_array($error) || ! in_array((int) ($error['type'] ?? 0), [
                E_ERROR,
                E_PARSE,
                E_CORE_ERROR,
                E_COMPILE_ERROR,
                E_USER_ERROR,
                E_RECOVERABLE_ERROR,
            ], true)) {
                return;
            }

            $failedRun = ProjectGenerationRun::query()
                ->with('project')
                ->find($runId);

            if (! $failedRun || ! $failedRun->isActive()) {
                return;
            }

            $message = trim((string) ($error['message'] ?? 'Project generation terminated unexpectedly.'));
            if ($message === '') {
                $message = 'Project generation terminated unexpectedly.';
            }

            $failedRun->forceFill([
                'status' => ProjectGenerationRun::STATUS_FAILED,
                'progress_message' => null,
                'error_message' => $message,
                'failed_at' => now(),
                'completed_at' => null,
            ])->save();

            if ($failedRun->project) {
                app(ProjectWorkspaceService::class)->syncInitialGenerationState($failedRun->project, [
                    'phase' => ProjectGenerationRun::STATUS_FAILED,
                    'error_message' => $message,
                ]);
            }

            Log::error('project_generation.fatal_shutdown', [
                'project_id' => $failedRun->project_id,
                'generation_run_id' => $failedRun->id,
                'message' => $message,
                'error' => $error,
            ]);
        });
    }
}
