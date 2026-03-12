import { Link } from '@inertiajs/react';
import { Star } from 'lucide-react';
import type { SectionInjectedProps } from './types';
import { path } from './types';
import { useCartStore } from '@/ecommerce/store/cartStore';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type ProductCardVariant = 'classic' | 'minimal' | 'modern' | 'premium' | 'compact';

export interface ProductCardProps extends SectionInjectedProps {
  id: string;
  slug: string;
  title: string;
  price: number;
  currency?: string;
  image: string;
  badge?: string | null;
  rating?: number | null;
  showAddToCart?: boolean;
  variant?: ProductCardVariant;
  className?: string;
}

export function ProductCard({
  basePath,
  id,
  slug,
  title,
  price,
  currency = 'GEL',
  image,
  badge,
  rating,
  showAddToCart = true,
  variant,
  className,
}: ProductCardProps) {
  const add = useCartStore((s) => s.add);
  const productUrl = path(basePath, `/product/${slug}`);
  const handleAddToCart = (e: React.MouseEvent) => {
    e.preventDefault();
    add({ productId: id, slug, title, price, image, quantity: 1 });
  };
  const variantClass = variant ? `webu-product-card--${variant}` : null;
  return (
    <article className={cn('webu-product-card', variantClass, className)}>
      <Link href={productUrl} className="block flex-1 min-h-0">
        <div className="webu-product-card__image-wrap">
          <img src={image} alt={title} className="webu-product-card__image" />
          {badge && (
            <span className="webu-product-card__badge">{badge}</span>
          )}
        </div>
        <div className="webu-product-card__body">
          <h3 className="webu-product-card__title">{title}</h3>
          {rating != null && (
            <div className="webu-product-card__rating">
              <Star className="h-4 w-4 fill-amber-400 text-amber-400" aria-hidden />
              <span>{rating.toFixed(1)}</span>
            </div>
          )}
          <p className="webu-product-card__price">{price.toFixed(2)} {currency}</p>
        </div>
      </Link>
      {showAddToCart && (
        <div className="webu-product-card__actions">
          <Button size="sm" className="w-full" onClick={handleAddToCart}>Add to cart</Button>
        </div>
      )}
    </article>
  );
}

ProductCard.defaultProps = { currency: 'GEL', showAddToCart: true };
