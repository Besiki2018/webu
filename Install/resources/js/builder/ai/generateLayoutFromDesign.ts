/**
 * Part 9 — Builder Integration: generate full page from design image.
 *
 * Pipeline:
 *   design image
 *   → layout detection
 *   → section mapping
 *   → variant matching (style-based)
 *   → content extraction
 *   → layout builder (tree + sectionsDraft)
 *   → builder state injection (caller applies tree / sectionsDraft)
 *
 * Result: full page appears in canvas when caller calls setSectionsDraft(sectionsDraft).
 */

import type { BuilderComponentInstance } from '../core/types';
import type { ProjectType } from '../projectTypes';
import type { BuilderSection } from '../visual/treeUtils';
import { detectLayoutFromImage, getHeuristicLayout, type DetectLayoutOptions } from './layoutDetector';
import { sectionPlanFromLayoutResult } from './sectionMapper';
import { detectStyleFromDesign, applyStyleToSectionPlan } from './designStyleAnalyzer';
import { extractContentFromDesign, type ExtractContentFromDesignOptions } from './designContentExtractor';
import { sectionPlanToComponentTree } from './siteBuilder';
import { treeToSectionsDraft } from '../aiSiteGeneration';
import { contentToHeroProps, contentToCtaProps } from './propsGenerator';
import type { SitePlanResult } from './sitePlanner';

// ---------------------------------------------------------------------------
// Options
// ---------------------------------------------------------------------------

export interface GenerateLayoutFromDesignOptions {
  /** Project type (influences layout heuristic and style). */
  projectType?: ProjectType;
  /** Preferred style hint (e.g. modern, minimal). */
  preferredStyle?: string;
  /** Layout detection options (vision provider, etc.). */
  layoutOptions?: Omit<DetectLayoutOptions, 'projectType'>;
  /** Content extraction options (extraction provider, content generator, etc.). */
  contentOptions?: ExtractContentFromDesignOptions;
  /** Language for extracted/generated content. */
  language?: string;
}

export interface GenerateLayoutFromDesignResult {
  /** Component tree for setComponentTree(). */
  tree: BuilderComponentInstance[];
  /** Section drafts for setSectionsDraft() — full page appears in canvas. */
  sectionsDraft: BuilderSection[];
  /** Project type used (for setProjectType). */
  projectType: ProjectType;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function imageToDataUrl(image: File | string): Promise<string> {
  if (typeof image === 'string') return Promise.resolve(image);
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => {
      const r = reader.result;
      if (typeof r === 'string') resolve(r);
      else reject(new Error('FileReader did not return string'));
    };
    reader.onerror = () => reject(reader.error);
    reader.readAsDataURL(image);
  });
}

function buildPropsByIndexFromContent(
  plan: SitePlanResult,
  extracted: {
    title?: string;
    subtitle?: string;
    ctaText?: string;
    ctaSecondary?: string;
    eyebrow?: string;
    images?: string[];
  }
): Record<number, Record<string, unknown>> {
  const propsByIndex: Record<number, Record<string, unknown>> = {};
  let heroApplied = false;
  let ctaApplied = false;

  for (let i = 0; i < plan.sections.length; i++) {
    const section = plan.sections[i]!;
    const key = section.componentKey;

    if (key === 'webu_general_hero_01' && !heroApplied) {
      heroApplied = true;
      propsByIndex[i] = contentToHeroProps(
        {
          title: extracted.title ?? 'Your headline',
          subtitle: extracted.subtitle ?? 'Supporting text.',
          cta: extracted.ctaText ?? 'Get started',
          ...(extracted.eyebrow && { eyebrow: extracted.eyebrow }),
          ...(extracted.ctaSecondary && { ctaSecondary: extracted.ctaSecondary }),
        },
        { image: extracted.images?.[0] ?? undefined }
      );
    }

    if (key === 'webu_general_cta_01' && !ctaApplied) {
      ctaApplied = true;
      propsByIndex[i] = contentToCtaProps({
        title: extracted.title ?? 'Ready to get started?',
        subtitle: extracted.subtitle,
        buttonLabel: extracted.ctaText ?? 'Get started',
      });
    }
  }

  return propsByIndex;
}

// ---------------------------------------------------------------------------
// Main pipeline
// ---------------------------------------------------------------------------

/**
 * Generates a full page layout from a design image and returns tree + sectionsDraft
 * for builder state injection. Caller should call setSectionsDraft(result.sectionsDraft)
 * and setProjectType(result.projectType) so the full page appears in the canvas.
 *
 * Pipeline: design image → layout detection → section mapping → variant matching (style)
 * → content extraction → layout builder (tree) → sectionsDraft.
 *
 * @param image — Design image (File or data URL / image URL).
 * @param options — projectType, preferredStyle, layoutOptions, contentOptions, language.
 * @returns { tree, sectionsDraft, projectType } for injection.
 */
export async function generateLayoutFromDesign(
  image: File | string,
  options: GenerateLayoutFromDesignOptions = {}
): Promise<GenerateLayoutFromDesignResult> {
  const {
    projectType: optionProjectType = 'landing',
    preferredStyle,
    layoutOptions = {},
    contentOptions = {},
    language = 'en',
  } = options;

  const imageUrl = await imageToDataUrl(image);

  // 1. Layout detection
  const layoutResult = await detectLayoutFromImage(imageUrl, {
    ...layoutOptions,
    projectType: optionProjectType,
    preferredStyle,
  });

  // Final result guarantee: design upload always generates Header, Hero, Features, Testimonials, CTA, Footer.
  // If detection returned fewer blocks (e.g. vision returned sparse result), use full heuristic layout.
  const MIN_FULL_PAGE_BLOCKS = 6;
  const blocks =
    layoutResult.blocks.length >= MIN_FULL_PAGE_BLOCKS
      ? layoutResult.blocks
      : getHeuristicLayout(optionProjectType);

  // 2. Section mapping (blocks → section plan with componentKey + variant)
  const mapped = sectionPlanFromLayoutResult({ blocks }, {
    projectType: optionProjectType,
    preferredStyle,
  });

  // 3. Variant matching (style-based): detect style from design, apply to sections
  const styleResult = await detectStyleFromDesign({
    designImageSource: imageUrl,
    projectType: optionProjectType,
  });
  const sectionsWithVariants = applyStyleToSectionPlan(mapped.sections, styleResult.style);
  const plan: SitePlanResult = { sections: sectionsWithVariants };

  // 4. Content extraction (titles, subtitles, buttons, images)
  const extracted = await extractContentFromDesign(imageUrl, {
    ...contentOptions,
    projectType: optionProjectType,
    language,
  });

  // 5. Build propsByIndex from extracted content (hero, cta)
  const propsByIndex = buildPropsByIndexFromContent(plan, {
    title: extracted.title,
    subtitle: extracted.subtitle,
    ctaText: extracted.ctaText,
    ctaSecondary: extracted.ctaSecondary,
    eyebrow: extracted.eyebrow,
    images: extracted.images,
  });

  // 6. Layout builder: section plan → component tree
  const tree = sectionPlanToComponentTree(plan, { propsByIndex });

  // 7. Section drafts for Cms (builder state injection)
  const sectionsDraft = treeToSectionsDraft(tree);

  return {
    tree,
    sectionsDraft,
    projectType: optionProjectType,
  };
}
