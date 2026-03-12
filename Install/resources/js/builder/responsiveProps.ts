/**
 * Resolve effective component props for a given breakpoint.
 * Responsive overrides live under responsive.{breakpoint}.* and override base props.
 * Breakpoint order for cascade: desktop (base) → tablet → mobile (mobile wins when active).
 */

import type { ResponsiveBreakpoint } from './responsiveFieldDefinitions';
import { RESPONSIVE_BREAKPOINTS } from './responsiveFieldDefinitions';

const BREAKPOINT_ORDER: ResponsiveBreakpoint[] = ['desktop', 'tablet', 'mobile'];

/**
 * Get the effective value for a single prop at a breakpoint by cascading from desktop down to the given breakpoint.
 * Base (non-responsive) keys are used as fallback when no responsive override is set.
 *
 * @param props - Full section/component props (may include responsive.*)
 * @param breakpoint - Current breakpoint
 * @param baseKey - Base prop key (e.g. 'padding', 'margin', 'fontSize'). For directional props we look for responsive.{bp}.padding_top etc.
 * @returns Effective value (responsive override or base)
 */
export function getResponsiveValue(
  props: Record<string, unknown>,
  breakpoint: ResponsiveBreakpoint,
  baseKey: string
): unknown {
  const bpIndex = BREAKPOINT_ORDER.indexOf(breakpoint);
  if (bpIndex < 0) return props[baseKey];

  // Walk from desktop down to current breakpoint; first defined override wins
  for (let i = bpIndex; i >= 0; i--) {
    const bp = BREAKPOINT_ORDER[i];
    const overrideKey = `responsive.${bp}.${baseKey}`;
    const v = props[overrideKey];
    if (v !== undefined && v !== null && v !== '') return v;
  }

  return props[baseKey];
}

/**
 * Get effective visibility for the breakpoint: hidden if responsive.hide_on_{breakpoint} is true.
 */
export function isHiddenAtBreakpoint(
  props: Record<string, unknown>,
  breakpoint: ResponsiveBreakpoint
): boolean {
  const key = `responsive.hide_on_${breakpoint}`;
  return props[key] === true;
}

/**
 * Build a flat object of effective values for the given breakpoint by merging base props
 * with responsive.{breakpoint}.* overrides (override wins when defined).
 * Use this to pass breakpoint-specific props to components when rendering in a responsive context.
 *
 * @param props - Full section/component props
 * @param breakpoint - Current breakpoint
 * @returns New object with base props + responsive overrides applied (shallow for responsive.* keys)
 */
export function getEffectivePropsForBreakpoint(
  props: Record<string, unknown>,
  breakpoint: ResponsiveBreakpoint
): Record<string, unknown> {
  const result = { ...props };

  const prefix = `responsive.${breakpoint}.`;
  for (const key of Object.keys(props)) {
    if (!key.startsWith(prefix)) continue;
    const baseKey = key.slice(prefix.length);
    const value = props[key];
    if (value !== undefined && value !== null && value !== '') {
      result[baseKey] = value;
    }
  }

  return result;
}

/**
 * List of breakpoints supported by the builder (desktop, tablet, mobile).
 */
export function getResponsiveBreakpoints(): ResponsiveBreakpoint[] {
  return [...RESPONSIVE_BREAKPOINTS];
}
