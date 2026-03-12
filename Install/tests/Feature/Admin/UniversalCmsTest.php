<?php

namespace Tests\Feature\Admin;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageSection;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsitePage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UniversalCmsTest extends TestCase
{
    use RefreshDatabase;

    private ?Tenant $tenant = null;

    protected function setUp(): void
    {
        parent::setUp();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
        $this->tenant = Tenant::create([
            'name' => 'Test Tenant',
            'slug' => 'test-tenant-'.uniqid(),
            'status' => 'active',
        ]);
    }

    private function websiteAttributes(array $overrides = []): array
    {
        return array_merge([
            'tenant_id' => $this->tenant->id,
        ], $overrides);
    }

    public function test_admin_can_list_websites(): void
    {
        $admin = User::factory()->admin()->create();
        $website = Website::create($this->websiteAttributes([
            'user_id' => $admin->id,
            'name' => 'Test Site',
            'domain' => null,
            'theme' => [],
            'site_id' => null,
        ]));
        $website2 = Website::create($this->websiteAttributes([
            'user_id' => $admin->id,
            'name' => 'Test Site 2',
            'domain' => null,
            'theme' => [],
            'site_id' => null,
        ]));

        $response = $this->actingAs($admin)->get(route('admin.universal-cms.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/UniversalCms/WebsitesIndex')
            ->has('websites')
        );
    }

    public function test_admin_can_update_section_and_builder_sync(): void
    {
        $admin = User::factory()->admin()->create();
        $project = Project::factory()->for($admin)->create();
        $site = app(\App\Services\SiteProvisioningService::class)->provisionForProject($project->fresh(['template', 'user']));
        $page = Page::query()->where('site_id', $site->id)->where('slug', 'home')->first();
        if (! $page) {
            $page = Page::create(['site_id' => $site->id, 'title' => 'Home', 'slug' => 'home', 'status' => 'draft']);
        }
        $revision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->first();
        if (! $revision) {
            $revision = PageRevision::create([
                'site_id' => $site->id,
                'page_id' => $page->id,
                'version' => 1,
                'content_json' => ['sections' => [['type' => 'hero', 'props' => ['title' => 'Old Title', 'subtitle' => 'Old']]]],
            ]);
        } else {
            $revision->update(['content_json' => ['sections' => [['type' => 'hero', 'props' => ['title' => 'Old Title', 'subtitle' => 'Old']]]]]);
        }
        $website = Website::create($this->websiteAttributes([
            'user_id' => $admin->id,
            'name' => 'Test',
            'domain' => null,
            'theme' => [],
            'site_id' => $site->id,
        ]));
        $websitePage = WebsitePage::create([
            'tenant_id' => $this->tenant->id,
            'website_id' => $website->id,
            'page_id' => $page->id,
            'slug' => 'home',
            'title' => 'Home',
            'order' => 0,
        ]);
        $section = PageSection::create([
            'tenant_id' => $this->tenant->id,
            'website_id' => $website->id,
            'page_id' => $websitePage->id,
            'section_type' => 'hero',
            'order' => 0,
            'settings_json' => ['title' => 'Old Title', 'subtitle' => 'Old'],
        ]);

        $response = $this->actingAs($admin)->put(
            route('admin.universal-cms.section-update', [$website->id, $websitePage->id, $section->id]),
            [
                'title' => 'Luxury Spa',
                'subtitle' => 'Professional care',
                'button_text' => 'Book Now',
            ]
        );

        $response->assertRedirect();
        $section->refresh();
        $this->assertSame('Luxury Spa', $section->getSettings()['title']);
        $this->assertSame('Professional care', $section->getSettings()['subtitle']);
        $this->assertSame('Book Now', $section->getSettings()['button_text']);

        $revision->refresh();
        $sections = $revision->content_json['sections'] ?? [];
        $this->assertCount(1, $sections);
        $this->assertSame('Luxury Spa', $sections[0]['props']['title'] ?? null);
    }

    public function test_admin_can_create_and_delete_page(): void
    {
        $admin = User::factory()->admin()->create();
        $website = Website::create($this->websiteAttributes([
            'user_id' => $admin->id,
            'name' => 'Test',
            'domain' => null,
            'theme' => [],
            'site_id' => null,
        ]));

        $response = $this->actingAs($admin)->post(route('admin.universal-cms.pages.store', $website->id), [
            'title' => 'About',
            'slug' => 'about',
        ]);
        $response->assertRedirect(route('admin.universal-cms.pages', $website->id));

        $wp = WebsitePage::query()->where('website_id', $website->id)->where('slug', 'about')->first();
        $this->assertNotNull($wp);
        $this->assertSame('About', $wp->title);

        $response2 = $this->actingAs($admin)->delete(route('admin.universal-cms.pages.destroy', [$website->id, $wp->id]));
        $response2->assertRedirect();
        $this->assertNull(WebsitePage::find($wp->id));
    }
}
