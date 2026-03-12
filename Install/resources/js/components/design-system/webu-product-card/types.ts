export type ProductCardVariant =
  | 'classic' | 'minimal' | 'modern' | 'premium' | 'compact'
  | 'product-card-1' | 'product-card-2' | 'product-card-3' | 'product-card-4' | 'product-card-5';

export interface WebuProductCardProduct {
  id?: number | string;
  name: string;
  slug: string;
  price: string;
  old_price?: string | null;
  image_url?: string | null;
  url: string;
}

export interface WebuProductCardProps {
  variant?: ProductCardVariant;
  product: WebuProductCardProduct;
  basePath?: string;
  className?: string;
}
