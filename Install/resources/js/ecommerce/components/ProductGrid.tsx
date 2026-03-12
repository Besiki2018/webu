import type { SectionInjectedProps } from './types';
import { getProducts } from '@/ecommerce/data/productService';
import type { Product } from '@/ecommerce/data/productService';
import { ProductCard } from './ProductCard';
import { cn } from '@/lib/utils';

export interface ProductGridProps extends SectionInjectedProps {
  title?: string;
  categoryId?: string;
  limit?: number;
  columns?: 2 | 3 | 4;
  showFilters?: boolean;
  showPagination?: boolean;
  /** When provided (e.g. playground), use instead of getProducts() */
  products?: Product[];
  className?: string;
}

export function ProductGrid({
  basePath,
  title = 'Products',
  categoryId,
  limit = 12,
  columns = 4,
  showFilters = false,
  showPagination = false,
  products: productsProp,
  className,
}: ProductGridProps) {
  const products = productsProp ?? getProducts({ categoryId, limit });

  return (
    <section className={cn('webu-product-grid-section', className)}>
      {title && (
        <h2 className="webu-product-grid-section__title">{title}</h2>
      )}
      {showFilters && (
        <div className="webu-product-grid-section__filters">
          <span>Filters (optional)</span>
        </div>
      )}
      <div
        className={cn('webu-product-grid', columns === 2 && 'webu-product-grid--2', columns === 3 && 'webu-product-grid--3', columns === 4 && 'webu-product-grid--4')}
      >
        {products.map((p) => (
          <ProductCard
            key={p.id}
            basePath={basePath}
            id={p.id}
            slug={p.slug}
            title={p.title}
            price={p.price}
            currency={p.currency}
            image={p.image}
            badge={p.badge}
            rating={p.rating}
          />
        ))}
      </div>
      {showPagination && products.length >= limit && (
        <div className="webu-product-grid-section__pagination">
          <span className="webu-product-grid-section__pagination-label">Pagination (optional)</span>
        </div>
      )}
    </section>
  );
}

ProductGrid.defaultProps = {
  title: 'Products',
  limit: 12,
  columns: 4,
  showFilters: false,
  showPagination: false,
};
