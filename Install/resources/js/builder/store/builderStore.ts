/**
 * Builder store — builder state.
 * Holds component tree, selection, hover, breakpoint, mode, selected props, and project type.
 */

import { create } from 'zustand';
import type { BuilderComponentInstance } from '../core';
import { defaultProjectType, type ProjectType } from '../projectTypes';
import { injectTreeMetadata } from '../componentMetadataInjection';
import type { BuildGenerationDiagnostics } from '../ai/blueprintTypes';
import type { BuilderGenerationState, BuilderGenerationStep } from '../state/builderGenerationState';

// ---------------------------------------------------------------------------
// State shape
// ---------------------------------------------------------------------------
export type BuilderBreakpoint = 'desktop' | 'tablet' | 'mobile';
export type BuilderMode = 'elements' | 'structure' | 'preview';
export type BuilderGenerationStepStatus = 'pending' | 'active' | 'complete';

export interface BuilderGenerationProgress {
  rawStatus: string | null;
  headline: string | null;
  detail: string | null;
  isActive: boolean;
  isFailed: boolean;
  readyForBuilder: boolean;
  locked: boolean;
  errorMessage: string | null;
  recoveryMessage: string | null;
  steps: Array<{
    key: BuilderGenerationStep['key'];
    label: string;
    status: BuilderGenerationStepStatus;
    detail: string | null;
  }>;
}

export interface BuilderStoreState {
  /** Project type (business, ecommerce, saas, etc.). Every project must include this. */
  projectType: ProjectType;
  componentTree: BuilderComponentInstance[];
  selectedComponentId: string | null;
  hoveredComponentId: string | null;
  currentBreakpoint: BuilderBreakpoint;
  builderMode: BuilderMode;
  selectedProps: Record<string, unknown> | null;
  generationStage: BuilderGenerationState;
  generationProgress: BuilderGenerationProgress;
  generationDiagnostics: BuildGenerationDiagnostics | null;

  setProjectType: (next: ProjectType) => void;
  setComponentTree: (next: BuilderComponentInstance[] | ((prev: BuilderComponentInstance[]) => BuilderComponentInstance[])) => void;
  setSelectedComponentId: (next: string | null) => void;
  setHoveredComponentId: (next: string | null) => void;
  setCurrentBreakpoint: (next: BuilderBreakpoint) => void;
  setBuilderMode: (next: BuilderMode) => void;
  setSelectedProps: (next: Record<string, unknown> | null) => void;
  setGenerationState: (next: {
    stage: BuilderGenerationState;
    progress: BuilderGenerationProgress;
    diagnostics?: BuildGenerationDiagnostics | null;
  }) => void;
  clearGenerationState: () => void;

  clearSelection: () => void;
  reset: () => void;
}

export const initialGenerationProgress: BuilderGenerationProgress = {
  rawStatus: null,
  headline: null,
  detail: null,
  isActive: false,
  isFailed: false,
  readyForBuilder: false,
  locked: false,
  errorMessage: null,
  recoveryMessage: null,
  steps: [],
};

export const initialState = {
  projectType: defaultProjectType as ProjectType,
  componentTree: [] as BuilderComponentInstance[],
  selectedComponentId: null as string | null,
  hoveredComponentId: null as string | null,
  currentBreakpoint: 'desktop' as BuilderBreakpoint,
  builderMode: 'elements' as BuilderMode,
  selectedProps: null as Record<string, unknown> | null,
  generationStage: 'idle' as BuilderGenerationState,
  generationProgress: initialGenerationProgress,
  generationDiagnostics: null as BuildGenerationDiagnostics | null,
};

function findComponentInTree(
  tree: BuilderComponentInstance[],
  componentId: string | null,
): BuilderComponentInstance | null {
  if (!componentId) {
    return null;
  }

  const stack = [...tree];
  while (stack.length > 0) {
    const node = stack.shift() ?? null;
    if (!node) {
      continue;
    }

    if (node.id === componentId) {
      return node;
    }

    if (Array.isArray(node.children) && node.children.length > 0) {
      stack.unshift(...node.children);
    }
  }

  return null;
}

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------
export const useBuilderStore = create<BuilderStoreState>((set) => ({
  ...initialState,

  setProjectType: (next) => {
    set({ projectType: next });
  },

  setComponentTree: (next) => {
    set((state) => {
      const raw = typeof next === 'function' ? next(state.componentTree) : next;
      const tree = injectTreeMetadata(raw);
      const selectedNode = findComponentInTree(tree, state.selectedComponentId);
      const hoveredNode = findComponentInTree(tree, state.hoveredComponentId);

      return {
        componentTree: tree,
        selectedComponentId: selectedNode ? state.selectedComponentId : null,
        hoveredComponentId: hoveredNode ? state.hoveredComponentId : null,
        selectedProps: selectedNode?.props ?? null,
      };
    });
  },

  setSelectedComponentId: (next) => {
    set({ selectedComponentId: next });
  },

  setHoveredComponentId: (next) => {
    set({ hoveredComponentId: next });
  },

  setCurrentBreakpoint: (next) => {
    set({ currentBreakpoint: next });
  },

  setBuilderMode: (next) => {
    set({ builderMode: next });
  },

  setSelectedProps: (next) => {
    set({ selectedProps: next });
  },

  setGenerationState: (next) => {
    set({
      generationStage: next.stage,
      generationProgress: next.progress,
      generationDiagnostics: next.diagnostics ?? null,
    });
  },

  clearGenerationState: () => {
    set({
      generationStage: 'idle',
      generationProgress: initialGenerationProgress,
      generationDiagnostics: null,
    });
  },

  clearSelection: () => {
    set({ selectedComponentId: null, selectedProps: null });
  },

  reset: () => {
    set(initialState);
  },
}));
