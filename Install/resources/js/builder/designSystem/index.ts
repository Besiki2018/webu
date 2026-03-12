/**
 * Design system (Parts 6–13): tokens, applier, panel helpers.
 * Use designTokens in components; use applier to normalize section props to token refs.
 */

export { designTokens, type DesignTokens, typography } from './tokens';
export {
  normalizeColor,
  normalizeSpacing,
  applyTokensToSectionProps,
  applyTokensToLayout,
  getButtonTokenRefs,
  type LayoutSectionLike,
} from './designSystemApplier';
