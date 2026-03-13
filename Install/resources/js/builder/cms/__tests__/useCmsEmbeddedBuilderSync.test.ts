import { renderHook } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import { useCmsEmbeddedBuilderSync } from '@/builder/cms/useCmsEmbeddedBuilderSync';
import { useCmsEmbeddedBuilderMutationHandlers } from '@/builder/cms/useCmsEmbeddedBuilderMutationHandlers';
import { useCmsEmbeddedBuilderSelectionHandlers } from '@/builder/cms/useCmsEmbeddedBuilderSelectionHandlers';
import { useEmbeddedBuilderBridge } from '@/builder/cms/useEmbeddedBuilderBridge';

vi.mock('@/builder/cms/useCmsEmbeddedBuilderSelectionHandlers', () => ({
    useCmsEmbeddedBuilderSelectionHandlers: vi.fn(),
}));

vi.mock('@/builder/cms/useCmsEmbeddedBuilderMutationHandlers', () => ({
    useCmsEmbeddedBuilderMutationHandlers: vi.fn(),
}));

vi.mock('@/builder/cms/useEmbeddedBuilderBridge', () => ({
    useEmbeddedBuilderBridge: vi.fn(),
}));

describe('useCmsEmbeddedBuilderSync', () => {
    it('wires selection and mutation handlers into the embedded bridge contract', () => {
        const selectionHandlers = {
            handleEmbeddedBuilderClearSelection: vi.fn(),
            handleEmbeddedBuilderInitialSections: vi.fn(),
            handleEmbeddedBuilderSelectedTarget: vi.fn(),
        };
        const mutationHandlers = {
            handleEmbeddedBuilderChangeSet: vi.fn(),
            handleEmbeddedBuilderSaveDraft: vi.fn(),
            handleEmbeddedBuilderAddSection: vi.fn(),
            handleEmbeddedBuilderRemoveSection: vi.fn(),
            handleEmbeddedBuilderMoveSection: vi.fn(),
        };

        vi.mocked(useCmsEmbeddedBuilderSelectionHandlers).mockReturnValue(selectionHandlers);
        vi.mocked(useCmsEmbeddedBuilderMutationHandlers).mockReturnValue(mutationHandlers);

        const selectionOptions = {
            isEmbeddedMode: true,
            pageEditorMode: 'builder' as const,
            setPageEditorMode: vi.fn(),
            setSectionsDraft: vi.fn(),
            sectionsDraftRef: { current: [] },
            scheduleStructuralDraftPersistRef: { current: vi.fn() },
            setSelectedSectionLocalId: vi.fn(),
            setSelectedFixedSectionKey: vi.fn(),
            setSelectedNestedSection: vi.fn(),
            setSelectedBuilderTarget: vi.fn(),
            setBuilderSidebarMode: vi.fn(),
            setSelectedSidebarTab: vi.fn(),
            selectSectionByPreviewKey: vi.fn(),
            builderPreviewMode: 'desktop' as const,
            builderPreviewInteractionState: 'normal' as const,
            normalizeSectionTypeKey: vi.fn((key: string) => key),
            formatPropsText: vi.fn(() => '{}'),
            builderFieldGroupToSidebarTab: vi.fn(() => 'content' as const),
            createBuilderSectionDraft: vi.fn(() => null),
            applyMutationState: vi.fn(),
        };
        const mutationOptions = {
            isEmbeddedMode: true,
            pageEditorMode: 'builder',
            selectedPageId: 12,
            selectedSectionLocalId: 'section-1',
            selectedBuilderTarget: null,
            sectionsDraftRef: { current: [] },
            saveDraftRevisionInternalRef: { current: vi.fn(async () => 1) },
            scheduleStructuralDraftPersistRef: { current: vi.fn() },
            setPageEditorMode: vi.fn(),
            setSectionsDraft: vi.fn(),
            setSelectedSectionLocalId: vi.fn(),
            setSelectedNestedSection: vi.fn(),
            setSelectedFixedSectionKey: vi.fn(),
            setBuilderSidebarMode: vi.fn(),
            normalizeSectionTypeKey: vi.fn((key: string) => key),
            isHeaderSectionKey: vi.fn(() => false),
            isFooterSectionKey: vi.fn(() => false),
            createBuilderSectionDraft: vi.fn(() => null),
            applyMutationState: vi.fn(),
            addSectionByKey: vi.fn(),
            handleAddSectionInside: vi.fn(),
            handleRemoveSection: vi.fn(),
            t: vi.fn((key: string) => key),
        };
        const bridgeOptions = {
            isEmbeddedMode: true,
            isEmbeddedVisualBuilder: true,
            isEmbeddedSidebarMode: true,
            builderViewport: 'desktop' as const,
            builderInteractionState: 'normal' as const,
            selectedTargetViewport: 'desktop' as const,
            selectedTargetInteractionState: 'normal' as const,
            isStructurePanelCollapsed: false,
            selectedPage: {
                pageId: 12,
                pageSlug: 'home',
                pageTitle: 'Home',
            },
            stateVersion: 3,
            structureHash: 'hash',
            revisionId: 4,
            revisionVersion: 5,
            selectedSectionLocalId: 'section-1',
            selectedSectionDraft: null,
            selectedFixedSectionKey: null,
            selectedBuilderTarget: null,
            sectionsDraft: [],
            builderSectionLibrary: [],
            sectionDisplayLabelByKey: new Map<string, string>(),
            t: vi.fn((key: string) => key),
            normalizeSectionTypeKey: vi.fn((key: string) => key),
            buildSectionPreviewText: vi.fn(() => 'Preview'),
            getBuilderSectionExplicitProps: vi.fn(() => null),
            onSetViewport: vi.fn(),
            onSetInteractionState: vi.fn(),
            onRefreshPreview: vi.fn(),
            onSetSidebarMode: vi.fn(),
            onSetStructureOpen: vi.fn(),
        };

        renderHook(() => useCmsEmbeddedBuilderSync({
            selection: selectionOptions,
            mutation: mutationOptions,
            bridge: bridgeOptions,
        }));

        expect(useCmsEmbeddedBuilderSelectionHandlers).toHaveBeenCalledWith(selectionOptions);
        expect(useCmsEmbeddedBuilderMutationHandlers).toHaveBeenCalledWith(mutationOptions);
        expect(useEmbeddedBuilderBridge).toHaveBeenCalledWith({
            ...bridgeOptions,
            onClearSelectedSection: selectionHandlers.handleEmbeddedBuilderClearSelection,
            onSetInitialSections: selectionHandlers.handleEmbeddedBuilderInitialSections,
            onApplyChangeSet: mutationHandlers.handleEmbeddedBuilderChangeSet,
            onSetSelectedTarget: selectionHandlers.handleEmbeddedBuilderSelectedTarget,
            onSaveDraft: mutationHandlers.handleEmbeddedBuilderSaveDraft,
            onAddSectionByKey: mutationHandlers.handleEmbeddedBuilderAddSection,
            onRemoveSection: mutationHandlers.handleEmbeddedBuilderRemoveSection,
            onMoveSection: mutationHandlers.handleEmbeddedBuilderMoveSection,
        });
    });
});
