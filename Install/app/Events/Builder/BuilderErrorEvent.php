<?php

namespace App\Events\Builder;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BuilderErrorEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $sessionId,
        public string $error
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('session.'.$this->sessionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'error';
    }

    public function broadcastWith(): array
    {
        return [
            'error' => $this->error,
        ];
    }

    public function broadcastWhen(): bool
    {
        // Keep Pusher-direct behavior unchanged; enable Laravel broadcasting for Reverb rollout.
        return config('broadcasting.default') === 'reverb';
    }
}
