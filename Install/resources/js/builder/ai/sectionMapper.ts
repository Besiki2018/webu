/**
 * Section Structure Builder — map detected layout blocks to existing Webu components.
 *
 * Part 3 (Design-to-Builder): Maps layout detector output to registry component keys.
 * Rules: grid layouts → features/grid/cards; image+text → hero/features; button areas → CTA;
 * heading sections → features/cards. Output is a section plan compatible with siteBuilder/planner.
 */

import type { SectionSlug } from './promptAnalyzer';
import type { DetectedLayoutBlock, LayoutPosition } from './layoutDetector';
import type { SiteStructureSection } from '../aiSiteGeneration';
import {
  hasEntry,
  DEFAULT_GENERIC_SECTION_REGISTRY_ID,
} from '../componentRegistry';

// ---------------------------------------------------------------------------
// Input: block with optional layout hints (from vision or heuristic)
// ---------------------------------------------------------------------------

/** Layout hints for a detected block (grid columns, image+text, button, heading). */
export interface BlockLayoutHint {
  /** Number of columns in a grid layout (2, 3, 4). */
  columnCount?: number;
  /** Block has prominent image + text (hero or feature row). */
  hasImageAndText?: boolean;
  /** Block has primary button / CTA area. */
  hasButton?: boolean;
  /** Block is primarily a heading/title section. */
  isHeadingSection?: boolean;
  /** Logo grid / partners / trust badges. */
  isLogoGrid?: boolean;
}

/** Block that can carry optional layout hints for variant/component choice. */
export type MappableBlock = DetectedLayoutBlock & BlockLayoutHint;

export interface SectionMapperInput {
  /** Detected blocks (from layout detector). */
  blocks: MappableBlock[];
  /** Project type (influences variant and fallbacks). */
  projectType?: string;
  /** Preferred style (e.g. modern, minimal) for variant selection. */
  preferredStyle?: string;
}

// ---------------------------------------------------------------------------
// Output: section plan (same shape as site planner)
// ---------------------------------------------------------------------------

export interface SectionPlanResult {
  sections: SiteStructureSection[];
}

// ---------------------------------------------------------------------------
// Slug → registry component key (must match registry; Part 13 safety)
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

/** Logo grid / partners have no dedicated component → use cards. */
const PARTNERS_OR_LOGO_GRID_COMPONENT_KEY = 'webu_general_cards_01';

/** Default variant per registry component. */
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

/** Variant by preferred style (modern → more visual variants). */
const STYLE_VARIANT_OVERRIDES: Record<string, Record<string, string>> = {
  modern: {
    webu_general_hero_01: 'hero-2',
    webu_general_features_01: 'features-2',
    webu_general_cta_01: 'cta-2',
  },
  minimal: {
    webu_general_hero_01: 'hero-1',
    webu_general_features_01: 'features-1',
  },
  bold: {
    webu_general_hero_01: 'hero-3',
    webu_general_features_01: 'features-2',
  },
};

// ---------------------------------------------------------------------------
// Mapping rules: block type + hints → component key + variant
// ---------------------------------------------------------------------------

/**
 * Resolve component key for a block.
 * - Uses block.type (SectionSlug) → SLUG_TO_COMPONENT_KEY.
 * - Logo grid / partners → cards.
 * - Multi-column (columnCount >= 2) for middle content → features when type is cards/grid.
 * - Part 11: If block cannot be mapped to a component (key missing or not in registry), fall back to generic section (GenericSection).
 */
function resolveComponentKey(block: MappableBlock, index: number, total: number): string {
  let key: string | undefined;

  if (block.isLogoGrid) {
    key = PARTNERS_OR_LOGO_GRID_COMPONENT_KEY;
  } else if (block.columnCount != null && block.columnCount >= 2 && block.position === 'middle') {
    if (block.type === 'cards' || block.type === 'grid' || block.type === 'testimonials') {
      key = block.type === 'grid' ? 'webu_general_grid_01' : 'webu_general_cards_01';
    } else {
      key = SLUG_TO_COMPONENT_KEY.features;
    }
  } else if (block.hasButton && block.position === 'bottom') {
    key = SLUG_TO_COMPONENT_KEY.cta;
  } else if (block.isHeadingSection && block.position === 'top-section') {
    key = SLUG_TO_COMPONENT_KEY.hero;
  } else if (block.hasImageAndText && block.position === 'top-section') {
    key = SLUG_TO_COMPONENT_KEY.hero;
  } else if (block.hasImageAndText && block.position === 'middle') {
    key = SLUG_TO_COMPONENT_KEY.features;
  } else {
    key = SLUG_TO_COMPONENT_KEY[block.type];
  }

  if (key && hasEntry(key)) return key;
  return DEFAULT_GENERIC_SECTION_REGISTRY_ID;
}

function getVariant(componentKey: string, preferredStyle: string | undefined): string {
  const style = (preferredStyle ?? '').toLowerCase().trim();
  const overrides = style ? STYLE_VARIANT_OVERRIDES[style] : undefined;
  if (overrides?.[componentKey]) return overrides[componentKey];
  return DEFAULT_VARIANTS[componentKey] ?? '';
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Maps detected layout blocks to a section plan (registry componentKey + variant per section).
 * Uses type + optional layout hints (columnCount, hasImageAndText, hasButton, isHeadingSection, isLogoGrid)
 * to choose component and variant. Only emits sections that exist in the registry (Part 13).
 */
export function mapBlocksToSectionPlan(input: SectionMapperInput): SectionPlanResult {
  const { blocks, preferredStyle } = input;
  const total = blocks.length;
  const sections: SiteStructureSection[] = [];

  for (let i = 0; i < blocks.length; i++) {
    const block = blocks[i]!;
    const componentKey = resolveComponentKey(block, i, total);
    const variant = getVariant(componentKey, preferredStyle);
    sections.push({
      componentKey,
      ...(variant && { variant }),
    });
  }

  return { sections };
}

/**
 * Convenience: build section plan from layout detection result (no extra hints).
 */
export function sectionPlanFromLayoutResult(
  layoutResult: { blocks: DetectedLayoutBlock[] },
  options: { projectType?: string; preferredStyle?: string } = {}
): SectionPlanResult {
  return mapBlocksToSectionPlan({
    blocks: layoutResult.blocks,
    ...options,
  });
}
