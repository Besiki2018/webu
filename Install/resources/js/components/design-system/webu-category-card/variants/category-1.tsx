import { Link } from '@inertiajs/react';
import type { WebuCategoryCardProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

export function Category1({ category, basePath }: WebuCategoryCardProps) {
  const href = path(basePath, `/shop?category=${category.slug}`);
  return (
    <Link href={href} className="webu-category-card webu-category-card--category-1">
      <div className="webu-category-card__image-wrap">
        {category.image_url ? (
          <img src={category.image_url} alt={category.name} className="webu-category-card__image" loading="lazy" />
        ) : (
          <div className="webu-category-card__placeholder" aria-hidden />
        )}
      </div>
      <h3 className="webu-category-card__title">{category.name}</h3>
      {category.count != null && (
        <span className="webu-category-card__count">{category.count} products</span>
      )}
    </Link>
  );
}
