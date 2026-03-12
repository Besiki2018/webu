/**
 * Builder store — builder state.
 * Holds component tree, selection, hover, breakpoint, mode, selected props, and project type.
 */

import { create } from 'zustand';
import type { BuilderComponentInstance } from '../core';
import { defaultProjectType, type ProjectType } from '../projectTypes';
import { injectTreeMetadata } from '../componentMetadataInjection';

// ---------------------------------------------------------------------------
// State shape
// ---------------------------------------------------------------------------
export type BuilderBreakpoint = 'desktop' | 'tablet' | 'mobile';
export type BuilderMode = 'elements' | 'structure' | 'preview';

export interface BuilderStoreState {
  /** Project type (business, ecommerce, saas, etc.). Every project must include this. */
  projectType: ProjectType;
  componentTree: BuilderComponentInstance[];
  selectedComponentId: string | null;
  hoveredComponentId: string | null;
  currentBreakpoint: BuilderBreakpoint;
  builderMode: BuilderMode;
  selectedProps: Record<string, unknown> | null;

  setProjectType: (next: ProjectType) => void;
  setComponentTree: (next: BuilderComponentInstance[] | ((prev: BuilderComponentInstance[]) => BuilderComponentInstance[])) => void;
  setSelectedComponentId: (next: string | null) => void;
  setHoveredComponentId: (next: string | null) => void;
  setCurrentBreakpoint: (next: BuilderBreakpoint) => void;
  setBuilderMode: (next: BuilderMode) => void;
  setSelectedProps: (next: Record<string, unknown> | null) => void;

  clearSelection: () => void;
  reset: () => void;
}

export const initialState = {
  projectType: defaultProjectType as ProjectType,
  componentTree: [] as BuilderComponentInstance[],
  selectedComponentId: null as string | null,
  hoveredComponentId: null as string | null,
  currentBreakpoint: 'desktop' as BuilderBreakpoint,
  builderMode: 'elements' as BuilderMode,
  selectedProps: null as Record<string, unknown> | null,
};

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
      return { componentTree: tree };
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

  clearSelection: () => {
    set({ selectedComponentId: null, selectedProps: null });
  },

  reset: () => {
    set(initialState);
  },
}));
