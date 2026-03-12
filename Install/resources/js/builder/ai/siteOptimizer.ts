/**
 * Part 7 — Auto Optimization Engine.
 *
 * Capabilities: replace weak component variant, add missing sections,
 * improve layout, normalize spacing.
 * Example transformation: hero1 → hero4.
 */

import { suggestComponentImprovements } from './componentImprover';
import { detectSectionGaps } from './sectionGapDetector';
import { analyzeLayout } from './layoutAnalyzer';
import { analyzeDesignConsistency } from './designConsistencyAnalyzer';
import { analyzeSite } from './siteAnalyzer';
import { scoreComponents, type ComponentScoringReport } from './componentScoring';
import type { PageType } from './uxRules';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface OptimizerSectionInput {
  localId: string;
  type: string;
  props?: Record<string, unknown>;
}

export interface SiteOptimizerInput {
  sections: OptimizerSectionInput[];
  pageType?: PageType;
  theme?: { primaryColor?: string; fontFamily?: string };
  /** Optional: skip add missing sections (e.g. when user wants variant/layout only). */
  skipAddSections?: boolean;
}

/** A single optimization step the engine proposes (can be applied to builder state). */
export type OptimizationStep =
  | { type: 'replaceVariant'; sectionId: string; sectionKind: string; from: string; to: string; patch: Record<string, unknown> }
  | { type: 'addSection'; sectionKind: string; suggestedType: string; label?: string; insertAfterSectionId?: string }
  | { type: 'improveLayout'; sectionId?: string; sectionKind?: string; issue: string; suggestedFix: string }
  | { type: 'normalizeSpacing'; issue: string; suggestedFix: string; patch?: Record<string, unknown> }
  | { type: 'normalizeColor'; sectionId?: string; sectionKind?: string; issue: string; suggestedFix: string; patch?: Record<string, unknown> };

export interface SiteOptimizerReport {
  /** Ordered list of transformations to apply (replace variant, add section, improve layout, normalize spacing). */
  transformations: OptimizationStep[];
  /** Summary of what the optimizer suggests. */
  summary?: string;
  /** Part 11 — Component scores (e.g. hero 6/10, cta 3/10). AI focuses on lowest. */
  scoring?: ComponentScoringReport;
}

// ---------------------------------------------------------------------------
// Optimizer variant targets (e.g. hero1 → hero4 for stronger upgrade)
// ---------------------------------------------------------------------------

const OPTIMIZER_HERO_TARGET: Record<string, string> = {
  'hero-1': 'hero-4',
  'hero-2': 'hero-4',
  'hero-3': 'hero-4',
  hero1: 'hero-4',
  hero2: 'hero-4',
  hero3: 'hero-4',
};

const OPTIMIZER_FEATURES_TARGET: Record<string, string> = {
  'features-1': 'features-3',
  'features-2': 'features-3',
  features1: 'features-3',
  features2: 'features-3',
};

const OPTIMIZER_CTA_TARGET: Record<string, string> = {
  'cta-1': 'cta-3',
  'cta-2': 'cta-3',
  cta1: 'cta-3',
  cta2: 'cta-3',
};

function getOptimizerVariantTarget(sectionKind: string, currentVariant: string): string | null {
  const v = (currentVariant || '').trim().toLowerCase();
  const k = (sectionKind || '').trim().toLowerCase();
  if (k === 'hero') return OPTIMIZER_HERO_TARGET[v] ?? null;
  if (k === 'features') return OPTIMIZER_FEATURES_TARGET[v] ?? null;
  if (k === 'cta') return OPTIMIZER_CTA_TARGET[v] ?? null;
  return null;
}

function getVariant(props: Record<string, unknown> | undefined): string {
  if (!props) return '';
  const v = props.variant ?? props.variantId;
  return typeof v === 'string' ? v.trim() : '';
}

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

// ---------------------------------------------------------------------------
// Build transformations
// ---------------------------------------------------------------------------

function buildReplaceVariantSteps(sections: OptimizerSectionInput[]): OptimizationStep[] {
  const steps: OptimizationStep[] = [];
  for (const s of sections) {
    const kind = getSectionKind(s.type);
    const from = getVariant(s.props);
    const to = getOptimizerVariantTarget(kind, from);
    if (to && from && from !== to) {
      steps.push({
        type: 'replaceVariant',
        sectionId: s.localId,
        sectionKind: kind,
        from,
        to,
        patch: { variant: to },
      });
    }
  }
  return steps;
}

