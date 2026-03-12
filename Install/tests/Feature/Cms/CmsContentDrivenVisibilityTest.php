<?php

namespace Tests\Feature\Cms;

use App\Cms\Services\CmsModuleRegistryService;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsContentDrivenVisibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_pages_endpoint_hides_vertical_pages_when_site_content_does_not_use_that_vertical(): void
    {
        [$owner, $site] = $this->createOwnerWithSite();

        $this->createPage($owner, $site, 'General Content', 'general-content-'.strtolower(Str::random(6)), [
            ['type' => 'webu_general_heading_01', 'props' => ['headline' => 'Welcome']],
        ]);

        $this->createPage($owner, $site, 'Bookings', 'bookings', [
            ['type' => 'webu_general_text_01', 'props' => ['body' => 'Contact us for details']],
        ]);

        $pages = collect(
            $this->actingAs($owner)
                ->getJson(route('panel.sites.pages.index', ['site' => $site->id]))
                ->assertOk()
                ->json('pages')
        );

        $this->assertNotNull($pages->firstWhere('slug', 'home'));
        $this->assertNull($pages->firstWhere('slug', 'bookings'));
    }

    public function test_modules_endpoint_uses_actual_site_content_for_structured_sites_instead_of_template_category(): void
    {
        $plan = Plan::factory()->create([
            'enable_booking' => true,
            'enable_ecommerce' => true,
        ]);
        $owner = User::factory()->withPlan($plan)->create();
        $template = Template::factory()->create([
            'slug' => 'booking-template-'.strtolower(Str::random(6)),
            'category' => 'booking',
            'metadata' => [],
        ]);

        [, $site] = $this->createPublishedProjectWithSite($owner, $template);

        $this->createPage($owner, $site, 'Shop', 'shop-'.strtolower(Str::random(6)), [
            ['type' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'Featured Products']],
        ]);

        $response = $this->actingAs($owner)
            ->getJson(route('panel.sites.modules.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('project_type.key', 'ecommerce')
            ->assertJsonPath('project_type.source', 'site.content_sections');

        $modules = collect($response->json('modules'));
        $bookingModule = $modules
            ->firstWhere('key', CmsModuleRegistryService::MODULE_BOOKING);
        $ecommerceModule = $modules
            ->firstWhere('key', CmsModuleRegistryService::MODULE_ECOMMERCE);

        $this->assertNotNull($bookingModule);
        $this->assertNotNull($ecommerceModule);
        $this->assertFalse((bool) ($bookingModule['requested'] ?? true));
        $this->assertFalse((bool) ($bookingModule['available'] ?? true));
        $this->assertSame('Module is not enabled for this site.', $bookingModule['reason'] ?? null);
        $this->assertTrue((bool) ($ecommerceModule['requested'] ?? false));
    }

    private function createPage(User $owner, Site $site, string $title, string $slug, array $sections): void
    {
        $this->actingAs($owner)
            ->postJson(route('panel.sites.pages.store', ['site' => $site->id]), [
                'title' => $title,
                'slug' => $slug,
                'content_json' => [
                    'sections' => $sections,
                ],
            ])
            ->assertCreated();
    }

    /**
     * @return array{0: User, 1: Site}
     */
    private function createOwnerWithSite(): array
    {
        $owner = User::factory()->create();
        $project = Project::factory()
            ->for($owner)
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$owner, $site];
    }

    /**
     * @return array{0: Project, 1: Site}
     */
    private function createPublishedProjectWithSite(User $owner, Template $template): array
    {
        $project = Project::factory()
            ->for($owner)
            ->state([
                'template_id' => $template->id,
            ])
            ->published(strtolower(Str::random(10)))
            ->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }
}
