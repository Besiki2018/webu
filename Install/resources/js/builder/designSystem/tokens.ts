/**
 * Part 8 — Global Design Tokens.
 *
 * Single export: designTokens with colors, typography, spacing, radius, shadows, buttons.
 * Components use tokens instead of raw values (e.g. spacing.md, colors.primary).
 *
 * Example:
 *   export const designTokens = {
 *     colors: {...},
 *     typography: {...},
 *     spacing: {...},
 *     radius: {...},
 *     shadows: {...},
 *     buttons: {...}
 *   }
 */

import {
  DESIGN_TOKEN_COLORS,
  DESIGN_TOKEN_FONTS,
  DESIGN_TOKEN_FONT_SIZES,
  DESIGN_TOKEN_SPACING,
  DESIGN_TOKEN_RADIUS,
  DESIGN_TOKEN_SHADOWS,
} from '@/builder/designTokens';
import { generateButtonStyles } from '@/builder/ai/buttonStyleGenerator';

// ---------------------------------------------------------------------------
// Typography = fonts + font sizes (and optional weights/lineHeights)
// ---------------------------------------------------------------------------

export const typography = {
  fonts: DESIGN_TOKEN_FONTS,
  fontSizes: DESIGN_TOKEN_FONT_SIZES,
  // Optional: add fontWeights, lineHeights when needed
  fontWeights: { normal: '400', medium: '500', semibold: '600', bold: '700' } as const,
  lineHeights: { tight: '1.25', normal: '1.5', relaxed: '1.75' } as const,
};

// ---------------------------------------------------------------------------
// Buttons (token-based references; resolve to CSS vars in applier)
// ---------------------------------------------------------------------------

const buttonStyles = generateButtonStyles({});

// ---------------------------------------------------------------------------
// Single designTokens export
// ---------------------------------------------------------------------------

export const designTokens = {
  colors: DESIGN_TOKEN_COLORS,
  typography,
  spacing: DESIGN_TOKEN_SPACING,
  radius: DESIGN_TOKEN_RADIUS,
  shadows: DESIGN_TOKEN_SHADOWS,
  buttons: buttonStyles,
} as const;

export type DesignTokens = typeof designTokens;
