/**
 * Part 2 — Color Palette Generator.
 *
 * Generates a full color palette including:
 * - primary, primary-light, primary-dark
 * - secondary
 * - accent, background, text, muted
 * - neutral scale (50–900)
 *
 * Example output:
 *   primary: #5B6CFF
 *   secondary: #00C2A8
 *   background: #F8F9FC
 *   text: #1F2937
 */

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface ColorPaletteGeneratorInput {
  /** Seed primary hex (e.g. #5B6CFF). If omitted, a default is used. */
  primary?: string;
  /** Seed secondary hex (e.g. #00C2A8). If omitted, derived or default. */
  secondary?: string;
  /** Style hint: modern, minimal, warm, professional, bold. */
  style?: string;
  /** Optional: prefer light or dark background. */
  backgroundTone?: 'light' | 'dark';
}

export interface NeutralScale {
  50: string;
  100: string;
  200: string;
  300: string;
  400: string;
  500: string;
  600: string;
  700: string;
  800: string;
  900: string;
}

export interface GeneratedColorPalette {
  primary: string;
  'primary-light': string;
  'primary-dark': string;
  secondary: string;
  accent: string;
  background: string;
  text: string;
  muted: string;
  /** Neutral grayscale (50 = lightest, 900 = darkest). */
  neutral: NeutralScale;
  /** Convenience: border often uses neutral-200. */
  border?: string;
  /** Foreground on primary (e.g. white). */
  'primary-foreground'?: string;
}

// ---------------------------------------------------------------------------
// Hex / HSL helpers
// ---------------------------------------------------------------------------

function parseHex(hex: string): { r: number; g: number; b: number } | null {
  const m = hex.replace(/^#/, '').match(/^([0-9a-f]{3}|[0-9a-f]{6})$/i);
  if (!m) return null;
  let s = m[1];
  if (s.length === 3) s = s[0] + s[0] + s[1] + s[1] + s[2] + s[2];
  const r = parseInt(s.slice(0, 2), 16);
  const g = parseInt(s.slice(2, 4), 16);
  const b = parseInt(s.slice(4, 6), 16);
  return { r, g, b };
}

function rgbToHsl(r: number, g: number, b: number): { h: number; s: number; l: number } {
  r /= 255;
  g /= 255;
  b /= 255;
  const max = Math.max(r, g, b);
  const min = Math.min(r, g, b);
  let h = 0;
  let s = 0;
  const l = (max + min) / 2;
  if (max !== min) {
    const d = max - min;
    s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
    switch (max) {
      case r:
        h = ((g - b) / d + (g < b ? 6 : 0)) / 6;
        break;
      case g:
        h = ((b - r) / d + 2) / 6;
        break;
      default:
        h = ((r - g) / d + 4) / 6;
    }
  }
  return { h: h * 360, s: s * 100, l: l * 100 };
}

function hslToHex(h: number, s: number, l: number): string {
  s /= 100;
  l /= 100;
  const a = s * Math.min(l, 1 - l);
  const f = (n: number) => {
    const k = (n + h / 30) % 12;
    const x = l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1);
    return Math.round(x * 255)
      .toString(16)
      .padStart(2, '0');
  };
  return `#${f(0)}${f(8)}${f(4)}`;
}

function hexToHsl(hex: string): { h: number; s: number; l: number } | null {
  const rgb = parseHex(hex);
  if (!rgb) return null;
  return rgbToHsl(rgb.r, rgb.g, rgb.b);
}

/** Lighten a hex color by a percentage of remaining luminance (0–1). */
function lighten(hex: string, amount: number): string {
  const hsl = hexToHsl(hex);
  if (!hsl) return hex;
  const l = Math.min(100, hsl.l + (100 - hsl.l) * amount);
  return hslToHex(hsl.h, hsl.s, l);
}

/** Darken a hex color by a percentage of current luminance (0–1). */
function darken(hex: string, amount: number): string {
  const hsl = hexToHsl(hex);
  if (!hsl) return hex;
  const l = Math.max(0, hsl.l - hsl.l * amount);
  return hslToHex(hsl.h, hsl.s, l);
}

