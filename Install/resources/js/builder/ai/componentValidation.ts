/**
 * Part 11 — Component Validation.
 *
 * After generation, run validation. Checks:
 * - schema exists
 * - defaults exist
 * - component compiles (structure valid)
 * - component renders (registry: component is function)
 * - registry updated
 * - builder can edit props (schema has editable fields, defaults exist)
 *
 * If validation fails → caller should regenerate component.
 */

import type { GeneratedComponentFolder, GeneratedFile } from './componentFolderGenerator';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface ValidationCheckResult {
  name: string;
  passed: boolean;
  message?: string;
}

export interface ComponentValidationResult {
  valid: boolean;
  errors: string[];
  checks: {
    schemaExists: ValidationCheckResult;
    defaultsExist: ValidationCheckResult;
    componentCompiles: ValidationCheckResult;
    componentRenders: ValidationCheckResult;
    registryUpdated: ValidationCheckResult;
    builderCanEditProps: ValidationCheckResult;
  };
}

export type GetEntryFn = (registryId: string) => {
  schema?: Record<string, unknown>;
  defaults?: Record<string, unknown>;
  component?: unknown;
} | null;

const CHECK_NAMES = [
  'schemaExists',
  'defaultsExist',
  'componentCompiles',
  'componentRenders',
  'registryUpdated',
  'builderCanEditProps',
] as const;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function findFile(files: GeneratedFile[], suffix: string): GeneratedFile | undefined {
  return files.find((f) => f.path.endsWith(suffix));
}

