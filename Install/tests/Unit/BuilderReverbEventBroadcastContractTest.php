<?php

namespace Tests\Unit;

use App\Events\Builder\BuilderActionEvent;
use App\Events\Builder\BuilderCompleteEvent;
use App\Events\Builder\BuilderErrorEvent;
use App\Events\Builder\BuilderMessageEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Tests\TestCase;

class BuilderReverbEventBroadcastContractTest extends TestCase
{
    public function test_builder_action_event_broadcast_contract_matches_frontend_shape(): void
    {
        $event = new BuilderActionEvent('session-123', 'create', 'index.blade.php', 'created file', 'file_ops');

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertSame('action', $event->broadcastAs());
        $this->assertSame([
            'action' => 'create',
            'target' => 'index.blade.php',
            'details' => 'created file',
            'category' => 'file_ops',
        ], $event->broadcastWith());

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('session.session-123', $channels[0]->name);
    }

    public function test_builder_message_event_broadcast_contract_matches_frontend_shape(): void
    {
        $event = new BuilderMessageEvent('session-123', 'Hello world');

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertSame('message', $event->broadcastAs());
        $this->assertSame([
            'content' => 'Hello world',
        ], $event->broadcastWith());

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('session.session-123', $channels[0]->name);
    }

    public function test_builder_error_event_broadcast_contract_matches_frontend_shape(): void
    {
        $event = new BuilderErrorEvent('session-123', 'build failed');

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertSame('error', $event->broadcastAs());
        $this->assertSame([
            'error' => 'build failed',
        ], $event->broadcastWith());

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('session.session-123', $channels[0]->name);
    }

    public function test_builder_complete_event_broadcast_contract_matches_frontend_shape(): void
    {
        $event = new BuilderCompleteEvent(
            'session-123',
            'evt-1',
            3,
            1500,
            true,
            600,
            900,
            'gpt-5',
            'completed',
            'Build finished',
            true,
        );

        $this->assertInstanceOf(ShouldBroadcast::class, $event);
        $this->assertSame('complete', $event->broadcastAs());
        $this->assertSame([
            'iterations' => 3,
            'tokens_used' => 1500,
            'files_changed' => true,
            'prompt_tokens' => 600,
            'completion_tokens' => 900,
            'model' => 'gpt-5',
            'build_status' => 'completed',
            'build_message' => 'Build finished',
            'build_required' => true,
            'message' => 'Build finished',
        ], $event->broadcastWith());

        $channels = $event->broadcastOn();
        $this->assertCount(1, $channels);
        $this->assertInstanceOf(Channel::class, $channels[0]);
        $this->assertSame('session.session-123', $channels[0]->name);
    }

    public function test_builder_events_broadcast_only_when_reverb_is_default_driver(): void
    {
        config(['broadcasting.default' => 'null']);
        $this->assertFalse((new BuilderActionEvent('session-1', 'a', 'b', 'c', 'd'))->broadcastWhen());
        $this->assertFalse((new BuilderMessageEvent('session-1', 'm'))->broadcastWhen());
        $this->assertFalse((new BuilderErrorEvent('session-1', 'e'))->broadcastWhen());
        $this->assertFalse((new BuilderCompleteEvent('session-1', null, 0, 0, false))->broadcastWhen());

        config(['broadcasting.default' => 'reverb']);
        $this->assertTrue((new BuilderActionEvent('session-1', 'a', 'b', 'c', 'd'))->broadcastWhen());
        $this->assertTrue((new BuilderMessageEvent('session-1', 'm'))->broadcastWhen());
        $this->assertTrue((new BuilderErrorEvent('session-1', 'e'))->broadcastWhen());
        $this->assertTrue((new BuilderCompleteEvent('session-1', null, 0, 0, false))->broadcastWhen());
    }
}

