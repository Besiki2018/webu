<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalPortfolioModuleComponentsP5F4Test extends TestCase
{
    public function test_p5_f4_01_portfolio_module_and_components_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/UNIVERSAL_PORTFOLIO_MODULE_COMPONENTS_P5_F4_01.md');
        $this->assertFileExists($docPath);

        $doc = File::get($docPath);
        $registry = File::get(base_path('app/Cms/Services/CmsModuleRegistryService.php'));
        $projectTypeFlags = File::get(base_path('app/Cms/Services/CmsProjectTypeModuleFeatureFlagService.php'));
        $templateNormalizer = File::get(base_path('app/Services/TemplateMetadataNormalizerService.php'));
        $cms = File::get(base_path('resources/js/Pages/Project/Cms.tsx'));
        $featureTest = File::get(base_path('tests/Feature/Cms/CmsModuleRegistryTest.php'));
        $unitTest = File::get(base_path('tests/Unit/CmsProjectTypeModuleFeatureFlagServiceTest.php'));
        $frontendContract = File::get(base_path('resources/js/Pages/Project/__tests__/CmsPortfolioBuilderCoverage.contract.test.ts'));

        $this->assertStringContainsString('P5-F4-01', $doc);
        $this->assertStringContainsString('MODULE_PORTFOLIO', $doc);
        $this->assertStringContainsString('CmsModuleRegistryService', $doc);
        $this->assertStringContainsString('CmsProjectTypeModuleFeatureFlagService', $doc);
        $this->assertStringContainsString('TemplateMetadataNormalizerService', $doc);
        $this->assertStringContainsString('Cms.tsx', $doc);
        $this->assertStringContainsString('project_type_allowed', $doc);
        $this->assertStringContainsString('webu_portfolio_projects_grid_01', $doc);

        $this->assertStringContainsString("public const MODULE_PORTFOLIO = 'portfolio';", $registry);
        $this->assertStringContainsString("'key' => self::MODULE_PORTFOLIO", $registry);
        $this->assertStringContainsString("'group' => 'content_verticals'", $registry);
        $this->assertStringContainsString("\$templateCategory === 'portfolio' && \$moduleKey === self::MODULE_PORTFOLIO", $registry);

        $this->assertStringContainsString("'portfolio' => [", $projectTypeFlags);
        $this->assertStringContainsString('CmsModuleRegistryService::MODULE_PORTFOLIO', $projectTypeFlags);
        $this->assertStringContainsString("'portfolio',", $templateNormalizer);

        $this->assertStringContainsString('BUILDER_PORTFOLIO_DISCOVERY_LIBRARY_SECTIONS', $cms);
        $this->assertStringContainsString('MODULE_PORTFOLIO', $cms);
        $this->assertStringContainsString('syntheticPortfolioSectionKeySet', $cms);
        $this->assertStringContainsString('createSyntheticPortfolioPlaceholder', $cms);
        $this->assertStringContainsString('applyPortfolioPreviewState', $cms);
        $this->assertStringContainsString('data-webby-portfolio-projects', $cms);
        $this->assertStringContainsString('data-webby-portfolio-gallery', $cms);
        $this->assertStringContainsString('builderSectionAvailabilityMatrix', $cms);
        $this->assertStringContainsString('isBuilderSectionAllowedByProjectTypeAvailabilityMatrix', $cms);
        $this->assertStringContainsString("key: 'portfolio'", $cms);
        $this->assertStringContainsString('requiredModules: [MODULE_PORTFOLIO]', $cms);

        $this->assertStringContainsString('test_portfolio_module_is_exposed_for_portfolio_project_type_and_blocked_for_ecommerce_override', $featureTest);
        $this->assertStringContainsString('test_portfolio_project_type_allows_portfolio_module_and_ecommerce_type_denies_it_when_framework_enabled', $unitTest);
        $this->assertStringContainsString('CMS portfolio builder component coverage contracts', $frontendContract);
    }
}
