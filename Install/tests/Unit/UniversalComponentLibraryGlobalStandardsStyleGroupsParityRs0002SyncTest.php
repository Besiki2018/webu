<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryGlobalStandardsStyleGroupsParityRs0002SyncTest extends TestCase
{
    public function test_rs_00_02_audit_doc_locks_universal_style_groups_matrix_ownership_and_follow_up_backlog_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_STYLE_GROUP_PARITY_AUDIT_RS_00_02_2026_02_25.md');

        $wrapperParityDocPath = base_path('docs/qa/CMS_PHASE3_WRAPPER_CONTROL_GROUP_PARITY_SUMMARY.md');
        $responsiveStateWrapperDocPath = base_path('docs/qa/CMS_PHASE3_RESPONSIVE_STATE_WRAPPER_SUMMARY.md');
        $advancedControlsDocPath = base_path('docs/qa/CMS_ADVANCED_CONTROLS_D3_BASELINE.md');
        $customCssDocPath = base_path('docs/qa/CMS_CUSTOM_CSS_SCOPING_D3_BASELINE.md');
        $presetsDocPath = base_path('docs/qa/CMS_REUSABLE_STYLE_PRESETS_D3_BASELINE.md');
        $controlGroupStandardsDocPath = base_path('docs/qa/CMS_CONTROL_GROUP_STANDARDS_D3.md');
        $controlMetadataDocPath = base_path('docs/architecture/CMS_CANONICAL_CONTROL_METADATA_V1.md');
        $themeTokenModelDocPath = base_path('docs/architecture/CMS_THEME_TOKEN_MODEL_VERSIONING.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $templateImportServicePath = base_path('app/Services/TemplateImportService.php');

        $wrapperParityContractPath = base_path('resources/js/Pages/Project/__tests__/CmsPhase3ControlGroupParitySummary.contract.test.ts');
        $responsiveTypographyContractPath = base_path('resources/js/Pages/Project/__tests__/CmsResponsiveTypographyOverrides.contract.test.ts');
        $stateTypographyContractPath = base_path('resources/js/Pages/Project/__tests__/CmsStateTypographyOverrides.contract.test.ts');
        $advancedNormalizationContractPath = base_path('resources/js/Pages/Project/__tests__/CmsAdvancedControlsNormalization.contract.test.ts');
        $advancedParityContractPath = base_path('resources/js/Pages/Project/__tests__/CmsAdvancedControlsParity.contract.test.ts');
        $customCssParityContractPath = base_path('resources/js/Pages/Project/__tests__/CmsCustomCssScopingParity.contract.test.ts');
        $presetsParityContractPath = base_path('resources/js/Pages/Project/__tests__/CmsReusableStylePresetsParity.contract.test.ts');
        $dynamicThemeUxContractPath = base_path('resources/js/Pages/Project/__tests__/CmsDynamicAndThemeUx.contract.test.ts');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $wrapperParityDocPath,
            $responsiveStateWrapperDocPath,
            $advancedControlsDocPath,
            $customCssDocPath,
            $presetsDocPath,
            $controlGroupStandardsDocPath,
            $controlMetadataDocPath,
            $themeTokenModelDocPath,
            $cmsPath,
            $templateImportServicePath,
            $wrapperParityContractPath,
            $responsiveTypographyContractPath,
            $stateTypographyContractPath,
            $advancedNormalizationContractPath,
            $advancedParityContractPath,
            $customCssParityContractPath,
            $presetsParityContractPath,
            $dynamicThemeUxContractPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);

        $wrapperParityDoc = File::get($wrapperParityDocPath);
        $responsiveStateWrapperDoc = File::get($responsiveStateWrapperDocPath);
        $advancedControlsDoc = File::get($advancedControlsDocPath);
        $customCssDoc = File::get($customCssDocPath);
        $presetsDoc = File::get($presetsDocPath);
        $controlGroupStandardsDoc = File::get($controlGroupStandardsDocPath);
        $controlMetadataDoc = File::get($controlMetadataDocPath);
        $themeTokenModelDoc = File::get($themeTokenModelDocPath);

        $cms = File::get($cmsPath);
        $templateImportService = File::get($templateImportServicePath);

        $wrapperParityContract = File::get($wrapperParityContractPath);
        $responsiveTypographyContract = File::get($responsiveTypographyContractPath);
        $stateTypographyContract = File::get($stateTypographyContractPath);
        $advancedNormalizationContract = File::get($advancedNormalizationContractPath);
        $advancedParityContract = File::get($advancedParityContractPath);
        $customCssParityContract = File::get($customCssParityContractPath);
        $presetsParityContract = File::get($presetsParityContractPath);
        $dynamicThemeUxContract = File::get($dynamicThemeUxContractPath);

        foreach ([
            '## 0.3 Universal Style Groups (must exist)',
            'Typography: font family, size, weight, transform, decoration, line-height, letter-spacing',
            'Colors: text, links, muted, accent',
            'Background: solid, gradient, image, overlay',
            'Border: width/type/color per side, radius linked/unlinked',
            'Shadow: box/text shadow',
            'Spacing: padding/margin per side, gap',
            'Layout: display, flex/grid controls, width/height, overflow',
            'Position: static/relative/absolute/fixed, z-index, offsets',
            'Effects: opacity, transform, transition',
            'Visibility: per breakpoint, conditional hooks (v2)',
            'Custom CSS: scoped to element id',
            'Attributes: id, aria-*, role, data-*',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-00-02` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_GLOBAL_STANDARDS_STYLE_GROUP_PARITY_AUDIT_RS_00_02_2026_02_25.md',
            'UniversalComponentLibraryGlobalStandardsStyleGroupsParityRs0002SyncTest.php',
            'CMS_PHASE3_WRAPPER_CONTROL_GROUP_PARITY_SUMMARY.md',
            'CMS_ADVANCED_CONTROLS_D3_BASELINE.md',
            'CMS_CUSTOM_CSS_SCOPING_D3_BASELINE.md',
            'CmsPhase3ControlGroupParitySummary.contract.test.ts',
            'CmsCustomCssScopingParity.contract.test.ts',
            '`✅` universal style-group matrix completed with truthful `implemented/partial/missing` labels',
            '`✅` reusable control ownership assigned across `runtime` / `builder` / `ui`',
            '`✅` partial parity and missing sub-controls documented per source group (no false parity claim)',
            '`✅` missing controls backlog captured by component family (`layout`, `basic`, `forms`, `catalog/listing`, shared baseline)',
            '`🧪` targeted RS-00-02 sync lock added',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:6479',
            'PROJECT_ROADMAP_TASKS_KA.md:6480',
            'PROJECT_ROADMAP_TASKS_KA.md:6491',
            '## ✅ What Was Done (Icon Summary)',
            '## Executive Result (`RS-00-02`)',
            '## Ownership Semantics (`runtime` / `builder` / `ui`)',
            '## Universal Style Group Contract Matrix (Source `0.3`)',
            '## Summary Counts (Top-Level Style Groups)',
            '## Missing Controls Backlog (By Component Family)',
            '## Reusable Control Ownership Assignment (DoD Explicit)',
            '## DoD Verdict (`RS-00-02`)',
            'implemented',
            'partial',
            'missing',
            'runtime',
            'builder',
            'ui',
            '`Typography`',
            '`Colors`',
            '`Background`',
            '`Border`',
            '`Shadow`',
            '`Spacing`',
            '`Layout`',
            '`Position`',
            '`Effects`',
            '`Visibility`',
            '`Custom CSS`',
            '`Attributes`',
            'text decoration',
            'gradient',
            'image',
            'per-side',
            'text shadow',
            'conditional hooks (v2)',
            'static/relative/absolute/sticky',
            'Advanced: Opacity (%)',
            'advanced.html_id',
            '`data-*` passthrough',
            '`layout.*` Family',
            '`basic.*` + Text/CTA-Oriented Families',
            'Form / Input-Oriented Families',
            'Catalog / Grid / Listing Families',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            '`implemented`: `1`',
            '`partial`: `11`',
            '`missing`: `0`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        // Wrapper/global parity docs still expose the umbrella evidence claimed by the audit.
        foreach ([
            'PROJECT_ROADMAP_TASKS_KA.md:159',
            'PROJECT_ROADMAP_TASKS_KA.md:160',
            'PROJECT_ROADMAP_TASKS_KA.md:161',
            'PROJECT_ROADMAP_TASKS_KA.md:162',
            'PROJECT_ROADMAP_TASKS_KA.md:163',
            'PROJECT_ROADMAP_TASKS_KA.md:164',
            'typography',
            'spacing',
            'border/radius/shadow',
            'background/overlay',
            'layout/display/position',
            'visibility',
        ] as $needle) {
            $this->assertStringContainsString($needle, $wrapperParityDoc);
        }

        $this->assertStringContainsString('base -> responsive -> state', $responsiveStateWrapperDoc);
        $this->assertStringContainsString('CMS_CONTROL_GROUP_STANDARDS_D3.md', $responsiveStateWrapperDoc);

        foreach ([
            'advanced.opacity_percent',
            'advanced.html_id',
            'advanced.custom_css',
            'attributes (role / aria-* / data-* subset)',
            'visibility (advanced per-device visibility flags)',
            'positioning (position mode + offsets + z-index)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $advancedControlsDoc);
        }

        foreach ([
            'data-webu-builder-custom-css-scoped',
            'data-webu-runtime-custom-css-scoped',
            'Scope id seed prefers `advanced.html_id`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $customCssDoc);
        }

        foreach ([
            'advanced.component_presets',
            '--webu-token-space-*',
            '--webu-token-shadow-*',
        ] as $needle) {
            $this->assertStringContainsString($needle, $presetsDoc);
        }

        foreach ([
            'visibility',
            'positioning',
            'attributes',
            'custom_css',
            'advanced.component_presets',
        ] as $needle) {
            $this->assertStringContainsString($needle, $controlGroupStandardsDoc);
        }

        foreach ([
            'Canonical Fields',
            'group',
            'style',
            'advanced',
            'responsive',
            'states',
        ] as $needle) {
            $this->assertStringContainsString($needle, $controlMetadataDoc);
        }

        foreach ([
            'theme_settings.colors.{primary,secondary,accent,...}',
            'background',
            'text',
            'muted',
            'shadows',
        ] as $needle) {
            $this->assertStringContainsString($needle, $themeTokenModelDoc);
        }

        // Direct implementation anchors supporting the audit truth labels.
        foreach ([
            'GENERAL_FOUNDATION_BASELINE_SCHEMA_PROPERTIES',
            'Style: Background Override',
            'Style: Text Color Override',
            'Style: Border Radius (px)',
            'Style: Vertical Padding (px)',
            'Style: Horizontal Padding (px)',
            'Style: Vertical Margin (px)',
            'Advanced: Opacity (%)',
            'Advanced: HTML ID (Preview Tag)',
            'Advanced: Custom CSS (Scoped to Element)',
            'position_mode: { type: \'string\', title: \'Advanced: Position Mode (static|relative|absolute|sticky)\'',
            'data_testid: { type: \'string\', title: \'Advanced: data-testid\'',
            'text_transform',
            'line_height',
            'letter_spacing_px',
            'font_size_px',
            'font_weight',
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'data-webu-runtime-background-overlay',
            'data-webu-runtime-positioning-mode',
            'data-webu-runtime-custom-css-scoped',
        ] as $needle) {
            $this->assertStringContainsString($needle, $templateImportService);
        }

        // Contract tests/docs used as evidence still contain the anchors cited in the audit.
        foreach ([
            'Phase 3 wrapper control-group parity summary contracts',
            'Overlay Color Override',
            'Border Radius (px)',
            'Vertical Padding (px)',
            'Vertical Margin (px)',
            'component_presets',
            'visibility',
            'positioning',
        ] as $needle) {
            $this->assertStringContainsString($needle, $wrapperParityContract);
        }

        $this->assertStringContainsString('responsive typography override contracts', strtolower($responsiveTypographyContract));
        $this->assertStringContainsString('state override contracts', strtolower($stateTypographyContract));

        foreach ([
            'advanced controls normalization contracts',
            'custom_css',
            'attributes',
            'visibility',
            'positioning',
        ] as $needle) {
            $this->assertStringContainsString(strtolower($needle), strtolower($advancedNormalizationContract));
        }

        foreach ([
            'advanced controls parity contracts',
            'data-webu-builder-positioning-mode',
            'data-webu-runtime-positioning-mode',
        ] as $needle) {
            $this->assertStringContainsString(strtolower($needle), strtolower($advancedParityContract));
        }

        foreach ([
            'custom css scoping parity contracts',
            'data-webu-builder-custom-css-scope-id',
            'data-webu-runtime-custom-css-scope-id',
        ] as $needle) {
            $this->assertStringContainsString(strtolower($needle), strtolower($customCssParityContract));
        }

        foreach ([
            'reusable style presets parity contracts',
            '--webu-token-radius-*',
            '--webu-token-shadow-*',
            'data-webu-builder-component-presets',
            'data-webu-runtime-component-presets',
        ] as $needle) {
            $this->assertStringContainsString(strtolower($needle), strtolower($presetsParityContract));
        }

        foreach ([
            'theme_tokens.colors.primary',
            'theme_tokens.shadows.card',
            'theme_tokens.spacing.md',
            'theme_tokens.radii.base',
        ] as $needle) {
            $this->assertStringContainsString($needle, $dynamicThemeUxContract);
        }
    }
}
