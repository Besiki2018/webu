/**
 * AI Project Processor — processProjectComponents(project).
 *
 * Steps:
 * 1. Analyze project type
 * 2. Scan all components
 * 3. Check compatibility
 * 4. Detect unnecessary elements
 * 5. Apply refactor rules (safe only: props, child elements, layout variants)
 * 6. Produce updates (prop patches only; never delete entire components)
 *
 * Safe refactor policy: see safeRefactorRules.ts. Only modify props/variants; suggest replacement when unsafe.
 */

import type { BuilderComponentInstance } from './core/types';
import type { ProjectType } from './projectTypes';
import { isProjectType, defaultProjectType } from './projectTypes';
import { isComponentCompatibleWithProjectType } from './componentCompatibility';
import {
  analyzeTreeForRefactor,
  type RefactorSuggestion,
  getRefactorPatchPayload,
} from './aiRefactorEngine';
import { isSafeRefactorSuggestion } from './safeRefactorRules';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

/** Input project: has projectType and component tree. */
export interface ProcessableProject {
  projectType?: ProjectType | string;
  componentTree: BuilderComponentInstance[];
}

/** Per-node compatibility result. */
export interface NodeCompatibility {
  nodeId: string;
  componentKey: string;
  compatible: boolean;
}

/** One prop update to apply (nodeId + patch). */
export interface ComponentPropUpdate {
  nodeId: string;
  patch: Record<string, unknown>;
}

/** Result of processProjectComponents(project). */
export interface ProcessProjectResult {
  /** Resolved project type (from project or default). */
  projectType: ProjectType;
  /** All node ids and component keys in tree order. */
  scannedNodes: Array<{ id: string; componentKey: string }>;
  /** Compatibility per node (for reporting; incompatible nodes can be flagged). */
  compatibility: NodeCompatibility[];
  /** Refactor suggestions (unnecessary elements + rule-based changes). */
  suggestions: RefactorSuggestion[];
  /** Updates to apply: merge these into component props (remove search, add cart, etc.). */
  updates: ComponentPropUpdate[];
  /** Human-readable summary (e.g. "Remove search from header; Add product search + cart"). */
  summary: string[];
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function collectNodes(tree: BuilderComponentInstance[]): Array<{ id: string; componentKey: string }> {
  const out: Array<{ id: string; componentKey: string }> = [];
  function walk(nodes: BuilderComponentInstance[]) {
    for (const node of nodes) {
      out.push({ id: node.id, componentKey: node.componentKey });
      if (Array.isArray(node.children) && node.children.length > 0) walk(node.children);
    }
  }
  walk(tree);
  return out;
}

function applyUpdatesToTree(
  tree: BuilderComponentInstance[],
  updates: ComponentPropUpdate[]
): BuilderComponentInstance[] {
  const byId = new Map(updates.map((u) => [u.nodeId, u.patch]));

  function mapNode(node: BuilderComponentInstance): BuilderComponentInstance {
    const patch = byId.get(node.id);
    const nextProps = patch ? { ...node.props, ...patch } : node.props;
    const nextChildren =
      Array.isArray(node.children) && node.children.length > 0
        ? node.children.map(mapNode)
        : undefined;
    const out: BuilderComponentInstance = { ...node, props: nextProps };
    if (nextChildren !== undefined) out.children = nextChildren;
    return out;
  }

  return tree.map(mapNode);
}

// ---------------------------------------------------------------------------
// Main API
// ---------------------------------------------------------------------------

/**
 * Processes a project: analyze project type, scan components, check compatibility,
 * detect unnecessary elements, apply refactor rules, and produce updates.
 *
 * Does not mutate the project. Use applyProjectComponentUpdates to get a new tree
 * with updates applied, or apply updates via updateComponentProps in your store.
 */
export function processProjectComponents(project: ProcessableProject): ProcessProjectResult {
  const projectType: ProjectType = isProjectType(project.projectType)
    ? project.projectType
    : defaultProjectType;
  const componentTree = project.componentTree ?? [];

  // 1. Analyze project type (done above)
  // 2. Scan all components
  const scannedNodes = collectNodes(componentTree);

  // 3. Check compatibility
  const compatibility: NodeCompatibility[] = scannedNodes.map(({ id, componentKey }) => ({
    nodeId: id,
    componentKey,
    compatible: isComponentCompatibleWithProjectType(componentKey, projectType),
  }));

  // 4 & 5. Detect unnecessary elements + apply refactor rules
  const suggestions = analyzeTreeForRefactor(projectType, componentTree);

  // 6. Build updates (only safe suggestions: prop patches; never delete components)
  const updatesByNode = new Map<string, Record<string, unknown>>();
  for (const s of suggestions) {
    if (!isSafeRefactorSuggestion(s) || (s.suggestReplacement && Object.keys(s.propPatch).length === 0)) continue;
    const { componentId, patch } = getRefactorPatchPayload(s);
    if (Object.keys(patch).length === 0) continue;
    const existing = updatesByNode.get(componentId) ?? {};
    updatesByNode.set(componentId, { ...existing, ...patch });
  }
  const updates: ComponentPropUpdate[] = Array.from(updatesByNode.entries()).map(
    ([nodeId, patch]) => ({ nodeId, patch })
  );

  const summary = suggestions.map((s) => s.label);

  return {
    projectType,
    scannedNodes,
    compatibility,
    suggestions,
    updates,
    summary,
  };
}

/**
 * Applies the updates from a ProcessProjectResult to a component tree.
 * Returns a new tree; does not mutate the input.
 */
export function applyProjectComponentUpdates(
  componentTree: BuilderComponentInstance[],
  updates: ComponentPropUpdate[]
): BuilderComponentInstance[] {
  return applyUpdatesToTree(componentTree, updates);
}

/**
 * Convenience: process the project and return the tree with refactor updates applied.
 * Use when you want to replace the project's componentTree with the refactored version.
 */
export function processAndApplyProjectComponents(
  project: ProcessableProject
): { result: ProcessProjectResult; updatedTree: BuilderComponentInstance[] } {
  const result = processProjectComponents(project);
  const updatedTree = applyProjectComponentUpdates(project.componentTree, result.updates);
  return { result, updatedTree };
}
