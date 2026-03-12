/**
 * AI Component Refactor Engine — analyzes components in a project and suggests or applies
 * refactors according to project context (project type).
 *
 * Uses standard refactor actions: remove_element, replace_element, add_element,
 * modify_element_props, restructure_layout (see refactorActions.ts).
 *
 * Example: Header with generic search
 * - projectType business → remove search field (remove_element / modify_element_props)
 * - projectType ecommerce → replace with product search + cart (modify_element_props)
 */

import type { ProjectType } from './projectTypes';
import type { BuilderComponentInstance } from './core';
import { getEntry } from './registry/componentRegistry';
import type { RefactorActionKind } from './refactorActions';

export type { RefactorActionKind } from './refactorActions';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface RefactorPayload {
  action: RefactorActionKind;
  targetId: string;
  patch?: Record<string, unknown>;
}

/** @deprecated Use RefactorActionKind from refactorActions instead */
export type RefactorAction =
  | 'remove_capability'
  | 'replace_capability'
  | 'add_capability'
  | 'update_props';

export interface RefactorSuggestion {
  componentId: string;
  componentKey: string;
  projectType: ProjectType;
  /** Standard action type (remove_element, replace_element, add_element, modify_element_props, restructure_layout) */
  actionType: RefactorActionKind;
  /** @deprecated Use actionType */
  action: RefactorAction;
  /** Human/AI-readable description of the refactor */
  description: string;
  /** Optional prop patch to apply (for modify_element_props) */
  propPatch?: Record<string, unknown>;
  /** Optional pipeline payload (for apply refactor) */
  payload?: RefactorPayload;
}

export interface RefactorRule {
  description: string;
  propPatch?: Record<string, unknown>;
  /** Capability to remove (e.g. 'search') */
  removeCapability?: string;
  /** Replace one capability with another */
  replaceCapability?: { from: string; to: string };
  /** Capabilities to add (for suggestion only; actual UI may require component support) */
  addCapabilities?: string[];
}

/** Registry key (e.g. 'header') -> refactor rule for this project type */
type RefactorRuleMap = Partial<Record<string, RefactorRule>>;

/** projectType -> component key -> rule */
const refactorRulesByProjectType: Record<ProjectType, RefactorRuleMap> = {
  business: {
    header: {
      description: 'Remove or simplify search for business sites',
      propPatch: { showSearch: false, searchMode: 'none', showCartIcon: false },
      removeCapability: 'search',
    },
  },
  ecommerce: {
    header: {
      description: 'Replace generic search with product search and add cart icon',
      propPatch: { showSearch: true, searchMode: 'product', showCartIcon: true },
      replaceCapability: { from: 'search', to: 'product_search' },
      addCapabilities: ['cart'],
    },
  },
  saas: {
    header: {
      description: 'Keep or add search; optional login/CTA focus',
      propPatch: { showSearch: true, searchMode: 'generic', showCartIcon: false },
    },
  },
  portfolio: {
    header: {
      description: 'Simplify header; search often not needed',
      propPatch: { showSearch: false, searchMode: 'none', showCartIcon: false },
    },
  },
  restaurant: {
    header: {
      description: 'Header for restaurant; no product search or cart',
      propPatch: { showSearch: false, searchMode: 'none', showCartIcon: false },
    },
  },
  hotel: {
    header: {
      description: 'Header for hotel; no product search or cart',
      propPatch: { showSearch: false, searchMode: 'none', showCartIcon: false },
    },
  },
  blog: {
    header: {
      description: 'Header for blog; generic search useful',
      propPatch: { showSearch: true, searchMode: 'generic', showCartIcon: false },
    },
  },
  landing: {
    header: {
      description: 'Minimal header for landing; optional CTA',
      propPatch: { showSearch: false, searchMode: 'none', showCartIcon: false },
    },
  },
  education: {
    header: {
      description: 'Header for education; generic search optional',
      propPatch: { showSearch: true, searchMode: 'generic', showCartIcon: false },
    },
  },
};

