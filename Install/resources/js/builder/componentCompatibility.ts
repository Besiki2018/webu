/**
 * Component Compatibility Engine — hides components that are irrelevant to the current project type.
 *
 * Example: for projectType "ecommerce", show components tagged product, cart, checkout, filters;
 * hide restaurant menu components (menu, food). Restaurant menu components must not appear in
 * ecommerce projects, and vice versa.
 *
 * Rules:
 * 1. Component must be allowed for project type (schema.projectTypes).
 * 2. Component must not have any capability that is excluded for this project type.
 */

import type { ProjectType } from './projectTypes';
import { getEntry, REGISTRY_ID_TO_KEY } from './registry/componentRegistry';

/** For each project type, capability tags that make a component irrelevant (hidden in the library). */
export const excludedCapabilitiesByProjectType: Record<ProjectType, string[]> = {
  business: [],
  ecommerce: ['menu', 'food', 'booking'],
  saas: [],
  portfolio: [],
  restaurant: ['product', 'cart', 'checkout', 'filters'],
  hotel: ['product', 'cart', 'checkout', 'filters'],
  blog: [],
  landing: [],
  education: [],
};

/** Relevant capabilities per project type (for reference / AI). Components with these tags are especially relevant. */
export const relevantCapabilitiesByProjectType: Record<ProjectType, string[]> = {
  business: ['navigation', 'cta', 'content', 'links'],
  ecommerce: ['product', 'cart', 'checkout', 'filters'],
  saas: ['navigation', 'cta', 'login', 'content'],
  portfolio: ['content', 'links', 'image'],
  restaurant: ['menu', 'food', 'booking'],
  hotel: ['booking', 'menu'],
  blog: ['content', 'links'],
  landing: ['headline', 'cta', 'content', 'image'],
  education: ['content', 'links'],
};

interface SchemaWithCapabilities {
  projectTypes?: ProjectType[];
  capabilities?: string[];
}

/**
 * Returns true if the component should be shown in the library for the given project type.
 * Uses schema.projectTypes and capability exclusions (e.g. restaurant menu components hidden in ecommerce).
 */
export function isComponentCompatibleWithProjectType(
  registryId: string,
  projectType: ProjectType
): boolean {
  const entry = getEntry(registryId);
  if (!entry?.schema || typeof entry.schema !== 'object') return true;

  const schema = entry.schema as SchemaWithCapabilities;

  // 1. Must be allowed for this project type
  const types = schema.projectTypes;
  if (types && types.length > 0 && !types.includes(projectType)) return false;

  // 2. Must not have any capability excluded for this project type
  const capabilities = schema.capabilities;
  if (!capabilities || capabilities.length === 0) return true;

  const excluded = excludedCapabilitiesByProjectType[projectType] ?? [];
  if (excluded.length === 0) return true;

  const hasExcluded = capabilities.some((cap) => excluded.includes(cap));
  return !hasExcluded;
}

/**
 * Returns registry IDs that are compatible with the given project type (for library filtering).
 */
export function getCompatibleRegistryIds(projectType: ProjectType): string[] {
  const ids: string[] = [];
  for (const registryId of Object.keys(REGISTRY_ID_TO_KEY)) {
    if (isComponentCompatibleWithProjectType(registryId, projectType)) {
      ids.push(registryId);
    }
  }
  return ids;
}
