/**
 * Part 7 — Registry Injection.
 *
 * Generates the code to add a generated component to componentRegistry.ts
 * so it appears in the builder library immediately.
 *
 * Output: import statement, REGISTRY_ID_TO_KEY entry, componentRegistry entry.
 */

import type { ComponentSpec } from './componentSpecGenerator';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface GeneratedRegistryInjection {
  /** Full path of the registry file to patch (for reference). */
  registryFilePath: string;
  /** Import statement to add with other section imports. */
  importStatement: string;
  /** Line to add to REGISTRY_ID_TO_KEY (registryId -> key). */
  registryIdToKeyLine: string;
  /** Block to add inside componentRegistry { ... } (trailing comma). */
  componentRegistryEntry: string;
  /** Registry ID (e.g. webu_general_pricing_01). */
  registryId: string;
  /** Short key (e.g. pricing). */
  registryKey: string;
}

/** Combined snippet for manual paste (all parts in order). */
export interface RegistryInjectionSnippet {
  /** Instructions + code to add to componentRegistry.ts. */
  instructions: string;
  /** Import line. */
  importLine: string;
  /** REGISTRY_ID_TO_KEY line. */
  idToKeyLine: string;
  /** componentRegistry entry block. */
  registryEntryBlock: string;
}

function shortName(componentName: string): string {
  if (componentName.endsWith('Section')) return componentName.slice(0, -7);
  if (componentName.endsWith('Slider')) return componentName.slice(0, -6);
  if (componentName.endsWith('Accordion')) return componentName.slice(0, -9);
  if (componentName.endsWith('Table')) return componentName.slice(0, -5);
  return componentName;
}

// ---------------------------------------------------------------------------
// Code generation
// ---------------------------------------------------------------------------

/**
 * Generates the registry injection: import, REGISTRY_ID_TO_KEY line, and componentRegistry entry.
 * Caller can merge these into builder/registry/componentRegistry.ts (or use registerGeneratedComponent at runtime).
 *
 * @param spec — From generateComponentSpec (Part 2). Must have suggestedRegistryId or slug for registry ID.
 * @param options — importPathPrefix: e.g. '@/components/sections' (default).
 */
export function generateRegistryInjection(
  spec: ComponentSpec,
  options?: { importPathPrefix?: string }
): GeneratedRegistryInjection {
  const short = shortName(spec.componentName);
  const prefix = options?.importPathPrefix ?? '@/components/sections';
  const registryId = spec.suggestedRegistryId ?? `webu_general_${(spec.slug ?? short.toLowerCase()).replace(/-/g, '_')}_01`;
  const registryKey = short.charAt(0).toLowerCase() + short.slice(1);

  const importStatement = `import { default as ${short}, ${short}Schema, ${short}Defaults } from '${prefix}/${short}';`;
  const registryIdToKeyLine = `  ${registryId}: '${registryKey}',`;
  const componentRegistryEntry = `
  ${registryKey}: {
    component: ${short} as ComponentType<Record<string, unknown>>,
    schema: ${short}Schema as Record<string, unknown>,
    defaults: ${short}Defaults as Record<string, unknown>,
  },`;

  return {
    registryFilePath: 'resources/js/builder/registry/componentRegistry.ts',
    importStatement,
    registryIdToKeyLine,
    componentRegistryEntry,
    registryId,
    registryKey,
  };
}

/**
 * Returns a single snippet with instructions for manual paste into componentRegistry.ts.
 */
export function getRegistryInjectionSnippet(
  spec: ComponentSpec,
  options?: { importPathPrefix?: string }
): RegistryInjectionSnippet {
  const gen = generateRegistryInjection(spec, options);
  return {
    instructions: `Add the following to builder/registry/componentRegistry.ts:
1. With other section imports (e.g. after Grid): add the importLine below.
2. In REGISTRY_ID_TO_KEY: add the idToKeyLine.
3. In componentRegistry, before the closing }; add the registryEntryBlock.`,
    importLine: gen.importStatement,
    idToKeyLine: gen.registryIdToKeyLine,
    registryEntryBlock: gen.componentRegistryEntry,
  };
}
