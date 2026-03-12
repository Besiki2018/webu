/**
 * Part 10 — Design System Applier.
 *
 * Updates component styles to use design tokens instead of raw values.
 * - Normalize colors (hex/rgb → token refs)
 * - Normalize spacing (px/rem → token refs)
 * - Map semantic roles: hero background → colors.background, buttons → button.primary
 *
 * Example transformation:
 *   hero backgroundColor: '#ffffff' → var(--color-background)
 *   buttons → use button.primary (CSS vars from design tokens)
 */

import type { DesignTokens } from './tokens';
import { designTokens } from './tokens';

// ---------------------------------------------------------------------------
// Token refs (CSS var names)
// ---------------------------------------------------------------------------

const COLOR_VAR_PREFIX = 'var(--color-';
const SPACING_VAR_PREFIX = 'var(--spacing-';
const RADIUS_VAR_PREFIX = 'var(--radius-';
const SHADOW_VAR_PREFIX = 'var(--shadow-';

// ---------------------------------------------------------------------------
// Color normalization
// ---------------------------------------------------------------------------

/** Hex to simple comparable form (lowercase, no #). */
function hexNorm(hex: string): string {
  let h = hex.replace(/^#/, '').toLowerCase();
  if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
  return h;
}

/** Find closest color token by hex (simple distance). */
function closestColorToken(hex: string, tokens: DesignTokens): string | undefined {
  const colors = tokens.colors as Record<string, string>;
  let best: string | undefined;
  let bestDist = Infinity;
  const target = hexNorm(hex);
  const r = (s: string) => parseInt(s.slice(0, 2), 16);
  const g = (s: string) => parseInt(s.slice(2, 4), 16);
  const b = (s: string) => parseInt(s.slice(4, 6), 16);
  if (target.length !== 6) return undefined;
  const tr = r(target), tg = g(target), tb = b(target);
  for (const [name, value] of Object.entries(colors)) {
    const v = String(value).trim();
    if (!v.startsWith('#')) continue;
    const h = hexNorm(v);
    if (h.length !== 6) continue;
    const dr = tr - r(h), dg = tg - g(h), db = tb - b(h);
    const dist = dr * dr + dg * dg + db * db;
    if (dist < bestDist) {
      bestDist = dist;
      best = name;
    }
  }
  return best;
}

/**
 * Normalize a color value to a token reference when possible.
 * - If already var(--color-*), return as-is.
 * - If hex/rgb, find closest token and return var(--color-<name>).
 * - Otherwise return original.
 */
export function normalizeColor(
  value: string | undefined,
  tokens: DesignTokens = designTokens
): string | undefined {
  if (value == null || value === '') return value;
  const v = value.trim();
  if (v.startsWith('var(--color-')) return v;
  if (v.startsWith('#')) {
    const name = closestColorToken(v, tokens);
    if (name) return `${COLOR_VAR_PREFIX}${name.replace(/([A-Z])/g, '-$1').toLowerCase().replace(/-+/g, '-')})`;
  }
  // rgb/rgba: could parse and match; for now leave as-is or map common ones
  return value;
}

// ---------------------------------------------------------------------------
// Spacing normalization
// ---------------------------------------------------------------------------

const PX_NUM = /^(\d+(?:\.\d+)?)\s*px$/i;
const REM_NUM = /^(\d+(?:\.\d+)?)\s*rem$/i;

/** Map px value to closest spacing token key. */
function closestSpacingToken(px: number, tokens: DesignTokens): string | undefined {
  const spacing = tokens.spacing as Record<string, string>;
  const toPx = (s: string): number => {
    if (s === '0' || s === 'none') return 0;
    const m = s.match(/^([\d.]+)\s*rem$/);
    if (m) return parseFloat(m[1]) * 16;
    const m2 = s.match(/^([\d.]+)\s*px$/);
    if (m2) return parseFloat(m2[1]);
    return parseFloat(s) || 0;
  };
  const entries = Object.entries(spacing).map(([k, v]) => [k, toPx(v)] as [string, number]);
  let best: string | undefined;
  let bestDist = Infinity;
  for (const [name, val] of entries) {
    const d = Math.abs(val - px);
    if (d < bestDist) {
      bestDist = d;
      best = name;
    }
  }
  return best;
}

/**
 * Normalize a spacing value to a token reference when possible.
 * - If already var(--spacing-*), return as-is.
 * - If px/rem, map to closest token and return var(--spacing-<name>).
 */
export function normalizeSpacing(
  value: string | undefined,
  tokens: DesignTokens = designTokens
): string | undefined {
  if (value == null || value === '') return value;
  const v = value.trim();
  if (v.startsWith('var(--spacing-')) return v;
  let px: number | undefined;
  const pxMatch = v.match(PX_NUM);
  if (pxMatch) px = parseFloat(pxMatch[1]);
  else {
    const remMatch = v.match(REM_NUM);
    if (remMatch) px = parseFloat(remMatch[1]) * 16;
  }
  if (px !== undefined) {
    const name = closestSpacingToken(px, tokens);
    if (name) return `${SPACING_VAR_PREFIX}${name})`;
  }
  return value;
}

// ---------------------------------------------------------------------------
// Semantic mapping (component role → token)
// ---------------------------------------------------------------------------

/** Props that are treated as "background" and map to colors.background when missing. */
const BACKGROUND_PROPS = ['backgroundColor', 'background'] as const;

/** Props that are treated as spacing (padding, margin, gap, etc.). */
const SPACING_PROPS = ['padding', 'margin', 'spacing', 'gap', 'paddingTop', 'paddingBottom', 'paddingLeft', 'paddingRight'] as const;

/** Component-specific semantic defaults (prop → token path). */
const SEMANTIC_DEFAULTS: Record<string, Record<string, string>> = {
  hero: {
    backgroundColor: 'var(--color-background)',
    color: 'var(--color-foreground)',
  },
  cta: {
    backgroundColor: 'var(--color-primary)',
    color: 'var(--color-primary-foreground)',
  },
  header: {
    backgroundColor: 'var(--color-background)',
    borderColor: 'var(--color-border)',
  },
  footer: {
    backgroundColor: 'var(--color-muted)',
    color: 'var(--color-foreground)',
  },
};

/**
 * Apply token normalization to a single section's props.
 * - Normalize known color props (backgroundColor, color, borderColor, etc.)
 * - Normalize known spacing props
 * - Apply semantic defaults for component type (e.g. hero → colors.background)
 */
export function applyTokensToSectionProps(
  componentType: string,
  props: Record<string, unknown>,
  tokens: DesignTokens = designTokens
): Record<string, unknown> {
  const out = { ...props };
  const component = (componentType || '').toLowerCase();

  const colorProps = ['backgroundColor', 'background', 'color', 'borderColor', 'border'];
  for (const key of colorProps) {
    if (key in out && typeof out[key] === 'string') {
      const normalized = normalizeColor(out[key] as string, tokens);
      if (normalized != null) out[key] = normalized;
    }
  }

  for (const key of SPACING_PROPS) {
    if (key in out && typeof out[key] === 'string') {
      const normalized = normalizeSpacing(out[key] as string, tokens);
      if (normalized != null) out[key] = normalized;
    }
  }

  const semantic = SEMANTIC_DEFAULTS[component];
  if (semantic) {
    for (const [prop, tokenValue] of Object.entries(semantic)) {
      if (out[prop] === undefined || out[prop] === '' || out[prop] === null) {
        out[prop] = tokenValue;
      }
    }
  }

  return out;
}

/** Layout section shape (id, component, variant, props). */
export interface LayoutSectionLike {
  id?: string;
  component?: string;
  variant?: string;
  [key: string]: unknown;
}

/**
 * Apply design tokens across all sections in a layout.
 * Each section's props are normalized (colors, spacing) and semantic defaults applied.
 */
export function applyTokensToLayout(
  sections: LayoutSectionLike[],
  tokens: DesignTokens = designTokens
): LayoutSectionLike[] {
  return sections.map((section) => {
    const component = (section.component ?? section.section ?? '') as string;
    const props = (section.props ?? section.bindings ?? section) as Record<string, unknown>;
    const applied = applyTokensToSectionProps(component, props, tokens);
    return { ...section, props: applied };
  });
}

/**
 * Resolve button style to CSS var usage.
 * Use in components: primary button → background: var(--color-primary), etc.
 */
export function getButtonTokenRefs(variant: 'primary' | 'secondary' | 'outline' | 'ghost' | 'destructive'): {
  background: string;
  color: string;
  radius: string;
  padding: string;
  border?: string;
} {
  const buttons = designTokens.buttons;
  const style = buttons[variant as keyof typeof buttons];
  if (!style) return buttons.primary as ReturnType<typeof getButtonTokenRefs>;
  return style as ReturnType<typeof getButtonTokenRefs>;
}
