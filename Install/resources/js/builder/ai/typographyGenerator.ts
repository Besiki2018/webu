/**
 * Part 3 — Typography System Generator.
 *
 * Generates a font system (heading, body, monospace) and a typography scale
 * (h1, h2, h3, h4, body, small) with sizes in px/rem.
 *
 * Example:
 *   heading: Inter, body: Inter
 *   h1: 48px, h2: 36px, h3: 28px, h4: 24px, body: 16px, small: 14px
 */

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface TypographyGeneratorInput {
  /** Style hint: modern, minimal, editorial, classic. */
  style?: string;
  /** Project type: landing, saas, ecommerce (can influence scale). */
  projectType?: string;
  /** Optional base body size in px (e.g. 16). Scale is derived from this. */
  baseSizePx?: number;
}

export interface FontSystem {
  heading: string;
  body: string;
  monospace: string;
}

export interface TypographyScale {
  h1: string;
  h2: string;
  h3: string;
  h4: string;
  body: string;
  small: string;
}

export interface TypographyScaleExtended extends TypographyScale {
  /** Optional extra levels for tokens. */
  xs?: string;
  lg?: string;
  xl?: string;
}

export interface GeneratedTypographySystem {
  fonts: FontSystem;
  scale: TypographyScaleExtended;
  /** Line heights per level (e.g. h1: 1.2, body: 1.5). */
  lineHeights?: Partial<Record<keyof TypographyScale, string>>;
  /** Font weights per level. */
  weights?: Partial<Record<keyof TypographyScale, string>>;
}

// ---------------------------------------------------------------------------
// Font presets (family name or stack)
// ---------------------------------------------------------------------------

const FONT_PRESETS: Record<string, FontSystem> = {
  modern: {
    heading: 'Inter, ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"',
    body: 'Inter, ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"',
    monospace: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace',
  },
  minimal: {
    heading: 'Inter, system-ui, sans-serif',
    body: 'Inter, system-ui, sans-serif',
    monospace: 'SFMono-Regular, Menlo, Monaco, Consolas, monospace',
  },
  editorial: {
    heading: 'Georgia, ui-serif, "Times New Roman", Times, serif',
    body: 'Georgia, ui-serif, "Times New Roman", Times, serif',
    monospace: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
  },
  classic: {
    heading: 'Georgia, "Times New Roman", Times, serif',
    body: 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
    monospace: 'Menlo, Monaco, "Courier New", monospace',
  },
  system: {
    heading: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
    body: 'ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
    monospace: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
  },
};

// ---------------------------------------------------------------------------
// Typography scale presets (px values; can be converted to rem)
// ---------------------------------------------------------------------------

/** Default scale: h1 48px, h2 36px, h3 28px, h4 24px, body 16px, small 14px. */
const SCALE_DEFAULT: Record<keyof TypographyScale, number> = {
  h1: 48,
  h2: 36,
  h3: 28,
  h4: 24,
  body: 16,
  small: 14,
};

const SCALE_LARGE: Record<keyof TypographyScale, number> = {
  h1: 56,
  h2: 42,
  h3: 32,
  h4: 26,
  body: 18,
  small: 14,
};

const SCALE_COMPACT: Record<keyof TypographyScale, number> = {
  h1: 40,
  h2: 30,
  h3: 24,
  h4: 20,
  body: 15,
  small: 13,
};

const SCALES: Record<string, Record<keyof TypographyScale, number>> = {
  default: SCALE_DEFAULT,
  large: SCALE_LARGE,
  compact: SCALE_COMPACT,
};

const LINE_HEIGHT_DEFAULT: Partial<Record<keyof TypographyScale, string>> = {
  h1: '1.2',
  h2: '1.25',
  h3: '1.3',
  h4: '1.35',
  body: '1.5',
  small: '1.5',
};

