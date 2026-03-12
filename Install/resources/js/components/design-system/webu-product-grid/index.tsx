import { WebuProductCard } from '../webu-product-card';
import type { WebuProductGridProps, WebuProductGridVariant } from './types';

export type { WebuProductGridProps, WebuProductGridVariant };

const GRID_TO_CARD: Record<string, 'classic' | 'minimal' | 'modern' | 'premium' | 'compact'> = {
  'grid-1': 'classic',
  'grid-2': 'minimal',
  'grid-3': 'modern',
  'grid-4': 'premium',
};

function toCardVariant(v: WebuProductGridVariant | undefined): 'classic' | 'minimal' | 'modern' | 'premium' | 'compact' {
  if (!v || v === 'classic' || v === 'minimal' || v === 'modern' || v === 'premium' || v === 'compact') {
    return (v as 'classic' | 'minimal' | 'modern' | 'premium' | 'compact') ?? 'classic';
  }
  return GRID_TO_CARD[v] ?? 'classic';
}

export function WebuProductGrid({ title, products, variant = 'classic', basePath, className }: WebuProductGridProps) {
  const cardVariant = toCardVariant(variant);
  return (
    <section className={`webu-product-grid ${className ?? ''}`.trim()}>
      {title && <h2 className="webu-product-grid__title">{title}</h2>}
      <div className="webu-product-grid__inner">
        {products.map((product) => (
          <WebuProductCard
            key={product.slug ?? product.id ?? product.name}
            product={product}
            variant={cardVariant}
            basePath={basePath}
          />
        ))}
      </div>
    </section>
  );
}
