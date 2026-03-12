/**
 * Part 4 — Spacing System Generator.
 *
 * Generates spacing tokens (xs, sm, md, lg, xl, 2xl) for use by components.
 * Components must use spacing tokens (e.g. var(--spacing-md)) instead of raw values.
 *
 * Example:
 *   xs: 4px, sm: 8px, md: 16px, lg: 24px, xl: 40px, 2xl: 64px
 */

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface SpacingScaleGeneratorInput {
  /** Base unit in px (e.g. 4 → xs=4, sm=8, md=16). Default 4. */
  baseUnitPx?: number;
  /** Output format: px or rem. */
  unit?: 'px' | 'rem';
  /** Style hint: tight, moderate, spacious (multipliers). */
  style?: string;
}

export interface SpacingScale {
  none: string;
  xs: string;
  sm: string;
  md: string;
  lg: string;
  xl: string;
  '2xl': string;
  '3xl'?: string;
  '4xl'?: string;
}

// ---------------------------------------------------------------------------
// Scale definitions (multipliers of base unit)
// ---------------------------------------------------------------------------

/** Multipliers for each token (baseUnit * N). Example base 4: xs=1→4px, sm=2→8px, md=4→16px. */
const SCALE_MULTIPLIERS: Record<keyof SpacingScale, number> = {
  none: 0,
  xs: 1,
  sm: 2,
  md: 4,
  lg: 6,
  xl: 10,
  '2xl': 16,
  '3xl': 24,
  '4xl': 32,
};

/** Optional tighter scale (smaller steps). */
const SCALE_TIGHT_MULTIPLIERS: Record<string, number> = {
  none: 0,
  xs: 1,
  sm: 2,
  md: 3,
  lg: 4,
  xl: 6,
  '2xl': 10,
  '3xl': 14,
  '4xl': 20,
};

/** Optional more spacious scale. */
const SCALE_SPACIOUS_MULTIPLIERS: Record<string, number> = {
  none: 0,
  xs: 2,
  sm: 4,
  md: 6,
  lg: 10,
  xl: 16,
  '2xl': 24,
  '3xl': 32,
  '4xl': 40,
};

const SCALES: Record<string, Record<string, number>> = {
  tight: SCALE_TIGHT_MULTIPLIERS,
  moderate: SCALE_MULTIPLIERS,
  spacious: SCALE_SPACIOUS_MULTIPLIERS,
  default: SCALE_MULTIPLIERS,
};

function resolveStyleKey(style: string): string {
  const s = (style || '').trim().toLowerCase();
  if (s === 'tight' || s === 'compact') return 'tight';
  if (s === 'spacious' || s === 'airy') return 'spacious';
  return 'moderate';
}

function formatValue(px: number, unit: 'px' | 'rem', baseRem = 16): string {
  return unit === 'rem' ? `${px / baseRem}rem` : `${px}px`;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Generates a spacing scale with tokens: none, xs, sm, md, lg, xl, 2xl (and optionally 3xl, 4xl).
 * Components should use these tokens (e.g. var(--spacing-md)) instead of raw values.
 *
 * Example (baseUnitPx: 4, unit: 'px'):
 *   xs: 4px, sm: 8px, md: 16px, lg: 24px, xl: 40px, 2xl: 64px
 */
export function generateSpacingScale(input: SpacingScaleGeneratorInput = {}): SpacingScale {
  const { baseUnitPx = 4, unit = 'px', style = 'moderate' } = input;
  const scaleKey = resolveStyleKey(style);
  const multipliers = SCALES[scaleKey] ?? SCALE_MULTIPLIERS;

  const scale: SpacingScale = {
    none: unit === 'rem' ? '0' : '0',
    xs: formatValue((multipliers.xs ?? 1) * baseUnitPx, unit),
    sm: formatValue((multipliers.sm ?? 2) * baseUnitPx, unit),
    md: formatValue((multipliers.md ?? 4) * baseUnitPx, unit),
    lg: formatValue((multipliers.lg ?? 6) * baseUnitPx, unit),
    xl: formatValue((multipliers.xl ?? 10) * baseUnitPx, unit),
    '2xl': formatValue((multipliers['2xl'] ?? 16) * baseUnitPx, unit),
  };

  if (multipliers['3xl'] != null) {
    scale['3xl'] = formatValue(multipliers['3xl'] * baseUnitPx, unit);
  }
  if (multipliers['4xl'] != null) {
    scale['4xl'] = formatValue(multipliers['4xl'] * baseUnitPx, unit);
  }

  return scale;
}

/**
 * Converts a spacing scale to CSS custom properties for use in :root or theme.
 * Components reference these as var(--spacing-xs), var(--spacing-md), etc.
 */
export function spacingScaleToCssVars(scale: SpacingScale, prefix = '--spacing-'): Record<string, string> {
  const vars: Record<string, string> = {};
  for (const [key, value] of Object.entries(scale)) {
    if (value !== undefined) vars[`${prefix}${key}`] = value;
  }
  return vars;
}

/**
 * Default spacing scale (base 4px, px output) for drop-in use.
 * xs: 4px, sm: 8px, md: 16px, lg: 24px, xl: 40px, 2xl: 64px
 */
export const DEFAULT_SPACING_SCALE: SpacingScale = generateSpacingScale({ baseUnitPx: 4, unit: 'px' });
