/**
 * Part 1 — AI Design System Generator.
 *
 * Creates a consistent design system for a website inside Webu from:
 * - projectType (e.g. saas, landing, ecommerce)
 * - industry (e.g. AI tools)
 * - designStyle (e.g. modern, minimal, bold)
 * - brandName (e.g. Webu)
 *
 * Output (design tokens):
 * - colors — color palette (primary, accent, background, etc.)
 * - typography — font families, font sizes, weights, line heights
 * - spacing — spacing scale (none, xs, sm, md, lg, xl, …)
 * - radius — border radius system (sm, md, lg, button, card, …)
 * - shadows — shadow system (none, sm, md, lg, card, elevated)
 * - buttonStyles — primary, secondary, outline, ghost, destructive
 *
 * All generated tokens are usable by builder components via CSS variables
 * (designSystemToCssVars for --webu-token-*, designSystemToBuilderCssVars for
 * builder’s --color-*, --spacing-*, etc.).
 *
 * Example input:  { projectType: 'saas', industry: 'AI tools', designStyle: 'modern', brandName: 'Webu' }
 * Example result: { colors, typography, spacing, radius, shadows, buttonStyles }
 */

// ---------------------------------------------------------------------------
// Input
// ---------------------------------------------------------------------------

export interface DesignSystemGeneratorInput {
  projectType: string;
  industry: string;
  designStyle: string;
  brandName: string;
}

// ---------------------------------------------------------------------------
// Output (design tokens)
// ---------------------------------------------------------------------------

export interface GeneratedColors {
  primary: string;
  'primary-foreground': string;
  secondary: string;
  'secondary-foreground': string;
  accent: string;
  'accent-foreground': string;
  background: string;
  foreground: string;
  muted: string;
  'muted-foreground': string;
  border: string;
  destructive: string;
  'destructive-foreground': string;
  ring: string;
  surface?: string;
  'on-primary'?: string;
  [key: string]: string | undefined;
}

export interface GeneratedTypography {
  fontFamily: Record<string, string>;
  fontSizes: Record<string, string>;
  fontWeights: Record<string, string>;
  lineHeights: Record<string, string>;
}

export interface GeneratedSpacing {
  none: string;
  xs: string;
  sm: string;
  md: string;
  lg: string;
  xl: string;
  '2xl': string;
  '3xl': string;
  '4xl': string;
  [key: string]: string;
}

export interface GeneratedRadius {
  none: string;
  sm: string;
  base: string;
  md: string;
  lg: string;
  xl: string;
  '2xl': string;
  button: string;
  card: string;
  full: string;
  [key: string]: string;
}

export interface GeneratedShadows {
  none: string;
  sm: string;
  md: string;
  lg: string;
  xl: string;
  card: string;
  elevated: string;
  [key: string]: string;
}

export interface GeneratedButtonStyles {
  primary: { background: string; color: string; border?: string; radius: string; shadow?: string };
  secondary: { background: string; color: string; border?: string; radius: string; shadow?: string };
  outline: { background: string; color: string; border: string; radius: string };
  ghost: { background: string; color: string; border?: string; radius: string };
  destructive?: { background: string; color: string; border?: string; radius: string };
}

export interface GeneratedDesignSystem {
  colors: GeneratedColors;
  typography: GeneratedTypography;
  spacing: GeneratedSpacing;
  radius: GeneratedRadius;
  shadows: GeneratedShadows;
  buttonStyles: GeneratedButtonStyles;
  /** Optional: global CSS variable prefix (e.g. --webu-token-). */
  cssVarPrefix?: string;
}

// ---------------------------------------------------------------------------
// Palettes by style + industry
// ---------------------------------------------------------------------------

