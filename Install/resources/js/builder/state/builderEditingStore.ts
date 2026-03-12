import type { SetStateAction } from 'react';
import { create } from 'zustand';

import type {
    BuilderBreakpoint,
    BuilderEditableTarget,
    BuilderInteractionState,
    BuilderSidebarMode,
    BuilderSidebarTab,
} from '@/builder/editingState';
import { areBuilderEditableTargetsEqual } from '@/builder/editingState';
import { normalizeBuilderSectionDrafts } from '@/builder/model/pageModel';
import type { BuilderSection } from '@/builder/visual/treeUtils';
import type { DropTarget } from '@/builder/visual/types';

export type SectionDraft = BuilderSection;

const INITIAL_STRUCTURE_PANEL_POSITION = { x: 24, y: 72 } as const;

type SetStateActionLike<T> = SetStateAction<T>;

function resolveStateAction<T>(current: T, next: SetStateActionLike<T>): T {
    return typeof next === 'function'
        ? (next as (value: T) => T)(current)
        : next;
}

function deriveSelectedState(
    target: BuilderEditableTarget | null,
    selectedSectionLocalId: string | null
): Pick<
    BuilderEditingStoreState,
    'selectedElementId' | 'selectedComponentType' | 'selectedComponentName' | 'selectedPath' | 'selectedComponentProps'
> {
    return {
        selectedElementId: target?.elementId ?? target?.builderId ?? target?.sectionLocalId ?? selectedSectionLocalId ?? null,
        selectedComponentType: target?.componentType ?? target?.sectionKey ?? null,
        selectedComponentName: target?.componentName ?? null,
        selectedPath: target?.path ?? null,
        selectedComponentProps: target?.props ?? null,
    };
}

function deriveHoveredElementId(
    target: BuilderEditableTarget | null,
    fallback: string | null
): string | null {
    return target?.elementId ?? target?.builderId ?? target?.sectionLocalId ?? fallback ?? null;
}

function shouldKeepCurrentSelectedBuilderTarget(
    current: BuilderEditableTarget | null,
    next: BuilderEditableTarget | null,
): boolean {
    if (current === next) {
        return true;
    }

    if (!areBuilderEditableTargetsEqual(current, next)) {
        return false;
    }

    return current?.props === next?.props
        && current?.textPreview === next?.textPreview;
}

export interface BuilderEditingStoreState {
    sectionsDraft: SectionDraft[];
    selectedSectionLocalId: string | null;
    selectedBuilderTarget: BuilderEditableTarget | null;
    hoveredBuilderTarget: BuilderEditableTarget | null;
    selectedElementId: string | null;
    selectedComponentType: string | null;
    selectedComponentName: string | null;
    selectedPath: string | null;
    selectedComponentProps: Record<string, unknown> | null;
    hoveredElementId: string | null;
    activeDragId: string | null;
    builderHoveredElementId: string | null;
    builderCurrentDropTarget: DropTarget | null;
    builderMode: BuilderSidebarMode;
    builderSidebarMode: BuilderSidebarMode;
    currentBreakpoint: BuilderBreakpoint;
    currentInteractionState: BuilderInteractionState;
    selectedSidebarTab: BuilderSidebarTab;
    isStructurePanelCollapsed: boolean;
    structurePanelPosition: { x: number; y: number };
    setSectionsDraft: (next: SetStateActionLike<SectionDraft[]>) => void;
    setSelectedSectionLocalId: (next: SetStateActionLike<string | null>) => void;
    setSelectedBuilderTarget: (next: SetStateActionLike<BuilderEditableTarget | null>) => void;
    setHoveredBuilderTarget: (next: SetStateActionLike<BuilderEditableTarget | null>) => void;
    setActiveDragId: (next: SetStateActionLike<string | null>) => void;
    setBuilderHoveredElementId: (next: SetStateActionLike<string | null>) => void;
    setBuilderCurrentDropTarget: (next: SetStateActionLike<DropTarget | null>) => void;
    setCurrentBreakpoint: (next: SetStateActionLike<BuilderBreakpoint>) => void;
    setCurrentInteractionState: (next: SetStateActionLike<BuilderInteractionState>) => void;
    setSelectedSidebarTab: (next: SetStateActionLike<BuilderSidebarTab>) => void;
    setIsStructurePanelCollapsed: (next: SetStateActionLike<boolean>) => void;
    setStructurePanelPosition: (next: SetStateActionLike<{ x: number; y: number }>) => void;
    setBuilderMode: (next: SetStateActionLike<BuilderSidebarMode>) => void;
    setBuilderSidebarMode: (next: SetStateActionLike<BuilderSidebarMode>) => void;
    applyMutationState: (next: {
        sectionsDraft: SectionDraft[];
        selectedSectionLocalId: string | null;
        selectedBuilderTarget: BuilderEditableTarget | null;
    }) => void;
    clearSelection: () => void;
    reset: () => void;
}

