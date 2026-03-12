/**
 * Part 3 — React Component Generator.
 *
 * Generates React (TSX) component source code from a ComponentSpec.
 * Output follows builder conventions: props-driven, variant-ready, semantic HTML.
 */

import type { ComponentSpec } from './componentSpecGenerator';

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface GeneratedComponentOutput {
  /** Suggested path (e.g. components/sections/Pricing/Pricing.tsx). */
  filePath: string;
  /** Full TSX source code. */
  source: string;
  /** Component name (e.g. PricingSection). */
  componentName: string;
}

export interface GenerateComponentCodeOptions {
  /** When true, emit import from ./ShortName.variants and use DEFAULT_VARIANT constant (folder architecture). */
  useVariantsImport?: boolean;
}

/** Known repeater prop names → item interface name and item field props. */
const REPEATER_PROPS: Record<string, { itemInterface: string; itemFields: string[] }> = {
  plans: { itemInterface: 'Plan', itemFields: ['planName', 'price', 'features', 'ctaButton'] },
  items: { itemInterface: 'Item', itemFields: ['question', 'answer', 'quote', 'author', 'role', 'avatar', 'value', 'label'] },
  members: { itemInterface: 'Member', itemFields: ['name', 'role', 'bio', 'image', 'socialLinks'] },
  logos: { itemInterface: 'Logo', itemFields: ['logoUrl', 'logoAlt'] },
};

/** Props that are style/layout only (not content). */
const STYLE_PROPS = new Set(['variant', 'backgroundColor', 'textColor', 'className', 'padding', 'spacing']);

/** Map spec prop name to common JSX/field name (e.g. planName → name, ctaButton → cta). */
const PROP_TO_FIELD: Record<string, string> = {
  planName: 'name',
  ctaButton: 'cta',
  logoUrl: 'url',
  logoAlt: 'alt',
};

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

function getRepeaterProp(spec: ComponentSpec): { key: string; itemInterface: string; itemFields: string[] } | null {
  for (const prop of spec.props) {
    const r = REPEATER_PROPS[prop];
    if (r) {
      const itemFields = r.itemFields.filter((f) => spec.props.includes(f));
      return { key: prop, itemInterface: r.itemInterface, itemFields: itemFields.length ? itemFields : r.itemFields };
    }
  }
  return null;
}

function tsTypeForProp(prop: string, spec: ComponentSpec): string {
  if (STYLE_PROPS.has(prop)) return 'string | undefined';
  if (prop === 'title' || prop === 'subtitle' || prop === 'placeholder' || prop === 'buttonLabel' || prop === 'submitLabel' || prop === 'successMessage') return 'string | undefined';
  if (prop === 'plans') return 'Plan[]';
  if (prop === 'items') return 'Item[]';
  if (prop === 'members') return 'Member[]';
  if (prop === 'logos') return 'Logo[]';
  if (prop === 'fields') return 'Record<string, unknown>[]';
  return 'string | undefined';
}

function jsxFieldName(prop: string): string {
  return PROP_TO_FIELD[prop] ?? prop;
}

function buildItemInterface(spec: ComponentSpec, repeater: { itemInterface: string; itemFields: string[] }): string {
  const lines = repeater.itemFields.map((f) => {
    const field = jsxFieldName(f);
    const type = f === 'features' ? 'string[]' : f === 'socialLinks' ? 'Record<string, string>[]' : 'string';
    return `  ${field}?: ${type}`;
  });
  return `export interface ${repeater.itemInterface} {\n${lines.join('\n')}\n}`;
}

function buildPropsInterface(
  spec: ComponentSpec,
  repeater: { key: string; itemInterface: string } | null,
  exportName: string
): string {
  const contentProps = spec.props.filter((p) => !STYLE_PROPS.has(p));
  const lines = contentProps.map((p) => `  ${p}?: ${tsTypeForProp(p, spec)}`);
  if (!lines.some((l) => l.startsWith('  className'))) lines.push('  className?: string');
  return `export interface ${exportName}Props {\n${lines.join('\n')}\n}`;
}


function buildGridContent(spec: ComponentSpec, repeater: { key: string; itemInterface: string; itemFields: string[] }): string {
  const itemVar = repeater.key === 'plans' ? 'plan' : repeater.key === 'members' ? 'member' : 'item';
  const arr = `props.${repeater.key} ?? []`;
  const fields = repeater.itemFields.filter((f) => !STYLE_PROPS.has(f));
  const firstField = jsxFieldName(fields[0] ?? 'title');
  const cells = fields.slice(1).map((f) => {
    const field = jsxFieldName(f);
    if (f === 'features') return `<ul>{(${itemVar}.features ?? []).map((feat, i) => <li key={i}>{feat}</li>)}</ul>`;
    return `<p>{${itemVar}.${field} ?? ''}</p>`;
  });
  const cta = fields.some((f) => f === 'ctaButton') ? `\n        <button>{${itemVar}.cta ?? 'Choose'}</button>` : '';
  const gridClass = `${shortName(spec.componentName).toLowerCase()}-grid`;
  return `<div className="${gridClass}">
    {${arr}.map((${itemVar}, index) => (
      <div key={index} className="${repeater.itemInterface.toLowerCase()}">
        <h3>{${itemVar}.${firstField} ?? ''}</h3>
        ${cells.join('\n        ')}
        ${cta}
      </div>
    ))}
  </div>`;
}

