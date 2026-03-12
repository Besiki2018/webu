export const CTA_VARIANTS = ['cta-1', 'cta-2', 'cta-3', 'cta-4'] as const;
export type CtaVariantId = (typeof CTA_VARIANTS)[number];
export const CTA_DEFAULT_VARIANT: CtaVariantId = 'cta-1';
