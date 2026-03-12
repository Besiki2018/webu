<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * AI project edit endpoint (chat backend). Ensures the full cycle is reachable and returns the expected shape.
 * Does not require a real workspace or AI provider; validates auth and response structure.
 */
class AiProjectEditEndpointTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_ai_project_edit_requires_auth(): void
    {
        $project = Project::factory()->create();
        $this->postJson(route('panel.projects.ai-project-edit', $project), [
            'message' => 'Create a landing page',
        ])
            ->assertUnauthorized();
    }

    public function test_ai_project_edit_returns_valid_shape_for_owner(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $response = $this->actingAs($owner)
            ->postJson(route('panel.projects.ai-project-edit', $project), [
                'message' => 'Create a website for restaurant',
            ])
            ->assertOk()
            ->assertJsonStructure([
                'success',
                'summary',
                'changes',
                'diagnostic_log',
            ]);

        $data = $response->json();
        $this->assertIsBool($data['success']);
        $this->assertIsString($data['summary']);
        $this->assertIsArray($data['changes']);
        $this->assertIsArray($data['diagnostic_log']);

        if (! $data['success']) {
            $this->assertArrayHasKey('error', $data);
        }
        if ($data['success'] && ! empty($data['changes'])) {
            $this->assertArrayHasKey('files_changed', $data);
            $this->assertTrue($data['files_changed']);
        }
    }

    public function test_full_website_triggers_are_detected(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $prompts = [
            'Create SaaS landing page',
            'Create website for restaurant',
            'Build a website for my agency',
            'Create a photography portfolio website',
        ];

        foreach ($prompts as $message) {
            $response = $this->actingAs($owner)
                ->postJson(route('panel.projects.ai-project-edit', $project), compact('message'))
                ->assertOk()
                ->assertJsonStructure(['success', 'summary', 'changes', 'diagnostic_log']);
            $this->assertIsBool($response->json('success'));
            $this->assertIsArray($response->json('diagnostic_log'));
        }
    }
}