const PALETTES: Record<string, Record<string, string>> = {
  modern: {
    primary: '#2563eb',
    'primary-foreground': '#ffffff',
    secondary: '#f1f5f9',
    'secondary-foreground': '#0f172a',
    accent: '#7c3aed',
    'accent-foreground': '#ffffff',
    background: '#ffffff',
    foreground: '#0f172a',
    muted: '#f1f5f9',
    'muted-foreground': '#64748b',
    border: '#e2e8f0',
    destructive: '#dc2626',
    'destructive-foreground': '#ffffff',
    ring: '#2563eb',
    surface: '#ffffff',
    'on-primary': '#ffffff',
  },
  minimal: {
    primary: '#18181b',
    'primary-foreground': '#fafafa',
    secondary: '#f4f4f5',
    'secondary-foreground': '#18181b',
    accent: '#18181b',
    'accent-foreground': '#fafafa',
    background: '#ffffff',
    foreground: '#18181b',
    muted: '#f4f4f5',
    'muted-foreground': '#71717a',
    border: '#e4e4e7',
    destructive: '#ef4444',
    'destructive-foreground': '#ffffff',
    ring: '#18181b',
    surface: '#ffffff',
    'on-primary': '#fafafa',
  },
  bold: {
    primary: '#7c3aed',
    'primary-foreground': '#ffffff',
    secondary: '#f5f3ff',
    'secondary-foreground': '#5b21b6',
    accent: '#ec4899',
    'accent-foreground': '#ffffff',
    background: '#fafafa',
    foreground: '#0f172a',
    muted: '#f5f5f5',
    'muted-foreground': '#737373',
    border: '#e5e5e5',
    destructive: '#dc2626',
    'destructive-foreground': '#ffffff',
    ring: '#7c3aed',
    surface: '#ffffff',
    'on-primary': '#ffffff',
  },
  professional: {
    primary: '#0f766e',
    'primary-foreground': '#ffffff',
    secondary: '#ccfbf1',
    'secondary-foreground': '#134e4a',
    accent: '#0d9488',
    'accent-foreground': '#ffffff',
    background: '#ffffff',
    foreground: '#0f172a',
    muted: '#f0fdfa',
    'muted-foreground': '#5eead4',
    border: '#99f6e4',
    destructive: '#b91c1c',
    'destructive-foreground': '#ffffff',
    ring: '#0f766e',
    surface: '#ffffff',
    'on-primary': '#ffffff',
  },
  warm: {
    primary: '#c2410c',
    'primary-foreground': '#ffffff',
    secondary: '#ffedd5',
    'secondary-foreground': '#9a3412',
    accent: '#ea580c',
    'accent-foreground': '#ffffff',
    background: '#fffbeb',
    foreground: '#1c1917',
    muted: '#fef3c7',
    'muted-foreground': '#b45309',
    border: '#fed7aa',
    destructive: '#b91c1c',
    'destructive-foreground': '#ffffff',
    ring: '#c2410c',
    surface: '#ffffff',
    'on-primary': '#ffffff',
  },
};

// ---------------------------------------------------------------------------
// Typography scales
// ---------------------------------------------------------------------------

const TYPOGRAPHY_SCALES: Record<string, Record<string, string>> = {
  modern: {
    xs: '0.75rem',
    sm: '0.875rem',
    base: '1rem',
    lg: '1.125rem',
    xl: '1.25rem',
    '2xl': '1.5rem',
    '3xl': '1.875rem',
    '4xl': '2.25rem',
    '5xl': '3rem',
  },
  compact: {
    xs: '0.6875rem',
    sm: '0.8125rem',
    base: '0.9375rem',
    lg: '1.0625rem',
    xl: '1.1875rem',
    '2xl': '1.375rem',
    '3xl': '1.625rem',
    '4xl': '2rem',
    '5xl': '2.5rem',
  },
  spacious: {
    xs: '0.8125rem',
    sm: '0.9375rem',
    base: '1.0625rem',
    lg: '1.25rem',
    xl: '1.5rem',
    '2xl': '1.875rem',
    '3xl': '2.25rem',
    '4xl': '3rem',
    '5xl': '3.75rem',
  },
};

const FONT_WEIGHTS: Record<string, string> = {
  normal: '400',
  medium: '500',
  semibold: '600',
  bold: '700',
};

const LINE_HEIGHTS: Record<string, string> = {
  tight: '1.25',
  snug: '1.375',
  normal: '1.5',
  relaxed: '1.625',
  loose: '2',
};

// ---------------------------------------------------------------------------
// Spacing systems
// ---------------------------------------------------------------------------

