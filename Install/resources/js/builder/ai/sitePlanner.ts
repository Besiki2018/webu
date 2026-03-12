/**
 * Section Planning Engine — prompt analysis → site structure.
 *
 * Part 3: Converts PromptAnalysisResult into an ordered site structure (sections with component + variant)
 * compatible with the builder data model. Output can be passed to buildTreeFromStructure().
 */

import type { PromptAnalysisResult, SectionSlug } from './promptAnalyzer';
import type { SiteStructureSection } from '../aiSiteGeneration';
import {
  hasEntry,
  DEFAULT_HERO_REGISTRY_ID,
  DEFAULT_FEATURES_REGISTRY_ID,
  DEFAULT_FOOTER_REGISTRY_ID,
} from '../registry/componentRegistry';

// ---------------------------------------------------------------------------
// Output type (builder-compatible)
// ---------------------------------------------------------------------------

/** Same as SiteStructureSection: componentKey is registry ID (e.g. webu_header_01). */
export type PlannedSection = SiteStructureSection;

export interface SitePlanResult {
  /** Ordered sections for the builder; compatible with buildTreeFromStructure(). */
  sections: PlannedSection[];
}

/** Human-readable section entry (e.g. for display). Component is short name; variant is schema variant id. */
export interface SitePlanDisplaySection {
  component: string;
  variant: string;
}

// ---------------------------------------------------------------------------
// Slug → registry component key (builder uses registry IDs)
// ---------------------------------------------------------------------------

const SLUG_TO_COMPONENT_KEY: Record<SectionSlug, string> = {
  header: 'webu_header_01',
  hero: 'webu_general_hero_01',
  features: 'webu_general_features_01',
  productGrid: 'webu_general_grid_01',
  pricing: 'webu_general_features_01',
  testimonials: 'webu_general_cards_01',
  cta: 'webu_general_cta_01',
  footer: 'webu_footer_01',
  navigation: 'webu_general_navigation_01',
  cards: 'webu_general_cards_01',
  grid: 'webu_general_grid_01',
  faq: 'webu_general_cards_01',
  contact: 'webu_general_cta_01',
  blog: 'webu_general_cards_01',
  menu: 'webu_general_cards_01',
  booking: 'webu_general_cta_01',
  gallery: 'webu_general_grid_01',
};

/** Default variant per registry component (schema variant ids). */
const DEFAULT_VARIANTS: Record<string, string> = {
  webu_header_01: 'header-1',
  webu_footer_01: 'footer-1',
  webu_general_hero_01: 'hero-1',
  webu_general_features_01: 'features-1',
  webu_general_cta_01: 'cta-1',
  webu_general_navigation_01: 'navigation-1',
  webu_general_cards_01: 'cards-1',
  webu_general_grid_01: 'grid-1',
};

/** Optional variant override by tone (e.g. modern → hero-2). */
const TONE_VARIANT_OVERRIDES: Record<string, Record<string, string>> = {
  modern: {
    webu_general_hero_01: 'hero-1',
    webu_general_features_01: 'features-2',
  },
  minimal: {
    webu_general_hero_01: 'hero-1',
    webu_general_cta_01: 'cta-1',
  },
  bold: {
    webu_general_hero_01: 'hero-1',
  },
};

/** Section slugs we can resolve to a registry component. Unmapped slugs are skipped. */
const RESOLVABLE_SLUGS = new Set<SectionSlug>(Object.keys(SLUG_TO_COMPONENT_KEY) as SectionSlug[]);

