import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

import { readCurrentBuilderDocs } from './builderContractTestUtils';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS general utility components builder coverage contracts', () => {
    it('keeps standalone canonical utility/general component keys and markers for iconbox/submit/misc blocks', () => {
        const cms = read(cmsPagePath);

        [
            'webu_general_icon_box_01',
            'webu_general_form_submit_01',
            'webu_general_testimonials_01',
            'webu_general_trust_badges_01',
            'webu_general_stats_counter_01',
        ].forEach((key) => expect(cms).toContain(key));

        [
            'data-webby-basic-icon-box',
            'data-webby-form-submit',
            'data-webby-misc-testimonials',
            'data-webby-misc-trust-badges',
            'data-webby-misc-stats-counter',
        ].forEach((marker) => expect(cms).toContain(marker));
    });

    it('keeps createGeneralPlaceholder and preview-update branches for utility/general components', () => {
        const cms = read(cmsPagePath);

        [
            "if (normalized === 'webu_general_icon_box_01')",
            "if (normalized === 'webu_general_form_submit_01')",
            "if (normalized === 'webu_general_testimonials_01')",
            "if (normalized === 'webu_general_trust_badges_01')",
            "if (normalized === 'webu_general_stats_counter_01')",
            "if (normalizedSectionType === 'webu_general_icon_box_01')",
            "if (normalizedSectionType === 'webu_general_form_submit_01')",
            "if (normalizedSectionType === 'webu_general_testimonials_01')",
            "if (normalizedSectionType === 'webu_general_trust_badges_01')",
            "if (normalizedSectionType === 'webu_general_stats_counter_01')",
        ].forEach((needle) => expect(cms).toContain(needle));

        [
            'data-webu-role="iconbox-wrap"',
            'data-webu-role="form-submit-button"',
            'data-webu-role="testimonials-grid"',
            'data-webu-role="trust-badges-row"',
            'data-webu-role="stats-counter-grid"',
        ].forEach((needle) => expect(cms).toContain(needle));
    });

    it('syncs component-library gap-audit rows for newly closed partial utility components', () => {
        const doc = readCurrentBuilderDocs();

        expect(doc).toContain('componentRegistry.ts');
        expect(doc).toContain('updatePipeline.ts');
        expect(doc).toContain('schema-driven builder');
        expect(doc).toContain('BuilderCanvas');
    });
});