const SPACING_SYSTEMS: Record<string, Record<string, string>> = {
  tight: {
    none: '0',
    xs: '0.25rem',
    sm: '0.5rem',
    md: '0.75rem',
    lg: '1rem',
    xl: '1.5rem',
    '2xl': '2rem',
    '3xl': '2.5rem',
    '4xl': '3rem',
  },
  moderate: {
    none: '0',
    xs: '0.25rem',
    sm: '0.5rem',
    md: '1rem',
    lg: '1.5rem',
    xl: '2rem',
    '2xl': '2.5rem',
    '3xl': '3rem',
    '4xl': '4rem',
  },
  spacious: {
    none: '0',
    xs: '0.5rem',
    sm: '0.75rem',
    md: '1.25rem',
    lg: '2rem',
    xl: '2.5rem',
    '2xl': '3rem',
    '3xl': '4rem',
    '4xl': '5rem',
  },
};

// ---------------------------------------------------------------------------
// Border radius systems
// ---------------------------------------------------------------------------

const RADIUS_SYSTEMS: Record<string, Record<string, string>> = {
  sharp: {
    none: '0',
    sm: '0.125rem',
    base: '0.25rem',
    md: '0.375rem',
    lg: '0.5rem',
    xl: '0.5rem',
    '2xl': '0.5rem',
    button: '0.25rem',
    card: '0.375rem',
    full: '9999px',
  },
  rounded: {
    none: '0',
    sm: '0.25rem',
    base: '0.5rem',
    md: '0.5rem',
    lg: '0.75rem',
    xl: '1rem',
    '2xl': '1rem',
    button: '0.5rem',
    card: '0.75rem',
    full: '9999px',
  },
  pill: {
    none: '0',
    sm: '0.375rem',
    base: '0.75rem',
    md: '0.75rem',
    lg: '1rem',
    xl: '1.25rem',
    '2xl': '1.5rem',
    button: '9999px',
    card: '1rem',
    full: '9999px',
  },
};

// ---------------------------------------------------------------------------
// Shadow systems
// ---------------------------------------------------------------------------

const SHADOW_SYSTEMS: Record<string, Record<string, string>> = {
  flat: {
    none: 'none',
    sm: '0 1px 2px rgb(0 0 0 / 0.04)',
    md: '0 2px 4px rgb(0 0 0 / 0.06)',
    lg: '0 4px 8px rgb(0 0 0 / 0.08)',
    xl: '0 8px 16px rgb(0 0 0 / 0.1)',
    card: '0 1px 2px rgb(0 0 0 / 0.05)',
    elevated: '0 4px 12px rgb(0 0 0 / 0.08)',
  },
  soft: {
    none: 'none',
    sm: '0 1px 3px rgb(0 0 0 / 0.06)',
    md: '0 4px 6px -1px rgb(0 0 0 / 0.08), 0 2px 4px -2px rgb(0 0 0 / 0.06)',
    lg: '0 10px 15px -3px rgb(0 0 0 / 0.08), 0 4px 6px -4px rgb(0 0 0 / 0.06)',
    xl: '0 20px 25px -5px rgb(0 0 0 / 0.08), 0 8px 10px -6px rgb(0 0 0 / 0.06)',
    card: '0 1px 3px rgb(0 0 0 / 0.06)',
    elevated: '0 8px 24px rgb(0 0 0 / 0.1)',
  },
  strong: {
    none: 'none',
    sm: '0 1px 2px rgb(0 0 0 / 0.08)',
    md: '0 4px 6px -1px rgb(0 0 0 / 0.12), 0 2px 4px -2px rgb(0 0 0 / 0.1)',
    lg: '0 10px 15px -3px rgb(0 0 0 / 0.12), 0 4px 6px -4px rgb(0 0 0 / 0.1)',
    xl: '0 20px 25px -5px rgb(0 0 0 / 0.15), 0 8px 10px -6px rgb(0 0 0 / 0.1)',
    card: '0 2px 8px rgb(0 0 0 / 0.1)',
    elevated: '0 12px 32px rgb(0 0 0 / 0.15)',
  },
};

// ---------------------------------------------------------------------------
// Style mapping (input designStyle → palette keys)
// ---------------------------------------------------------------------------

