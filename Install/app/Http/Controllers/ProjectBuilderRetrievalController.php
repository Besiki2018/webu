<?php

namespace App\Http\Controllers;

use App\Models\OperationLog;
use App\Models\Project;
use App\Services\AssetFirstDraftComposerService;
use App\Services\InternalAssetRetrievalService;
use App\Services\OperationLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectBuilderRetrievalController extends Controller
{
    public function __construct(
        protected InternalAssetRetrievalService $retrieval,
        protected AssetFirstDraftComposerService $composer,
        protected OperationLogService $operationLogs
    ) {}

    public function preview(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'prompt' => ['nullable', 'string', 'max:10000'],
            'template_id' => ['nullable', 'string'],
        ]);

        $prompt = trim((string) ($validated['prompt'] ?? $project->initial_prompt ?? $project->name ?? ''));
        if ($prompt === '') {
            $prompt = 'business website';
        }

        $context = $this->retrieval->buildContext(
            $project,
            $prompt,
            $validated['template_id'] ?? null
        );

        $this->operationLogs->logBuild(
            project: $project,
            event: 'builder_retrieval_preview',
            status: OperationLog::STATUS_INFO,
            message: 'Builder retrieval preview generated.',
            attributes: [
                'user_id' => $request->user()?->id,
                'source' => self::class,
                'context' => [
                    'source' => $context['source'] ?? null,
                    'fallback_to_generic' => $context['fallback_to_generic'] ?? null,
                ],
            ]
        );

        return response()->json([
            'project_id' => $project->id,
            'retrieval_context' => $context,
        ]);
    }

    public function compose(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'prompt' => ['required', 'string', 'max:10000'],
            'template_id' => ['nullable', 'string'],
            'reset_existing_content' => ['sometimes', 'boolean'],
        ]);

        $result = $this->composer->composeForProject(
            project: $project,
            prompt: (string) $validated['prompt'],
            templateId: isset($validated['template_id']) ? (string) $validated['template_id'] : null,
            resetExistingContent: (bool) ($validated['reset_existing_content'] ?? true),
            actorId: $request->user()?->id
        );

        $this->operationLogs->logBuild(
            project: $project,
            event: 'builder_asset_first_composed',
            status: OperationLog::STATUS_SUCCESS,
            message: 'Asset-first draft composed from retrieval catalog.',
            attributes: [
                'user_id' => $request->user()?->id,
                'source' => self::class,
                'context' => [
                    'template' => $result['template'] ?? null,
                    'retrieval' => $result['retrieval'] ?? null,
                    'classification' => $result['classification'] ?? null,
                ],
            ]
        );

        return response()->json([
            'project_id' => $project->id,
            'composition' => $result,
        ]);
    }
}
