import { Link } from '@inertiajs/react';
import type { WebuWishlistProps } from '../types';

function path(basePath: string | undefined, url: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const p = url.startsWith('/') ? url : `/${url}`;
  return base ? `${base}${p}` : p;
}

/** Grid – data from CMS */
export function Wishlist1({ title, products, basePath }: WebuWishlistProps) {
  return (
    <section className="webu-wishlist webu-wishlist--wishlist-1">
      <div className="webu-wishlist__inner">
        {title && <h2 className="webu-wishlist__title">{title}</h2>}
        <div className="webu-wishlist__grid">
          {products.map((p) => (
            <div key={p.id} className="webu-wishlist__card">
              {p.image && <img src={p.image} alt={p.name} className="webu-wishlist__img" />}
              {p.url ? (
                <Link href={path(basePath, p.url)} className="webu-wishlist__name">{p.name}</Link>
              ) : (
                <span className="webu-wishlist__name">{p.name}</span>
              )}
              <span className="webu-wishlist__price">${p.price.toFixed(2)}</span>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
