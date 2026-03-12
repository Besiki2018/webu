/**
 * AI Layout Generator output schema.
 * AI generates layout JSON (sections with component + variant), not raw HTML.
 */

export interface LayoutSection {
  component: string;
  variant?: string;
  /** Optional CMS bindings (AI: string placeholders; runtime: resolved values). */
  bindings?: Record<string, unknown>;
  /** Optional key for section reorder / identity */
  id?: string;
}

export interface LayoutPage {
  page: string;
  sections: LayoutSection[];
}

export interface ThemeTokens {
  primary?: string;
  secondary?: string;
  font?: string;
  radius?: string;
}

/** Example layout JSON (AI-generated). Variants map to design-system components. */
export const DEFAULT_LAYOUT: LayoutPage = {
  page: 'home',
  sections: [
    { component: 'announcement', variant: 'announcement-1', id: 'announcement' },
    { component: 'header', variant: 'header-1', id: 'header' },
    { component: 'hero', variant: 'hero-1', id: 'hero' },
    { component: 'features', variant: 'features-1', id: 'features' },
    { component: 'product-grid', variant: 'classic', id: 'products' },
    { component: 'cta', variant: 'cta-1', id: 'cta' },
    { component: 'testimonials', variant: 'testimonials-1', id: 'testimonials' },
    { component: 'banner', variant: 'banner-1', id: 'banner' },
    { component: 'faq', variant: 'faq-1', id: 'faq' },
    { component: 'newsletter', variant: 'newsletter-1', id: 'newsletter' },
    { component: 'footer', variant: 'footer-1', id: 'footer' },
  ],
};
