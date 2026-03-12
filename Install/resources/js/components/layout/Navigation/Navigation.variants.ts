export const NAVIGATION_VARIANTS = ['navigation-1', 'navigation-2'] as const;
export type NavigationVariantId = (typeof NAVIGATION_VARIANTS)[number];
export const NAVIGATION_DEFAULT_VARIANT: NavigationVariantId = 'navigation-1';
