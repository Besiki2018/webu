<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryActivationP5F5Test extends TestCase
{
    public function test_p5_f5_01_and_p5_f5_02_universal_component_library_activation_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/UNIVERSAL_COMPONENT_LIBRARY_ACTIVATION_P5_F5_01_F5_02.md');
        $this->assertFileExists($docPath);

        $doc = File::get($docPath);
        $cms = File::get(base_path('resources/js/Pages/Project/Cms.tsx'));
        $frontendContract = File::get(base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts'));
        $roadmap = File::get(base_path('../PROJECT_ROADMAP_TASKS_KA.md'));

        $this->assertStringContainsString('P5-F5-01', $doc);
        $this->assertStringContainsString('P5-F5-02', $doc);
        $this->assertStringContainsString('BuilderUniversalTaxonomyGroupKey', $doc);
        $this->assertStringContainsString('BUILDER_UNIVERSAL_TAXONOMY_GROUP_ORDER', $doc);
        $this->assertStringContainsString('BUILDER_UNIVERSAL_TAXONOMY_GROUP_LABELS', $doc);
        $this->assertStringContainsString('builderSectionAvailabilityMatrix', $doc);
        $this->assertStringContainsString('isBuilderSectionAllowedByProjectTypeAvailabilityMatrix', $doc);
        $this->assertStringContainsString('project_type_allowed', $doc);
        $this->assertStringContainsString('P5-F5-03', $doc);
        $this->assertStringContainsString('P5-F5-04', $doc);

        $this->assertStringContainsString('type BuilderUniversalTaxonomyGroupKey =', $cms);
        $this->assertStringContainsString('BUILDER_UNIVERSAL_TAXONOMY_GROUP_ORDER', $cms);
        $this->assertStringContainsString('BUILDER_UNIVERSAL_TAXONOMY_GROUP_LABELS', $cms);
        $this->assertStringContainsString('builderSectionAvailabilityMatrix', $cms);
        $this->assertStringContainsString('isBuilderSectionAllowedByProjectTypeAvailabilityMatrix', $cms);
        $this->assertStringContainsString('taxonomyGroupItems', $cms);
        $this->assertStringContainsString('BUILDER_UNIVERSAL_TAXONOMY_GROUP_ORDER.forEach', $cms);
        $this->assertStringContainsString('return isBuilderSectionAllowedByProjectTypeAvailabilityMatrix(normalizedKey);', $cms);
        $this->assertStringContainsString("key: 'ecommerce'", $cms);
        $this->assertStringContainsString("key: 'booking'", $cms);
        $this->assertStringContainsString("key: 'portfolio'", $cms);
        $this->assertStringContainsString("key: 'real_estate'", $cms);
        $this->assertStringContainsString("key: 'restaurant'", $cms);
        $this->assertStringContainsString("key: 'hotel'", $cms);
        $this->assertStringContainsString("key: 'restaurant_reservation'", $cms);
        $this->assertStringContainsString("key: 'hotel_reservation'", $cms);
        $this->assertStringContainsString('requiredModules: [MODULE_ECOMMERCE]', $cms);
        $this->assertStringContainsString('requiredModules: [MODULE_HOTEL, MODULE_BOOKING]', $cms);

        $this->assertStringContainsString('CMS universal component library activation contracts', $frontendContract);
        $this->assertStringContainsString('builderSectionAvailabilityMatrix', $frontendContract);
        $this->assertStringContainsString('BUILDER_UNIVERSAL_TAXONOMY_GROUP_ORDER', $frontendContract);

        $this->assertStringContainsString('- ✅ Register all universal components grouped by category', $roadmap);
        $this->assertStringContainsString('- ✅ Industry-specific enable/disable by `project.type`', $roadmap);
        $this->assertStringContainsString("`P5-F5-01` (✅ `DONE`) Register universal taxonomy groups in builder library UI.", $roadmap);
        $this->assertStringContainsString("`P5-F5-02` (✅ `DONE`) Component availability matrix by project type.", $roadmap);
    }
}

