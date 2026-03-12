/**
 * Component Metadata Injection — ensure every component instance contains metadata:
 * componentKey, variant, capabilities, projectTypes.
 *
 * Example:
 * {
 *   componentKey: "webu_header_01",
 *   variant: "header-1",
 *   capabilities: ["navigation", "search"],
 *   projectTypes: ["business", "ecommerce", ...]
 * }
 */

import type { BuilderComponentInstance } from './core/types';
import type { ProjectType } from './projectTypes';
import { getEntry } from './registry/componentRegistry';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

/** Standard metadata injected into every component instance (from registry schema). */
export interface ComponentInstanceMetadata {
  componentKey: string;
  variant?: string;
  capabilities: string[];
  projectTypes: string[];
}

const METADATA_KEYS: (keyof ComponentInstanceMetadata)[] = [
  'componentKey',
  'variant',
  'capabilities',
  'projectTypes',
];

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

interface SchemaWithMetadata {
  projectTypes?: ProjectType[];
  capabilities?: string[];
}

/**
 * Returns metadata for a component from the registry schema.
 * Used when injecting into a node that has componentKey (and optional variant).
 */
export function getMetadataForComponentKey(
  componentKey: string,
  variant?: string
): ComponentInstanceMetadata {
  const entry = getEntry(componentKey);
  const schema = entry?.schema as SchemaWithMetadata | undefined;
  const projectTypes: string[] = Array.isArray(schema?.projectTypes) ? [...schema.projectTypes] : [];
  const capabilities: string[] = Array.isArray(schema?.capabilities) ? [...schema.capabilities] : [];

  return {
    componentKey,
    ...(variant !== undefined && variant !== '' && { variant }),
    capabilities,
    projectTypes,
  };
}

/**
 * Injects standard metadata (componentKey, variant, capabilities, projectTypes) into a node
 * from the registry schema. Preserves existing metadata; overwrites standard keys.
 * Returns a new node (does not mutate).
 */
export function injectComponentMetadata(node: BuilderComponentInstance): BuilderComponentInstance {
  const meta = getMetadataForComponentKey(node.componentKey, node.variant);
  const existing = node.metadata && typeof node.metadata === 'object' ? node.metadata : {};
  const nextMetadata: Record<string, unknown> = { ...existing };
  for (const key of METADATA_KEYS) {
    const v = meta[key];
    if (v !== undefined) nextMetadata[key] = v;
  }
  return {
    ...node,
    metadata: nextMetadata,
  };
}

/**
 * Injects metadata into every node in the tree. Returns a new tree (does not mutate).
 * Call when loading or creating the component tree so every instance has
 * componentKey, variant, capabilities, projectTypes in node.metadata.
 */
export function injectTreeMetadata(
  tree: BuilderComponentInstance[]
): BuilderComponentInstance[] {
  return tree.map((node) => {
    const injected = injectComponentMetadata(node);
    if (Array.isArray(node.children) && node.children.length > 0) {
      return {
        ...injected,
        children: injectTreeMetadata(node.children),
      };
    }
    return injected;
  });
}

/**
 * Reads the standard metadata from a node (node.metadata or from registry if missing).
 * Use for display or AI context.
 */
export function getInstanceMetadata(node: BuilderComponentInstance): ComponentInstanceMetadata {
  const existing = node.metadata as Partial<ComponentInstanceMetadata> | undefined;
  if (
    existing &&
    typeof existing.componentKey === 'string' &&
    Array.isArray(existing.capabilities) &&
    Array.isArray(existing.projectTypes)
  ) {
    return {
      componentKey: existing.componentKey,
      variant: existing.variant,
      capabilities: existing.capabilities,
      projectTypes: existing.projectTypes,
    };
  }
  return getMetadataForComponentKey(node.componentKey, node.variant);
}
