import { Link } from '@inertiajs/react';
import type { SectionInjectedProps } from './types';
import { path } from './types';
import { getCategories } from '@/ecommerce/data/productService';
import { cn } from '@/lib/utils';

export interface CategoryGridProps extends SectionInjectedProps {
  title?: string;
  categories?: { id: string; title: string; image: string; link: string }[];
  columns?: 2 | 3 | 4;
  className?: string;
}

export function CategoryGrid({
  basePath,
  title = 'Shop by Category',
  categories: propCategories,
  columns = 3,
  className,
}: CategoryGridProps) {
  const list = propCategories ?? getCategories().map((c) => ({
    id: c.id,
    title: c.title,
    image: c.image,
    link: c.link,
  }));

  return (
    <section className={cn('webu-section webu-category-gallery', className)}>
      {title && (
        <h2 className="text-2xl font-semibold mb-8 text-center">{title}</h2>
      )}
      <div
        className={cn('webu-grid', columns === 2 && 'webu-grid--2', columns === 3 && 'webu-grid--3', columns === 4 && 'webu-grid--4')}
      >
        {list.map((cat) => (
          <Link
            key={cat.id}
            href={path(basePath, cat.link)}
            className="webu-category-card"
          >
            <div className="webu-category-card__image-wrap">
              <img
                src={cat.image}
                alt={cat.title}
                className="webu-category-card__image"
              />
            </div>
            <div className="webu-category-card__title">
              <h3 className="font-medium text-foreground group-hover:underline">
                {cat.title}
              </h3>
            </div>
          </Link>
        ))}
      </div>
    </section>
  );
}

CategoryGrid.defaultProps = {
  title: 'Shop by Category',
  columns: 3,
};
