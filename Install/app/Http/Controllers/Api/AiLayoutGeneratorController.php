<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiLayoutGeneratorService;
use App\Services\AiLayoutProjectGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * AI Layout Generator API.
 * Prompt → structured input; generate layout JSON; generate theme tokens; create project.
 * AI never returns raw HTML; only layout JSON.
 */
class AiLayoutGeneratorController extends Controller
{
    public function __construct(
        protected AiLayoutGeneratorService $layoutGenerator,
        protected AiLayoutProjectGeneratorService $projectGenerator
    ) {}

    /**
     * POST /api/ai-layout/prompt-to-input
     * Body: { "prompt": "I need an online store selling cosmetics. Minimal design. ..." }
     */
    public function promptToInput(Request $request): JsonResponse
    {
        $request->validate([
            'prompt' => 'required|string|max:2000',
        ]);

        $input = $this->layoutGenerator->promptToStructuredInput($request->input('prompt'));

        return response()->json([
            'structured_input' => $input,
        ]);
    }

    /**
     * POST /api/ai-layout/generate
     * Body: { "prompt": "..." } OR { "structured_input": { business_type, industry, design_style, color_scheme, sections_required } }
     */
    public function generate(Request $request): JsonResponse
    {
        $structured = $request->input('structured_input');
        if (! is_array($structured)) {
            $request->validate(['prompt' => 'required|string|max:2000']);
            $structured = $this->layoutGenerator->promptToStructuredInput($request->input('prompt'));
        }

        $layout = $this->layoutGenerator->generateLayoutFromStructuredInput($structured);

        return response()->json([
            'layout' => $layout,
            'structured_input' => $structured,
        ]);
    }

    /**
     * POST /api/ai-layout/generate-theme
     * Body: { "color_scheme": "pastel", "design_style": "minimal", ... }
     */
    public function generateTheme(Request $request): JsonResponse
    {
        $tokens = $this->layoutGenerator->generateThemeTokens($request->all());

        return response()->json([
            'theme_tokens' => $tokens,
        ]);
    }

    /**
     * POST /api/ai-layout/create-project
     * Body: { "layout": { page, sections }, "theme_tokens": {}, "project_name": "My Store" }
     */
    public function createProject(Request $request): JsonResponse
    {
        $request->validate([
            'layout' => 'required|array',
            'layout.sections' => 'required|array',
            'theme_tokens' => 'nullable|array',
            'project_name' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        if (! $user->canCreateMoreProjects()) {
            return response()->json([
                'error' => __('You have reached the maximum number of projects.'),
            ], 422);
        }

        try {
            $project = $this->projectGenerator->createProjectFromAiLayout(
                $user,
                $request->input('layout'),
                $request->input('theme_tokens', []),
                $request->input('project_name', 'My Store')
            );
        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }

        return response()->json([
            'project_id' => $project->id,
            'project_name' => $project->name,
            'redirect_url' => route('project.cms', $project),
        ]);
    }

    /**
     * GET /api/ai-layout/presets
     */
    public function presets(): JsonResponse
    {
        $presets = config('ai-layout-generator.presets', []);

        return response()->json([
            'presets' => $presets,
        ]);
    }

    /**
     * GET /api/ai-layout/component-registry
     */
    public function componentRegistry(): JsonResponse
    {
        $registry = config('ai-layout-generator.component_registry', []);
        $variants = config('ai-layout-generator.component_variants', []);

        return response()->json([
            'components' => $registry,
            'variants' => $variants,
        ]);
    }
}
