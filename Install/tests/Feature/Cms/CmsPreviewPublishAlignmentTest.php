<?php

namespace Tests\Feature\Cms;

use App\Models\Page;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsPreviewPublishAlignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_public_page_endpoint_matches_runtime_bridge_home_fallback_for_missing_slug(): void
    {
        [$project, $site] = $this->createPublishedProjectWithSite();
        $this->setHomePublishedContent($site, $this->pageContent('home-fallback'));

        $missingSlug = 'missing-route';
        $publicPageUrl = route('public.sites.page', [
            'site' => $site->id,
            'slug' => $missingSlug,
        ]);

        $this->getJson($publicPageUrl)
            ->assertOk()
            ->assertJsonPath('page.slug', 'home')
            ->assertJsonPath('revision.content_json.sections.0.props.headline', 'home-fallback')
            ->assertJsonPath('meta.requested_slug', $missingSlug)
            ->assertJsonPath('meta.resolved_slug', 'home')
            ->assertJsonPath('meta.slug_fallback', true)
            ->assertJsonPath('meta.draft_preview', false);

        $bridgeUrl = route('app.serve', [
            'project' => $project->id,
            'path' => '__cms/bootstrap',
        ]);

        $this->getJson("{$bridgeUrl}?slug={$missingSlug}")
            ->assertOk()
            ->assertJsonPath('requested_slug', $missingSlug)
            ->assertJsonPath('slug', 'home')
            ->assertJsonPath('revision.content_json.sections.0.props.headline', 'home-fallback');
    }

    public function test_draft_preview_uses_latest_revision_while_runtime_bridge_stays_on_published_revision(): void
    {
        [$project, $site, $owner] = $this->createPublishedProjectWithSiteWithOwner();
        $page = $this->setHomePublishedContent($site, $this->pageContent('published-home'));

        $page->revisions()->create([
            'site_id' => $site->id,
            'version' => ((int) $page->revisions()->max('version')) + 1,
            'content_json' => $this->pageContent('draft-home'),
            'created_by' => $owner->id,
            'published_at' => null,
        ]);

        $publicPageUrl = route('public.sites.page', [
            'site' => $site->id,
            'slug' => 'home',
        ]);

        $this->getJson("{$publicPageUrl}?draft=1")
            ->assertOk()
            ->assertJsonPath('meta.draft_preview', false)
            ->assertJsonPath('revision.content_json.sections.0.props.headline', 'published-home');

        $this->actingAs($owner)
            ->getJson("{$publicPageUrl}?draft=1")
            ->assertOk()
            ->assertJsonPath('meta.draft_preview', true)
            ->assertJsonPath('revision.content_json.sections.0.props.headline', 'draft-home');

        $bridgeUrl = route('app.serve', [
            'project' => $project->id,
            'path' => '__cms/bootstrap',
        ]);

        $this->getJson("{$bridgeUrl}?slug=home")
            ->assertOk()
            ->assertJsonPath('slug', 'home')
            ->assertJsonPath('revision.content_json.sections.0.props.headline', 'published-home');
    }

    public function test_runtime_bridge_exposes_route_params_from_query_for_canonical_bindings(): void
    {
        [$project, $site] = $this->createPublishedProjectWithSite();
        $this->setHomePublishedContent($site, $this->pageContent('product-page'));

        $bridgeUrl = route('app.serve', [
            'project' => $project->id,
            'path' => '__cms/bootstrap',
        ]);

        $this->getJson("{$bridgeUrl}?slug=home&product_slug=premium-dog-snack&product_id=42")
            ->assertOk()
            ->assertJsonPath('route.slug', 'home')
            ->assertJsonPath('route.params.product_slug', 'premium-dog-snack')
            ->assertJsonPath('route.params.slug', 'premium-dog-snack')
            ->assertJsonPath('route.params.product_id', '42');
    }

    public function test_runtime_bridge_normalizes_dynamic_storefront_paths_and_aliases_category_order_params(): void
    {
        [$project, $site, $owner] = $this->createPublishedProjectWithSiteWithOwner();
        $this->setHomePublishedContent($site, $this->pageContent('home-page'));
        $this->createPublishedPageWithContent($site, 'product', 'product-detail-page', $owner);

        $bridgeUrl = route('app.serve', [
            'project' => $project->id,
            'path' => '__cms/bootstrap',
        ]);

        $this->getJson("{$bridgeUrl}?slug=product/premium-dog-snack&product_slug=premium-dog-snack&category_slug=headphones&order_id=1001")
            ->assertOk()
            ->assertJsonPath('requested_slug', 'product')
            ->assertJsonPath('slug', 'product')
            ->assertJsonPath('route.requested_slug', 'product')
            ->assertJsonPath('route.slug', 'product')
            ->assertJsonPath('route.params.product_slug', 'premium-dog-snack')
            ->assertJsonPath('route.params.category_slug', 'headphones')
            ->assertJsonPath('route.params.slug', 'premium-dog-snack')
            ->assertJsonPath('route.params.order_id', '1001')
            ->assertJsonPath('route.params.id', '1001')
            ->assertJsonPath('meta.endpoints.ecommerce_products', route('public.sites.ecommerce.products.index', ['site' => $site->id]))
            ->assertJsonPath(
                'meta.endpoints.ecommerce_checkout',
                str_replace('__cart__', '{cart_id}', route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => '__cart__']))
            );
    }

    /**
     * @return array{0: Project, 1: Site}
     */
    private function createPublishedProjectWithSite(): array
    {
        [$project, $site, ] = $this->createPublishedProjectWithSiteWithOwner();

        return [$project, $site];
    }

    /**
     * @return array{0: Project, 1: Site, 2: User}
     */
    private function createPublishedProjectWithSiteWithOwner(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()
            ->for($owner)
            ->published(Str::lower(Str::random(10)))
            ->create([
                'published_visibility' => 'public',
            ]);

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site, $owner];
    }

    /**
     * @param  array<string, mixed>  $content
     */
    private function setHomePublishedContent(Site $site, array $content): Page
    {
        /** @var Page $page */
        $page = $site->pages()->where('slug', 'home')->firstOrFail();
        $page->update(['status' => 'published']);

        $revision = $page->revisions()
            ->where('site_id', $site->id)
            ->latest('version')
            ->firstOrFail();

        $revision->update([
            'content_json' => $content,
            'published_at' => now(),
        ]);

        return $page->fresh();
    }

    private function createPublishedPageWithContent(Site $site, string $slug, string $headline, User $owner): Page
    {
        /** @var Page $page */
        $page = $site->pages()->create([
            'title' => Str::headline(str_replace('-', ' ', $slug)),
            'slug' => $slug,
            'status' => 'published',
        ]);

        $page->revisions()->create([
            'site_id' => $site->id,
            'version' => 1,
            'content_json' => $this->pageContent($headline),
            'created_by' => $owner->id,
            'published_at' => now(),
        ]);

        return $page->fresh();
    }

    /**
     * @return array<string, mixed>
     */
    private function pageContent(string $headline): array
    {
        return [
            'sections' => [
                [
                    'type' => 'hero',
                    'props' => [
                        'headline' => $headline,
                    ],
                ],
            ],
        ];
    }
}
