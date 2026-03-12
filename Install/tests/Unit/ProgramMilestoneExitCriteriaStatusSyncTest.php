<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class ProgramMilestoneExitCriteriaStatusSyncTest extends TestCase
{
    public function test_milestone_a_b_c_exit_criteria_statuses_are_synced_to_existing_evidence(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $paths = [
            base_path('tests/Feature/Templates/TemplateStorefrontE2eFlowMatrixSmokeTest.php'),
            base_path('tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php'),
            base_path('tests/Feature/Cms/CmsAiGeneratedSiteBuilderEditabilityTest.php'),
            base_path('resources/js/Pages/Project/__tests__/CmsLayoutStability.contract.test.ts'),
            base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts'),
            base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php'),
            base_path('tests/Unit/UniversalServicesBookingContractsP5F3Test.php'),
            base_path('docs/qa/CMS_TEMPLATE_RUNTIME_CONTRACT_LOCK.md'),
            base_path('docs/architecture/CMS_CANONICAL_BINDING_RESOLVER_CONTRACT_V1.md'),
        ];

        $this->assertFileExists($roadmapPath);
        foreach ($paths as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('- ✅ Builder components (core + ecommerce) are builder-editable and runtime-renderable', $roadmap);
        $this->assertStringContainsString('- ✅ Preview and publish flows are stable', $roadmap);
        $this->assertStringContainsString('- ✅ Product → Cart → Checkout → Order works end-to-end', $roadmap);
        $this->assertStringContainsString('- ✅ Auth/account/orders pages work for customer flows', $roadmap);
        $this->assertStringContainsString('- ✅ Template import/runtime validation and smoke tests are in CI', $roadmap);
        $this->assertStringContainsString('- ✅ No known page-level horizontal overflow / blocking UI regressions in CMS critical tabs', $roadmap);
        $this->assertStringContainsString('- ✅ Documentation exists for template contract + component/binding contract', $roadmap);
        $this->assertStringContainsString('- ✅ AI-generated site remains editable in builder without special cases', $roadmap);
        $this->assertStringContainsString('- ✅ Project-type feature flags control module/component visibility', $roadmap);
        $this->assertStringContainsString('- ✅ At least one non-ecommerce vertical works end-to-end (recommended: services+booking)', $roadmap);
        $this->assertStringContainsString('- ✅ Universal component taxonomy is active without duplicating builder systems', $roadmap);
    }
}
