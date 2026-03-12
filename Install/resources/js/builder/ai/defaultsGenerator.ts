/**
 * Part 5 — Default Props Generator.
 *
 * Automatically generates a defaults file (e.g. Pricing.defaults.ts) from a ComponentSpec.
 * Output provides sensible default values for builder/sidebar and runtime.
 */

import type { ComponentSpec } from './componentSpecGenerator';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface GeneratedDefaultsOutput {
  /** Suggested path (e.g. components/sections/Pricing/Pricing.defaults.ts). */
  filePath: string;
  /** Full TypeScript source code. */
  source: string;
  /** Export name (e.g. PricingDefaults). */
  exportName: string;
}

/** Repeater prop keys and their default item shape (field name → example value). */
const REPEATER_DEFAULTS: Record<
  string,
  { itemCount: number; itemFields: Array<{ key: string; jsKey: string; value: string }> }
> = {
  plans: {
    itemCount: 3,
    itemFields: [
      { key: 'planName', jsKey: 'name', value: '"Basic"' },
      { key: 'price', jsKey: 'price', value: '"$19"' },
      { key: 'ctaButton', jsKey: 'cta', value: '"Start"' },
    ],
  },
  items: {
    itemCount: 3,
    itemFields: [
      { key: 'question', jsKey: 'question', value: '"How do I get started?"' },
      { key: 'answer', jsKey: 'answer', value: '"Sign up and follow the steps."' },
    ],
  },
  members: {
    itemCount: 3,
    itemFields: [
      { key: 'name', jsKey: 'name', value: '"Jane Doe"' },
      { key: 'role', jsKey: 'role', value: '"Lead"' },
      { key: 'bio', jsKey: 'bio', value: '"Short bio."' },
    ],
  },
  logos: {
    itemCount: 3,
    itemFields: [
      { key: 'logoUrl', jsKey: 'url', value: '""' },
      { key: 'logoAlt', jsKey: 'alt', value: '"Partner"' },
    ],
  },
};

/** Default title by slug. */
const TITLE_BY_SLUG: Record<string, string> = {
  pricing_table: 'Pricing Plans',
  testimonials_slider: 'What people say',
  team_section: 'Our team',
  faq_accordion: 'Frequently asked questions',
  feature_comparison_table: 'Compare plans',
  stats_section: 'By the numbers',
  logo_strip: 'Trusted by',
  contact_form: 'Get in touch',
  newsletter_signup: 'Stay updated',
};

const STYLE_PROPS = new Set(['variant', 'backgroundColor', 'textColor', 'className', 'padding', 'spacing']);
const LIST_PROPS = new Set(['plans', 'items', 'members', 'logos', 'fields']);
/** Item-level props (repeater item fields) — do not emit as top-level defaults. */
const ITEM_ONLY_PROPS = new Set([
  'planName', 'price', 'features', 'ctaButton', 'question', 'answer', 'quote', 'author', 'role', 'avatar',
  'value', 'label', 'name', 'bio', 'image', 'socialLinks', 'logoUrl', 'logoAlt', 'featureLabel', 'checked',
]);

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

function defaultForProp(prop: string, spec: ComponentSpec): string | null {
  if (STYLE_PROPS.has(prop)) {
    if (prop === 'variant') return spec.variantTypes[0] != null ? `"${spec.variantTypes[0]}"` : 'undefined';
    if (prop === 'backgroundColor' || prop === 'textColor') return '""';
    return 'undefined';
  }
  if (prop === 'title') return spec.slug && TITLE_BY_SLUG[spec.slug] ? `"${TITLE_BY_SLUG[spec.slug]}"` : '"Section title"';
  if (prop === 'subtitle') return '""';
  if (prop === 'placeholder' || prop === 'buttonLabel' || prop === 'submitLabel') return '""';
  if (prop === 'successMessage') return '"Thanks for your message."';
  if (LIST_PROPS.has(prop)) return null;
  if (prop === 'features' || prop === 'socialLinks') return '[]';
  return '""';
}

function formatLiteral(value: string): string {
  if (value === 'undefined' || value === '[]') return value;
  return value;
}

// ---------------------------------------------------------------------------
// Code generation
// ---------------------------------------------------------------------------

function generateDefaultsTs(spec: ComponentSpec): string {
  const short = shortName(spec.componentName);
  const exportName = `${short}Defaults`;
  const lines: string[] = [];

  lines.push(`/**\n * ${short} section defaults (auto-generated).\n */\n`);
  lines.push(`export const ${exportName} = {`);

  const contentProps = spec.props.filter(
    (p) => !STYLE_PROPS.has(p) && !ITEM_ONLY_PROPS.has(p)
  );
  let needComma = false;

  for (const prop of contentProps) {
    if (LIST_PROPS.has(prop)) {
      const def = REPEATER_DEFAULTS[prop];
      if (def) {
        if (needComma) lines.push(',');
        const names = ['Basic', 'Pro', 'Enterprise', 'Starter', 'Growth', 'Scale'];
        const prices = ['$19', '$49', '$99', '$9', '$29'];
        const ctas = ['Start', 'Start', 'Contact', 'Get started', 'Choose'];
        const itemLines: string[] = [];
        for (let i = 0; i < def.itemCount; i++) {
          const itemParts = def.itemFields.map((f) => {
            let v = f.value;
            if (prop === 'plans' && f.jsKey === 'name') v = `"${names[i] ?? names[0]!}"`;
            if (prop === 'plans' && f.jsKey === 'price') v = `"${prices[i] ?? prices[0]!}"`;
            if (prop === 'plans' && f.jsKey === 'cta') v = `"${ctas[i] ?? ctas[0]!}"`;
            return `${f.jsKey}: ${v}`;
          });
          itemLines.push(`  { ${itemParts.join(', ')} }`);
        }
        lines.push(`  ${prop}: [`);
        lines.push(itemLines.join(',\n'));
        lines.push('  ]');
        needComma = true;
      } else {
        if (needComma) lines.push(',');
        lines.push(`  ${prop}: []`);
        needComma = true;
      }
      continue;
    }

    const literal = defaultForProp(prop, spec);
    if (literal != null) {
      if (needComma) lines.push(',');
      lines.push(`  ${prop}: ${formatLiteral(literal)}`);
      needComma = true;
    }
  }

  const variantDefault = spec.variantTypes[0];
  if (variantDefault && !contentProps.includes('variant')) {
    if (needComma) lines.push(',');
    lines.push(`  variant: "${variantDefault}"`);
  }

  lines.push('};');
  return lines.join('\n');
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Generates a defaults file (TS source) from a component spec.
 * Path: components/sections/{ShortName}/{ShortName}.defaults.ts
 *
 * @param spec — From generateComponentSpec (Part 2).
 * @returns filePath, source, exportName.
 */
export function generateDefaults(spec: ComponentSpec): GeneratedDefaultsOutput {
  const short = shortName(spec.componentName);
  const filePath = `components/sections/${short}/${short}.defaults.ts`;
  const source = generateDefaultsTs(spec);
  const exportName = `${short}Defaults`;
  return { filePath, source, exportName };
}
