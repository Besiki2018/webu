/**
 * Part 2 — UX Heuristic Rules.
 *
 * Rules for UX analysis by page type. Defines which sections every landing page,
 * ecommerce page, etc. should include. If missing → AI suggests adding.
 */

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export type PageType = 'landing' | 'ecommerce' | 'saas' | 'blog' | 'portfolio' | 'generic';

/** Section kind (normalized category matching siteAnalyzer). */
export type SectionKind =
  | 'header'
  | 'hero'
  | 'features'
  | 'social_proof'
  | 'testimonials'
  | 'cta'
  | 'footer'
  | 'product_grid'
  | 'grid'
  | 'filters'
  | 'reviews'
  | 'pricing'
  | 'newsletter'
  | 'navigation'
  | 'cards'
  | 'faq'
  | 'team';

export interface UxRuleSet {
  pageType: PageType;
  /** Human-readable label (e.g. "Landing page"). */
  label: string;
  /** Section kinds that should be present. If missing, AI suggests adding. */
  required: SectionKind[];
  /** Optional: preferred order hint (first should be near top, last near bottom). */
  orderHint?: SectionKind[];
}

export interface UxSuggestion {
  /** Section kind to add. */
  sectionKind: SectionKind;
  /** Human-readable suggestion (e.g. "Add a CTA section"). */
  message: string;
  /** Optional: suggested registry/section type key for adding. */
  suggestedType?: string;
}

export interface UxRuleResult {
  pageType: PageType;
  /** Sections that are missing and should be suggested. */
  missing: UxSuggestion[];
  /** Sections present (for reporting). */
  present: SectionKind[];
}

// ---------------------------------------------------------------------------
// Rule sets by page type
// ---------------------------------------------------------------------------

/**
 * Every landing page should include: hero, features, social proof, cta, footer.
 * (Header is usually implied by layout; we can include it in required.)
 */
export const LANDING_PAGE_RULES: UxRuleSet = {
  pageType: 'landing',
  label: 'Landing page',
  required: ['header', 'hero', 'features', 'social_proof', 'cta', 'footer'],
  orderHint: ['header', 'hero', 'features', 'social_proof', 'cta', 'footer'],
};

/**
 * Ecommerce pages should include: hero, product grid, filters, reviews, cta, footer.
 */
export const ECOMMERCE_PAGE_RULES: UxRuleSet = {
  pageType: 'ecommerce',
  label: 'Ecommerce page',
  required: ['header', 'hero', 'product_grid', 'filters', 'reviews', 'cta', 'footer'],
  orderHint: ['header', 'hero', 'product_grid', 'filters', 'reviews', 'cta', 'footer'],
};

/** SaaS / app landing: hero, features, pricing, testimonials, cta, footer. */
export const SAAS_PAGE_RULES: UxRuleSet = {
  pageType: 'saas',
  label: 'SaaS page',
  required: ['header', 'hero', 'features', 'pricing', 'testimonials', 'cta', 'footer'],
  orderHint: ['header', 'hero', 'features', 'pricing', 'testimonials', 'cta', 'footer'],
};

/** Blog: header, hero/banner, content list, newsletter, footer. */
export const BLOG_PAGE_RULES: UxRuleSet = {
  pageType: 'blog',
  label: 'Blog',
  required: ['header', 'hero', 'footer'],
  orderHint: ['header', 'hero', 'newsletter', 'footer'],
};

/** Portfolio: header, hero, features/cards (work), cta, footer. */
export const PORTFOLIO_PAGE_RULES: UxRuleSet = {
  pageType: 'portfolio',
  label: 'Portfolio',
  required: ['header', 'hero', 'features', 'cta', 'footer'],
  orderHint: ['header', 'hero', 'features', 'cta', 'footer'],
};

/** Generic fallback: header, hero, cta, footer. */
export const GENERIC_PAGE_RULES: UxRuleSet = {
  pageType: 'generic',
  label: 'Page',
  required: ['header', 'hero', 'cta', 'footer'],
  orderHint: ['header', 'hero', 'cta', 'footer'],
};

// ---------------------------------------------------------------------------
// All rule sets and lookup
// ---------------------------------------------------------------------------

export const UX_RULE_SETS: UxRuleSet[] = [
  LANDING_PAGE_RULES,
  ECOMMERCE_PAGE_RULES,
  SAAS_PAGE_RULES,
  BLOG_PAGE_RULES,
  PORTFOLIO_PAGE_RULES,
  GENERIC_PAGE_RULES,
];

