<?php

namespace App\Services;

use App\Models\OperationLog;
use App\Models\SystemSetting;
use App\Notifications\OpsCriticalAlertNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;

class OpsAlertService
{
    public function dispatchFromOperationLog(OperationLog $log): void
    {
        $this->dispatch(
            event: $log->event,
            message: $log->message ?? 'Critical operation failure detected.',
            payload: [
                'channel' => $log->channel,
                'status' => $log->status,
                'project_id' => $log->project_id,
                'user_id' => $log->user_id,
                'domain' => $log->domain,
                'identifier' => $log->identifier,
                'source' => $log->source,
                'context' => $log->context ?? [],
                'occurred_at' => $log->occurred_at?->toISOString(),
            ]
        );
    }

    public function dispatch(string $event, string $message, array $payload = []): void
    {
        if (! config('ops.alerts_enabled', true)) {
            return;
        }

        $normalizedPayload = [
            ...$payload,
            'alert_event' => $event,
            'severity' => 'critical',
            'environment' => config('app.env'),
            'app_url' => config('app.url'),
            'triggered_at' => now()->toIso8601String(),
        ];

        if (! $this->acquireDedupeLock($event, $normalizedPayload)) {
            return;
        }

        $alertChannel = (string) config('ops.alert_channel', 'ops_alerts');

        try {
            Log::channel($alertChannel)->critical($message, $normalizedPayload);
        } catch (\Throwable $e) {
            Log::error('Failed to dispatch ops alert log.', [
                'event' => $event,
                'channel' => $alertChannel,
                'error' => $e->getMessage(),
            ]);
        }

        $this->dispatchEmailAlert($message, $normalizedPayload);
    }

    private function acquireDedupeLock(string $event, array $payload): bool
    {
        $ttlSeconds = (int) config('ops.alert_dedupe_seconds', 300);

        if ($ttlSeconds <= 0) {
            return true;
        }

        $signature = implode('|', [
            $event,
            (string) ($payload['channel'] ?? 'unknown'),
            (string) ($payload['project_id'] ?? 'global'),
            (string) ($payload['identifier'] ?? 'none'),
        ]);

        $cacheKey = 'ops-alert:'.sha1($signature);

        return Cache::add($cacheKey, now()->toIso8601String(), now()->addSeconds($ttlSeconds));
    }

    private function dispatchEmailAlert(string $message, array $payload): void
    {
        if (! config('ops.alert_email_enabled', false)) {
            return;
        }

        $recipientSource = config('ops.alert_email_override') ?: SystemSetting::get('admin_notification_email');
        if (! is_string($recipientSource)) {
            return;
        }

        $recipients = array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', $recipientSource)
        )));

        if ($recipients === []) {
            return;
        }

        foreach ($recipients as $recipient) {
            try {
                Notification::route('mail', $recipient)->notify(
                    new OpsCriticalAlertNotification($message, $payload)
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send ops alert email.', [
                    'event' => $payload['alert_event'] ?? 'unknown',
                    'recipient' => $recipient,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
