/**
 * Site plan types and runtime parsing for the ecommerce storefront.
 */

export type SectionType =
  | 'Header'
  | 'Footer'
  | 'HeroBanner'
  | 'CategoryGrid'
  | 'ProductGrid'
  | 'ProductDetails'
  | 'Cart'
  | 'Checkout'
  | 'FAQ'
  | string;

export interface SitePlanMeta {
  name?: string;
  language?: string;
  currency?: string;
}

export interface SitePlanTheme {
  mode?: 'light' | 'dark';
  primaryColor?: string;
  secondaryColor?: string;
  fontFamily?: string;
  radius?: string;
  buttonStyle?: string;
}

export interface SitePlanSection {
  id: string;
  type: SectionType;
  props?: Record<string, unknown>;
}

export interface SitePlanPage {
  id: string;
  route: string;
  title: string;
  sections: SitePlanSection[];
}

export interface SitePlan {
  meta?: SitePlanMeta;
  theme: SitePlanTheme;
  pages: SitePlanPage[];
}

/** Section type keys used in the storefront. */
export const SECTION_TYPES: SectionType[] = [
  'Header',
  'Footer',
  'HeroBanner',
  'CategoryGrid',
  'ProductGrid',
  'ProductDetails',
  'Cart',
  'Checkout',
  'FAQ',
];

/** Allowed route patterns. */
export const ALLOWED_ROUTES = ['/', '/shop', '/product/:id', '/cart', '/checkout', '/contact'];

/** Minimal schema-like objects for compatibility (no zod). */
export const sitePlanSchema = { _tag: 'sitePlan' as const };
export const metaSchema = { _tag: 'meta' as const };
export const themeSchema = { _tag: 'theme' as const };
export const sectionSchema = { _tag: 'section' as const };
export const sectionTypeSchema = { _tag: 'sectionType' as const };
export const pageSchema = { _tag: 'page' as const };
export const pageRouteSchema = { _tag: 'pageRoute' as const };

function isObject(v: unknown): v is Record<string, unknown> {
  return typeof v === 'object' && v !== null && !Array.isArray(v);
}

export function parseSitePlan(input: unknown): SitePlan {
  if (!isObject(input) || !isObject(input.theme) || !Array.isArray(input.pages)) {
    throw new Error('Invalid site plan: expected { theme, pages }');
  }
  return {
    meta: isObject(input.meta) ? (input.meta as SitePlanMeta) : undefined,
    theme: input.theme as SitePlanTheme,
    pages: (input.pages as SitePlanPage[]).map((p) => ({
      id: String((p as SitePlanPage).id ?? ''),
      route: String((p as SitePlanPage).route ?? '/'),
      title: String((p as SitePlanPage).title ?? ''),
      sections: Array.isArray((p as SitePlanPage).sections) ? ((p as SitePlanPage).sections as SitePlanSection[]) : [],
    })),
  };
}

export function parseSitePlanSafe(input: unknown): SitePlan | null {
  try {
    return parseSitePlan(input);
  } catch {
    return null;
  }
}
