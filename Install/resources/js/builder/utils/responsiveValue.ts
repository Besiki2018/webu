/**
 * Responsive props — breakpoints and ResponsiveValue<T> helpers.
 * Structure: desktop | tablet | mobile.
 * Example fields: padding, fontSize, gridColumns, visibility.
 */

import type { ResponsiveValue as ResponsiveValueType } from '../core';

export type ResponsiveBreakpoint = 'desktop' | 'tablet' | 'mobile';

/** Breakpoint order (largest to smallest viewport). */
export const RESPONSIVE_BREAKPOINTS: readonly ResponsiveBreakpoint[] = ['desktop', 'tablet', 'mobile'];

export function isResponsiveBreakpoint(value: string): value is ResponsiveBreakpoint {
  return RESPONSIVE_BREAKPOINTS.includes(value as ResponsiveBreakpoint);
}

/**
 * Type guard: value is a ResponsiveValue (object with breakpoint keys or base).
 */
export function isResponsiveValue<T = unknown>(value: unknown): value is ResponsiveValueType<T> {
  if (value === null || typeof value !== 'object' || Array.isArray(value)) return false;
  const o = value as Record<string, unknown>;
  return 'base' in o || 'desktop' in o || 'tablet' in o || 'mobile' in o;
}

/**
 * Resolves the effective value for a breakpoint.
 * Returns value[breakpoint] ?? value.base so the component can use one value per breakpoint.
 *
 * @example
 * getResponsiveValue({ base: 16, tablet: 14, mobile: 12 }, 'mobile') // => 12
 * getResponsiveValue({ base: 16, tablet: 14 }, 'desktop')           // => 16 (base)
 * getResponsiveValue(24, 'tablet')                                  // => 24 (plain value)
 */
export function getResponsiveValue<T>(
  value: ResponsiveValueType<T> | T,
  breakpoint: ResponsiveBreakpoint
): T | undefined {
  if (!isResponsiveValue(value)) {
    return value as T;
  }
  const v = value as ResponsiveValueType<T>;
  const bp = v[breakpoint];
  if (bp !== undefined) return bp;
  return v.base;
}

/**
 * Same as getResponsiveValue but returns a definite value using fallback when undefined.
 */
export function getResponsiveValueOr<T>(
  value: ResponsiveValueType<T> | T,
  breakpoint: ResponsiveBreakpoint,
  fallback: T
): T {
  const resolved = getResponsiveValue(value, breakpoint);
  return resolved !== undefined ? resolved : fallback;
}

/**
 * Sets the value for a breakpoint and returns a new ResponsiveValue.
 * If current is a plain value, it becomes the base and the breakpoint is set.
 */
export function setResponsiveValue<T>(
  current: ResponsiveValueType<T> | T,
  breakpoint: ResponsiveBreakpoint,
  value: T
): ResponsiveValueType<T> {
  if (!isResponsiveValue(current)) {
    return { base: current as T, [breakpoint]: value } as ResponsiveValueType<T>;
  }
  return { ...(current as ResponsiveValueType<T>), [breakpoint]: value };
}

/** Common responsive prop keys (padding, fontSize, gridColumns, visibility). */
export const RESPONSIVE_PROP_KEYS = ['padding', 'fontSize', 'gridColumns', 'visibility'] as const;
