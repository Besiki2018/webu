/**
 * Part 8 — Builder Integration: add generated component to canvas after generation.
 *
 * After generating a component and registering it (registerGeneratedComponent),
 * call this to add the new section to the canvas so the builder renders it immediately.
 *
 * Example: addSectionByKey("webu_general_pricing_table_01", "library") → section appears on canvas.
 */

import type { ComponentSpec } from './componentSpecGenerator';
import { generateRegistryInjection } from './registryInjectionGenerator';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

/** Same shape as Cms addSectionByKey: (sectionKey, source, options?) => void */
export type AddSectionByKeyFn = (
  sectionKey: string,
  source: 'library' | 'toolbar',
  options?: { insertIndex?: number }
) => void;

export interface AddGeneratedSectionToCanvasOptions {
  /** Insert at this index (e.g. before footer). Omit to append. */
  insertIndex?: number;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Adds the generated component to the canvas by calling addSectionByKey with its registry ID.
 * Call after registerGeneratedComponent(registryId, key, entry) so the component is in the registry.
 *
 * @param addSectionByKey — From Cms (or builder): (sectionKey, source, options?) => void
 * @param registryId — e.g. 'webu_general_pricing_table_01'
 * @param options — insertIndex to place before footer or at a specific position
 */
export function addGeneratedSectionToCanvas(
  addSectionByKey: AddSectionByKeyFn,
  registryId: string,
  options?: AddGeneratedSectionToCanvasOptions
): void {
  const insertIndex = options?.insertIndex;
  addSectionByKey(registryId, 'library', typeof insertIndex === 'number' ? { insertIndex } : undefined);
}

/**
 * Returns the registry ID to use when adding a generated section to the canvas.
 * Use with addGeneratedSectionToCanvas(addSectionByKey, registryId).
 */
export function getRegistryIdForGeneratedSpec(spec: ComponentSpec): string {
  const gen = generateRegistryInjection(spec);
  return gen.registryId;
}

/**
 * Full flow: add the section for a generated spec to the canvas.
 * Equivalent to addGeneratedSectionToCanvas(addSectionByKey, getRegistryIdForGeneratedSpec(spec), options).
 */
export function addGeneratedSpecSectionToCanvas(
  addSectionByKey: AddSectionByKeyFn,
  spec: ComponentSpec,
  options?: AddGeneratedSectionToCanvasOptions
): void {
  const registryId = getRegistryIdForGeneratedSpec(spec);
  addGeneratedSectionToCanvas(addSectionByKey, registryId, options);
}
