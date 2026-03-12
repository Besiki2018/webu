/**
 * Resolve binding paths (e.g. "site.hero.title", "products.featured") from CMS data.
 * Used by LayoutRenderer to pass dynamic content to components.
 */

import type { CMSData } from './types';

export function resolveBindingPath(cmsData: CMSData, path: string): unknown {
  if (!path || typeof path !== 'string') return undefined;
  const parts = path.trim().split('.');
  let current: unknown = cmsData;
  for (const key of parts) {
    if (current == null || typeof current !== 'object') return undefined;
    current = (current as Record<string, unknown>)[key];
  }
  return current;
}

/**
 * Resolve a section's bindings object to a props object (values from cmsData).
 */
export function resolveBindings(
  cmsData: CMSData,
  bindings: Record<string, string> | undefined
): Record<string, unknown> {
  if (!bindings || typeof bindings !== 'object') return {};
  const props: Record<string, unknown> = {};
  for (const [propKey, path] of Object.entries(bindings)) {
    const value = resolveBindingPath(cmsData, path);
    if (value !== undefined) props[propKey] = value;
  }
  return props;
}
