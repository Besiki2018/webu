import { describe, expect, it } from 'vitest';
import { buildAiPageContextPayload, resolveAnalyzePage } from '@/lib/aiSiteEditorPageContext';
import type { AnalyzeResult } from '@/hooks/useAiSiteEditor';

const analyzeResult: AnalyzeResult = {
    success: true,
    pages: [
        {
            id: 10,
            slug: 'home',
            title: 'Home',
            sections: [
                { id: 'home-hero', type: 'hero', label: 'Home Hero' },
            ],
        },
        {
            id: 20,
            slug: 'pricing',
            title: 'Pricing',
            sections: [
                { id: 'pricing-hero', type: 'pricing_hero', label: 'Pricing Hero' },
                { id: 'pricing-faq', type: 'faq', label: 'Pricing FAQ' },
            ],
        },
    ],
    global_components: [{ id: 'header', label: 'Header' }],
    available_components: ['hero', 'pricing_hero', 'faq'],
};

describe('aiSiteEditorPageContext', () => {
    it('resolves the exact selected page by id before falling back', () => {
        const page = resolveAnalyzePage(analyzeResult.pages, {
            pageId: 20,
            pageSlug: 'home',
        });

        expect(page?.slug).toBe('pricing');
        expect(page?.sections.map((section) => section.id)).toEqual(['pricing-hero', 'pricing-faq']);
    });

    it('builds page context from the selected non-home page', () => {
        const payload = buildAiPageContextPayload(analyzeResult, {
            pageId: 20,
            pageSlug: 'pricing',
            locale: 'ka',
            selectedSectionId: 'pricing-faq',
        });

        expect(payload).toMatchObject({
            page_id: 20,
            page_slug: 'pricing',
            locale: 'ka',
            selected_section_id: 'pricing-faq',
        });
        expect(payload?.sections).toEqual([
            { id: 'pricing-hero', type: 'pricing_hero', label: 'Pricing Hero' },
            { id: 'pricing-faq', type: 'faq', label: 'Pricing FAQ' },
        ]);
        expect(payload?.component_types).toEqual(['hero', 'pricing_hero', 'faq']);
    });

    it('does not fall back to the first page when an explicit target is missing', () => {
        const page = resolveAnalyzePage(analyzeResult.pages, {
            pageId: 999,
            pageSlug: 'missing',
        });
        const payload = buildAiPageContextPayload(analyzeResult, {
            pageId: 999,
            pageSlug: 'missing',
        });

        expect(page).toBeNull();
        expect(payload).toBeNull();
    });
});
