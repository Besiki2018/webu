/**
 * Phase 14 — Performance Optimization.
 *
 * Builder must support: lazy components, virtual canvas, fast drag, fast rerender.
 * Target: <50ms interaction time.
 */

import type { ComponentType } from 'react';

// ---------------------------------------------------------------------------
// Target
// ---------------------------------------------------------------------------

/** Target max time from user interaction to visible response (e.g. drag start, selection, reorder). */
export const INTERACTION_TARGET_MS = 50;

// ---------------------------------------------------------------------------
// Lazy components
// ---------------------------------------------------------------------------

/**
 * Lazy factory type: returns a dynamic import for a section component.
 * Use with React.lazy() so section chunks load on demand and reduce initial bundle.
 */
export type LazySectionFactory = () => Promise<{ default: ComponentType<Record<string, unknown>> }>;

/**
 * Optional registry: componentKey → lazy factory.
 * When set, canvas (or wrapper) can render React.lazy(registry[key]) instead of sync component for below-fold or virtualized items.
 */
export const lazySectionRegistry: Record<string, LazySectionFactory> = {};

/** Check if a section component is available as a lazy load. */
export function getLazySectionFactory(componentKey: string): LazySectionFactory | null {
  return lazySectionRegistry[componentKey] ?? null;
}

// ---------------------------------------------------------------------------
// Virtual canvas
// ---------------------------------------------------------------------------

export interface VisibleRangeResult {
  /** First index to render (inclusive). */
  startIndex: number;
  /** Last index to render (inclusive). */
  endIndex: number;
  /** Height in px to render above the first visible item (for scroll stability). */
  offsetTop: number;
  /** Total height of all items (for container height). */
  totalHeight: number;
}

/**
 * Computes the visible range of items for a virtualized list.
 * Use with a fixed or estimated item height so only visible sections are mounted.
 *
 * @param containerHeight - Visible viewport height in px.
 * @param scrollTop - Current scroll position in px.
 * @param itemCount - Total number of sections.
 * @param getItemHeight - (index) => height in px; if omitted, uses defaultSectionHeightEstimate.
 */
export function getVisibleRange(
  containerHeight: number,
  scrollTop: number,
  itemCount: number,
  getItemHeight?: (index: number) => number
): VisibleRangeResult {
  const defaultHeight = 200;
  const getHeight = getItemHeight ?? (() => defaultHeight);

  const heights: number[] = [];
  let totalHeight = 0;
  for (let i = 0; i < itemCount; i++) {
    const h = getHeight(i);
    heights.push(h);
    totalHeight += h;
  }

  if (itemCount === 0) {
    return { startIndex: 0, endIndex: -1, offsetTop: 0, totalHeight: 0 };
  }

  let offsetTop = 0;
  let startIndex = 0;
  for (let i = 0; i < itemCount; i++) {
    const h = heights[i];
    if (offsetTop + h > scrollTop) {
      startIndex = i;
      break;
    }
    offsetTop += h;
  }

  let endIndex = startIndex;
  let bottom = offsetTop;
  for (let i = startIndex; i < itemCount; i++) {
    const h = heights[i];
    if (bottom > scrollTop + containerHeight) break;
    endIndex = i;
    bottom += h;
  }

  return {
    startIndex,
    endIndex,
    offsetTop,
    totalHeight,
  };
}

/** Default estimated height per section when actual heights are unknown (e.g. first paint). */
export const DEFAULT_SECTION_HEIGHT_ESTIMATE = 200;

// ---------------------------------------------------------------------------
// Fast drag (dnd-kit recommendations)
// ---------------------------------------------------------------------------

/**
 * Recommended pointer activation distance in px.
 * Prevents drag from starting on tiny movements; keeps click vs drag unambiguous and reduces layout thrash.
 */
export const DRAG_ACTIVATION_DISTANCE_PX = 5;

/**
 * Recommended delay before drag can start (ms). Use 0 for immediate drag after distance.
 * Slight delay can reduce accidental drags; 0 minimizes interaction time.
 */
export const DRAG_ACTIVATION_DELAY_MS = 0;

// ---------------------------------------------------------------------------
// Fast rerender
// ---------------------------------------------------------------------------

/**
 * Stable key for a section row: use section.localId so reorders don't remount unnecessarily.
 * Canvas already uses key={section.localId}; this documents the contract.
 */
export function getSectionRowKey(localId: string): string {
  return localId;
}