/** Map componentKey (registry id) to short key for rule lookup (e.g. webu_header_01 -> header) */
function getRuleKey(componentKey: string): string | null {
  const entry = getEntry(componentKey);
  if (!entry) return null;
  const keyMap: Record<string, string> = {
    webu_header_01: 'header',
    webu_footer_01: 'footer',
    webu_general_hero_01: 'hero',
    webu_general_features_01: 'features',
    webu_general_cta_01: 'cta',
    webu_general_navigation_01: 'navigation',
    webu_general_cards_01: 'cards',
    webu_general_grid_01: 'grid',
  };
  return keyMap[componentKey] ?? null;
}

/**
 * Analyzes a single node and returns a refactor suggestion if the project type has a rule for this component.
 */
export function analyzeNodeRefactor(
  projectType: ProjectType,
  node: BuilderComponentInstance
): RefactorSuggestion | null {
  const ruleKey = getRuleKey(node.componentKey);
  if (!ruleKey) return null;

  const rules = refactorRulesByProjectType[projectType];
  const rule = rules?.[ruleKey];
  if (!rule) return null;

  const hasPatch = rule.propPatch && Object.keys(rule.propPatch).length > 0;
  const actionType: RefactorActionKind = rule.removeCapability && !hasPatch
    ? 'remove_element'
    : rule.replaceCapability && !hasPatch
      ? 'replace_element'
      : rule.addCapabilities?.length && !hasPatch
        ? 'add_element'
        : 'modify_element_props';

  const legacyAction: RefactorAction = rule.propPatch
    ? 'update_props'
    : rule.replaceCapability
      ? 'replace_capability'
      : rule.removeCapability
        ? 'remove_capability'
        : rule.addCapabilities?.length
          ? 'add_capability'
          : 'update_props';

  const payload: RefactorPayload | undefined = hasPatch
    ? { action: 'modify_element_props', targetId: node.id, patch: rule.propPatch! }
    : undefined;

  return {
    componentId: node.id,
    componentKey: node.componentKey,
    projectType,
    actionType,
    action: legacyAction,
    description: rule.description,
    propPatch: rule.propPatch,
    payload,
  };
}

/**
 * Analyzes all components in the tree and returns refactor suggestions for the given project type.
 * Use this to drive AI or UI: "refactor this project for ecommerce" → list of suggested changes.
 */
export function analyzeComponentRefactors(
  projectType: ProjectType,
  componentTree: BuilderComponentInstance[]
): RefactorSuggestion[] {
  const suggestions: RefactorSuggestion[] = [];

  function visit(nodes: BuilderComponentInstance[]) {
    for (const node of nodes) {
      const suggestion = analyzeNodeRefactor(projectType, node);
      if (suggestion) suggestions.push(suggestion);
      if (node.children?.length) visit(node.children);
    }
  }

  visit(componentTree);
  return suggestions;
}

/**
 * Returns the prop patch for a node if the project type has a refactor rule that includes props.
 * Caller can apply this via updateComponentProps(node.id, { patch: result }).
 */
export function getRefactorPropPatch(
  projectType: ProjectType,
  node: BuilderComponentInstance
): Record<string, unknown> | null {
  const suggestion = analyzeNodeRefactor(projectType, node);
  return suggestion?.propPatch ?? null;
}

/**
 * Builds a list of prop patches for the whole tree. Each item has node id and patch;
 * apply each with updateComponentProps(item.componentId, { patch: item.patch }).
 */
export function getRefactorPatchesForTree(
  projectType: ProjectType,
  componentTree: BuilderComponentInstance[]
): Array<{ componentId: string; patch: Record<string, unknown> }> {
  const result: Array<{ componentId: string; patch: Record<string, unknown> }> = [];
  const suggestions = analyzeComponentRefactors(projectType, componentTree);
  for (const s of suggestions) {
    if (s.propPatch && Object.keys(s.propPatch).length > 0) {
      result.push({ componentId: s.componentId, patch: s.propPatch });
    }
  }
  return result;
}
