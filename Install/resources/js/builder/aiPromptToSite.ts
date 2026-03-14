/**
 * Thin adapter around the blueprint-first generation pipeline.
 *
 * This file is intentionally not the generation truth. It simply adapts a prompt
 * into the same generate_site payload shape by delegating to the blueprint system.
 */

import type { ProjectType } from './projectTypes';
import type { SiteStructureSection } from './aiSiteGeneration';
import type { ProjectBlueprint, BlueprintGenerationLogEntry } from './ai/blueprintTypes';
import { createBlueprint } from './ai/createBlueprint';
import { buildSiteFromBlueprint } from './ai/buildSiteFromBlueprint';

export interface PromptToSiteInput {
  userPrompt: string;
  projectType?: ProjectType | string | null;
}

export interface PromptToSiteOutput {
  projectType: ProjectType;
  blueprint: ProjectBlueprint;
  generationLog: BlueprintGenerationLogEntry[];
  structure: SiteStructureSection[];
}

export async function promptToSite(input: PromptToSiteInput): Promise<PromptToSiteOutput> {
  const blueprint = createBlueprint({
    prompt: input.userPrompt,
    projectType: input.projectType,
  });
  const result = await buildSiteFromBlueprint({
    prompt: input.userPrompt,
    blueprint,
    builderProjectTypeOverride: typeof input.projectType === 'string' ? input.projectType as ProjectType : null,
    generationMode: 'blueprint',
  });
  const homeSections = result.sitePlan.pages[0]?.sections;

  if (!Array.isArray(homeSections) || homeSections.length === 0) {
    throw new Error('prompt_to_site_requires_blueprint_sections');
  }

  return {
    projectType: result.projectType,
    blueprint: result.blueprint,
    generationLog: result.generationLog,
    structure: homeSections.map((section) => ({
      componentKey: section.componentKey,
      ...(section.variant ? { variant: section.variant } : {}),
      ...(section.props ? { props: section.props } : {}),
    })),
  };
}
