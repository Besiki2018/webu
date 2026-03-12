export const FEATURES_VARIANTS = ['features-1', 'features-2', 'features-3', 'features-4'] as const;
export type FeaturesVariantId = (typeof FEATURES_VARIANTS)[number];
export const FEATURES_DEFAULT_VARIANT: FeaturesVariantId = 'features-1';
