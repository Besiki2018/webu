<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalRealEstateModuleComponentsP5F4Test extends TestCase
{
    public function test_p5_f4_02_real_estate_module_and_components_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/UNIVERSAL_REAL_ESTATE_MODULE_COMPONENTS_P5_F4_02.md');
        $this->assertFileExists($docPath);

        $doc = File::get($docPath);
        $registry = File::get(base_path('app/Cms/Services/CmsModuleRegistryService.php'));
        $projectTypeFlags = File::get(base_path('app/Cms/Services/CmsProjectTypeModuleFeatureFlagService.php'));
        $templateNormalizer = File::get(base_path('app/Services/TemplateMetadataNormalizerService.php'));
        $cms = File::get(base_path('resources/js/Pages/Project/Cms.tsx'));
        $featureTest = File::get(base_path('tests/Feature/Cms/CmsModuleRegistryTest.php'));
        $unitTest = File::get(base_path('tests/Unit/CmsProjectTypeModuleFeatureFlagServiceTest.php'));
        $frontendContract = File::get(base_path('resources/js/Pages/Project/__tests__/CmsRealEstateBuilderCoverage.contract.test.ts'));

        $this->assertStringContainsString('P5-F4-02', $doc);
        $this->assertStringContainsString('MODULE_REAL_ESTATE', $doc);
        $this->assertStringContainsString('CmsModuleRegistryService', $doc);
        $this->assertStringContainsString('CmsProjectTypeModuleFeatureFlagService', $doc);
        $this->assertStringContainsString('TemplateMetadataNormalizerService', $doc);
        $this->assertStringContainsString('Cms.tsx', $doc);
        $this->assertStringContainsString('project_type_allowed', $doc);
        $this->assertStringContainsString('webu_realestate_map_01', $doc);
        $this->assertStringContainsString('{{route.params.slug}}', $doc);

        $this->assertStringContainsString("public const MODULE_REAL_ESTATE = 'real_estate';", $registry);
        $this->assertStringContainsString("'key' => self::MODULE_REAL_ESTATE", $registry);
        $this->assertStringContainsString("'group' => 'content_verticals'", $registry);
        $this->assertStringContainsString("\$templateCategory, ['real_estate', 'realestate']", $registry);

        $this->assertStringContainsString("'real_estate' => [", $projectTypeFlags);
        $this->assertStringContainsString('CmsModuleRegistryService::MODULE_REAL_ESTATE', $projectTypeFlags);
        $this->assertStringContainsString("'real_estate'", $templateNormalizer);

        $this->assertStringContainsString('BUILDER_REAL_ESTATE_DISCOVERY_LIBRARY_SECTIONS', $cms);
        $this->assertStringContainsString('MODULE_REAL_ESTATE', $cms);
        $this->assertStringContainsString('syntheticRealEstateSectionKeySet', $cms);
        $this->assertStringContainsString('createSyntheticRealEstatePlaceholder', $cms);
        $this->assertStringContainsString('applyRealEstatePreviewState', $cms);
        $this->assertStringContainsString('data-webby-realestate-properties', $cms);
        $this->assertStringContainsString('data-webby-realestate-map', $cms);
        $this->assertStringContainsString('builderSectionAvailabilityMatrix', $cms);
        $this->assertStringContainsString('isBuilderSectionAllowedByProjectTypeAvailabilityMatrix', $cms);
        $this->assertStringContainsString("key: 'real_estate'", $cms);
        $this->assertStringContainsString('requiredModules: [MODULE_REAL_ESTATE]', $cms);

        $this->assertStringContainsString('test_real_estate_module_is_exposed_for_real_estate_project_type_and_blocked_for_ecommerce_override', $featureTest);
        $this->assertStringContainsString('test_real_estate_project_type_allows_real_estate_module_and_ecommerce_type_denies_it_when_framework_enabled', $unitTest);
        $this->assertStringContainsString('CMS real-estate builder component coverage contracts', $frontendContract);
    }
}
