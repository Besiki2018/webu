/**
 * Part 1 — Site Analyzer (AI Component Intelligence Engine).
 *
 * Analyzes current builder state (page structure) and produces an analysis report:
 * missing sections, weak layout hierarchy, imbalanced spacing, missing CTAs,
 * design inconsistencies.
 *
 * Input: current builder state (sections list).
 * Output: analysisReport with issues[].
 */

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

/** Minimal section shape for analysis (avoids coupling to full BuilderSection). */
export interface SectionInput {
  localId: string;
  type: string;
  props?: Record<string, unknown>;
}

/** Current builder state input. */
export interface BuilderStateInput {
  /** Top-level page sections in order. */
  sections: SectionInput[];
  /** Optional: project type (landing, saas, ecommerce) for context. */
  projectType?: string;
}

/** Analysis report produced by the site analyzer. */
export interface AnalysisReport {
  /** Human-readable issues and improvement suggestions. */
  issues: string[];
  /** Optional: section kinds detected (header, hero, features, cta, footer, etc.). */
  sectionKinds?: string[];
  /** Optional: summary stats for consumers. */
  stats?: {
    totalSections: number;
    hasHeader: boolean;
    hasHero: boolean;
    hasCta: boolean;
    hasFooter: boolean;
  };
}

// ---------------------------------------------------------------------------
// Section type → kind (normalize registry ID or key to category)
// ---------------------------------------------------------------------------

const TYPE_TO_KIND: Record<string, string> = {
  webu_header_01: 'header',
  webu_footer_01: 'footer',
  webu_general_hero_01: 'hero',
  webu_general_features_01: 'features',
  webu_general_cta_01: 'cta',
  webu_general_navigation_01: 'navigation',
  webu_general_cards_01: 'cards',
  webu_general_grid_01: 'grid',
  webu_general_pricing_table_01: 'pricing',
  webu_general_newsletter_01: 'newsletter',
  header: 'header',
  footer: 'footer',
  hero: 'hero',
  features: 'features',
  cta: 'cta',
  navigation: 'navigation',
  cards: 'cards',
  grid: 'grid',
  pricing: 'pricing',
  newsletter: 'newsletter',
  testimonials: 'testimonials',
  faq: 'faq',
  team: 'team',
};

function getSectionKind(type: string): string {
  const t = (type || '').trim().toLowerCase();
  if (TYPE_TO_KIND[t]) return TYPE_TO_KIND[t];
  if (t.includes('header') || t.includes('nav')) return 'header';
  if (t.includes('footer')) return 'footer';
  if (t.includes('hero') || t.includes('banner')) return 'hero';
  if (t.includes('feature')) return 'features';
  if (t.includes('cta') || t.includes('call_to_action') || t.includes('calltoaction')) return 'cta';
  if (t.includes('pricing') || t.includes('plan')) return 'pricing';
  if (t.includes('testimonial') || t.includes('review')) return 'testimonials';
  if (t.includes('faq')) return 'faq';
  if (t.includes('team')) return 'team';
  if (t.includes('card')) return 'cards';
  if (t.includes('grid')) return 'grid';
  if (t.includes('newsletter') || t.includes('subscribe')) return 'newsletter';
  return t || 'unknown';
}

// ---------------------------------------------------------------------------
// Detection rules
// ---------------------------------------------------------------------------

/** Recommended minimum sections for a landing page. */
const RECOMMENDED_KINDS = ['header', 'hero', 'footer'] as const;
/** CTA is recommended for conversion. */
const CTA_KIND = 'cta';
/** Ideal order hint (hero near top, footer at end). */
const HIERARCHY_ORDER = ['header', 'navigation', 'hero', 'features', 'pricing', 'testimonials', 'cards', 'grid', 'cta', 'newsletter', 'footer'];

function analyzeMissingSections(kinds: string[]): string[] {
  const issues: string[] = [];
  const set = new Set(kinds);
  if (!set.has('header')) issues.push('missing header');
  if (!set.has('hero')) issues.push('missing hero section');
  if (!set.has('footer')) issues.push('missing footer');
  if (!set.has(CTA_KIND)) issues.push('missing CTA section');
  return issues;
}

function analyzeLayoutHierarchy(kinds: string[]): string[] {
  const issues: string[] = [];
  if (kinds.length === 0) {
    issues.push('page has no sections');
    return issues;
  }
  const first = kinds[0];
  const last = kinds[kinds.length - 1];
  if (first !== 'header' && first !== 'navigation' && first !== 'hero') {
    issues.push('hero or header should be near the top for clear hierarchy');
  }
  if (last !== 'footer' && last !== 'cta' && last !== 'newsletter') {
    issues.push('footer or closing CTA should be at the end of the page');
  }
  if (kinds.length === 1) {
    issues.push('single section creates weak layout hierarchy');
  }
  if (kinds.length === 2 && !kinds.includes('hero')) {
    issues.push('consider adding a hero section for stronger visual hierarchy');
  }
  return issues;
}

