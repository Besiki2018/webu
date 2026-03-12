/**
 * Part 9 — Chat AI Improvements.
 *
 * Allows chat commands like "Improve this page", "Optimize layout", "Make design modern",
 * "Improve hero section". When matched, the frontend runs the analyzer + optimizer and
 * applies the suggested transformations.
 */

import { optimizeSite, type OptimizationStep, type SiteOptimizerReport } from './siteOptimizer';

// ---------------------------------------------------------------------------
// Command detection
// ---------------------------------------------------------------------------

const IMPROVEMENT_PHRASES = [
  'improve this page',
  'improve the page',
  'optimize layout',
  'optimize the layout',
  'make design modern',
  'modernize design',
  'improve hero section',
  'improve the hero section',
  'improve hero',
  'upgrade layout',
  'optimize this page',
  'optimize the page',
  'improve layout',
  'improve the layout',
];

/** Part 10 — Design upgrade: "Improve hero" runs replace variant + regenerate content + spacing. */
const DESIGN_UPGRADE_PHRASES: { phrase: string; sectionKind: string }[] = [
  { phrase: 'improve hero', sectionKind: 'hero' },
  { phrase: 'improve the hero', sectionKind: 'hero' },
  { phrase: 'improve hero section', sectionKind: 'hero' },
  { phrase: 'improve the hero section', sectionKind: 'hero' },
  { phrase: 'upgrade hero', sectionKind: 'hero' },
  { phrase: 'regenerate hero', sectionKind: 'hero' },
  { phrase: 'improve features', sectionKind: 'features' },
  { phrase: 'improve the features', sectionKind: 'features' },
  { phrase: 'improve cta', sectionKind: 'cta' },
  { phrase: 'improve the cta', sectionKind: 'cta' },
];

function normalizeForMatch(text: string): string {
  return text.toLowerCase().trim().replace(/\s+/g, ' ');
}

/**
 * Returns true if the user message is an improvement/optimize command that should
 * trigger the analyzer + optimizer locally.
 */
export function isImprovementCommand(text: string): boolean {
  const norm = normalizeForMatch(text);
  if (norm.length < 3) return false;
  return IMPROVEMENT_PHRASES.some((phrase) => norm.includes(phrase) || norm === phrase);
}

/**
 * Part 10 — If the message is a design-upgrade command (e.g. "Improve hero"),
 * returns { sectionKind: 'hero' | 'features' | 'cta' }. Otherwise null.
 */
export function getDesignUpgradeSectionKind(text: string): 'hero' | 'features' | 'cta' | null {
  const norm = normalizeForMatch(text);
  if (norm.length < 3) return null;
  for (const { phrase, sectionKind } of DESIGN_UPGRADE_PHRASES) {
    if (norm.includes(phrase) || norm === phrase) {
      return sectionKind as 'hero' | 'features' | 'cta';
    }
  }
  return null;
}

// ---------------------------------------------------------------------------
// Run optimizer
// ---------------------------------------------------------------------------

export interface ChatSectionInput {
  localId: string;
  type: string;
  props?: Record<string, unknown>;
}

/**
 * Runs the site analyzer + optimizer on the given sections and returns the report.
 */
export function runChatImprovement(
  sections: ChatSectionInput[],
  options?: { pageType?: 'landing' | 'ecommerce' | 'saas'; theme?: { primaryColor?: string; fontFamily?: string } }
): SiteOptimizerReport {
  return optimizeSite({
    sections: sections.map((s) => ({
      localId: s.localId,
      type: s.type,
      props: s.props,
    })),
    pageType: options?.pageType ?? 'landing',
    theme: options?.theme,
  });
}

// ---------------------------------------------------------------------------
// Apply transformations to section list (Chat builder code sections)
// ---------------------------------------------------------------------------

export interface SectionWithProps {
  localId: string;
  type: string;
  props: Record<string, unknown>;
  propsText: string;
}

function nextLocalId(): string {
  return `chat-improve-${Date.now()}-${Math.random().toString(36).slice(2, 9)}`;
}

/**
 * Applies optimization steps to a list of sections. Returns a new array;
 * does not mutate the input.
 */
export function applyOptimizationStepsToSections(
  sections: SectionWithProps[],
  steps: OptimizationStep[]
): SectionWithProps[] {
  let result = sections.map((s) => ({
    localId: s.localId,
    type: s.type,
    props: { ...s.props },
    propsText: s.propsText,
  }));

  for (const step of steps) {
    if (step.type === 'replaceVariant' && step.sectionId && step.patch) {
      const idx = result.findIndex((s) => s.localId === step.sectionId);
      if (idx !== -1) {
        const nextProps = { ...result[idx].props, ...step.patch };
        result = [...result];
        result[idx] = {
          ...result[idx],
          props: nextProps,
          propsText: JSON.stringify(nextProps, null, 2),
        };
      }
    } else if (step.type === 'addSection') {
      const newSection: SectionWithProps = {
        localId: nextLocalId(),
        type: step.suggestedType,
        props: {},
        propsText: '{}',
      };
      if (step.insertAfterSectionId) {
        const insertIdx = result.findIndex((s) => s.localId === step.insertAfterSectionId);
        const i = insertIdx === -1 ? result.length : insertIdx + 1;
        result = [...result.slice(0, i), newSection, ...result.slice(i)];
      } else {
        result = [...result, newSection];
      }
    } else if (
      (step.type === 'normalizeColor' || step.type === 'improveLayout') &&
      step.sectionId &&
      'patch' in step &&
      step.patch
    ) {
      const idx = result.findIndex((s) => s.localId === step.sectionId);
      if (idx !== -1) {
        const nextProps = { ...result[idx].props, ...step.patch };
        result = [...result];
        result[idx] = {
          ...result[idx],
          props: nextProps,
          propsText: JSON.stringify(nextProps, null, 2),
        };
      }
    }
  }

  return result;
}

/**
 * Builds a short human-readable summary of applied steps for the chat reply.
 */
export function formatImprovementSummary(steps: OptimizationStep[], appliedCount: number): string {
  if (appliedCount === 0) {
    return 'No changes were needed; the page already looks good.';
  }
  const labels: string[] = [];
  for (const step of steps) {
    if (step.type === 'replaceVariant') {
      labels.push(`Upgraded ${step.sectionKind} layout`);
    } else if (step.type === 'addSection') {
      labels.push(step.label ? `Added ${step.label} section` : `Added ${step.sectionKind} section`);
    } else if (step.type === 'normalizeColor' && step.sectionKind === 'cta') {
      labels.push('Improved CTA visibility');
    } else if (step.type === 'normalizeSpacing') {
      labels.push('Normalized spacing');
    }
  }
  const unique = [...new Set(labels)];
  if (unique.length === 0) return `Applied ${appliedCount} improvement(s).`;
  return `Applied: ${unique.join('; ')}.`;
}
