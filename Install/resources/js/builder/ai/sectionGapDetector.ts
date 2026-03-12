/**
 * Part 5 — Missing Section Detection.
 *
 * Detects missing blocks/sections by page type. Example: landing page missing testimonials,
 * landing page missing CTA. AI suggests: add testimonials section, add CTA section.
 */

import {
  getUxRulesForPageType,
  evaluateUxRules,
  normalizeToSectionKinds,
  type PageType,
} from './uxRules';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface SectionInput {
  localId: string;
  type: string;
  props?: Record<string, unknown>;
}

export interface SectionGapDetectorInput {
  /** Current page sections. */
  sections: SectionInput[];
  /** Page type (landing, ecommerce, saas, etc.). */
  pageType?: PageType;
  /** Optional: precomputed section kinds from siteAnalyzer. */
  sectionKinds?: string[];
}

/** A detected gap: a missing section the AI can suggest adding. */
export interface MissingSectionGap {
  /** Section kind (testimonials, cta, hero, etc.). */
  sectionKind: string;
  /** Message for the AI to use (e.g. "add testimonials section"). */
  message: string;
  /** Suggested registry/section type when adding (e.g. webu_general_cards_01). */
  suggestedType: string;
  /** Optional display label (e.g. "Testimonials"). */
  label?: string;
}

export interface SectionGapReport {
  /** Missing sections; AI suggests adding these. */
  missing: MissingSectionGap[];
  /** Section kinds currently on the page. */
  present: string[];
  /** Page type used for rules. */
  pageType: PageType;
  /** Short summary. */
  summary?: string;
}

// ---------------------------------------------------------------------------
// Section kind → add message and suggested type
// ---------------------------------------------------------------------------

const GAP_LABELS: Record<string, string> = {
  header: 'header',
  hero: 'hero',
  features: 'features',
  social_proof: 'social proof',
  testimonials: 'testimonials',
  cta: 'CTA',
  footer: 'footer',
  product_grid: 'product grid',
  grid: 'product grid',
  filters: 'filters',
  reviews: 'reviews',
  pricing: 'pricing',
  newsletter: 'newsletter',
  navigation: 'navigation',
  cards: 'cards',
  faq: 'FAQ',
  team: 'team',
};

const GAP_SUGGESTED_TYPES: Record<string, string> = {
  header: 'webu_header_01',
  hero: 'webu_general_hero_01',
  features: 'webu_general_features_01',
  social_proof: 'webu_general_cards_01',
  testimonials: 'webu_general_cards_01',
  cta: 'webu_general_cta_01',
  footer: 'webu_footer_01',
  product_grid: 'webu_general_grid_01',
  grid: 'webu_general_grid_01',
  filters: 'webu_general_features_01',
  reviews: 'webu_general_cards_01',
  pricing: 'webu_general_pricing_table_01',
  newsletter: 'webu_general_newsletter_01',
  navigation: 'webu_general_navigation_01',
  cards: 'webu_general_cards_01',
  faq: 'webu_general_cards_01',
  team: 'webu_general_features_01',
};

function toAddMessage(sectionKind: string): string {
  const label = GAP_LABELS[sectionKind] ?? sectionKind;
  return `add ${label} section`;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Detects missing sections (gaps) for the current page. Returns a list of missing blocks
 * so the AI can suggest adding them (e.g. "add testimonials section", "add CTA section").
 *
 * @param input — sections, pageType (default landing), optional sectionKinds
 * @returns SectionGapReport with missing[] (each has message and suggestedType)
 */
export function detectSectionGaps(input: SectionGapDetectorInput): SectionGapReport {
  const pageType = (input.pageType ?? 'landing') as PageType;
  const sectionKinds = input.sectionKinds ?? normalizeToSectionKinds(
    (input.sections ?? []).map((s) => {
      const t = (s.type || '').trim().toLowerCase();
      if (t.includes('header') || t.includes('nav')) return 'header';
      if (t.includes('footer')) return 'footer';
      if (t.includes('hero')) return 'hero';
      if (t.includes('feature')) return 'features';
      if (t.includes('cta')) return 'cta';
      if (t.includes('pricing')) return 'pricing';
      if (t.includes('testimonial') || t.includes('review')) return 'testimonials';
      if (t.includes('card')) return 'cards';
      if (t.includes('grid')) return 'grid';
      if (t.includes('newsletter')) return 'newsletter';
      return t || 'unknown';
    })
  );

  const result = evaluateUxRules(sectionKinds, pageType);
  const missing: MissingSectionGap[] = result.missing.map((m) => ({
    sectionKind: m.sectionKind,
    message: toAddMessage(m.sectionKind),
    suggestedType: m.suggestedType ?? GAP_SUGGESTED_TYPES[m.sectionKind] ?? 'webu_general_features_01',
    label: GAP_LABELS[m.sectionKind],
  }));

  const summary =
    missing.length === 0
      ? 'No missing sections detected'
      : `Landing page missing ${missing.map((m) => m.label ?? m.sectionKind).join(', ')}; AI suggests: ${missing.map((m) => m.message).join('; ')}`;

  return {
    missing,
    present: result.present,
    pageType: result.pageType,
    summary,
  };
}
