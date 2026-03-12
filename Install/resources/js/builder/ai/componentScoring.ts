/**
 * Part 11 — Component Scoring.
 *
 * Assigns a score (1–10) per section kind (hero, features, cta, etc.).
 * AI can focus on the lowest score when suggesting improvements.
 *
 * Example: hero 6/10, features 7/10, cta 3/10 → focus on cta.
 */

import { analyzeLayout } from './layoutAnalyzer';
import { analyzeDesignConsistency } from './designConsistencyAnalyzer';
import { detectSectionGaps } from './sectionGapDetector';
import type { PageType } from './uxRules';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface SectionInput {
  localId: string;
  type: string;
  props?: Record<string, unknown>;
}

export interface ComponentScoringInput {
  sections: SectionInput[];
  pageType?: PageType;
  theme?: { primaryColor?: string; fontFamily?: string };
}

export interface ComponentScore {
  sectionKind: string;
  score: number;
  maxScore: number;
  /** Short reason (e.g. "weak variant", "missing content", "layout issues"). */
  reason?: string;
  /** Section localId when present (missing sections have none). */
  sectionId?: string;
}

export interface ComponentScoringReport {
  /** Scores per section kind (present and missing). */
  scores: ComponentScore[];
  /** Section kind with the lowest score (for AI to focus on). */
  lowestSectionKind: string | null;
  /** Lowest score value (1–10). */
  lowestScore: number;
  /** Human-readable summary (e.g. "hero 6/10, features 7/10, cta 3/10"). */
  summary: string;
}

const MAX_SCORE = 10;

// ---------------------------------------------------------------------------
// Section kind + variant (mirror siteOptimizer)
// ---------------------------------------------------------------------------

function getSectionKind(type: string): string {
  const t = (type || '').trim().toLowerCase();
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
}

function getVariant(props: Record<string, unknown> | undefined): string {
  if (!props) return '';
  const v = props.variant ?? props.variantId;
  return typeof v === 'string' ? v.trim().toLowerCase() : '';
}

/** Variant → score for hero/features/cta. Stronger variant = higher score. */
const VARIANT_SCORE: Record<string, number> = {
  'hero-4': 10,
  hero4: 10,
  'hero-3': 8,
  hero3: 8,
  'hero-2': 6,
  hero2: 6,
  'hero-1': 3,
  hero1: 3,
  'features-3': 10,
  features3: 10,
  'features-2': 7,
  features2: 7,
  'features-1': 4,
  features1: 4,
  'cta-3': 10,
  cta3: 10,
  'cta-2': 6,
  cta2: 6,
  'cta-1': 3,
  cta1: 3,
};

function getVariantScore(sectionKind: string, variant: string): number {
  if (!variant) return 5;
  const v = (variant || '').trim().toLowerCase();
  return VARIANT_SCORE[v] ?? 5;
}

function hasKeyContent(props: Record<string, unknown> | undefined, kind: string): boolean {
  if (!props) return false;
  const title = [props.title, props.headline].some((x) => typeof x === 'string' && x.trim());
  const subtitle = [props.subtitle, props.description].some((x) => typeof x === 'string' && x.trim());
  const cta = [props.buttonText, props.buttonLabel, props.cta].some((x) => typeof x === 'string' && x.trim());
  if (kind === 'hero') return title && (subtitle || cta);
  if (kind === 'features') return title || (Array.isArray(props.items) && (props.items as unknown[]).length > 0);
  if (kind === 'cta') return (title || subtitle) && cta;
  return title || subtitle || cta;
}

// ---------------------------------------------------------------------------
// Score computation
// ---------------------------------------------------------------------------

/**
 * Computes component scores for the current page. Uses layout and design
 * analyzers to deduct points for issues. Missing sections (from gap detector)
 * get score 0. AI should focus on lowestSectionKind.
 */
export function scoreComponents(input: ComponentScoringInput): ComponentScoringReport {
  const sections = input.sections ?? [];
  const pageType = (input.pageType ?? 'landing') as PageType;
  const theme = input.theme;

  const sectionKinds = sections.map((s) => getSectionKind(s.type));
  const layoutReport = analyzeLayout({ sections, sectionKinds });
  const designReport = analyzeDesignConsistency({ sections, theme, sectionKinds });
  const gapReport = detectSectionGaps({ sections, pageType, sectionKinds });

  const issueCountByKind: Record<string, number> = {};
  for (const i of layoutReport.issues) {
    const k = i.sectionKind ?? 'unknown';
    issueCountByKind[k] = (issueCountByKind[k] ?? 0) + 1;
  }
  for (const i of designReport.issues) {
    const k = i.sectionKind ?? 'unknown';
    issueCountByKind[k] = (issueCountByKind[k] ?? 0) + 1;
  }

  const scores: ComponentScore[] = [];
  const seenKinds = new Set<string>();

  for (let i = 0; i < sections.length; i++) {
    const s = sections[i];
    const kind = getSectionKind(s.type);
    if (seenKinds.has(kind)) continue;
    seenKinds.add(kind);

    const variant = getVariant(s.props);
    let score = getVariantScore(kind, variant);
    if (hasKeyContent(s.props, kind) && score < MAX_SCORE) score = Math.min(MAX_SCORE, score + 1);
    const deductions = issueCountByKind[kind] ?? 0;
    score = Math.max(1, score - deductions);

    const reasons: string[] = [];
    if (variant && getVariantScore(kind, variant) <= 4) reasons.push('weak variant');
    if (!hasKeyContent(s.props, kind)) reasons.push('missing content');
    if (deductions > 0) reasons.push('layout/design issues');

    scores.push({
      sectionKind: kind,
      score,
      maxScore: MAX_SCORE,
      reason: reasons.length ? reasons.join('; ') : undefined,
      sectionId: s.localId,
    });
  }

  for (const m of gapReport.missing) {
    const kind = m.sectionKind;
    if (seenKinds.has(kind)) continue;
    seenKinds.add(kind);
    scores.push({
      sectionKind: kind,
      score: 0,
      maxScore: MAX_SCORE,
      reason: 'missing',
      sectionId: undefined,
    });
  }

  let lowestScore = MAX_SCORE + 1;
  let lowestSectionKind: string | null = null;
  for (const sc of scores) {
    if (sc.score < lowestScore) {
      lowestScore = sc.score;
      lowestSectionKind = sc.sectionKind;
    }
  }
  if (lowestSectionKind === null && scores.length > 0) {
    lowestScore = Math.min(...scores.map((s) => s.score));
    const low = scores.find((s) => s.score === lowestScore);
    lowestSectionKind = low?.sectionKind ?? null;
  }

  const summary = scores
    .filter((s) => ['hero', 'features', 'cta', 'testimonials', 'pricing'].includes(s.sectionKind))
    .sort((a, b) => a.sectionKind.localeCompare(b.sectionKind))
    .map((s) => `${s.sectionKind} ${s.score}/${s.maxScore}`)
    .join(', ') || 'No sections scored';

  return {
    scores,
    lowestSectionKind,
    lowestScore: lowestSectionKind !== null ? lowestScore : 0,
    summary,
  };
}
