import { describe, expect, it } from 'vitest';

import { buildSectionPreviewText } from '@/builder/state/sectionProps';

describe('buildSectionPreviewText', () => {
    it('prefers schema-backed hero title over stale legacy headline aliases', () => {
        const previewText = buildSectionPreviewText({
            title: 'Runtime Title',
            headline: 'Legacy Headline',
        }, 'Fallback', 'webu_general_hero_01');

        expect(previewText).toBe('Runtime Title');
    });

    it('falls back to legacy alias order when no schema key is available', () => {
        const previewText = buildSectionPreviewText({
            headline: 'Legacy Headline',
        }, 'Fallback');

        expect(previewText).toBe('Legacy Headline');
    });
});
