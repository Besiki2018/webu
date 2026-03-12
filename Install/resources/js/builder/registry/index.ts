/**
 * Builder registry — component registry.
 * Maps component keys to definitions (component, schema, defaults).
 * Canvas must use this registry to resolve and render components.
 */

export {
  componentRegistry,
  REGISTRY_ID_TO_KEY,
  getRegistryKeyByComponentId,
  getEntry,
  getCentralRegistryEntry,
  hasEntry,
  getRegistryIdsForProjectType,
  isComponentAllowedForProjectType,
  registerGeneratedComponent,
  DEFAULT_HERO_REGISTRY_ID,
  DEFAULT_FEATURES_REGISTRY_ID,
  DEFAULT_FOOTER_REGISTRY_ID,
  DEFAULT_GENERIC_SECTION_REGISTRY_ID,
} from './componentRegistry';
export type { ComponentRegistryEntry } from './componentRegistry';
