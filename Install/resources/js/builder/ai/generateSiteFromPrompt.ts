/**
 * Part 8 — Inject Site Into Builder.
 *
 * Orchestrates: prompt → blueprint → section/component assembly → content generator → props generator → builder state.
 * Caller applies the returned tree to the builder (setComponentTree / setSectionsDraft) so the canvas shows the full site.
 */

import type { BuilderComponentInstance } from '../core/types';
import type { BuilderProject, ProjectType } from '../projectTypes';
import type { ProjectBlueprint, BlueprintGenerationLogEntry } from './blueprintTypes';
import { createBlueprint } from './createBlueprint';
import { buildSiteFromBlueprint } from './buildSiteFromBlueprint';
import type { AiSitePlan } from './sitePlanner';
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
import {
  formatGeneratedSiteValidationIssues,
  validateGeneratedSite,
} from './validateGeneratedSite';
import type { BuilderSection } from '../visual/treeUtils';

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
  /** Explicit project type chosen by the user; overrides prompt auto-detection for layout governance. */
  projectType?: ProjectType;
}

export interface GenerateSiteFromPromptResult {
  /** Component tree for the builder store (setComponentTree) or treeToSectionsDraft for Cms. */
  tree: BuilderComponentInstance[];
  /** Section drafts for Cms setSectionsDraft. */
  sectionsDraft: BuilderSection[];
  /** Detected project type; caller should setProjectType(projectType). */
  projectType: ProjectType;
  /** Normalized project metadata for governance-aware callers. */
  project: BuilderProject;
  /** Canonical registry ids the AI planner was allowed to use. */
  available_components: string[];
  /** The normalized blueprint that drove section and component assembly. */
  blueprint: ProjectBlueprint;
  /** Generation log entries emitted by the blueprint pipeline. */
  generationLog: BlueprintGenerationLogEntry[];
  /** Full AI site plan for builder-side mutation adapters. */
  sitePlan: AiSitePlan;
}

// ---------------------------------------------------------------------------
// Main flow
// ---------------------------------------------------------------------------

/**
 * Full flow: prompt → blueprint → section/component assembly → (optional) content generator → props generation → builder state.
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
  const {
    contentProvider = null,
    imageProvider = null,
    language = 'en',
    brandName = null,
    projectType: explicitProjectType,
  } = options;

  const blueprint = createBlueprint({
    prompt,
    projectType: explicitProjectType,
  });
  const blueprintSite = buildSiteFromBlueprint({
    prompt,
    blueprint,
    brandName,
    builderProjectTypeOverride: explicitProjectType ?? null,
    generationMode: 'blueprint',
  });
  const plannedSections = blueprintSite.sitePlan.pages[0]?.sections ?? [];
  const propsByIndex: Record<number, Record<string, unknown>> = plannedSections.reduce<Record<number, Record<string, unknown>>>((accumulator, section, index) => {
    accumulator[index] = {
      ...(section.props ?? {}),
      ...(section.variant ? { variant: section.variant } : {}),
    };
    return accumulator;
  }, {});

  // 4 & 5. Optional content generator → prop enhancer
  if (contentProvider) {
    const contentInput = {
      projectType: blueprintSite.projectType,
      industry: blueprint.businessType,
      tone: blueprint.tone,
      language,
      brandName,
    };

    for (let i = 0; i < plannedSections.length; i++) {
      const section = plannedSections[i]!;
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
    const heroIndex = plannedSections.findIndex((s) => s.componentKey === 'webu_general_hero_01');
    if (heroIndex >= 0) {
      try {
        const imageUrl = await generateImageFromContext(
          {
            sectionType: 'hero',
            industry: blueprint.businessType,
            tone: blueprint.tone,
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

  const plannedSectionsWithProps = plannedSections.map((section, index) => ({
    ...section,
    props: {
      ...(section.props ?? {}),
      ...(propsByIndex[index] ?? {}),
    },
  }));

  const sitePlanWithProps: AiSitePlan = {
    ...blueprintSite.sitePlan,
    pages: blueprintSite.sitePlan.pages.map((page, pageIndex) => (
      pageIndex === 0
        ? {
          ...page,
          sections: plannedSectionsWithProps,
        }
        : page
    )),
  };

  // 6. Builder state (component tree)
  const tree = sectionPlanToComponentTree({ sections: plannedSectionsWithProps }, { propsByIndex: {} });
  const validation = validateGeneratedSite({
    projectType: blueprintSite.projectType,
    tree,
    supportedComponentKeys: sitePlanWithProps.available_components ?? blueprintSite.available_components,
    plannedSections: plannedSectionsWithProps.map((section, index) => ({
      componentKey: section.componentKey,
      props: section.props ?? {},
      sectionId: `planned-section-${index + 1}`,
    })),
    generationMode: blueprintSite.usedEmergencyFallback ? 'emergency-fallback' : 'blueprint',
    usedEmergencyFallback: blueprintSite.usedEmergencyFallback,
  });
  if (!validation.ok) {
    throw new Error(formatGeneratedSiteValidationIssues(validation.issues));
  }

  // 7. Section drafts for Cms
  const sectionsDraft = treeToSectionsDraft(tree);

  return {
    tree,
    sectionsDraft,
    projectType: blueprintSite.projectType,
    project: blueprintSite.project,
    available_components: sitePlanWithProps.available_components ?? [],
    blueprint: blueprintSite.blueprint,
    generationLog: blueprintSite.generationLog,
    sitePlan: sitePlanWithProps,
  };
}
