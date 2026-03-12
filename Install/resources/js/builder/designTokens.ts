/**
 * Phase 11 — Design Token System.
 *
 * Global design tokens: colors, fonts, spacing, radius, shadows.
 * Components use tokens instead of raw CSS (e.g. color: primary, spacing: lg).
 */

// ---------------------------------------------------------------------------
// Token definitions (semantic name → CSS value)
// ---------------------------------------------------------------------------

export const DESIGN_TOKEN_COLORS = {
  primary: '#0f172a',
  'primary-foreground': '#f8fafc',
  secondary: '#475569',
  'secondary-foreground': '#f8fafc',
  accent: '#3b82f6',
  'accent-foreground': '#ffffff',
  muted: '#f1f5f9',
  'muted-foreground': '#64748b',
  background: '#ffffff',
  foreground: '#0f172a',
  destructive: '#dc2626',
  border: '#e2e8f0',
  ring: '#3b82f6',
} as const;

export const DESIGN_TOKEN_FONTS = {
  sans: 'ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"',
  serif: 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif',
  mono: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
  'heading': 'ui-sans-serif, system-ui, sans-serif',
  'body': 'ui-sans-serif, system-ui, sans-serif',
} as const;

export const DESIGN_TOKEN_FONT_SIZES = {
  xs: '0.75rem',
  sm: '0.875rem',
  base: '1rem',
  lg: '1.125rem',
  xl: '1.25rem',
  '2xl': '1.5rem',
  '3xl': '1.875rem',
  '4xl': '2.25rem',
  '5xl': '3rem',
} as const;

export const DESIGN_TOKEN_SPACING = {
  none: '0',
  xs: '0.25rem',
  sm: '0.5rem',
  md: '1rem',
  lg: '1.5rem',
  xl: '2rem',
  '2xl': '2.5rem',
  '3xl': '3rem',
  '4xl': '4rem',
} as const;

export const DESIGN_TOKEN_RADIUS = {
  none: '0',
  sm: '0.25rem',
  md: '0.375rem',
  lg: '0.5rem',
  xl: '0.75rem',
  '2xl': '1rem',
  full: '9999px',
} as const;

export const DESIGN_TOKEN_SHADOWS = {
  none: 'none',
  sm: '0 1px 2px 0 rgb(0 0 0 / 0.05)',
  md: '0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
  lg: '0 10px 15px -3px rgb(0 0 0 / 0.1), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
  xl: '0 20px 25px -5px rgb(0 0 0 / 0.1), 0 8px 10px -6px rgb(0 0 0 / 0.1)',
} as const;

// ---------------------------------------------------------------------------
// Token categories (for schema / UI)
// ---------------------------------------------------------------------------

export type DesignTokenCategory = 'colors' | 'fonts' | 'fontSizes' | 'spacing' | 'radius' | 'shadows';

export const DESIGN_TOKEN_CATEGORIES: DesignTokenCategory[] = [
  'colors',
  'fonts',
  'fontSizes',
  'spacing',
  'radius',
  'shadows',
];

export const DESIGN_TOKEN_CATEGORY_LABELS: Record<DesignTokenCategory, string> = {
  colors: 'Colors',
  fonts: 'Fonts',
  fontSizes: 'Font sizes',
  spacing: 'Spacing',
  radius: 'Radius',
  shadows: 'Shadows',
};

// ---------------------------------------------------------------------------
// Resolve token to CSS value
// ---------------------------------------------------------------------------

const TOKEN_MAP: Record<DesignTokenCategory, Record<string, string>> = {
  colors: DESIGN_TOKEN_COLORS as Record<string, string>,
  fonts: DESIGN_TOKEN_FONTS as Record<string, string>,
  fontSizes: DESIGN_TOKEN_FONT_SIZES as Record<string, string>,
  spacing: DESIGN_TOKEN_SPACING as Record<string, string>,
  radius: DESIGN_TOKEN_RADIUS as Record<string, string>,
  shadows: DESIGN_TOKEN_SHADOWS as Record<string, string>,
};

/**
 * Resolves a token reference to its CSS value.
 * Examples: getTokenValue('colors', 'primary') → '#0f172a'; getTokenValue('spacing', 'lg') → '1.5rem'.
 */
export function getTokenValue(category: DesignTokenCategory, name: string): string | undefined {
  const map = TOKEN_MAP[category];
  if (!map) return undefined;
  return map[name];
}

/**
 * Resolves a token path "category.name" to CSS value.
 * Examples: resolveToken('color.primary') → '#0f172a'; resolveToken('spacing.lg') → '1.5rem'.
 * Accepts "color" as alias for "colors".
 */
export function resolveToken(path: string): string | undefined {
  const [cat, name] = path.split('.');
  if (!cat || !name) return undefined;
  const category = (cat === 'color' ? 'colors' : cat) as DesignTokenCategory;
  if (!DESIGN_TOKEN_CATEGORIES.includes(category)) return undefined;
  return getTokenValue(category, name);
}

// ---------------------------------------------------------------------------
// CSS custom properties (for :root or theme provider)
// ---------------------------------------------------------------------------

const CSS_VAR_PREFIX: Record<DesignTokenCategory, string> = {
  colors: 'color',
  fonts: 'font',
  fontSizes: 'font-size',
  spacing: 'spacing',
  radius: 'radius',
  shadows: 'shadow',
};

/**
 * Returns a flat object of CSS custom property names to values.
 * Use for :root { ...getDesignTokensAsCssVars() } or inline style.
 * Example: --color-primary, --spacing-lg, --radius-md, --font-size-lg, --shadow-md
 */
export function getDesignTokensAsCssVars(): Record<string, string> {
  const vars: Record<string, string> = {};
  for (const category of DESIGN_TOKEN_CATEGORIES) {
    const map = TOKEN_MAP[category];
    const prefix = CSS_VAR_PREFIX[category];
    for (const [name, value] of Object.entries(map)) {
      const kebab = name.replace(/([A-Z])/g, '-$1').toLowerCase().replace(/-+/g, '-');
      vars[`--${prefix}-${kebab}`] = value;
    }
  }
  return vars;
}

/** All design tokens as a single object (for export or builder schema defaults). */
export const DESIGN_TOKENS = {
  colors: DESIGN_TOKEN_COLORS,
  fonts: DESIGN_TOKEN_FONTS,
  fontSizes: DESIGN_TOKEN_FONT_SIZES,
  spacing: DESIGN_TOKEN_SPACING,
  radius: DESIGN_TOKEN_RADIUS,
  shadows: DESIGN_TOKEN_SHADOWS,
} as const;