function checkSchemaExists(files: GeneratedFile[]): ValidationCheckResult {
  const schemaFile = findFile(files, '.schema.ts');
  if (!schemaFile?.content) {
    return { name: 'schemaExists', passed: false, message: 'Schema file missing or empty' };
  }
  const hasEditableFields =
    /editableFields\s*:\s*\[/.test(schemaFile.content) || /component\s*:\s*["']/.test(schemaFile.content);
  if (!hasEditableFields) {
    return { name: 'schemaExists', passed: false, message: 'Schema file has no editableFields or component' };
  }
  return { name: 'schemaExists', passed: true };
}

function checkDefaultsExist(files: GeneratedFile[]): ValidationCheckResult {
  const defaultsFile = findFile(files, '.defaults.ts');
  if (!defaultsFile?.content) {
    return { name: 'defaultsExist', passed: false, message: 'Defaults file missing or empty' };
  }
  const hasExport = /export\s+(const|default)\s+\w+Defaults/.test(defaultsFile.content) || /export\s+\{\s*\w+Defaults\s*\}/.test(defaultsFile.content);
  if (!hasExport) {
    return { name: 'defaultsExist', passed: false, message: 'Defaults file has no export' };
  }
  return { name: 'defaultsExist', passed: true };
}

function checkComponentCompiles(files: GeneratedFile[]): ValidationCheckResult {
  const tsxFile = findFile(files, '.tsx');
  if (!tsxFile?.content) {
    return { name: 'componentCompiles', passed: false, message: 'Component TSX file missing or empty' };
  }
  const src = tsxFile.content;
  const hasDefaultExport = /export\s+default\s+function\s+\w+/.test(src) || /export\s+default\s+\w+/.test(src);
  if (!hasDefaultExport) {
    return { name: 'componentCompiles', passed: false, message: 'Component has no default export function' };
  }
  const hasJsx = /<section[\s>]/.test(src) || /return\s*\(/.test(src);
  if (!hasJsx) {
    return { name: 'componentCompiles', passed: false, message: 'Component has no JSX return' };
  }
  return { name: 'componentCompiles', passed: true };
}

function checkComponentRendersFromFiles(_files: GeneratedFile[]): ValidationCheckResult {
  return {
    name: 'componentRenders',
    passed: true,
    message: 'Renders check requires registry (use validateGeneratedComponentInRegistry)',
  };
}

function checkRegistryUpdated(registryId: string | null, getEntry: GetEntryFn | null): ValidationCheckResult {
  if (!registryId || !getEntry) {
    return {
      name: 'registryUpdated',
      passed: true,
      message: 'Registry check skipped (no registryId or getEntry)',
    };
  }
  const entry = getEntry(registryId);
  if (!entry) {
    return { name: 'registryUpdated', passed: false, message: `Registry has no entry for ${registryId}` };
  }
  return { name: 'registryUpdated', passed: true };
}

function checkComponentRendersFromRegistry(registryId: string | null, getEntry: GetEntryFn | null): ValidationCheckResult {
  if (!registryId || !getEntry) {
    return {
      name: 'componentRenders',
      passed: true,
      message: 'Renders check skipped (no registryId or getEntry)',
    };
  }
  const entry = getEntry(registryId);
  if (!entry) {
    return { name: 'componentRenders', passed: false, message: `Registry has no entry for ${registryId}` };
  }
  const comp = entry.component;
  if (typeof comp !== 'function') {
    return { name: 'componentRenders', passed: false, message: 'Entry component is not a function (cannot render)' };
  }
  return { name: 'componentRenders', passed: true };
}

function checkBuilderCanEditProps(registryId: string | null, getEntry: GetEntryFn | null, files: GeneratedFile[]): ValidationCheckResult {
  if (registryId && getEntry) {
    const entry = getEntry(registryId);
    if (!entry) {
      return { name: 'builderCanEditProps', passed: false, message: 'No registry entry to check editable props' };
    }
    const schema = entry.schema;
    if (!schema || typeof schema !== 'object') {
      return { name: 'builderCanEditProps', passed: false, message: 'Entry has no schema' };
    }
    const editableFields = (schema as { editableFields?: unknown }).editableFields ?? (schema as { fields?: unknown }).fields;
    const hasEditable = Array.isArray(editableFields) && editableFields.length > 0;
    const hasDefaults = entry.defaults != null && typeof entry.defaults === 'object';
    if (!hasEditable || !hasDefaults) {
      return {
        name: 'builderCanEditProps',
        passed: false,
        message: !hasEditable ? 'Schema has no editableFields/fields' : 'Entry has no defaults',
      };
    }
    return { name: 'builderCanEditProps', passed: true };
  }
  const schemaFile = findFile(files, '.schema.ts');
  const defaultsFile = findFile(files, '.defaults.ts');
  const hasSchema = !!schemaFile?.content && /editableFields\s*:\s*\[/.test(schemaFile.content);
  const hasDefaults = !!defaultsFile?.content;
  const passed = hasSchema && hasDefaults;
  return {
    name: 'builderCanEditProps',
    passed,
    message: passed ? undefined : (!hasSchema ? 'Schema missing editableFields' : 'Defaults file missing'),
  };
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Validates a generated component folder (in-memory files).
 * Use after generateComponentFolder, before or after writing to disk.
 * Pass getEntry + registryId to also validate registry and builder-can-edit.
 *
 * @param folder — Output of generateComponentFolder
 * @param options — optional registryId and getEntry for registry/render/edit checks
 * @returns Validation result with per-check pass/fail and errors list
 */
export function validateGeneratedComponentFolder(
  folder: GeneratedComponentFolder,
  options?: { registryId?: string | null; getEntry?: GetEntryFn | null }
): ComponentValidationResult {
  const { registryId = null, getEntry = null } = options ?? {};
  const files = folder.files ?? [];

  const schemaExists = checkSchemaExists(files);
  const defaultsExist = checkDefaultsExist(files);
  const componentCompiles = checkComponentCompiles(files);
  const componentRenders = getEntry && registryId
    ? checkComponentRendersFromRegistry(registryId, getEntry)
    : checkComponentRendersFromFiles(files);
  const registryUpdated = checkRegistryUpdated(registryId, getEntry);
  const builderCanEditProps = checkBuilderCanEditProps(registryId, getEntry, files);

  const checks = {
    schemaExists,
    defaultsExist,
    componentCompiles,
    componentRenders,
    registryUpdated,
    builderCanEditProps,
  };

  const errors = (CHECK_NAMES as readonly string[])
    .map((name) => checks[name as keyof typeof checks] as ValidationCheckResult)
    .filter((c) => !c.passed && c.message && !c.message.includes('skipped'))
    .map((c) => `${c.name}: ${c.message}`);

  const valid = errors.length === 0;

  return { valid, errors, checks };
}

/**
 * Validates that a generated component is correctly registered and usable in the builder.
 * Call after registerGeneratedComponent(registryId, key, entry).
 *
 * @param registryId — e.g. 'webu_general_pricing_table_01'
 * @param getEntry — registry lookup (e.g. getEntry from @/builder/registry)
 */
export function validateGeneratedComponentInRegistry(
  registryId: string,
  getEntry: GetEntryFn
): ComponentValidationResult {
  const entry = getEntry(registryId);
  const schemaExists: ValidationCheckResult = entry?.schema && typeof entry.schema === 'object'
    ? { name: 'schemaExists', passed: true }
    : { name: 'schemaExists', passed: false, message: 'No schema in registry entry' };
  const defaultsExist: ValidationCheckResult = entry?.defaults != null && typeof entry.defaults === 'object'
    ? { name: 'defaultsExist', passed: true }
    : { name: 'defaultsExist', passed: false, message: 'No defaults in registry entry' };
  const componentCompiles: ValidationCheckResult = { name: 'componentCompiles', passed: true, message: 'Assumed (registry entry exists)' };
  const componentRenders = checkComponentRendersFromRegistry(registryId, getEntry);
  const registryUpdated = checkRegistryUpdated(registryId, getEntry);
  const builderCanEditProps = checkBuilderCanEditProps(registryId, getEntry, []);

  const checks = {
    schemaExists,
    defaultsExist,
    componentCompiles,
    componentRenders,
    registryUpdated,
    builderCanEditProps,
  };

  const errors = (CHECK_NAMES as readonly string[])
    .map((name) => checks[name as keyof typeof checks] as ValidationCheckResult)
    .filter((c) => !c.passed && c.message && !c.message.includes('Assumed') && !c.message.includes('skipped'))
    .map((c) => `${c.name}: ${c.message}`);

  return { valid: errors.length === 0, errors, checks };
}

/** Default max regeneration attempts when validation fails. */
export const DEFAULT_MAX_VALIDATION_RETRIES = 2;

/**
 * Runs generation with validation; on failure, regenerates up to maxRetries times.
 * Does not perform registration or file I/O — caller must write files and register.
 *
 * @param generate — function that returns a new GeneratedComponentFolder (e.g. () => generateComponentFolder(spec))
 * @param validate — function that returns validation result (e.g. (folder) => validateGeneratedComponentFolder(folder, { registryId, getEntry }))
 * @param maxRetries — max regeneration attempts after first failure (default 2)
 * @returns { folder, validation, attempt } on success; { validation, attempt } on final failure
 */
export function runGenerationWithValidation<TFolder extends GeneratedComponentFolder>(
  generate: () => TFolder,
  validate: (folder: TFolder) => ComponentValidationResult,
  maxRetries: number = DEFAULT_MAX_VALIDATION_RETRIES
): { folder: TFolder; validation: ComponentValidationResult; attempt: number } | { validation: ComponentValidationResult; attempt: number } {
  let lastValidation: ComponentValidationResult;
  let attempt = 0;
  const totalTries = 1 + maxRetries;

  while (attempt < totalTries) {
    attempt += 1;
    const folder = generate();
    lastValidation = validate(folder);
    if (lastValidation.valid) {
      return { folder, validation: lastValidation, attempt };
    }
  }

  return { validation: lastValidation!, attempt };
}
