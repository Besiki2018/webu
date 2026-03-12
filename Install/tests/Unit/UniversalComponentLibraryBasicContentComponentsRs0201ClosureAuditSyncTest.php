<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryBasicContentComponentsRs0201ClosureAuditSyncTest extends TestCase
{
    public function test_rs_02_01_closure_audit_locks_basic_component_parity_button_state_and_html_sanitization_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BASIC_CONTENT_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_02_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BASIC_CONTENT_COMPONENTS_PARITY_STATE_SANITIZATION_CLOSURE_AUDIT_RS_02_01_2026_02_26.md');
        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $basicContentContractPath = base_path('resources/js/Pages/Project/__tests__/CmsBasicContentComponentsParity.contract.test.ts');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $baselineDocPath,
            $closureDocPath,
            $cmsPath,
            $basicContentContractPath,
            base_path('resources/js/Pages/Project/__tests__/CmsPhase3ResponsiveStateWrapperSummary.contract.test.ts'),
            base_path('resources/js/Pages/Project/__tests__/CmsStateOverridesParity.contract.test.ts'),
            base_path('resources/js/Pages/Project/__tests__/CmsResponsiveStatePreviewRuntimeParity.contract.test.ts'),
            base_path('resources/js/Pages/Project/__tests__/CmsEcommerceBuilderCoverage.contract.test.ts'),
            base_path('resources/js/Pages/Project/__tests__/CmsGeneralUtilitiesBuilderCoverage.contract.test.ts'),
            base_path('tests/Unit/UniversalComponentLibraryBasicContentComponentsRs0201BaselineGapAuditSyncTest.php'),
            base_path('tests/Unit/Phase3ResponsiveStateWrapperSummaryStatusSyncTest.php'),
            base_path('tests/Unit/UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php'),
            base_path('docs/qa/CMS_PHASE3_RESPONSIVE_STATE_WRAPPER_SUMMARY.md'),
            base_path('docs/qa/CMS_CONTROL_GROUP_STANDARDS_D3.md'),
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $closureDoc = File::get($closureDocPath);
        $cms = File::get($cmsPath);
        $basicContentContract = File::get($basicContentContractPath);

        foreach ([
            '# 2) BASIC CONTENT COMPONENTS',
            '## 2.3 basic.button',
            'States: normal/hover/focus/active',
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
            '`✅` `basic.button` state coverage (`normal/hover/focus/active`) now has dedicated component-specific contract evidence',
            '`✅` `basic.html` deny-path evidence is now component-specific in builder preview path',
            '`✅` DoD closure is achieved via mapped+tested parity evidence and deny-path verification',
            '`⚠️` source control exactness gaps remain',
            '`🧪` RS-02-01 closure sync lock added',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Goal (`RS-02-01` Closure Pass)',
            '## ✅ What Was Done (Closure Pass)',
            '## Executive Result (`RS-02-01`)',
            '`RS-02-01` is now **DoD-complete** as a parity verification task.',
            '## Component-Level Contract Coverage Additions (Closure Evidence)',
            'CmsBasicContentComponentsParity.contract.test.ts',
            '## `basic.html` Sanitization Deny-Path Closure (Scripts Disabled by Default)',
            'sanitizeGeneralHtmlPreviewCode(value, allowScripts = false)',
            'code.textContent = sanitizeGeneralHtmlPreviewCode(effectiveProps.html_code, false);',
            "code.setAttribute('data-webu-scripts-allowed', 'false');",
            '## DoD Closure Matrix (`RS-02-01`)',
            'button state coverage (`normal/hover/focus/active`)',
            'HTML sanitization deny-path evidence',
            '## Remaining Exactness Gaps (Truthful, Non-Blocking for `RS-02-01` DoD)',
            '## DoD Verdict (`RS-02-01`)',
            '`RS-02-01` passes and is `DONE`.',
            '## Result',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            'const sanitizeGeneralHtmlPreviewCode = useCallback((value: unknown, allowScripts = false): string => {',
            '.replace(/<script\\b',
            '.replace(/\\bon[a-z0-9_-]+',
            '.replace(/javascript\\s*:/gi, \'\');',
            "if (normalizedSectionType === 'webu_general_html_01')",
            'code.textContent = sanitizeGeneralHtmlPreviewCode(effectiveProps.html_code, false);',
            "code.setAttribute('data-webu-scripts-allowed', 'false');",
            "if (normalized === 'webu_general_button_01')",
            '[data-webu-field="button"], [data-webu-field="primary_cta"]',
            "const [builderPreviewInteractionState, setBuilderPreviewInteractionState] = useState<BuilderInteractionPreviewState>('normal');",
            "container.setAttribute('data-webu-builder-interaction-state-preview', builderPreviewInteractionState);",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'CMS basic content components parity contracts',
            'webu_general_heading_01',
            'webu_general_text_01',
            'webu_general_button_01',
            'webu_general_image_01',
            'webu_general_video_01',
            'webu_general_icon_01',
            'webu_general_icon_box_01',
            'webu_general_html_01',
            'builderPreviewInteractionState',
            'data-webu-builder-interaction-state-preview',
            'sanitizeGeneralHtmlPreviewCode',
            'data-webu-scripts-allowed',
            "expect(cms).not.toContain('code.innerHTML = effectiveProps.html_code');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $basicContentContract);
        }
    }
}
