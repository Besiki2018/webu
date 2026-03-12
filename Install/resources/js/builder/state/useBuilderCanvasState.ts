/**
 * Builder canvas state hook backed by the centralized builder editing store.
 * Keeps the existing Cms-facing API stable while moving state to a shared source of truth.
 */

import {
    useBuilderEditingStore,
    type BuilderEditingStoreState,
    type SectionDraft,
} from './builderEditingStore';

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
    return useBuilderEditingStore();
}
