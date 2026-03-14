<?php

namespace App\Events\Builder;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BuilderToolResultEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public string $sessionId,
        public string $id,
        public string $tool,
        public bool $success,
        public string $output,
        public int $durationMs = 0,
        public int $iteration = 0,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('session.'.$this->sessionId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'tool_result';
    }

    public function broadcastWith(): array
    {
        return [
            'session_id' => $this->sessionId,
            'build_id' => $this->sessionId,
            'id' => $this->id,
            'tool' => $this->tool,
            'success' => $this->success,
            'output' => $this->output,
            'duration_ms' => $this->durationMs,
            'iteration' => $this->iteration,
        ];
    }
}
