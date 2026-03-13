/**
 * Transitional compatibility shim.
 * The canonical runtime registry now lives in builder/componentRegistry.ts.
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
    DEFAULT_HERO_REGISTRY_ID,
    DEFAULT_FEATURES_REGISTRY_ID,
    DEFAULT_FOOTER_REGISTRY_ID,
    DEFAULT_GENERIC_SECTION_REGISTRY_ID,
} from '../componentRegistry';

export type { ComponentRegistryEntry } from '../componentRegistry';

export function registerGeneratedComponent(): void {
    throw new Error(
        'registerGeneratedComponent is no longer supported in builder/registry/componentRegistry.ts. Use builder/componentRegistry.ts as the canonical registry.'
    );
}
