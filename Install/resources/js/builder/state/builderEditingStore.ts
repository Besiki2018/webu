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
    buildEditableTargetFromMessagePayload,
    buildTargetId,
    editableTargetToMessagePayload,
    getBuilderTargetPropPaths,
    getBuilderTargetSchemaKey,
    getBuilderTargetStableNodeId,
} from '@/builder/editingState';
import { normalizeBuilderSectionDrafts } from '@/builder/model/pageModel';
import { buildSectionPreviewText, getValueAtPath } from '@/builder/state/sectionProps';
import type { BuilderSection } from '@/builder/visual/treeUtils';
import type { DropTarget } from '@/builder/visual/types';

export type SectionDraft = BuilderSection;

const INITIAL_STRUCTURE_PANEL_POSITION = { x: 24, y: 72 } as const;

type SetStateActionLike<T> = SetStateAction<T>;

function hasOwnKey<T extends object>(
    value: T,
    key: PropertyKey,
): boolean {
    return Object.prototype.hasOwnProperty.call(value, key);
}

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

function deriveHoveredState(
    target: BuilderEditableTarget | null,
    fallback: string | null,
): Pick<BuilderEditingStoreState, 'hoveredTargetId' | 'hoveredElementId'> {
    return {
        hoveredTargetId: deriveHoveredTargetId(target, fallback),
        hoveredElementId: deriveHoveredElementId(target, fallback),
    };
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

    const section = sectionsDraft.find((entry) => entry.localId === sectionLocalId) ?? null;
    if (!section) {
        return null;
    }

    const payload = editableTargetToMessagePayload(target);
    if (!payload) {
        return target;
    }

    const nextProps = section.props ?? {};
    const targetPath = target?.path?.trim() ?? '';
    const nextTextPreview = targetPath !== ''
        ? (() => {
            const nextValue = getValueAtPath(nextProps, targetPath);
            if (typeof nextValue === 'string' && nextValue.trim() !== '') {
                return nextValue.trim();
            }
            if (typeof nextValue === 'number' || typeof nextValue === 'boolean') {
                return String(nextValue);
            }

            return buildSectionPreviewText(
                nextProps,
                target?.textPreview ?? '',
                payload.sectionKey ?? payload.componentType ?? section.type,
            );
        })()
        : buildSectionPreviewText(
            nextProps,
            target?.textPreview ?? '',
            payload.sectionKey ?? payload.componentType ?? section.type,
        );

    return buildEditableTargetFromMessagePayload({
        ...payload,
        sectionLocalId: section.localId,
        sectionKey: payload.sectionKey ?? section.type,
        componentType: payload.componentType ?? section.type,
        props: nextProps,
        textPreview: nextTextPreview,
    });
}

function shouldKeepCurrentBuilderTarget(
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
});

function reconcileStructureState(
    state: Pick<
        BuilderEditingStoreState,
        | 'selectedSectionLocalId'
        | 'selectedBuilderTarget'
        | 'hoveredBuilderTarget'
        | 'builderHoveredElementId'
    >,
    sectionsDraft: SectionDraft[],
    overrides?: {
        selectedSectionLocalId?: string | null;
        selectedBuilderTarget?: BuilderEditableTarget | null;
        hoveredBuilderTarget?: BuilderEditableTarget | null;
        builderHoveredElementId?: string | null;
    },
): Pick<
    BuilderEditingStoreState,
    | 'sectionsDraft'
    | 'selectedSectionLocalId'
    | 'selectedBuilderTarget'
    | 'hoveredBuilderTarget'
    | 'builderHoveredElementId'
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
    | 'hoveredTargetId'
    | 'hoveredElementId'
> {
    const selectedBuilderTarget = reconcileBuilderTarget(
        overrides && hasOwnKey(overrides, 'selectedBuilderTarget')
            ? overrides.selectedBuilderTarget ?? null
            : state.selectedBuilderTarget,
        sectionsDraft,
    );
    const explicitSelectedSectionLocalId = overrides && hasOwnKey(overrides, 'selectedSectionLocalId')
        ? overrides.selectedSectionLocalId ?? null
        : state.selectedSectionLocalId;
    const selectedSectionLocalId = hasSectionLocalId(sectionsDraft, explicitSelectedSectionLocalId)
        ? explicitSelectedSectionLocalId
        : (selectedBuilderTarget?.sectionLocalId ?? null);
    const hoveredBuilderTarget = reconcileBuilderTarget(
        overrides && hasOwnKey(overrides, 'hoveredBuilderTarget')
            ? overrides.hoveredBuilderTarget ?? null
            : state.hoveredBuilderTarget,
        sectionsDraft,
    );
    const builderHoveredElementId = hoveredBuilderTarget
        ? (
            overrides && hasOwnKey(overrides, 'builderHoveredElementId')
                ? overrides.builderHoveredElementId ?? null
                : state.builderHoveredElementId
        )
        : null;

    return {
        sectionsDraft,
        selectedSectionLocalId,
        selectedBuilderTarget,
        hoveredBuilderTarget,
        builderHoveredElementId,
        ...deriveSelectedState(selectedBuilderTarget, selectedSectionLocalId),
        ...deriveHoveredState(hoveredBuilderTarget, builderHoveredElementId),
    };
}

