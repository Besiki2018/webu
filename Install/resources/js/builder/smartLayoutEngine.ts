/**
 * Phase 12 — Smart Layout Engine.
 *
 * AI must understand layout: grid, columns, spacing, alignment, responsive stacking.
 * Allows AI to restructure sections via a canonical layout vocabulary that maps to component props.
 */

import type { BuilderComponentInstance } from './core/types';
import { getEntry } from './registry/componentRegistry';
import { resolveToken } from './designTokens';

// ---------------------------------------------------------------------------
// Layout vocabulary (AI-understandable)
// ---------------------------------------------------------------------------

export type LayoutAlignment = 'left' | 'center' | 'right';

export const LAYOUT_ALIGNMENT_OPTIONS: LayoutAlignment[] = ['left', 'center', 'right'];

/** Responsive stacking: on small screens, stack to 1 column. */
export type ResponsiveStacking = 'stack' | 'wrap' | 'none';

export const LAYOUT_COLUMN_OPTIONS = [1, 2, 3, 4, 5, 6] as const;
export type LayoutColumnCount = (typeof LAYOUT_COLUMN_OPTIONS)[number];

/** Layout intent — what the AI sends when restructure_layout is requested. */
export interface LayoutIntent {
  /** Grid: use grid layout with N columns. */
  grid?: boolean;
  /** Number of columns (1–6). */
  columns?: LayoutColumnCount | number;
  /** Spacing between items (token name e.g. 'lg' or CSS value). */
  spacing?: string;
  /** Horizontal/content alignment. */
  alignment?: LayoutAlignment;
  /** On mobile/tablet: stack to 1 column, wrap, or no change. */
  responsiveStacking?: ResponsiveStacking;
  /** Per-breakpoint column override (e.g. { mobile: 1, tablet: 2 }). */
  responsiveColumns?: Record<string, number>;
}

// ---------------------------------------------------------------------------
// Schema-aware layout props (which props does a component accept for layout?)
// ---------------------------------------------------------------------------

function componentAcceptsLayoutProp(componentKey: string, propKey: string): boolean {
  const entry = getEntry(componentKey);
  if (!entry?.schema || typeof entry.schema !== 'object') return false;
  const props = (entry.schema as { props?: Record<string, unknown> }).props;
  if (!props || typeof props !== 'object') return false;
  return Object.prototype.hasOwnProperty.call(props, propKey);
}

/**
 * Converts a layout intent into a prop patch for a given component.
 * Only includes props that the component schema accepts.
 */
export function layoutIntentToPropPatch(
  intent: LayoutIntent,
  componentKey: string
): Record<string, unknown> {
  const patch: Record<string, unknown> = {};

  if (intent.columns !== undefined && componentAcceptsLayoutProp(componentKey, 'columns')) {
    patch.columns = Math.min(6, Math.max(1, intent.columns));
  }
  if (intent.alignment !== undefined && componentAcceptsLayoutProp(componentKey, 'alignment')) {
    patch.alignment = intent.alignment;
  }
  if (intent.spacing !== undefined) {
    const spacingValue = resolveToken('spacing.' + intent.spacing) ?? intent.spacing;
    if (componentAcceptsLayoutProp(componentKey, 'spacing')) patch.spacing = spacingValue;
    if (componentAcceptsLayoutProp(componentKey, 'gap')) patch.gap = spacingValue;
    if (componentAcceptsLayoutProp(componentKey, 'padding')) patch.padding = spacingValue;
  }
  if (intent.responsiveStacking === 'stack' && intent.responsiveColumns === undefined) {
    intent.responsiveColumns = { mobile: 1, tablet: 2 };
  }
  if (intent.responsiveColumns && Object.keys(intent.responsiveColumns).length > 0) {
    if (componentAcceptsLayoutProp(componentKey, 'responsive')) {
      patch.responsive = {
        ...(patch.responsive as Record<string, unknown>),
        ...intent.responsiveColumns,
      };
    }
    if (componentAcceptsLayoutProp(componentKey, 'gridColumns')) {
      patch.gridColumns = intent.responsiveColumns;
    }
  }

  return patch;
}

/**
 * Returns layout-relevant props from a node (for AI to read current layout state).
 */
export function getLayoutSummary(node: BuilderComponentInstance): LayoutIntent {
  const props = node.props ?? {};
  const intent: LayoutIntent = {};
  if (typeof props.columns === 'number') intent.columns = props.columns as LayoutColumnCount;
  if (typeof props.alignment === 'string' && LAYOUT_ALIGNMENT_OPTIONS.includes(props.alignment as LayoutAlignment)) {
    intent.alignment = props.alignment as LayoutAlignment;
  }
  if (typeof props.spacing === 'string' || typeof props.gap === 'string') {
    intent.spacing = props.spacing as string || (props.gap as string);
  }
  const responsive = props.responsive ?? props.gridColumns;
  if (typeof responsive === 'object' && responsive !== null && !Array.isArray(responsive)) {
    const r = responsive as Record<string, unknown>;
    if (r.mobile === 1 && (r.tablet === 2 || r.tablet === 1)) intent.responsiveStacking = 'stack';
    intent.responsiveColumns = r as Record<string, number>;
  }
  return intent;
}

/** Human-readable layout vocabulary for AI prompts. */
export const LAYOUT_VOCABULARY = {
  grid: 'Grid layout with configurable columns (1–6).',
  columns: 'Number of columns (1–6).',
  spacing: 'Spacing between items (token: xs, sm, md, lg, xl, 2xl).',
  alignment: 'Horizontal alignment: left, center, right.',
  responsiveStacking: 'On mobile: stack (1 col), wrap, or none.',
  responsiveColumns: 'Per-breakpoint columns: { mobile: 1, tablet: 2, desktop: 3 }.',
} as const;
