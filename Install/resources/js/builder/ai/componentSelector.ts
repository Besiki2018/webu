/**
 * Component Selection Engine — select best component variants.
 *
 * Part 4: Selects variant per section based on projectType, design tone, industry,
 * and layout complexity. Rules: match projectType, match category, prefer modern variants, avoid duplicates.
 * Part 11: Smart variants — when no tone set, prefer balanced layouts, modern hero, visual hierarchy;
 * avoid old/simple variants and duplicate layouts.
 * Part 13: Only select variants for components that exist in the registry (hasEntry); otherwise return ''.
 */

import type { ProjectType } from '../projectTypes';
import { hasEntry } from '../componentRegistry';
import {
  getSmartPreferredIndices,
  filterAvoidedIndices,
} from './smartVariants';

// ---------------------------------------------------------------------------
// Available variants per registry component key (schema variant ids)
// ---------------------------------------------------------------------------

export const AVAILABLE_VARIANTS_BY_COMPONENT: Record<string, readonly string[]> = {
  webu_header_01: ['header-1'],
  webu_footer_01: ['footer-1'],
  webu_general_hero_01: ['hero-1', 'hero-2', 'hero-3', 'hero-4', 'hero-5', 'hero-6', 'hero-7'],
  webu_general_features_01: ['features-1', 'features-2', 'features-3', 'features-4'],
  webu_general_cta_01: ['cta-1', 'cta-2', 'cta-3', 'cta-4'],
  webu_general_navigation_01: ['navigation-1'],
  webu_general_cards_01: ['cards-1', 'cards-2'],
  webu_general_grid_01: ['grid-1', 'grid-2'],
};

/** Variant indices considered "modern" per component (0-based). Prefer these when tone is modern. */
const MODERN_VARIANT_INDICES: Record<string, number[]> = {
  webu_general_hero_01: [2, 3, 4],
  webu_general_features_01: [1, 2],
  webu_general_cta_01: [1, 2],
  webu_general_cards_01: [1],
  webu_general_grid_01: [1],
};

/** Variant indices for "minimal" tone (lower index = simpler). */
const MINIMAL_VARIANT_INDICES: Record<string, number[]> = {
  webu_general_hero_01: [0, 1],
  webu_general_features_01: [0, 1],
  webu_general_cta_01: [0, 1],
  webu_general_cards_01: [0],
  webu_general_grid_01: [0],
};

/** Variant indices for "bold" tone (higher impact layouts). */
const BOLD_VARIANT_INDICES: Record<string, number[]> = {
  webu_general_hero_01: [3, 4, 5],
  webu_general_features_01: [2, 3],
  webu_general_cta_01: [2, 3],
  webu_general_cards_01: [1],
  webu_general_grid_01: [1],
};

/** Layout complexity: influences variant choice (simple → lower index, complex → allow higher). */
export type LayoutComplexity = 'simple' | 'medium' | 'complex';

export interface ComponentSelectionContext {
  /** Project type (ecommerce, saas, etc.). */
  projectType: ProjectType;
  /** Design tone (modern, minimal, bold, etc.). */
  tone: string | null;
  /** Industry (furniture, fashion, tech, etc.). */
  industry: string | null;
  /** Layout complexity. Optional; default medium. */
  layoutComplexity?: LayoutComplexity;
  /** Already selected variant ids per component key (avoid duplicates when planning multiple sections of same type). */
  alreadyUsedVariantsByComponent?: Record<string, Set<string>>;
}

// ---------------------------------------------------------------------------
// Selection rules
// ---------------------------------------------------------------------------

function getVariantsForComponent(componentKey: string): string[] {
  const list = AVAILABLE_VARIANTS_BY_COMPONENT[componentKey];
  return list ? [...list] : [];
}

/** Prefer variants at given indices; filter to those not in used set; return first match or fallback. */
function pickFromIndices(
  variants: string[],
  indices: number[],
  used: Set<string>
): string | null {
  for (const i of indices) {
    const v = variants[i];
    if (v != null && !used.has(v)) return v;
  }
  return null;
}

