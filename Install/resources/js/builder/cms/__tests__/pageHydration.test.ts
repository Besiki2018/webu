import { describe, expect, it } from 'vitest';
import { resolveCmsPageHydrationContent } from '@/builder/cms/pageHydration';

describe('pageHydration', () => {
    it('keeps explicit empty section arrays from saved revisions', () => {
        const result = resolveCmsPageHydrationContent({
            page: { slug: 'home' },
            latest_revision: {
                content_json: {
                    sections: [],
                    editor_mode: 'text',
                    text_editor_html: '<p>Hello</p>',
                },
            },
            published_revision: null,
        }, {
            isEmbeddedMode: false,
            templateSectionsBySlug: new Map([
                ['home', [{ type: 'webu_general_hero_01', props: { headline: 'Fallback' } }]],
            ]),
        });

        expect(result.rawSections).toEqual([]);
        expect(result.resolvedEditorMode).toBe('text');
        expect(result.resolvedTextEditorHtml).toBe('<p>Hello</p>');
    });

    it('falls back to template sections only for unrevised legacy pages', () => {
        const result = resolveCmsPageHydrationContent({
            page: { slug: 'home' },
            latest_revision: null,
            published_revision: null,
        }, {
            isEmbeddedMode: true,
            templateSectionsBySlug: new Map([
                ['home', [{ type: 'webu_general_hero_01', props: { headline: 'Template hero' } }]],
            ]),
        });

        expect(result.resolvedEditorMode).toBe('builder');
        expect(result.rawSections).toEqual([{
            type: 'webu_general_hero_01',
            props: { headline: 'Template hero' },
        }]);
    });
});
