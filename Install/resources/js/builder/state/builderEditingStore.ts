import type { SetStateAction } from 'react';
import { create } from 'zustand';

import type {
    BuilderBreakpoint,
    BuilderEditableTarget,
    BuilderInteractionState,
    BuilderSidebarMode,
    BuilderSidebarTab,
} from '@/builder/editingState';
import {
    areBuilderEditableTargetsEqual,
    buildTargetId,
    getBuilderTargetPropPaths,
    getBuilderTargetSchemaKey,
    getBuilderTargetStableNodeId,
} from '@/builder/editingState';
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
    | 'selectedTargetId'
    | 'selectedTargetType'
    | 'selectedNodePath'
    | 'selectedNodeId'
    | 'selectedComponentKey'
    | 'selectedSchemaKey'
    | 'selectedPropPaths'
    | 'selectedElementId'
    | 'selectedComponentType'
    | 'selectedComponentName'
    | 'selectedPath'
    | 'selectedComponentProps'
> {
    return {
        selectedTargetId: target?.targetId ?? (selectedSectionLocalId ? buildTargetId(selectedSectionLocalId, target?.sectionKey ?? null, target?.path ?? null) : null),
        selectedTargetType: target?.componentType ?? target?.sectionKey ?? null,
        selectedNodePath: target?.componentPath ?? target?.path ?? null,
        selectedNodeId: getBuilderTargetStableNodeId(target) ?? selectedSectionLocalId ?? null,
        selectedComponentKey: target?.sectionKey ?? target?.componentType ?? null,
        selectedSchemaKey: getBuilderTargetSchemaKey(target),
        selectedPropPaths: getBuilderTargetPropPaths(target),
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

function deriveHoveredTargetId(target: BuilderEditableTarget | null, fallback: string | null): string | null {
    return target?.targetId ?? deriveHoveredElementId(target, fallback);
}

function hasSectionLocalId(
    sectionsDraft: SectionDraft[],
    sectionLocalId: string | null,
): boolean {
    if (!sectionLocalId) {
        return false;
    }

    return sectionsDraft.some((section) => section.localId === sectionLocalId);
}

function reconcileBuilderTarget(
    target: BuilderEditableTarget | null,
    sectionsDraft: SectionDraft[],
): BuilderEditableTarget | null {
    const sectionLocalId = target?.sectionLocalId ?? null;
    if (!sectionLocalId) {
        return target;
    }

    return hasSectionLocalId(sectionsDraft, sectionLocalId) ? target : null;
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
    selectedTargetId: string | null;
    hoveredTargetId: string | null;
    selectedTargetType: string | null;
    selectedNodePath: string | null;
    selectedNodeId: string | null;
    selectedComponentKey: string | null;
    selectedSchemaKey: string | null;
    selectedPropPaths: string[];
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
    isPreviewReady: boolean;
    isSidebarReady: boolean;
    lastMutationId: string | null;
    lastSyncedAt: number | null;
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
    selectTarget: (target: BuilderEditableTarget | null) => void;
    hoverTarget: (target: BuilderEditableTarget | null) => void;
    patchSelectedProps: (payload: {
        props: Record<string, unknown>;
        textPreview?: string | null;
        mutationId?: string | null;
    }) => void;
    deleteSelectedNode: (mutationId?: string | null) => void;
    insertNode: (payload: {
        sectionsDraft: SectionDraft[];
        selectedSectionLocalId?: string | null;
        selectedBuilderTarget?: BuilderEditableTarget | null;
        mutationId?: string | null;
    }) => void;
    syncFromRemote: (payload: {
        sectionsDraft?: SectionDraft[];
        selectedSectionLocalId?: string | null;
        selectedBuilderTarget?: BuilderEditableTarget | null;
        hoveredBuilderTarget?: BuilderEditableTarget | null;
        currentBreakpoint?: BuilderBreakpoint;
        currentInteractionState?: BuilderInteractionState;
        builderMode?: BuilderSidebarMode;
        syncedAt?: number | null;
        mutationId?: string | null;
    }) => void;
    markPreviewReady: (ready?: boolean) => void;
    markSidebarReady: (ready?: boolean) => void;
    applyMutationState: (next: {
        sectionsDraft: SectionDraft[];
        selectedSectionLocalId: string | null;
        selectedBuilderTarget: BuilderEditableTarget | null;
        mutationId?: string | null;
    }) => void;
    clearSelection: () => void;
    reset: () => void;
}

export const createInitialBuilderEditingState = () => ({
    sectionsDraft: [] as SectionDraft[],
    selectedSectionLocalId: null,
    selectedBuilderTarget: null,
    hoveredBuilderTarget: null,
    selectedTargetId: null,
    hoveredTargetId: null,
    selectedTargetType: null,
    selectedNodePath: null,
    selectedNodeId: null,
    selectedComponentKey: null,
    selectedSchemaKey: null,
    selectedPropPaths: [],
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
    isPreviewReady: false,
    isSidebarReady: false,
    lastMutationId: null,
    lastSyncedAt: null,
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
    selectTarget: (target) => {
        set((state) => {
            const selectedSectionLocalId = target?.sectionLocalId ?? null;
            if (
                state.selectedSectionLocalId === selectedSectionLocalId
                && shouldKeepCurrentSelectedBuilderTarget(state.selectedBuilderTarget, target)
            ) {
                return state;
            }

            return {
                selectedSectionLocalId,
                selectedBuilderTarget: target,
                ...deriveSelectedState(target, selectedSectionLocalId),
                lastSyncedAt: Date.now(),
            };
        });
    },
    setHoveredBuilderTarget: (next) => {
        set((state) => {
            const hoveredBuilderTarget = resolveStateAction(state.hoveredBuilderTarget, next);

            return {
                hoveredBuilderTarget,
                hoveredTargetId: deriveHoveredTargetId(hoveredBuilderTarget, state.builderHoveredElementId),
                hoveredElementId: deriveHoveredElementId(hoveredBuilderTarget, state.builderHoveredElementId),
            };
        });
    },
    hoverTarget: (target) => {
        set((state) => ({
            hoveredBuilderTarget: target,
            hoveredTargetId: deriveHoveredTargetId(target, state.builderHoveredElementId),
            hoveredElementId: deriveHoveredElementId(target, state.builderHoveredElementId),
            lastSyncedAt: Date.now(),
        }));
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
                hoveredTargetId: deriveHoveredTargetId(state.hoveredBuilderTarget, builderHoveredElementId),
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
    patchSelectedProps: ({ props, textPreview = null, mutationId = null }) => {
        set((state) => {
            if (!state.selectedBuilderTarget) {
                return state;
            }

            const nextTarget: BuilderEditableTarget = {
                ...state.selectedBuilderTarget,
                props: {
                    ...(state.selectedBuilderTarget.props ?? {}),
                    ...props,
                },
                textPreview: textPreview ?? state.selectedBuilderTarget.textPreview,
            };
            const nextSectionsDraft = state.selectedSectionLocalId
                ? state.sectionsDraft.map((section) => (
                    section.localId === state.selectedSectionLocalId
                        ? {
                            ...section,
                            props: nextTarget.props ?? section.props,
                        }
                        : section
                ))
                : state.sectionsDraft;

            return {
                sectionsDraft: nextSectionsDraft,
                selectedBuilderTarget: nextTarget,
                ...deriveSelectedState(nextTarget, state.selectedSectionLocalId),
                lastMutationId: mutationId,
                lastSyncedAt: Date.now(),
            };
        });
    },
    deleteSelectedNode: (mutationId = null) => {
        set((state) => {
            if (!state.selectedSectionLocalId) {
                return {
                    ...state,
                    lastMutationId: mutationId,
                    lastSyncedAt: Date.now(),
                };
            }

            const nextSectionsDraft = state.sectionsDraft.filter((section) => section.localId !== state.selectedSectionLocalId);
            return {
                sectionsDraft: nextSectionsDraft,
                selectedSectionLocalId: null,
                selectedBuilderTarget: null,
                hoveredBuilderTarget: state.hoveredBuilderTarget?.sectionLocalId === state.selectedSectionLocalId
                    ? null
                    : state.hoveredBuilderTarget,
                hoveredTargetId: state.hoveredBuilderTarget?.sectionLocalId === state.selectedSectionLocalId
                    ? null
                    : state.hoveredTargetId,
                hoveredElementId: state.hoveredBuilderTarget?.sectionLocalId === state.selectedSectionLocalId
                    ? null
                    : state.hoveredElementId,
                ...deriveSelectedState(null, null),
                lastMutationId: mutationId,
                lastSyncedAt: Date.now(),
            };
        });
    },
    insertNode: ({ sectionsDraft, selectedSectionLocalId = null, selectedBuilderTarget = null, mutationId = null }) => {
        set(() => ({
            sectionsDraft: normalizeBuilderSectionDrafts(sectionsDraft),
            selectedSectionLocalId,
            selectedBuilderTarget,
            ...deriveSelectedState(selectedBuilderTarget, selectedSectionLocalId),
            lastMutationId: mutationId,
            lastSyncedAt: Date.now(),
        }));
    },
    syncFromRemote: ({
        sectionsDraft,
        selectedSectionLocalId,
        selectedBuilderTarget,
        hoveredBuilderTarget,
        currentBreakpoint,
        currentInteractionState,
        builderMode,
        syncedAt = null,
        mutationId = null,
    }) => {
        set((state) => {
            const nextSelectedSectionLocalId = selectedBuilderTarget?.sectionLocalId
                ?? selectedSectionLocalId
                ?? state.selectedSectionLocalId;
            const nextSelectedTarget = selectedBuilderTarget ?? state.selectedBuilderTarget;
            const nextHoveredTarget = hoveredBuilderTarget ?? state.hoveredBuilderTarget;

            return {
                sectionsDraft: sectionsDraft ? normalizeBuilderSectionDrafts(sectionsDraft) : state.sectionsDraft,
                selectedSectionLocalId: nextSelectedSectionLocalId,
                selectedBuilderTarget: nextSelectedTarget,
                hoveredBuilderTarget: nextHoveredTarget,
                hoveredTargetId: deriveHoveredTargetId(nextHoveredTarget, state.builderHoveredElementId),
                hoveredElementId: deriveHoveredElementId(nextHoveredTarget, state.builderHoveredElementId),
                currentBreakpoint: currentBreakpoint ?? state.currentBreakpoint,
                currentInteractionState: currentInteractionState ?? state.currentInteractionState,
                builderMode: builderMode ?? state.builderMode,
                builderSidebarMode: builderMode ?? state.builderSidebarMode,
                ...deriveSelectedState(nextSelectedTarget, nextSelectedSectionLocalId),
                lastMutationId: mutationId,
                lastSyncedAt: syncedAt ?? Date.now(),
            };
        });
    },
    markPreviewReady: (ready = true) => {
        set(() => ({
            isPreviewReady: ready,
            lastSyncedAt: Date.now(),
        }));
    },
    markSidebarReady: (ready = true) => {
        set(() => ({
            isSidebarReady: ready,
            lastSyncedAt: Date.now(),
        }));
    },
    applyMutationState: (next) => {
        set((state) => {
            const normalizedSectionsDraft = normalizeBuilderSectionDrafts(next.sectionsDraft);
            const selectedBuilderTarget = reconcileBuilderTarget(next.selectedBuilderTarget, normalizedSectionsDraft);
            const selectedSectionLocalId = hasSectionLocalId(
                normalizedSectionsDraft,
                next.selectedSectionLocalId,
            )
                ? next.selectedSectionLocalId
                : (selectedBuilderTarget?.sectionLocalId ?? null);
            const hoveredBuilderTarget = reconcileBuilderTarget(state.hoveredBuilderTarget, normalizedSectionsDraft);

            return {
                sectionsDraft: normalizedSectionsDraft,
                selectedSectionLocalId,
                selectedBuilderTarget,
                hoveredBuilderTarget,
                hoveredTargetId: deriveHoveredTargetId(hoveredBuilderTarget, state.builderHoveredElementId),
                hoveredElementId: deriveHoveredElementId(hoveredBuilderTarget, state.builderHoveredElementId),
                ...deriveSelectedState(selectedBuilderTarget, selectedSectionLocalId),
                lastMutationId: next.mutationId ?? null,
                lastSyncedAt: Date.now(),
            };
        });
    },
    clearSelection: () => {
        set((state) => ({
            selectedSectionLocalId: null,
            selectedBuilderTarget: null,
            hoveredBuilderTarget: state.hoveredBuilderTarget,
            ...deriveSelectedState(null, null),
            lastSyncedAt: Date.now(),
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