function buildAccordionContent(spec: ComponentSpec, repeater: { key: string; itemFields: string[] }): string {
  const itemVar = 'item';
  const arr = `props.${repeater.key} ?? []`;
  return `<div className="faq-accordion">
    {${arr}.map((${itemVar}, index) => (
      <details key={index} className="faq-item">
        <summary>{${itemVar}.question ?? ${itemVar}.answer ?? 'Item'}</summary>
        <p>{${itemVar}.answer ?? ${itemVar}.question ?? ''}</p>
      </details>
    ))}
  </div>`;
}

function buildDefaultGridContent(spec: ComponentSpec, repeater: { key: string }): string {
  const arr = `props.${repeater.key} ?? []`;
  return `<div className="${shortName(spec.componentName).toLowerCase()}-grid">
    {${arr}.map((item, index) => (
      <div key={index} className="item">{/* render item */}</div>
    ))}
  </div>`;
}

// ---------------------------------------------------------------------------
// Code generation
// ---------------------------------------------------------------------------

function generateTsx(spec: ComponentSpec, useVariantsImport?: boolean): string {
  const repeater = getRepeaterProp(spec);
  const name = spec.componentName;
  const short = shortName(name);

  const parts: string[] = [];

  parts.push(`/**
 * ${name} — builder section (auto-generated).
 * Props-driven; content from props. Variant-ready for layout/style.
 */\n`);

  if (useVariantsImport) {
    const upper = toUpperSnake(short);
    const variantIdType = `${short}VariantId`;
    parts.push(`import { ${upper}_DEFAULT_VARIANT, type ${variantIdType} } from './${short}.variants';\n\n`);
  }

  const exportName = short;
  if (repeater) {
    parts.push(buildItemInterface(spec, { itemInterface: repeater.itemInterface, itemFields: repeater.itemFields }));
    parts.push('\n');
  }

  parts.push(buildPropsInterface(spec, repeater, exportName));
  parts.push('\n');

  const defaultVariant = spec.variantTypes[0] ?? 'default';
  const sectionClass = `${short.toLowerCase()}-section`;
  const variantExpr = useVariantsImport
    ? `(props.variant ?? ${toUpperSnake(short)}_DEFAULT_VARIANT)`
    : `(props.variant ?? "${defaultVariant}")`;
  const classNameExpr = `["section", "${sectionClass}", ${variantExpr}, props.className].filter(Boolean).join(" ")`;

  let body = '';
  if (repeater && spec.layoutType === 'grid') {
    body = `
  return (
    <section className={${classNameExpr}} style={props.backgroundColor ? { backgroundColor: props.backgroundColor } : undefined}>
      {props.title != null && <h2>{props.title}</h2>}
      ${buildGridContent(spec, repeater)}
    </section>
  );`;
  } else if (repeater && spec.layoutType === 'accordion') {
    body = `
  return (
    <section className={${classNameExpr}} style={props.backgroundColor ? { backgroundColor: props.backgroundColor } : undefined}>
      {props.title != null && <h2>{props.title}</h2>}
      {props.subtitle != null && <p>{props.subtitle}</p>}
      ${buildAccordionContent(spec, repeater)}
    </section>
  );`;
  } else if (repeater) {
    body = `
  return (
    <section className={${classNameExpr}} style={props.backgroundColor ? { backgroundColor: props.backgroundColor } : undefined}>
      {props.title != null && <h2>{props.title}</h2>}
      ${buildDefaultGridContent(spec, repeater)}
    </section>
  );`;
  } else {
    body = `
  return (
    <section className={${classNameExpr}} style={props.backgroundColor ? { backgroundColor: props.backgroundColor } : undefined}>
      {props.title != null && <h2>{props.title}</h2>}
      {props.subtitle != null && <p>{props.subtitle}</p>}
    </section>
  );`;
  }

  parts.push(`export default function ${exportName}(props: ${exportName}Props) {${body}\n}\n`);

  return parts.join('');
}

function normalizeGeneratedSource(raw: string): string {
  return raw;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Generates React (TSX) component source code from a component spec.
 * Output path follows builder convention: components/sections/{ShortName}/{ShortName}.tsx.
 *
 * @param spec — From generateComponentSpec (Part 2).
 * @param options — useVariantsImport: emit import from ./ShortName.variants (for folder layout).
 * @returns filePath, source, componentName.
 */
export function generateComponentCode(
  spec: ComponentSpec,
  options?: GenerateComponentCodeOptions
): GeneratedComponentOutput {
  const short = shortName(spec.componentName);
  const filePath = `components/sections/${short}/${short}.tsx`;
  const rawSource = generateTsx(spec, options?.useVariantsImport);
  const source = normalizeGeneratedSource(rawSource);
  return { filePath, source, componentName: spec.componentName };
}
