export const CARDS_VARIANTS = ['cards-1', 'cards-2'] as const;
export type CardsVariantId = (typeof CARDS_VARIANTS)[number];
export const CARDS_DEFAULT_VARIANT: CardsVariantId = 'cards-1';
