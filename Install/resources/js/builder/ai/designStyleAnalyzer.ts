/**
 * Design Style Analyzer — detect style characteristics and select component variants.
 *
 * Part 8 (Design-to-Builder): Detects modern, minimal, corporate, startup, ecommerce, dark theme.
 * Uses style to pick component variants (e.g. minimal → hero-2, corporate → hero-1).
 */

import { hasEntry } from '../componentRegistry';
import { AVAILABLE_VARIANTS_BY_COMPONENT } from './componentSelector';

// ---------------------------------------------------------------------------
// Style types
// ---------------------------------------------------------------------------

export type DesignStyle =
  | 'modern'
  | 'minimal'
  | 'corporate'
  | 'startup'
  | 'ecommerce'
  | 'dark';

export interface StyleDetectionResult {
  /** Primary detected style. */
  style: DesignStyle;
  /** 0–1 confidence. Optional. */
  confidence?: number;
  /** True if dark theme detected (can combine with other styles). */
  isDarkTheme?: boolean;
}

// ---------------------------------------------------------------------------
// Vision provider (optional — app injects API that analyzes design image)
// ---------------------------------------------------------------------------

/**
 * Provider that analyzes a design image and returns detected style.
 * The app can wire this to a vision model (e.g. GPT-4V, Claude).
 */
export type StyleVisionProvider = (
  designImageSource: string,
  options?: { projectType?: string }
) => Promise<DesignStyle | StyleDetectionResult>;

// ---------------------------------------------------------------------------
// Style → variant mapping (use style to select component variants)
// ---------------------------------------------------------------------------

/** Recommended variant per component for each design style. */
const STYLE_TO_VARIANT: Record<DesignStyle, Record<string, string>> = {
  minimal: {
    webu_general_hero_01: 'hero-2',
    webu_general_features_01: 'features-1',
    webu_general_cta_01: 'cta-1',
    webu_general_cards_01: 'cards-1',
    webu_general_grid_01: 'grid-1',
  },
  corporate: {
    webu_general_hero_01: 'hero-1',
    webu_general_features_01: 'features-1',
    webu_general_cta_01: 'cta-1',
    webu_general_cards_01: 'cards-1',
    webu_general_grid_01: 'grid-1',
  },
  modern: {
    webu_general_hero_01: 'hero-2',
    webu_general_features_01: 'features-2',
    webu_general_cta_01: 'cta-2',
    webu_general_cards_01: 'cards-2',
    webu_general_grid_01: 'grid-2',
  },
  startup: {
    webu_general_hero_01: 'hero-2',
    webu_general_features_01: 'features-2',
    webu_general_cta_01: 'cta-2',
    webu_general_cards_01: 'cards-1',
    webu_general_grid_01: 'grid-1',
  },
  ecommerce: {
    webu_general_hero_01: 'hero-1',
    webu_general_features_01: 'features-2',
    webu_general_cta_01: 'cta-2',
    webu_general_cards_01: 'cards-2',
    webu_general_grid_01: 'grid-2',
  },
  dark: {
    webu_general_hero_01: 'hero-4',
    webu_general_features_01: 'features-2',
    webu_general_cta_01: 'cta-2',
    webu_general_cards_01: 'cards-2',
    webu_general_grid_01: 'grid-2',
  },
};

// ---------------------------------------------------------------------------
// Heuristic: projectType → style when no vision
// ---------------------------------------------------------------------------

const PROJECT_TYPE_TO_STYLE: Record<string, DesignStyle> = {
  saas: 'startup',
  ecommerce: 'ecommerce',
  business: 'corporate',
  landing: 'modern',
  portfolio: 'minimal',
  restaurant: 'modern',
  hotel: 'modern',
  blog: 'minimal',
  education: 'corporate',
};

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

export interface DetectStyleOptions {
  /** Vision provider to analyze design image. If omitted, heuristic is used. */
  styleVisionProvider?: StyleVisionProvider | null;
  /** Project type for heuristic when no vision or image. */
  projectType?: string;
  /** Design image (data URL or URL). Required when using styleVisionProvider. */
  designImageSource?: string;
}

/**
 * Detects design style from an image (via vision provider) or from context (projectType heuristic).
 *
 * @param options — styleVisionProvider, projectType, designImageSource.
 * @returns StyleDetectionResult with style and optional confidence, isDarkTheme.
 */
export async function detectStyleFromDesign(
  options: DetectStyleOptions = {}
): Promise<StyleDetectionResult> {
  const { styleVisionProvider = null, projectType = 'landing', designImageSource } = options;

  if (styleVisionProvider && designImageSource) {
    try {
      const result = await styleVisionProvider(designImageSource, { projectType });
      if (typeof result === 'string') {
        return { style: result };
      }
      return {
        style: result.style,
        confidence: result.confidence,
        isDarkTheme: result.isDarkTheme,
      };
    } catch {
      // fall through to heuristic
    }
  }

  const style = PROJECT_TYPE_TO_STYLE[projectType] ?? 'modern';
  return { style };
}

/**
 * Returns the recommended variant id for a component given a design style.
 * Example: getVariantForStyle('webu_general_hero_01', 'minimal') → 'hero-2'
 *           getVariantForStyle('webu_general_hero_01', 'corporate') → 'hero-1'
 *
 * Only returns variants for components that exist in the registry (Part 13).
 */
export function getVariantForStyle(
  componentKey: string,
  style: DesignStyle,
  alreadyUsed?: Set<string>
): string {
  if (!hasEntry(componentKey)) return '';

  const variants = AVAILABLE_VARIANTS_BY_COMPONENT[componentKey];
  if (!variants?.length) return '';

  const preferred = STYLE_TO_VARIANT[style]?.[componentKey];
  if (preferred && variants.includes(preferred)) {
    if (!alreadyUsed?.has(preferred)) return preferred;
  }

  return variants.find((v) => !alreadyUsed?.has(v)) ?? variants[0] ?? '';
}

/**
 * Applies style-based variant selection to a list of sections.
 * Each section gets the variant recommended for the given style; duplicates are avoided per component.
 */
export function applyStyleToSectionPlan(
  sections: Array<{ componentKey: string; variant?: string }>,
  style: DesignStyle
): Array<{ componentKey: string; variant: string }> {
  const usedByComponent: Record<string, Set<string>> = {};
  return sections.map((section) => {
    const used = usedByComponent[section.componentKey] ?? new Set<string>();
    if (!usedByComponent[section.componentKey]) usedByComponent[section.componentKey] = used;

    const variant = getVariantForStyle(section.componentKey, style, used);
    if (variant) used.add(variant);

    return {
      componentKey: section.componentKey,
      variant: variant || section.variant || '',
    };
  });
}