function resolveStyleKey(designStyle: string, projectType: string): string {
  const s = (designStyle || 'modern').trim().toLowerCase();
  const p = (projectType || '').trim().toLowerCase();
  if (s === 'minimal' || s === 'clean') return 'minimal';
  if (s === 'bold' || s === 'vibrant') return 'bold';
  if (s === 'professional' || s === 'corporate') return 'professional';
  if (s === 'warm' || s === 'friendly') return 'warm';
  if (s === 'modern' || s === 'tech' || s === 'contemporary') return 'modern';
  if (p === 'saas' && !s) return 'professional';
  return 'modern';
}

function resolveSpacingKey(designStyle: string): string {
  const s = (designStyle || '').trim().toLowerCase();
  if (s === 'minimal' || s === 'compact') return 'tight';
  if (s === 'spacious' || s === 'airy') return 'spacious';
  return 'moderate';
}

function resolveRadiusKey(designStyle: string): string {
  const s = (designStyle || '').trim().toLowerCase();
  if (s === 'sharp' || s === 'minimal') return 'sharp';
  if (s === 'pill' || s === 'rounded-full') return 'pill';
  return 'rounded';
}

function resolveShadowKey(designStyle: string): string {
  const s = (designStyle || '').trim().toLowerCase();
  if (s === 'flat' || s === 'minimal') return 'flat';
  if (s === 'strong' || s === 'bold') return 'strong';
  return 'soft';
}

