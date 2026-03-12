export type ProductGalleryVariant = 'gallery-1' | 'gallery-2' | 'gallery-3';

export interface WebuProductGalleryProps {
  variant?: ProductGalleryVariant;
  images: string[];
  productName?: string;
  className?: string;
}
