<?php

namespace Tests\Feature\Cms;

use App\Models\Media;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_panel_pages_index_is_forbidden_for_other_tenant_user(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        [, $ownerSite] = $this->createDraftProjectWithSite($owner);

        $this->actingAs($intruder)
            ->getJson(route('panel.sites.pages.index', ['site' => $ownerSite->id]))
            ->assertForbidden();
    }

    public function test_panel_page_show_returns_404_when_page_belongs_to_another_site(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        [, $siteA] = $this->createDraftProjectWithSite($ownerA);
        [, $siteB] = $this->createDraftProjectWithSite($ownerB);

        $foreignPage = $siteB->pages()->where('slug', 'home')->firstOrFail();

        $this->actingAs($ownerA)
            ->getJson(route('panel.sites.pages.show', [
                'site' => $siteA->id,
                'page' => $foreignPage->id,
            ]))
            ->assertNotFound();
    }

    public function test_panel_media_delete_returns_404_for_cross_site_resource(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        [, $siteA] = $this->createDraftProjectWithSite($ownerA);
        [, $siteB] = $this->createDraftProjectWithSite($ownerB);

        $media = Media::create([
            'site_id' => $siteB->id,
            'path' => "site-media/{$siteB->id}/tenant-b-logo.png",
            'mime' => 'image/png',
            'size' => 512,
            'meta_json' => [],
        ]);

        $this->actingAs($ownerA)
            ->deleteJson(route('panel.sites.media.destroy', [
                'site' => $siteA->id,
                'media' => $media->id,
            ]))
            ->assertNotFound();
    }

    public function test_public_cms_endpoints_hide_private_site_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();

        [, $privateSite] = $this->createPublishedProjectWithSite($owner, 'private');

        $resolveUrl = route('public.sites.resolve', ['domain' => $privateSite->subdomain]);
        $settingsUrl = route('public.sites.settings', ['site' => $privateSite->id]);

        $this->getJson($resolveUrl)->assertNotFound();
        $this->getJson($settingsUrl)->assertNotFound();

        $this->actingAs($otherUser)->getJson($resolveUrl)->assertNotFound();
        $this->actingAs($otherUser)->getJson($settingsUrl)->assertNotFound();

        $this->actingAs($owner)
            ->getJson($resolveUrl)
            ->assertOk()
            ->assertJsonPath('site_id', $privateSite->id);

        $this->actingAs($owner)
            ->getJson($settingsUrl)
            ->assertOk()
            ->assertJsonPath('site_id', $privateSite->id);
    }

    public function test_app_preview_cms_bridge_blocks_private_project_for_non_owner(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        [$privateProject, $site] = $this->createPublishedProjectWithSite($owner, 'private');

        $bridgeUrl = route('app.serve', [
            'project' => $privateProject->id,
            'path' => '__cms/bootstrap',
        ]).'?slug=home';

        $this->getJson($bridgeUrl)->assertNotFound();
        $this->actingAs($intruder)->getJson($bridgeUrl)->assertNotFound();

        $this->actingAs($owner)
            ->getJson($bridgeUrl)
            ->assertOk()
            ->assertJsonPath('project_id', $privateProject->id)
            ->assertJsonPath('site_id', $site->id)
            ->assertJsonPath('slug', 'home');
    }

    public function test_app_preview_cms_bridge_payload_isolated_per_project(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        [$projectA, $siteA] = $this->createPublishedProjectWithSite($ownerA, 'public');
        [$projectB, $siteB] = $this->createPublishedProjectWithSite($ownerB, 'public');

        $this->updateHomePagePayload($siteA, 'Tenant A Home', 'Tenant A Headline');
        $this->updateHomePagePayload($siteB, 'Tenant B Home', 'Tenant B Headline');

        $response = $this->getJson(route('app.serve', [
            'project' => $projectA->id,
            'path' => '__cms/bootstrap',
        ]).'?slug=home');

        $response->assertOk()
            ->assertJsonPath('project_id', $projectA->id)
            ->assertJsonPath('site_id', $siteA->id)
            ->assertJsonPath('page.title', 'Tenant A Home')
            ->assertJsonPath('revision.content_json.sections.0.props.headline', 'Tenant A Headline');

        $this->assertNotSame($siteB->id, (string) $response->json('site_id'));
        $this->assertNotSame('Tenant B Home', $response->json('page.title'));
        $this->assertNotSame('Tenant B Headline', $response->json('revision.content_json.sections.0.props.headline'));
    }

    private function createDraftProjectWithSite(User $owner): array
    {
        $project = Project::factory()->for($owner)->create();
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }

    private function createPublishedProjectWithSite(User $owner, string $visibility): array
    {
        $factory = Project::factory()->for($owner);
        $subdomain = strtolower(Str::random(10));

        if ($visibility === 'private') {
            $factory = $factory->privatePublished($subdomain);
        } else {
            $factory = $factory->published($subdomain);
        }

        $project = $factory->create();
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }

    private function updateHomePagePayload(Site $site, string $title, string $headline): void
    {
        $page = $site->pages()->where('slug', 'home')->firstOrFail();
        $page->update([
            'title' => $title,
            'status' => 'published',
        ]);

        $revision = $page->revisions()
            ->where('site_id', $site->id)
            ->latest('version')
            ->firstOrFail();

        $revision->update([
            'published_at' => now(),
            'content_json' => [
                'sections' => [
                    [
                        'type' => 'hero',
                        'props' => [
                            'headline' => $headline,
                        ],
                    ],
                ],
            ],
        ]);
    }
}
