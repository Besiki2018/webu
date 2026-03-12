import { act, renderHook, waitFor } from '@testing-library/react';
import { describe, expect, it, vi, afterEach } from 'vitest';

import { useCmsPreviewIframeBinding } from '@/builder/cms/useCmsPreviewIframeBinding';
import { observeDOMMapInvalidation } from '@/builder/domMapper';

vi.mock('@/builder/domMapper', () => ({
    observeDOMMapInvalidation: vi.fn(() => vi.fn()),
}));

function createIframeEnvironment() {
    const iframe = document.createElement('iframe');
    const doc = document.implementation.createHTMLDocument('preview');
    Object.defineProperty(iframe, 'contentDocument', {
        value: doc,
        writable: true,
        configurable: true,
    });

    return { iframe, doc };
}

function buildOptions(overrides: Partial<Parameters<typeof useCmsPreviewIframeBinding>[0]> = {}) {
    const { iframe, doc } = createIframeEnvironment();

    return {
        iframe,
        doc,
        options: {
            isVisualBuilderOpen: true,
            builderPreviewIframeRef: { current: iframe },
            builderPreviewDocumentRef: { current: null as Document | null },
            selectedSectionLocalId: null,
            selectedFixedSectionKey: null,
            headerVariant: 'webu_header_01',
            footerVariant: 'webu_footer_01',
            designSystemOverrides: {},
            ensurePreviewSelectionStyle: vi.fn(),
            ensureBuilderStableCanvasStyle: vi.fn(),
            ensureDesignSystemTokensStyle: vi.fn(),
            syncPreviewVisibleSections: vi.fn(),
            syncBuilderPreviewDraftBindings: vi.fn(),
            clearPreviewSelectionHighlight: vi.fn(),
            highlightPreviewSection: vi.fn(),
            selectSectionByPreviewKey: vi.fn(),
            isFixedLayoutSectionKey: vi.fn((key: string) => key.startsWith('webu_header_') || key.startsWith('webu_footer_')),
            detectFixedSectionKeyFromElement: vi.fn(() => null),
            ...overrides,
        },
    };
}

describe('useCmsPreviewIframeBinding', () => {
    afterEach(() => {
        vi.clearAllMocks();
    });

    it('binds the preview document and forwards click selection to the preview selection controller', async () => {
        const { doc, options } = buildOptions();
        const section = doc.createElement('section');
        section.setAttribute('data-webu-section', 'webu_general_hero_01');
        section.setAttribute('data-webu-section-local-id', 'hero-1');
        const button = doc.createElement('button');
        section.appendChild(button);
        doc.body.appendChild(section);

        renderHook(() => useCmsPreviewIframeBinding(options));

        await waitFor(() => {
            expect(options.builderPreviewDocumentRef.current).toBe(doc);
        });

        expect(options.ensurePreviewSelectionStyle).toHaveBeenCalledWith(doc);
        expect(options.ensureBuilderStableCanvasStyle).toHaveBeenCalledWith(doc);
        expect(options.syncPreviewVisibleSections).toHaveBeenCalled();
        expect(options.syncBuilderPreviewDraftBindings).toHaveBeenCalled();
        expect(observeDOMMapInvalidation).toHaveBeenCalledWith(doc);

        act(() => {
            button.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        });

        expect(options.selectSectionByPreviewKey).toHaveBeenCalledWith('webu_general_hero_01', section);
    });

    it('prefers detected fixed-section keys over raw section keys during preview clicks', async () => {
        const { doc, options } = buildOptions({
            detectFixedSectionKeyFromElement: vi.fn(() => 'webu_header_02'),
        });
        const section = doc.createElement('section');
        section.setAttribute('data-webu-section', 'webu_general_hero_01');
        const button = doc.createElement('button');
        section.appendChild(button);
        doc.body.appendChild(section);

        renderHook(() => useCmsPreviewIframeBinding(options));

        await waitFor(() => {
            expect(options.builderPreviewDocumentRef.current).toBe(doc);
        });

        act(() => {
            button.dispatchEvent(new MouseEvent('click', { bubbles: true, cancelable: true }));
        });

        expect(options.selectSectionByPreviewKey).toHaveBeenCalledWith('webu_header_02', section);
    });

    it('syncs highlight state from selected section ids and clears it when the preview closes', async () => {
        const { doc, options } = buildOptions();
        const section = doc.createElement('section');
        section.setAttribute('data-webu-section', 'webu_general_hero_01');
        section.setAttribute('data-webu-section-local-id', 'hero-1');
        doc.body.appendChild(section);

        const { rerender } = renderHook((props: Parameters<typeof useCmsPreviewIframeBinding>[0]) => useCmsPreviewIframeBinding(props), {
            initialProps: options,
        });

        await waitFor(() => {
            expect(options.builderPreviewDocumentRef.current).toBe(doc);
        });

        rerender({
            ...options,
            selectedSectionLocalId: 'hero-1',
        });

        await waitFor(() => {
            expect(options.highlightPreviewSection).toHaveBeenCalledWith(section);
        });

        rerender({
            ...options,
            isVisualBuilderOpen: false,
        });

        await waitFor(() => {
            expect(options.builderPreviewDocumentRef.current).toBeNull();
        });
        expect(options.clearPreviewSelectionHighlight).toHaveBeenCalled();
    });
});
