/**
 * Part 7 — Button Style Generator.
 *
 * Generates button styles (primary, secondary, outline, ghost) using
 * design tokens so components use tokens instead of raw values.
 *
 * Example primary button:
 *   background: primary
 *   color: white (primary-foreground)
 *   radius: md
 *   padding: md
 */

// ---------------------------------------------------------------------------
// Types
// ---------------------------------------------------------------------------

export interface ButtonStyleGeneratorInput {
  /** Token references: colors.primary, spacing.md, radius.md, etc. */
  colorTokenPrefix?: string;
  spacingTokenPrefix?: string;
  radiusTokenPrefix?: string;
}

export interface ButtonStyle {
  background: string;
  color: string;
  border?: string;
  radius: string;
  padding: string;
  shadow?: string;
}

export interface ButtonStyles {
  primary: ButtonStyle;
  secondary: ButtonStyle;
  outline: ButtonStyle;
  ghost: ButtonStyle;
  destructive?: ButtonStyle;
}

// ---------------------------------------------------------------------------
// Token reference helpers (components resolve these to CSS vars)
// ---------------------------------------------------------------------------

const defaultColorPrefix = 'var(--color-';
const defaultSpacingPrefix = 'var(--spacing-';
const defaultRadiusPrefix = 'var(--radius-';
const defaultShadowPrefix = 'var(--shadow-';

function colorRef(name: string, prefix = defaultColorPrefix): string {
  return `${prefix}${name})`;
}
function spacingRef(name: string, prefix = defaultSpacingPrefix): string {
  return `${prefix}${name})`;
}
function radiusRef(name: string, prefix = defaultRadiusPrefix): string {
  return `${prefix}${name})`;
}
function shadowRef(name: string, prefix = defaultShadowPrefix): string {
  return `${prefix}${name})`;
}

// ---------------------------------------------------------------------------
// Public API
// ---------------------------------------------------------------------------

/**
 * Generates button styles that reference design tokens.
 * Components use these values (e.g. background: tokens.buttons.primary.background)
 * which resolve to var(--color-primary), etc.
 */
export function generateButtonStyles(input: ButtonStyleGeneratorInput = {}): ButtonStyles {
  const colorP = input.colorTokenPrefix ?? defaultColorPrefix.replace('var(', '');
  const spaceP = input.spacingTokenPrefix ?? defaultSpacingPrefix.replace('var(', '');
  const radiusP = input.radiusTokenPrefix ?? defaultRadiusPrefix.replace('var(', '');

  const c = (name: string) => (name.startsWith('var(') ? name : colorRef(name, `var(${colorP}`));
  const s = (name: string) => (name.startsWith('var(') ? name : spacingRef(name, `var(${spaceP}`));
  const r = (name: string) => (name.startsWith('var(') ? name : radiusRef(name, `var(${radiusP}`));
  const sh = (name: string) => (name.startsWith('var(') ? name : shadowRef(name, `var(${defaultShadowPrefix}`));

  return {
    primary: {
      background: c('primary'),
      color: c('primary-foreground'),
      radius: r('md'),
      padding: `${s('sm')} ${s('md')}`,
      shadow: sh('sm'),
    },
    secondary: {
      background: c('secondary'),
      color: c('secondary-foreground'),
      radius: r('md'),
      padding: `${s('sm')} ${s('md')}`,
    },
    outline: {
      background: 'transparent',
      color: c('primary'),
      border: `2px solid ${c('border')}`,
      radius: r('md'),
      padding: `${s('sm')} ${s('md')}`,
    },
    ghost: {
      background: 'transparent',
      color: c('foreground'),
      radius: r('md'),
      padding: `${s('sm')} ${s('md')}`,
    },
    destructive: {
      background: c('destructive'),
      color: c('destructive-foreground'),
      radius: r('md'),
      padding: `${s('sm')} ${s('md')}`,
    },
  };
}

/**
 * Returns button styles as CSS custom properties (--button-primary-background, etc.)
 * for use in :root when you want to expose full button tokens.
 */
export function buttonStylesToCssVars(styles: ButtonStyles, prefix = '--button-'): Record<string, string> {
  const vars: Record<string, string> = {};
  for (const [variant, style] of Object.entries(styles)) {
    if (!style || typeof style !== 'object') continue;
    for (const [prop, value] of Object.entries(style)) {
      const resolvedValue = typeof value === 'string' ? value : null;
      if (resolvedValue !== null && prop !== 'border') vars[`${prefix}${variant}-${prop}`] = resolvedValue;
    }
  }
  return vars;
}
