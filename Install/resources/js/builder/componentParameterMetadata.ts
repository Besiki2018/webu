/**
 * Component Parameter Metadata — maps section keys to display names and editable fields.
 * Used by DOM Mapper, element selection, and AI chat to resolve ComponentName.parameterName.
 */

import { getComponent, getComponentSchema, getEditableFieldPaths } from './componentRegistry';

export type ParameterKind =
  | 'text'
  | 'image'
  | 'menu'
  | 'link'
  | 'icon'
  | 'boolean'
  | 'color'
  | 'number'
  | 'richtext'
  | 'url'
  | 'collection'
  | 'video'
  | 'repeater'
  | 'button-group'
  | 'alignment'
  | 'spacing'
  | 'radius'
  | 'shadow'
  | 'visibility'
  | 'typography'
  | 'width'
  | 'height'
  | 'overlay';

export interface EditableFieldMeta {
  parameterName: string;
  kind: ParameterKind;
  title?: string;
}

export interface ComponentParameterMeta {
  /** Registry section key (e.g. webu_general_hero_01) */
  sectionKey: string;
  /** Display name for chat/sidebar (e.g. Hero Section) */
  displayName: string;
  /** Short name for element id (e.g. HeroSection) */
  shortName: string;
  fields: EditableFieldMeta[];
}

const PARAM_TYPE_TO_KIND: Record<string, ParameterKind> = {
  string: 'text',
  number: 'number',
  boolean: 'boolean',
  image: 'image',
  url: 'link',
  richtext: 'richtext',
  collection: 'menu',
  text: 'text',
  link: 'link',
  icon: 'icon',
  color: 'color',
  menu: 'menu',
  select: 'text',
  video: 'video',
  repeater: 'repeater',
  'button-group': 'button-group',
  alignment: 'alignment',
  spacing: 'spacing',
  radius: 'radius',
  shadow: 'shadow',
  visibility: 'visibility',
  typography: 'typography',
  width: 'width',
  height: 'height',
  overlay: 'overlay',
  'layout-variant': 'text',
  'style-variant': 'text',
};

/**
 * Get component display name suitable for element id (e.g. HeroSection).
 */
export function getComponentShortName(sectionKey: string): string {
  const def = getComponent(sectionKey);
  if (!def) return sectionKey.replace(/^webu_|_\d{2}$/g, '').replace(/_/g, '') || 'Section';
  const name = def.name.replace(/\s+Section\s*$/i, '').trim() || def.name;
  const words = name.split(/\s+/).filter(Boolean);
  const pascal = words.map((w) => w.charAt(0).toUpperCase() + w.slice(1).toLowerCase()).join('');
  return pascal + 'Section';
}

/**
 * Build full parameter metadata for a section key.
 */
export function getComponentParameterMeta(sectionKey: string): ComponentParameterMeta | null {
  const def = getComponent(sectionKey);
  if (!def) return null;
  const schema = getComponentSchema(sectionKey);
  const paramNames = getEditableFieldPaths(sectionKey);
  const shortName = getComponentShortName(sectionKey);
  const fields: EditableFieldMeta[] = paramNames.map((paramName) => {
    const schemaField = schema?.fields.find((field) => field.path === paramName);
    const parameterSchema = def.parameters[paramName];
    const kind = schemaField
      ? (PARAM_TYPE_TO_KIND[schemaField.type] ?? 'text')
      : parameterSchema
        ? (PARAM_TYPE_TO_KIND[parameterSchema.type] ?? 'text')
        : 'text';
    return {
      parameterName: paramName,
      kind,
      title: schemaField?.label ?? parameterSchema?.title,
    };
  });
  return {
    sectionKey: def.id,
    displayName: def.name,
    shortName,
    fields,
  };
}

/**
 * Resolve element identifier "ComponentName.parameterName" to section key + parameter path.
 * Used when AI returns "change HeroSection.title" to find sectionId and path for updateText.
 */
export function resolveElementIdToParameter(
  elementId: string,
  sections: Array<{ id?: string; type: string }>
): { sectionId: string; path: string } | null {
  const dot = elementId.indexOf('.');
  if (dot <= 0) return null;
  const shortName = elementId.slice(0, dot).trim();
  const paramPath = elementId.slice(dot + 1).trim();
  if (!paramPath) return null;
  for (const section of sections) {
    const meta = getComponentParameterMeta(section.type);
    if (!meta) continue;
    if (meta.shortName !== shortName) continue;
    const hasParam = meta.fields.some((f) => (
      f.parameterName === paramPath
      || paramPath.startsWith(`${f.parameterName}.`)
    ));
    if (hasParam && section.id) {
      return { sectionId: section.id, path: paramPath };
    }
  }
  return null;
}

/**
 * Build element id string for chat/sidebar (e.g. "HeroSection.title").
 */
export function buildElementId(sectionKey: string, parameterName: string): string {
  const shortName = getComponentShortName(sectionKey);
  return `${shortName}.${parameterName}`;
}

export const componentParameterMetadata = {
  getComponentShortName,
  getComponentParameterMeta,
  resolveElementIdToParameter,
  buildElementId,
};
