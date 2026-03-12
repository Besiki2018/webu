<?php

namespace Tests\Feature\Cms;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AiInterpretCommandService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiInterpretCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_instruction_is_required(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-interpret-command', $project), [
                'instruction' => '',
            ])
            ->assertStatus(422);
    }

    public function test_page_context_is_optional(): void
    {
        $this->mock(AiInterpretCommandService::class, function ($mock) {
            $mock->shouldReceive('interpret')
                ->once()
                ->with('Add pricing section', \Mockery::on(function ($ctx) {
                    return is_array($ctx)
                        && isset($ctx['sections'])
                        && $ctx['sections'] === [];
                }))
                ->andReturn([
                    'success' => true,
                    'change_set' => [
                        'operations' => [
                            ['op' => 'insertSection', 'sectionType' => 'pricing'],
                        ],
                        'summary' => ['Added pricing section'],
                    ],
                ]);
        });

        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-interpret-command', $project), [
                'instruction' => 'Add pricing section',
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('change_set.operations.0.op', 'insertSection')
            ->assertJsonPath('change_set.summary.0', 'Added pricing section');
    }

    public function test_page_context_recent_edits_is_passed_to_interpret(): void
    {
        $this->mock(AiInterpretCommandService::class, function ($mock) {
            $mock->shouldReceive('interpret')
                ->once()
                ->with('Make the title shorter', \Mockery::on(function ($ctx) {
                    return is_array($ctx)
                        && isset($ctx['recent_edits'])
                        && $ctx['recent_edits'] === 'Section updated: title: Old → New';
                }))
                ->andReturn([
                    'success' => true,
                    'change_set' => [
                        'operations' => [
                            ['op' => 'updateSection', 'sectionId' => 'hero-1', 'patch' => ['title' => 'Shorter']],
                        ],
                        'summary' => ['Shortened hero title'],
                    ],
                ]);
        });

        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-interpret-command', $project), [
                'instruction' => 'Make the title shorter',
                'page_context' => [
                    'recent_edits' => 'Section updated: title: Old → New',
                ],
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('change_set.operations.0.op', 'updateSection');
    }

    public function test_returns_error_when_interpret_fails(): void
    {
        $this->mock(AiInterpretCommandService::class, function ($mock) {
            $mock->shouldReceive('interpret')
                ->once()
                ->andReturn([
                    'success' => false,
                    'error' => 'AI did not return a response.',
                ]);
        });

        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-interpret-command', $project), [
                'instruction' => 'Do something',
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'AI did not return a response.');
    }

    public function test_non_owner_cannot_call_interpret_command(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($other)
            ->postJson(route('panel.projects.ai-interpret-command', $project), [
                'instruction' => 'Add hero',
            ])
            ->assertForbidden();
    }

    public function test_guest_cannot_call_interpret_command(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->postJson(route('panel.projects.ai-interpret-command', $project), [
            'instruction' => 'Add hero',
        ])
            ->assertUnauthorized();
    }
}
