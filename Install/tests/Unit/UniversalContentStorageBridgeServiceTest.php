<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @group docs-sync */
class UniversalContentStorageBridgeServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::clearCache();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_it_builds_read_only_unified_snapshot_across_current_cms_content_tables(): void
    {
        $site = $this->makeSite();
        $site->forceFill([
            'theme_settings' => [
                'project_type' => 'ecommerce',
                'layout' => ['header_menu_key' => 'header'],
                'colors' => ['primary' => '#0ea5e9'],
            ],
            'locale' => 'en',
        ])->save();

        $media = Media::query()->create([
            'site_id' => $site->id,
            'path' => 'uploads/p5-f2-01/hero.jpg',
            'mime' => 'image/jpeg',
            'size' => 32145,
            'meta_json' => ['width' => 1200, 'height' => 800],
        ]);

        $page = Page::query()->create([
            'site_id' => $site->id,
            'title' => 'Landing',
            'slug' => 'landing-p5-f2-01',
            'status' => 'published',
            'seo_title' => 'Landing SEO',
            'seo_description' => 'P5-F2-01 page',
        ]);

        PageRevision::query()->create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'version' => 1,
            'content_json' => ['sections' => [['type' => 'webu_hero_01']]],
            'published_at' => now()->subHour(),
        ]);

        PageRevision::query()->create([
            'site_id' => $site->id,
            'page_id' => $page->id,
            'version' => 2,
            'content_json' => ['sections' => [['type' => 'webu_product_grid_01']]],
            'published_at' => null,
        ]);

        Menu::query()->create([
            'site_id' => $site->id,
            'key' => 'header-p5-f2-01',
            'items_json' => [
                ['label' => 'Home', 'href' => '/'],
                ['label' => 'Shop', 'href' => '/shop'],
            ],
        ]);

        BlogPost::query()->create([
            'site_id' => $site->id,
            'title' => 'Post P5-F2-01',
            'slug' => 'post-p5-f2-01',
            'excerpt' => 'Bridge coverage post',
            'content' => 'Hello universal content bridge',
            'status' => 'published',
            'cover_media_id' => $media->id,
            'published_at' => now()->subMinutes(30),
        ]);

        GlobalSetting::query()->updateOrCreate(
            ['site_id' => $site->id],
            [
                'logo_media_id' => $media->id,
                'contact_json' => ['email' => 'p5f2@example.test'],
                'social_links_json' => ['instagram' => 'https://example.test/ig'],
                'analytics_ids_json' => ['ga4' => 'G-P5F201'],
            ]
        );

        SitePaymentGatewaySetting::query()->create([
            'site_id' => $site->id,
            'provider_slug' => 'stripe',
            'availability' => SitePaymentGatewaySetting::AVAILABILITY_ENABLED,
            'config' => ['mode' => 'test'],
        ]);

        SiteCourierSetting::query()->create([
            'site_id' => $site->id,
            'courier_slug' => 'fedex',
            'availability' => SiteCourierSetting::AVAILABILITY_DISABLED,
            'config' => ['region' => 'global'],
        ]);

        $countsBefore = $this->contentTableCounts();
        $siteThemeBefore = $site->fresh()->theme_settings;

        $service = app(UniversalContentStorageBridgeService::class);
        $snapshot = $service->snapshot($site->fresh());

        $this->assertSame('universal_content_storage_bridge', data_get($snapshot, 'schema.name'));
        $this->assertSame(1, data_get($snapshot, 'schema.version'));
        $this->assertSame('P5-F2-01', data_get($snapshot, 'schema.task'));
        $this->assertSame((string) $site->id, data_get($snapshot, 'site.id'));
        $this->assertContains('sites.theme_settings', data_get($snapshot, 'sources.settings'));

        $pageEntry = collect(data_get($snapshot, 'content.pages', []))->firstWhere('slug', 'landing-p5-f2-01');
        $this->assertNotNull($pageEntry);
        $this->assertSame(2, data_get($pageEntry, 'revisions.latest.version'));
        $this->assertFalse((bool) data_get($pageEntry, 'revisions.latest.is_published'));
        $this->assertSame(1, data_get($pageEntry, 'revisions.published.version'));
        $this->assertTrue((bool) data_get($pageEntry, 'revisions.published.is_published'));
        $this->assertSame('webu_product_grid_01', data_get($pageEntry, 'revisions.latest.content_json.sections.0.type'));
        $this->assertSame('webu_hero_01', data_get($pageEntry, 'revisions.published.content_json.sections.0.type'));

        $postEntry = collect(data_get($snapshot, 'content.posts', []))->firstWhere('slug', 'post-p5-f2-01');
        $this->assertNotNull($postEntry);
        $this->assertSame((int) $media->id, data_get($postEntry, 'cover_media_id'));
        $this->assertSame('uploads/p5-f2-01/hero.jpg', data_get($postEntry, 'cover_media_path'));

        $menuEntry = collect(data_get($snapshot, 'content.menus', []))->firstWhere('menu_key', 'header-p5-f2-01');
        $this->assertNotNull($menuEntry);
        $this->assertSame(2, data_get($menuEntry, 'items_count'));

        $mediaEntry = collect(data_get($snapshot, 'content.media', []))->firstWhere('path', 'uploads/p5-f2-01/hero.jpg');
        $this->assertNotNull($mediaEntry);
        $this->assertSame(32145, data_get($mediaEntry, 'size'));

        $settings = collect(data_get($snapshot, 'content.settings', []));
        $this->assertSame('site.theme_settings', data_get($settings->firstWhere('key', 'site.theme_settings'), 'key'));
        $this->assertSame('p5f2@example.test', data_get($settings->firstWhere('key', 'global_settings'), 'payload.contact_json.email'));
        $this->assertSame('enabled', data_get($settings->firstWhere('key', 'payment_gateway:stripe'), 'availability'));
        $this->assertSame('disabled', data_get($settings->firstWhere('key', 'courier:fedex'), 'availability'));

        $redacted = $service->snapshot($site->fresh(), ['include_payloads' => false]);
        $redactedPageEntry = collect(data_get($redacted, 'content.pages', []))->firstWhere('slug', 'landing-p5-f2-01');
        $this->assertNull(data_get($redactedPageEntry, 'revisions.latest.content_json'));
        $this->assertNull(data_get($redacted, 'content.settings.0.payload'));

        $this->assertSame($countsBefore, $this->contentTableCounts());
        $this->assertSame($siteThemeBefore, $site->fresh()->theme_settings);
    }

    public function test_architecture_doc_documents_p5_f2_01_universal_content_storage_bridge_contract(): void
    {
        $path = base_path('docs/architecture/UNIVERSAL_CONTENT_STORAGE_NORMALIZATION_P5_F2_01.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('P5-F2-01', $doc);
        $this->assertStringContainsString('UniversalContentStorageBridgeService', $doc);
        $this->assertStringContainsString('pages', $doc);
        $this->assertStringContainsString('posts', $doc);
        $this->assertStringContainsString('menus', $doc);
        $this->assertStringContainsString('media', $doc);
        $this->assertStringContainsString('settings', $doc);
        $this->assertStringContainsString('read-only bridge', $doc);
        $this->assertStringContainsString('P5-F2-04', $doc);
    }

    private function makeSite(): Site
    {
        $project = Project::factory()->create();

        $site = $project->fresh()->site;
        $this->assertInstanceOf(Site::class, $site);

        return $site->fresh();
    }

    /**
     * @return array<string, int>
     */
    private function contentTableCounts(): array
    {
        return [
            'pages' => DB::table('pages')->count(),
            'page_revisions' => DB::table('page_revisions')->count(),
            'blog_posts' => DB::table('blog_posts')->count(),
            'menus' => DB::table('menus')->count(),
            'media' => DB::table('media')->count(),
            'global_settings' => DB::table('global_settings')->count(),
            'site_payment_gateway_settings' => DB::table('site_payment_gateway_settings')->count(),
            'site_courier_settings' => DB::table('site_courier_settings')->count(),
        ];
    }
}