/** Part 13 — When planned component is not in registry, fallback to default hero / features / footer. */
const SLUG_TO_FALLBACK_KEY: Partial<Record<SectionSlug, string>> = {
  hero: DEFAULT_HERO_REGISTRY_ID,
  features: DEFAULT_FEATURES_REGISTRY_ID,
  pricing: DEFAULT_FEATURES_REGISTRY_ID,
  productGrid: DEFAULT_FEATURES_REGISTRY_ID,
  testimonials: DEFAULT_FEATURES_REGISTRY_ID,
  cards: DEFAULT_FEATURES_REGISTRY_ID,
  grid: DEFAULT_FEATURES_REGISTRY_ID,
  faq: DEFAULT_FEATURES_REGISTRY_ID,
  contact: DEFAULT_FEATURES_REGISTRY_ID,
  blog: DEFAULT_FEATURES_REGISTRY_ID,
  menu: DEFAULT_FEATURES_REGISTRY_ID,
  booking: DEFAULT_FEATURES_REGISTRY_ID,
  cta: DEFAULT_FEATURES_REGISTRY_ID,
  gallery: DEFAULT_FEATURES_REGISTRY_ID,
  footer: DEFAULT_FOOTER_REGISTRY_ID,
};

/** Canonical order for sections (header first, footer last). */
const SECTION_ORDER: SectionSlug[] = [
  'navigation',
  'header',
  'hero',
  'productGrid',
  'grid',
  'gallery',
  'features',
  'pricing',
  'cards',
  'testimonials',
  'faq',
  'blog',
  'menu',
  'contact',
  'booking',
  'cta',
  'footer',
];

function orderSlugs(slugs: SectionSlug[]): SectionSlug[] {
  const seen = new Set<SectionSlug>();
  const result: SectionSlug[] = [];
  for (const s of SECTION_ORDER) {
    if (slugs.includes(s) && !seen.has(s)) {
      result.push(s);
      seen.add(s);
    }
  }
  for (const s of slugs) {
    if (!seen.has(s)) result.push(s);
  }
  return result;
}

/** Resolve slug to registry componentKey; use fallback (default hero/features/footer) if primary key not in registry. */
function resolveComponentKey(slug: SectionSlug): string | null {
  const key = SLUG_TO_COMPONENT_KEY[slug];
  if (key && hasEntry(key)) return key;
  const fallback = SLUG_TO_FALLBACK_KEY[slug];
  if (fallback && hasEntry(fallback)) return fallback;
  return null;
}

/** Get variant for a componentKey; optionally apply tone override. */
function getVariant(componentKey: string, tone: string | null): string {
  if (tone && TONE_VARIANT_OVERRIDES[tone]?.[componentKey]) {
    return TONE_VARIANT_OVERRIDES[tone][componentKey];
  }
  return DEFAULT_VARIANTS[componentKey] ?? '';
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Plans the site structure from prompt analysis.
 * Output sections are ordered (HEADER → HERO → … → FOOTER) and compatible with the builder:
 * componentKey = registry ID, variant = schema variant id.
 */
export function planSite(analysis: PromptAnalysisResult): SitePlanResult {
  const ordered = orderSlugs(analysis.requiredSections);
  const sections: PlannedSection[] = [];

  for (const slug of ordered) {
    if (!RESOLVABLE_SLUGS.has(slug)) continue;
    const componentKey = resolveComponentKey(slug);
    if (!componentKey) continue;
    const variant = getVariant(componentKey, analysis.tone);
    sections.push({
      componentKey,
      ...(variant && { variant }),
    });
  }

  if (sections.length === 0) {
    sections.push(
      { componentKey: 'webu_header_01', variant: 'header-1' },
      { componentKey: DEFAULT_HERO_REGISTRY_ID, variant: 'hero-1' },
      { componentKey: DEFAULT_FEATURES_REGISTRY_ID, variant: 'features-1' },
      { componentKey: 'webu_general_cta_01', variant: 'cta-1' },
      { componentKey: DEFAULT_FOOTER_REGISTRY_ID, variant: 'footer-1' }
    );
  }

  return { sections };
}

/**
 * Converts a site plan to the display format (component short name + variant).
 * Useful for UI or logging. Component names are derived from registry key (e.g. webu_header_01 → header).
 */
export function planToDisplayFormat(result: SitePlanResult): SitePlanDisplaySection[] {
  return result.sections.map((s) => {
    const short = s.componentKey
      .replace(/^webu_/, '')
      .replace(/_01$/, '')
      .replace(/_/g, '')
      .replace(/general/, '');
    const component = short || s.componentKey;
    const variant = s.variant ?? DEFAULT_VARIANTS[s.componentKey] ?? '';
    return { component, variant };
  });
}
