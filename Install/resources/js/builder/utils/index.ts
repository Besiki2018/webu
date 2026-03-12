/**
 * Builder utils — helper functions.
 * Path normalization, prop parsing, merge helpers, responsive values.
 * No UI; pure utilities only.
 */

export { mergeDefaults } from './mergeDefaults';
export {
  RESPONSIVE_BREAKPOINTS,
  RESPONSIVE_PROP_KEYS,
  isResponsiveBreakpoint,
  isResponsiveValue,
  getResponsiveValue,
  getResponsiveValueOr,
  setResponsiveValue,
} from './responsiveValue';
export type { ResponsiveBreakpoint } from './responsiveValue';
