/**
 * Variant Matching Engine — select the best variant for each section from layout hints.
 *
 * Part 4 (Design-to-Builder): Matches image position, text alignment, button location,
 * and layout density to available component variants (e.g. hero-1 … hero-7).
 * Output is a single variant id per section for use in the section plan.
 */

import { hasEntry } from '../componentRegistry';
import { AVAILABLE_VARIANTS_BY_COMPONENT } from './componentSelector';

// ---------------------------------------------------------------------------
// Layout hints (from design analysis or vision)
// ---------------------------------------------------------------------------

export type ImagePosition = 'left' | 'right' | 'top' | 'background' | 'none';
export type TextAlignment = 'left' | 'center' | 'right';
export type ButtonLocation = 'left' | 'center' | 'right' | 'below';
export type LayoutDensity = 'compact' | 'medium' | 'spacious';

export interface VariantLayoutHints {
  imagePosition?: ImagePosition;
  textAlignment?: TextAlignment;
  buttonLocation?: ButtonLocation;
  layoutDensity?: LayoutDensity;
}

export interface VariantMatchInput {
  /** Registry component key (e.g. webu_general_hero_01). */
  componentKey: string;
  /** Layout hints detected from the design. */
  hints: VariantLayoutHints;
  /** Already used variant ids (avoid duplicates when matching multiple sections). */
  alreadyUsed?: Set<string>;
}

// ---------------------------------------------------------------------------
// Hero variant attributes (from design-system: hero-1 … hero-7)
// ---------------------------------------------------------------------------

const HERO_VARIANT_ATTRIBUTES: Record<
  string,
  { imagePosition?: ImagePosition; textAlignment?: TextAlignment; buttonLocation?: ButtonLocation; layoutDensity?: LayoutDensity }
> = {
  'hero-1': { textAlignment: 'center', layoutDensity: 'compact' },
  'hero-2': { imagePosition: 'top', textAlignment: 'center', buttonLocation: 'center', layoutDensity: 'medium' },
  'hero-3': { imagePosition: 'right', textAlignment: 'left', buttonLocation: 'left', layoutDensity: 'medium' },
  'hero-4': { imagePosition: 'background', textAlignment: 'center', buttonLocation: 'center', layoutDensity: 'spacious' },
  'hero-5': { imagePosition: 'left', textAlignment: 'left', buttonLocation: 'below', layoutDensity: 'spacious' },
  'hero-6': { imagePosition: 'background', textAlignment: 'center', buttonLocation: 'center', layoutDensity: 'spacious' },
  'hero-7': { imagePosition: 'right', textAlignment: 'left', layoutDensity: 'medium' },
};

// ---------------------------------------------------------------------------
// Features / CTA / Cards / Grid: variant order by density (0 = compact, 1 = medium, 2 = spacious)
// ---------------------------------------------------------------------------

const FEATURES_VARIANT_BY_DENSITY: Record<LayoutDensity, string> = {
  compact: 'features-1',
  medium: 'features-2',
  spacious: 'features-3',
};

const CTA_VARIANT_BY_DENSITY: Record<LayoutDensity, string> = {
  compact: 'cta-1',
  medium: 'cta-2',
  spacious: 'cta-3',
};

// ---------------------------------------------------------------------------
// Scoring: count matching hint attributes
// ---------------------------------------------------------------------------

function scoreHeroVariant(variantId: string, hints: VariantLayoutHints): number {
  const attrs = HERO_VARIANT_ATTRIBUTES[variantId];
  if (!attrs) return 0;
  let score = 0;
  if (hints.imagePosition != null && attrs.imagePosition === hints.imagePosition) score += 3;
  if (hints.textAlignment != null && attrs.textAlignment === hints.textAlignment) score += 2;
  if (hints.buttonLocation != null && attrs.buttonLocation === hints.buttonLocation) score += 2;
  if (hints.layoutDensity != null && attrs.layoutDensity === hints.layoutDensity) score += 1;
  return score;
}

