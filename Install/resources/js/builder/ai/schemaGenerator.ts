/**
 * Part 4 — Schema Generator.
 *
 * Automatically generates a schema file from a ComponentSpec.
 * Output: { component, editableFields } for builder/sidebar editing.
 */

import type { ComponentSpec } from './componentSpecGenerator';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface GeneratedSchemaOutput {
  /** Suggested path (e.g. components/sections/Pricing/Pricing.schema.ts). */
  filePath: string;
  /** Full schema file source code. */
  source: string;
  /** Schema export name (e.g. PricingSchema). */
  schemaName: string;
}

/** Repeater/list prop keys. */
const LIST_PROPS = new Set(['plans', 'items', 'members', 'logos', 'fields']);
/** Color/style props. */
const COLOR_PROPS = new Set(['backgroundColor', 'textColor']);
/** Variant prop. */
const VARIANT_PROP = 'variant';

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function shortName(componentName: string): string {
  if (componentName.endsWith('Section')) return componentName.slice(0, -7);
  if (componentName.endsWith('Slider')) return componentName.slice(0, -6);
  if (componentName.endsWith('Accordion')) return componentName.slice(0, -9);
  if (componentName.endsWith('Table')) return componentName.slice(0, -5);
  return componentName;
}

function schemaFieldType(prop: string): string {
  if (LIST_PROPS.has(prop)) return 'list';
  if (prop === 'features' || prop === 'socialLinks') return 'list';
  if (COLOR_PROPS.has(prop)) return 'color';
  if (prop === VARIANT_PROP) return 'select';
  if (prop === 'padding' || prop === 'spacing') return 'spacing';
  return 'text';
}

// ---------------------------------------------------------------------------
// Code generation
// ---------------------------------------------------------------------------

function generateSchemaTs(spec: ComponentSpec): string {
  const short = shortName(spec.componentName);
  const schemaExportName = `${short}Schema`;
  const componentId = short.charAt(0).toLowerCase() + short.slice(1);

  const editableFields = spec.props.map((key) => ({
    key,
    type: schemaFieldType(key),
  }));

  const fieldsJson = editableFields
    .map((f) => `  { key: "${f.key}", type: "${f.type}" }`)
    .join(',\n');

  return `/**
 * ${short} section schema — builder editable fields (auto-generated).
 */

export const ${schemaExportName} = {
  component: "${componentId}",
  editableFields: [
${fieldsJson}
  ],
};
`;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Generates a schema file (TS source) from a component spec.
 * Output path: components/sections/{ShortName}/{ShortName}.schema.ts
 *
 * @param spec — From generateComponentSpec (Part 2).
 * @returns filePath, source, schemaName.
 */
export function generateSchema(spec: ComponentSpec): GeneratedSchemaOutput {
  const short = shortName(spec.componentName);
  const filePath = `components/sections/${short}/${short}.schema.ts`;
  const source = generateSchemaTs(spec);
  const schemaName = `${short}Schema`;
  return { filePath, source, schemaName };
}
