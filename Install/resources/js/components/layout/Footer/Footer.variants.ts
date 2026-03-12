/**
 * Footer layout variants — single source of truth for builder and codegen.
 */

export const FOOTER_VARIANTS = ['footer-1', 'footer-2', 'footer-3', 'footer-4'] as const;

export type FooterVariantId = (typeof FOOTER_VARIANTS)[number];

export const FOOTER_DEFAULT_VARIANT: FooterVariantId = 'footer-1';

export const FOOTER_VARIANT_OPTIONS = FOOTER_VARIANTS.map((value) => ({
  label: value
    .split('-')
    .map((s) => s.charAt(0).toUpperCase() + s.slice(1))
    .join(' '),
  value,
}));
