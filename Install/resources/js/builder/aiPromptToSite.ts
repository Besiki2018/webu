/**
 * Thin adapter around the blueprint-first generation pipeline.
 *
 * This file is intentionally not the generation truth. It simply adapts a prompt
 * into the same generate_site payload shape by delegating to the blueprint system.
 */

import type { ProjectType } from './projectTypes';
import type { SiteStructureSection } from './aiSiteGeneration';
import type { ProjectBlueprint, BlueprintGenerationLogEntry } from './ai/blueprintTypes';
import {
  createBlueprint,
  createEmergencyFallbackBlueprint,
  mapBuilderProjectTypeToBlueprintProjectType,
} from './ai/createBlueprint';
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

export function promptToSite(input: PromptToSiteInput): PromptToSiteOutput {
  const blueprint = createBlueprint({
    prompt: input.userPrompt,
    projectType: input.projectType,
  });
  const result = buildSiteFromBlueprint({
    prompt: input.userPrompt,
    blueprint,
    builderProjectTypeOverride: typeof input.projectType === 'string' ? input.projectType as ProjectType : null,
    generationMode: 'blueprint',
  });

  return {
    projectType: result.projectType,
    blueprint: result.blueprint,
    generationLog: result.generationLog,
    structure: result.sitePlan.pages[0]?.sections.map((section) => ({
      componentKey: section.componentKey,
      ...(section.variant ? { variant: section.variant } : {}),
      ...(section.props ? { props: section.props } : {}),
    })) ?? getDefaultStructureForPrompt(result.projectType),
  };
}

/** Emergency fallback only. Main generation must always flow through a blueprint first. */
export function getDefaultStructureForPrompt(projectType: ProjectType): SiteStructureSection[] {
  const fallbackBlueprint = createEmergencyFallbackBlueprint(
    mapBuilderProjectTypeToBlueprintProjectType(projectType)
  );
  const result = buildSiteFromBlueprint({
    prompt: projectType,
    blueprint: fallbackBlueprint,
    builderProjectTypeOverride: projectType,
    generationMode: 'emergency-fallback',
  });

  return result.sitePlan.pages[0]?.sections.map((section) => ({
    componentKey: section.componentKey,
    ...(section.variant ? { variant: section.variant } : {}),
    ...(section.props ? { props: section.props } : {}),
  })) ?? []
}
