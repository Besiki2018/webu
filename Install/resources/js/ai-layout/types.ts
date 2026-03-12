/**
 * AI Layout Generator — TypeScript types.
 * Layout is JSON only; no raw HTML. All content via CMS bindings.
 */

export interface LayoutSection {
  component: string;
  variant?: string;
  bindings?: Record<string, string>;
}

export interface AILayoutSchema {
  page?: string;
  sections: LayoutSection[];
}

export interface StructuredInput {
  business_type?: string;
  industry?: string;
  design_style?: string;
  color_scheme?: string;
  sections_required?: string[];
}

export interface ThemeTokens {
  primary_color?: string;
  secondary_color?: string;
  font_family?: string;
  border_radius?: string;
}

/** CMS data shape used to resolve bindings (site, products, categories, etc.) */
export interface CMSData {
  site?: {
    name?: string;
    logo?: string;
    navigation?: { label: string; url: string }[];
    hero?: { title?: string; subtitle?: string; image?: string; cta_text?: string; cta_url?: string };
    footer?: { links?: { label: string; url: string }[]; contact?: string; copyright?: string };
    categories_title?: string;
    featured_title?: string;
    cart_title?: string;
    cart_empty_message?: string;
  };
  products?: {
    featured?: Array<{
      id: string;
      slug: string;
      title: string;
      price: number;
      currency: string;
      image: string;
      badge?: string | null;
      rating?: number | null;
    }>;
    latest?: unknown[];
  };
  categories?: {
    main?: Array<{ id: string; title: string; image: string; link: string }>;
    featured?: Array<{ id: string; title: string; image: string; link: string }>;
  };
  banners?: {
    home?: { title?: string; subtitle?: string; cta_text?: string; cta_url?: string };
    promo?: { title?: string; subtitle?: string; cta_text?: string; cta_url?: string };
  };
  newsletter?: {
    form?: { title?: string; subtitle?: string };
  };
}
