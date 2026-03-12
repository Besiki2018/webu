import type { WebuProductCardProduct } from '../webu-product-card/types';

/** Layout variants (grid-1..4) map to product-card style; classic/minimal etc. pass through. */
export type WebuProductGridVariant =
  | 'classic' | 'minimal' | 'modern' | 'premium' | 'compact'
  | 'grid-1' | 'grid-2' | 'grid-3' | 'grid-4';

export interface WebuProductGridProps {
  title?: string;
  products: WebuProductCardProduct[];
  variant?: WebuProductGridVariant;
  basePath?: string;
  className?: string;
}
