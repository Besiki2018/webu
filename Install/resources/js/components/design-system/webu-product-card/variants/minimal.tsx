import { Link } from '@inertiajs/react';
import type { WebuProductCardProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

export function ProductCardMinimal({ product, basePath }: WebuProductCardProps) {
  const href = product.url.startsWith('http') ? product.url : path(basePath, product.url);
  return (
    <article className="webu-product-card webu-product-card--minimal">
      <Link href={href} className="webu-product-card__image-wrap">
        {product.image_url ? (
          <img src={product.image_url} alt={product.name} className="webu-product-card__image" loading="lazy" />
        ) : (
          <div className="webu-product-card__placeholder" aria-hidden />
        )}
      </Link>
      <div className="webu-product-card__content">
        <h3 className="webu-product-card__title">
          <Link href={href}>{product.name}</Link>
        </h3>
        <span className="webu-product-card__price">{product.price}</span>
      </div>
    </article>
  );
}
