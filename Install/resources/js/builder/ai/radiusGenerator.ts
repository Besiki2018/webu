/**
 * Part 5 — Radius System Generator.
 *
 * Generates border-radius tokens (radius-sm, radius-md, radius-lg, radius-xl)
 * for use by components. Output as CSS variables (--radius-sm, etc.).
 *
 * Example values:
 *   sm: 4px, md: 8px, lg: 12px, xl: 16px
 */

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface RadiusGeneratorInput {
  /** Base unit in px (e.g. 4 → sm=4, md=8, lg=12, xl=16). Default 4. */
  baseUnitPx?: number;
  /** Output format: px or rem. */
  unit?: 'px' | 'rem';
  /** Style hint: sharp, rounded, pill (affects scale). */
  style?: string;
}

export interface RadiusScale {
  none: string;
  sm: string;
  md: string;
  lg: string;
  xl: string;
  '2xl'?: string;
  full: string;
}

// ---------------------------------------------------------------------------
// Scale definitions (multipliers of base unit)
// ---------------------------------------------------------------------------

/** Multipliers for each token. Base 4: sm=4px, md=8px, lg=12px, xl=16px. */
const SCALE_DEFAULT: Record<keyof RadiusScale, number | string> = {
  none: 0,
  sm: 1,
  md: 2,
  lg: 3,
  xl: 4,
  '2xl': 6,
  full: 9999,
};

const SCALE_SHARP: Record<string, number | string> = {
  none: 0,
  sm: 1,
  md: 1.5,
  lg: 2,
  xl: 2,
  '2xl': 3,
  full: 9999,
};

const SCALE_PILL: Record<string, number | string> = {
  none: 0,
  sm: 2,
  md: 4,
  lg: 6,
  xl: 8,
  '2xl': 12,
  full: 9999,
};

const SCALES: Record<string, Record<string, number | string>> = {
  default: SCALE_DEFAULT,
  moderate: SCALE_DEFAULT,
  sharp: SCALE_SHARP,
  rounded: SCALE_DEFAULT,
  pill: SCALE_PILL,
};

function resolveStyleKey(style: string): string {
  const s = (style || '').trim().toLowerCase();
  if (s === 'sharp' || s === 'minimal') return 'sharp';
  if (s === 'pill' || s === 'rounded-full') return 'pill';
  return 'moderate';
}

function formatValue(value: number | string, baseUnitPx: number, unit: 'px' | 'rem', baseRem = 16): string {
  if (value === 9999 || value === '9999') return '9999px';
  const n = typeof value === 'string' ? parseFloat(value) : value;
  const px = n * baseUnitPx;
  return unit === 'rem' ? `${px / baseRem}rem` : `${Math.round(px)}px`;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Generates a radius scale: none, sm, md, lg, xl, 2xl, full.
 * Example (baseUnitPx: 4, unit: 'px'): sm: 4px, md: 8px, lg: 12px, xl: 16px
 */
export function generateRadiusScale(input: RadiusGeneratorInput = {}): RadiusScale {
  const { baseUnitPx = 4, unit = 'px', style = 'moderate' } = input;
  const scaleKey = resolveStyleKey(style);
  const multipliers = SCALES[scaleKey] ?? SCALE_DEFAULT;

  const scale: RadiusScale = {
    none: unit === 'rem' ? '0' : '0',
    sm: formatValue(multipliers.sm ?? 1, baseUnitPx, unit),
    md: formatValue(multipliers.md ?? 2, baseUnitPx, unit),
    lg: formatValue(multipliers.lg ?? 3, baseUnitPx, unit),
    xl: formatValue(multipliers.xl ?? 4, baseUnitPx, unit),
    full: '9999px',
  };

  if (multipliers['2xl'] != null) {
    scale['2xl'] = formatValue(multipliers['2xl'], baseUnitPx, unit);
  }

  return scale;
}

/**
 * Converts a radius scale to CSS custom properties for use in :root or theme.
 * Components reference these as var(--radius-sm), var(--radius-md), etc.
 */
export function radiusScaleToCssVars(scale: RadiusScale, prefix = '--radius-'): Record<string, string> {
  const vars: Record<string, string> = {};
  for (const [key, value] of Object.entries(scale)) {
    if (value !== undefined) vars[`${prefix}${key}`] = value;
  }
  return vars;
}

/**
 * Default radius scale (base 4px, px output).
 * sm: 4px, md: 8px, lg: 12px, xl: 16px, 2xl: 24px
 */
export const DEFAULT_RADIUS_SCALE: RadiusScale = generateRadiusScale({ baseUnitPx: 4, unit: 'px' });
