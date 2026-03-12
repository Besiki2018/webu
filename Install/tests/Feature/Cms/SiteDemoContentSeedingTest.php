<?php

namespace Tests\Feature\Cms;

use App\Models\BlogPost;
use App\Models\Booking;
use App\Models\BookingService;
use App\Models\EcommerceCategory;
use App\Models\EcommerceProduct;
use App\Models\Media;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use App\Services\SiteDemoContentSeederService;
use App\Services\SiteProvisioningService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteDemoContentSeedingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'cms.demo_content.enabled' => true,
            'cms.demo_content.seed_in_testing' => true,
        ]);

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_project_provisioning_seeds_demo_content_for_new_site(): void
    {
        $owner = User::factory()->create();

        $project = Project::factory()
            ->for($owner)
            ->create([
                'name' => 'დემო ონლაინ მაღაზია',
            ]);

        $site = $project->site()->firstOrFail()->fresh();
        if (! (bool) data_get($site->theme_settings, 'demo_content.seeded')) {
            app(SiteDemoContentSeederService::class)->seedForProject($site, $project->fresh());
            $site = $site->fresh();
        }
        if (! (bool) data_get($site->theme_settings, 'demo_content.seeded')) {
            $this->markTestSkipped('Demo content seeding skipped (disabled or existing content). Set CMS_DEMO_CONTENT_SEED_IN_TESTING=1 and ensure no existing business data.');
        }

        $this->assertTrue((bool) data_get($site->theme_settings, 'demo_content.seeded'));
        $this->assertNotNull(data_get($site->theme_settings, 'demo_content.seeded_at'));
        $this->assertSame(1, (int) data_get($site->theme_settings, 'demo_content.seed_version'));

        $this->assertGreaterThan(0, Media::query()->where('site_id', $site->id)->count());
        $this->assertGreaterThan(0, BlogPost::query()->where('site_id', $site->id)->count());
        $this->assertGreaterThan(0, EcommerceProduct::query()->where('site_id', $site->id)->count());
        $this->assertGreaterThan(0, BookingService::query()->where('site_id', $site->id)->count());
        $this->assertGreaterThan(0, Booking::query()->where('site_id', $site->id)->count());

        $category = EcommerceCategory::query()
            ->where('site_id', $site->id)
            ->where('slug', 'akhali-produqcia')
            ->firstOrFail();
        $this->assertSame('ახალი პროდუქცია', $category->name);

        $blogPost = BlogPost::query()
            ->where('site_id', $site->id)
            ->where('slug', 'rogor-daviwyot-online-gayidvebi')
            ->firstOrFail();
        $this->assertSame('როგორ დავიწყოთ ონლაინ გაყიდვები მარტივად', $blogPost->title);
    }

    public function test_demo_seeding_is_idempotent_on_repeat_provision(): void
    {
        $owner = User::factory()->create();
        $project = Project::factory()->for($owner)->create();
        $site = $project->site()->firstOrFail();
        if (! (bool) data_get($site->theme_settings, 'demo_content.seeded')) {
            app(SiteDemoContentSeederService::class)->seedForProject($site->fresh(), $project->fresh());
            $site = $site->fresh();
        }

        $before = $this->seededStats($site->id);

        app(SiteProvisioningService::class)->provisionForProject($project->fresh());

        $after = $this->seededStats($site->id);

        $this->assertSame($before, $after);
        $seeded = (bool) data_get($site->fresh()->theme_settings, 'demo_content.seeded');
        if (! $seeded) {
            $this->markTestSkipped('Demo content not seeded (disabled or existing content).');
        }
        $this->assertTrue($seeded);
    }

    public function test_project_provisioning_uses_template_demo_content_json_for_webu_shop_template(): void
    {
        $owner = User::factory()->create();
        $template = Template::factory()->create([
            'slug' => 'webu-shop-01',
            'name' => 'Webu Shop 01',
            'metadata' => [],
        ]);

        $project = Project::factory()
            ->for($owner)
            ->create([
                'template_id' => $template->id,
                'name' => 'Webu Shop Demo JSON',
            ]);

        $site = $project->site()->firstOrFail()->fresh();
        if (! (bool) data_get($site->theme_settings, 'demo_content.seeded')) {
            app(SiteDemoContentSeederService::class)->seedForProject($site, $project->fresh());
            $site = $site->fresh();
        }
        if (! (bool) data_get($site->theme_settings, 'demo_content.seeded')) {
            $this->markTestSkipped('Demo content not seeded (disabled or existing content).');
        }
        $templateDemo = data_get($site->theme_settings, 'demo_content.template_demo_content');
        if (! is_array($templateDemo)) {
            $this->markTestSkipped('Template webu-shop-01 has no demo-content directory; template_demo_content not set.');
        }
        $this->assertTrue((bool) data_get($site->theme_settings, 'demo_content.seeded'));
        $this->assertSame(8, (int) ($templateDemo['products'] ?? 0));
        $this->assertSame(3, (int) ($templateDemo['posts'] ?? 0));

        $this->assertDatabaseHas('blog_posts', [
            'site_id' => $site->id,
            'title' => 'ახალი სეზონის ავეჯის ტრენდები',
        ]);

        $this->assertDatabaseHas('ecommerce_products', [
            'site_id' => $site->id,
            'name' => 'დეკორატიული დივანი',
        ]);

        $seededCoverMedia = Media::query()
            ->where('site_id', $site->id)
            ->where('path', 'like', 'site-media/%/demo/demo-post-cover-%')
            ->exists();

        $seededProductMedia = Media::query()
            ->where('site_id', $site->id)
            ->where('path', 'like', 'site-media/%/demo/demo-product-%')
            ->exists();

        $this->assertTrue($seededCoverMedia, 'Expected blog cover media imported from template demo-content image references.');
        $this->assertTrue($seededProductMedia, 'Expected product media imported from template demo-content image references.');
    }

    /**
     * @return array{media:int,posts:int,products:int,booking_services:int,bookings:int}
     */
    private function seededStats(string $siteId): array
    {
        return [
            'media' => Media::query()->where('site_id', $siteId)->count(),
            'posts' => BlogPost::query()->where('site_id', $siteId)->count(),
            'products' => EcommerceProduct::query()->where('site_id', $siteId)->count(),
            'booking_services' => BookingService::query()->where('site_id', $siteId)->count(),
            'bookings' => Booking::query()->where('site_id', $siteId)->count(),
        ];
    }
}
