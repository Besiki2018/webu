import { describe, expect, it, vi } from 'vitest';
import { renderHook } from '@testing-library/react';

import { useCmsSidebarSelectionActions } from '@/builder/cms/useCmsSidebarSelectionActions';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';

function makeSection(localId: string, type = 'webu_general_hero_01'): SectionDraft {
    return {
        localId,
        type,
        props: { headline: 'Hello' },
        propsText: '{"headline":"Hello"}',
        propsError: null,
        bindingMeta: null,
    };
}

function buildOptions(overrides: Partial<Parameters<typeof useCmsSidebarSelectionActions>[0]> = {}) {
    return {
        sectionsDraft: [],
        setSelectedSectionLocalId: vi.fn(),
        setSelectedFixedSectionKey: vi.fn(),
        setSelectedNestedSection: vi.fn(),
        setSelectedBuilderTarget: vi.fn(),
        setBuilderSidebarMode: vi.fn(),
        ensurePreviewSectionVisibility: vi.fn(),
        ...overrides,
    };
}

describe('useCmsSidebarSelectionActions', () => {
    it('switches sidebar modes without mutating selection state', () => {
        const options = buildOptions();

        const { result } = renderHook(() => useCmsSidebarSelectionActions(options));

        result.current.handleOpenElementsSidebar();
        result.current.handleOpenSettingsSidebar();
        result.current.handleOpenDesignSystemSidebar();

        expect(options.setBuilderSidebarMode).toHaveBeenNthCalledWith(1, 'elements');
        expect(options.setBuilderSidebarMode).toHaveBeenNthCalledWith(2, 'settings');
        expect(options.setBuilderSidebarMode).toHaveBeenNthCalledWith(3, 'design-system');
        expect(options.setSelectedSectionLocalId).not.toHaveBeenCalled();
        expect(options.setSelectedFixedSectionKey).not.toHaveBeenCalled();
    });

    it('focuses a section and opens the settings sidebar', () => {
        const options = buildOptions({
            sectionsDraft: [makeSection('hero-1')],
        });

        const { result } = renderHook(() => useCmsSidebarSelectionActions(options));

        result.current.handleFocusSection('hero-1');

        expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith(null);
        expect(options.setSelectedNestedSection).toHaveBeenCalledWith(null);
        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith('hero-1');
        expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
        }));
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('settings');
    });

    it('opens a warning section and syncs preview focus', () => {
        const options = buildOptions({
            sectionsDraft: [makeSection('hero-1')],
        });

        const { result } = renderHook(() => useCmsSidebarSelectionActions(options));

        result.current.handleOpenWarningSection('hero-1', 'webu_general_hero_01', 'Hero');

        expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith(null);
        expect(options.setSelectedNestedSection).toHaveBeenCalledWith(null);
        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith('hero-1');
        expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
        }));
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('settings');
        expect(options.ensurePreviewSectionVisibility).toHaveBeenCalledWith('webu_general_hero_01', 'Hero');
    });

    it('keeps nested selection tied to the parent section and sidebar state', () => {
        const options = buildOptions({
            sectionsDraft: [makeSection('layout-1', 'container')],
        });

        const { result } = renderHook(() => useCmsSidebarSelectionActions(options));

        result.current.handleSelectNestedSection('layout-1', [0, 2]);

        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith('layout-1');
        expect(options.setSelectedNestedSection).toHaveBeenCalledWith({ parentLocalId: 'layout-1', path: [0, 2] });
        expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(expect.objectContaining({
            sectionLocalId: 'layout-1',
            sectionKey: 'container',
        }));
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('settings');
    });

    it('opens a fixed section editor and highlights the preview target', () => {
        const options = buildOptions();

        const { result } = renderHook(() => useCmsSidebarSelectionActions(options));

        result.current.handleOpenFixedSection('webu_header_01', 'Header');

        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith(null);
        expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith('webu_header_01');
        expect(options.setSelectedNestedSection).toHaveBeenCalledWith(null);
        expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(null);
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('settings');
        expect(options.ensurePreviewSectionVisibility).toHaveBeenCalledWith('webu_header_01', 'Header');
    });

    it('opens site settings without leaving settings mode', () => {
        const options = buildOptions();

        const { result } = renderHook(() => useCmsSidebarSelectionActions(options));

        result.current.handleOpenSiteSettings();

        expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith(null);
        expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith(null);
        expect(options.setSelectedNestedSection).toHaveBeenCalledWith(null);
        expect(options.setSelectedBuilderTarget).toHaveBeenCalledWith(null);
        expect(options.setBuilderSidebarMode).toHaveBeenCalledWith('settings');
    });

    it('provides explicit back actions for fixed and nested editing scopes', () => {
        const options = buildOptions({
            sectionsDraft: [makeSection('hero-1')],
        });

        const { result } = renderHook(() => useCmsSidebarSelectionActions(options));

        result.current.handleCloseFixedSection();
        result.current.handleBackToParentNestedSection();

        expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith(null);
        expect(options.setSelectedNestedSection).toHaveBeenCalledWith(null);
        const backUpdater = options.setSelectedBuilderTarget.mock.calls.at(-1)?.[0];
        expect(typeof backUpdater).toBe('function');
        expect(backUpdater({ sectionLocalId: 'hero-1' })).toEqual(expect.objectContaining({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
        }));
    });
});