export const useBuilderEditingStore = create<BuilderEditingStoreState>((set) => ({
    ...createInitialBuilderEditingState(),
    setSectionsDraft: (next) => {
        set((state) => reconcileStructureState(
            state,
            normalizeBuilderSectionDrafts(resolveStateAction(state.sectionsDraft, next)),
        ));
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
                && shouldKeepCurrentBuilderTarget(state.selectedBuilderTarget, selectedBuilderTarget)
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
                && shouldKeepCurrentBuilderTarget(state.selectedBuilderTarget, target)
            ) {
                return state;
            }

            return {
                selectedSectionLocalId,
                selectedBuilderTarget: target,
                ...deriveSelectedState(target, selectedSectionLocalId),
            };
        });
    },
    setHoveredBuilderTarget: (next) => {
        set((state) => {
            const hoveredBuilderTarget = resolveStateAction(state.hoveredBuilderTarget, next);
            const builderHoveredElementId = hoveredBuilderTarget ? state.builderHoveredElementId : null;

            if (
                shouldKeepCurrentBuilderTarget(state.hoveredBuilderTarget, hoveredBuilderTarget)
                && state.builderHoveredElementId === builderHoveredElementId
            ) {
                return state;
            }

            return {
                hoveredBuilderTarget,
                builderHoveredElementId,
                ...deriveHoveredState(hoveredBuilderTarget, builderHoveredElementId),
            };
        });
    },
    hoverTarget: (target) => {
        set((state) => {
            const builderHoveredElementId = target ? state.builderHoveredElementId : null;

            if (
                shouldKeepCurrentBuilderTarget(state.hoveredBuilderTarget, target)
                && state.builderHoveredElementId === builderHoveredElementId
            ) {
                return state;
            }

            return {
                hoveredBuilderTarget: target,
                builderHoveredElementId,
                ...deriveHoveredState(target, builderHoveredElementId),
            };
        });
    },
    setActiveDragId: (next) => {
        set((state) => {
            const activeDragId = resolveStateAction(state.activeDragId, next);
            if (state.activeDragId === activeDragId) {
                return state;
            }

            return {
                activeDragId,
            };
        });
    },
    setBuilderHoveredElementId: (next) => {
        set((state) => {
            const builderHoveredElementId = resolveStateAction(state.builderHoveredElementId, next);

            if (state.builderHoveredElementId === builderHoveredElementId) {
                return state;
            }

            return {
                builderHoveredElementId,
                ...deriveHoveredState(state.hoveredBuilderTarget, builderHoveredElementId),
            };
        });
    },
    setBuilderCurrentDropTarget: (next) => {
        set((state) => {
            const builderCurrentDropTarget = resolveStateAction(state.builderCurrentDropTarget, next);
            if (state.builderCurrentDropTarget === builderCurrentDropTarget) {
                return state;
            }

            return {
                builderCurrentDropTarget,
            };
        });
    },
    setCurrentBreakpoint: (next) => {
        set((state) => {
            const currentBreakpoint = resolveStateAction(state.currentBreakpoint, next);
            if (state.currentBreakpoint === currentBreakpoint) {
                return state;
            }

            return {
                currentBreakpoint,
            };
        });
    },
    setCurrentInteractionState: (next) => {
        set((state) => {
            const currentInteractionState = resolveStateAction(state.currentInteractionState, next);
            if (state.currentInteractionState === currentInteractionState) {
                return state;
            }

            return {
                currentInteractionState,
            };
        });
    },
    setSelectedSidebarTab: (next) => {
        set((state) => {
            const selectedSidebarTab = resolveStateAction(state.selectedSidebarTab, next);
            if (state.selectedSidebarTab === selectedSidebarTab) {
                return state;
            }

            return {
                selectedSidebarTab,
            };
        });
    },
    setIsStructurePanelCollapsed: (next) => {
        set((state) => {
            const isStructurePanelCollapsed = resolveStateAction(state.isStructurePanelCollapsed, next);
            if (state.isStructurePanelCollapsed === isStructurePanelCollapsed) {
                return state;
            }

            return {
                isStructurePanelCollapsed,
            };
        });
    },
    setStructurePanelPosition: (next) => {
        set((state) => {
            const structurePanelPosition = resolveStateAction(state.structurePanelPosition, next);
            if (
                state.structurePanelPosition.x === structurePanelPosition.x
                && state.structurePanelPosition.y === structurePanelPosition.y
            ) {
                return state;
            }

            return {
                structurePanelPosition,
            };
        });
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
            };
        });
    },
    deleteSelectedNode: (mutationId = null) => {
        set((state) => {
            if (!state.selectedSectionLocalId) {
                return {
                    ...state,
                    lastMutationId: mutationId,
                };
            }

            const nextSectionsDraft = state.sectionsDraft.filter((section) => section.localId !== state.selectedSectionLocalId);
            const shouldClearHover = state.hoveredBuilderTarget?.sectionLocalId === state.selectedSectionLocalId
                || state.builderHoveredElementId === state.selectedSectionLocalId;

            return {
                ...reconcileStructureState(state, nextSectionsDraft, {
                    selectedSectionLocalId: null,
                    selectedBuilderTarget: null,
                    hoveredBuilderTarget: shouldClearHover ? null : state.hoveredBuilderTarget,
                    builderHoveredElementId: shouldClearHover ? null : state.builderHoveredElementId,
                }),
                lastMutationId: mutationId,
            };
        });
    },
    insertNode: ({ sectionsDraft, selectedSectionLocalId = null, selectedBuilderTarget = null, mutationId = null }) => {
        set((state) => ({
            ...reconcileStructureState(state, normalizeBuilderSectionDrafts(sectionsDraft), {
                selectedSectionLocalId,
                selectedBuilderTarget,
            }),
            lastMutationId: mutationId,
        }));
    },
    syncFromRemote: (payload) => {
        set((state) => {
            const nextSectionsDraft = payload.sectionsDraft
                ? normalizeBuilderSectionDrafts(payload.sectionsDraft)
                : state.sectionsDraft;
            const hasSelectedSectionLocalIdUpdate = hasOwnKey(payload, 'selectedSectionLocalId');
            const hasSelectedBuilderTargetUpdate = hasOwnKey(payload, 'selectedBuilderTarget');
            const hasHoveredBuilderTargetUpdate = hasOwnKey(payload, 'hoveredBuilderTarget');
            const explicitSelectedSectionLocalId = hasSelectedSectionLocalIdUpdate
                ? payload.selectedSectionLocalId ?? null
                : undefined;

            let nextSelectedTarget = hasSelectedBuilderTargetUpdate
                ? payload.selectedBuilderTarget ?? null
                : state.selectedBuilderTarget;

            if (!hasSelectedBuilderTargetUpdate && explicitSelectedSectionLocalId !== undefined) {
                nextSelectedTarget = explicitSelectedSectionLocalId !== null
                    && state.selectedBuilderTarget?.sectionLocalId === explicitSelectedSectionLocalId
                    ? state.selectedBuilderTarget
                    : null;
            }

            nextSelectedTarget = reconcileBuilderTarget(nextSelectedTarget, nextSectionsDraft);

            const nextSelectedSectionLocalId = explicitSelectedSectionLocalId !== undefined
                ? (
                    hasSectionLocalId(nextSectionsDraft, explicitSelectedSectionLocalId)
                        ? explicitSelectedSectionLocalId
                        : (nextSelectedTarget?.sectionLocalId ?? null)
                )
                : (
                    nextSelectedTarget?.sectionLocalId
                    ?? (hasSectionLocalId(nextSectionsDraft, state.selectedSectionLocalId) ? state.selectedSectionLocalId : null)
                );

            return {
                ...reconcileStructureState(state, nextSectionsDraft, {
                    selectedSectionLocalId: nextSelectedSectionLocalId,
                    selectedBuilderTarget: nextSelectedTarget,
                    hoveredBuilderTarget: hasHoveredBuilderTargetUpdate ? payload.hoveredBuilderTarget ?? null : state.hoveredBuilderTarget,
                }),
                currentBreakpoint: payload.currentBreakpoint ?? state.currentBreakpoint,
                currentInteractionState: payload.currentInteractionState ?? state.currentInteractionState,
                builderMode: payload.builderMode ?? state.builderMode,
                builderSidebarMode: payload.builderMode ?? state.builderSidebarMode,
                lastMutationId: payload.mutationId ?? null,
            };
        });
    },
    markPreviewReady: (ready = true) => {
        set((state) => {
            if (state.isPreviewReady === ready) {
                return state;
            }

            return {
                isPreviewReady: ready,
            };
        });
    },
    markSidebarReady: (ready = true) => {
        set((state) => {
            if (state.isSidebarReady === ready) {
                return state;
            }

            return {
                isSidebarReady: ready,
            };
        });
    },
    applyMutationState: (next) => {
        set((state) => {
            return {
                ...reconcileStructureState(state, normalizeBuilderSectionDrafts(next.sectionsDraft), {
                    selectedSectionLocalId: next.selectedSectionLocalId,
                    selectedBuilderTarget: next.selectedBuilderTarget,
                }),
                lastMutationId: next.mutationId ?? null,
            };
        });
    },
    clearSelection: () => {
        set(() => ({
            selectedSectionLocalId: null,
            selectedBuilderTarget: null,
            hoveredBuilderTarget: null,
            builderHoveredElementId: null,
            builderCurrentDropTarget: null,
            activeDragId: null,
            ...deriveHoveredState(null, null),
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
