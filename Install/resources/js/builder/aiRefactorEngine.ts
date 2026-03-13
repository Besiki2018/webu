/**
 * AI Component Refactor Engine — analyzes components and suggests refactors by project context.
 *
 * Safe refactor policy (see safeRefactorRules.ts):
 * - Never delete entire components automatically.
 * - Only modify: props, child elements, layout variants.
 * - If a component cannot be safely modified, suggest a replacement instead.
 *
 * Example: Header with generic search
 * - projectType business → suggest remove search (prop change)
 * - projectType ecommerce → suggest replace with product search + cart icon (prop change)
 */

import type { BuilderComponentInstance } from './core/types';
import type { ProjectType } from './projectTypes';
import type { RefactorActionKind } from './refactorActions';
import type { SuggestReplacement } from './safeRefactorRules';
import { getEntry } from './componentRegistry';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

/** Specific action identifier for refactor rules (maps to RefactorActionKind). */
export type RefactorAction =
  | 'remove_search'
  | 'replace_with_product_search_and_cart'
  | 'add_product_search_and_cart'
  | 'custom';

export interface RefactorSuggestion {
  nodeId: string;
  componentKey: string;
  /** Specific rule action id. */
  action: RefactorAction;
  /** Canonical kind: remove_element | replace_element | add_element | modify_element_props | restructure_layout. */
  actionKind: RefactorActionKind;
  reason: string;
  /** Props to merge into the component (apply via updateComponentProps patch). Safe: only props/variant, never deletes component. */
  propPatch: Record<string, unknown>;
  /** Human-readable label for UI. */
  label: string;
  /**
   * When set, the engine suggests replacing this component instead of modifying it (safe-refactor policy).
   * No propPatch is applied automatically; caller may offer user the option to replace.
   */
  suggestReplacement?: SuggestReplacement;
}

interface RefactorRule {
  projectType: ProjectType;
  componentKey: string;
  /** When true, rule applies if component has search (showSearch or searchMode !== 'none'). */
  whenHasSearch?: boolean;
  action: RefactorAction;
  actionKind: RefactorActionKind;
  reason: string;
  label: string;
  /** Build prop patch from current node props. */
  getPropPatch: (props: Record<string, unknown>) => Record<string, unknown>;
}

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function headerHasSearch(props: Record<string, unknown>): boolean {
  const showSearch = props.showSearch === true;
  const searchMode = props.searchMode as string | undefined;
  const hasSearchMode = searchMode && searchMode !== 'none';
  return showSearch || !!hasSearchMode;
}

// ---------------------------------------------------------------------------
// Refactor rules (data-driven)
// ---------------------------------------------------------------------------

const REFACTOR_RULES: RefactorRule[] = [
  {
    projectType: 'business',
    componentKey: 'webu_header_01',
    whenHasSearch: true,
    action: 'remove_search',
    actionKind: 'remove_element',
    reason: 'Business projects typically do not need a header search bar; removing it keeps the header focused.',
    label: 'Remove search from header',
    getPropPatch: () => ({
      showSearch: false,
      searchMode: 'none',
    }),
  },
  {
    projectType: 'ecommerce',
    componentKey: 'webu_header_01',
    whenHasSearch: true,
    action: 'replace_with_product_search_and_cart',
    actionKind: 'replace_element',
    reason: 'Ecommerce projects benefit from product search, cart icon, and wishlist in the header.',
    label: 'Replace with product search + cart icon + wishlist',
    getPropPatch: () => ({
      showSearch: true,
      searchMode: 'product',
      showCartIcon: true,
      showWishlistIcon: true,
    }),
  },
  {
    projectType: 'ecommerce',
    componentKey: 'webu_header_01',
    whenHasSearch: false,
    action: 'add_product_search_and_cart',
    actionKind: 'add_element',
    reason: 'Ecommerce projects benefit from product search, cart icon, and wishlist in the header.',
    label: 'Add product search + cart icon + wishlist',
    getPropPatch: () => ({
      showSearch: true,
      searchMode: 'product',
      showCartIcon: true,
      showWishlistIcon: true,
    }),
  },
];

// ---------------------------------------------------------------------------
// Engine API
// ---------------------------------------------------------------------------

/**
 * Returns refactor suggestions for a single node given the project type.
 * Only suggests if the node's component has a matching rule and condition.
 */
export function analyzeNodeForRefactor(
  projectType: ProjectType,
  node: BuilderComponentInstance
): RefactorSuggestion[] {
  const suggestions: RefactorSuggestion[] = [];
  const props = node.props ?? {};
  const componentKey = node.componentKey;

  for (const rule of REFACTOR_RULES) {
    if (rule.projectType !== projectType || rule.componentKey !== componentKey) continue;

    if (rule.whenHasSearch !== undefined) {
      const hasSearch = headerHasSearch(props);
      if (rule.whenHasSearch && !hasSearch) continue;
      if (!rule.whenHasSearch && hasSearch) continue;
    }

    const propPatch = rule.getPropPatch(props);
    if (Object.keys(propPatch).length === 0) continue;

    suggestions.push({
      nodeId: node.id,
      componentKey: node.componentKey,
      action: rule.action,
      actionKind: rule.actionKind,
      reason: rule.reason,
      label: rule.label,
      propPatch,
    });
  }

  return suggestions;
}

/**
 * Analyzes the full component tree and returns all refactor suggestions for the project type.
 */
export function analyzeTreeForRefactor(
  projectType: ProjectType,
  componentTree: BuilderComponentInstance[]
): RefactorSuggestion[] {
  const suggestions: RefactorSuggestion[] = [];

  function walk(nodes: BuilderComponentInstance[]) {
    for (const node of nodes) {
      suggestions.push(...analyzeNodeForRefactor(projectType, node));
      if (Array.isArray(node.children) && node.children.length > 0) {
        walk(node.children);
      }
    }
  }

  walk(componentTree);
  return suggestions;
}

/**
 * Converts a refactor suggestion into a patch payload for updateComponentProps(componentId, { patch }).
 * Caller applies via: updateComponentProps(suggestion.nodeId, { patch: suggestionToPatch(suggestion) }).
 */
export function suggestionToPatch(suggestion: RefactorSuggestion): Record<string, unknown> {
  return { ...suggestion.propPatch };
}

/**
 * Applies a refactor suggestion by returning the arguments for the update pipeline.
 * Use with updateComponentProps: updateComponentProps(nodeId, { patch }).
 */
export function getRefactorPatchPayload(suggestion: RefactorSuggestion): {
  componentId: string;
  patch: Record<string, unknown>;
} {
  return {
    componentId: suggestion.nodeId,
    patch: suggestionToPatch(suggestion),
  };
}

/**
 * Returns whether the schema for the given registry ID supports the refactor props we might set.
 * Used to avoid suggesting patches for components that don't have the props.
 */
export function componentSupportsHeaderSearchRefactor(registryId: string): boolean {
  const entry = getEntry(registryId);
  if (!entry?.schema || typeof entry.schema !== 'object') return false;
  const schema = entry.schema as { props?: Record<string, unknown> };
  const props = schema.props ?? {};
  return 'showSearch' in props && 'searchMode' in props && 'showCartIcon' in props;
}
