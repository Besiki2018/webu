import type { SectionInjectedProps } from './types';
import { path } from './types';
import { getProductBySlug } from '@/ecommerce/data/productService';
import { useCartStore } from '@/ecommerce/store/cartStore';
import { Button } from '@/components/ui/button';
import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export interface ProductDetailsProps extends SectionInjectedProps {
  productSlug?: string;
  className?: string;
}

function getSlugFromPath(basePath?: string): string {
  if (typeof window === 'undefined') return '';
  const pathname = window.location.pathname;
  const base = (basePath ?? '').replace(/\/$/, '');
  const segment = base ? pathname.replace(base, '') || '/' : pathname;
  const match = segment.match(/\/product\/([^/]+)/);
  return match ? match[1] : '';
}

export function ProductDetails(props: ProductDetailsProps) {
  const { basePath, productSlug, className } = props;
  const slug = productSlug ?? getSlugFromPath(basePath);
  const product = getProductBySlug(slug);
  const add = useCartStore((s) => s.add);

  if (!product) {
    return (
      <section className={cn('webu-product-details', className)}>
        <p className="text-muted-foreground">Product not found.</p>
        <Button asChild className="mt-4">
          <Link href={path(basePath, '/shop')}>Back to Shop</Link>
        </Button>
      </section>
    );
  }

  const handleAddToCart = () => {
    add({
      productId: product.id,
      slug: product.slug,
      title: product.title,
      price: product.price,
      image: product.image,
      quantity: 1,
    });
  };

  return (
    <section className={cn('webu-product-details', className)}>
      <div className="webu-product-details__grid">
        <div className="webu-product-details__image-wrap">
          <img src={product.image} alt={product.title} className="h-full w-full object-cover" />
        </div>
        <div className="webu-product-details__body">
          {product.badge && (
            <span className="webu-product-card__badge">{product.badge}</span>
          )}
          <h1 className="webu-product-details__title">{product.title}</h1>
          <p className="webu-product-details__price">{product.price.toFixed(2)} {product.currency}</p>
          <p className="webu-product-details__description">{product.description ?? 'No description.'}</p>
          <div className="webu-product-details__actions">
            <Button size="lg" onClick={handleAddToCart}>Add to cart</Button>
            <Button variant="outline" size="lg" asChild>
              <Link href={path(basePath, '/cart')}>View cart</Link>
            </Button>
          </div>
        </div>
      </div>
    </section>
  );
}
