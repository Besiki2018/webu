/**
 * Layout Refiner Engine for Webu Builder.
 * Analyzes page/section structure and produces refinement operations for spacing,
 * container alignment, grid, and typography. Non-destructive; only suggests parameter/class updates.
 */

import type { SectionItem } from '../changes/applyChangeSet';
import { applyChangeSetToSections } from '../changes/applyChangeSet';
import type { ChangeSet, UpdateSectionOp } from '../changes/changeSet.schema';
import { SPACING, CONTAINER_CLASS, TYPOGRAPHY } from '../designSystem';

export interface LayoutRefinerInput {
  sections: SectionItem[];
  /** Optional: if true, apply large section spacing by default */
  defaultSpacing?: 'large' | 'medium' | 'small';
}

/** Input for refiner with optional AI suggestions (e.g. interpret "suggest layout improvements"). */
export interface LayoutRefinerInputWithAI extends LayoutRefinerInput {
  /** When provided, AI-suggested updateSection ops are merged with rule-based refinements. */
  aiSuggest?: (ctx: { sections: SectionItem[] }) => Promise<ChangeSet | null>;
}

export interface LayoutRefinerResult {
  changeSet: ChangeSet;
  report: string[];
}

const LARGE_PADDING = { paddingTop: '80px', paddingBottom: '80px' };
const MEDIUM_PADDING = { paddingTop: '60px', paddingBottom: '60px' };
const SMALL_PADDING = { paddingTop: '40px', paddingBottom: '40px' };

/** Section types that typically use medium spacing (e.g. features, testimonials) */
const MEDIUM_SPACING_TYPES = new Set([
  'webu_general_features_01',
  'webu_general_testimonials_01',
  'webu_general_newsletter_01',
  'features',
  'testimonials',
  'newsletter',
]);

/** Section types that use small spacing (e.g. banner, announcement) */
const SMALL_SPACING_TYPES = new Set([
  'webu_general_spacer_01',
  'webu_announcement_01',
  'spacer',
  'announcement',
  'banner',
]);

/**
 * Run layout refinement: analyze sections and produce a ChangeSet of updateSection
 * operations for spacing, container, and typography consistency.
 */
export function runLayoutRefiner(input: LayoutRefinerInput): LayoutRefinerResult {
  const { sections, defaultSpacing = 'large' } = input;
  const report: string[] = [];
  const operations: ChangeSet['operations'] = [];

  const defaultPadding =
    defaultSpacing === 'medium'
      ? MEDIUM_PADDING
      : defaultSpacing === 'small'
        ? SMALL_PADDING
        : LARGE_PADDING;

  sections.forEach((section, index) => {
    const sectionId = section.id ?? `section-${index}`;
    const type = (section.type || '').trim().toLowerCase();
    const props = section.props ?? {};
    const patch: Record<string, unknown> = {};

    // Spacing
    const hasExplicitPadding =
      props.paddingTop !== undefined ||
      props.paddingBottom !== undefined ||
      props.padding_y !== undefined ||
      (typeof props.style === 'object' && props.style && (props.style as Record<string, unknown>).paddingTop !== undefined);

    if (!hasExplicitPadding) {
      let padding = defaultPadding;
      if (MEDIUM_SPACING_TYPES.has(type)) {
        padding = MEDIUM_PADDING;
        report.push(`Applied medium spacing to section ${sectionId}`);
      } else if (SMALL_SPACING_TYPES.has(type)) {
        padding = SMALL_PADDING;
        report.push(`Applied small spacing to section ${sectionId}`);
      } else {
        report.push(`Applied large spacing to section ${sectionId}`);
      }
      patch.paddingTop = padding.paddingTop;
      patch.paddingBottom = padding.paddingBottom;
    }

    // Container: ensure component knows to use container class (if it supports it)
    if (
      props.containerClass === undefined &&
      props.max_width === undefined &&
      !type.includes('header') &&
      !type.includes('footer')
    ) {
      patch.containerClass = CONTAINER_CLASS;
    }

    // Typography: optional H1/H2 defaults if component has headline/title and no explicit font size
    if (
      (props.headline !== undefined || props.title !== undefined) &&
      props.fontSize === undefined &&
      props.headlineSize === undefined
    ) {
      patch.headlineSize = TYPOGRAPHY.h2.desktop;
      patch.headlineSizeTablet = TYPOGRAPHY.h2.tablet;
      patch.headlineSizeMobile = TYPOGRAPHY.h2.mobile;
    }

    if (Object.keys(patch).length > 0) {
      operations.push({
        op: 'updateSection',
        sectionId: String(sectionId),
        patch,
      });
    }
  });

  if (report.length === 0 && operations.length === 0) {
    report.push('No layout refinements needed.');
  } else if (operations.length > 0) {
    report.unshift(`Layout refinement: ${operations.length} section(s) updated.`);
  }

  return {
    changeSet: { operations, summary: report },
    report,
  };
}

/**
 * Apply layout refiner result to a sections array (via applyChangeSet).
 * Re-export from changes for convenience.
 */
export { applyChangeSetToSections } from '../changes/applyChangeSet';

/**
 * One-shot: run refiner and apply to sections. Use from CMS when user triggers "Optimize Layout".
 * Returns updated sections and a short report for toast/log.
 */
export function applyLayoutRefinement(sections: SectionItem[], defaultSpacing: 'large' | 'medium' | 'small' = 'large'): {
  sections: SectionItem[];
  report: string[];
} {
  const { changeSet, report } = runLayoutRefiner({ sections, defaultSpacing });
  const updated = applyChangeSetToSections(sections, changeSet);
  return { sections: updated, report };
}

/**
 * Run layout refiner and optionally merge AI-suggested updateSection ops.
 * When aiSuggest is provided, it is called with current sections; only updateSection
 * operations from the returned ChangeSet are merged with rule-based refinements.
 */
export async function runLayoutRefinerWithAI(input: LayoutRefinerInputWithAI): Promise<LayoutRefinerResult> {
  const ruleResult = runLayoutRefiner({ sections: input.sections, defaultSpacing: input.defaultSpacing });
  let operations = [...ruleResult.changeSet.operations];

  if (input.aiSuggest) {
    try {
      const aiChangeSet = await input.aiSuggest({ sections: input.sections });
      if (aiChangeSet?.operations?.length) {
        const safeOps = aiChangeSet.operations.filter((o): o is UpdateSectionOp => o.op === 'updateSection');
        operations = [...operations, ...safeOps];
        if (safeOps.length > 0) {
          ruleResult.report.push(`AI suggested ${safeOps.length} layout update(s).`);
        }
      }
    } catch {
      // ignore AI suggest failures; rule-based result still applies
    }
  }

  return {
    changeSet: { ...ruleResult.changeSet, operations },
    report: ruleResult.report,
  };
}

/**
 * One-shot: run refiner with optional AI and apply to sections.
 * Pass options.aiSuggest to merge AI-suggested layout improvements with rule-based refinements.
 */
export async function applyLayoutRefinementWithAI(
  sections: SectionItem[],
  defaultSpacing: 'large' | 'medium' | 'small' = 'large',
  options?: { aiSuggest?: (ctx: { sections: SectionItem[] }) => Promise<ChangeSet | null> }
): Promise<{ sections: SectionItem[]; report: string[] }> {
  if (options?.aiSuggest) {
    const { changeSet, report } = await runLayoutRefinerWithAI({ sections, defaultSpacing, aiSuggest: options.aiSuggest });
    const updated = applyChangeSetToSections(sections, changeSet);
    return { sections: updated, report };
  }
  return applyLayoutRefinement(sections, defaultSpacing);
}
