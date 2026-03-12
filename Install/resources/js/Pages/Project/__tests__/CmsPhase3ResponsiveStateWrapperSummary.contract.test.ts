import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');

function read(relativePath: string): string {
    return fs.readFileSync(path.join(ROOT, relativePath), 'utf8');
}

describe('CMS Phase 3 responsive/state wrapper summary contracts', () => {
    it('keeps canonical responsive and state builder target UI markers in Cms.tsx', () => {
        const cms = read('resources/js/Pages/Project/Cms.tsx');

        expect(cms).toContain('data-webu-role="builder-typography-responsive-target"');
        expect(cms).toContain('data-webu-role="builder-typography-state-target"');
        expect(cms).toContain("const activeTypographyViewport: BuilderPreviewViewport = builderPreviewMode;");
        expect(cms).toContain("const activeTypographyInteractionState: BuilderInteractionPreviewState = builderPreviewInteractionState;");
        expect(cms).toContain("t('Desktop')");
        expect(cms).toContain("t('Tablet')");
        expect(cms).toContain("t('Mobile')");
        expect(cms).toContain("t('Hover')");
        expect(cms).toContain("t('Focus')");
        expect(cms).toContain("t('Active')");
    });

    it('documents wrapper-level responsive/state closure semantics and related wrapper evidence locks', () => {
        const doc = read('docs/qa/CMS_PHASE3_RESPONSIVE_STATE_WRAPPER_SUMMARY.md');

        expect(doc).toContain('PROJECT_ROADMAP_TASKS_KA.md:155');
        expect(doc).toContain('PROJECT_ROADMAP_TASKS_KA.md:156');
        expect(doc).toContain('Closure Semantics (Wrapper Level)');
        expect(doc).toContain('base -> responsive -> state');
        expect(doc).toContain('Desktop / Tablet / Mobile');
        expect(doc).toContain('Normal / Hover / Focus / Active');
        expect(doc).toContain('Related Wrapper Evidence Locks');
        expect(doc).toContain('PROJECT_ROADMAP_TASKS_KA.md:154');
        expect(doc).toContain('CMS_PHASE3_PRIMARY_TABS_WRAPPER_SUMMARY.md');
        expect(doc).toContain('PROJECT_ROADMAP_TASKS_KA.md:162');
    });

    it('keeps D2/D3 evidence references and parity contract links in wrapper summary doc', () => {
        const doc = read('docs/qa/CMS_PHASE3_RESPONSIVE_STATE_WRAPPER_SUMMARY.md');

        expect(doc).toContain('CMS_RESPONSIVE_OVERRIDES_D2_BASELINE.md');
        expect(doc).toContain('CMS_STATE_CONTROLS_D2_BASELINE.md');
        expect(doc).toContain('CMS_RUNTIME_STYLE_RESOLUTION_ORDER_D2.md');
        expect(doc).toContain('CmsResponsiveTypographyOverrides.contract.test.ts');
        expect(doc).toContain('CmsStateTypographyOverrides.contract.test.ts');
        expect(doc).toContain('CmsResponsiveStatePreviewRuntimeParity.contract.test.ts');
        expect(doc).toContain('CmsRuntimeStyleResolutionOrder.contract.test.ts');
        expect(doc).toContain('CMS_CONTROL_GROUP_STANDARDS_D3.md');
    });
});
