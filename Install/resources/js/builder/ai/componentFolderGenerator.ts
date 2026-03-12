/**
 * Part 6 — Component Folder Creation.
 *
 * Generates the full component folder structure matching existing sections:
 *   components/sections/{Name}/
 *     {Name}.tsx
 *     {Name}.schema.ts
 *     {Name}.defaults.ts
 *     {Name}.variants.ts
 *     index.ts
 */

import type { ComponentSpec } from './componentSpecGenerator';
import { generateComponentCode } from './componentCodeGenerator';
import { generateSchema } from './schemaGenerator';
import { generateDefaults } from './defaultsGenerator';
import { generateVariants } from './variantsGenerator';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface GeneratedFile {
  path: string;
  content: string;
}

export interface GeneratedComponentFolder {
  /** Folder path (e.g. components/sections/Pricing). */
  folderPath: string;
  /** All files to write (tsx, schema, defaults, variants, index). */
  files: GeneratedFile[];
  /** Short component name (e.g. Pricing). */
  componentShortName: string;
}

function shortName(componentName: string): string {
  if (componentName.endsWith('Section')) return componentName.slice(0, -7);
  if (componentName.endsWith('Slider')) return componentName.slice(0, -6);
  if (componentName.endsWith('Accordion')) return componentName.slice(0, -9);
  if (componentName.endsWith('Table')) return componentName.slice(0, -5);
  return componentName;
}

function generateIndexTs(short: string): string {
  const upper = short.replace(/([A-Z])/g, '_$1').replace(/^_/, '').toUpperCase();
  return `/**
 * ${short} section — barrel export (auto-generated).
 */

export { default as ${short}, type ${short}Props } from './${short}';
export { ${short}Schema } from './${short}.schema';
export { ${short}Defaults } from './${short}.defaults';
export { ${upper}_VARIANTS, ${upper}_DEFAULT_VARIANT, type ${short}VariantId } from './${short}.variants';
`;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Generates the full component folder: tsx, schema, defaults, variants, index.
 * Aligns with existing section architecture (Features, CTA, Hero, etc.).
 *
 * @param spec — From generateComponentSpec (Part 2).
 * @returns folderPath, files[], componentShortName.
 */
export function generateComponentFolder(spec: ComponentSpec): GeneratedComponentFolder {
  const short = shortName(spec.componentName);
  const folderPath = `components/sections/${short}`;

  const code = generateComponentCode(spec, { useVariantsImport: true });
  const schema = generateSchema(spec);
  const defaults = generateDefaults(spec);
  const variants = generateVariants(spec);

  const files: GeneratedFile[] = [
    { path: code.filePath, content: code.source },
    { path: schema.filePath, content: schema.source },
    { path: defaults.filePath, content: defaults.source },
    { path: variants.filePath, content: variants.source },
    { path: `${folderPath}/index.ts`, content: generateIndexTs(short) },
  ];

  return {
    folderPath,
    files,
    componentShortName: short,
  };
}
