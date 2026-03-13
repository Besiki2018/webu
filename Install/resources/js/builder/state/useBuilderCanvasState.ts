/**
 * Builder canvas state hook backed by the centralized builder editing store.
 * Keeps the existing Cms-facing API stable while moving state to a shared source of truth.
 */

import {
    useBuilderEditingStore,
    type BuilderEditingStoreState,
    type SectionDraft,
} from './builderEditingStore';
import { useShallow } from 'zustand/shallow';

export type { SectionDraft } from './builderEditingStore';

export type BuilderCanvasState = Pick<
    BuilderEditingStoreState,
    | 'sectionsDraft'
    | 'setSectionsDraft'
    | 'selectedSectionLocalId'
    | 'setSelectedSectionLocalId'
    | 'selectedBuilderTarget'
    | 'setSelectedBuilderTarget'
    | 'hoveredBuilderTarget'
    | 'setHoveredBuilderTarget'
    | 'selectedElementId'
    | 'selectedComponentType'
    | 'selectedComponentName'
    | 'selectedPath'
    | 'selectedComponentProps'
    | 'activeDragId'
    | 'setActiveDragId'
    | 'builderHoveredElementId'
    | 'hoveredElementId'
    | 'setBuilderHoveredElementId'
    | 'builderCurrentDropTarget'
    | 'setBuilderCurrentDropTarget'
    | 'currentBreakpoint'
    | 'setCurrentBreakpoint'
    | 'currentInteractionState'
    | 'setCurrentInteractionState'
    | 'selectedSidebarTab'
    | 'setSelectedSidebarTab'
    | 'isStructurePanelCollapsed'
    | 'setIsStructurePanelCollapsed'
    | 'structurePanelPosition'
    | 'setStructurePanelPosition'
    | 'builderMode'
    | 'builderSidebarMode'
    | 'setBuilderMode'
    | 'setBuilderSidebarMode'
    | 'applyMutationState'
    | 'clearSelection'
>;

export function useBuilderCanvasState(): BuilderCanvasState {
    return useBuilderEditingStore(useShallow((state) => ({
        sectionsDraft: state.sectionsDraft,
        setSectionsDraft: state.setSectionsDraft,
        selectedSectionLocalId: state.selectedSectionLocalId,
        setSelectedSectionLocalId: state.setSelectedSectionLocalId,
        selectedBuilderTarget: state.selectedBuilderTarget,
        setSelectedBuilderTarget: state.setSelectedBuilderTarget,
        hoveredBuilderTarget: state.hoveredBuilderTarget,
        setHoveredBuilderTarget: state.setHoveredBuilderTarget,
        selectedElementId: state.selectedElementId,
        selectedComponentType: state.selectedComponentType,
        selectedComponentName: state.selectedComponentName,
        selectedPath: state.selectedPath,
        selectedComponentProps: state.selectedComponentProps,
        activeDragId: state.activeDragId,
        setActiveDragId: state.setActiveDragId,
        builderHoveredElementId: state.builderHoveredElementId,
        hoveredElementId: state.hoveredElementId,
        setBuilderHoveredElementId: state.setBuilderHoveredElementId,
        builderCurrentDropTarget: state.builderCurrentDropTarget,
        setBuilderCurrentDropTarget: state.setBuilderCurrentDropTarget,
        currentBreakpoint: state.currentBreakpoint,
        setCurrentBreakpoint: state.setCurrentBreakpoint,
        currentInteractionState: state.currentInteractionState,
        setCurrentInteractionState: state.setCurrentInteractionState,
        selectedSidebarTab: state.selectedSidebarTab,
        setSelectedSidebarTab: state.setSelectedSidebarTab,
        isStructurePanelCollapsed: state.isStructurePanelCollapsed,
        setIsStructurePanelCollapsed: state.setIsStructurePanelCollapsed,
        structurePanelPosition: state.structurePanelPosition,
        setStructurePanelPosition: state.setStructurePanelPosition,
        builderMode: state.builderMode,
        builderSidebarMode: state.builderSidebarMode,
        setBuilderMode: state.setBuilderMode,
        setBuilderSidebarMode: state.setBuilderSidebarMode,
        applyMutationState: state.applyMutationState,
        clearSelection: state.clearSelection,
    })));
}
