/**
 * AI Project Processor — processProjectComponents(project).
 *
 * Steps:
 * 1. Analyze project type
 * 2. Scan all components
 * 3. Check compatibility
 * 4. Detect unnecessary elements
 * 5. Apply refactor rules
 * 6. Update component props (return list of updates; caller or apply() runs them)
 *
 * Examples:
 * - Remove search bar if irrelevant (e.g. business → header showSearch: false)
 * - Add ecommerce cart if missing (e.g. ecommerce → header showCartIcon: true)
 * - Adjust navigation items (via refactor rules / prop patches)
 */

import type { ProjectType } from './projectTypes';
import type { BuilderComponentInstance } from './core';
import { isProjectType } from './projectTypes';
import { isComponentCompatibleWithProjectType } from './componentCompatibility';
import {
  analyzeComponentRefactors,
  getRefactorPatchesForTree,
  type RefactorSuggestion,
} from './refactorEngine';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface BuilderProjectInput {
  /** Current project type (e.g. ecommerce, business, restaurant) */
  projectType: ProjectType;
  /** Current component tree (sections/layout) */
  componentTree: BuilderComponentInstance[];
}

export interface ProcessProjectResult {
  /** Resolved project type (from input or default) */
  projectType: ProjectType;
  /** All node ids in the tree (scanned) */
  scannedNodeIds: string[];
  /** Node ids that are compatible with this project type */
  compatibleNodeIds: string[];
  /** Node ids that are incompatible (e.g. restaurant menu in ecommerce) — consider remove or replace */
  incompatibleNodeIds: string[];
  /** Node ids detected as unnecessary for this project type (same as incompatible for now) */
  unnecessaryElementIds: string[];
  /** Refactor suggestions from rules (e.g. remove search, add cart) */
  refactorSuggestions: RefactorSuggestion[];
  /** Prop updates to apply: run updateComponentProps(componentId, { patch }) for each */
  propUpdatesToApply: Array<{ componentId: string; patch: Record<string, unknown> }>;
  /** Summary for AI/log: what was done */
  summary: string[];
}

export type PropUpdater = (componentId: string, payload: { patch: Record<string, unknown> }) => { ok: boolean };

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function collectAllNodeIds(tree: BuilderComponentInstance[]): string[] {
  const ids: string[] = [];
  function visit(nodes: BuilderComponentInstance[]) {
    for (const node of nodes) {
      ids.push(node.id);
      if (node.children?.length) visit(node.children);
    }
  }
  visit(tree);
  return ids;
}

// ---------------------------------------------------------------------------
// Main processor
// ---------------------------------------------------------------------------

const DEFAULT_PROJECT_TYPE: ProjectType = 'landing';

/**
 * Process project components: analyze project type, scan tree, check compatibility,
 * detect unnecessary elements, apply refactor rules, and produce prop updates.
 *
 * Does not mutate the tree. Returns a result object; use propUpdatesToApply with
 * updateComponentProps to apply changes, or call applyPropUpdates(result, updater).
 */
export function processProjectComponents(project: BuilderProjectInput): ProcessProjectResult {
  const summary: string[] = [];
  const projectType = isProjectType(project.projectType) ? project.projectType : DEFAULT_PROJECT_TYPE;
  const componentTree = Array.isArray(project.componentTree) ? project.componentTree : [];

  // 1. Analyze project type
  summary.push(`Project type: ${projectType}`);

  // 2. Scan all components
  const scannedNodeIds = collectAllNodeIds(componentTree);
  summary.push(`Scanned ${scannedNodeIds.length} component(s)`);

  // 3. Check compatibility
  const compatibleNodeIds: string[] = [];
  const incompatibleNodeIds: string[] = [];
  const nodeById = new Map<string, BuilderComponentInstance>();
  function indexNodes(nodes: BuilderComponentInstance[]) {
    for (const node of nodes) {
      nodeById.set(node.id, node);
      if (node.children?.length) indexNodes(node.children);
    }
  }
  indexNodes(componentTree);

  for (const id of scannedNodeIds) {
    const node = nodeById.get(id);
    if (!node) continue;
    const compatible = isComponentCompatibleWithProjectType(node.componentKey, projectType);
    if (compatible) compatibleNodeIds.push(id);
    else incompatibleNodeIds.push(id);
  }
  if (incompatibleNodeIds.length > 0) {
    summary.push(`${incompatibleNodeIds.length} incompatible component(s) for ${projectType}`);
  }

  // 4. Detect unnecessary elements (for this project type)
  const unnecessaryElementIds = [...incompatibleNodeIds];

  // 5. Apply refactor rules
  const refactorSuggestions = analyzeComponentRefactors(projectType, componentTree);
  if (refactorSuggestions.length > 0) {
    summary.push(`Refactor rules: ${refactorSuggestions.length} suggestion(s) (e.g. remove search, add cart, adjust header)`);
  }

  // 6. Build prop updates from refactor rules
  const propUpdatesToApply = getRefactorPatchesForTree(projectType, componentTree);
  if (propUpdatesToApply.length > 0) {
    summary.push(`Prop updates to apply: ${propUpdatesToApply.length} (run updateComponentProps for each)`);
  }

  return {
    projectType,
    scannedNodeIds,
    compatibleNodeIds,
    incompatibleNodeIds,
    unnecessaryElementIds,
    refactorSuggestions,
    propUpdatesToApply,
    summary,
  };
}

/**
 * Apply the prop updates from a processProjectComponents result.
 * Uses the provided updater (e.g. updateComponentProps from builder/updates/updateComponentProps).
 * Returns count of successful updates and any errors.
 */
export function applyPropUpdates(
  result: ProcessProjectResult,
  updater: PropUpdater
): { applied: number; failed: number; errors: string[] } {
  let applied = 0;
  let failed = 0;
  const errors: string[] = [];
  for (const { componentId, patch } of result.propUpdatesToApply) {
    const out = updater(componentId, { patch });
    if (out.ok) applied += 1;
    else {
      failed += 1;
      errors.push(`Failed to update ${componentId}`);
    }
  }
  return { applied, failed, errors };
}