/** Prefer variants at given indices (any); return first not in used. */
function pickFirstUnused(variants: string[], indices: number[], used: Set<string>): string | null {
  const sorted = indices.filter((i) => i >= 0 && i < variants.length);
  for (const i of sorted) {
    const v = variants[i];
    if (v != null && !used.has(v)) return v;
  }
  return null;
}

/** Default preference order by layout complexity (simple → earlier variants). */
function complexityIndices(componentKey: string, complexity: LayoutComplexity): number[] {
  const variants = getVariantsForComponent(componentKey);
  const n = variants.length;
  if (n === 0) return [];
  switch (complexity) {
    case 'simple':
      return [0, 1].filter((i) => i < n);
    case 'complex':
      return [Math.min(3, n - 1), Math.min(2, n - 1), Math.min(4, n - 1)].filter((i) => i >= 0 && i < n);
    default:
      return [1, 2, 0].filter((i) => i < n);
  }
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Selects the best variant for a component given context.
 * Rules: match projectType (via context), prefer tone-aligned variants (modern/minimal/bold),
 * respect layout complexity, avoid duplicates (use alreadyUsedVariantsByComponent).
 * Part 13: Only use registry components; if componentKey is not in registry, return ''.
 */
export function selectVariant(
  componentKey: string,
  context: ComponentSelectionContext
): string {
  if (!hasEntry(componentKey)) return '';
  const variants = getVariantsForComponent(componentKey);
  if (variants.length === 0) return '';
  if (variants.length === 1) return variants[0]!;

  const used = context.alreadyUsedVariantsByComponent?.[componentKey] ?? new Set<string>();
  const tone = (context.tone ?? '').toLowerCase();
  const complexity = context.layoutComplexity ?? 'medium';

  let chosen: string | null = null;

  if (tone === 'modern') {
    const indices = MODERN_VARIANT_INDICES[componentKey];
    if (indices?.length) chosen = pickFromIndices(variants, indices, used);
  } else if (tone === 'minimal') {
    const indices = MINIMAL_VARIANT_INDICES[componentKey];
    if (indices?.length) chosen = pickFromIndices(variants, indices, used);
  } else if (tone === 'bold') {
    const indices = BOLD_VARIANT_INDICES[componentKey];
    if (indices?.length) chosen = pickFromIndices(variants, indices, used);
  }

  if (!chosen) {
    const smartPrefer = getSmartPreferredIndices(componentKey);
    if (smartPrefer?.length) {
      const allowed = filterAvoidedIndices(smartPrefer, componentKey);
      if (allowed.length) chosen = pickFromIndices(variants, allowed, used);
    }
  }

  if (!chosen) {
    const fallbackIndices = complexityIndices(componentKey, complexity);
    chosen = pickFirstUnused(variants, fallbackIndices, used);
  }

  if (!chosen) chosen = variants.find((v) => !used.has(v)) ?? variants[0] ?? '';

  return chosen;
}

/**
 * Applies variant selection to a list of planned sections (componentKey + optional variant).
 * Updates each section with the selected variant and tracks used variants to avoid duplicates.
 */
export function applyVariantSelection<T extends { componentKey: string; variant?: string }>(
  sections: T[],
  context: Omit<ComponentSelectionContext, 'alreadyUsedVariantsByComponent'>
): Array<T & { variant: string }> {
  const usedByComponent: Record<string, Set<string>> = {};
  const result: Array<T & { variant: string }> = [];

  for (const section of sections) {
    const key = section.componentKey;
    const used = usedByComponent[key] ?? new Set<string>();
    if (!usedByComponent[key]) usedByComponent[key] = used;

    const selected = selectVariant(key, {
      ...context,
      alreadyUsedVariantsByComponent: usedByComponent,
    });

    if (selected) used.add(selected);
    result.push({ ...section, variant: selected || section.variant || '' });
  }

  return result;
}
