<?php

namespace App\Services;

use App\Models\OperationLog;
use App\Models\Project;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Facades\Log;
use JsonSerializable;
use Stringable;
use Throwable;

class OperationLogService
{
    public function __construct(
        protected OpsAlertService $opsAlerts
    ) {}

    public function log(
        string $channel,
        string $event,
        string $status = OperationLog::STATUS_INFO,
        ?string $message = null,
        array $attributes = []
    ): OperationLog {
        $log = OperationLog::create([
            'project_id' => $attributes['project_id'] ?? null,
            'user_id' => $attributes['user_id'] ?? null,
            'channel' => $channel,
            'event' => $event,
            'status' => $status,
            'source' => $attributes['source'] ?? null,
            'domain' => $attributes['domain'] ?? null,
            'identifier' => $attributes['identifier'] ?? null,
            'message' => $message,
            'context' => $attributes['context'] ?? null,
            'occurred_at' => $attributes['occurred_at'] ?? now(),
        ]);

        $this->forwardStructuredOpsLog($log);

        if ($this->shouldTriggerCriticalAlert($log)) {
            $this->opsAlerts->dispatchFromOperationLog($log);
        }

        return $log;
    }

    public function logProject(
        Project $project,
        string $channel,
        string $event,
        string $status = OperationLog::STATUS_INFO,
        ?string $message = null,
        array $attributes = []
    ): OperationLog {
        return $this->log(
            channel: $channel,
            event: $event,
            status: $status,
            message: $message,
            attributes: [
                ...$attributes,
                'project_id' => $project->id,
                'user_id' => $attributes['user_id'] ?? $project->user_id,
            ]
        );
    }

    public function logBuild(
        Project $project,
        string $event,
        string $status = OperationLog::STATUS_INFO,
        ?string $message = null,
        array $attributes = []
    ): OperationLog {
        return $this->logProject(
            project: $project,
            channel: OperationLog::CHANNEL_BUILD,
            event: $event,
            status: $status,
            message: $message,
            attributes: $attributes
        );
    }

    public function logPublish(
        Project $project,
        string $event,
        string $status = OperationLog::STATUS_INFO,
        ?string $message = null,
        array $attributes = []
    ): OperationLog {
        return $this->logProject(
            project: $project,
            channel: OperationLog::CHANNEL_PUBLISH,
            event: $event,
            status: $status,
            message: $message,
            attributes: $attributes
        );
    }

    public function logPayment(
        string $event,
        string $status = OperationLog::STATUS_INFO,
        ?string $message = null,
        array $attributes = []
    ): OperationLog {
        return $this->log(
            channel: OperationLog::CHANNEL_PAYMENT,
            event: $event,
            status: $status,
            message: $message,
            attributes: $attributes
        );
    }

    public function logSubscription(
        string $event,
        string $status = OperationLog::STATUS_INFO,
        ?string $message = null,
        array $attributes = []
    ): OperationLog {
        return $this->log(
            channel: OperationLog::CHANNEL_SUBSCRIPTION,
            event: $event,
            status: $status,
            message: $message,
            attributes: $attributes
        );
    }

    public function transform(OperationLog $log): array
    {
        return [
            'id' => $log->id,
            'project_id' => $log->project_id,
            'user_id' => $log->user_id,
            'channel' => $log->channel,
            'event' => $log->event,
            'status' => $log->status,
            'source' => $log->source,
            'domain' => $log->domain,
            'identifier' => $log->identifier,
            'message' => $log->message,
            'context' => $log->context ?? [],
            'occurred_at' => $this->toIso($log->occurred_at),
            'created_at' => $this->toIso($log->created_at),
            'user' => $log->relationLoaded('user') && $log->user
                ? [
                    'id' => $log->user->id,
                    'name' => $log->user->name,
                    'email' => $log->user->email,
                ]
                : null,
            'project' => $log->relationLoaded('project') && $log->project
                ? [
                    'id' => $log->project->id,
                    'name' => $log->project->name,
                ]
                : null,
        ];
    }

    private function toIso(mixed $value): ?string
    {
        if (! $value instanceof CarbonInterface) {
            return null;
        }

        return $value->toISOString();
    }

    private function forwardStructuredOpsLog(OperationLog $log): void
    {
        $channel = (string) config('ops.structured_channel', 'ops_structured');
        $message = $log->message === null
            ? null
            : (string) $this->sanitizeStructuredContext($log->message);

        try {
            Log::channel($channel)->info('operation_log', [
                'operation_log_id' => $log->id,
                'channel' => $log->channel,
                'event' => $log->event,
                'status' => $log->status,
                'project_id' => $log->project_id,
                'user_id' => $log->user_id,
                'source' => $log->source,
                'domain' => $log->domain,
                'identifier' => $log->identifier,
                'message' => $message,
                'context' => $this->sanitizeStructuredContext($log->context ?? []),
                'occurred_at' => $this->toIso($log->occurred_at),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Unable to forward structured operation log.', [
                'operation_log_id' => $log->id,
                'channel' => $channel,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function shouldTriggerCriticalAlert(OperationLog $log): bool
    {
        if ($log->status !== OperationLog::STATUS_ERROR) {
            return false;
        }

        $criticalChannels = config('ops.critical_channels', []);
        if (is_array($criticalChannels) && $criticalChannels !== [] && ! in_array($log->channel, $criticalChannels, true)) {
            return false;
        }

        $criticalEvents = config('ops.critical_events', []);
        if (is_array($criticalEvents) && $criticalEvents !== [] && ! in_array($log->event, $criticalEvents, true)) {
            return false;
        }

        return true;
    }

    private function sanitizeStructuredContext(mixed $value, int $depth = 0): mixed
    {
        $maxDepth = max(1, (int) config('ops.structured_context_max_depth', 5));
        $maxItems = max(1, (int) config('ops.structured_context_max_items', 200));
        $maxString = max(64, (int) config('ops.structured_context_max_string', 2000));

        if ($depth >= $maxDepth) {
            return '[truncated:depth]';
        }

        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return mb_strlen($value) > $maxString
                ? mb_substr($value, 0, $maxString).'...[truncated]'
                : $value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DateTimeInterface::ATOM);
        }

        if ($value instanceof Throwable) {
            return [
                'type' => 'throwable',
                'class' => $value::class,
                'code' => $value->getCode(),
                'message' => $this->sanitizeStructuredContext($value->getMessage(), $depth + 1),
                'file' => $value->getFile(),
                'line' => $value->getLine(),
            ];
        }

        if ($value instanceof Arrayable) {
            return $this->sanitizeStructuredContext($value->toArray(), $depth + 1);
        }

        if ($value instanceof JsonSerializable) {
            return $this->sanitizeStructuredContext($value->jsonSerialize(), $depth + 1);
        }

        if ($value instanceof Stringable) {
            return $this->sanitizeStructuredContext((string) $value, $depth + 1);
        }

        if (is_array($value)) {
            $normalized = [];
            $count = 0;
            $total = count($value);

            foreach ($value as $key => $item) {
                if ($count >= $maxItems) {
                    $normalized['__truncated_items'] = $total - $maxItems;
                    break;
                }

                $normalized[$key] = $this->sanitizeStructuredContext($item, $depth + 1);
                $count++;
            }

            return $normalized;
        }

        if (is_object($value)) {
            return [
                'type' => 'object',
                'class' => $value::class,
            ];
        }

        if (is_resource($value)) {
            return [
                'type' => 'resource',
                'resource_type' => get_resource_type($value),
            ];
        }

        return $this->sanitizeStructuredContext((string) $value, $depth + 1);
    }
}
