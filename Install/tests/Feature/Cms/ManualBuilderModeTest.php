<?php

namespace Tests\Feature\Cms;

use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ManualBuilderModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_manual_builder_templates_endpoint_returns_plan_available_templates(): void
    {
        [$owner, $site] = $this->makeOwnerAndSite();

        Template::factory()->create([
            'slug' => 'ecommerce',
            'name' => 'Manual System Template',
            'is_system' => true,
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => ['hero']],
                ],
            ],
        ]);

        $response = $this->actingAs($owner)
            ->getJson(route('panel.sites.builder.templates', ['site' => $site->id]));

        $response->assertOk()
            ->assertJsonPath('site_id', $site->id)
            ->assertJsonPath('project_id', $site->project_id);

        $this->assertNotEmpty($response->json('templates'));
    }

    public function test_manual_builder_can_apply_template_and_reset_pages(): void
    {
        [$owner, $site] = $this->makeOwnerAndSite();

        $template = Template::factory()->create([
            'slug' => 'ecommerce',
            'name' => 'Manual Apply Template',
            'is_system' => true,
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => ['hero', 'services']],
                    ['slug' => 'services', 'title' => 'Services', 'sections' => ['services', 'contact']],
                ],
                'default_sections' => [
                    'home' => [['key' => 'hero'], ['key' => 'services']],
                    'services' => [['key' => 'services'], ['key' => 'contact']],
                ],
            ],
        ]);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.builder.templates.apply', ['site' => $site->id]), [
                'template_id' => $template->id,
                'theme_preset' => 'forest',
                'reset_existing_content' => true,
            ])
            ->assertOk()
            ->assertJsonPath('template_id', $template->id);

        $this->assertDatabaseHas('pages', [
            'site_id' => $site->id,
            'slug' => 'services',
        ]);

        $project = Project::query()->findOrFail($site->project_id);
        $this->assertSame($template->id, (int) $project->template_id);
        $this->assertSame('forest', $project->theme_preset);
    }

    public function test_manual_builder_preserves_template_section_props_when_provisioning_pages(): void
    {
        [$owner, $site] = $this->makeOwnerAndSite();

        $template = Template::factory()->create([
            'slug' => 'ecommerce',
            'name' => 'Manual Props Template',
            'is_system' => true,
            'metadata' => [
                'default_pages' => [
                    ['slug' => 'home', 'title' => 'Home', 'sections' => ['hero_centered_gradient']],
                ],
                'default_sections' => [
                    'home' => [[
                        'key' => 'hero_centered_gradient',
                        'props' => [
                            'headline' => 'Business-ready hero',
                            'subtitle' => 'Mobile-first and conversion-focused.',
                            'primary_cta' => [
                                'label' => 'Book a call',
                                'url' => '/contact',
                            ],
                        ],
                    ]],
                ],
            ],
        ]);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.builder.templates.apply', ['site' => $site->id]), [
                'template_id' => $template->id,
                'reset_existing_content' => true,
            ])
            ->assertOk();

        $home = $site->fresh()->pages()->where('slug', 'home')->firstOrFail();
        $revision = $home->revisions()->latest('version')->firstOrFail();
        $sections = $revision->content_json['sections'] ?? [];

        $this->assertIsArray($sections);
        $this->assertSame('hero_centered_gradient', $sections[0]['type'] ?? null);
        $this->assertSame('Business-ready hero', $sections[0]['props']['headline'] ?? null);
        $this->assertSame('Mobile-first and conversion-focused.', $sections[0]['props']['subtitle'] ?? null);
        $this->assertSame('Book a call', $sections[0]['props']['primary_cta']['label'] ?? null);
        $this->assertSame('/contact', $sections[0]['props']['primary_cta']['url'] ?? null);
    }

    public function test_manual_builder_sections_and_styles_endpoints_mutate_site_state(): void
    {
        [$owner, $site] = $this->makeOwnerAndSite();
        $home = $site->pages()->where('slug', 'home')->firstOrFail();

        $this->actingAs($owner)
            ->postJson(route('panel.sites.builder.sections.mutate', ['site' => $site->id]), [
                'action' => 'add',
                'page_id' => $home->id,
                'section' => [
                    'type' => 'faq',
                    'props' => [
                        'title' => 'FAQ',
                        'items' => [],
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('revision.content_json.sections.2.type', 'faq');

        $this->actingAs($owner)
            ->putJson(route('panel.sites.builder.styles.update', ['site' => $site->id]), [
                'theme_preset' => 'ocean',
                'theme_settings' => [
                    'colors' => [
                        'accent' => '#0088ff',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('theme_settings.preset', 'ocean')
            ->assertJsonPath('theme_settings.colors.accent', '#0088ff');

        $project = Project::query()->findOrFail($site->project_id);
        $this->assertSame('ocean', $project->theme_preset);
    }

    private function makeOwnerAndSite(): array
    {
        $plan = Plan::factory()->withProjectLimit(10)->create();
        $owner = User::factory()->withPlan($plan)->create();

        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$owner, $site];
    }
}