function getHeroBestVariant(componentKey: string, hints: VariantLayoutHints, alreadyUsed: Set<string>): string {
  const variants = AVAILABLE_VARIANTS_BY_COMPONENT[componentKey];
  if (!variants?.length) return '';

  let bestId = '';
  let bestScore = -1;

  for (const variantId of variants) {
    if (alreadyUsed.has(variantId)) continue;
    const score = scoreHeroVariant(variantId, hints);
    if (score > bestScore) {
      bestScore = score;
      bestId = variantId;
    }
  }

  if (bestId) return bestId;
  return variants.find((v) => !alreadyUsed.has(v)) ?? variants[0] ?? '';
}

function getFeaturesBestVariant(componentKey: string, hints: VariantLayoutHints, alreadyUsed: Set<string>): string {
  const variants = AVAILABLE_VARIANTS_BY_COMPONENT[componentKey];
  if (!variants?.length) return '';
  const density = hints.layoutDensity ?? 'medium';
  const preferred = FEATURES_VARIANT_BY_DENSITY[density];
  if (preferred && variants.includes(preferred) && !alreadyUsed.has(preferred)) return preferred;
  return variants.find((v) => !alreadyUsed.has(v)) ?? variants[0] ?? '';
}

function getCtaBestVariant(componentKey: string, hints: VariantLayoutHints, alreadyUsed: Set<string>): string {
  const variants = AVAILABLE_VARIANTS_BY_COMPONENT[componentKey];
  if (!variants?.length) return '';
  const density = hints.layoutDensity ?? 'medium';
  const preferred = CTA_VARIANT_BY_DENSITY[density];
  if (preferred && variants.includes(preferred) && !alreadyUsed.has(preferred)) return preferred;
  return variants.find((v) => !alreadyUsed.has(v)) ?? variants[0] ?? '';
}

function getDefaultBestVariant(componentKey: string, alreadyUsed: Set<string>): string {
  const variants = AVAILABLE_VARIANTS_BY_COMPONENT[componentKey];
  if (!variants?.length) return '';
  return variants.find((v) => !alreadyUsed.has(v)) ?? variants[0] ?? '';
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Returns the best variant id for the given component and layout hints.
 * - Hero: scores variants by image position, text alignment, button location, layout density.
 * - Features / CTA: prefer variant by layout density (compact → 1, medium → 2, spacious → 3).
 * - Others: first unused variant or first available.
 * Only considers components that exist in the registry (Part 13).
 *
 * @example
 * matchVariant({ componentKey: 'webu_general_hero_01', hints: { imagePosition: 'right', textAlignment: 'left' } })
 * // => 'hero-3'
 */
export function matchVariant(input: VariantMatchInput): string {
  const { componentKey, hints, alreadyUsed = new Set<string>() } = input;

  if (!hasEntry(componentKey)) return '';

  if (componentKey === 'webu_general_hero_01') {
    return getHeroBestVariant(componentKey, hints, alreadyUsed);
  }
  if (componentKey === 'webu_general_features_01') {
    return getFeaturesBestVariant(componentKey, hints, alreadyUsed);
  }
  if (componentKey === 'webu_general_cta_01') {
    return getCtaBestVariant(componentKey, hints, alreadyUsed);
  }

  return getDefaultBestVariant(componentKey, alreadyUsed);
}

/**
 * Apply variant matching to a list of sections (e.g. from section mapper).
 * Each section gets a variant from matchVariant; alreadyUsed is tracked to avoid duplicates.
 */
export function applyVariantMatching(
  sections: Array<{ componentKey: string; variant?: string }>,
  hintsByIndex: (VariantLayoutHints | undefined)[] = []
): Array<{ componentKey: string; variant: string }> {
  const usedByComponent: Record<string, Set<string>> = {};
  return sections.map((section, index) => {
    const hints = hintsByIndex[index];
    const used = usedByComponent[section.componentKey] ?? new Set<string>();
    if (!usedByComponent[section.componentKey]) usedByComponent[section.componentKey] = used;

    const variant = matchVariant({
      componentKey: section.componentKey,
      hints: hints ?? {},
      alreadyUsed: used,
    });
    if (variant) used.add(variant);

    return {
      componentKey: section.componentKey,
      variant: variant || section.variant || '',
    };
  });
}
