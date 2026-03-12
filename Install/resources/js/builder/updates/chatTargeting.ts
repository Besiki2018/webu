/**
 * Chat targeting — selection context for chat editing.
 * When a component is selected: selectedComponentId, selectedComponentSchema, selectedProps.
 * Chat uses: editable fields, allowed updates. Chat uses the same update pipeline (updateComponentProps).
 */

import { useBuilderStore } from '../store/builderStore';
import { getEntry } from '../registry/componentRegistry';
import type { BuilderComponentInstance } from '../core';

export interface EditableField {
  key: string;
  type: string;
  label?: string;
}

export interface AllowedUpdate {
  path: string;
  type: string;
}

export interface SelectionContext {
  selectedComponentId: string | null;
  selectedNode: BuilderComponentInstance | null;
  selectedComponentSchema: Record<string, unknown> | null;
  selectedProps: Record<string, unknown> | null;
  editableFields: EditableField[];
  allowedUpdates: AllowedUpdate[];
}

function getEditableFieldsFromSchema(schema: Record<string, unknown>): EditableField[] {
  if (!schema || typeof schema !== 'object') return [];

  const props = schema.props as Record<string, Record<string, unknown>> | undefined;
  if (props && typeof props === 'object') {
    return Object.entries(props).map(([key, def]) => ({
      key,
      type: (def?.type as string) ?? 'text',
      label: (def?.label as string) ?? key,
    }));
  }

  const fields = schema.fields as Array<{ key?: string; path?: string; type?: string; label?: string }> | undefined;
  if (Array.isArray(fields)) {
    return fields.map((f) => ({
      key: f.path ?? f.key ?? '',
      type: f.type ?? 'text',
      label: f.label ?? f.key ?? '',
    }));
  }

  // Part 9 — Generated component schema: { component, editableFields: [{ key, type }] }
  const editableFields = schema.editableFields as Array<{ key?: string; type?: string; label?: string }> | undefined;
  if (Array.isArray(editableFields)) {
    return editableFields.map((f) => ({
      key: (f.key ?? '').trim(),
      type: (f.type as string) ?? 'text',
      label: (f.label as string) ?? f.key ?? '',
    })).filter((f) => f.key.length > 0);
  }

  return [];
}

/**
 * Derives selection context from store state.
 * Use this (or useChatTargeting) so chat knows: selectedComponentId, selectedComponentSchema, selectedProps, editable fields, allowed updates.
 */
export function getSelectionContext(state: {
  componentTree: BuilderComponentInstance[];
  selectedComponentId: string | null;
  selectedProps: Record<string, unknown> | null;
}): SelectionContext {
  const { componentTree, selectedComponentId, selectedProps } = state;

  if (!selectedComponentId || !componentTree?.length) {
    return {
      selectedComponentId: null,
      selectedNode: null,
      selectedComponentSchema: null,
      selectedProps: null,
      editableFields: [],
      allowedUpdates: [],
    };
  }

  const selectedNode = componentTree.find((n) => n.id === selectedComponentId) ?? null;
  if (!selectedNode) {
    return {
      selectedComponentId,
      selectedNode: null,
      selectedComponentSchema: null,
      selectedProps,
      editableFields: [],
      allowedUpdates: [],
    };
  }

  const entry = getEntry(selectedNode.componentKey);
  const selectedComponentSchema = entry?.schema && typeof entry.schema === 'object' ? (entry.schema as Record<string, unknown>) : null;
  const editableFields = selectedComponentSchema ? getEditableFieldsFromSchema(selectedComponentSchema) : [];
  const allowedUpdates: AllowedUpdate[] = editableFields.map((f) => ({ path: f.key, type: f.type }));

  return {
    selectedComponentId,
    selectedNode,
    selectedComponentSchema,
    selectedProps: selectedProps ?? selectedNode.props,
    editableFields,
    allowedUpdates,
  };
}

/**
 * Hook: returns selection context for chat.
 * Chat must use the same update pipeline: updateComponentProps(selectedComponentId, { path, value }).
 * Only paths in allowedUpdates are valid.
 */
export function useChatTargeting(): SelectionContext {
  const componentTree = useBuilderStore((s) => s.componentTree);
  const selectedComponentId = useBuilderStore((s) => s.selectedComponentId);
  const selectedProps = useBuilderStore((s) => s.selectedProps);

  return getSelectionContext({
    componentTree,
    selectedComponentId,
    selectedProps,
  });
}
