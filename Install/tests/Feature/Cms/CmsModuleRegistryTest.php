<?php

namespace Tests\Feature\Cms;

use App\Cms\Services\CmsModuleRegistryService;
use App\Cms\Services\CmsProjectTypeModuleFeatureFlagService;
use App\Models\Plan;
use App\Models\Project;
use App\Models\Site;
use App\Models\SystemSetting;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CmsModuleRegistryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_modules_endpoint_returns_site_scoped_module_states(): void
    {
        SystemSetting::set('domain_enable_subdomains', true, 'boolean', 'domain');
        SystemSetting::set('domain_enable_custom_domains', true, 'boolean', 'domain');

        $plan = Plan::factory()
            ->withSubdomains()
            ->withFileStorage(1024, 10)
            ->withFirebase()
            ->state([
                'enable_custom_domains' => true,
            ])
            ->create();

        $owner = User::factory()->withPlan($plan)->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, 'public');

        $response = $this->actingAs($owner)
            ->getJson(route('panel.sites.modules.index', ['site' => $site->id]));

        $response->assertOk()
            ->assertJsonPath('site_id', $site->id)
            ->assertJsonPath('project_id', $site->project_id);

        $modules = collect($response->json('modules'));

        $this->assertSame($modules->count(), (int) $response->json('summary.total'));
        $this->assertSame(
            $modules->where('available', true)->count(),
            (int) $response->json('summary.available')
        );

        $this->assertTrue((bool) $modules->firstWhere('key', 'media_library')['available']);
        $this->assertTrue((bool) $modules->firstWhere('key', 'database')['available']);
        $this->assertTrue((bool) $modules->firstWhere('key', 'domains')['available']);
        $this->assertFalse((bool) $modules->firstWhere('key', 'ecommerce')['available']);
        $this->assertSame('Module is not enabled for this site.', $modules->firstWhere('key', 'ecommerce')['reason']);
    }

    public function test_entitlements_endpoint_returns_feature_matrix_and_module_availability(): void
    {
        SystemSetting::set('domain_enable_subdomains', true, 'boolean', 'domain');
        SystemSetting::set('domain_enable_custom_domains', false, 'boolean', 'domain');

        $plan = Plan::factory()
            ->withSubdomains()
            ->state([
                'enable_custom_domains' => false,
                'enable_file_storage' => false,
                'enable_firebase' => false,
            ])
            ->create();

        $owner = User::factory()->withPlan($plan)->create();
        [, $site] = $this->createPublishedProjectWithSite($owner, 'public');

        $this->actingAs($owner)
            ->getJson(route('panel.sites.entitlements.show', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('site_id', $site->id)
            ->assertJsonPath('features.subdomains', true)
            ->assertJsonPath('features.custom_domains', false)
            ->assertJsonPath('features.file_storage', false)
            ->assertJsonPath('features.firebase', false)
            ->assertJsonPath('modules.domains', true)
            ->assertJsonPath('modules.media_library', false)
            ->assertJsonPath('modules.database', false)
            ->assertJsonPath('plan.id', $plan->id);
    }

    public function test_modules_endpoints_are_forbidden_for_other_tenant_user(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        [, $site] = $this->createPublishedProjectWithSite($owner, 'public');

        $this->actingAs($intruder)
            ->getJson(route('panel.sites.modules.index', ['site' => $site->id]))
            ->assertForbidden();

        $this->actingAs($intruder)
            ->getJson(route('panel.sites.entitlements.show', ['site' => $site->id]))
            ->assertForbidden();
    }

    public function test_template_module_flags_are_used_for_requested_state(): void
    {
        $plan = Plan::factory()->create();
        $owner = User::factory()->withPlan($plan)->create();

        $template = Template::factory()->create([
            'slug' => 'vet-template-test',
            'category' => 'vet',
            'metadata' => [
                'module_flags' => [
                    'booking' => true,
                    'payments' => true,
                    'shipping' => false,
                    'ecommerce' => false,
                ],
            ],
        ]);

        [, $site] = $this->createPublishedProjectWithSite($owner, 'public', $template);
        $this->seedSiteCapabilityPage($owner, $site, [
            ['type' => 'webu_book_service_list_01', 'props' => ['title' => 'Services']],
        ]);

        $modules = collect(
            $this->actingAs($owner)
                ->getJson(route('panel.sites.modules.index', ['site' => $site->id]))
                ->assertOk()
                ->json('modules')
        );

        $booking = $modules->firstWhere('key', 'booking');
        $payments = $modules->firstWhere('key', 'payments');
        $ecommerce = $modules->firstWhere('key', 'ecommerce');

        $this->assertTrue((bool) ($booking['requested'] ?? false));
        $this->assertTrue((bool) ($payments['requested'] ?? false));
        $this->assertFalse((bool) ($ecommerce['requested'] ?? true));

        $this->assertTrue((bool) ($booking['available'] ?? false));
        $this->assertNull($booking['reason'] ?? null);
        $this->assertSame('Module is not enabled for this site.', $ecommerce['reason'] ?? null);
    }

    public function test_plan_entitlements_can_disable_requested_modules(): void
    {
        $plan = Plan::factory()->create([
            'enable_ecommerce' => false,
            'enable_booking' => false,
        ]);
        $owner = User::factory()->withPlan($plan)->create();

        $template = Template::factory()->create([
            'slug' => 'commerce-booking-disabled-test',
            'category' => 'cms',
            'metadata' => [
                'module_flags' => [
                    'booking' => true,
                    'ecommerce' => true,
                ],
            ],
        ]);

        [, $site] = $this->createPublishedProjectWithSite($owner, 'public', $template);
        $this->seedSiteCapabilityPage($owner, $site, [
            ['type' => 'webu_book_service_list_01', 'props' => ['title' => 'Services']],
            ['type' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'Catalog']],
        ]);

        $modules = collect(
            $this->actingAs($owner)
                ->getJson(route('panel.sites.modules.index', ['site' => $site->id]))
                ->assertOk()
                ->json('modules')
        );

        $booking = $modules->firstWhere('key', 'booking');
        $ecommerce = $modules->firstWhere('key', 'ecommerce');

        $this->assertTrue((bool) ($booking['requested'] ?? false));
        $this->assertFalse((bool) ($booking['available'] ?? true));
        $this->assertSame('Current plan does not include this module.', $booking['reason'] ?? null);

        $this->assertTrue((bool) ($ecommerce['requested'] ?? false));
        $this->assertFalse((bool) ($ecommerce['available'] ?? true));
        $this->assertSame('Current plan does not include this module.', $ecommerce['reason'] ?? null);
    }

    public function test_modules_and_entitlements_endpoints_expose_project_type_feature_flag_gates(): void
    {
        SystemSetting::set(CmsProjectTypeModuleFeatureFlagService::FLAG_ENABLED, true, 'boolean', 'cms_project_type_flags');

        $plan = Plan::factory()->create([
            'enable_ecommerce' => true,
            'enable_booking' => true,
        ]);
        $owner = User::factory()->withPlan($plan)->create();

        $template = Template::factory()->create([
            'slug' => 'commerce-with-booking-requested-test',
            'category' => 'booking',
            'metadata' => [
                'module_flags' => [
                    CmsModuleRegistryService::MODULE_BOOKING => true,
                    CmsModuleRegistryService::MODULE_ECOMMERCE => true,
                ],
            ],
        ]);

        [, $site] = $this->createPublishedProjectWithSite($owner, 'public', $template);
        $this->seedSiteCapabilityPage($owner, $site, [
            ['type' => 'webu_book_service_list_01', 'props' => ['title' => 'Appointments']],
            ['type' => 'webu_ecom_product_grid_01', 'props' => ['title' => 'Catalog']],
        ]);
        $site->forceFill([
            'theme_settings' => array_merge((array) ($site->theme_settings ?? []), [
                'project_type' => 'ecommerce',
            ]),
        ])->save();

        $modulesResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.modules.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('project_type.key', 'ecommerce')
            ->assertJsonPath('project_type.source', 'site.theme_settings.project_type')
            ->assertJsonPath('project_type_flags.enabled', true);

        $modules = collect($modulesResponse->json('modules'));
        $booking = $modules->firstWhere('key', CmsModuleRegistryService::MODULE_BOOKING);
        $ecommerce = $modules->firstWhere('key', CmsModuleRegistryService::MODULE_ECOMMERCE);

        $this->assertNotNull($booking);
        $this->assertNotNull($ecommerce);
        $this->assertTrue((bool) ($booking['requested'] ?? false));
        $this->assertTrue((bool) ($booking['entitled'] ?? false));
        $this->assertFalse((bool) ($booking['project_type_allowed'] ?? true));
        $this->assertStringContainsString('project type [ecommerce]', (string) ($booking['reason'] ?? ''));
        $this->assertTrue((bool) data_get($booking, 'project_type_gate.framework_enabled'));
        $this->assertSame('ecommerce', data_get($booking, 'project_type_gate.project_type.key'));
        $this->assertTrue((bool) ($ecommerce['project_type_allowed'] ?? false));
        $this->assertGreaterThanOrEqual(1, (int) $modulesResponse->json('summary.blocked_by_project_type'));

        $this->actingAs($owner)
            ->getJson(route('panel.sites.entitlements.show', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('project_type.key', 'ecommerce')
            ->assertJsonPath('project_type_flags.enabled', true)
            ->assertJsonPath('modules.booking', false)
            ->assertJsonPath('modules.ecommerce', true);
    }

    public function test_portfolio_module_is_exposed_for_portfolio_project_type_and_blocked_for_ecommerce_override(): void
    {
        SystemSetting::set(CmsProjectTypeModuleFeatureFlagService::FLAG_ENABLED, true, 'boolean', 'cms_project_type_flags');

        $plan = Plan::factory()->create([
            'enable_ecommerce' => true,
            'enable_booking' => true,
        ]);
        $owner = User::factory()->withPlan($plan)->create();

        $template = Template::factory()->create([
            'slug' => 'portfolio-template-test',
            'category' => 'portfolio',
            'metadata' => [],
        ]);

        [, $site] = $this->createPublishedProjectWithSite($owner, 'public', $template);
        $this->seedSiteCapabilityPage($owner, $site, [
            ['type' => 'webu_portfolio_gallery_01', 'props' => ['title' => 'Projects']],
        ]);

        $portfolioModulesResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.modules.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('project_type.key', 'portfolio');

        $portfolioModules = collect($portfolioModulesResponse->json('modules'));
        $portfolio = $portfolioModules->firstWhere('key', CmsModuleRegistryService::MODULE_PORTFOLIO);

        $this->assertNotNull($portfolio);
        $this->assertTrue((bool) ($portfolio['implemented'] ?? false));
        $this->assertTrue((bool) ($portfolio['requested'] ?? false)); // template category auto-request
        $this->assertTrue((bool) ($portfolio['project_type_allowed'] ?? false));
        $this->assertTrue((bool) ($portfolio['enabled'] ?? false));
        $this->assertTrue((bool) ($portfolio['available'] ?? false));
        $this->assertNull($portfolio['reason'] ?? null);

        $site->forceFill([
            'theme_settings' => array_merge((array) ($site->theme_settings ?? []), [
                'project_type' => 'ecommerce',
                'modules' => array_merge((array) data_get($site->theme_settings, 'modules', []), [
                    CmsModuleRegistryService::MODULE_PORTFOLIO => true,
                ]),
            ]),
        ])->save();

        $blockedModulesResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.modules.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('project_type.key', 'ecommerce');

        $blockedPortfolio = collect($blockedModulesResponse->json('modules'))->firstWhere('key', CmsModuleRegistryService::MODULE_PORTFOLIO);
        $this->assertNotNull($blockedPortfolio);
        $this->assertTrue((bool) ($blockedPortfolio['requested'] ?? false));
        $this->assertFalse((bool) ($blockedPortfolio['project_type_allowed'] ?? true));
        $this->assertFalse((bool) ($blockedPortfolio['available'] ?? true));
        $this->assertStringContainsString('project type [ecommerce]', (string) ($blockedPortfolio['reason'] ?? ''));

        $this->actingAs($owner)
            ->getJson(route('panel.sites.entitlements.show', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('project_type.key', 'ecommerce')
            ->assertJsonPath('modules.portfolio', false);
    }

    public function test_real_estate_module_is_exposed_for_real_estate_project_type_and_blocked_for_ecommerce_override(): void
    {
        SystemSetting::set(CmsProjectTypeModuleFeatureFlagService::FLAG_ENABLED, true, 'boolean', 'cms_project_type_flags');

        $plan = Plan::factory()->create([
            'enable_ecommerce' => true,
            'enable_booking' => true,
        ]);
        $owner = User::factory()->withPlan($plan)->create();

        $template = Template::factory()->create([
            'slug' => 'real-estate-template-test',
            'category' => 'real_estate',
            'metadata' => [],
        ]);

        [, $site] = $this->createPublishedProjectWithSite($owner, 'public', $template);
        $this->seedSiteCapabilityPage($owner, $site, [
            ['type' => 'webu_realestate_listing_grid_01', 'props' => ['title' => 'Listings']],
        ]);

        $realEstateModulesResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.modules.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('project_type.key', 'real_estate');

        $realEstateModules = collect($realEstateModulesResponse->json('modules'));
        $realEstate = $realEstateModules->firstWhere('key', CmsModuleRegistryService::MODULE_REAL_ESTATE);

        $this->assertNotNull($realEstate);
        $this->assertTrue((bool) ($realEstate['implemented'] ?? false));
        $this->assertTrue((bool) ($realEstate['requested'] ?? false)); // template category auto-request
        $this->assertTrue((bool) ($realEstate['project_type_allowed'] ?? false));
        $this->assertTrue((bool) ($realEstate['enabled'] ?? false));
        $this->assertTrue((bool) ($realEstate['available'] ?? false));
        $this->assertNull($realEstate['reason'] ?? null);

        $site->forceFill([
            'theme_settings' => array_merge((array) ($site->theme_settings ?? []), [
                'project_type' => 'ecommerce',
                'modules' => array_merge((array) data_get($site->theme_settings, 'modules', []), [
                    CmsModuleRegistryService::MODULE_REAL_ESTATE => true,
                ]),
            ]),
        ])->save();

        $blockedModulesResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.modules.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('project_type.key', 'ecommerce');

        $blockedRealEstate = collect($blockedModulesResponse->json('modules'))->firstWhere('key', CmsModuleRegistryService::MODULE_REAL_ESTATE);
        $this->assertNotNull($blockedRealEstate);
        $this->assertTrue((bool) ($blockedRealEstate['requested'] ?? false));
        $this->assertFalse((bool) ($blockedRealEstate['project_type_allowed'] ?? true));
        $this->assertFalse((bool) ($blockedRealEstate['available'] ?? true));
        $this->assertStringContainsString('project type [ecommerce]', (string) ($blockedRealEstate['reason'] ?? ''));

        $this->actingAs($owner)
            ->getJson(route('panel.sites.entitlements.show', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('project_type.key', 'ecommerce')
            ->assertJsonPath('modules.real_estate', false);
    }

    public function test_restaurant_module_is_exposed_for_restaurant_project_type_and_blocked_for_ecommerce_override(): void
    {
        SystemSetting::set(CmsProjectTypeModuleFeatureFlagService::FLAG_ENABLED, true, 'boolean', 'cms_project_type_flags');

        $plan = Plan::factory()->create([
            'enable_ecommerce' => true,
            'enable_booking' => true,
        ]);
        $owner = User::factory()->withPlan($plan)->create();

        $template = Template::factory()->create([
            'slug' => 'restaurant-template-test',
            'category' => 'restaurant',
            'metadata' => [],
        ]);

        [, $site] = $this->createPublishedProjectWithSite($owner, 'public', $template);
        $this->seedSiteCapabilityPage($owner, $site, [
            ['type' => 'webu_rest_menu_grid_01', 'props' => ['title' => 'Menu']],
        ]);

        $restaurantModulesResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.modules.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('project_type.key', 'restaurant');

        $restaurantModules = collect($restaurantModulesResponse->json('modules'));
        $restaurant = $restaurantModules->firstWhere('key', CmsModuleRegistryService::MODULE_RESTAURANT);

        $this->assertNotNull($restaurant);
        $this->assertTrue((bool) ($restaurant['implemented'] ?? false));
        $this->assertTrue((bool) ($restaurant['requested'] ?? false)); // template category auto-request
        $this->assertTrue((bool) ($restaurant['project_type_allowed'] ?? false));
        $this->assertTrue((bool) ($restaurant['enabled'] ?? false));
        $this->assertTrue((bool) ($restaurant['available'] ?? false));
        $this->assertNull($restaurant['reason'] ?? null);

        $site->forceFill([
            'theme_settings' => array_merge((array) ($site->theme_settings ?? []), [
                'project_type' => 'ecommerce',
                'modules' => array_merge((array) data_get($site->theme_settings, 'modules', []), [
                    CmsModuleRegistryService::MODULE_RESTAURANT => true,
                ]),
            ]),
        ])->save();

        $blockedModulesResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.modules.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('project_type.key', 'ecommerce');

        $blockedRestaurant = collect($blockedModulesResponse->json('modules'))->firstWhere('key', CmsModuleRegistryService::MODULE_RESTAURANT);
        $this->assertNotNull($blockedRestaurant);
        $this->assertTrue((bool) ($blockedRestaurant['requested'] ?? false));
        $this->assertFalse((bool) ($blockedRestaurant['project_type_allowed'] ?? true));
        $this->assertFalse((bool) ($blockedRestaurant['available'] ?? true));
        $this->assertStringContainsString('project type [ecommerce]', (string) ($blockedRestaurant['reason'] ?? ''));

        $this->actingAs($owner)
            ->getJson(route('panel.sites.entitlements.show', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('project_type.key', 'ecommerce')
            ->assertJsonPath('modules.restaurant', false);
    }

    public function test_hotel_module_is_exposed_for_hotel_project_type_and_blocked_for_ecommerce_override(): void
    {
        SystemSetting::set(CmsProjectTypeModuleFeatureFlagService::FLAG_ENABLED, true, 'boolean', 'cms_project_type_flags');

        $plan = Plan::factory()->create([
            'enable_ecommerce' => true,
            'enable_booking' => true,
        ]);
        $owner = User::factory()->withPlan($plan)->create();

        $template = Template::factory()->create([
            'slug' => 'hotel-template-test',
            'category' => 'hotel',
            'metadata' => [],
        ]);

        [, $site] = $this->createPublishedProjectWithSite($owner, 'public', $template);
        $this->seedSiteCapabilityPage($owner, $site, [
            ['type' => 'webu_hotel_room_grid_01', 'props' => ['title' => 'Rooms']],
        ]);

        $hotelModulesResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.modules.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('project_type.key', 'hotel');

        $hotelModules = collect($hotelModulesResponse->json('modules'));
        $hotel = $hotelModules->firstWhere('key', CmsModuleRegistryService::MODULE_HOTEL);

        $this->assertNotNull($hotel);
        $this->assertTrue((bool) ($hotel['implemented'] ?? false));
        $this->assertTrue((bool) ($hotel['requested'] ?? false)); // template category auto-request
        $this->assertTrue((bool) ($hotel['project_type_allowed'] ?? false));
        $this->assertTrue((bool) ($hotel['enabled'] ?? false));
        $this->assertTrue((bool) ($hotel['available'] ?? false));
        $this->assertNull($hotel['reason'] ?? null);

        $site->forceFill([
            'theme_settings' => array_merge((array) ($site->theme_settings ?? []), [
                'project_type' => 'ecommerce',
                'modules' => array_merge((array) data_get($site->theme_settings, 'modules', []), [
                    CmsModuleRegistryService::MODULE_HOTEL => true,
                ]),
            ]),
        ])->save();

        $blockedModulesResponse = $this->actingAs($owner)
            ->getJson(route('panel.sites.modules.index', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('project_type.key', 'ecommerce');

        $blockedHotel = collect($blockedModulesResponse->json('modules'))->firstWhere('key', CmsModuleRegistryService::MODULE_HOTEL);
        $this->assertNotNull($blockedHotel);
        $this->assertTrue((bool) ($blockedHotel['requested'] ?? false));
        $this->assertFalse((bool) ($blockedHotel['project_type_allowed'] ?? true));
        $this->assertFalse((bool) ($blockedHotel['available'] ?? true));
        $this->assertStringContainsString('project type [ecommerce]', (string) ($blockedHotel['reason'] ?? ''));

        $this->actingAs($owner)
            ->getJson(route('panel.sites.entitlements.show', ['site' => $site->id]))
            ->assertOk()
            ->assertJsonPath('project_type.key', 'ecommerce')
            ->assertJsonPath('modules.hotel', false);
    }

    private function createPublishedProjectWithSite(User $owner, string $visibility, ?Template $template = null): array
    {
        $factory = Project::factory()->for($owner);
        $subdomain = strtolower(Str::random(10));

        if ($template !== null) {
            $factory = $factory->state([
                'template_id' => $template->id,
            ]);
        }

        if ($visibility === 'private') {
            $factory = $factory->privatePublished($subdomain);
        } else {
            $factory = $factory->published($subdomain);
        }

        $project = $factory->create();

        /** @var Site $site */
        $site = $project->site()->firstOrFail();

        return [$project, $site];
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function seedSiteCapabilityPage(User $owner, Site $site, array $sections): void
    {
        $this->actingAs($owner)
            ->postJson(route('panel.sites.pages.store', ['site' => $site->id]), [
                'title' => 'Capability '.strtolower(Str::random(6)),
                'slug' => 'capability-'.strtolower(Str::random(8)),
                'content_json' => [
                    'sections' => $sections,
                ],
            ])
            ->assertCreated();
    }
}
