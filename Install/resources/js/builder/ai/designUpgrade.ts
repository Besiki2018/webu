/**
 * Part 10 — Automatic Design Upgrade.
 *
 * When the user says "Improve hero" (or similar), the system:
 * 1. Replaces hero variant (upgrade to stronger variant)
 * 2. Regenerates hero content (headline, subtitle, CTA via AI)
 * 3. Adjusts spacing (normalize spacing from design consistency)
 */

import { optimizeSite, type OptimizationStep } from './siteOptimizer';
import { applyOptimizationStepsToSections, type SectionWithProps } from './chatImprovementCommands';
import {
  contentToHeroProps,
  contentToFeaturesProps,
  contentToCtaProps,
} from './propsGenerator';
import type { HeroContentResult, FeaturesContentResult, CtaContentResult } from './contentGenerator';

// ---------------------------------------------------------------------------
// Section kind helpers (mirror siteOptimizer)
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
  return t || 'unknown';
}

/** Filter optimization steps to those that affect the given section kind (e.g. hero). */
function filterStepsForSectionKind(steps: OptimizationStep[], sectionKind: string): OptimizationStep[] {
  return steps.filter((step) => {
    if (step.type === 'replaceVariant' && step.sectionKind === sectionKind) return true;
    if (step.type === 'addSection') return false;
    if (step.type === 'improveLayout' && (step.sectionKind === sectionKind || step.sectionId)) return true;
    if (step.type === 'normalizeSpacing') return true;
    if (step.type === 'normalizeColor' && step.sectionKind === sectionKind) return true;
    return false;
  });
}

// ---------------------------------------------------------------------------
// Design upgrade pipeline
// ---------------------------------------------------------------------------

export type ContentSectionType = 'hero' | 'features' | 'cta';

/**
 * Fetches generated section content from the backend (e.g. POST /panel/projects/:id/generate-section-content).
 * Returns the content object (hero: title, subtitle, cta, ...; features: title, items; cta: title, buttonLabel).
 */
export type FetchSectionContent = (sectionType: ContentSectionType) => Promise<Record<string, unknown>>;

export interface DesignUpgradeOptions {
  pageType?: 'landing' | 'ecommerce' | 'saas';
  theme?: { primaryColor?: string; fontFamily?: string };
  /** If true, call fetchContent and merge into the target section. */
  regenerateContent?: boolean;
}

export interface DesignUpgradeResult {
  sections: SectionWithProps[];
  stepsApplied: OptimizationStep[];
  contentRegenerated: boolean;
  summary: string[];
}

/**
 * Runs the design upgrade pipeline for a given section kind (e.g. "hero"):
 * 1. Replace variant (from optimizer)
 * 2. Optionally regenerate content (via fetchContent)
 * 3. Adjust spacing (from optimizer)
 *
 * @param sections Current page sections (SectionWithProps)
 * @param sectionKind Target section kind: "hero" | "features" | "cta"
 * @param fetchContent Async function to get generated content (e.g. from API)
 * @param options Optional pageType, theme, regenerateContent (default true for hero/features/cta)
 */
export async function runDesignUpgrade(
  sections: SectionWithProps[],
  sectionKind: string,
  fetchContent: FetchSectionContent | null,
  options: DesignUpgradeOptions = {}
): Promise<DesignUpgradeResult> {
  const { regenerateContent = true } = options;
  const summary: string[] = [];

  const optimizerInput = {
    sections: sections.map((s) => ({
      localId: s.localId,
      type: s.type,
      props: s.props,
    })),
    pageType: options.pageType ?? 'landing',
    theme: options.theme,
  };
  const report = optimizeSite(optimizerInput);
  const stepsForKind = filterStepsForSectionKind(report.transformations, sectionKind);

  let result = applyOptimizationStepsToSections(sections, stepsForKind);
  if (stepsForKind.some((s) => s.type === 'replaceVariant')) {
    summary.push(`Replaced ${sectionKind} variant`);
  }
  if (stepsForKind.some((s) => s.type === 'normalizeSpacing')) {
    summary.push('Adjusted spacing');
  }
  if (stepsForKind.some((s) => s.type === 'normalizeColor')) {
    summary.push('Improved CTA visibility');
  }

  let contentRegenerated = false;
  const contentSectionType = sectionKind as ContentSectionType;
  const canRegenerate =
    (contentSectionType === 'hero' || contentSectionType === 'features' || contentSectionType === 'cta') &&
    regenerateContent &&
    fetchContent;

  if (canRegenerate && fetchContent) {
    const heroIndex = result.findIndex((s) => getSectionKind(s.type) === 'hero');
    const featuresIndex = result.findIndex((s) => getSectionKind(s.type) === 'features');
    const ctaIndex = result.findIndex((s) => getSectionKind(s.type) === 'cta');

    const targetIndex =
      contentSectionType === 'hero'
        ? heroIndex
        : contentSectionType === 'features'
          ? featuresIndex
          : contentSectionType === 'cta'
            ? ctaIndex
            : -1;

    if (targetIndex !== -1) {
      try {
        const content = await fetchContent(contentSectionType);
        if (content && typeof content === 'object') {
          result = [...result];
          const section = result[targetIndex];
          let mergedProps: Record<string, unknown> = { ...section.props };
          if (contentSectionType === 'hero') {
            const heroProps = contentToHeroProps(content as unknown as HeroContentResult);
            mergedProps = { ...mergedProps, ...heroProps };
          } else if (contentSectionType === 'features') {
            const featureProps = contentToFeaturesProps(content as unknown as FeaturesContentResult);
            mergedProps = { ...mergedProps, ...featureProps };
          } else if (contentSectionType === 'cta') {
            const ctaProps = contentToCtaProps(content as unknown as CtaContentResult);
            mergedProps = { ...mergedProps, ...ctaProps };
          }
          result[targetIndex] = {
            ...section,
            props: mergedProps,
            propsText: JSON.stringify(mergedProps, null, 2),
          };
          contentRegenerated = true;
          summary.push(`Regenerated ${sectionKind} content`);
        }
      } catch {
        // Keep result without regenerated content; summary already has variant/spacing
      }
    }
  }

  return {
    sections: result,
    stepsApplied: stepsForKind,
    contentRegenerated,
    summary,
  };
}
