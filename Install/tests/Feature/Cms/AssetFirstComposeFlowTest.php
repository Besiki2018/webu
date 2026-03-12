<?php

namespace Tests\Feature\Cms;

use App\Models\Page;
use App\Models\Plan;
use App\Models\Project;
use App\Models\SectionLibrary;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssetFirstComposeFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
        SystemSetting::set('domain_enable_subdomains', true, 'boolean', 'domains');
    }

    public function test_owner_can_compose_draft_and_sync_module_signals_and_bindings(): void
    {
        [$owner, $project, $template] = $this->makeScenario();

        $response = $this->actingAs($owner)
            ->postJson(route('panel.projects.builder.compose-draft', $project), [
                'prompt' => 'Build ecommerce website with products, checkout, delivery, and cart.',
                'reset_existing_content' => true,
            ])
            ->assertOk()
            ->assertJsonPath('composition.template.id', $template->id)
            ->assertJsonPath('composition.retrieval.source', 'internal_catalog');

        $project->refresh();
        $this->assertSame($template->id, (int) $project->template_id);

        $site = $project->site()->firstOrFail();
        $homePage = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', 'home')
            ->firstOrFail();

        $latestRevision = $homePage->revisions()->latest('version')->firstOrFail();
        $sections = $latestRevision->content_json['sections'] ?? [];

        $this->assertNotEmpty($sections);
        $this->assertSame('hero_split_image', data_get($sections, '0.type'));
        $this->assertSame('sections_library', data_get($sections, '0.binding.source'));
        $this->assertSame('hero_split_image', data_get($sections, '0.binding.section_key'));
        $this->assertContains('headline', data_get($sections, '0.binding.editable_fields', []));

        $modules = data_get($site->fresh()->theme_settings, 'modules', []);
        $this->assertTrue((bool) data_get($modules, 'ecommerce', false));
        $this->assertTrue((bool) data_get($modules, 'payments', false));
        $this->assertTrue((bool) data_get($modules, 'shipping', false));

        $this->assertDatabaseHas('operation_logs', [
            'project_id' => $project->id,
            'event' => 'builder_asset_first_composed',
        ]);

        $this->assertNotNull($response->json('composition.composed_at'));
    }

    public function test_compose_to_publish_public_flow_is_stable_end_to_end(): void
    {
        [$owner, $project] = $this->makeScenario();

        $this->actingAs($owner)
            ->postJson(route('panel.projects.builder.compose-draft', $project), [
                'prompt' => 'Ecommerce storefront with featured products and checkout.',
                'reset_existing_content' => true,
            ])
            ->assertOk();

        $site = $project->fresh()->site()->firstOrFail();
        $homePage = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', 'home')
            ->firstOrFail();

        $revisionPayload = [
            'content_json' => [
                'sections' => [
                    [
                        'type' => 'hero_split_image',
                        'props' => [
                            'headline' => 'Flow Updated Headline',
                            'subtitle' => 'Updated in CMS and expected on public endpoint.',
                        ],
                    ],
                    [
                        'type' => 'ecommerce_product_grid',
                        'props' => [
                            'title' => 'Featured Products',
                        ],
                    ],
                ],
            ],
        ];

        $revisionResponse = $this->actingAs($owner)
            ->postJson(route('panel.sites.pages.revisions.store', [
                'site' => $site->id,
                'page' => $homePage->id,
            ]), $revisionPayload)
            ->assertCreated();

        $revisionId = (int) $revisionResponse->json('revision.id');
        $this->assertGreaterThan(0, $revisionId);

        $this->actingAs($owner)
            ->postJson(route('panel.sites.pages.publish', [
                'site' => $site->id,
                'page' => $homePage->id,
            ]), [
                'revision_id' => $revisionId,
            ])
            ->assertOk();

        $subdomain = 'assetflow'.Str::lower(Str::random(6));
        $this->actingAs($owner)
            ->postJson("/project/{$project->id}/publish", [
                'subdomain' => $subdomain,
                'visibility' => 'public',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->getJson(route('public.sites.page', [
            'site' => $site->id,
            'slug' => 'home',
        ]))
            ->assertOk()
            ->assertJsonPath('page.slug', 'home')
            ->assertJsonPath('revision.content_json.sections.0.props.headline', 'Flow Updated Headline')
            ->assertJsonPath('revision.content_json.sections.0.type', 'hero_split_image');
    }

    public function test_non_owner_cannot_compose_draft_for_foreign_project(): void
    {
        [$owner, $project] = $this->makeScenario();
        $intruder = User::factory()->create();

        $this->actingAs($intruder)
            ->postJson(route('panel.projects.builder.compose-draft', $project), [
                'prompt' => 'Try to mutate foreign tenant project',
            ])
            ->assertForbidden();

        $this->assertSame($owner->id, $project->fresh()->user_id);
    }

    /**
     * @return array{0: User, 1: Project, 2: Template}
     */
    private function makeScenario(): array
    {
        $plan = Plan::factory()
            ->withProjectLimit(10)
            ->withSubdomains()
            ->create();

        $owner = User::factory()
            ->withPlan($plan)
            ->create();

        SectionLibrary::query()->create([
            'key' => 'hero_split_image',
            'category' => 'marketing',
            'schema_json' => [
                'properties' => [
                    'headline' => ['type' => 'string'],
                    'subtitle' => ['type' => 'string'],
                ],
                'bindings' => [
                    'headline' => 'content.headline',
                    'subtitle' => 'content.subtitle',
                ],
            ],
            'enabled' => true,
        ]);

        SectionLibrary::query()->create([
            'key' => 'ecommerce_product_grid',
            'category' => 'ecommerce',
            'schema_json' => [
                'properties' => [
                    'title' => ['type' => 'string'],
                    'collection' => ['type' => 'string'],
                ],
                'bindings' => [
                    'title' => 'content.title',
                ],
            ],
            'enabled' => true,
        ]);

        $template = Template::factory()->create([
            'slug' => 'asset-first-ecommerce-pack',
            'name' => 'Asset First Ecommerce Pack',
            'description' => 'Selection-only ecommerce template.',
            'category' => 'ecommerce',
            'is_system' => true,
            'metadata' => [
                'module_flags' => [
                    'ecommerce' => true,
                    'payments' => true,
                    'shipping' => true,
                ],
                'default_pages' => [
                    [
                        'slug' => 'home',
                        'title' => 'Home',
                        'sections' => ['hero_split_image', 'ecommerce_product_grid'],
                    ],
                    [
                        'slug' => 'contact',
                        'title' => 'Contact',
                        'sections' => ['contact_split_form'],
                    ],
                ],
                'default_sections' => [
                    'home' => [
                        ['key' => 'hero_split_image', 'enabled' => true],
                        ['key' => 'ecommerce_product_grid', 'enabled' => true],
                    ],
                    'contact' => [
                        ['key' => 'contact_split_form', 'enabled' => true],
                    ],
                ],
            ],
        ]);

        $project = Project::factory()
            ->for($owner)
            ->create([
                'name' => 'Asset Compose Scenario',
                'initial_prompt' => 'Need ecommerce storefront',
            ]);

        return [$owner, $project, $template];
    }
}
