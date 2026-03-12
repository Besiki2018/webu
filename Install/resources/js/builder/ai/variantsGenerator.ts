/**
 * Part 6 + Part 10 — Variants file generator.
 *
 * Generates Pricing.variants.ts (and similar) with layout variant ids
 * (e.g. pricing → cards, horizontal, minimal). CONST_VARIANTS, VariantId type,
 * CONST_DEFAULT_VARIANT; optional VARIANT_OPTIONS for builder dropdowns.
 */

import type { ComponentSpec } from './componentSpecGenerator';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface GeneratedVariantsOutput {
  /** Suggested path (e.g. components/sections/Pricing/Pricing.variants.ts). */
  filePath: string;
  /** Full TypeScript source code. */
  source: string;
  /** Constant name for default variant (e.g. PRICING_DEFAULT_VARIANT). */
  defaultVariantConstantName: string;
}

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

function toUpperSnake(name: string): string {
  return name.replace(/([A-Z])/g, '_$1').replace(/^_/, '').toUpperCase();
}

// ---------------------------------------------------------------------------
// Code generation
// ---------------------------------------------------------------------------

function labelForVariantId(v: string): string {
  return v
    .split(/[-_]/)
    .map((s) => s.charAt(0).toUpperCase() + s.slice(1).toLowerCase())
    .join(' ');
}

function generateVariantsTs(spec: ComponentSpec): string {
  const short = shortName(spec.componentName);
  const upper = toUpperSnake(short);
  const variantsConst = `${upper}_VARIANTS`;
  const variantIdType = `${short}VariantId`;
  const defaultConst = `${upper}_DEFAULT_VARIANT`;
  const optionsConst = `${upper}_VARIANT_OPTIONS`;

  const variantValues = spec.variantTypes.map((v) => `'${v}'`).join(', ');
  const defaultVariant = spec.variantTypes[0] ?? 'default';

  const optionsLines = spec.variantTypes
    .map((v) => `  { label: '${labelForVariantId(v)}', value: '${v}' }`)
    .join(',\n');

  return `/**
 * ${short} section layout variants (auto-generated).
 * Stored in ${short}.variants.ts — e.g. pricing: cards, horizontal, minimal.
 */

export const ${variantsConst} = [${variantValues}] as const;
export type ${variantIdType} = (typeof ${variantsConst})[number];
export const ${defaultConst}: ${variantIdType} = '${defaultVariant}';

export const ${optionsConst} = [\n${optionsLines}\n];
`;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Generates a variants file (TS source) from a component spec.
 * Path: components/sections/{ShortName}/{ShortName}.variants.ts
 *
 * @param spec — From generateComponentSpec (Part 2).
 * @returns filePath, source, defaultVariantConstantName.
 */
export function generateVariants(spec: ComponentSpec): GeneratedVariantsOutput {
  const short = shortName(spec.componentName);
  const upper = toUpperSnake(short);
  const filePath = `components/sections/${short}/${short}.variants.ts`;
  const source = generateVariantsTs(spec);
  const defaultVariantConstantName = `${upper}_DEFAULT_VARIANT`;
  return { filePath, source, defaultVariantConstantName };
}
