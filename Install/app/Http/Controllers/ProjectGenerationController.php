<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\RedirectResponse;

class ProjectGenerationController extends Controller
{
    public function show(Project $project): RedirectResponse
    {
        $this->authorize('view', $project);

        return redirect()->route('chat', ['project' => $project]);
    }
}
