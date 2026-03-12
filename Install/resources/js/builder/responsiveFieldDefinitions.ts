/**
 * Canonical responsive override field definitions.
 * Used by componentRegistry (buildFoundationFields) and by component schemas (Header, Footer, Hero)
 * to ensure desktop / tablet / mobile overrides for: padding, margin, fontSize, gridColumns, visibility.
 */

export const RESPONSIVE_BREAKPOINTS = ['desktop', 'tablet', 'mobile'] as const;
export type ResponsiveBreakpoint = (typeof RESPONSIVE_BREAKPOINTS)[number];

/** Prop keys that support per-breakpoint overrides (base prop name → responsive path prefix). */
export const RESPONSIVE_PROP_KEYS = [
  'padding',
  'margin',
  'fontSize',
  'gridColumns',
  'visibility',
] as const;

export type ResponsivePropKey = (typeof RESPONSIVE_PROP_KEYS)[number];

/**
 * Path convention for responsive overrides:
 * - responsive.{breakpoint}.padding_top | padding_bottom | padding_left | padding_right
 * - responsive.{breakpoint}.margin_top | margin_bottom | margin_left | margin_right
 * - responsive.{breakpoint}.font_size
 * - responsive.{breakpoint}.grid_columns
 * - responsive.hide_on_desktop | hide_on_tablet | hide_on_mobile (visibility)
 */
export interface ResponsiveFieldSpec {
  path: string;
  label: string;
  type: 'spacing' | 'number' | 'visibility' | 'color';
  group: 'responsive';
  default?: string | number | boolean;
}

function cap(s: string): string {
  return s.charAt(0).toUpperCase() + s.slice(1);
}

/**
 * Returns the list of responsive field specs (path, label, type, group).
 * Components can map these to BuilderFieldDefinition via their field() helper.
 */
export function getResponsiveFieldSpecs(): ResponsiveFieldSpec[] {
  const specs: ResponsiveFieldSpec[] = [];

  for (const bp of RESPONSIVE_BREAKPOINTS) {
    const prefix = `responsive.${bp}`;
    const labelPrefix = cap(bp);

    specs.push(
      { path: `${prefix}.padding_top`, label: `${labelPrefix} padding top`, type: 'spacing', group: 'responsive', default: '' },
      { path: `${prefix}.padding_bottom`, label: `${labelPrefix} padding bottom`, type: 'spacing', group: 'responsive', default: '' },
      { path: `${prefix}.padding_left`, label: `${labelPrefix} padding left`, type: 'spacing', group: 'responsive', default: '' },
      { path: `${prefix}.padding_right`, label: `${labelPrefix} padding right`, type: 'spacing', group: 'responsive', default: '' },
      { path: `${prefix}.margin_top`, label: `${labelPrefix} margin top`, type: 'spacing', group: 'responsive', default: '' },
      { path: `${prefix}.margin_bottom`, label: `${labelPrefix} margin bottom`, type: 'spacing', group: 'responsive', default: '' },
      { path: `${prefix}.margin_left`, label: `${labelPrefix} margin left`, type: 'spacing', group: 'responsive', default: '' },
      { path: `${prefix}.margin_right`, label: `${labelPrefix} margin right`, type: 'spacing', group: 'responsive', default: '' },
      { path: `${prefix}.font_size`, label: `${labelPrefix} font size`, type: 'spacing', group: 'responsive', default: '' },
      { path: `${prefix}.grid_columns`, label: `${labelPrefix} grid columns`, type: 'number', group: 'responsive', default: 0 }
    );
  }

  specs.push(
    { path: 'responsive.hide_on_desktop', label: 'Hide on desktop', type: 'visibility', group: 'responsive', default: false },
    { path: 'responsive.hide_on_tablet', label: 'Hide on tablet', type: 'visibility', group: 'responsive', default: false },
    { path: 'responsive.hide_on_mobile', label: 'Hide on mobile', type: 'visibility', group: 'responsive', default: false }
  );

  return specs;
}
