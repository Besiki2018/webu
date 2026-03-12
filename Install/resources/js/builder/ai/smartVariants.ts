/**
 * Part 11 — Smart Variants.
 *
 * When generating sections, prefer balanced layouts, modern hero designs, and visual hierarchy.
 * Avoid old/simple variants and duplicate layouts (duplicates handled in component selector).
 */

// ---------------------------------------------------------------------------
// Prefer: balanced layouts, modern hero, visual hierarchy
// ---------------------------------------------------------------------------

/** Variant indices to prefer when using "smart" selection (0-based). Balanced layouts, modern hero, clear hierarchy. */
export const SMART_PREFERRED_INDICES: Record<string, number[]> = {
  webu_general_hero_01: [2, 3, 4],
  webu_general_features_01: [1, 2],
  webu_general_cta_01: [1, 2],
  webu_general_cards_01: [1],
  webu_general_grid_01: [0, 1],
};

// ---------------------------------------------------------------------------
// Avoid: old/simple variants
// ---------------------------------------------------------------------------

/** Variant indices to avoid when using "smart" selection (0-based). Old or overly simple layouts. */
export const SMART_AVOID_INDICES: Record<string, number[]> = {
  webu_general_hero_01: [0, 1],
  webu_general_features_01: [0],
  webu_general_cta_01: [0],
  webu_general_cards_01: [],
  webu_general_grid_01: [],
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

/**
 * Returns preferred indices for smart selection for a component, or undefined if not configured.
 */
export function getSmartPreferredIndices(componentKey: string): number[] | undefined {
  return SMART_PREFERRED_INDICES[componentKey];
}

/**
 * Returns indices to avoid for smart selection for a component.
 */
export function getSmartAvoidIndices(componentKey: string): number[] {
  return SMART_AVOID_INDICES[componentKey] ?? [];
}

/**
 * Filters a list of variant indices to exclude avoided (old/simple) indices.
 */
export function filterAvoidedIndices(indices: number[], componentKey: string): number[] {
  const avoid = new Set(getSmartAvoidIndices(componentKey));
  return indices.filter((i) => !avoid.has(i));
}
