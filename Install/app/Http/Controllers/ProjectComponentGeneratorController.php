<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\AiTools\ComponentGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Generates new section components when a required component does not exist.
 * Used by the Component Generator frontend and by flows that ensure sections exist before inserting.
 */
class ProjectComponentGeneratorController extends Controller
{
    public function __construct(
        protected ComponentGeneratorService $generator
    ) {}

    /**
     * Generate component TSX for a section. Returns content only if section does not already exist.
     *
     * POST body: { "section_name": "PricingSection"|"pricing", "user_prompt": "optional" }
     * Response: { "success", "already_exists", "path", "content"? }
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'section_name' => 'required|string|max:120',
            'user_prompt' => 'nullable|string|max:2000',
        ]);

        $result = $this->generator->generate(
            $project,
            $validated['section_name'],
            $validated['user_prompt'] ?? ''
        );

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Generation failed',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'already_exists' => $result['already_exists'] ?? false,
            'path' => $result['path'],
            'content' => $result['content'] ?? null,
        ]);
    }
}
