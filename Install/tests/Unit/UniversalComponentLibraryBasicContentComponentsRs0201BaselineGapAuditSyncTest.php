<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryBasicContentComponentsRs0201BaselineGapAuditSyncTest extends TestCase
{
    public function test_rs_02_01_progress_audit_doc_locks_basic_content_component_parity_baseline_and_html_sanitization_gap_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BASIC_CONTENT_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_02_01_2026_02_25.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $responsiveStateWrapperDocPath = base_path('docs/qa/CMS_PHASE3_RESPONSIVE_STATE_WRAPPER_SUMMARY.md');
        $controlGroupStandardsDocPath = base_path('docs/qa/CMS_CONTROL_GROUP_STANDARDS_D3.md');
        $responsiveStateWrapperSyncTestPath = base_path('tests/Unit/Phase3ResponsiveStateWrapperSummaryStatusSyncTest.php');
        $responsiveStateWrapperContractPath = base_path('resources/js/Pages/Project/__tests__/CmsPhase3ResponsiveStateWrapperSummary.contract.test.ts');
        $builderCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts');
        $generalUtilitiesCoverageContractPath = base_path('resources/js/Pages/Project/__tests__/CmsGeneralUtilitiesBuilderCoverage.contract.test.ts');
        $componentGapAuditTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php');
        $aliasMapJsonPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $securityValidatorServicePath = base_path('app/Services/CmsAiGeneratedComponentSecurityValidationService.php');
        $securityValidatorTestPath = base_path('tests/Unit/CmsAiGeneratedComponentSecurityValidationServiceTest.php');
        $registryWorkflowTestPath = base_path('tests/Unit/CmsAiComponentRegistryIntegrationWorkflowServiceTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $cmsPath,
            $responsiveStateWrapperDocPath,
            $controlGroupStandardsDocPath,
            $responsiveStateWrapperSyncTestPath,
            $responsiveStateWrapperContractPath,
            $builderCoverageContractPath,
            $generalUtilitiesCoverageContractPath,
            $componentGapAuditTestPath,
            $aliasMapJsonPath,
            $securityValidatorServicePath,
            $securityValidatorTestPath,
            $registryWorkflowTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $cms = File::get($cmsPath);
        $responsiveStateWrapperDoc = File::get($responsiveStateWrapperDocPath);
        $controlGroupStandardsDoc = File::get($controlGroupStandardsDocPath);
        $responsiveStateWrapperSyncTest = File::get($responsiveStateWrapperSyncTestPath);
        $responsiveStateWrapperContract = File::get($responsiveStateWrapperContractPath);
        $builderCoverageContract = File::get($builderCoverageContractPath);
        $generalUtilitiesCoverageContract = File::get($generalUtilitiesCoverageContractPath);
        $componentGapAuditTest = File::get($componentGapAuditTestPath);
        $aliasMapJson = File::get($aliasMapJsonPath);
        $securityValidatorService = File::get($securityValidatorServicePath);
        $securityValidatorTest = File::get($securityValidatorTestPath);
        $registryWorkflowTest = File::get($registryWorkflowTestPath);

        foreach ([
            '# 2) BASIC CONTENT COMPONENTS',
            '## 2.1 basic.heading',
            'Content: text, tag (h1-h6), align',
            'Style: typography, color, textShadow',
            'States: hover color (optional)',
            '## 2.2 basic.text',
            'Content: text (rich text basic), align',
            'Style: typography, color, link color, list styles',
            '## 2.3 basic.button',
            'Content: label, link (url/target/nofollow), icon (optional)',
            'Style: typography, bg, border, radius, padding, hover styles',
            'States: normal/hover/focus/active',
            '## 2.4 basic.image',
            'Content: src (media), alt, link (optional), fit (cover/contain)',
            'Style: radius, shadow, border, hover zoom (optional)',
            '## 2.5 basic.video',
            'Content: provider (youtube/vimeo/file), url, autoplay, controls',
            '## 2.6 basic.icon',
            'Content: icon, size, link (optional)',
            'Style: color, hover color',
            '## 2.7 basic.iconBox',
            'Content: icon, title, text, link',
            '## 2.8 basic.html',
            'Content: html (sanitized)',
            'Advanced: allow scripts? (NO by default)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-02-01` (`DONE`, `P0`)',
            'UNIVERSAL_COMPONENT_LIBRARY_BASIC_CONTENT_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_02_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_BASIC_CONTENT_COMPONENTS_PARITY_STATE_SANITIZATION_CLOSURE_AUDIT_RS_02_01_2026_02_26.md',
            'UniversalComponentLibraryBasicContentComponentsRs0201BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryBasicContentComponentsRs0201ClosureAuditSyncTest.php',
            'CmsBasicContentComponentsParity.contract.test.ts',
            'CmsGeneralUtilitiesBuilderCoverage.contract.test.ts',
            'CmsAiGeneratedComponentSecurityValidationService.php',
            'CmsAiGeneratedComponentSecurityValidationServiceTest.php',
            'CmsAiComponentRegistryIntegrationWorkflowServiceTest.php',
            '`✅` all 8 basic content components remain parity-mapped with explicit `pass/partial` field/state rows',
            '`✅` `basic.button` state coverage (`normal/hover/focus/active`) now has dedicated component-specific contract evidence',
            '`✅` `basic.html` deny-path evidence is now component-specific in builder preview path',
            '`⚠️` source control exactness gaps remain',
            '`🧪` RS-02-01 closure sync lock added',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '## Scope',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            '## Audit Inputs Reviewed',
            '## ✅ What Was Done (This Pass)',
            '## Executive Result (`RS-02-01`)',
            '## Component Parity Checklist (Field/State Baseline)',
            '### Checklist Matrix (`content/style/states/tests/security`)',
            '`basic.heading`',
            '`basic.text`',
            '`basic.button`',
            '`basic.image`',
            '`basic.video`',
            '`basic.icon`',
            '`basic.iconBox`',
            '`basic.html`',
            '`webu_general_heading_01`',
            '`webu_general_text_01`',
            '`webu_general_button_01`',
            '`webu_general_image_01`',
            '`webu_general_video_01`',
            '`webu_general_icon_01`',
            '`webu_general_icon_box_01`',
            '`webu_general_html_01`',
            '## Button State Coverage Status (DoD Line)',
            'states.normal|hover|focus|active',
            'applyGeneralFoundationComponentStylePresetsPreview',
            '[data-webu-field="button"]',
            'Verdict: `partial`',
            '## `basic.html` Sanitization Policy Verification (DoD Line)',
            'builder preview representation inert',
            'Adjacent deny-path security evidence (not equivalent to `basic.html` runtime component sanitization)',
            'unsafe_renderer_html_script_tag',
            'Why this does **not** close `RS-02-01`',
            'Verdict: `missing`',
            '## DoD Verdict (`RS-02-01`)',
            'Conclusion: `RS-02-01` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'GENERAL_FOUNDATION_BASELINE_SCHEMA_PROPERTIES',
            'applyBuilderPreviewValueToNode',
            'applyBuilderPreviewFieldByKey',
            'applyBuilderPreviewSectionProps',
            'const [builderPreviewInteractionState, setBuilderPreviewInteractionState]',
            'resolveGeneralFoundationRuntimeStyleResolution',
            'interactionState: builderPreviewInteractionState',
            'applyGeneralFoundationComponentStylePresetsPreview',
            '[data-webu-field="button"], [data-webu-field="primary_cta"]',
            'urlLikeKey = /(url|href|link|src|image|logo)/i.test(keyLower);',
            'webu_general_heading_01',
            'webu_general_text_01',
            'webu_general_button_01',
            'webu_general_image_01',
            'webu_general_video_01',
            'webu_general_icon_01',
            'webu_general_html_01',
            'webu_general_icon_box_01',
            "if (normalized === 'webu_general_heading_01')",
            "if (normalized === 'webu_general_text_01')",
            "if (normalized === 'webu_general_image_01')",
            "if (normalized === 'webu_general_button_01')",
            "if (normalized === 'webu_general_video_01')",
            "if (normalized === 'webu_general_icon_01')",
            "if (normalized === 'webu_general_html_01')",
            "if (normalized === 'webu_general_icon_box_01')",
            "if (normalizedSectionType === 'webu_general_icon_01')",
            "if (normalizedSectionType === 'webu_general_html_01')",
            "if (normalizedSectionType === 'webu_general_icon_box_01')",
            "code.setAttribute('data-webu-role', 'html-code')",
            "code.setAttribute('data-webu-field', 'html_code')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'Canonical state override shape exists (`normal/hover/focus/active`)',
            'base -> responsive -> state',
            'CMS_CONTROL_GROUP_STANDARDS_D3.md',
        ] as $needle) {
            $this->assertStringContainsString($needle, $responsiveStateWrapperDoc);
        }

        $this->assertStringContainsString('states.normal|hover|focus|active', $controlGroupStandardsDoc);
        $this->assertStringContainsString('responsive.desktop|tablet|mobile', $controlGroupStandardsDoc);
        $this->assertStringContainsString('states.hover', $responsiveStateWrapperSyncTest);
        $this->assertStringContainsString('responsive/state wrapper summary contracts', strtolower($responsiveStateWrapperContract));

        foreach ([
            'webu_general_heading_01',
            'webu_general_text_01',
            'webu_general_button_01',
            'webu_general_icon_01',
            'webu_general_image_01',
            'webu_general_video_01',
            'webu_general_html_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderCoverageContract);
        }

        foreach ([
            'webu_general_icon_box_01',
            "if (normalized === 'webu_general_icon_box_01')",
            "if (normalizedSectionType === 'webu_general_icon_box_01')",
            '| basic.iconBox | equivalent | `webu_general_icon_box_01` |',
        ] as $needle) {
            $this->assertStringContainsString($needle, $generalUtilitiesCoverageContract);
        }

        foreach ([
            "rowsByKey['basic.iconBox']",
            '`webu_general_icon_box_01`',
        ] as $needle) {
            $this->assertStringContainsString($needle, $componentGapAuditTest);
        }

        foreach ([
            '"source_component_key": "basic.heading"',
            '"source_component_key": "basic.text"',
            '"source_component_key": "basic.button"',
            '"source_component_key": "basic.image"',
            '"source_component_key": "basic.video"',
            '"source_component_key": "basic.icon"',
            '"source_component_key": "basic.iconBox"',
            '"source_component_key": "basic.html"',
            '"webu_general_icon_box_01"',
            '"webu_general_html_01"',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMapJson);
        }

        foreach ([
            'validateRendererHtml',
            'unsafe_renderer_html_script_tag',
            'unsafe_renderer_html_inline_event_handler',
            'unsafe_renderer_html_javascript_url',
        ] as $needle) {
            $this->assertStringContainsString($needle, $securityValidatorService);
        }

        foreach ([
            'unsafe_renderer_html_script_tag',
            'unsafe_renderer_html_inline_event_handler',
            'test_it_rejects_unsafe_renderer_html_template_refs_and_custom_css',
        ] as $needle) {
            $this->assertStringContainsString($needle, $securityValidatorTest);
        }

        foreach ([
            'unsafe_renderer_html_script_tag',
            'generated_component_validation_failed',
            'security_validation.errors',
        ] as $needle) {
            $this->assertStringContainsString($needle, $registryWorkflowTest);
        }
    }
}
