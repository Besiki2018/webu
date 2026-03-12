export const GRID_VARIANTS = ['grid-1', 'grid-2'] as const;
export type GridVariantId = (typeof GRID_VARIANTS)[number];
export const GRID_DEFAULT_VARIANT: GridVariantId = 'grid-1';
