<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class Phase3BuilderAdvancedControlsSummaryStatusSyncTest extends TestCase
{
    public function test_phase3_summary_lines_for_advanced_controls_and_reusable_presets_match_d3_evidence(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $advancedDocPath = base_path('docs/qa/CMS_ADVANCED_CONTROLS_D3_BASELINE.md');
        $customCssDocPath = base_path('docs/qa/CMS_CUSTOM_CSS_SCOPING_D3_BASELINE.md');
        $presetsDocPath = base_path('docs/qa/CMS_REUSABLE_STYLE_PRESETS_D3_BASELINE.md');
        $advancedContractPath = base_path('resources/js/Pages/Project/__tests__/CmsAdvancedControlsNormalization.contract.test.ts');
        $customCssContractPath = base_path('resources/js/Pages/Project/__tests__/CmsCustomCssScopingParity.contract.test.ts');
        $presetsContractPath = base_path('resources/js/Pages/Project/__tests__/CmsReusableStylePresetsParity.contract.test.ts');

        foreach ([
            $roadmapPath,
            $advancedDocPath,
            $customCssDocPath,
            $presetsDocPath,
            $advancedContractPath,
            $customCssContractPath,
            $presetsContractPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $advancedDoc = File::get($advancedDocPath);
        $customCssDoc = File::get($customCssDocPath);
        $presetsDoc = File::get($presetsDocPath);
        $advancedContract = File::get($advancedContractPath);
        $customCssContract = File::get($customCssContractPath);
        $presetsContract = File::get($presetsContractPath);

        $this->assertStringContainsString('- ✅ Custom CSS + HTML attributes for all relevant components', $roadmap);
        $this->assertStringContainsString('- ✅ Reusable presets for core UI components (button/card/input)', $roadmap);

        $this->assertStringContainsString('P3-D3-01', $advancedDoc);
        $this->assertStringContainsString('custom_css', $advancedDoc);
        $this->assertStringContainsString('attributes', $advancedDoc);
        $this->assertStringContainsString('visibility', $advancedDoc);

        $this->assertStringContainsString('P3-D3-03', $customCssDoc);
        $this->assertStringContainsString('scoping', strtolower($customCssDoc));

        $this->assertStringContainsString('P3-D3-02', $presetsDoc);
        $this->assertStringContainsString('button', strtolower($presetsDoc));
        $this->assertStringContainsString('card', strtolower($presetsDoc));
        $this->assertStringContainsString('input', strtolower($presetsDoc));

        $this->assertStringContainsString('CMS advanced controls normalization contracts', $advancedContract);
        $this->assertStringContainsString('CMS custom CSS scoping parity contracts', $customCssContract);
        $this->assertStringContainsString('CMS reusable style presets parity contracts', $presetsContract);
    }
}
