/**
 * Converts builder sections into deterministic export-ready TSX code.
 * The output is driven by the centralized component registry so future
 * page export can be reproduced directly from builder data.
 */

import {
  getComponentCodegenMetadata,
  getComponentSchema,
  getDefaultProps,
  resolveComponentProps,
} from '@/builder/componentRegistry';
import { getDesignTokensAsCssVars } from '@/builder/designTokens';

import type {
  CodeGeneratorOptions,
  GeneratedPageCodeOptions,
  SectionDraftLike,
} from './types';
import { getSectionTagName } from './sectionTagMap';

interface NormalizedCodegenSection {
  id: string;
  type: string;
  props: Record<string, unknown>;
}

function isPlainObject(value: unknown): value is Record<string, unknown> {
  return !!value && typeof value === 'object' && !Array.isArray(value);
}

function parseProps(
  input: Pick<SectionDraftLike, 'type' | 'props' | 'propsText'>,
  options: { resolveDefaults?: boolean } = {}
): Record<string, unknown> {
  const rawProps = (() => {
    if (input.props && typeof input.props === 'object' && !Array.isArray(input.props)) {
      return input.props;
    }

    const propsText = input.propsText;
    if (!propsText || propsText.trim() === '') return {};
    try {
      const parsed = JSON.parse(propsText);
      return typeof parsed === 'object' && parsed !== null ? parsed : {};
    } catch {
      return {};
    }
  })();

  if (options.resolveDefaults !== true) {
    return rawProps;
  }

  const codegenMeta = getComponentCodegenMetadata(input.type);
  if (!codegenMeta) {
    return rawProps;
  }

  return resolveComponentProps(input.type, rawProps);
}

function normalizeSections(sections: SectionDraftLike[]): NormalizedCodegenSection[] {
  return sections.map((section, index) => ({
    id: section.localId?.trim() || `section-${index + 1}`,
    type: section.type.trim(),
    props: parseProps(section, { resolveDefaults: true }),
  }));
}

function sortObjectKeys(value: unknown): unknown {
  if (Array.isArray(value)) {
    return value.map((entry) => sortObjectKeys(entry));
  }

  if (!isPlainObject(value)) {
    return value;
  }

  return Object.keys(value)
    .sort((left, right) => left.localeCompare(right))
    .reduce<Record<string, unknown>>((result, key) => {
      result[key] = sortObjectKeys(value[key]);
      return result;
    }, {});
}

function stableJson(value: unknown): string {
  return JSON.stringify(sortObjectKeys(value), null, 2);
}

function propsToJsxAttributes(
  props: Record<string, unknown>,
  options: CodeGeneratorOptions
): string[] {
  const keysToEmit = options.propKeysToEmit ?? Object.keys(props);
  const omitEmpty = options.omitEmptyProps !== false;
  const parts: string[] = [];

  for (const key of keysToEmit) {
    if (!(key in props)) continue;
    const value = props[key];
    if (value === undefined) continue;
    if (omitEmpty && (value === '' || value === null)) continue;
    const attribute = propToJsxAttribute(key, value);
    if (attribute !== null) {
      parts.push(attribute);
    }
  }

  return parts;
}

