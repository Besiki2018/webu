<?php

namespace Tests\Feature\Ecommerce;

use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PART 7 — AI Chat Editing: chat-apply-patch applies theme_preset or add_section.
 */
class ChatApplyPatchTest extends TestCase
{
    use RefreshDatabase;

    public function test_apply_patch_theme_preset_make_it_darker(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'theme_preset' => 'luxury_minimal',
        ]);

        $response = $this->actingAs($user)->postJson(
            route('panel.projects.chat-apply-patch', ['project' => $project->id]),
            ['message' => 'Make the design darker']
        );

        $response->assertOk();
        $response->assertJsonPath('applied', true);
        $response->assertJsonPath('type', 'theme_preset');
        $response->assertJsonPath('patch.theme_preset', 'dark_modern');

        $project->refresh();
        $this->assertSame('dark_modern', $project->theme_preset);
    }

    public function test_apply_patch_returns_not_applied_for_unknown_intent(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson(
            route('panel.projects.chat-apply-patch', ['project' => $project->id]),
            ['message' => 'What is the weather today?']
        );

        $response->assertOk();
        $response->assertJsonPath('applied', false);
    }

    public function test_apply_patch_theme_updates_site_theme_settings_when_site_exists(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create(['theme_preset' => 'default']);
        // ProjectObserver already provisions a site; use it and set preset for assertion
        $site = $project->site()->firstOrFail();
        $site->update(['theme_settings' => array_merge($site->theme_settings ?? [], ['preset' => 'default'])]);

        $response = $this->actingAs($user)->postJson(
            route('panel.projects.chat-apply-patch', ['project' => $project->id]),
            ['message' => 'Make it lighter']
        );

        $response->assertOk();
        $response->assertJsonPath('applied', true);
        $response->assertJsonPath('type', 'theme_preset');

        $site->refresh();
        $this->assertSame('luxury_minimal', $site->theme_settings['preset'] ?? null);
    }

    public function test_apply_patch_requires_authenticated_user(): void
    {
        $project = Project::factory()->create();

        $response = $this->postJson(
            route('panel.projects.chat-apply-patch', ['project' => $project->id]),
            ['message' => 'Make it darker']
        );

        $response->assertUnauthorized();
    }

    public function test_apply_patch_requires_message(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson(
            route('panel.projects.chat-apply-patch', ['project' => $project->id]),
            []
        );

        $response->assertUnprocessable();
    }

    /** Instruction → patch flow: propose returns summary without applying. */
    public function test_propose_patch_returns_proposed_and_summary_for_theme_intent(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson(
            route('panel.projects.chat-propose-patch', ['project' => $project->id]),
            ['message' => 'Make it darker']
        );

        $response->assertOk();
        $response->assertJsonPath('proposed', true);
        $response->assertJsonPath('type', 'theme_preset');
        $response->assertJsonPath('patch.theme_preset', 'dark_modern');
        $response->assertJsonStructure(['summary']);

        $project->refresh();
        $this->assertNotSame('dark_modern', $project->theme_preset ?? '');
    }

    public function test_propose_patch_returns_not_proposed_for_unknown_intent(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $response = $this->actingAs($user)->postJson(
            route('panel.projects.chat-propose-patch', ['project' => $project->id]),
            ['message' => 'What is the weather?']
        );

        $response->assertOk();
        $response->assertJsonPath('proposed', false);
    }
}
