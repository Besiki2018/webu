<?php

namespace Tests\Feature;

use App\Models\Page;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\DesignDecisionService;
use App\Services\RequirementCollectionService;
use Database\Seeders\TemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RequirementCollectionFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_fallback_returns_question_then_config(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'conversation_history' => [],
            'requirement_config' => null,
        ]);

        $service = app(RequirementCollectionService::class);

        $first = $service->processMessage($project, [], 'I want an online store');
        $this->assertSame('question', $first['type']);
        $this->assertNotEmpty($first['text']);

        $second = $service->processMessage($project, [
            ['role' => 'user', 'content' => 'I want an online store'],
            ['role' => 'assistant', 'content' => $first['text']],
        ], 'Fashion and accessories');
        $this->assertSame('question', $second['type']);

        $third = $service->processMessage($project, [
            ['role' => 'user', 'content' => 'I want an online store'],
            ['role' => 'assistant', 'content' => $first['text']],
            ['role' => 'user', 'content' => 'Fashion'],
            ['role' => 'assistant', 'content' => $second['text']],
        ], 'Luxury minimal style');
        $this->assertSame('question', $third['type']);

        $fourth = $service->processMessage($project, [
            ['role' => 'user', 'content' => 'I want an online store'],
            ['role' => 'assistant', 'content' => $first['text']],
            ['role' => 'user', 'content' => 'Fashion'],
            ['role' => 'assistant', 'content' => $second['text']],
            ['role' => 'user', 'content' => 'Luxury minimal'],
            ['role' => 'assistant', 'content' => $third['text']],
        ], 'Card and bank transfer');
        $this->assertSame('config', $fourth['type']);
        $this->assertArrayHasKey('config', $fourth);
        $config = $fourth['config'];
        $this->assertSame('ecommerce', $config['siteType']);
        $this->assertSame('luxury_minimal', $config['designStyle']);
        $this->assertIsArray($config['payments']);
        $this->assertIsArray($config['modules']);
    }

    public function test_design_decision_produces_blueprint(): void
    {
        $service = app(DesignDecisionService::class);
        $config = [
            'siteType' => 'ecommerce',
            'designStyle' => 'luxury_minimal',
            'modules' => ['products', 'orders', 'checkout'],
            'homepageSections' => ['hero', 'featured_products', 'testimonials'],
        ];

        $blueprint = $service->configToBlueprint($config);

        $this->assertSame('luxury_minimal', $blueprint['theme_preset']);
        $this->assertNotEmpty($blueprint['default_pages']);
        $slugs = array_column($blueprint['default_pages'], 'slug');
        $this->assertContains('home', $slugs);
        $this->assertContains('shop', $slugs);
        $this->assertContains('product', $slugs);
        $this->assertContains('cart', $slugs);
        $this->assertContains('checkout', $slugs);
        $this->assertContains('contact', $slugs);
    }

    /** When templates are seeded, businessType selects the matching template (e.g. jewelry → ecommerce-jewelry). */
    public function test_design_decision_uses_template_when_business_type_set_and_seeded(): void
    {
        $this->seed(TemplateSeeder::class);

        $service = app(DesignDecisionService::class);
        $blueprint = $service->configToBlueprint([
            'siteType' => 'ecommerce',
            'businessType' => 'jewelry',
            'designStyle' => 'luxury_minimal',
        ]);

        $this->assertNotEmpty($blueprint['name']);
        $this->assertTrue(str_contains(strtolower($blueprint['name']), 'jewelry') || str_contains(strtolower($blueprint['name']), 'store'), 'Blueprint name should reflect business type or store');
        $this->assertSame('luxury_minimal', $blueprint['theme_preset']);
        $slugs = array_column($blueprint['default_pages'], 'slug');
        $this->assertContains('home', $slugs);
        $this->assertContains('shop', $slugs);
        $this->assertContains('contact', $slugs);
    }

    public function test_generate_from_config_provisions_site(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create([
            'requirement_config' => [
                'siteType' => 'ecommerce',
                'designStyle' => 'luxury_minimal',
                'modules' => ['products', 'orders', 'checkout'],
            ],
        ]);

        // Establish session and CSRF token (required when run with other tests)
        $this->actingAs($user)->get(route('project.requirements', $project));
        $this->actingAs($user)
            ->postJson(route('panel.projects.generate-from-config', $project), [
                '_token' => session()->token(),
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['site_id']);

        $site = Site::query()->where('project_id', $project->id)->first();
        $this->assertNotNull($site);
        $pages = Page::query()->where('site_id', $site->id)->get();
        $this->assertGreaterThanOrEqual(5, $pages->count());

        // Part 8: generated project CMS is reachable
        $this->actingAs($user)
            ->get(route('project.cms', $project))
            ->assertOk();
    }
}
