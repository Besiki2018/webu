import { describe, expect, it, vi } from 'vitest';
import { renderHook, waitFor } from '@testing-library/react';

import { buildEditableTargetFromMessagePayload } from '@/builder/editingState';
import { useCmsSelectionStateSync } from '@/builder/cms/useCmsSelectionStateSync';

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

function buildOptions(overrides: Partial<Parameters<typeof useCmsSelectionStateSync>[0]> = {}) {
    return {
        sectionsDraft: [],
        selectedSectionLocalId: null,
        selectedSectionDraft: null,
        selectedFixedSectionKey: null,
        selectedNestedSection: null,
        setSelectedSectionLocalId: vi.fn(),
        setSelectedFixedSectionKey: vi.fn(),
        setSelectedNestedSection: vi.fn(),
        setSelectedBuilderTarget: vi.fn(),
        normalizeSectionTypeKey: (key: string | null | undefined) => typeof key === 'string' ? key.trim().toLowerCase() : '',
        resolveFixedSectionComponentName: (key: string | null) => (key ? 'Header' : null),
        ...overrides,
    };
}

describe('useCmsSelectionStateSync', () => {
    it('clears invalid selected section ids when the section no longer exists', async () => {
        const options = buildOptions({
            sectionsDraft: [makeSection('hero-1')],
            selectedSectionLocalId: 'missing-section',
        });

        renderHook(() => useCmsSelectionStateSync(options));

        await waitFor(() => {
            expect(options.setSelectedSectionLocalId).toHaveBeenCalledWith(null);
        });
    });

    it('clears fixed-section selection when a normal section is selected', async () => {
        const options = buildOptions({
            sectionsDraft: [makeSection('hero-1')],
            selectedSectionLocalId: 'hero-1',
            selectedFixedSectionKey: 'webu_header_01',
        });

        renderHook(() => useCmsSelectionStateSync(options));

        await waitFor(() => {
            expect(options.setSelectedFixedSectionKey).toHaveBeenCalledWith(null);
        });
    });

    it('clears nested selection when the parent section is no longer selected', async () => {
        const options = buildOptions({
            sectionsDraft: [makeSection('hero-1')],
            selectedSectionLocalId: 'hero-1',
            selectedNestedSection: { parentLocalId: 'cards-1', path: [0] },
        });

        renderHook(() => useCmsSelectionStateSync(options));

        await waitFor(() => {
            expect(options.setSelectedNestedSection).toHaveBeenCalledWith(null);
        });
    });

    it('rebuilds selected builder target from the currently selected section draft', async () => {
        const section = makeSection('hero-1');
        const options = buildOptions({
            sectionsDraft: [section],
            selectedSectionLocalId: 'hero-1',
            selectedSectionDraft: section,
        });

        renderHook(() => useCmsSelectionStateSync(options));

        await waitFor(() => {
            expect(options.setSelectedBuilderTarget).toHaveBeenCalled();
        });

        const updater = options.setSelectedBuilderTarget.mock.calls.at(-1)?.[0];
        expect(typeof updater).toBe('function');
        expect(updater(null)).toEqual(expect.objectContaining({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
        }));
    });

    it('preserves the selected target identity while refreshing props from the selected section draft', async () => {
        const section = {
            ...makeSection('hero-1'),
            props: { headline: 'Updated headline' },
            propsText: '{"headline":"Updated headline"}',
        };
        const staleTarget = buildEditableTargetFromMessagePayload({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            parameterPath: 'headline',
            componentPath: 'headline',
            props: { headline: 'Old headline' },
            currentBreakpoint: 'tablet',
            currentInteractionState: 'hover',
        });
        const options = buildOptions({
            sectionsDraft: [section],
            selectedSectionLocalId: 'hero-1',
            selectedSectionDraft: section,
        });

        renderHook(() => useCmsSelectionStateSync(options));

        await waitFor(() => {
            expect(options.setSelectedBuilderTarget).toHaveBeenCalled();
        });

        const updater = options.setSelectedBuilderTarget.mock.calls.at(-1)?.[0];
        expect(typeof updater).toBe('function');
        expect(updater(staleTarget)).toEqual(expect.objectContaining({
            targetId: staleTarget?.targetId,
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            path: 'headline',
            componentPath: 'headline',
            props: { headline: 'Updated headline' },
            responsiveContext: expect.objectContaining({
                currentBreakpoint: 'tablet',
                currentInteractionState: 'hover',
            }),
        }));
    });

    it('keeps fixed-section target identity when no normal section is selected', async () => {
        const currentTarget = buildEditableTargetFromMessagePayload({
            sectionLocalId: null,
            sectionKey: 'webu_header_01',
            componentType: 'webu_header_01',
            componentName: 'Old Header',
            currentBreakpoint: 'desktop',
            currentInteractionState: 'normal',
        });
        const options = buildOptions({
            selectedFixedSectionKey: 'webu_header_01',
        });

        renderHook(() => useCmsSelectionStateSync(options));

        await waitFor(() => {
            expect(options.setSelectedBuilderTarget).toHaveBeenCalled();
        });

        const updater = options.setSelectedBuilderTarget.mock.calls.at(-1)?.[0];
        expect(typeof updater).toBe('function');
        expect(updater(currentTarget)).toEqual(expect.objectContaining({
            sectionLocalId: null,
            sectionKey: 'webu_header_01',
            componentType: 'webu_header_01',
            componentName: 'Header',
        }));
    });
});
