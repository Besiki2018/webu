/**
 * Part 8 — Inject Site Into Builder.
 *
 * Orchestrates: prompt → analyzer → planner → component selector → content generator → props generator → builder state.
 * Caller applies the returned tree to the builder (setComponentTree / setSectionsDraft) so the canvas shows the full site.
 */

import type { BuilderComponentInstance } from '../core/types';
import type { ProjectType } from '../projectTypes';
import { analyzePrompt } from './promptAnalyzer';
import { planSite } from './sitePlanner';
import { applyVariantSelection } from './componentSelector';
import { generateContent, type ContentGeneratorProvider } from './contentGenerator';
import {
  contentToHeroProps,
  contentToFeaturesProps,
  contentToCtaProps,
} from './propsGenerator';
import { sectionPlanToComponentTree } from './siteBuilder';
import { treeToSectionsDraft } from '../aiSiteGeneration';
import {
  generateImageFromContext,
  injectImageIntoProps,
  type ImageGeneratorProvider,
} from './imageGenerator';

// ---------------------------------------------------------------------------
// Component key → content section type (for AI content generation)
// ---------------------------------------------------------------------------

const COMPONENT_KEY_TO_CONTENT_TYPE: Record<string, 'hero' | 'features' | 'cta'> = {
  webu_general_hero_01: 'hero',
  webu_general_features_01: 'features',
  webu_general_cta_01: 'cta',
};

// ---------------------------------------------------------------------------
// Options and result
// ---------------------------------------------------------------------------

export interface GenerateSiteFromPromptOptions {
  /** AI provider for generating section content (hero, features, cta). If omitted, sections use registry defaults only. */
  contentProvider?: ContentGeneratorProvider | null;
  /** Optional AI image provider (DALL·E, Stability, Replicate). If set, generates hero image and injects URL into hero props. */
  imageProvider?: ImageGeneratorProvider | null;
  /** Optional language for generated content. */
  language?: string;
  /** Optional brand name for content. */
  brandName?: string | null;
}

export interface GenerateSiteFromPromptResult {
  /** Component tree for the builder store (setComponentTree) or treeToSectionsDraft for Cms. */
  tree: BuilderComponentInstance[];
  /** Section drafts for Cms setSectionsDraft. */
  sectionsDraft: ReturnType<typeof treeToSectionsDraft>;
  /** Detected project type; caller should setProjectType(projectType). */
  projectType: ProjectType;
}

// ---------------------------------------------------------------------------
// Main flow
// ---------------------------------------------------------------------------

/**
 * Full flow: prompt → analyzer → planner → component selector → (optional) content generator → props generator → builder state.
 * Returns tree and sectionsDraft so the caller can inject into the builder and render the canvas.
 *
 * After generation: setSectionsDraft(result.sectionsDraft), setProjectType(result.projectType) (and optionally persist).
 * Canvas will then show the full site.
 *
 * Part 10 — Editable output: The generated site is fully editable. Same sectionsDraft format as manual sections:
 * drag sections, change text, replace images, change colors, add/delete sections, and chat editing (e.g. "Change hero title",
 * "Replace hero image") work immediately via the same update pipeline. See EDITABLE_OUTPUT.md.
 */
export async function generateSiteFromPrompt(
  prompt: string,
  options: GenerateSiteFromPromptOptions = {}
): Promise<GenerateSiteFromPromptResult> {
  const { contentProvider = null, imageProvider = null, language = 'en', brandName = null } = options;

  // 1. Prompt analyzer
  const analysis = analyzePrompt(prompt);

  // 2. Site planner
  const plan = planSite(analysis);

  // 3. Component selector (variants by tone, avoid duplicates)
  const sectionsWithVariants = applyVariantSelection(plan.sections, {
    projectType: analysis.projectType,
    tone: analysis.tone,
    industry: analysis.industry,
  });
  const planWithVariants = { sections: sectionsWithVariants };

  // 4 & 5. Content generator → props generator (per section that supports content)
  const propsByIndex: Record<number, Record<string, unknown>> = {};

  if (contentProvider) {
    const contentInput = {
      projectType: analysis.projectType,
      industry: analysis.industry,
      tone: analysis.tone,
      language,
      brandName,
    };

    for (let i = 0; i < planWithVariants.sections.length; i++) {
      const section = planWithVariants.sections[i]!;
      const contentType = COMPONENT_KEY_TO_CONTENT_TYPE[section.componentKey];
      if (!contentType) continue;

      try {
        const content = await generateContent(
          { ...contentInput, sectionType: contentType },
          contentProvider
        );
        if (contentType === 'hero' && 'title' in content && 'cta' in content) {
          propsByIndex[i] = contentToHeroProps(content);
        } else if (contentType === 'features' && 'items' in content) {
          propsByIndex[i] = contentToFeaturesProps(content as import('./contentGenerator').FeaturesContentResult);
        } else if (contentType === 'cta' && 'buttonLabel' in content) {
          propsByIndex[i] = contentToCtaProps(content);
        }
      } catch {
        // Keep registry defaults if content generation fails
      }
    }
  }

  // Optional: AI image for first hero section (insert URL into props)
  if (imageProvider) {
    const heroIndex = planWithVariants.sections.findIndex((s) => s.componentKey === 'webu_general_hero_01');
    if (heroIndex >= 0) {
      try {
        const imageUrl = await generateImageFromContext(
          {
            sectionType: 'hero',
            industry: analysis.industry,
            tone: analysis.tone,
          },
          imageProvider
        );
        const existing = propsByIndex[heroIndex] ?? {};
        propsByIndex[heroIndex] = injectImageIntoProps(existing, imageUrl, 'image');
      } catch {
        // Continue without hero image
      }
    }
  }

  // 6. Builder state (component tree)
  const tree = sectionPlanToComponentTree(planWithVariants, { propsByIndex });

  // 7. Section drafts for Cms
  const sectionsDraft = treeToSectionsDraft(tree);

  return {
    tree,
    sectionsDraft,
    projectType: analysis.projectType,
  };
}