function buildAddSectionSteps(
  gapReport: ReturnType<typeof detectSectionGaps>,
  _sections: OptimizerSectionInput[]
): OptimizationStep[] {
  return gapReport.missing.map((m) => ({
    type: 'addSection' as const,
    sectionKind: m.sectionKind,
    suggestedType: m.suggestedType,
    label: m.label,
  }));
}

function buildImproveLayoutSteps(layoutReport: ReturnType<typeof analyzeLayout>): OptimizationStep[] {
  return layoutReport.issues
    .filter((i) => i.sectionId || i.sectionKind)
    .map((i) => ({
      type: 'improveLayout' as const,
      sectionId: i.sectionId,
      sectionKind: i.sectionKind,
      issue: i.issue,
      suggestedFix: i.proposedFix,
    }));
}

function buildNormalizeSpacingSteps(designReport: ReturnType<typeof analyzeDesignConsistency>): OptimizationStep[] {
  const steps: OptimizationStep[] = [];
  for (const i of designReport.issues) {
    if (i.category === 'spacing') {
      steps.push({
        type: 'normalizeSpacing',
        issue: i.issue,
        suggestedFix: i.suggestedFix,
      });
    }
    if (i.category === 'color' && i.suggestedFix.toLowerCase().includes('primary')) {
      steps.push({
        type: 'normalizeColor',
        sectionId: i.sectionId,
        sectionKind: i.sectionKind,
        issue: i.issue,
        suggestedFix: i.suggestedFix,
        patch: i.sectionKind === 'cta' ? { backgroundColor: 'var(--color-primary, #2563eb)' } : undefined,
      });
    }
  }
  return steps;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Runs the auto optimization engine: replace weak component variant (e.g. hero1 → hero4),
 * add missing sections, improve layout, normalize spacing. Returns an ordered list of
 * transformations that can be applied to the builder state.
 *
 * @param input — sections, pageType (default landing), optional theme, optional skipAddSections
 * @returns SiteOptimizerReport with transformations[]
 */
export function optimizeSite(input: SiteOptimizerInput): SiteOptimizerReport {
  const sections = input.sections ?? [];
  const pageType = (input.pageType ?? 'landing') as PageType;
  const theme = input.theme;

  const siteReport = analyzeSite({ sections, projectType: pageType });
  const sectionKinds = siteReport.sectionKinds ?? sections.map((s) => getSectionKind(s.type));

  const transformations: OptimizationStep[] = [];

  // 1. Replace weak component variant (e.g. hero1 → hero4)
  transformations.push(...buildReplaceVariantSteps(sections));

  // 2. Add missing sections
  if (!input.skipAddSections) {
    const gapReport = detectSectionGaps({ sections, pageType, sectionKinds });
    transformations.push(...buildAddSectionSteps(gapReport, sections));
  }

  // 3. Improve layout (from layout analyzer)
  const layoutReport = analyzeLayout({ sections, sectionKinds });
  transformations.push(...buildImproveLayoutSteps(layoutReport));

  // 4. Normalize spacing (and color) from design consistency
  const designReport = analyzeDesignConsistency({ sections, theme, sectionKinds });
  transformations.push(...buildNormalizeSpacingSteps(designReport));

  const scoring = scoreComponents({ sections, pageType, theme });
  const lowestKind = scoring.lowestSectionKind;

  if (lowestKind && transformations.length > 1) {
    transformations.sort((a, b) => {
      const aAffects = stepAffectsSectionKind(a, lowestKind);
      const bAffects = stepAffectsSectionKind(b, lowestKind);
      if (aAffects && !bAffects) return -1;
      if (!aAffects && bAffects) return 1;
      return 0;
    });
  }

  const summary =
    transformations.length === 0
      ? 'No optimizations suggested'
      : `${transformations.length} optimization${transformations.length === 1 ? '' : 's'} (replace variant, add sections, improve layout, normalize spacing)`;

  return { transformations, summary, scoring };
}

function stepAffectsSectionKind(step: OptimizationStep, sectionKind: string): boolean {
  const k = (sectionKind || '').trim().toLowerCase();
  switch (step.type) {
    case 'replaceVariant':
      return (step.sectionKind || '').trim().toLowerCase() === k;
    case 'addSection':
      return (step.sectionKind || '').trim().toLowerCase() === k;
    case 'improveLayout':
    case 'normalizeColor':
      return (step.sectionKind || '').trim().toLowerCase() === k;
    case 'normalizeSpacing':
      return false;
    default:
      return false;
  }
}
