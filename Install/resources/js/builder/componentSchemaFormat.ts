/**
 * Canonical component schema format.
 * Each builder component schema file exports a schema that describes:
 * - component name, category
 * - editable fields (props with type and options)
 * - variants, defaults, responsive support, style groups
 * - projectTypes: which project types can use this component (for component library filtering)
 * - capabilities: functional tags (e.g. navigation, search, cart, product) so AI understands component purpose
 */

import type { ProjectType } from './projectTypes';

export type PropType =
  | 'text'
  | 'richtext'
  | 'image'
  | 'video'
  | 'menu'
  | 'link'
  | 'color'
  | 'number'
  | 'boolean'
  | 'select'
  | 'alignment'
  | 'spacing'
  | 'repeater';

export interface PropOption {
  label: string;
  value: string;
}

export interface PropDefItemField {
  path: string;
  type: string;
  label?: string;
  default?: unknown;
}

export interface PropDef {
  type: PropType;
  label?: string;
  default?: unknown;
  options?: PropOption[];
  placeholder?: string;
  group?: 'content' | 'layout' | 'style' | 'advanced';
  /** For repeater/menu: nested field definitions */
  itemFields?: PropDefItemField[];
}

export interface StyleGroupDef {
  key: string;
  label: string;
  description?: string;
  fields: string[];
}

/** Breakpoints for responsive overrides (padding, margin, fontSize, gridColumns, visibility). */
export type ResponsiveBreakpoint = 'desktop' | 'tablet' | 'mobile';

/** Prop keys that can have per-breakpoint overrides (responsive.{breakpoint}.{key}). */
export type ResponsivePropKey = 'padding' | 'margin' | 'fontSize' | 'gridColumns' | 'visibility';

export interface ResponsiveSupportDef {
  enabled: boolean;
  breakpoints?: ResponsiveBreakpoint[];
  supportsVisibility?: boolean;
  supportsOverrides?: boolean;
  /** Which base props support responsive overrides; defaults to padding, margin, fontSize, gridColumns, visibility. */
  responsivePropKeys?: ResponsivePropKey[];
}

export interface ComponentSchemaDef {
  /** Component display name */
  name: string;
  /** Builder category: layout | header | footer | sections | content | etc. */
  category: string;
  /** Registry / codegen key (e.g. webu_header_01) */
  componentKey?: string;
  /** Icon identifier for builder UI */
  icon?: string;
  /** Editable fields: prop path -> definition */
  props: Record<string, PropDef>;
  /** Layout and style variant options */
  variants?: {
    layout?: string[];
    style?: string[];
    design?: string[];
  };
  /** Default values for each prop */
  defaults: Record<string, unknown>;
  /** Editable field paths (for chat targeting); defaults to all prop keys */
  editableFields?: string[];
  /** Responsive behavior */
  responsiveSupport: ResponsiveSupportDef;
  /** Grouping of style-related props for sidebar */
  styleGroups?: StyleGroupDef[];
  /** Grouping of content props */
  contentGroups?: StyleGroupDef[];
  /** Grouping of advanced props */
  advancedGroups?: StyleGroupDef[];
  /** Project types this component is compatible with (e.g. ["business", "saas", "landing"]). Omit or empty = show for all. */
  projectTypes?: ProjectType[];
  /** Functional capability tags (e.g. navigation, search, cart, product, menu, booking, login). Lets AI understand component purpose. */
  capabilities?: string[];
}
