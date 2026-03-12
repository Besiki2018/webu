/**
 * Builder field type system — schema-driven field definitions.
 * Supported types and groups for inspector controls and validation.
 */

// ---------------------------------------------------------------------------
// Supported field types
// ---------------------------------------------------------------------------
export const BUILDER_FIELD_TYPES = [
  'text',
  'textarea',
  'number',
  'color',
  'image',
  'icon',
  'link',
  'menu',
  'select',
  'toggle',
  'spacing',
  'alignment',
  'grid',
  'repeater',
] as const;

export type BuilderFieldType = (typeof BUILDER_FIELD_TYPES)[number];

export function isBuilderFieldType(value: string): value is BuilderFieldType {
  return BUILDER_FIELD_TYPES.includes(value as BuilderFieldType);
}

// ---------------------------------------------------------------------------
// Field groups (sidebar grouping)
// ---------------------------------------------------------------------------
export const BUILDER_FIELD_GROUPS = [
  'content',
  'style',
  'advanced',
  'responsive',
  'state',
] as const;

export type BuilderFieldGroup = (typeof BUILDER_FIELD_GROUPS)[number];

export function isBuilderFieldGroup(value: string): value is BuilderFieldGroup {
  return BUILDER_FIELD_GROUPS.includes(value as BuilderFieldGroup);
}

// ---------------------------------------------------------------------------
// BuilderFieldDefinition — schema-driven field definition
// ---------------------------------------------------------------------------
export interface BuilderFieldDefinition {
  /** Field key (prop path or identifier) */
  key: string;
  /** Field type; use BuilderFieldType for schema-driven controls */
  type: string;
  label?: string;
  default?: unknown;
  /** Options for select/menu; labels optional via BuilderFieldOption[] */
  options?: string[] | BuilderFieldOption[];
  /** Group for sidebar tab / section */
  group?: BuilderFieldGroup;
  placeholder?: string;
  description?: string;
  /** For number: min, max, step, units */
  min?: number;
  max?: number;
  step?: number;
  units?: string[];
  /** For image/icon: accepted formats or keys */
  accepts?: string[];
  /** For repeater/grid: nested field definitions */
  itemFields?: BuilderFieldDefinition[];
  /** Whether this field supports responsive overrides */
  responsive?: boolean;
  /** Whether chat/AI can edit this field */
  chatEditable?: boolean;
}

export interface BuilderFieldOption {
  value: string;
  label?: string;
}
