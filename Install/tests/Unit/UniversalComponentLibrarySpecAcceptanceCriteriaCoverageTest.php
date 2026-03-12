<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibrarySpecAcceptanceCriteriaCoverageTest extends TestCase
{
    public function test_component_library_source_spec_acceptance_criteria_are_mapped_to_existing_automated_evidence(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_ACCEPTANCE_CRITERIA_COVERAGE.md');

        $evidencePaths = [
            base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts'),
            base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php'),
            base_path('tests/Feature/Cms/CmsModuleRegistryTest.php'),
            base_path('resources/js/Pages/Project/__tests__/CmsPhase3PrimaryTabsWrapperSummary.contract.test.ts'),
            base_path('tests/Unit/Phase3PrimaryTabsWrapperSummaryStatusSyncTest.php'),
            base_path('resources/js/Pages/Project/__tests__/CmsControlPanelAudit.contract.test.ts'),
            base_path('resources/js/Pages/Project/__tests__/CmsPhase3ResponsiveStateWrapperSummary.contract.test.ts'),
            base_path('resources/js/Pages/Project/__tests__/CmsResponsiveStatePreviewRuntimeParity.contract.test.ts'),
            base_path('resources/js/Pages/Project/__tests__/CmsRuntimeStyleResolutionOrder.contract.test.ts'),
            base_path('tests/Unit/Phase3ResponsiveStateWrapperSummaryStatusSyncTest.php'),
            base_path('resources/js/Pages/Project/__tests__/CmsUniversalBindingNamespaceCompatibility.contract.test.ts'),
            base_path('tests/Unit/UniversalBindingNamespaceCompatibilityP5F5Test.php'),
            base_path('tests/Feature/Cms/CmsPreviewPublishAlignmentTest.php'),
            base_path('tests/Unit/BuilderCmsRuntimeScriptContractsTest.php'),
            base_path('tests/Unit/UniversalAiIndustryComponentMappingP5F5Test.php'),
            base_path('tests/Unit/CmsAiPageGenerationServiceTest.php'),
            base_path('tests/Unit/CmsAiFeatureSpecParserTest.php'),
        ];

        foreach (array_merge([$roadmapPath, $docPath], $evidencePaths) as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $doc = File::get($docPath);

        foreach ([
            '# Acceptance Criteria',
            'Builder shows this library grouped by category',
            'Every component has Content/Style/Advanced',
            'Responsive and states work',
            'Data bindings work with universal backend',
            'AI can assemble correct industry site automatically',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        $this->assertStringContainsString('PROJECT_ROADMAP_TASKS_KA.md:6874', $doc);
        $this->assertStringContainsString('Acceptance Criteria Mapping', $doc);
        $this->assertStringContainsString('Builder shows this library grouped by category', $doc);
        $this->assertStringContainsString('Every component has Content/Style/Advanced', $doc);
        $this->assertStringContainsString('Responsive and states work', $doc);
        $this->assertStringContainsString('Data bindings work with universal backend', $doc);
        $this->assertStringContainsString('AI can assemble correct industry site automatically', $doc);

        foreach ([
            'CmsUniversalComponentLibraryActivation.contract.test.ts',
            'UniversalComponentLibraryActivationP5F5Test.php',
            'CmsModuleRegistryTest.php',
            'CmsPhase3PrimaryTabsWrapperSummary.contract.test.ts',
            'Phase3PrimaryTabsWrapperSummaryStatusSyncTest.php',
            'CmsControlPanelAudit.contract.test.ts',
            'CmsPhase3ResponsiveStateWrapperSummary.contract.test.ts',
            'CmsResponsiveStatePreviewRuntimeParity.contract.test.ts',
            'CmsRuntimeStyleResolutionOrder.contract.test.ts',
            'UniversalBindingNamespaceCompatibilityP5F5Test.php',
            'CmsPreviewPublishAlignmentTest.php',
            'BuilderCmsRuntimeScriptContractsTest.php',
            'UniversalAiIndustryComponentMappingP5F5Test.php',
            'CmsAiPageGenerationServiceTest.php',
            'CmsAiFeatureSpecParserTest.php',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        $this->assertStringContainsString('**covered by automated evidence**', $doc);
    }
}
