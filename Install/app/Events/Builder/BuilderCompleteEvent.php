<?php

namespace App\Events\Builder;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BuilderCompleteEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $sessionId,
        public ?string $eventId,
        public int $iterations,
        public int $tokensUsed,
        public bool $filesChanged,
        public ?int $promptTokens = null,
        public ?int $completionTokens = null,
        public ?string $model = null,
        public ?string $buildStatus = null,
        public ?string $buildMessage = null,
        public bool $buildRequired = false,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('session.'.$this->sessionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'complete';
    }

    public function broadcastWith(): array
    {
        return [
            'iterations' => $this->iterations,
            'tokens_used' => $this->tokensUsed,
            'files_changed' => $this->filesChanged,
            'prompt_tokens' => $this->promptTokens,
            'completion_tokens' => $this->completionTokens,
            'model' => $this->model,
            'build_status' => $this->buildStatus,
            'build_message' => $this->buildMessage,
            'build_required' => $this->buildRequired,
            'message' => $this->buildMessage,
        ];
    }

    public function broadcastWhen(): bool
    {
        // Keep Pusher-direct behavior unchanged; enable Laravel broadcasting for Reverb rollout.
        return config('broadcasting.default') === 'reverb';
    }
}
