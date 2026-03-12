<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\AiTools\AiToolExecutorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Execute AI agent tools for a project workspace.
 * Used by the frontend tool executor when the AI requests file/list/search/reload actions.
 */
class ProjectAiToolsController extends Controller
{
    public function __construct(
        protected AiToolExecutorService $executor
    ) {}

    /**
     * Execute a single tool.
     *
     * POST body: { "tool": "readFile", "args": { "path": "src/pages/home/Page.tsx" }, "user_prompt": "optional" }
     */
    public function execute(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'tool' => 'required|string|max:64',
            'args' => 'nullable|array',
            'args.path' => 'nullable|string|max:500',
            'args.file_path' => 'nullable|string|max:500',
            'args.content' => 'nullable|string|max:1048576',
            'args.keyword' => 'nullable|string|max:200',
            'args.max_files' => 'nullable|integer|min:1|max:1000',
            'args.max_results' => 'nullable|integer|min:1|max:200',
            'user_prompt' => 'nullable|string|max:2000',
        ]);

        $args = $validated['args'] ?? [];
        $userPrompt = $validated['user_prompt'] ?? null;

        $result = $this->executor->execute(
            $project,
            $validated['tool'],
            $args,
            $userPrompt
        );

        if (! ($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'Tool execution failed',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => $result['data'] ?? null,
        ]);
    }
}
