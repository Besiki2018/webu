/**
 * Transitional compatibility shim.
 * The canonical registry now lives in builder/componentRegistry.ts.
 */

export {
    REGISTRY_ID_TO_KEY,
    getRegistryKeyByComponentId,
    getCentralRegistryEntry,
    isInCentralRegistry,
} from './componentRegistry';

export type { ComponentRegistryEntry } from './componentRegistry';
