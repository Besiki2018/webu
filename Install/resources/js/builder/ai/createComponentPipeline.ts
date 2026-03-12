/**
 * FINAL RESULT — Create component pipeline.
 *
 * When the user writes "Create pricing section", Webu automatically:
 * 1. creates component (TSX)
 * 2. creates schema
 * 3. creates defaults
 * 4. registers component
 * 5. adds it to canvas
 *
 * User can immediately edit it.
 *
 * This module orchestrates the pipeline: detection → spec (with duplicate check) →
 * validation → folder generation. Caller is responsible for writing files to disk,
 * calling registerGeneratedComponent, and addGeneratedSectionToCanvas.
 */

import { detectComponentRequest } from './componentRequestDetector';
import { generateComponentSpecWithDuplicateCheck } from './componentSpecGenerator';
import { generateComponentFolder } from './componentFolderGenerator';
import { validateGeneratedComponentFolder } from './componentValidation';
import { addGeneratedSectionToCanvas, getRegistryIdForGeneratedSpec } from './addGeneratedSectionToCanvas';
import { getExistingSummariesFromBuilderRegistry } from './duplicateComponentChecker';
import type { ComponentSpec } from './componentSpecGenerator';
import type { GeneratedComponentFolder } from './componentFolderGenerator';
import type { AddSectionByKeyFn } from './addGeneratedSectionToCanvas';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface CreateComponentPipelineInput {
  /** User prompt (e.g. "Create pricing section"). */
  prompt: string;
  projectType?: string;
  designStyle?: string;
  /** Registry snapshot for duplicate check. If omitted, no duplicate check (always create). */
  registrySnapshot?: { registryIdToKey: Record<string, string>; getEntry: (id: string) => { schema?: Record<string, unknown> } | null };
}

export interface CreateComponentPipelineResult {
  /** Whether the prompt was a component request and we produced a spec. */
  success: boolean;
  /** If duplicate: do not create; add variant to existing instead. */
  action: 'create' | 'addVariant';
  /** When action === 'create': spec and folder to write; registryId to register and add to canvas. */
  spec?: ComponentSpec;
  folder?: GeneratedComponentFolder;
  registryId?: string;
  /** When action === 'addVariant': use these to add variant to existing component. */
  existingRegistryId?: string;
  existingKey?: string;
  /** Validation result when action === 'create' and folder was generated. */
  validation?: import('./componentValidation').ComponentValidationResult;
  /** Error or reason when success is false. */
  reason?: string;
}

// ---------------------------------------------------------------------------
// Pipeline
// ---------------------------------------------------------------------------

/**
 * Runs the create-component pipeline for a user prompt.
 *
 * 1. Detects component request (e.g. "Create pricing section").
 * 2. Optionally checks for duplicate; if duplicate, returns action 'addVariant'.
 * 3. Generates spec and folder (component, schema, defaults, variants, index).
 * 4. Validates folder (schema exists, defaults exist, component compiles, etc.).
 *
 * Caller must:
 * - Write folder.files to disk (when action === 'create').
 * - Dynamically import the new component and call registerGeneratedComponent(registryId, key, entry).
 * - Call addGeneratedSectionToCanvas(addSectionByKey, registryId) so the section appears on the canvas.
 *
 * Then the user can immediately edit the new section (schema-driven props, same as any section).
 */
export function runCreateComponentPipeline(input: CreateComponentPipelineInput): CreateComponentPipelineResult {
  const { prompt, projectType, designStyle, registrySnapshot } = input;
  const trimmed = prompt?.trim() ?? '';
  if (!trimmed) {
    return { success: false, action: 'create', reason: 'Empty prompt' };
  }

  const detection = detectComponentRequest(trimmed);
  if (!detection.isComponentRequest) {
    return { success: false, action: 'create', reason: 'Not a component request' };
  }
  if (detection.existsInRegistry && detection.registryId) {
    return {
      success: true,
      action: 'addVariant',
      existingRegistryId: detection.registryId,
      reason: 'Component already in registry; add variant instead',
    };
  }

  const existingSummaries = registrySnapshot
    ? getExistingSummariesFromBuilderRegistry(registrySnapshot)
    : [];
  const { spec, duplicateResult } = generateComponentSpecWithDuplicateCheck(
    { prompt: trimmed, projectType, designStyle },
    existingSummaries
  );

  if (duplicateResult.action === 'addVariant') {
    return {
      success: true,
      action: 'addVariant',
      existingRegistryId: duplicateResult.existingRegistryId,
      existingKey: duplicateResult.existingKey,
      reason: 'Equivalent component exists; add variant instead',
    };
  }

  const folder = generateComponentFolder(spec);
  const registryId = getRegistryIdForGeneratedSpec(spec);
  const validation = validateGeneratedComponentFolder(folder);

  return {
    success: true,
    action: 'create',
    spec,
    folder,
    registryId,
    validation,
    ...(validation.valid ? {} : { reason: `Validation failed: ${validation.errors.join('; ')}` }),
  };
}

/**
 * After the caller has written folder files and registered the component,
 * call this to add the new section to the canvas so the user sees it immediately.
 */
export function addCreatedComponentToCanvas(
  addSectionByKey: AddSectionByKeyFn,
  registryId: string,
  options?: { insertIndex?: number }
): void {
  addGeneratedSectionToCanvas(addSectionByKey, registryId, options);
}