function analyzeSpacingAndBalance(kinds: string[]): string[] {
  const issues: string[] = [];
  if (kinds.length > 12) {
    issues.push('many sections may create imbalanced spacing; consider grouping or shortening');
  }
  const counts: Record<string, number> = {};
  for (const k of kinds) {
    counts[k] = (counts[k] ?? 0) + 1;
  }
  if ((counts['features'] ?? 0) > 3) {
    issues.push('multiple feature blocks in a row can feel imbalanced; consider one features section with more items');
  }
  if ((counts['hero'] ?? 0) > 1) {
    issues.push('multiple hero sections can weaken layout hierarchy');
  }
  if ((counts['footer'] ?? 0) > 1) {
    issues.push('duplicate footer sections create design inconsistency');
  }
  return issues;
}

function analyzeCta(kinds: string[]): string[] {
  const issues: string[] = [];
  if (!kinds.includes(CTA_KIND) && !kinds.includes('newsletter')) {
    issues.push('missing CTAs: add a CTA or newsletter section for conversion');
  }
  const ctaIndex = kinds.indexOf(CTA_KIND);
  const newsletterIndex = kinds.indexOf('newsletter');
  if (kinds.length >= 4 && ctaIndex === -1 && newsletterIndex === -1) {
    issues.push('missing CTAs');
  }
  return issues;
}

function analyzeDesignConsistency(kinds: string[], sections: SectionInput[]): string[] {
  const issues: string[] = [];
  const counts: Record<string, number> = {};
  for (const k of kinds) {
    counts[k] = (counts[k] ?? 0) + 1;
  }
  if ((counts['header'] ?? 0) > 1) issues.push('design inconsistency: multiple headers');
  if ((counts['footer'] ?? 0) > 1) issues.push('footer incomplete or duplicated');
  if ((counts['footer'] ?? 0) === 1) {
    const footerSection = sections[kinds.lastIndexOf('footer')];
    if (footerSection?.props && Object.keys(footerSection.props).length < 2) {
      issues.push('footer incomplete');
    }
  }
  const heroIndex = kinds.indexOf('hero');
  if (heroIndex >= 0) {
    const heroSection = sections[heroIndex];
    const heroKeys = heroSection?.props ? Object.keys(heroSection.props) : [];
    if (heroKeys.length < 2) issues.push('hero layout too simple');
  }
  const emptyOrMinimal = sections.filter((s) => {
    const keys = s.props ? Object.keys(s.props) : [];
    return keys.length < 2;
  });
  if (emptyOrMinimal.length > 0 && sections.length > 0) {
    const pct = Math.round((emptyOrMinimal.length / sections.length) * 100);
    if (pct >= 50) {
      issues.push('design inconsistencies: many sections have minimal or no content');
    }
  }
  return issues;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Analyzes the current builder state and returns an analysis report with issues.
 *
 * Detects:
 * - missing sections (header, hero, footer, CTA)
 * - weak layout hierarchy (hero not near top, footer not at end, too few sections)
 * - imbalanced spacing (too many sections, repeated blocks)
 * - missing CTAs
 * - design inconsistencies (duplicate headers/footers, minimal content)
 *
 * @param state — Current builder state (sections in order)
 * @returns analysisReport with issues array and optional stats
 */
export function analyzeSite(state: BuilderStateInput): AnalysisReport {
  const sections = state.sections ?? [];
  const kinds = sections.map((s) => getSectionKind(s.type));
  const issues: string[] = [];

  issues.push(...analyzeMissingSections(kinds));
  issues.push(...analyzeLayoutHierarchy(kinds));
  issues.push(...analyzeSpacingAndBalance(kinds));
  issues.push(...analyzeCta(kinds));
  issues.push(...analyzeDesignConsistency(kinds, sections));

  const uniqueIssues = Array.from(new Set(issues));
  const set = new Set(kinds);
  const stats = {
    totalSections: sections.length,
    hasHeader: set.has('header') || set.has('navigation'),
    hasHero: set.has('hero'),
    hasCta: set.has('cta') || set.has('newsletter'),
    hasFooter: set.has('footer'),
  };

  return {
    issues: uniqueIssues,
    sectionKinds: kinds,
    stats,
  };
}
