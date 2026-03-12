/**
 * Builder command: Generate site from structure (AI website generation).
 *
 * Phase 8: User prompt "Create SaaS landing page" → AI returns structure (Header, Hero, Features, etc.)
 * → this command builds the tree and sets builder state.
 *
 * Process: prompt → site structure → component selection → variant selection → props generation → builder state creation
 */

import type { ProjectType } from '../projectTypes';
import { isProjectType } from '../projectTypes';
import {
  buildTreeFromStructure,
  DEFAULT_LANDING_STRUCTURE,
  DEFAULT_SAAS_LANDING_STRUCTURE,
  DEFAULT_ECOMMERCE_STRUCTURE,
  type SiteStructureSection,
} from '../aiSiteGeneration';
import { useBuilderStore } from '../store/builderStore';

export const GENERATE_SITE_COMMAND = 'generate_site';

export interface GenerateSiteParams {
  projectType: ProjectType | string;
  /** Ordered list of sections. If omitted, uses default landing structure for project type. */
  structure?: SiteStructureSection[];
}

export interface GenerateSiteResult {
  ok: boolean;
  projectType: ProjectType;
  nodeCount: number;
  error?: string;
}

/**
 * Builds a component tree from the given structure (or default) and sets store: projectType, componentTree.
 * Call from chat when AI sends generate_site tool_result with params.
 */
export function runGenerateSite(params: GenerateSiteParams): GenerateSiteResult {
  const projectType: ProjectType = isProjectType(params.projectType) ? params.projectType : 'landing';
  const structure = params.structure ?? getDefaultStructureForProjectType(projectType);
  const state = useBuilderStore.getState();
  const { setProjectType, setComponentTree } = state;

  try {
    const tree = buildTreeFromStructure({ projectType, structure });
    setProjectType(projectType);
    setComponentTree(tree);
    return { ok: true, projectType, nodeCount: tree.length };
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    return { ok: false, projectType, nodeCount: 0, error: message };
  }
}

function getDefaultStructureForProjectType(projectType: ProjectType): SiteStructureSection[] {
  if (projectType === 'saas') return DEFAULT_SAAS_LANDING_STRUCTURE;
  if (projectType === 'ecommerce') return DEFAULT_ECOMMERCE_STRUCTURE;
  return DEFAULT_LANDING_STRUCTURE;
}
