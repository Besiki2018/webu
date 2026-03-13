<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\ProjectGenerationRun;
use Illuminate\Http\JsonResponse;

class ProjectGenerationStatusController extends Controller
{
    public function __invoke(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $project->loadMissing('latestGenerationRun');

        return response()->json([
            'generation' => $this->buildGenerationPayload($project->latestGenerationRun, $project),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildGenerationPayload(?ProjectGenerationRun $run, Project $project): ?array
    {
        if (! $run) {
            return null;
        }

        return [
            'id' => (string) $run->id,
            'status' => $run->status,
            'is_active' => $run->isActive(),
            'progress_message' => $run->progress_message,
            'error_message' => $run->error_message,
            'started_at' => $run->started_at?->toIso8601String(),
            'completed_at' => $run->completed_at?->toIso8601String(),
            'failed_at' => $run->failed_at?->toIso8601String(),
            'status_url' => route('project.generation.status', $project),
        ];
    }
}
