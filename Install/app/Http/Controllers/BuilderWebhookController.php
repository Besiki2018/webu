<?php

namespace App\Http\Controllers;

use App\Events\Builder\BuilderActionEvent;
use App\Events\Builder\BuilderCompleteEvent;
use App\Events\Builder\BuilderErrorEvent;
use App\Events\Builder\BuilderMessageEvent;
use App\Events\Builder\BuilderStatusEvent;
use App\Events\Builder\BuilderThinkingEvent;
use App\Events\Builder\BuilderToolCallEvent;
use App\Events\Builder\BuilderToolResultEvent;
use App\Events\ProjectStatusUpdatedEvent;
use App\Models\OperationLog;
use App\Models\Project;
use App\Services\NotificationService;
use App\Services\OperationLogService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuilderWebhookController extends Controller
{
    protected NotificationService $notificationService;

    public function __construct(
        NotificationService $notificationService,
        protected OperationLogService $operationLogs
    ) {
        $this->notificationService = $notificationService;
    }

    public function handle(Request $request): JsonResponse
    {
        // Validate required fields
        $validated = $request->validate([
            'session_id' => 'required|string',
            'event_type' => 'required|string',
            'data' => 'required|array',
            'timestamp' => 'nullable|string',
        ]);

        $sessionId = $validated['session_id'];
        $eventType = $validated['event_type'];
        $data = $validated['data'];

        // Dispatch appropriate event
        $dispatched = match ($eventType) {
            'status' => $this->dispatchStatus($sessionId, $data),
            'thinking' => $this->dispatchThinking($sessionId, $data),
            'action' => $this->dispatchAction($sessionId, $data),
            'tool_call' => $this->dispatchToolCall($sessionId, $data),
            'tool_result' => $this->dispatchToolResult($sessionId, $data),
            'message' => $this->dispatchMessage($sessionId, $data),
            'error' => $this->dispatchError($sessionId, $data),
            'complete' => $this->dispatchComplete($sessionId, $data),
            'summarization_complete' => $this->handleSummarizationComplete($sessionId, $data),
            'plan', 'iteration_start', 'retry', 'token_usage',
            'tool_timeout', 'tool_retry', 'credit_warning', 'credit_exceeded' => true,
            default => false,
        };

        if ($dispatched === false) {
            return response()->json(['error' => 'Unknown event type: '.$eventType], 400);
        }

        return response()->json(['success' => true]);
    }

    protected function dispatchStatus(string $sessionId, array $data): bool
    {
        $status = $data['status'] ?? '';
        $message = $data['message'] ?? '';

        BuilderStatusEvent::dispatch(
            $sessionId,
            $status,
            $message
        );

        if (in_array($status, ['failed', 'completed', 'cancelled'], true)) {
            $project = $this->resolveProjectBySession($sessionId);
            if ($project) {
                $this->operationLogs->logBuild(
                    project: $project,
                    event: 'builder_status_'.$status,
                    status: $status === 'failed' ? OperationLog::STATUS_ERROR : OperationLog::STATUS_INFO,
                    message: $message ?: "Builder status updated to {$status}.",
                    attributes: [
                        'source' => self::class,
                        'identifier' => $sessionId,
                        'context' => [
                            'builder_status' => $status,
                            'builder_message' => $message,
                        ],
                    ]
                );
            }
        }

        return true;
    }

    protected function dispatchThinking(string $sessionId, array $data): bool
    {
        $content = $data['content'] ?? '';

        // Save substantial AI content to conversation history
        // (longer than 100 chars and not just thinking indicators)
        if (strlen($content) > 100 && ! preg_match('/^(thinking|analyzing|processing)/i', $content)) {
            $project = Project::where('build_session_id', $sessionId)->first();
            if ($project) {
                // Calculate thinking duration from last user message
                $thinkingDuration = null;
                $lastUserTimestamp = $project->getLastUserMessageTimestamp();
                if ($lastUserTimestamp) {
                    $thinkingDuration = (int) $lastUserTimestamp->diffInSeconds(now());
                }

                $project->appendToHistory('assistant', $content, null, $thinkingDuration);
            }
        }

        BuilderThinkingEvent::dispatch(
            $sessionId,
            $content,
            $data['iteration'] ?? 0
        );

        return true;
    }

    protected function dispatchAction(string $sessionId, array $data): bool
    {
        $action = $data['action'] ?? '';
        $target = $data['target'] ?? '';
        $category = $data['category'] ?? '';

        // Save action to conversation history for context
        if (! empty($action)) {
            $project = Project::where('build_session_id', $sessionId)->first();
            if ($project) {
                $actionText = trim("{$action} {$target}");
                $project->appendToHistory('action', $actionText, $category ?: null);
            }
        }

        BuilderActionEvent::dispatch(
            $sessionId,
            $action,
            $target,
            $data['details'] ?? '',
            $category
        );

        return true;
    }

    protected function dispatchToolCall(string $sessionId, array $data): bool
    {
        BuilderToolCallEvent::dispatch(
            $sessionId,
            $data['id'] ?? '',
            $data['tool'] ?? '',
            $data['params'] ?? []
        );

        return true;
    }

    protected function dispatchToolResult(string $sessionId, array $data): bool
    {
        BuilderToolResultEvent::dispatch(
            $sessionId,
            $data['id'] ?? '',
            $data['tool'] ?? '',
            $data['success'] ?? false,
            $data['output'] ?? '',
            (int) ($data['duration_ms'] ?? 0),
            (int) ($data['iteration'] ?? 0),
        );

        return true;
    }

    protected function dispatchMessage(string $sessionId, array $data): bool
    {
        $content = $data['content'] ?? '';

        // Persist assistant message to conversation history
        if (! empty($content)) {
            $project = Project::where('build_session_id', $sessionId)->first();
            if ($project) {
                // Check if this content was already saved (from thinking event)
                $history = $project->conversation_history ?? [];
                $alreadySaved = false;
                foreach (array_reverse($history) as $entry) {
                    if ($entry['role'] === 'user') {
                        // Only check recent entries since the last user message
                        break;
                    }
                    if ($entry['role'] === 'assistant' && $entry['content'] === $content) {
                        $alreadySaved = true;
                        break;
                    }
                }

                if (! $alreadySaved) {
                    // Calculate thinking duration from last user message
                    $thinkingDuration = null;
                    $lastUserTimestamp = $project->getLastUserMessageTimestamp();
                    if ($lastUserTimestamp) {
                        $thinkingDuration = (int) $lastUserTimestamp->diffInSeconds(now());
                    }

                    $project->appendToHistory('assistant', $content, null, $thinkingDuration);
                }
            }
        }

        BuilderMessageEvent::dispatch(
            $sessionId,
            $content
        );

        return true;
    }

    protected function dispatchError(string $sessionId, array $data): bool
    {
        $errorMessage = $data['error'] ?? '';

        BuilderErrorEvent::dispatch(
            $sessionId,
            $errorMessage
        );

        $project = $this->resolveProjectBySession($sessionId);
        if ($project) {
            $this->operationLogs->logBuild(
                project: $project,
                event: 'builder_error',
                status: OperationLog::STATUS_ERROR,
                message: $errorMessage ?: 'Builder reported an unknown error.',
                attributes: [
                    'source' => self::class,
                    'identifier' => $sessionId,
                    'context' => [
                        'payload' => $data,
                    ],
                ]
            );
        }

        return true;
    }

    protected function dispatchComplete(string $sessionId, array $data): bool
    {
        // Extract event ID from webhook data (sent by Go builder)
        $eventId = $data['event_id'] ?? null;

        // Also check header as fallback
        if (! $eventId) {
            $eventId = request()->header('X-Webhook-ID');
        }

        // Save the final AI message if included in complete event
        $message = $data['message'] ?? '';
        if (! empty($message)) {
            $project = Project::where('build_session_id', $sessionId)->first();
            if ($project) {
                // Check if this message was already saved (from message event)
                $history = $project->conversation_history ?? [];
                $lastEntry = end($history);
                $alreadySaved = $lastEntry &&
                    $lastEntry['role'] === 'assistant' &&
                    $lastEntry['content'] === $message;

                if (! $alreadySaved) {
                    // Calculate thinking duration from last user message
                    $thinkingDuration = null;
                    $lastUserTimestamp = $project->getLastUserMessageTimestamp();
                    if ($lastUserTimestamp) {
                        $thinkingDuration = (int) $lastUserTimestamp->diffInSeconds(now());
                    }

                    $project->appendToHistory('assistant', $message, null, $thinkingDuration);
                }
            }
        }

        BuilderCompleteEvent::dispatch(
            $sessionId,
            $eventId,
            $data['iterations'] ?? 0,
            $data['tokens_used'] ?? 0,
            $data['files_changed'] ?? false,
            $data['prompt_tokens'] ?? null,
            $data['completion_tokens'] ?? null,
            $data['model'] ?? null,
            $data['build_status'] ?? null,
            $data['build_message'] ?? null,
            $data['build_required'] ?? false,
        );

        // Send user notifications for build status
        $this->notifyBuildStatus($sessionId, $data);

        $project = $this->resolveProjectBySession($sessionId);
        if ($project) {
            $buildStatus = $data['build_status'] ?? null;
            $eventName = $buildStatus ? "builder_complete_{$buildStatus}" : 'builder_complete';
            $status = match ($buildStatus) {
                'failed' => OperationLog::STATUS_ERROR,
                'completed' => OperationLog::STATUS_SUCCESS,
                default => OperationLog::STATUS_INFO,
            };

            $this->operationLogs->logBuild(
                project: $project,
                event: $eventName,
                status: $status,
                message: $data['build_message'] ?? $data['message'] ?? 'Builder session completed.',
                attributes: [
                    'source' => self::class,
                    'identifier' => $eventId ?: $sessionId,
                    'context' => [
                        'session_id' => $sessionId,
                        'event_id' => $eventId,
                        'build_status' => $buildStatus,
                        'iterations' => $data['iterations'] ?? 0,
                        'tokens_used' => $data['tokens_used'] ?? 0,
                        'files_changed' => $data['files_changed'] ?? false,
                    ],
                ]
            );
        }

        return true;
    }

    /**
     * Notify user about build completion or failure.
     */
    protected function notifyBuildStatus(string $sessionId, array $data): void
    {
        $buildStatus = $data['build_status'] ?? null;

        // Only notify on actual build status changes
        if (! in_array($buildStatus, ['completed', 'failed'])) {
            return;
        }

        $project = Project::where('build_session_id', $sessionId)->with('user')->first();

        if (! $project || ! $project->user) {
            return;
        }

        // Broadcast project status update for real-time project list updates
        event(new ProjectStatusUpdatedEvent(
            $project->user->id,
            $project->id,
            $buildStatus,
            $data['build_message'] ?? null
        ));

        // Send notification based on build status
        if ($buildStatus === 'completed') {
            $this->notificationService->notifyBuildComplete($project->user, $project);
        } elseif ($buildStatus === 'failed') {
            $this->notificationService->notifyBuildFailed(
                $project->user,
                $project,
                $data['build_message'] ?? 'Build failed'
            );
        }
    }

    /**
     * Handle summarization_complete event.
     * Stores the compacted history for reuse on future requests.
     */
    protected function handleSummarizationComplete(string $sessionId, array $data): bool
    {
        $compactedHistory = $data['compacted_history'] ?? null;

        // Only store if compacted_history is provided
        if (! empty($compactedHistory) && is_array($compactedHistory)) {
            $project = Project::where('build_session_id', $sessionId)->first();
            if ($project) {
                $project->storeCompactedHistory($compactedHistory);

                \Log::info('Stored compacted history from builder', [
                    'project_id' => $project->id,
                    'session_id' => $sessionId,
                    'turns_compacted' => $data['turns_compacted'] ?? 0,
                    'turns_kept' => $data['turns_kept'] ?? 0,
                    'reduction_percent' => $data['reduction_percent'] ?? 0,
                ]);
            }
        }

        return true;
    }

    protected function resolveProjectBySession(string $sessionId): ?Project
    {
        return Project::where('build_session_id', $sessionId)->first();
    }
}
