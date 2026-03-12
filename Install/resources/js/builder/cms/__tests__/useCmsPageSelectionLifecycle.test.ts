import { act, renderHook, waitFor } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { useCmsPageSelectionLifecycle } from '@/builder/cms/useCmsPageSelectionLifecycle';

function buildOptions(overrides: Partial<Parameters<typeof useCmsPageSelectionLifecycle>[0]> = {}) {
    return {
        selectedPageId: 42,
        selectedSectionLocalId: null,
        isVisualBuilderOpen: false,
        pageDetailRequestSeqRef: { current: 0 },
        selectedPageIdRef: { current: null as number | null },
        loadPageDetail: vi.fn(),
        setSelectedPageDetail: vi.fn(),
        setPageEditorMode: vi.fn(),
        setPageRichTextHtml: vi.fn(),
        setSelectedSectionLocalId: vi.fn(),
        setSelectedBuilderTarget: vi.fn(),
        setSelectedNestedSection: vi.fn(),
        setSelectedFixedSectionKey: vi.fn(),
        setBindingValidationResult: vi.fn(),
        setBuilderSidebarMode: vi.fn(),
        ...overrides,
    };
}

describe('useCmsPageSelectionLifecycle', () => {
    it('resets selection state and clears page detail when no page is selected', async () => {
        const options = buildOptions({
            selectedPageId: null,
            selectedPageIdRef: { current: 42 },
        });

        renderHook(() => useCmsPageSelectionLifecycle(options));

        await waitFor(() => {
            expect(options.selectedPageIdRef.current).toBeNull();
        });

        expect(options.pageDetailRequestSeqRef.current).toBe(1);
        expect(options.setSelectedPageDetail).toHaveBeenCalledWith(null);
        expect(options.setPageEditorMode).toHaveBeenCalledWith('builder');
        expect(options.setPageRichTextHtml).toHaveBeenCalledWith('');
        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith(null);
        expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(null);
        expect(options.setSelectedNestedSection).toHaveBeenCalledWith(null);
        expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith(null);
        expect(options.setBindingValidationResult).toHaveBeenCalledWith(null);
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('elements');
        expect(options.loadPageDetail).not.toHaveBeenCalled();
    });

    it('resets selection state and loads the selected page detail', async () => {
        const options = buildOptions({
            selectedPageId: 7,
        });

        renderHook(() => useCmsPageSelectionLifecycle(options));

        await waitFor(() => {
            expect(options.selectedPageIdRef.current).toBe(7);
        });

        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith(null);
        expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(null);
        expect(options.setSelectedNestedSection).toHaveBeenCalledWith(null);
        expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith(null);
        expect(options.setBindingValidationResult).toHaveBeenCalledWith(null);
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('elements');
        expect(options.loadPageDetail).toHaveBeenCalledWith(7);
    });

    it('clears the active section selection when Escape is pressed in visual builder mode', async () => {
        const options = buildOptions({
            selectedPageId: 7,
            selectedSectionLocalId: 'hero-1',
            isVisualBuilderOpen: true,
        });

        renderHook(() => useCmsPageSelectionLifecycle(options));

        await waitFor(() => {
            expect(options.loadPageDetail).toHaveBeenCalledWith(7);
        });

        options.setSelectedSectionLocalId.mockClear();
        options.setBuilderSidebarMode.mockClear();

        act(() => {
            window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
        });

        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith(null);
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('elements');
    });
});
