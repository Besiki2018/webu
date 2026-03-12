export type WishlistVariant = 'wishlist-1' | 'wishlist-2';

export interface WishlistProduct {
  id: string;
  name: string;
  price: number;
  image?: string;
  url?: string;
}

export interface WebuWishlistProps {
  variant?: WishlistVariant;
  title?: string;
  products: WishlistProduct[];
  basePath?: string;
  className?: string;
}
