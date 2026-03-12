<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalHotelModuleComponentsP5F4Test extends TestCase
{
    public function test_p5_f4_04_hotel_module_and_components_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/UNIVERSAL_HOTEL_MODULE_COMPONENTS_P5_F4_04.md');
        $this->assertFileExists($docPath);

        $doc = File::get($docPath);
        $registry = File::get(base_path('app/Cms/Services/CmsModuleRegistryService.php'));
        $projectTypeFlags = File::get(base_path('app/Cms/Services/CmsProjectTypeModuleFeatureFlagService.php'));
        $templateNormalizer = File::get(base_path('app/Services/TemplateMetadataNormalizerService.php'));
        $cms = File::get(base_path('resources/js/Pages/Project/Cms.tsx'));
        $featureTest = File::get(base_path('tests/Feature/Cms/CmsModuleRegistryTest.php'));
        $unitTest = File::get(base_path('tests/Unit/CmsProjectTypeModuleFeatureFlagServiceTest.php'));
        $frontendContract = File::get(base_path('resources/js/Pages/Project/__tests__/CmsHotelBuilderCoverage.contract.test.ts'));

        $this->assertStringContainsString('P5-F4-04', $doc);
        $this->assertStringContainsString('MODULE_HOTEL', $doc);
        $this->assertStringContainsString('MODULE_BOOKING', $doc);
        $this->assertStringContainsString('CmsModuleRegistryService', $doc);
        $this->assertStringContainsString('CmsProjectTypeModuleFeatureFlagService', $doc);
        $this->assertStringContainsString('TemplateMetadataNormalizerService', $doc);
        $this->assertStringContainsString('Cms.tsx', $doc);
        $this->assertStringContainsString('project_type_allowed', $doc);
        $this->assertStringContainsString('webu_hotel_room_grid_01', $doc);
        $this->assertStringContainsString('webu_hotel_reservation_form_01', $doc);

        $this->assertStringContainsString("public const MODULE_HOTEL = 'hotel';", $registry);
        $this->assertStringContainsString("'key' => self::MODULE_HOTEL", $registry);
        $this->assertStringContainsString("\$templateCategory === 'hotel' && \$moduleKey === self::MODULE_HOTEL", $registry);

        $this->assertStringContainsString("'hotel' => [", $projectTypeFlags);
        $this->assertStringContainsString('CmsModuleRegistryService::MODULE_HOTEL', $projectTypeFlags);
        $this->assertStringContainsString("'hotel' => \$isHotel", $templateNormalizer);

        $this->assertStringContainsString('BUILDER_HOTEL_DISCOVERY_LIBRARY_SECTIONS', $cms);
        $this->assertStringContainsString('MODULE_HOTEL', $cms);
        $this->assertStringContainsString('syntheticHotelSectionKeySet', $cms);
        $this->assertStringContainsString('syntheticHotelReservationSectionKeySet', $cms);
        $this->assertStringContainsString('createSyntheticHotelPlaceholder', $cms);
        $this->assertStringContainsString('applyHotelPreviewState', $cms);
        $this->assertStringContainsString('data-webby-hotel-rooms', $cms);
        $this->assertStringContainsString('data-webby-hotel-availability', $cms);
        $this->assertStringContainsString('data-webby-hotel-reservation-form', $cms);
        $this->assertStringContainsString('builderSectionAvailabilityMatrix', $cms);
        $this->assertStringContainsString('isBuilderSectionAllowedByProjectTypeAvailabilityMatrix', $cms);
        $this->assertStringContainsString("key: 'hotel'", $cms);
        $this->assertStringContainsString("key: 'hotel_reservation'", $cms);
        $this->assertStringContainsString('requiredModules: [MODULE_HOTEL]', $cms);
        $this->assertStringContainsString('requiredModules: [MODULE_HOTEL, MODULE_BOOKING]', $cms);

        $this->assertStringContainsString('test_hotel_module_is_exposed_for_hotel_project_type_and_blocked_for_ecommerce_override', $featureTest);
        $this->assertStringContainsString('test_hotel_project_type_allows_hotel_module_and_ecommerce_type_denies_it_when_framework_enabled', $unitTest);
        $this->assertStringContainsString('CMS hotel builder component coverage contracts', $frontendContract);
    }
}
