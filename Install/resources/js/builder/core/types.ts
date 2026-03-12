/**
 * Builder core — base types and interfaces.
 * Reusable contracts for registry, schema, renderer, store, inspector, updates.
 * No UI; no dependencies on other builder modules.
 */

import type { ComponentType } from 'react';

// ---------------------------------------------------------------------------
// BuilderComponentSchema — schema for a builder component
// ---------------------------------------------------------------------------
export interface BuilderComponentSchema {
  schemaVersion?: number;
  componentKey: string;
  displayName: string;
  category: string;
  icon?: string;
  defaultProps: Record<string, unknown>;
  fields: BuilderFieldDefinition[];
  editableFields?: string[];
  contentGroups?: BuilderFieldGroupDefinition[];
  styleGroups?: BuilderFieldGroupDefinition[];
  advancedGroups?: BuilderFieldGroupDefinition[];
  variants?: { layout?: string[]; style?: string[] };
  variantDefinitions?: BuilderVariantDefinition[];
  responsive?: boolean;
  responsiveSupport?: BuilderResponsiveSupport;
  chatTargets?: string[];
  bindingFields?: string[];
  projectTypes?: string[];
  serializable?: boolean;
  metadata?: Record<string, unknown>;
}

// ---------------------------------------------------------------------------
// BuilderFieldDefinition — single field in a component schema
// ---------------------------------------------------------------------------
export interface BuilderFieldDefinition {
  path: string;
  label: string;
  type: string;
  group: string;
  default?: unknown;
  options?: BuilderFieldOption[];
  placeholder?: string;
  description?: string;
  min?: number;
  max?: number;
  step?: number;
  units?: string[];
  accepts?: string[];
  itemFields?: BuilderFieldDefinition[];
  responsive?: boolean;
  chatEditable?: boolean;
  bindingCompatible?: boolean;
}

export interface BuilderFieldOption {
  value: string;
  label?: string;
}

export interface BuilderFieldGroupDefinition {
  key: string;
  label: string;
  description?: string;
  fields: string[];
}

export interface BuilderVariantDefinition {
  kind: 'layout' | 'style';
  label: string;
  options: BuilderFieldOption[];
  default?: string;
}

export interface BuilderResponsiveSupport {
  enabled: boolean;
  breakpoints: string[];
  supportsVisibility: boolean;
  supportsResponsiveOverrides: boolean;
}

// ---------------------------------------------------------------------------
// BuilderComponentDefaults — defaults for a component (serializable)
// ---------------------------------------------------------------------------
export type BuilderComponentDefaults = Record<string, unknown>;

// ---------------------------------------------------------------------------
// BuilderComponentVariant — variant option (id + label)
// ---------------------------------------------------------------------------
export interface BuilderComponentVariant {
  value: string;
  label?: string;
}

// ---------------------------------------------------------------------------
// BuilderComponentRegistryEntry — registry entry (component + schema + defaults)
// ---------------------------------------------------------------------------
export interface BuilderComponentRegistryEntry<P = Record<string, unknown>> {
  component: ComponentType<P>;
  schema: BuilderComponentSchema;
  defaults: BuilderComponentDefaults;
  mapBuilderProps?: (builderProps: Record<string, unknown>) => P;
}

// ---------------------------------------------------------------------------
// BuilderComponentInstance — serializable component instance (tree node)
// ---------------------------------------------------------------------------
export interface BuilderComponentInstance {
  id: string;
  componentKey: string;
  variant?: string;
  props: Record<string, unknown>;
  children?: BuilderComponentInstance[];
  responsive?: Record<string, Record<string, unknown>>;
  responsiveOverrides?: Record<string, Record<string, unknown>>;
  metadata?: Record<string, unknown>;
}

// ---------------------------------------------------------------------------
// ResponsiveValue<T> — value with optional per-breakpoint overrides
// ---------------------------------------------------------------------------
export interface ResponsiveValue<T = unknown> {
  base?: T;
  desktop?: T;
  tablet?: T;
  mobile?: T;
  [breakpoint: string]: T | undefined;
}

// ---------------------------------------------------------------------------
// BuilderUpdatePayload — payload for the update pipeline (single op or batch)
// ---------------------------------------------------------------------------
export type BuilderUpdatePayload =
  | BuilderUpdateOperation
  | { operations: BuilderUpdateOperation[] };

export interface BuilderUpdateOperation {
  kind: string;
  source?: string;
  sectionLocalId?: string;
  path?: string | string[];
  value?: unknown;
  patch?: Record<string, unknown>;
  sectionType?: string;
  afterSectionId?: string | null;
  toIndex?: number;
  [key: string]: unknown;
}
