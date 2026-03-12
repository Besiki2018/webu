<?php

namespace Tests\Feature\Cms;

use App\Models\Plan;
use App\Models\Project;
use App\Models\SectionLibrary;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InternalAssetRetrievalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
        SystemSetting::set('domain_enable_subdomains', true, 'boolean', 'domains');
    }

    public function test_project_owner_can_preview_internal_retrieval_context(): void
    {
        $plan = Plan::factory()->create();
        $owner = User::factory()->withPlan($plan)->create();
        $project = Project::factory()->for($owner)->create([
            'name' => 'Restaurant Starter',
        ]);

        Template::factory()->create([
            'name' => 'Restaurant Pro',
            'slug' => 'restaurant-pro',
            'category' => 'restaurant',
            'metadata' => [
                'module_flags' => ['ecommerce' => true],
            ],
        ]);

        SectionLibrary::query()->create([
            'key' => 'menu-grid',
            'category' => 'restaurant',
            'schema_json' => ['title' => 'Menu Grid'],
            'enabled' => true,
        ]);

        $this->actingAs($owner)
            ->getJson(route('panel.projects.builder.retrieval-preview', [
                'project' => $project,
                'prompt' => 'Build a restaurant page with menu and delivery',
            ]))
            ->assertOk()
            ->assertJsonPath('project_id', $project->id)
            ->assertJsonPath('retrieval_context.source', 'internal_catalog')
            ->assertJsonPath('retrieval_context.catalog.sections.0.key', 'menu-grid');
    }

    public function test_non_owner_cannot_preview_internal_retrieval_context(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $project = Project::factory()->for($owner)->create();

        $this->actingAs($intruder)
            ->getJson(route('panel.projects.builder.retrieval-preview', $project))
            ->assertForbidden();
    }
}

