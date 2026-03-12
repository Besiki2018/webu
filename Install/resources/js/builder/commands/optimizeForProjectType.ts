/**
 * Builder command: Optimize for project type.
 *
 * Exposes "Optimize site for [projectType]" (e.g. "Optimize site for ecommerce").
 * The AI must: analyze components, apply compatibility rules, refactor components.
 *
 * Uses: processProjectComponents → apply safe updates → set project type and tree in store.
 */

import type { ProjectType } from '../projectTypes';
import { isProjectType } from '../projectTypes';
import { processAndApplyProjectComponents } from '../aiProjectProcessor';
import { useBuilderStore } from '../store/builderStore';

export const OPTIMIZE_FOR_PROJECT_TYPE_COMMAND = 'optimize_for_project_type';

export interface OptimizeForProjectTypeResult {
  ok: boolean;
  projectType: ProjectType;
  updatesApplied: number;
  summary: string[];
  error?: string;
}

/**
 * Runs the full optimize flow:
 * 1. Analyze components (from store tree)
 * 2. Apply compatibility rules (via processProjectComponents)
 * 3. Refactor components (safe prop updates only)
 * 4. Updates store: projectType and componentTree
 *
 * Call from UI ("Optimize for project type" button) or from AI when user says e.g. "Optimize site for ecommerce".
 */
export function runOptimizeForProjectType(targetProjectType: ProjectType | string): OptimizeForProjectTypeResult {
  const resolvedType: ProjectType = isProjectType(targetProjectType) ? targetProjectType : 'landing';
  const state = useBuilderStore.getState();
  const { componentTree, setProjectType, setComponentTree } = state;

  try {
    const { result, updatedTree } = processAndApplyProjectComponents({
      projectType: resolvedType,
      componentTree,
    });

    setProjectType(resolvedType);
    setComponentTree(updatedTree);

    return {
      ok: true,
      projectType: resolvedType,
      updatesApplied: result.updates.length,
      summary: result.summary,
    };
  } catch (err) {
    const message = err instanceof Error ? err.message : String(err);
    return {
      ok: false,
      projectType: resolvedType,
      updatesApplied: 0,
      summary: [],
      error: message,
    };
  }
}
