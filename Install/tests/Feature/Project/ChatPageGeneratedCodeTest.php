<?php

namespace Tests\Feature\Project;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use App\Services\SiteProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChatPageGeneratedCodeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_chat_page_exposes_locale_aware_generated_pages_for_code_generation(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $homePage = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', 'home')
            ->first();

        if (! $homePage) {
            $homePage = Page::query()->create([
                'site_id' => $site->id,
                'title' => 'Home',
                'slug' => 'home',
                'status' => 'draft',
            ]);
        }

        $shopPage = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', 'shop')
            ->first();

        if (! $shopPage) {
            $shopPage = Page::query()->create([
                'site_id' => $site->id,
                'title' => 'Shop',
                'slug' => 'shop',
                'status' => 'draft',
            ]);
        }

        $homeRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $homePage->id)
            ->latest('version')
            ->first();

        $shopRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $shopPage->id)
            ->latest('version')
            ->first();

        $homeContent = [
            'locales' => [
                'ka' => [
                    'sections' => [
                        [
                            'type' => 'hero_split_image',
                            'props' => [
                                'title' => 'Critical hero title',
                                'subtitle' => 'Generated from CMS',
                                'ctaText' => 'Start now',
                                'items' => [
                                    ['label' => 'Fast checkout', 'value' => '24/7'],
                                    ['label' => 'Local delivery', 'value' => 'Same day'],
                                ],
                            ],
                        ],
                    ],
                    'editor_mode' => 'builder',
                    'text_editor_html' => '',
                ],
            ],
        ];

        $shopContent = [
            'sections' => [
                [
                    'type' => 'webu_ecom_product_grid_01',
                    'props' => [
                        'title' => 'Featured products',
                        'products_per_page' => 8,
                        'show_filters' => true,
                    ],
                ],
            ],
        ];

        if ($homeRevision) {
            $homeRevision->update(['content_json' => $homeContent]);
        } else {
            PageRevision::query()->create([
                'site_id' => $site->id,
                'page_id' => $homePage->id,
                'version' => 1,
                'content_json' => $homeContent,
            ]);
        }

        if ($shopRevision) {
            $shopRevision->update(['content_json' => $shopContent]);
        } else {
            PageRevision::query()->create([
                'site_id' => $site->id,
                'page_id' => $shopPage->id,
                'version' => 1,
                'content_json' => $shopContent,
            ]);
        }

        $response = $this->actingAs($user)->get(route('chat', $project));
        $response->assertOk();
        $response->assertInertia(fn (Assert $inertia) => $inertia
            ->component('Chat')
            ->where('project.cms_preview_url', fn (?string $url) => is_string($url) && str_contains($url, '/themes/default?'))
            ->where('generatedPage.slug', 'home')
            ->where('generatedPage.revision_source', 'latest')
            ->where('generatedPage.sections.0.type', 'hero_split_image')
            ->where('generatedPage.sections.0.localId', 'section-0')
            ->where('generatedPage.sections.0.props.title', 'Critical hero title')
            ->where('generatedPage.sections.0.props.items.0.label', 'Fast checkout')
        );
        $pageData = $response->original && method_exists($response->original, 'getData') ? $response->original->getData() : ($response->viewData('page') ?? []);
        $props = is_array($pageData) && isset($pageData['props']) ? $pageData['props'] : (isset($pageData['page']['props']) ? $pageData['page']['props'] : []);
        $cmsPreviewUrl = data_get($props, 'project.cms_preview_url');
        $this->assertIsString($cmsPreviewUrl);
        $this->assertStringContainsString('/themes/default?', $cmsPreviewUrl);
        $generatedPages = $props['generatedPages'] ?? [];
        $slugs = array_column($generatedPages, 'slug');
        $this->assertContains('home', $slugs, 'generatedPages should include home');
        $this->assertContains('shop', $slugs, 'generatedPages should include shop');
        $homePage = collect($generatedPages)->firstWhere('slug', 'home');
        $shopPage = collect($generatedPages)->firstWhere('slug', 'shop');
        $this->assertNotNull($homePage);
        $this->assertNotNull($shopPage);
        $this->assertSame('section-0', $homePage['sections'][0]['localId'] ?? null);
        $this->assertSame('Critical hero title', $homePage['sections'][0]['props']['title'] ?? null);
        $this->assertGreaterThanOrEqual(1, count($shopPage['sections'] ?? []));
        $this->assertSame('section-0', $shopPage['sections'][0]['localId'] ?? null);
        if (isset($shopPage['sections'][0]['props']['products_per_page'])) {
            $this->assertSame(8, $shopPage['sections'][0]['props']['products_per_page']);
        }
    }

    public function test_chat_page_uses_ecommerce_preview_when_home_page_is_ecommerce(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $homePage = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', 'home')
            ->first();

        if (! $homePage) {
            $homePage = Page::query()->create([
                'site_id' => $site->id,
                'title' => 'Home',
                'slug' => 'home',
                'status' => 'draft',
            ]);
        }

        PageRevision::query()->updateOrCreate(
            [
                'site_id' => $site->id,
                'page_id' => $homePage->id,
                'version' => 1,
            ],
            [
                'content_json' => [
                    'sections' => [
                        [
                            'type' => 'webu_ecom_product_grid_01',
                            'props' => [
                                'title' => 'Featured products',
                            ],
                        ],
                    ],
                ],
            ]
        );

        $response = $this->actingAs($user)->get(route('chat', $project));
        $response->assertOk();

        $pageData = $response->original && method_exists($response->original, 'getData') ? $response->original->getData() : ($response->viewData('page') ?? []);
        $props = is_array($pageData) && isset($pageData['props']) ? $pageData['props'] : (isset($pageData['page']['props']) ? $pageData['page']['props'] : []);
        $cmsPreviewUrl = data_get($props, 'project.cms_preview_url');

        $this->assertIsString($cmsPreviewUrl);
        $this->assertStringContainsString('/themes/ecommerce?', $cmsPreviewUrl);
    }

    public function test_chat_page_uses_default_preview_template_for_generated_non_ecommerce_projects(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->for($user)->create();

        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $homePage = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', 'home')
            ->first();

        if (! $homePage) {
            $homePage = Page::query()->create([
                'site_id' => $site->id,
                'title' => 'Home',
                'slug' => 'home',
                'status' => 'draft',
            ]);
        }

        PageRevision::query()->updateOrCreate(
            [
                'site_id' => $site->id,
                'page_id' => $homePage->id,
                'version' => 1,
            ],
            [
                'content_json' => [
                    'sections' => [
                        [
                            'type' => 'hero_split_image',
                            'props' => [
                                'title' => 'Calm studio',
                            ],
                        ],
                    ],
                ],
            ]
        );

        $response = $this->actingAs($user)->get(route('chat', $project));
        $response->assertOk();

        $pageData = $response->original && method_exists($response->original, 'getData') ? $response->original->getData() : ($response->viewData('page') ?? []);
        $props = is_array($pageData) && isset($pageData['props']) ? $pageData['props'] : (isset($pageData['page']['props']) ? $pageData['page']['props'] : []);
        $cmsPreviewUrl = data_get($props, 'project.cms_preview_url');

        $this->assertIsString($cmsPreviewUrl);
        $this->assertStringContainsString('/themes/default?', $cmsPreviewUrl);
        $this->assertStringNotContainsString('/template-demos/ecommerce', $cmsPreviewUrl);
    }

    public function test_chat_page_prefers_generated_preview_template_over_project_template_slug(): void
    {
        $user = User::factory()->create();
        $template = Template::factory()->create([
            'slug' => 'ecommerce',
            'name' => 'Ecommerce',
        ]);
        $project = Project::factory()->for($user)->for($template)->create();

        $site = app(SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $homePage = Page::query()
            ->where('site_id', $site->id)
            ->where('slug', 'home')
            ->first();

        if (! $homePage) {
            $homePage = Page::query()->create([
                'site_id' => $site->id,
                'title' => 'Home',
                'slug' => 'home',
                'status' => 'draft',
            ]);
        }

        PageRevision::query()->updateOrCreate(
            [
                'site_id' => $site->id,
                'page_id' => $homePage->id,
                'version' => 1,
            ],
            [
                'content_json' => [
                    'sections' => [
                        [
                            'type' => 'hero',
                            'props' => [
                                'title' => 'Studio landing',
                            ],
                        ],
                        [
                            'type' => 'features',
                            'props' => [
                                'title' => 'Why choose us',
                            ],
                        ],
                    ],
                ],
            ]
        );

        $response = $this->actingAs($user)->get(route('chat', $project));
        $response->assertOk();

        $pageData = $response->original && method_exists($response->original, 'getData') ? $response->original->getData() : ($response->viewData('page') ?? []);
        $props = is_array($pageData) && isset($pageData['props']) ? $pageData['props'] : (isset($pageData['page']['props']) ? $pageData['page']['props'] : []);
        $cmsPreviewUrl = data_get($props, 'project.cms_preview_url');

        $this->assertIsString($cmsPreviewUrl);
        $this->assertStringContainsString('/themes/default?', $cmsPreviewUrl);
        $this->assertStringNotContainsString('/template-demos/ecommerce', $cmsPreviewUrl);
    }
}
