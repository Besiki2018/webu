<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OpsCriticalAlertNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private string $message,
        private array $payload
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $event = (string) ($this->payload['alert_event'] ?? 'unknown_event');
        $severity = (string) ($this->payload['severity'] ?? 'critical');
        $environment = (string) ($this->payload['environment'] ?? config('app.env'));
        $projectId = (string) ($this->payload['project_id'] ?? 'n/a');
        $identifier = (string) ($this->payload['identifier'] ?? 'n/a');
        $channel = (string) ($this->payload['channel'] ?? 'n/a');
        $triggeredAt = (string) ($this->payload['triggered_at'] ?? now()->toIso8601String());
        $domain = (string) ($this->payload['domain'] ?? 'n/a');
        $source = (string) ($this->payload['source'] ?? 'n/a');
        $context = json_encode($this->payload['context'] ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return (new MailMessage)
            ->subject("[Webby Ops] {$severity}: {$event}")
            ->line($this->message)
            ->line("Environment: {$environment}")
            ->line("Channel: {$channel}")
            ->line("Project ID: {$projectId}")
            ->line("Identifier: {$identifier}")
            ->line("Domain: {$domain}")
            ->line("Source: {$source}")
            ->line("Triggered At: {$triggeredAt}")
            ->line('Context: '.($context ?: '{}'));
    }
}
