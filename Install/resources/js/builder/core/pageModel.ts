/**
 * Builder page model — serializable page structure.
 * Canvas renders from this model. No functions or non-JSON values.
 */

import type { BuilderComponentInstance } from './types';

/**
 * Serializable component instance (node in the tree).
 * Example:
 * {
 *   id: "hero-1",
 *   componentKey: "webu_general_hero_01",
 *   variant: "hero-2",
 *   props: {},
 *   children: []
 * }
 */
export type BuilderPageNode = BuilderComponentInstance;

/**
 * Builder page model — list of root sections (flat or tree).
 * Each node is serializable: id, componentKey, variant?, props, children?.
 * Canvas receives componentTree of this shape and renders via registry.
 */
export type BuilderPageModel = BuilderPageNode[];

/**
 * Ensures a plain object can be JSON-serialized (no functions, no undefined values that would be dropped).
 * Use before save/export. In practice, store keeps only serializable data; this is for validation.
 */
export function toSerializableNode(node: BuilderComponentInstance): BuilderComponentInstance {
  return {
    id: node.id,
    componentKey: node.componentKey,
    ...(node.variant !== undefined && { variant: node.variant }),
    props: typeof node.props === 'object' && node.props !== null && !Array.isArray(node.props)
      ? node.props
      : {},
    ...(Array.isArray(node.children) &&
      node.children.length > 0 && {
        children: node.children.map(toSerializableNode),
      }),
    ...(node.responsive !== undefined &&
      typeof node.responsive === 'object' && { responsive: node.responsive }),
    ...(node.metadata !== undefined &&
      typeof node.metadata === 'object' && { metadata: node.metadata }),
  };
}

/**
 * Serialize page model to JSON string (e.g. for persistence or send to backend).
 */
export function serializePageModel(model: BuilderPageModel): string {
  return JSON.stringify(model.map(toSerializableNode));
}

/**
 * Parse page model from JSON string. Returns empty array on invalid input.
 */
export function parsePageModel(json: string): BuilderPageModel {
  try {
    const parsed = JSON.parse(json);
    if (!Array.isArray(parsed)) return [];
    return parsed.filter(
      (n): n is BuilderComponentInstance =>
        n != null &&
        typeof n === 'object' &&
        typeof (n as BuilderComponentInstance).id === 'string' &&
        typeof (n as BuilderComponentInstance).componentKey === 'string'
    );
  } catch {
    return [];
  }
}