export const createInitialBuilderEditingState = () => ({
    sectionsDraft: [] as SectionDraft[],
    selectedSectionLocalId: null,
    selectedBuilderTarget: null,
    hoveredBuilderTarget: null,
    selectedElementId: null,
    selectedComponentType: null,
    selectedComponentName: null,
    selectedPath: null,
    selectedComponentProps: null,
    hoveredElementId: null,
    activeDragId: null,
    builderHoveredElementId: null,
    builderCurrentDropTarget: null as DropTarget | null,
    builderMode: 'elements' as BuilderSidebarMode,
    builderSidebarMode: 'elements' as BuilderSidebarMode,
    currentBreakpoint: 'desktop' as BuilderBreakpoint,
    currentInteractionState: 'normal' as BuilderInteractionState,
    selectedSidebarTab: 'content' as BuilderSidebarTab,
    isStructurePanelCollapsed: false,
    structurePanelPosition: { ...INITIAL_STRUCTURE_PANEL_POSITION },
});

export const useBuilderEditingStore = create<BuilderEditingStoreState>((set) => ({
    ...createInitialBuilderEditingState(),
    setSectionsDraft: (next) => {
        set((state) => ({
            sectionsDraft: normalizeBuilderSectionDrafts(resolveStateAction(state.sectionsDraft, next)),
        }));
    },
    setSelectedSectionLocalId: (next) => {
        set((state) => {
            const selectedSectionLocalId = resolveStateAction(state.selectedSectionLocalId, next);
            if (state.selectedSectionLocalId === selectedSectionLocalId) {
                return state;
            }

            return {
                selectedSectionLocalId,
                ...deriveSelectedState(state.selectedBuilderTarget, selectedSectionLocalId),
            };
        });
    },
    setSelectedBuilderTarget: (next) => {
        set((state) => {
            const selectedBuilderTarget = resolveStateAction(state.selectedBuilderTarget, next);
            const selectedSectionLocalId = selectedBuilderTarget?.sectionLocalId ?? state.selectedSectionLocalId;
            if (
                state.selectedSectionLocalId === selectedSectionLocalId
                && shouldKeepCurrentSelectedBuilderTarget(state.selectedBuilderTarget, selectedBuilderTarget)
            ) {
                return state;
            }

            return {
                selectedBuilderTarget,
                selectedSectionLocalId,
                ...deriveSelectedState(selectedBuilderTarget, selectedSectionLocalId),
            };
        });
    },
    setHoveredBuilderTarget: (next) => {
        set((state) => {
            const hoveredBuilderTarget = resolveStateAction(state.hoveredBuilderTarget, next);

            return {
                hoveredBuilderTarget,
                hoveredElementId: deriveHoveredElementId(hoveredBuilderTarget, state.builderHoveredElementId),
            };
        });
    },
    setActiveDragId: (next) => {
        set((state) => ({
            activeDragId: resolveStateAction(state.activeDragId, next),
        }));
    },
    setBuilderHoveredElementId: (next) => {
        set((state) => {
            const builderHoveredElementId = resolveStateAction(state.builderHoveredElementId, next);

            return {
                builderHoveredElementId,
                hoveredElementId: deriveHoveredElementId(state.hoveredBuilderTarget, builderHoveredElementId),
            };
        });
    },
    setBuilderCurrentDropTarget: (next) => {
        set((state) => ({
            builderCurrentDropTarget: resolveStateAction(state.builderCurrentDropTarget, next),
        }));
    },
    setCurrentBreakpoint: (next) => {
        set((state) => ({
            currentBreakpoint: resolveStateAction(state.currentBreakpoint, next),
        }));
    },
    setCurrentInteractionState: (next) => {
        set((state) => ({
            currentInteractionState: resolveStateAction(state.currentInteractionState, next),
        }));
    },
    setSelectedSidebarTab: (next) => {
        set((state) => ({
            selectedSidebarTab: resolveStateAction(state.selectedSidebarTab, next),
        }));
    },
    setIsStructurePanelCollapsed: (next) => {
        set((state) => ({
            isStructurePanelCollapsed: resolveStateAction(state.isStructurePanelCollapsed, next),
        }));
    },
    setStructurePanelPosition: (next) => {
        set((state) => ({
            structurePanelPosition: resolveStateAction(state.structurePanelPosition, next),
        }));
    },
    setBuilderMode: (next) => {
        set((state) => {
            const builderMode = resolveStateAction(state.builderMode, next);
            if (state.builderMode === builderMode && state.builderSidebarMode === builderMode) {
                return state;
            }

            return {
                builderMode,
                builderSidebarMode: builderMode,
            };
        });
    },
    setBuilderSidebarMode: (next) => {
        set((state) => {
            const builderMode = resolveStateAction(state.builderMode, next);
            if (state.builderMode === builderMode && state.builderSidebarMode === builderMode) {
                return state;
            }

            return {
                builderMode,
                builderSidebarMode: builderMode,
            };
        });
    },
    applyMutationState: (next) => {
        set(() => ({
            sectionsDraft: normalizeBuilderSectionDrafts(next.sectionsDraft),
            selectedSectionLocalId: next.selectedSectionLocalId,
            selectedBuilderTarget: next.selectedBuilderTarget,
            ...deriveSelectedState(next.selectedBuilderTarget, next.selectedSectionLocalId),
        }));
    },
    clearSelection: () => {
        set((state) => ({
            selectedSectionLocalId: null,
            selectedBuilderTarget: null,
            hoveredBuilderTarget: state.hoveredBuilderTarget,
            ...deriveSelectedState(null, null),
        }));
    },
    reset: () => {
        set(() => ({
            ...createInitialBuilderEditingState(),
        }));
    },
}));

export function resetBuilderEditingStore(): void {
    useBuilderEditingStore.getState().reset();
}
