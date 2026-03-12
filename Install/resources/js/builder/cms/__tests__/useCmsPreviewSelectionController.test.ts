import { describe, expect, it, vi } from 'vitest';
import { renderHook } from '@testing-library/react';

import { useCmsPreviewSelectionController } from '@/builder/cms/useCmsPreviewSelectionController';

function makeSection(localId: string, type = 'webu_general_hero_01') {
    return {
        localId,
        type,
        props: { headline: 'Hello' },
        propsText: '{"headline":"Hello"}',
        propsError: null,
        bindingMeta: null,
    };
}

function buildOptions(overrides: Partial<Parameters<typeof useCmsPreviewSelectionController>[0]> = {}) {
    return {
        sectionsDraftRef: { current: [] },
        normalizeSectionTypeKey: (key: string) => key.trim().toLowerCase(),
        isFixedLayoutSectionKey: (key: string) => key.startsWith('webu_header_') || key.startsWith('webu_footer_'),
        syncFixedLayoutVariant: vi.fn(),
        setSelectedSectionLocalId: vi.fn(),
        setSelectedFixedSectionKey: vi.fn(),
        setSelectedNestedSection: vi.fn(),
        setSelectedBuilderTarget: vi.fn(),
        setBuilderSidebarMode: vi.fn(),
        highlightPreviewSection: vi.fn(),
        ...overrides,
    };
}

describe('useCmsPreviewSelectionController', () => {
    it('selects the exact draft instance when preview local id is present', () => {
        const element = document.createElement('section');
        element.setAttribute('data-webu-section-local-id', 'hero-2');
        const options = buildOptions({
            sectionsDraftRef: {
                current: [
                    makeSection('hero-1'),
                    makeSection('hero-2'),
                ],
            },
        });

        const { result } = renderHook(() => useCmsPreviewSelectionController(options));

        result.current.selectSectionByPreviewKey('webu_general_hero_01', element);

        expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith(null);
        expect(options.setSelectedNestedSection).toHaveBeenCalledWith(null);
        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith('hero-2');
        expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
            sectionLocalId: 'hero-2',
            sectionKey: 'webu_general_hero_01',
        }));
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('settings');
        expect(options.highlightPreviewSection).toHaveBeenCalledWith(element);
    });

    it('routes fixed layout selection through the fixed-section path', () => {
        const element = document.createElement('section');
        const options = buildOptions();

        const { result } = renderHook(() => useCmsPreviewSelectionController(options));

        result.current.selectSectionByPreviewKey('webu_header_02', element);

        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith(null);
        expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith('webu_header_02');
        expect(options.setSelectedNestedSection).toHaveBeenCalledWith(null);
        expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(null);
        expect(options.syncFixedLayoutVariant).toHaveBeenCalledWith('webu_header_02');
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('settings');
        expect(options.highlightPreviewSection).toHaveBeenCalledWith(element);
    });

    it('does not resolve duplicate sections without an exact local id', () => {
        const options = buildOptions({
            sectionsDraftRef: {
                current: [
                    makeSection('hero-1'),
                    makeSection('hero-2'),
                ],
            },
        });

        const { result } = renderHook(() => useCmsPreviewSelectionController(options));

        result.current.selectSectionByPreviewKey('webu_general_hero_01');

        expect(options.setSelectedSectionLocalId).not.toHaveBeenCalled();
        expect(options.setSelectedFixedSectionKey).not.toHaveBeenCalled();
        expect(options.setBuilderSidebarMode).not.toHaveBeenCalled();
    });

    it('falls back to a unique normalized section key match when local id is absent', () => {
        const options = buildOptions({
            sectionsDraftRef: {
                current: [
                    makeSection('hero-1', 'webu_general_hero_01'),
                ],
            },
        });

        const { result } = renderHook(() => useCmsPreviewSelectionController(options));

        result.current.selectSectionByPreviewKey('WEBU_GENERAL_HERO_01');

        expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith(null);
        expect(options.setSelectedNestedSection).toHaveBeenCalledWith(null);
        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith('hero-1');
        expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
        }));
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('settings');
    });
});
