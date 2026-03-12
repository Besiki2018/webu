export type ProductBuyVariant = 'buy-1' | 'buy-2' | 'buy-3';

export interface WebuProductBuyProps {
  variant?: ProductBuyVariant;
  productId: string;
  price: number;
  addToCartUrl?: string;
  buyNowUrl?: string;
  quantity?: number;
  onQuantityChange?: (q: number) => void;
  basePath?: string;
  className?: string;
}
