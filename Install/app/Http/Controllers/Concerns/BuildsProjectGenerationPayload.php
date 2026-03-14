<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Project;
use App\Models\ProjectGenerationRun;
use App\Services\ProjectGenerationRecoveryService;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;

trait BuildsProjectGenerationPayload
{
    protected function resolveGenerationSourceType(
        Project $project,
        ?ProjectGenerationRun $run
    ): string {
        $requestedTemplateId = data_get($run?->requested_input, 'template_id');
        $hasTemplateSeed = (
            (is_scalar($requestedTemplateId) && trim((string) $requestedTemplateId) !== '')
            || $project->template_id !== null
        );

        return $hasTemplateSeed ? 'template' : 'new';
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function buildGenerationPayload(
        ?ProjectGenerationRun $run,
        Project $project,
        ProjectWorkspaceService $projectWorkspace
    ): ?array {
        $run = app(ProjectGenerationRecoveryService::class)->recoverStaleRun($run);

        if (! $run) {
            return null;
        }

        $workspaceManifest = $projectWorkspace->getWorkspaceManifestSummary($project, $run->status);

        return [
            'id' => (string) $run->id,
            'status' => $run->status,
            'is_active' => $run->isActive(),
            'ready_for_builder' => (bool) ($workspaceManifest['ready_for_builder'] ?? false),
            'project_generation_version' => data_get($workspaceManifest, 'active_generation_run_id')
                ?: (string) $run->id,
            'source_generation_type' => $this->resolveGenerationSourceType($project, $run),
            'workspace_manifest_exists' => (bool) ($workspaceManifest['exists'] ?? false),
            'workspace_preview_ready' => (bool) data_get($workspaceManifest, 'preview.ready', false),
            'workspace_preview_phase' => (string) data_get($workspaceManifest, 'preview.phase', 'idle'),
            'active_generation_run_id' => data_get($workspaceManifest, 'active_generation_run_id'),
            'preview_build_id' => data_get($workspaceManifest, 'preview.build_id'),
            'preview_url' => data_get($workspaceManifest, 'preview.preview_url'),
            'progress_message' => $run->progress_message,
            'error_message' => $run->error_message,
            'started_at' => $run->started_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'failed_at' => $run->failed_at?->toIso8601String(),
            'status_url' => route('project.generation.status', $project),
        ];
    }

    /**
     * Builder stays locked until the workspace manifest is explicitly marked ready.
     *
     * @param  array<string, mixed>|null  $generationPayload
     */
    protected function generationRequiresCompletionGate(?array $generationPayload): bool
    {
        return $generationPayload !== null
            && (bool) ($generationPayload['ready_for_builder'] ?? false) !== true;
    }
}
