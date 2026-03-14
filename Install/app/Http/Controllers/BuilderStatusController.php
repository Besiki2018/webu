<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\BuilderService;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only builder status endpoint (Task 6: separate from mutation proxy).
 * Uses throttle:builder-status; mutations use BuilderProxyController + throttle:builder-operations.
 */
class BuilderStatusController extends Controller
{
    public function __construct(
        protected BuilderService $builderService,
        protected ProjectWorkspaceService $projectWorkspace
    ) {}

    /**
     * Get build status (quick = DB only; otherwise includes builder service status).
     */
    public function getStatus(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $project->loadMissing('latestGenerationRun');
        $latestGenerationRun = $project->latestGenerationRun;
        $workspaceManifest = $latestGenerationRun
            ? $this->projectWorkspace->getWorkspaceManifestSummary($project, $latestGenerationRun->status)
            : null;
        $previewBuildId = data_get($workspaceManifest, 'preview.build_id');
        $projectGenerationVersion = data_get($workspaceManifest, 'active_generation_run_id')
            ?: ($latestGenerationRun ? (string) $latestGenerationRun->id : null);
        $sourceGenerationType = (
            (is_scalar(data_get($latestGenerationRun?->requested_input, 'template_id'))
                && trim((string) data_get($latestGenerationRun?->requested_input, 'template_id')) !== '')
            || $project->template_id !== null
        ) ? 'template' : 'new';

        if ($request->boolean('quick')) {
            return response()->json([
                'status' => $project->build_status,
                'has_session' => (bool) ($project->builder && $project->build_session_id),
                'build_session_id' => $project->build_session_id,
                'build_started_at' => $project->build_started_at?->toIso8601String(),
                'can_reconnect' => $project->build_status === 'building',
                'project_generation_version' => $projectGenerationVersion,
                'source_generation_type' => $sourceGenerationType,
                'preview_build_id' => $previewBuildId,
                'preview_url' => Storage::disk('local')->exists("previews/{$project->id}")
                    ? "/preview/{$project->id}"
                    : null,
                ...($request->boolean('history') ? [
                    'recent_history' => $project->getRecentHistory(20),
                ] : []),
            ]);
        }

        if (! $project->builder || ! $project->build_session_id) {
            return response()->json([
                'status' => $project->build_status,
                'has_session' => false,
                'project_generation_version' => $projectGenerationVersion,
                'source_generation_type' => $sourceGenerationType,
                'preview_build_id' => $previewBuildId,
                'recent_history' => $project->getRecentHistory(20),
            ]);
        }

        try {
            $status = $this->builderService->getSessionStatus(
                $project->builder,
                $project->build_session_id
            );

            return response()->json([
                'status' => $project->build_status,
                'has_session' => true,
                'session_status' => $status,
                'build_session_id' => $project->build_session_id,
                'build_started_at' => $project->build_started_at?->toIso8601String(),
                'can_reconnect' => $project->build_status === 'building',
                'project_generation_version' => $projectGenerationVersion,
                'source_generation_type' => $sourceGenerationType,
                'preview_build_id' => $previewBuildId,
                'preview_url' => Storage::disk('local')->exists("previews/{$project->id}")
                    ? "/preview/{$project->id}"
                    : null,
                'recent_history' => $project->getRecentHistory(20),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => $project->build_status,
                'has_session' => true,
                'build_session_id' => $project->build_session_id,
                'build_started_at' => $project->build_started_at?->toIso8601String(),
                'can_reconnect' => $project->build_status === 'building',
                'project_generation_version' => $projectGenerationVersion,
                'source_generation_type' => $sourceGenerationType,
                'preview_build_id' => $previewBuildId,
                'preview_url' => Storage::disk('local')->exists("previews/{$project->id}")
                    ? "/preview/{$project->id}"
                    : null,
                'error' => $e->getMessage(),
                'recent_history' => $project->getRecentHistory(20),
            ]);
        }
    }
}
