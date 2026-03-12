<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use App\Models\Project;
use App\Models\Site;
use App\Models\User;
use App\Models\Template;
use App\Services\CmsModuleRegistryService;
use App\Services\CmsProjectTypeModuleFeatureFlagService;
use App\Support\SystemSetting;
use Tests\TestCase;

/** @group docs-sync */
class CmsProjectTypeModuleFeatureFlagServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::clearCache();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_it_resolves_project_type_from_template_category_and_defaults_to_non_enforced_framework(): void
    {
        $site = $this->makeSiteWithTemplateCategory('ecommerce');
        $service = app(CmsProjectTypeModuleFeatureFlagService::class);

        $projectType = $service->resolveProjectType($site->fresh('project.template'));
        $this->assertSame('ecommerce', $projectType['key']);
        $this->assertSame('project.template.category', $projectType['source']);

        $evaluation = $service->evaluateModule($site->fresh('project.template'), CmsModuleRegistryService::MODULE_BOOKING);
        $this->assertTrue($evaluation['ok']);
        $this->assertFalse((bool) $evaluation['framework_enabled']);
        $this->assertTrue((bool) $evaluation['allowed']);
        $this->assertNull($evaluation['reason']);
    }

    public function test_module_registry_applies_project_type_feature_flag_gates_to_module_visibility(): void
    {
        SystemSetting::set(CmsProjectTypeModuleFeatureFlagService::FLAG_ENABLED, true, 'boolean', 'cms_project_type_flags');

        $admin = User::factory()->admin()->create();
        $site = $this->makeSiteWithTemplateCategory('ecommerce', [
            'modules' => [
                CmsModuleRegistryService::MODULE_BOOKING => true,
            ],
        ]);

        $registry = app(CmsModuleRegistryService::class);
        $modulesPayload = $registry->modules($site->fresh('project.template'), $admin);
        $entitlementsPayload = $registry->entitlements($site->fresh('project.template'), $admin);

        $booking = collect($modulesPayload['modules'])->firstWhere('key', CmsModuleRegistryService::MODULE_BOOKING);
        $this->assertNotNull($booking);
        $this->assertTrue((bool) data_get($booking, 'requested'));
        $this->assertTrue((bool) data_get($booking, 'entitled')); // admin bypass isolates project-type gate
        $this->assertFalse((bool) data_get($booking, 'project_type_allowed'));
        $this->assertFalse((bool) data_get($booking, 'enabled'));
        $this->assertFalse((bool) data_get($booking, 'available'));
        $this->assertStringContainsString('project type [ecommerce]', (string) data_get($booking, 'reason'));
        $this->assertTrue((bool) data_get($booking, 'project_type_gate.framework_enabled'));
        $this->assertSame('ecommerce', data_get($booking, 'project_type_gate.project_type.key'));

        $this->assertSame('ecommerce', data_get($modulesPayload, 'project_type.key'));
        $this->assertTrue((bool) data_get($modulesPayload, 'project_type_flags.enabled'));
        $this->assertGreaterThanOrEqual(1, (int) data_get($modulesPayload, 'summary.blocked_by_project_type'));

        $this->assertSame('ecommerce', data_get($entitlementsPayload, 'project_type.key'));
        $this->assertTrue((bool) data_get($entitlementsPayload, 'project_type_flags.enabled'));
        $this->assertStringContainsString('project type [ecommerce]', (string) data_get($entitlementsPayload, 'reasons.booking'));
    }

    public function test_matrix_override_can_allow_module_for_specific_project_type(): void
    {
        SystemSetting::set(CmsProjectTypeModuleFeatureFlagService::FLAG_ENABLED, true, 'boolean', 'cms_project_type_flags');
        SystemSetting::set(CmsProjectTypeModuleFeatureFlagService::FLAG_MATRIX, [
            'types' => [
                'ecommerce' => [
                    'allow' => [CmsModuleRegistryService::MODULE_BOOKING],
                    'deny' => [],
                ],
            ],
        ], 'json', 'cms_project_type_flags');

        $admin = User::factory()->admin()->create();
        $site = $this->makeSiteWithTemplateCategory('ecommerce', [
            'modules' => [
                CmsModuleRegistryService::MODULE_BOOKING => true,
            ],
        ]);

        $registry = app(CmsModuleRegistryService::class);
        $modulesPayload = $registry->modules($site->fresh('project.template'), $admin);

        $booking = collect($modulesPayload['modules'])->firstWhere('key', CmsModuleRegistryService::MODULE_BOOKING);
        $this->assertNotNull($booking);
        $this->assertTrue((bool) data_get($booking, 'project_type_allowed'));
        $this->assertTrue((bool) data_get($booking, 'enabled'));
        $this->assertTrue((bool) data_get($booking, 'available'));
        $this->assertNull(data_get($booking, 'reason'));
    }

    public function test_portfolio_project_type_allows_portfolio_module_and_ecommerce_type_denies_it_when_framework_enabled(): void
    {
        SystemSetting::set(CmsProjectTypeModuleFeatureFlagService::FLAG_ENABLED, true, 'boolean', 'cms_project_type_flags');

        $service = app(CmsProjectTypeModuleFeatureFlagService::class);

        $portfolioSite = $this->makeSiteWithTemplateCategory('portfolio');
        $portfolioEval = $service->evaluateModule($portfolioSite, CmsModuleRegistryService::MODULE_PORTFOLIO);
        $this->assertTrue((bool) $portfolioEval['allowed']);
        $this->assertNull($portfolioEval['reason']);
        $this->assertSame('portfolio', data_get($portfolioEval, 'project_type.key'));
        $this->assertSame('allow', data_get($portfolioEval, 'rule.match'));

        $ecommerceSite = $this->makeSiteWithTemplateCategory('ecommerce');
        $ecommerceEval = $service->evaluateModule($ecommerceSite, CmsModuleRegistryService::MODULE_PORTFOLIO);
        $this->assertFalse((bool) $ecommerceEval['allowed']);
        $this->assertStringContainsString('project type [ecommerce]', (string) $ecommerceEval['reason']);
        $this->assertSame('ecommerce', data_get($ecommerceEval, 'project_type.key'));
        $this->assertSame('deny', data_get($ecommerceEval, 'rule.match'));
    }

    public function test_real_estate_project_type_allows_real_estate_module_and_ecommerce_type_denies_it_when_framework_enabled(): void
    {
        SystemSetting::set(CmsProjectTypeModuleFeatureFlagService::FLAG_ENABLED, true, 'boolean', 'cms_project_type_flags');

        $service = app(CmsProjectTypeModuleFeatureFlagService::class);

        $realEstateSite = $this->makeSiteWithTemplateCategory('real_estate');
        $realEstateEval = $service->evaluateModule($realEstateSite, CmsModuleRegistryService::MODULE_REAL_ESTATE);
        $this->assertTrue((bool) $realEstateEval['allowed']);
        $this->assertNull($realEstateEval['reason']);
        $this->assertSame('real_estate', data_get($realEstateEval, 'project_type.key'));
        $this->assertSame('allow', data_get($realEstateEval, 'rule.match'));

        $ecommerceSite = $this->makeSiteWithTemplateCategory('ecommerce');
        $ecommerceEval = $service->evaluateModule($ecommerceSite, CmsModuleRegistryService::MODULE_REAL_ESTATE);
        $this->assertFalse((bool) $ecommerceEval['allowed']);
        $this->assertStringContainsString('project type [ecommerce]', (string) $ecommerceEval['reason']);
        $this->assertSame('ecommerce', data_get($ecommerceEval, 'project_type.key'));
        $this->assertSame('deny', data_get($ecommerceEval, 'rule.match'));
    }

    public function test_restaurant_project_type_allows_restaurant_module_and_ecommerce_type_denies_it_when_framework_enabled(): void
    {
        SystemSetting::set(CmsProjectTypeModuleFeatureFlagService::FLAG_ENABLED, true, 'boolean', 'cms_project_type_flags');

        $service = app(CmsProjectTypeModuleFeatureFlagService::class);

        $restaurantSite = $this->makeSiteWithTemplateCategory('restaurant');
        $restaurantEval = $service->evaluateModule($restaurantSite, CmsModuleRegistryService::MODULE_RESTAURANT);
        $this->assertTrue((bool) $restaurantEval['allowed']);
        $this->assertNull($restaurantEval['reason']);
        $this->assertSame('restaurant', data_get($restaurantEval, 'project_type.key'));
        $this->assertSame('allow', data_get($restaurantEval, 'rule.match'));

        $ecommerceSite = $this->makeSiteWithTemplateCategory('ecommerce');
        $ecommerceEval = $service->evaluateModule($ecommerceSite, CmsModuleRegistryService::MODULE_RESTAURANT);
        $this->assertFalse((bool) $ecommerceEval['allowed']);
        $this->assertStringContainsString('project type [ecommerce]', (string) $ecommerceEval['reason']);
        $this->assertSame('ecommerce', data_get($ecommerceEval, 'project_type.key'));
        $this->assertSame('deny', data_get($ecommerceEval, 'rule.match'));
    }

    public function test_hotel_project_type_allows_hotel_module_and_ecommerce_type_denies_it_when_framework_enabled(): void
    {
        SystemSetting::set(CmsProjectTypeModuleFeatureFlagService::FLAG_ENABLED, true, 'boolean', 'cms_project_type_flags');

        $service = app(CmsProjectTypeModuleFeatureFlagService::class);

        $hotelSite = $this->makeSiteWithTemplateCategory('hotel');
        $hotelEval = $service->evaluateModule($hotelSite, CmsModuleRegistryService::MODULE_HOTEL);
        $this->assertTrue((bool) $hotelEval['allowed']);
        $this->assertNull($hotelEval['reason']);
        $this->assertSame('hotel', data_get($hotelEval, 'project_type.key'));
        $this->assertSame('allow', data_get($hotelEval, 'rule.match'));

        $ecommerceSite = $this->makeSiteWithTemplateCategory('ecommerce');
        $ecommerceEval = $service->evaluateModule($ecommerceSite, CmsModuleRegistryService::MODULE_HOTEL);
        $this->assertFalse((bool) $ecommerceEval['allowed']);
        $this->assertStringContainsString('project type [ecommerce]', (string) $ecommerceEval['reason']);
        $this->assertSame('ecommerce', data_get($ecommerceEval, 'project_type.key'));
        $this->assertSame('deny', data_get($ecommerceEval, 'rule.match'));
    }

    public function test_architecture_doc_documents_project_type_module_feature_flag_framework_and_system_setting_keys(): void
    {
        $path = base_path('docs/architecture/CMS_PROJECT_TYPE_MODULE_FEATURE_FLAGS_P5_F1_04.md');
        $this->assertFileExists($path);

        $doc = File::get($path);

        $this->assertStringContainsString('P5-F1-04', $doc);
        $this->assertStringContainsString('CmsProjectTypeModuleFeatureFlagService', $doc);
        $this->assertStringContainsString('CmsModuleRegistryService', $doc);
        $this->assertStringContainsString('cms_project_type_module_flags_enabled', $doc);
        $this->assertStringContainsString('cms_project_type_module_flags_matrix', $doc);
        $this->assertStringContainsString('cms_project_type_module_flags_default_policy', $doc);
        $this->assertStringContainsString('tenant_project', $doc);
        $this->assertStringContainsString('project.template.category', $doc);
        $this->assertStringContainsString('project_type_allowed', $doc);
        $this->assertStringContainsString('P5-F2-01', $doc);
    }

    /**
     * @param  array<string, mixed>  $themeSettings
     */
    private function makeSiteWithTemplateCategory(string $category, array $themeSettings = []): Site
    {
        $template = Template::factory()->create([
            'slug' => 'tpl-'.uniqid(),
            'category' => $category,
            'metadata' => [],
            'is_system' => true,
        ]);

        $project = Project::factory()->create([
            'template_id' => $template->id,
        ]);

        $site = $project->fresh()->site;

        if ($site instanceof Site) {
            $site->forceFill([
                'name' => $site->name ?: 'Site '.uniqid(),
                'status' => $site->status ?: 'draft',
                'locale' => $site->locale ?: 'en',
                'theme_settings' => $themeSettings,
            ])->save();

            return $site->fresh('project.template');
        }

        return Site::query()->create([
            'project_id' => $project->id,
            'name' => 'Site '.uniqid(),
            'status' => 'draft',
            'locale' => 'en',
            'theme_settings' => $themeSettings,
        ])->fresh('project.template');
    }
}
