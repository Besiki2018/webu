/**
 * Part 6 — Shadow System Generator.
 *
 * Generates shadow tokens (shadow-sm, shadow-md, shadow-lg, shadow-xl)
 * for use by components. Output as CSS variables (--shadow-sm, etc.).
 *
 * Example: shadow-md: 0 10px 25px rgba(0,0,0,0.1)
 */

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface ShadowGeneratorInput {
  /** Style hint: flat, soft, strong. */
  style?: string;
}

export interface ShadowScale {
  none: string;
  sm: string;
  md: string;
  lg: string;
  xl: string;
  card?: string;
  elevated?: string;
}

// ---------------------------------------------------------------------------
// Shadow presets
// ---------------------------------------------------------------------------

const SHADOW_FLAT: ShadowScale = {
  none: 'none',
  sm: '0 1px 2px rgba(0,0,0,0.04)',
  md: '0 2px 4px rgba(0,0,0,0.06)',
  lg: '0 4px 8px rgba(0,0,0,0.08)',
  xl: '0 8px 16px rgba(0,0,0,0.1)',
  card: '0 1px 2px rgba(0,0,0,0.05)',
  elevated: '0 4px 12px rgba(0,0,0,0.08)',
};

const SHADOW_SOFT: ShadowScale = {
  none: 'none',
  sm: '0 1px 3px rgba(0,0,0,0.06)',
  md: '0 10px 25px rgba(0,0,0,0.1)',
  lg: '0 10px 15px -3px rgba(0,0,0,0.08), 0 4px 6px -4px rgba(0,0,0,0.06)',
  xl: '0 20px 25px -5px rgba(0,0,0,0.08), 0 8px 10px -6px rgba(0,0,0,0.06)',
  card: '0 1px 3px rgba(0,0,0,0.06)',
  elevated: '0 8px 24px rgba(0,0,0,0.1)',
};

const SHADOW_STRONG: ShadowScale = {
  none: 'none',
  sm: '0 1px 2px rgba(0,0,0,0.08)',
  md: '0 4px 6px -1px rgba(0,0,0,0.12), 0 2px 4px -2px rgba(0,0,0,0.1)',
  lg: '0 10px 15px -3px rgba(0,0,0,0.12), 0 4px 6px -4px rgba(0,0,0,0.1)',
  xl: '0 20px 25px -5px rgba(0,0,0,0.15), 0 8px 10px -6px rgba(0,0,0,0.1)',
  card: '0 2px 8px rgba(0,0,0,0.1)',
  elevated: '0 12px 32px rgba(0,0,0,0.15)',
};

const SHADOW_PRESETS: Record<string, ShadowScale> = {
  flat: SHADOW_FLAT,
  soft: SHADOW_SOFT,
  strong: SHADOW_STRONG,
  default: SHADOW_SOFT,
  modern: SHADOW_SOFT,
};

function resolveStyleKey(style: string): string {
  const s = (style || '').trim().toLowerCase();
  if (s === 'flat' || s === 'minimal') return 'flat';
  if (s === 'strong' || s === 'bold') return 'strong';
  return 'soft';
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Generates a shadow scale: none, sm, md, lg, xl (and optionally card, elevated).
 */
export function generateShadowScale(input: ShadowGeneratorInput = {}): ShadowScale {
  const { style = 'soft' } = input;
  const key = resolveStyleKey(style);
  const scale = SHADOW_PRESETS[key] ?? SHADOW_SOFT;
  return { ...scale };
}

/**
 * Converts a shadow scale to CSS custom properties.
 * Components use var(--shadow-sm), var(--shadow-md), etc.
 */
export function shadowScaleToCssVars(scale: ShadowScale, prefix = '--shadow-'): Record<string, string> {
  const vars: Record<string, string> = {};
  for (const [k, v] of Object.entries(scale)) {
    if (v !== undefined) vars[`${prefix}${k}`] = v;
  }
  return vars;
}

/** Default shadow scale (soft). */
export const DEFAULT_SHADOW_SCALE: ShadowScale = generateShadowScale({ style: 'soft' });
