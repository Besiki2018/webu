/**
 * Update pipeline — updateComponentProps(componentId, payload).
 * Validates component, validates field against schema, patches props, updates store, triggers rerender.
 * Sidebar and Chat must both use this function.
 */

import { getEntry } from '../registry/componentRegistry';
import { useBuilderStore } from '../store/builderStore';

export type UpdatePayload =
  | { path: string | string[]; value: unknown }
  | { patch: Record<string, unknown> };

export interface UpdateResult {
  ok: boolean;
  error?: 'component_not_found' | 'schema_not_found' | 'field_not_found';
  message?: string;
}

const schemaPropKeys = (schema: Record<string, unknown>): Set<string> => {
  const keys = new Set<string>();
  const props = schema.props as Record<string, unknown> | undefined;
  if (props && typeof props === 'object') {
    Object.keys(props).forEach((k) => keys.add(k));
  }
  const fields = schema.fields as Array<{ key?: string; path?: string }> | undefined;
  if (Array.isArray(fields)) {
    fields.forEach((f) => keys.add((f.path ?? f.key) ?? ''));
  }
  return keys;
};

const pathToKey = (path: string | string[]): string => {
  if (Array.isArray(path)) return path[0] ?? '';
  return path;
};

/**
 * Validates component exists in tree and field(s) exist in registry schema.
 * Returns error result or null if valid.
 */
function validate(
  componentTree: Array<{ id: string; componentKey: string; props: Record<string, unknown> }>,
  componentId: string,
  payload: UpdatePayload
): UpdateResult | null {
  const node = componentTree.find((n) => n.id === componentId);
  if (!node) {
    return { ok: false, error: 'component_not_found', message: `Component ${componentId} not found in tree.` };
  }

  const entry = getEntry(node.componentKey);
  if (!entry?.schema || typeof entry.schema !== 'object') {
    return { ok: false, error: 'schema_not_found', message: `No schema for ${node.componentKey}.` };
  }

  const allowed = schemaPropKeys(entry.schema as Record<string, unknown>);
  if (allowed.size === 0) return null;

  if ('path' in payload && payload.path !== undefined) {
    const key = pathToKey(payload.path);
    if (key && !allowed.has(key)) {
      return { ok: false, error: 'field_not_found', message: `Field "${key}" not in schema.` };
    }
  } else if ('patch' in payload && payload.patch && typeof payload.patch === 'object') {
    for (const key of Object.keys(payload.patch)) {
      if (!allowed.has(key)) {
        return { ok: false, error: 'field_not_found', message: `Field "${key}" not in schema.` };
      }
    }
  }

  return null;
}

/**
 * Applies payload to current props (single path or patch object).
 * Supports shallow path (single key or first segment of path).
 */
function applyPayload(
  currentProps: Record<string, unknown>,
  payload: UpdatePayload
): Record<string, unknown> {
  if ('patch' in payload && payload.patch && typeof payload.patch === 'object') {
    return { ...currentProps, ...payload.patch };
  }
  if ('path' in payload && payload.path !== undefined) {
    const key = pathToKey(payload.path);
    return { ...currentProps, [key]: payload.value };
  }
  return currentProps;
}

/**
 * updateComponentProps(componentId, payload)
 *
 * 1. Validate component exists in store tree
 * 2. Validate field(s) exist in componentRegistry[componentKey].schema
 * 3. Patch props
 * 4. Update store (setComponentTree, setSelectedProps if selected)
 * 5. Rerender is triggered by store update (Canvas + Sidebar subscribe)
 *
 * Sidebar and Chat must both use this function.
 */
export function updateComponentProps(componentId: string, payload: UpdatePayload): UpdateResult {
  const state = useBuilderStore.getState();
  const { componentTree, setComponentTree, setSelectedProps, selectedComponentId } = state;

  const validationError = validate(componentTree, componentId, payload);
  if (validationError) return validationError;

  const node = componentTree.find((n) => n.id === componentId);
  if (!node) return { ok: false, error: 'component_not_found', message: 'Component not found.' };

  const nextProps = applyPayload(node.props, payload);
  // Keep node.variant in sync when variant is updated (canonical storage for variant system)
  const nextVariant = nextProps.variant !== undefined ? String(nextProps.variant) : node.variant;
  const nextTree = componentTree.map((n) =>
    n.id === componentId ? { ...n, props: nextProps, variant: nextVariant } : n
  );

  setComponentTree(nextTree);
  if (selectedComponentId === componentId) {
    setSelectedProps(nextProps);
  }

  return { ok: true };
}
