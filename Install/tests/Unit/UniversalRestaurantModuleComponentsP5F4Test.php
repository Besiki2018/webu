<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalRestaurantModuleComponentsP5F4Test extends TestCase
{
    public function test_p5_f4_03_restaurant_module_and_components_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/UNIVERSAL_RESTAURANT_MODULE_COMPONENTS_P5_F4_03.md');
        $this->assertFileExists($docPath);

        $doc = File::get($docPath);
        $registry = File::get(base_path('app/Cms/Services/CmsModuleRegistryService.php'));
        $projectTypeFlags = File::get(base_path('app/Cms/Services/CmsProjectTypeModuleFeatureFlagService.php'));
        $templateNormalizer = File::get(base_path('app/Services/TemplateMetadataNormalizerService.php'));
        $cms = File::get(base_path('resources/js/Pages/Project/Cms.tsx'));
        $featureTest = File::get(base_path('tests/Feature/Cms/CmsModuleRegistryTest.php'));
        $unitTest = File::get(base_path('tests/Unit/CmsProjectTypeModuleFeatureFlagServiceTest.php'));
        $frontendContract = File::get(base_path('resources/js/Pages/Project/__tests__/CmsRestaurantBuilderCoverage.contract.test.ts'));

        $this->assertStringContainsString('P5-F4-03', $doc);
        $this->assertStringContainsString('MODULE_RESTAURANT', $doc);
        $this->assertStringContainsString('MODULE_BOOKING', $doc);
        $this->assertStringContainsString('CmsModuleRegistryService', $doc);
        $this->assertStringContainsString('CmsProjectTypeModuleFeatureFlagService', $doc);
        $this->assertStringContainsString('TemplateMetadataNormalizerService', $doc);
        $this->assertStringContainsString('Cms.tsx', $doc);
        $this->assertStringContainsString('project_type_allowed', $doc);
        $this->assertStringContainsString('webu_rest_reservation_form_01', $doc);

        $this->assertStringContainsString("public const MODULE_RESTAURANT = 'restaurant';", $registry);
        $this->assertStringContainsString("'key' => self::MODULE_RESTAURANT", $registry);
        $this->assertStringContainsString("\$templateCategory === 'restaurant' && \$moduleKey === self::MODULE_RESTAURANT", $registry);

        $this->assertStringContainsString("'restaurant' => [", $projectTypeFlags);
        $this->assertStringContainsString('CmsModuleRegistryService::MODULE_RESTAURANT', $projectTypeFlags);
        $this->assertStringContainsString("'restaurant' => \$isRestaurant", $templateNormalizer);

        $this->assertStringContainsString('BUILDER_RESTAURANT_DISCOVERY_LIBRARY_SECTIONS', $cms);
        $this->assertStringContainsString('MODULE_RESTAURANT', $cms);
        $this->assertStringContainsString('syntheticRestaurantSectionKeySet', $cms);
        $this->assertStringContainsString('syntheticRestaurantReservationSectionKeySet', $cms);
        $this->assertStringContainsString('createSyntheticRestaurantPlaceholder', $cms);
        $this->assertStringContainsString('applyRestaurantPreviewState', $cms);
        $this->assertStringContainsString('data-webby-restaurant-menu-items', $cms);
        $this->assertStringContainsString('data-webby-restaurant-reservation-form', $cms);
        $this->assertStringContainsString('builderSectionAvailabilityMatrix', $cms);
        $this->assertStringContainsString('isBuilderSectionAllowedByProjectTypeAvailabilityMatrix', $cms);
        $this->assertStringContainsString("key: 'restaurant'", $cms);
        $this->assertStringContainsString("key: 'restaurant_reservation'", $cms);
        $this->assertStringContainsString('requiredModules: [MODULE_RESTAURANT]', $cms);
        $this->assertStringContainsString('requiredModules: [MODULE_RESTAURANT, MODULE_BOOKING]', $cms);

        $this->assertStringContainsString('test_restaurant_module_is_exposed_for_restaurant_project_type_and_blocked_for_ecommerce_override', $featureTest);
        $this->assertStringContainsString('test_restaurant_project_type_allows_restaurant_module_and_ecommerce_type_denies_it_when_framework_enabled', $unitTest);
        $this->assertStringContainsString('CMS restaurant builder component coverage contracts', $frontendContract);
    }
}