const WEIGHT_DEFAULT: Partial<Record<keyof TypographyScale, string>> = {
  h1: '700',
  h2: '700',
  h3: '600',
  h4: '600',
  body: '400',
  small: '400',
};

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function resolveStyleKey(style: string, projectType: string): string {
  const s = (style || '').trim().toLowerCase();
  const p = (projectType || '').trim().toLowerCase();
  if (s === 'editorial' || s === 'serif') return 'editorial';
  if (s === 'classic') return 'classic';
  if (s === 'minimal' || s === 'clean') return 'minimal';
  if (s === 'system') return 'system';
  return 'modern';
}

function resolveScaleKey(style: string, projectType: string): string {
  const s = (style || '').trim().toLowerCase();
  const p = (projectType || '').trim().toLowerCase();
  if (s === 'large' || s === 'spacious') return 'large';
  if (s === 'compact' || p === 'ecommerce') return 'compact';
  return 'default';
}

function pxToRem(px: number, base = 16): string {
  return `${px / base}rem`;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Generates a typography system: font system (heading, body, monospace) and
 * typography scale (h1–h4, body, small) with sizes in px and rem.
 *
 * Example:
 *   generateTypographySystem({ style: 'modern' })
 *   → fonts: { heading: 'Inter, ...', body: 'Inter, ...', monospace: '...' }
 *   → scale: { h1: '48px', h2: '36px', h3: '28px', h4: '24px', body: '16px', small: '14px' }
 */
export function generateTypographySystem(input: TypographyGeneratorInput = {}): GeneratedTypographySystem {
  const { style = 'modern', projectType = 'landing', baseSizePx } = input;

  const fontKey = resolveStyleKey(style, projectType);
  const fonts: FontSystem = { ...FONT_PRESETS[fontKey] ?? FONT_PRESETS.modern };

  const scaleKey = resolveScaleKey(style, projectType);
  const scalePx = SCALES[scaleKey] ?? SCALE_DEFAULT;
  const base = baseSizePx ?? scalePx.body;

  const scale: TypographyScaleExtended = {
    h1: `${scalePx.h1}px`,
    h2: `${scalePx.h2}px`,
    h3: `${scalePx.h3}px`,
    h4: `${scalePx.h4}px`,
    body: `${scalePx.body}px`,
    small: `${scalePx.small}px`,
    xs: `${Math.round(scalePx.small * 0.85)}px`,
    lg: `${Math.round(scalePx.body * 1.125)}px`,
    xl: `${Math.round(scalePx.body * 1.25)}px`,
  };

  return {
    fonts,
    scale,
    lineHeights: LINE_HEIGHT_DEFAULT,
    weights: WEIGHT_DEFAULT,
  };
}

/**
 * Returns the same scale with sizes in rem (e.g. body 16px → 1rem when base 16).
 */
export function typographyScaleToRem(
  scale: TypographyScaleExtended,
  basePx = 16
): Record<keyof TypographyScaleExtended, string> {
  const out: Record<string, string> = {};
  for (const [key, value] of Object.entries(scale)) {
    const match = typeof value === 'string' && value.endsWith('px') ? value.replace('px', '') : '';
    const num = parseInt(match, 10);
    out[key] = Number.isNaN(num) ? value : pxToRem(num, basePx);
  }
  return out as Record<keyof TypographyScaleExtended, string>;
}

/**
 * Converts the generated typography system to CSS custom properties.
 * Example: --font-heading, --font-body, --font-size-h1, --font-size-body, etc.
 */
export function typographySystemToCssVars(
  system: GeneratedTypographySystem,
  prefix = '--font'
): Record<string, string> {
  const vars: Record<string, string> = {};
  vars[`${prefix}-heading`] = system.fonts.heading;
  vars[`${prefix}-body`] = system.fonts.body;
  vars[`${prefix}-monospace`] = system.fonts.monospace;
  for (const [key, value] of Object.entries(system.scale)) {
    if (value) vars[`${prefix}-size-${key}`] = value;
  }
  if (system.lineHeights) {
    for (const [key, value] of Object.entries(system.lineHeights)) {
      if (value) vars[`${prefix}-line-height-${key}`] = value;
    }
  }
  if (system.weights) {
    for (const [key, value] of Object.entries(system.weights)) {
      if (value) vars[`${prefix}-weight-${key}`] = value;
    }
  }
  return vars;
}