function propToJsxAttribute(key: string, value: unknown): string | null {
  if (typeof value === 'string') {
    const escaped = value
      .replace(/\\/g, '\\\\')
      .replace(/"/g, '\\"')
      .replace(/\r/g, '\\r')
      .replace(/\n/g, '\\n');
    return `${key}="${escaped}"`;
  }

  const serialized = JSON.stringify(value, null, 2);
  if (serialized === undefined) {
    return null;
  }

  if (!serialized.includes('\n')) {
    return `${key}={${serialized}}`;
  }

  const indented = serialized
    .split('\n')
    .map((line, index) => (index === 0 ? line : `  ${line}`))
    .join('\n');

  return `${key}={${indented}}`;
}

function renderSectionTag(tagName: string, attributes: string[]): string {
  if (attributes.length === 0) {
    return `<${tagName} />`;
  }

  const inline = `<${tagName} ${attributes.join(' ')} />`;
  const hasMultilineAttributes = attributes.some((attribute) => attribute.includes('\n'));

  if (!hasMultilineAttributes && inline.length <= 120) {
    return inline;
  }

  return [
    `<${tagName}`,
    ...attributes.flatMap((attribute) => attribute.split('\n').map((line) => `  ${line}`)),
    '/>',
  ].join('\n');
}

function renderImportLines(
  sections: NormalizedCodegenSection[],
  componentImportPrefix?: string | null
): string[] {
  const imports = new Map<string, { importName: string; importPath: string }>();

  sections.forEach((section) => {
    const codegen = getComponentCodegenMetadata(section.type);
    if (!codegen?.importPath) {
      return;
    }
    const importPath = componentImportPrefix != null
      ? `${componentImportPrefix.replace(/\/$/, '')}/${codegen.importName}`
      : codegen.importPath;
    imports.set(codegen.importName, {
      importName: codegen.importName,
      importPath,
    });
  });

  return Array.from(imports.values())
    .sort((left, right) => left.importName.localeCompare(right.importName) || left.importPath.localeCompare(right.importPath))
    .map(({ importName, importPath }) => `import ${importName} from '${importPath}';`);
}

function renderSectionComponentMap(sections: NormalizedCodegenSection[]): string {
  const entries = new Map<string, string>();
  sections.forEach((section) => {
    const codegen = getComponentCodegenMetadata(section.type);
    if (!codegen?.importPath) {
      return;
    }

    entries.set(section.type, `  ${JSON.stringify(section.type)}: ${codegen.importName},`);
  });
  const lines = Array.from(entries.values());

  if (lines.length === 0) {
    return 'const sectionComponents = {} as const;';
  }

  return ['const sectionComponents = {', ...lines, '} as const;'].join('\n');
}

function renderPageData(
  pageName: string,
  revisionSource: string | null | undefined,
  sections: NormalizedCodegenSection[]
): string {
  const payload = {
    pageName,
    revisionSource: revisionSource ?? null,
    sections: sections.map((section) => ({
      id: section.id,
      type: section.type,
      props: section.props,
    })),
  };

  return `const pageData = ${stableJson(payload)} as const;`;
}

function renderUnsupportedSectionComponent(): string {
  return `type PageSection = (typeof pageData.sections)[number];

function UnsupportedSection({ section }: { section: PageSection }) {
  return (
    <section
      data-webu-unsupported-section={section.type}
      className="rounded-2xl border border-dashed border-amber-300 bg-amber-50/80 px-6 py-8 text-left text-amber-900"
    >
      <p className="text-sm font-semibold">Unsupported builder section</p>
      <p className="mt-2 text-sm">{section.type}</p>
    </section>
  );
}`;
}

function renderPageBody(sections: NormalizedCodegenSection[], pageName: string): string {
  if (sections.length === 0) {
    return `      <div className="flex min-h-screen flex-col items-center justify-center px-4 text-center">
        <h1 className="mb-4 text-4xl font-bold text-foreground">${pageName}</h1>
        <p className="text-lg text-muted-foreground">
          No builder sections found for this page yet.
        </p>
      </div>`;
  }

  return `      {pageData.sections.map((section) => {
        const SectionComponent = sectionComponents[section.type as keyof typeof sectionComponents];

        if (!SectionComponent) {
          return <UnsupportedSection key={section.id} section={section} />;
        }

        return (
          <div
            key={section.id}
            data-webu-section={section.type}
            data-webu-section-local-id={section.id}
            className="webu-page-section"
          >
            <SectionComponent {...section.props} />
          </div>
        );
      })}`;
}

/**
 * Generate React-like JSX code from an array of section drafts.
 */
export function sectionsDraftToCode(
  sections: SectionDraftLike[],
  options: CodeGeneratorOptions = {}
): string {
  const tagMap = options.sectionTagMap;
  const lines: string[] = [];

  for (const section of sections) {
    const tagName = tagMap?.[section.type.trim().toLowerCase()] ?? getSectionTagName(section.type);
    const props = parseProps(section);
    const attrs = propsToJsxAttributes(props, options);
    lines.push(renderSectionTag(tagName, attrs));
  }

  return lines.join('\n');
}

export function buildPageComponentCode(
  sections: SectionDraftLike[],
  options: GeneratedPageCodeOptions = {}
): string {
  const pageName = options.pageName?.trim() || 'Current page';
  const revisionSource = options.revisionSource?.trim() || null;
  const normalizedSections = normalizeSections(sections);
  const componentImportPrefix = options.componentImportPrefix ?? null;
  const importLines = options.includeImports === false
    ? []
    : renderImportLines(normalizedSections, componentImportPrefix);
  const pageDataLine = options.includePageData === false
    ? null
    : renderPageData(pageName, revisionSource, normalizedSections);
  const revisionSourceSuffix = revisionSource ? ` (${revisionSource} revision)` : '';

  return [
    '/**',
    ` * ${pageName} component.`,
    ` * Generated from explicit builder page data${revisionSourceSuffix}.`,
    ' */',
    '',
    "import React from 'react';",
    ...importLines,
    '',
    ...(pageDataLine ? [pageDataLine, ''] : []),
    renderSectionComponentMap(normalizedSections),
    '',
    renderUnsupportedSectionComponent(),
    '',
    'export default function Page() {',
    '  return (',
    '    <main className="min-h-screen bg-background">',
    renderPageBody(normalizedSections, pageName),
    '    </main>',
    '  );',
    '}',
    '',
  ].join('\n');
}

/**
 * Generates full TSX source for a single component so the agent can edit it.
 * Uses schema and defaultProps from the registry; output is self-contained and editable.
 */
export function buildFullComponentSource(registryId: string): string {
  const codegen = getComponentCodegenMetadata(registryId);
  const schema = getComponentSchema(registryId);
  const defaultProps = getDefaultProps(registryId);
  if (!codegen?.importName || !schema) {
    return `/** No schema for ${registryId} */\nexport default function Placeholder() { return <section data-section="${registryId}">Unsupported</section>; }\n`;
  }

  const name = codegen.importName;
  const propKeys = Object.keys(defaultProps);
  const extraKeys = ['backgroundColor', 'textColor', 'ctaUrl', 'buttonLink'];
  const sortedKeys = [...new Set([...propKeys, ...(schema.fields?.map((f) => f.path) ?? []), ...extraKeys])].filter(Boolean).sort();
  const interfaceLines = sortedKeys.map((k) => `  ${k}?: unknown;`);
  const destructureDefaults = sortedKeys.length === 0
    ? '    ...rest'
    : sortedKeys
        .map((k) => {
          const v = defaultProps[k];
          const str = typeof v === 'string' ? `'${v.replace(/'/g, "\\'")}'` : JSON.stringify(v);
          return `    ${k} = ${str},`;
        })
        .join('\n');
  const urlFieldKeys = new Set(['ctaUrl', 'buttonLink', 'url', 'link', 'href']);
  const semanticMap: Record<string, string> = {
    title: 'h1',
    headline: 'h1',
    subheading: 'h2',
    subtitle: 'p',
    description: 'p',
    eyebrow: 'p',
    badgeText: 'span',
    ctaLabel: 'span',
    buttonText: 'span',
    body: 'div',
  };
  const bodyLines: string[] = [];
  sortedKeys.forEach((key) => {
    if (urlFieldKeys.has(key)) return;
    if (key === 'image') {
      bodyLines.push(`        {${key} && <img src={typeof ${key} === 'string' ? ${key} : (${key} as { url?: string })?.url ?? ''} alt="" data-webu-field="image" className="section-image max-h-48 object-cover rounded-lg" />}`);
      return;
    }
    const tag = semanticMap[key] ?? 'div';
    bodyLines.push(`        {${key} != null && ${key} !== '' && <${tag} data-webu-field="${key}" className="section-${key}">{String(${key})}</${tag}>}`);
  });
  if (bodyLines.length === 0) {
    bodyLines.push('        <div className="section-content">Content</div>');
  }

  const hasCta = sortedKeys.some((k) => ['ctaLabel', 'buttonText', 'ctaUrl', 'buttonLink'].includes(k));
  const styleLine = `style={{ backgroundColor: backgroundColor ?? 'var(--color-background)', color: textColor ?? 'var(--color-foreground)', padding: 'var(--spacing-lg)', borderRadius: 'var(--radius-md)' }}`;
  const sectionOpen = `    <section data-webu-section="${registryId}" className="min-h-[200px]" ${styleLine}>`;
  const ctaLines: string[] = hasCta
    ? [
        '',
        '      {/* Primary CTA — visual builder editable via data-webu-field / data-webu-field-url */}',
        '      {(ctaLabel ?? buttonText) && (ctaUrl ?? buttonLink) && (',
        '        <a href={ctaUrl ?? buttonLink ?? "#"} data-webu-field-url="ctaUrl" className="webu-hero__cta webu-hero__cta--primary" style={{ background: "var(--color-primary)", color: "var(--color-primary-foreground)", borderRadius: "var(--radius-md)", padding: "var(--spacing-sm) var(--spacing-md)" }}>',
        '          <span data-webu-field="ctaLabel">{String(ctaLabel ?? buttonText ?? "")}</span>',
        '        </a>',
        '      )}',
      ]
    : [];

  return [
    '/**',
    ` * ${schema.displayName ?? name} — full source for agent editing.`,
    ` * Registry: ${registryId}. Styles use design tokens (var(--color-*), var(--spacing-*)); edit src/theme/designTokens.css to change design.`,
    ' */',
    '',
    "import React from 'react';",
    '',
    'export interface ' + name + 'Props {',
    ...interfaceLines,
    '}',
    '',
    `export default function ${name}(props: ${name}Props) {`,
    '  const {',
    destructureDefaults || '    ...rest',
    '  } = props;',
    '',
    '  return (',
    sectionOpen,
    '      <div className="max-w-4xl mx-auto space-y-4" style={{ display: "flex", flexDirection: "column", gap: "var(--spacing-md)" }}>',
    ...bodyLines,
    ...ctaLines,
    '      </div>',
    '    </section>',
    '  );',
    '}',
    '',
  ].join('\n');
}

/**
 * Builds full design tokens as a single CSS file so the agent can view and edit colors, spacing, radius, shadows, typography.
 * Used as a virtual file in the project code (e.g. src/theme/designTokens.css).
 */
export function buildDesignTokensFileContent(): string {
  const vars = getDesignTokensAsCssVars();
  const lines = [
    '/**',
    ' * Design system tokens — editable by agent.',
    ' * Change values below to update colors, spacing, radius, shadows, typography across the site.',
    ' */',
    '',
    ':root {',
    ...Object.entries(vars).map(([key, value]) => `  ${key}: ${value};`),
    '}',
    '',
    '/* Button and section defaults using tokens */',
    '[data-webu-section] {',
    '  background-color: var(--color-background);',
    '  color: var(--color-foreground);',
    '}',
    '.webu-hero__cta--primary, .webu-hero__cta--editorial {',
    '  background: var(--color-primary);',
    '  color: var(--color-primary-foreground);',
    '  border-radius: var(--radius-md);',
    '  padding: var(--spacing-sm) var(--spacing-md);',
    '}',
    '',
  ];
  return lines.join('\n');
}
