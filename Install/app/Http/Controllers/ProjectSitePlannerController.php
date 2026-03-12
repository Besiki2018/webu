<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\AiTools\SitePlannerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns a structured site plan (pages + sections) from a user prompt.
 * Does not execute file changes; the plan is consumed by the AI tools/execution pipeline.
 */
class ProjectSitePlannerController extends Controller
{
    public function __construct(
        protected SitePlannerService $planner
    ) {}

    /**
     * Generate site plan from user prompt.
     *
     * POST body: { "prompt": "Create website for restaurant" }
     * Response: { "success": true, "plan": { "siteName", "pages" }, "from_fallback": false }
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'prompt' => 'required|string|max:4000',
            'design_pattern_hints' => 'sometimes|nullable|array',
            'design_pattern_hints.*' => 'string|max:500',
        ]);

        $hints = isset($validated['design_pattern_hints']) && is_array($validated['design_pattern_hints'])
            ? array_values(array_filter(array_map('trim', $validated['design_pattern_hints'])))
            : null;

        $result = $this->planner->generate($project, $validated['prompt'], null, $hints);

        return response()->json([
            'success' => $result['success'],
            'plan' => $result['plan'],
            'from_fallback' => $result['from_fallback'] ?? false,
            'error' => $result['error'] ?? null,
        ]);
    }
}