function resolveTypographyScaleKey(designStyle: string, projectType: string): string {
  const s = (designStyle || '').trim().toLowerCase();
  const p = (projectType || '').trim().toLowerCase();
  if (s === 'compact' || p === 'ecommerce') return 'compact';
  if (s === 'spacious' || s === 'editorial') return 'spacious';
  return 'modern';
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

const DEFAULT_FONT_FAMILIES: Record<string, string> = {
  sans: 'ui-sans-serif, system-ui, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol", "Noto Color Emoji"',
  serif: 'ui-serif, Georgia, Cambria, "Times New Roman", Times, serif',
  mono: 'ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace',
  heading: 'ui-sans-serif, system-ui, sans-serif',
  body: 'ui-sans-serif, system-ui, sans-serif',
};

/**
 * Generates a full design system from project type, industry, design style, and brand.
 * Tokens are compatible with builder components (--webu-token-* CSS variables).
 *
 * @example
 * generateDesignSystem({
 *   projectType: 'saas',
 *   industry: 'AI tools',
 *   designStyle: 'modern',
 *   brandName: 'Webu',
 * })
 */
export function generateDesignSystem(input: DesignSystemGeneratorInput): GeneratedDesignSystem {
  const { projectType, industry: _industry, designStyle, brandName: _brandName } = input;

  const styleKey = resolveStyleKey(designStyle, projectType);
  const palette = PALETTES[styleKey] ?? PALETTES.modern;
  const spacing = SPACING_SYSTEMS[resolveSpacingKey(designStyle)] ?? SPACING_SYSTEMS.moderate;
  const radius = RADIUS_SYSTEMS[resolveRadiusKey(designStyle)] ?? RADIUS_SYSTEMS.rounded;
  const shadows = SHADOW_SYSTEMS[resolveShadowKey(designStyle)] ?? SHADOW_SYSTEMS.soft;
  const typoScaleKey = resolveTypographyScaleKey(designStyle, projectType);
  const fontSizes = TYPOGRAPHY_SCALES[typoScaleKey] ?? TYPOGRAPHY_SCALES.modern;

  const colors = { ...PALETTES.modern, ...palette } as GeneratedColors;

  const typography: GeneratedTypography = {
    fontFamily: { ...DEFAULT_FONT_FAMILIES },
    fontSizes: { ...fontSizes },
    fontWeights: { ...FONT_WEIGHTS },
    lineHeights: { ...LINE_HEIGHTS },
  };

  const spacingSystem = { ...SPACING_SYSTEMS.moderate, ...spacing } as GeneratedSpacing;

  const radiusSystem = { ...RADIUS_SYSTEMS.rounded, ...radius } as GeneratedRadius;

  const shadowSystem = { ...SHADOW_SYSTEMS.soft, ...shadows } as GeneratedShadows;

  const buttonStyles: GeneratedButtonStyles = {
    primary: {
      background: colors.primary ?? palette.primary,
      color: colors['primary-foreground'] ?? palette['primary-foreground'],
      radius: radius.button ?? radius.base,
      shadow: shadows.sm,
    },
    secondary: {
      background: colors.secondary ?? palette.secondary,
      color: colors['secondary-foreground'] ?? palette['secondary-foreground'],
      radius: radius.button ?? radius.base,
      shadow: shadows.none,
    },
    outline: {
      background: 'transparent',
      color: colors.primary ?? palette.primary,
      border: (colors.border ?? palette.border) + ' 2px solid',
      radius: radius.button ?? radius.base,
    },
    ghost: {
      background: 'transparent',
      color: colors.foreground ?? palette.foreground,
      radius: radius.button ?? radius.base,
    },
    destructive: {
      background: colors.destructive ?? palette.destructive,
      color: colors['destructive-foreground'] ?? palette['destructive-foreground'],
      radius: radius.button ?? radius.base,
    },
  };

  return {
    colors,
    typography,
    spacing: spacingSystem,
    radius: radiusSystem,
    shadows: shadowSystem,
    buttonStyles,
    cssVarPrefix: '--webu-token-',
  };
}

/**
 * Converts a generated design system to CSS custom properties (--webu-token-*)
 * for use in :root or builder preview.
 */
export function designSystemToCssVars(system: GeneratedDesignSystem): Record<string, string> {
  const prefix = system.cssVarPrefix ?? '--webu-token-';
  const vars: Record<string, string> = {};

  for (const [name, value] of Object.entries(system.colors)) {
    if (value) vars[`${prefix}color-${name}`] = value;
  }
  for (const [name, value] of Object.entries(system.spacing)) {
    vars[`${prefix}space-${name}`] = value;
  }
  for (const [name, value] of Object.entries(system.radius)) {
    vars[`${prefix}radius-${name}`] = value;
  }
  for (const [name, value] of Object.entries(system.shadows)) {
    vars[`${prefix}shadow-${name}`] = value;
  }
  for (const [name, value] of Object.entries(system.typography.fontSizes)) {
    vars[`${prefix}font-size-${name}`] = value;
  }
  for (const [name, value] of Object.entries(system.typography.fontFamily)) {
    vars[`${prefix}font-${name}`] = value;
  }

  return vars;
}

/**
 * Converts a generated design system to builder-compatible CSS custom properties.
 * Uses the same variable names as builder/designTokens.ts (--color-primary, --spacing-lg,
 * --radius-md, --font-size-lg, --font-sans, --shadow-md) so that applying this to :root
 * makes all builder components use the generated design system.
 */
export function designSystemToBuilderCssVars(system: GeneratedDesignSystem): Record<string, string> {
  const vars: Record<string, string> = {};

  for (const [name, value] of Object.entries(system.colors)) {
    if (value) {
      const kebab = name.replace(/([A-Z])/g, '-$1').toLowerCase().replace(/-+/g, '-');
      vars[`--color-${kebab}`] = value;
    }
  }
  for (const [name, value] of Object.entries(system.spacing)) {
    const kebab = name.replace(/([A-Z])/g, '-$1').toLowerCase().replace(/-+/g, '-');
    vars[`--spacing-${kebab}`] = value;
  }
  for (const [name, value] of Object.entries(system.radius)) {
    const kebab = name.replace(/([A-Z])/g, '-$1').toLowerCase().replace(/-+/g, '-');
    vars[`--radius-${kebab}`] = value;
  }
  for (const [name, value] of Object.entries(system.shadows)) {
    const kebab = name.replace(/([A-Z])/g, '-$1').toLowerCase().replace(/-+/g, '-');
    vars[`--shadow-${kebab}`] = value;
  }
  for (const [name, value] of Object.entries(system.typography.fontSizes)) {
    const kebab = name.replace(/([A-Z])/g, '-$1').toLowerCase().replace(/-+/g, '-');
    vars[`--font-size-${kebab}`] = value;
  }
  for (const [name, value] of Object.entries(system.typography.fontFamily)) {
    const kebab = name.replace(/([A-Z])/g, '-$1').toLowerCase().replace(/-+/g, '-');
    vars[`--font-${kebab}`] = value;
  }

  return vars;
}
