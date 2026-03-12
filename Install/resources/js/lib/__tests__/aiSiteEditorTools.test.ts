import { describe, expect, it, vi } from 'vitest';
import { createAgentTools } from '@/lib/aiSiteEditorTools';
import type { AnalyzeResult } from '@/hooks/useAiSiteEditor';

const analyzeResult: AnalyzeResult = {
    success: true,
    pages: [
        {
            id: 10,
            slug: 'home',
            title: 'Home',
            sections: [{ id: 'home-hero', type: 'hero', label: 'Home Hero' }],
        },
        {
            id: 20,
            slug: 'pricing',
            title: 'Pricing',
            sections: [{ id: 'pricing-faq', type: 'faq', label: 'Pricing FAQ' }],
        },
    ],
    global_components: [],
    available_components: ['hero', 'faq'],
};

describe('aiSiteEditorTools', () => {
    const tools = createAgentTools('42', {
        analyze: vi.fn(),
        interpret: vi.fn(),
        execute: vi.fn(),
    });

    it('returns the explicitly targeted page sections by id', () => {
        expect(tools.getPageComponents(analyzeResult, 'home', 20)).toEqual([
            { id: 'pricing-faq', type: 'faq', label: 'Pricing FAQ' },
        ]);
    });

    it('does not fall back to the first page when an explicit target is missing', () => {
        expect(tools.getPageComponents(analyzeResult, 'missing')).toBeUndefined();
        expect(tools.getPageComponents(analyzeResult, undefined, 999)).toBeUndefined();
    });
});
