<?php

namespace App\Http\Controllers;

/**
 * Builder mutation endpoints (Task 6: read/status is in BuilderStatusController).
 * All routes here use throttle:builder-operations. Status polling uses BuilderStatusController + throttle:builder-status.
 */
use App\Models\Builder;
use App\Models\OperationLog;
use App\Models\Project;
use App\Models\Template;
use App\Services\BuilderService;
use App\Services\InternalAssetRetrievalService;
use App\Services\OperationLogService;
use App\Services\ProjectOperationGuardService;
use App\Services\TemplateClassifierService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

class BuilderProxyController extends Controller
{
    private const STALE_BUILD_LOCK_MINUTES = 5;

    private const STALE_BUILD_LOCK_HARD_TIMEOUT_MINUTES = 30;

    public function __construct(
        protected BuilderService $builderService,
        protected TemplateClassifierService $templateClassifier,
        protected ProjectOperationGuardService $operationGuard,
        protected OperationLogService $operationLogs,
        protected InternalAssetRetrievalService $retrieval
    ) {}

    /**
     * Get available builders for the current user.
     */
    public function getAvailableBuilders(Request $request): JsonResponse
    {
        $builders = Builder::active()->get();

        return response()->json([
            'builders' => $builders->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
            ]),
        ]);
    }

    /**
     * Start a new build session.
     */
    public function startBuild(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'prompt' => 'required|string|max:10000',
            'builder_id' => 'nullable|exists:builders,id',
            'template_url' => 'nullable|url',
            'template_id' => 'nullable|string',
            'history' => 'array',
        ]);

        $user = $request->user();

        $this->releaseStaleBuildLocksForUser((int) $user->id);

        // Block concurrent builds for the same user
        $activeBuild = Project::where('user_id', $user->id)
            ->where('build_status', 'building')
            ->orderByDesc('build_started_at')
            ->first();

        if ($activeBuild) {
            return response()->json([
                'error' => 'You have an active session. Wait for it to complete, or stop it.',
            ], 409);
        }

        // Check if user can still perform builds
        $buildCreditService = app(\App\Services\BuildCreditService::class);
        $canBuild = $buildCreditService->canPerformBuild($user);

        if (! $canBuild['allowed']) {
            return response()->json([
                'error' => $canBuild['reason'],
            ], 403);
        }

        // Capacity enforcement: block new build sessions when tenant storage quota is exhausted.
        if ($user->canUseFileStorage()) {
            $remainingStorage = $user->getRemainingStorageBytes();
            if ($remainingStorage !== -1 && $remainingStorage <= 0) {
                return response()->json([
                    'error' => 'Storage quota exceeded for current plan. Upgrade your plan or free up storage before starting a new build.',
                    'code' => 'capacity_quota_exceeded',
                ], 403);
            }
        }

        // Get AI config from user's plan
        try {
            $aiConfig = $this->builderService->getAiConfigForUser($user);
            // Pass remaining credits to builder for mid-session enforcement
            // 0 = unlimited (user has own API key or unlimited plan)
            $aiConfig['agent']['remaining_build_credits'] = $user->getRemainingBuildCredits();
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 400);
        }

        // Select builder based on plan or auto-select
        $builder = null;

        // First try to get builder from user's resolved plan
        $plan = $user->getCurrentPlan();
        if ($plan) {
            $builder = $plan->getBuilderWithFallbacks();
        }

        // Platform admins can always fall back to any active builder.
        if (! $builder && $user->hasAdminBypass()) {
            $builder = Builder::query()
                ->where('status', 'active')
                ->orderBy('id')
                ->first();
        }

        // If no builder from plan, allow manual selection or auto-select
        if (! $builder && ! empty($validated['builder_id'])) {
            $builder = Builder::findOrFail($validated['builder_id']);

            // Check builder is active
            if ($builder->status !== 'active') {
                return response()->json([
                    'error' => 'Selected builder is not active',
                ], 400);
            }
        }

        if (! $builder) {
            return response()->json([
                'error' => 'No builders are currently available. Please try again later.',
            ], 503);
        }

        // Check builder is reachable before starting (avoids generic connection error)
        if (! $this->ensureBuilderOnline($builder)) {
            return response()->json([
                'error' => __('Builder is offline. Start it with "composer dev" or run "bash scripts/start-local-builder.sh".'),
                'code' => 'builder_offline',
            ], 503);
        }

        // Validate and select template
        $templateId = $validated['template_id'] ?? null;

        // Validate explicit template_id against user's plan
        if ($templateId) {
            $template = Template::find($templateId);
            if ($template && ! $user->hasAdminBypass() && ! $template->isAvailableForPlan($user->getCurrentPlan())) {
                return response()->json([
                    'error' => 'The selected template is not available for your plan.',
                ], 403);
            }
        }

        // Auto-select template based on user's goal if none specified
        if (! $templateId) {
            $templateId = $this->autoSelectTemplate($user, $validated['prompt']);
        }

        try {
            // Detect repeated prompts before appending to history
            $promptToSend = $this->prependAssistantReplyRules($validated['prompt'], $project);
            $repeated = $project->detectRepeatedPrompts($validated['prompt']);
            if ($repeated) {
                $promptToSend .= "\n\nNOTE: The user has asked about this issue {$repeated['count']} times before. Previous attempts may not have fully resolved it. Try a fundamentally different approach.";
            }

            // Append user message to conversation history (keeps project in sync)
            $project->appendToHistory('user', $validated['prompt']);

            // Use client-sent history when provided so chat context is preserved (frontend has latest from Pusher)
            $historyData = $this->buildHistoryDataForBuilder($project, $validated['history'] ?? [], $promptToSend);
            $retrievalContext = $this->retrieval->buildContext(
                $project,
                $validated['prompt'],
                $templateId
            );

            $this->operationLogs->logBuild(
                project: $project,
                event: 'build_retrieval_context_prepared',
                status: OperationLog::STATUS_INFO,
                message: 'Internal asset retrieval context attached to build payload.',
                attributes: [
                    'user_id' => $user->id,
                    'source' => self::class,
                    'context' => [
                        'source' => $retrievalContext['source'] ?? null,
                        'fallback_to_generic' => $retrievalContext['fallback_to_generic'] ?? null,
                        'template_candidate_count' => count($retrievalContext['catalog']['templates'] ?? []),
                        'section_candidate_count' => count($retrievalContext['catalog']['sections'] ?? []),
                    ],
                ]
            );

            $result = $this->builderService->startSession(
                $builder,
                $project,
                $promptToSend,
                [], // Legacy parameter, use historyData instead
                $validated['template_url'] ?? null,
                $templateId, // Use auto-selected or provided template
                $aiConfig,
                $historyData, // Optimized history with is_compacted flag
                $retrievalContext
            );

            // Update project with build info
            $resolvedTemplateId = ctype_digit((string) $templateId) ? (int) $templateId : null;
            $project->update([
                'template_id' => $resolvedTemplateId ?? $project->template_id,
                'builder_id' => $builder->id,
                'build_session_id' => $result['session_id'],
                'build_status' => 'building',
                'build_started_at' => now(),
                'build_completed_at' => null,
            ]);

            return response()->json([
                'session_id' => $result['session_id'],
                'build_id' => $result['session_id'],
                'builder_id' => $builder->id,
                'builder_name' => $builder->name,
            ]);
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $isOffline = str_contains($message, 'offline') || str_contains($message, 'unreachable');
            return response()->json([
                'error' => 'Failed to start build: '.$message,
                ...($isOffline ? ['code' => 'builder_offline'] : []),
            ], $isOffline ? 503 : 500);
        }
    }

    /**
     * Build history payload for the builder. Uses client-sent history when provided so chat context is preserved.
     *
     * @param  array<int, array{role?: string, content?: string}>  $requestHistory  History from frontend (user/assistant only)
     * @return array{history: array<int, array{role: string, content: string}>, is_compacted: bool}
     */
    private function buildHistoryDataForBuilder(Project $project, array $requestHistory, string $currentPrompt): array
    {
        if ($requestHistory === []) {
            return $project->getHistoryForBuilderOptimized();
        }

        $builderHistory = [];
        foreach ($requestHistory as $m) {
            $role = isset($m['role']) && in_array((string) $m['role'], ['user', 'assistant'], true)
                ? (string) $m['role']
                : 'user';
            $content = isset($m['content']) ? (string) $m['content'] : '';
            $builderHistory[] = ['role' => $role, 'content' => $content];
        }
        $builderHistory[] = ['role' => 'user', 'content' => $currentPrompt];

        $base = $project->getHistoryForBuilderOptimized();
        $baseHistory = $base['history'] ?? [];
        if (! empty($baseHistory) && ($baseHistory[0]['role'] ?? '') === 'system') {
            array_unshift($builderHistory, $baseHistory[0]);
        }

        return [
            'history' => $builderHistory,
            'is_compacted' => false,
        ];
    }

    /**
     * Send a chat message to continue the session.
     */
    public function chat(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'message' => 'required|string|max:10000',
        ]);

        if (! $project->builder || ! $project->build_session_id) {
            return response()->json([
                'error' => 'No active build session',
            ], 404);
        }

        try {
            // Detect repeated prompts before appending to history
            $messageToSend = $this->prependAssistantReplyRules($validated['message'], $project);
            $repeated = $project->detectRepeatedPrompts($validated['message']);
            if ($repeated) {
                $messageToSend .= "\n\nNOTE: The user has asked about this issue {$repeated['count']} times before. Previous attempts may not have fully resolved it. Try a fundamentally different approach.";
            }

            // Save user message BEFORE sending to builder
            // Note: This clears compacted_history since it's now stale
            $project->appendToHistory('user', $validated['message']);

            // Get optimized history (uses compacted if available, but after appendToHistory
            // it will be cleared and use full conversation_history)
            $historyData = $project->getHistoryForBuilderOptimized();

            $result = $this->builderService->sendMessage(
                $project->builder,
                $project->build_session_id,
                $messageToSend,
                [], // Legacy parameter, use historyData instead
                $historyData // Optimized history with is_compacted flag
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Cancel a running build session.
     */
    public function cancel(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        if (! $project->builder || ! $project->build_session_id) {
            return response()->json([
                'error' => 'No active build session',
            ], 404);
        }

        try {
            $cancelled = $this->builderService->cancelSession(
                $project->builder,
                $project->build_session_id
            );

            if ($cancelled) {
                $this->builderService->completeSession($project->builder);

                $project->update([
                    'build_status' => 'cancelled',
                    'build_completed_at' => now(),
                ]);
            }

            return response()->json([
                'cancelled' => $cancelled,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark build as complete.
     */
    public function completeBuild(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        if ($project->builder) {
            $this->builderService->completeSession($project->builder);
        }

        $project->update([
            'build_status' => 'completed',
            'build_completed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Download build output.
     */
    public function downloadOutput(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        if (! $project->builder) {
            return response()->json([
                'error' => 'No build to download',
            ], 404);
        }

        try {
            $path = $this->builderService->fetchBuildOutput(
                $project->builder,
                $project->id,
                $project
            );

            $project->update(['build_path' => $path]);

            return response()->json([
                'success' => true,
                'path' => $path,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get workspace files.
     */
    public function getFiles(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $builder = $this->resolveProjectBuilder($request, $project);
        if (! $builder) {
            return response()->json([
                'files' => [],
                'error' => 'No builder assigned to this project',
                'code' => 'builder_missing',
                'offline' => true,
            ]);
        }

        if (! $this->ensureBuilderOnline($builder)) {
            return response()->json([
                'files' => [],
                'error' => __('Builder is offline. Start it with "composer dev" or run "bash scripts/start-local-builder.sh".'),
                'code' => 'builder_offline',
                'offline' => true,
            ]);
        }

        try {
            $files = $this->builderService->getWorkspaceFiles(
                $builder,
                $project->id
            );

            return response()->json($files);
        } catch (\Exception $e) {
            Log::warning('builder_get_files_failed', [
                'project_id' => $project->id,
                'builder_id' => $builder->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'files' => [],
                'error' => 'Failed to get workspace files',
                'code' => 'workspace_files_unavailable',
                'offline' => true,
            ]);
        }
    }

    /**
     * Get a specific file.
     */
    public function getFile(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $validated = $request->validate([
            'path' => 'required|string',
        ]);

        $builder = $this->resolveProjectBuilder($request, $project);
        if (! $builder) {
            return response()->json([
                'error' => 'No builder assigned to this project',
                'code' => 'builder_missing',
            ], 503);
        }

        if (! $this->ensureBuilderOnline($builder)) {
            return response()->json([
                'error' => __('Builder is offline. Start it with "composer dev" or run "bash scripts/start-local-builder.sh".'),
                'code' => 'builder_offline',
            ], 503);
        }

        try {
            $file = $this->builderService->getFile(
                $builder,
                $project->id,
                $validated['path']
            );

            return response()->json($file);
        } catch (\Exception $e) {
            Log::warning('builder_get_file_failed', [
                'project_id' => $project->id,
                'builder_id' => $builder->id,
                'path' => $validated['path'],
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'error' => 'Failed to get file',
                'code' => 'workspace_file_unavailable',
            ], 503);
        }
    }

    /**
     * Update a file in workspace.
     */
    public function updateFile(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'path' => 'required|string',
            'content' => 'required|string',
        ]);

        // Must have a builder to proceed
        if (! $project->builder) {
            return response()->json([
                'error' => 'No builder assigned to this project',
            ], 404);
        }

        try {
            $success = $this->builderService->updateFile(
                $project->builder,
                $project->id,
                $validated['path'],
                $validated['content']
            );

            return response()->json(['success' => $success]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update file',
            ], 500);
        }
    }

    /**
     * Trigger a build.
     */
    public function triggerBuild(Request $request, Project $project): JsonResponse
    {
        return $this->runPreviewBuild($request, $project, 'build-preview');
    }

    /**
     * Retry preview build with idempotency/locking safeguards.
     */
    public function retryBuild(Request $request, Project $project): JsonResponse
    {
        return $this->runPreviewBuild($request, $project, 'build-preview-retry');
    }

    /**
     * Run preview build with concurrency and idempotency guard.
     */
    protected function runPreviewBuild(Request $request, Project $project, string $operation): JsonResponse
    {
        $this->authorize('update', $project);

        $builder = $this->resolveProjectBuilder($request, $project);
        $previewUrl = Storage::disk('local')->exists("previews/{$project->id}")
            ? "/preview/{$project->id}"
            : null;

        // Must have a builder to proceed
        if (! $builder) {
            $this->operationLogs->logBuild(
                project: $project,
                event: 'preview_builder_missing',
                status: OperationLog::STATUS_ERROR,
                message: 'Preview build failed because no active builder is available.',
                attributes: [
                    'source' => self::class,
                    'context' => ['operation' => $operation],
                ]
            );

            return response()->json([
                'success' => false,
                'error' => 'No active builder is available for this project.',
                'code' => 'builder_missing',
                'offline' => true,
                'preview_url' => $previewUrl,
            ]);
        }

        // Check builder is reachable
        if (! $this->ensureBuilderOnline($builder)) {
            return response()->json([
                'success' => false,
                'error' => __('Builder is offline. Start it with "composer dev" or run "bash scripts/start-local-builder.sh".'),
                'code' => 'builder_offline',
                'offline' => true,
                'preview_url' => $previewUrl,
            ]);
        }

        if ($project->build_status === 'building') {
            $this->operationLogs->logBuild(
                project: $project,
                event: 'preview_build_conflict',
                status: OperationLog::STATUS_WARNING,
                message: 'Preview build retry was blocked because another build is still running.',
                attributes: [
                    'source' => self::class,
                    'context' => [
                        'operation' => $operation,
                        'build_status' => $project->build_status,
                    ],
                ]
            );

            return response()->json([
                'error' => 'Build session is still running. Retry preview build after it completes.',
            ], 409);
        }

        $response = $this->operationGuard->execute(
            $request,
            $project,
            $operation,
            function () use ($project, $builder) {
                try {
                    $result = $this->builderService->triggerBuild(
                        $builder,
                        $project->id,
                        $project->id
                    );

                    $buildSucceeded = (bool) ($result['success'] ?? true);

                    $this->operationLogs->logBuild(
                        project: $project,
                        event: 'preview_build_completed',
                        status: $buildSucceeded ? OperationLog::STATUS_SUCCESS : OperationLog::STATUS_WARNING,
                        message: $buildSucceeded
                            ? 'Preview build completed successfully.'
                            : 'Preview build returned a non-success status.',
                        attributes: [
                            'source' => self::class,
                            'context' => [
                                'success' => $buildSucceeded,
                                'preview_url' => $result['preview_url'] ?? null,
                            ],
                        ]
                    );

                    return response()->json($result);
                } catch (\Exception $e) {
                    $this->operationLogs->logBuild(
                        project: $project,
                        event: 'preview_build_failed',
                        status: OperationLog::STATUS_ERROR,
                        message: $e->getMessage(),
                        attributes: [
                            'source' => self::class,
                            'context' => [
                                'exception' => $e->getMessage(),
                            ],
                        ]
                    );

                    return response()->json([
                        'error' => 'Build failed: '.$e->getMessage(),
                    ], 500);
                }
            },
            [
                'project_id' => $project->id,
                'builder_id' => $builder->id,
                'operation' => $operation,
            ]
        );

        if ($response->headers->get('X-Idempotent-Replay') === 'true') {
            $this->operationLogs->logBuild(
                project: $project,
                event: 'preview_build_idempotent_replay',
                status: OperationLog::STATUS_INFO,
                message: 'Build retry request was served from idempotency cache.',
                attributes: [
                    'source' => self::class,
                    'context' => ['operation' => $operation],
                ]
            );
        } elseif ($response->getStatusCode() >= 400 && $response->getStatusCode() < 500) {
            $body = $response->getData(true);
            $this->operationLogs->logBuild(
                project: $project,
                event: 'preview_build_rejected',
                status: OperationLog::STATUS_WARNING,
                message: $body['error'] ?? 'Build request was rejected.',
                attributes: [
                    'source' => self::class,
                    'context' => [
                        'operation' => $operation,
                        'http_status' => $response->getStatusCode(),
                    ],
                ]
            );
        }

        return $response;
    }

    /**
     * Resolve the active builder for a project with safe fallbacks.
     */
    protected function resolveProjectBuilder(Request $request, Project $project): ?Builder
    {
        $builder = $project->builder;

        if ($builder && $builder->status === 'active') {
            return $builder;
        }

        $user = $request->user();
        $builder = $user?->getCurrentPlan()?->getBuilderWithFallbacks();

        if (! $builder) {
            $builder = Builder::query()
                ->where('status', 'active')
                ->orderBy('id')
                ->first();
        }

        if (! $builder) {
            return null;
        }

        if ((int) $project->builder_id !== (int) $builder->id) {
            $project->forceFill([
                'builder_id' => $builder->id,
            ])->save();
        }

        $project->setRelation('builder', $builder);

        return $builder;
    }

    /**
     * Ensure builder is online; in local dev, attempt auto-start for localhost builders.
     */
    protected function ensureBuilderOnline(Builder $builder): bool
    {
        $details = $builder->getDetails();
        if ((bool) ($details['online'] ?? false)) {
            return true;
        }

        if (! $this->tryAutoStartLocalBuilder($builder)) {
            return false;
        }

        $details = $builder->getDetails();

        return (bool) ($details['online'] ?? false);
    }

    /**
     * Best-effort local builder auto-start to avoid manual restarts during local development.
     */
    protected function tryAutoStartLocalBuilder(Builder $builder): bool
    {
        $autoStartEnabled = filter_var((string) env('BUILDER_AUTO_START_LOCAL', 'true'), FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
        if ($autoStartEnabled === false) {
            return false;
        }

        $host = parse_url((string) $builder->full_url, PHP_URL_HOST);
        if (! is_string($host) || ! in_array(strtolower($host), ['localhost', '127.0.0.1'], true)) {
            return false;
        }

        $scriptPath = base_path('scripts/start-local-builder.sh');
        if (! is_file($scriptPath)) {
            return false;
        }

        $port = (int) ($builder->port ?? 8846);
        $serverKey = (string) ($builder->server_key ?? '123456');
        $logPath = storage_path('logs/local-builder.log');

        $command = sprintf(
            'env BUILDER_PORT=%s BUILDER_SERVER_KEY=%s nohup bash %s > %s 2>&1 < /dev/null &',
            escapeshellarg((string) $port),
            escapeshellarg($serverKey),
            escapeshellarg($scriptPath),
            escapeshellarg($logPath),
        );

        try {
            Process::path(base_path())->run($command);
        } catch (\Throwable $e) {
            Log::warning('builder_autostart_failed', [
                'builder_id' => $builder->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        // Allow short warm-up window before declaring startup failure.
        $deadline = microtime(true) + 3.0;
        while (microtime(true) < $deadline) {
            usleep(250000);
            $details = $builder->getDetails();
            if ((bool) ($details['online'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clear stale "building" rows that no longer have a live builder session.
     */
    protected function releaseStaleBuildLocksForUser(int $userId): void
    {
        $activeBuilds = Project::query()
            ->with('builder')
            ->where('user_id', $userId)
            ->where('build_status', 'building')
            ->get();

        foreach ($activeBuilds as $activeBuild) {
            $resolvedStatus = $this->resolveRecoveredBuildStatus($activeBuild);

            if ($resolvedStatus === null) {
                continue;
            }

            $activeBuild->forceFill([
                'build_status' => $resolvedStatus,
                'build_completed_at' => now(),
            ])->save();

            Log::info('builder_stale_lock_recovered', [
                'project_id' => $activeBuild->id,
                'build_session_id' => $activeBuild->build_session_id,
                'recovered_status' => $resolvedStatus,
            ]);
        }
    }

    protected function resolveRecoveredBuildStatus(Project $project): ?string
    {
        if (! $project->builder || ! is_string($project->build_session_id) || trim($project->build_session_id) === '') {
            return 'failed';
        }

        $minutesOld = $project->build_started_at?->diffInMinutes(now()) ?? self::STALE_BUILD_LOCK_HARD_TIMEOUT_MINUTES;

        if ($minutesOld >= self::STALE_BUILD_LOCK_HARD_TIMEOUT_MINUTES) {
            return 'failed';
        }

        $details = $project->builder->getDetails();
        $online = (bool) ($details['online'] ?? false);
        $sessions = (int) ($details['sessions'] ?? 0);

        if ($online && $minutesOld >= self::STALE_BUILD_LOCK_MINUTES && $sessions === 0) {
            return 'failed';
        }

        if (! $online && $minutesOld < self::STALE_BUILD_LOCK_MINUTES) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders(['X-Server-Key' => $project->builder->server_key])
                ->get("{$project->builder->full_url}/api/status/{$project->build_session_id}");
        } catch (\Throwable $e) {
            return $minutesOld >= self::STALE_BUILD_LOCK_MINUTES ? 'failed' : null;
        }

        if ($response->status() === 404) {
            return 'failed';
        }

        if (! $response->successful()) {
            return $minutesOld >= self::STALE_BUILD_LOCK_MINUTES ? 'failed' : null;
        }

        $status = strtolower(trim((string) $response->json('status')));

        return in_array($status, ['completed', 'failed', 'cancelled'], true)
            ? $status
            : null;
    }

    /**
     * Get AI suggestions.
     */
    public function getSuggestions(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        if (! $project->builder || ! $project->build_session_id) {
            return response()->json([
                'suggestions' => [],
            ]);
        }

        try {
            $result = $this->builderService->getSuggestions(
                $project->builder,
                $project->build_session_id
            );

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'suggestions' => [],
            ]);
        }
    }

    /**
     * Check if the builder is online/healthy.
     */
    public function checkBuilderHealth(Request $request, Project $project): JsonResponse
    {
        $this->authorize('view', $project);

        $builder = $this->resolveProjectBuilder($request, $project);

        if (! $builder) {
            return response()->json([
                'online' => false,
                'message' => 'No builder available',
            ]);
        }

        // Health check is allowed to auto-start local builders in dev environments.
        $online = $this->ensureBuilderOnline($builder);
        $details = $builder->getDetails();

        return response()->json([
            'online' => $online && (bool) ($details['online'] ?? false),
            'builder_id' => $builder->id,
            'builder_name' => $builder->name,
            'builder_url' => $builder->full_url,
            'version' => $details['version'],
            'sessions' => $details['sessions'],
            'message' => ($online && (bool) ($details['online'] ?? false))
                ? 'Builder is online'
                : __('Builder is offline. Start it with "composer dev" or run "bash scripts/start-local-builder.sh".'),
        ]);
    }

    /**
     * Redirect GET /builder/projects/{project}/build to chat (POST is triggerBuild).
     */
    public function redirectBuildToChat(Project $project): RedirectResponse
    {
        return redirect()->route('chat', $project);
    }

    /**
     * Auto-select template based on user's goal using AI classification.
     */
    protected function autoSelectTemplate($user, string $prompt): ?string
    {
        $plan = $user->hasAdminBypass() ? null : $user->getCurrentPlan();
        $classification = $this->templateClassifier->classifyDetailed(
            $prompt,
            (string) ($user->locale ?? app()->getLocale())
        );
        $locale = (string) ($classification['locale'] ?? ($user->locale ?? app()->getLocale()));
        $keywordClassification = $this->templateClassifier->keywordFallbackDetailed($prompt, $locale, null);
        $category = $classification['category'] ?? null;

        if (
            $category === 'ecommerce'
            && ($keywordClassification['strategy'] ?? null) !== 'default'
            && ($keywordClassification['category'] ?? null) !== 'ecommerce'
        ) {
            $category = $keywordClassification['category'] ?? $category;
        }

        $focusedCategory = null;

        if (is_string($category) && trim($category) !== '') {
            $normalizedCategory = strtolower(trim($category));
            $focusedCategory = match ($normalizedCategory) {
                'ecommerce' => 'ecommerce',
                'business' => 'business',
                'booking', 'medical', 'vet', 'grooming' => 'booking',
                default => 'business',
            };
        }

        if ($focusedCategory) {
            $template = $this->templateClassifier->findPreferredTemplateByCategory(
                $focusedCategory,
                $plan,
                $user->hasAdminBypass()
            );

            if ($template) {
                Log::info('Auto-selected template', [
                    'category' => $category,
                    'focused_category' => $focusedCategory,
                    'confidence' => $classification['confidence'] ?? null,
                    'fallback_reason' => $classification['fallback_reason'] ?? null,
                    'classification_strategy' => $classification['strategy'] ?? null,
                    'classification_locale' => $classification['locale'] ?? null,
                    'template_id' => $template->id,
                    'template_name' => $template->name,
                    'plan_id' => $plan?->id,
                ]);

                return (string) $template->id;
            }
        }

        // No focused template matched: enforce the current product focus track order.
        $focusedFallbackSlugs = ['ecommerce', 'business-starter', 'booking-starter', 'default'];
        $fallbackQuery = $user->hasAdminBypass()
            ? Template::query()
            : Template::forPlan($plan);
        $focusedFallbackTemplate = $fallbackQuery
            ->whereIn('slug', $focusedFallbackSlugs)
            ->orderByRaw(
                'CASE slug WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 WHEN ? THEN 3 ELSE 4 END',
                $focusedFallbackSlugs
            )
            ->orderBy('id')
            ->first();

        if ($focusedFallbackTemplate) {
            return (string) $focusedFallbackTemplate->id;
        }

        // Last-resort fallback for legacy environments.
        $defaultTemplate = Template::where('slug', 'default')->first();

        return $defaultTemplate ? (string) $defaultTemplate->id : null;
    }

    protected function prependAssistantReplyRules(string $message, Project $project): string
    {
        $trimmed = trim($message);
        if ($trimmed === '') {
            return $message;
        }

        $rules = [
            'Reply in the same language as the user message.',
            'Use a natural, polished, user-facing tone similar to ChatGPT.',
            'Keep answers direct and useful. Do not expose tool calls, internal reasoning, or developer meta commentary unless the user explicitly asks for it.',
        ];

        if (preg_match('/\p{Georgian}/u', $trimmed) === 1) {
            $rules[] = 'The user wrote in Georgian. Reply fully in Georgian.';
        } elseif (($project->site?->locale ?? null) === 'ka') {
            $rules[] = 'The project locale is Georgian. Prefer Georgian unless the user explicitly requests another language.';
        }

        return "[ASSISTANT_REPLY_RULES]\n- ".implode("\n- ", $rules)."\n\n".$message;
    }
}
