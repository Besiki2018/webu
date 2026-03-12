import type { WebuWishlistProps, WishlistProduct } from './types';
import { Wishlist1 } from './variants/wishlist-1';

const VARIANTS = ['wishlist-1', 'wishlist-2'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'wishlist-1';

export type { WebuWishlistProps, WishlistProduct };

export function WebuWishlist({ variant, ...props }: WebuWishlistProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Wishlist1 {...props} />;
}