/** Ensure hex has # and is 6-char. */
function normalizeHex(hex: string): string | null {
  const h = hex.replace(/^#/, '').trim();
  if (/^[0-9a-f]{6}$/i.test(h)) return `#${h}`;
  if (/^[0-9a-f]{3}$/i.test(h))
    return `#${h[0]}${h[0]}${h[1]}${h[1]}${h[2]}${h[2]}`;
  return null;
}

// ---------------------------------------------------------------------------
// Neutral scale (from base gray)
// ---------------------------------------------------------------------------

const DEFAULT_NEUTRAL_BASE = '#6b7280'; // gray-500

function buildNeutralScale(baseHex: string): NeutralScale {
  const hsl = hexToHsl(baseHex);
  const h = hsl?.h ?? 220;
  const s = Math.min(10, hsl?.s ?? 5);
  return {
    50: hslToHex(h, s, 98),
    100: hslToHex(h, s, 96),
    200: hslToHex(h, s, 90),
    300: hslToHex(h, s, 82),
    400: hslToHex(h, s, 64),
    500: hslToHex(h, s, 45),
    600: hslToHex(h, s, 35),
    700: hslToHex(h, s, 25),
    800: hslToHex(h, s, 15),
    900: hslToHex(h, s, 9),
  };
}

// ---------------------------------------------------------------------------
// Preset palettes (when no primary/secondary given)
// ---------------------------------------------------------------------------

const PRESET_PALETTES: Record<string, { primary: string; secondary: string; background: string; text: string }> = {
  modern: {
    primary: '#5B6CFF',
    secondary: '#00C2A8',
    background: '#F8F9FC',
    text: '#1F2937',
  },
  minimal: {
    primary: '#18181b',
    secondary: '#71717a',
    background: '#ffffff',
    text: '#18181b',
  },
  warm: {
    primary: '#c2410c',
    secondary: '#ea580c',
    background: '#fffbeb',
    text: '#1c1917',
  },
  professional: {
    primary: '#0f766e',
    secondary: '#0d9488',
    background: '#ffffff',
    text: '#0f172a',
  },
  bold: {
    primary: '#7c3aed',
    secondary: '#ec4899',
    background: '#fafafa',
    text: '#0f172a',
  },
};

function resolvePreset(style: string): (typeof PRESET_PALETTES)['modern'] {
  const s = (style || 'modern').trim().toLowerCase();
  return PRESET_PALETTES[s] ?? PRESET_PALETTES.modern;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Generates a full color palette: primary, primary-light, primary-dark,
 * secondary, accent, background, text, muted, and neutral scale.
 *
 * Example:
 *   generateColorPalette({ primary: '#5B6CFF', secondary: '#00C2A8' })
 *   → { primary: '#5B6CFF', 'primary-light': '...', 'primary-dark': '...', secondary, accent, background, text, muted, neutral }
 */
export function generateColorPalette(input: ColorPaletteGeneratorInput = {}): GeneratedColorPalette {
  const { primary: inputPrimary, secondary: inputSecondary, style = 'modern', backgroundTone = 'light' } = input;

  let primary: string;
  let secondary: string;
  let background: string;
  let text: string;

  if (inputPrimary && normalizeHex(inputPrimary)) {
    primary = normalizeHex(inputPrimary)!;
    if (inputSecondary && normalizeHex(inputSecondary)) {
      secondary = normalizeHex(inputSecondary)!;
      background = backgroundTone === 'dark' ? '#0f172a' : '#F8F9FC';
      text = backgroundTone === 'dark' ? '#f8fafc' : '#1F2937';
    } else {
      const preset = resolvePreset(style);
      secondary = preset.secondary;
      background = preset.background;
      text = preset.text;
    }
  } else {
    const preset = resolvePreset(style);
    primary = preset.primary;
    secondary = preset.secondary;
    background = preset.background;
    text = preset.text;
  }

  const primaryLight = lighten(primary, 0.4);
  const primaryDark = darken(primary, 0.25);

  const neutral = buildNeutralScale(text);
  const muted = neutral[200];
  const accent = secondary;

  const palette: GeneratedColorPalette = {
    primary,
    'primary-light': primaryLight,
    'primary-dark': primaryDark,
    secondary,
    accent,
    background,
    text,
    muted,
    neutral,
    border: neutral[200],
    'primary-foreground': '#ffffff',
  };

  return palette;
}

/**
 * Converts a generated palette to flat CSS-ready entries (--color-primary, --color-primary-light, etc.).
 */
export function colorPaletteToCssVars(palette: GeneratedColorPalette, prefix = '--color-'): Record<string, string> {
  const vars: Record<string, string> = {};
  vars[`${prefix}primary`] = palette.primary;
  vars[`${prefix}primary-light`] = palette['primary-light'];
  vars[`${prefix}primary-dark`] = palette['primary-dark'];
  vars[`${prefix}secondary`] = palette.secondary;
  vars[`${prefix}accent`] = palette.accent;
  vars[`${prefix}background`] = palette.background;
  vars[`${prefix}text`] = palette.text;
  vars[`${prefix}muted`] = palette.muted;
  if (palette.border) vars[`${prefix}border`] = palette.border;
  if (palette['primary-foreground']) vars[`${prefix}primary-foreground`] = palette['primary-foreground'];
  for (const [key, value] of Object.entries(palette.neutral)) {
    vars[`${prefix}neutral-${key}`] = value;
  }
  return vars;
}
