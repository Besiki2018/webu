<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsProjectGenerationPayload;
use App\Models\Project;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use Illuminate\Http\JsonResponse;

class ProjectGenerationStatusController extends Controller
{
    use BuildsProjectGenerationPayload;

    public function __construct(
        protected ProjectWorkspaceService $projectWorkspace
    ) {}

    public function __invoke(Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $project->loadMissing('latestGenerationRun');

        return response()->json([
            'generation' => $this->buildGenerationPayload(
                $project->latestGenerationRun,
                $project,
                $this->projectWorkspace
            ),
        ]);
    }
}