const RULES_BY_PAGE_TYPE: Record<PageType, UxRuleSet> = {
  landing: LANDING_PAGE_RULES,
  ecommerce: ECOMMERCE_PAGE_RULES,
  saas: SAAS_PAGE_RULES,
  blog: BLOG_PAGE_RULES,
  portfolio: PORTFOLIO_PAGE_RULES,
  generic: GENERIC_PAGE_RULES,
};

/** Section kind → suggested registry/section type for "add section" actions. */
export const SECTION_KIND_TO_SUGGESTED_TYPE: Record<SectionKind, string> = {
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

/** Human-readable labels for section kinds (for suggestion messages). */
const SECTION_KIND_LABELS: Record<SectionKind, string> = {
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

// ---------------------------------------------------------------------------
// Normalize analyzer kinds to UX rule kinds
// ---------------------------------------------------------------------------

const ANALYZER_KIND_TO_UX: Record<string, SectionKind> = {
  header: 'header',
  navigation: 'header',
  hero: 'hero',
  features: 'features',
  cards: 'social_proof',
  testimonials: 'social_proof',
  cta: 'cta',
  footer: 'footer',
  grid: 'product_grid',
  pricing: 'pricing',
  newsletter: 'newsletter',
  faq: 'faq',
  team: 'team',
  social_proof: 'social_proof',
  product_grid: 'product_grid',
  filters: 'filters',
  reviews: 'reviews',
};

/** Map section kinds from siteAnalyzer (or raw types) to UxRule SectionKind. */
export function normalizeToSectionKinds(analyzerKinds: string[]): SectionKind[] {
  const set = new Set<SectionKind>();
  for (const k of analyzerKinds) {
    const n = (k || '').trim().toLowerCase();
    const mapped = ANALYZER_KIND_TO_UX[n];
    if (mapped) set.add(mapped);
  }
  return Array.from(set);
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Returns the UX rule set for the given page type.
 */
export function getUxRulesForPageType(pageType: PageType): UxRuleSet {
  const normalized = (pageType || 'generic').toLowerCase() as PageType;
  return RULES_BY_PAGE_TYPE[normalized] ?? GENERIC_PAGE_RULES;
}

/**
 * Evaluates UX rules for the current page. Returns missing sections that the AI should suggest adding.
 *
 * @param sectionKinds — Current section kinds (from siteAnalyzer.sectionKinds or normalizeToSectionKinds(analyzerKinds))
 * @param pageType — landing, ecommerce, saas, blog, portfolio, or generic
 * @returns UxRuleResult with missing[] (suggestions) and present[]
 */
export function evaluateUxRules(sectionKinds: SectionKind[] | string[], pageType: PageType = 'generic'): UxRuleResult {
  const normalizedKinds = Array.isArray(sectionKinds) && sectionKinds.length > 0
    ? (typeof sectionKinds[0] === 'string'
        ? normalizeToSectionKinds(sectionKinds as string[])
        : (sectionKinds as SectionKind[]))
    : [];
  const rules = getUxRulesForPageType(pageType);
  const presentSet = new Set(normalizedKinds);
  const present: SectionKind[] = [];
  const missing: UxSuggestion[] = [];

  for (const kind of rules.required) {
    if (presentSet.has(kind)) {
      present.push(kind);
    } else {
      const label = SECTION_KIND_LABELS[kind] ?? kind;
      missing.push({
        sectionKind: kind,
        message: `Add a ${label} section`,
        suggestedType: SECTION_KIND_TO_SUGGESTED_TYPE[kind],
      });
    }
  }

  return {
    pageType: rules.pageType,
    missing,
    present,
  };
}

/**
 * Returns UX suggestions for the current builder state. Use with siteAnalyzer output.
 * If sections are missing → AI suggests adding them.
 *
 * @param analyzerSectionKinds — sectionKinds from analyzeSite(state) (e.g. ['header', 'hero', 'features', 'footer'])
 * @param pageType — landing, ecommerce, saas, etc.
 */
export function getUxSuggestions(
  analyzerSectionKinds: string[],
  pageType: PageType = 'landing'
): UxSuggestion[] {
  const result = evaluateUxRules(analyzerSectionKinds, pageType);
  return result.missing;
}
