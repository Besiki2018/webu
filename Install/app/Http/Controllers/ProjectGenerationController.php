<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\BuildsProjectGenerationPayload;
use App\Models\Project;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProjectGenerationController extends Controller
{
    use BuildsProjectGenerationPayload;

    public function __construct(
        protected ProjectWorkspaceService $projectWorkspace
    ) {}

    public function show(Request $request, Project $project): Response|RedirectResponse
    {
        $this->authorize('view', $project);

        $project->loadMissing('latestGenerationRun');
        $generationPayload = $this->buildGenerationPayload(
            $project->latestGenerationRun,
            $project,
            $this->projectWorkspace
        );

        if (! $this->generationRequiresCompletionGate($generationPayload)) {
            return redirect()->route('chat', ['project' => $project]);
        }

        $resumeDraftAvailable = Storage::disk('local')->exists("previews/{$project->id}");
        $resumeDraftMode = $resumeDraftAvailable && $request->boolean('resume_draft');

        return Inertia::render('Project/Generation', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'initial_prompt' => $project->initial_prompt,
            ],
            'generation' => $generationPayload,
            'builderUrl' => route('chat', ['project' => $project]),
            'resumeDraftAvailable' => $resumeDraftAvailable,
            'resumeDraftMode' => $resumeDraftMode,
            'resumeDraftUrl' => $resumeDraftAvailable
                ? route('project.generation', ['project' => $project, 'resume_draft' => 1])
                : null,
            'hideDraftUrl' => route('project.generation', ['project' => $project]),
            'resumeDraftPreviewUrl' => $resumeDraftMode ? "/preview/{$project->id}" : null,
            'createUrl' => '/create',
        ]);
    }
}
