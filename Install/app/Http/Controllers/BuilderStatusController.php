<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\BuilderService;
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
        protected BuilderService $builderService
    ) {}

    /**
     * Get build status (quick = DB only; otherwise includes builder service status).
     */
    public function getStatus(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        if ($request->boolean('quick')) {
            return response()->json([
                'status' => $project->build_status,
                'has_session' => (bool) ($project->builder && $project->build_session_id),
                'build_session_id' => $project->build_session_id,
                'build_started_at' => $project->build_started_at?->toIso8601String(),
                'can_reconnect' => $project->build_status === 'building',
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
                'preview_url' => Storage::disk('local')->exists("previews/{$project->id}")
                    ? "/preview/{$project->id}"
                    : null,
                'error' => $e->getMessage(),
                'recent_history' => $project->getRecentHistory(20),
            ]);
        }
    }
}
