import { useCallback } from 'react';

import type { CmsNestedSelection } from '@/builder/cms/useCmsSelectionStateSync';
import {
    buildEditableTargetFromSection,
    builderFieldGroupToSidebarTab,
    type BuilderEditableTarget,
    type BuilderSidebarMode,
    type BuilderSidebarTab,
} from '@/builder/editingState';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';

type StateUpdater<T> = (value: T | ((current: T) => T)) => void;

interface UseCmsCanvasInteractionHandlersOptions {
    sectionsDraft: SectionDraft[];
    setSelectedSectionLocalId: StateUpdater<string | null>;
    setSelectedFixedSectionKey: StateUpdater<string | null>;
    setSelectedNestedSection: StateUpdater<CmsNestedSelection | null>;
    setSelectedBuilderTarget: StateUpdater<BuilderEditableTarget | null>;
    setHoveredBuilderTarget: StateUpdater<BuilderEditableTarget | null>;
    setBuilderHoveredElementId: StateUpdater<string | null>;
    setBuilderSidebarMode: (mode: BuilderSidebarMode) => void;
    setSelectedSidebarTab: (tab: BuilderSidebarTab) => void;
}

export function useCmsCanvasInteractionHandlers({
    sectionsDraft,
    setSelectedSectionLocalId,
    setSelectedFixedSectionKey,
    setSelectedNestedSection,
    setSelectedBuilderTarget,
    setHoveredBuilderTarget,
    setBuilderHoveredElementId,
    setBuilderSidebarMode,
    setSelectedSidebarTab,
}: UseCmsCanvasInteractionHandlersOptions) {
    const findSectionByLocalId = useCallback((localId: string | null) => {
        if (!localId) {
            return null;
        }

        return sectionsDraft.find((section) => section.localId === localId) ?? null;
    }, [sectionsDraft]);

    const handleCanvasSelect = useCallback((localId: string) => {
        const selectedSection = findSectionByLocalId(localId);
        setSelectedFixedSectionKey(null);
        setSelectedNestedSection(null);
        setSelectedSectionLocalId(localId);
        setSelectedBuilderTarget(buildEditableTargetFromSection(selectedSection));
        setBuilderSidebarMode('settings');
    }, [
        findSectionByLocalId,
        setBuilderSidebarMode,
        setSelectedBuilderTarget,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        setSelectedSectionLocalId,
    ]);

    const handleCanvasHover = useCallback((localId: string | null) => {
        setBuilderHoveredElementId(localId);
        setHoveredBuilderTarget(buildEditableTargetFromSection(findSectionByLocalId(localId)));
    }, [findSectionByLocalId, setBuilderHoveredElementId, setHoveredBuilderTarget]);

    const handleCanvasSelectTarget = useCallback((target: BuilderEditableTarget) => {
        const selectedSection = findSectionByLocalId(target.sectionLocalId);
        setSelectedFixedSectionKey(null);
        setSelectedNestedSection(null);
        setSelectedSectionLocalId(target.sectionLocalId);
        setSelectedBuilderTarget(buildEditableTargetFromSection(selectedSection));
        setBuilderSidebarMode('settings');
        setSelectedSidebarTab(builderFieldGroupToSidebarTab(target.fieldGroup));
    }, [
        findSectionByLocalId,
        setBuilderSidebarMode,
        setSelectedBuilderTarget,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        setSelectedSectionLocalId,
        setSelectedSidebarTab,
    ]);

    const handleCanvasHoverTarget = useCallback((target: BuilderEditableTarget | null) => {
        setHoveredBuilderTarget(target);
    }, [setHoveredBuilderTarget]);

    const handleCanvasDeselect = useCallback(() => {
        setSelectedSectionLocalId(null);
        setSelectedFixedSectionKey(null);
        setSelectedNestedSection(null);
        setSelectedBuilderTarget(null);
        setHoveredBuilderTarget(null);
        setBuilderSidebarMode('elements');
    }, [
        setBuilderSidebarMode,
        setHoveredBuilderTarget,
        setSelectedBuilderTarget,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        setSelectedSectionLocalId,
    ]);

    const handleCanvasEditSection = useCallback((localId: string) => {
        const selectedSection = findSectionByLocalId(localId);
        setSelectedFixedSectionKey(null);
        setSelectedNestedSection(null);
        setSelectedSectionLocalId(localId);
        setSelectedBuilderTarget(buildEditableTargetFromSection(selectedSection));
        setBuilderSidebarMode('settings');
    }, [
        findSectionByLocalId,
        setBuilderSidebarMode,
        setSelectedBuilderTarget,
        setSelectedFixedSectionKey,
        setSelectedNestedSection,
        setSelectedSectionLocalId,
    ]);

    return {
        handleCanvasSelect,
        handleCanvasHover,
        handleCanvasSelectTarget,
        handleCanvasHoverTarget,
        handleCanvasDeselect,
        handleCanvasEditSection,
    };
}
