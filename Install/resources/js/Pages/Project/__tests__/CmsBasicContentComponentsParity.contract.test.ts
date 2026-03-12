import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS basic content components parity contracts', () => {
    it('keeps canonical basic component keys and placeholder branches for all 8 basic components', () => {
        const cms = read(cmsPagePath);

        [
            'webu_general_heading_01',
            'webu_general_text_01',
            'webu_general_button_01',
            'webu_general_image_01',
            'webu_general_video_01',
            'webu_general_icon_01',
            'webu_general_icon_box_01',
            'webu_general_html_01',
        ].forEach((key) => expect(cms).toContain(key));

        [
            "if (normalized === 'webu_general_heading_01')",
            "if (normalized === 'webu_general_text_01')",
            "if (normalized === 'webu_general_button_01')",
            "if (normalized === 'webu_general_image_01')",
            "if (normalized === 'webu_general_video_01')",
            "if (normalized === 'webu_general_icon_01')",
            "if (normalized === 'webu_general_icon_box_01')",
            "if (normalized === 'webu_general_html_01')",
        ].forEach((needle) => expect(cms).toContain(needle));
    });

    it('keeps basic button state preview wiring on the shared state system (normal/hover/focus/active)', () => {
        const cms = read(cmsPagePath);

        [
            "const [builderPreviewInteractionState, setBuilderPreviewInteractionState] = useState<BuilderInteractionPreviewState>('normal');",
            'resolveGeneralFoundationRuntimeStyleResolution',
            'interactionState: builderPreviewInteractionState',
            'applyGeneralFoundationComponentStylePresetsPreview',
            '[data-webu-field="button"], [data-webu-field="primary_cta"]',
            "if (normalized === 'webu_general_button_01')",
            "container.setAttribute('data-webu-builder-interaction-state-preview', builderPreviewInteractionState);",
        ].forEach((needle) => expect(cms).toContain(needle));
    });

    it('keeps basic html preview sanitization deny-path default (scripts disabled)', () => {
        const cms = read(cmsPagePath);

        [
            'const sanitizeGeneralHtmlPreviewCode = useCallback((value: unknown, allowScripts = false): string => {',
            '.replace(/<script\\b',
            '.replace(/\\bon[a-z0-9_-]+',
            '.replace(/javascript\\s*:/gi, \'\');',
            "if (normalized === 'webu_general_html_01')",
            "if (normalizedSectionType === 'webu_general_html_01')",
            "code.setAttribute('data-webu-role', 'html-code')",
            "code.setAttribute('data-webu-field', 'html_code')",
            'code.textContent = sanitizeGeneralHtmlPreviewCode(effectiveProps.html_code, false);',
            "code.setAttribute('data-webu-scripts-allowed', 'false');",
        ].forEach((needle) => expect(cms).toContain(needle));

        expect(cms).not.toContain('code.innerHTML = effectiveProps.html_code');
    });

    it('keeps representative mapped fields for the 8 basic components in Cms.tsx builder schemas/placeholders', () => {
        const cms = read(cmsPagePath);

        [
            'headline',
            'body',
            'button_url',
            'image_url',
            'image_alt',
            'image_link',
            'video_url',
            'caption',
            'icon_class',
            'icon_fallback',
            'cta_label',
            'html_code',
        ].forEach((needle) => expect(cms).toContain(needle));
    });
});
