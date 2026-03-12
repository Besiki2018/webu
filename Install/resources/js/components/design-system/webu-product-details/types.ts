export type ProductDetailsVariant = 'details-1' | 'details-2' | 'details-3';

export interface ProductVariantOption {
  name: string;
  value: string;
}

export interface WebuProductDetailsProps {
  variant?: ProductDetailsVariant;
  title: string;
  price: number;
  compareAtPrice?: number;
  description?: string;
  variants?: ProductVariantOption[];
  stock?: number;
  sku?: string;
  className?: string;
}
