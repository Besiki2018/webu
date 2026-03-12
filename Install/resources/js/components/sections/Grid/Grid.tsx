/**
 * Grid — builder section: title + grid of items.
 * All content from props; no hardcoded values.
 */

import { Link } from '@inertiajs/react';
import { GRID_DEFAULT_VARIANT, type GridVariantId } from './Grid.variants';

export interface GridItem {
  image?: string;
  imageAlt?: string;
  title: string;
  link?: string;
}

export interface GridProps {
  title?: string;
  items?: GridItem[];
  columns?: number;
  variant?: GridVariantId;
  backgroundColor?: string;
  textColor?: string;
  padding?: string;
  spacing?: string;
  className?: string;
}

export function Grid(props: GridProps) {
  const variant = (props.variant ?? GRID_DEFAULT_VARIANT) as GridVariantId;
  const items = Array.isArray(props.items) ? props.items : [];
  const columns = Math.min(6, Math.max(1, Number(props.columns) || 3));
  const style: React.CSSProperties = {};
  if (props.backgroundColor) style.backgroundColor = props.backgroundColor;
  if (props.textColor) style.color = props.textColor;
  if (props.padding) style.padding = props.padding;
  if (props.spacing) style.margin = props.spacing;

  const gridClass =
    columns === 1
      ? 'grid-cols-1'
      : columns === 2
        ? 'grid-cols-1 md:grid-cols-2'
        : columns === 4
          ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-4'
          : columns === 5
            ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-5'
            : columns === 6
              ? 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6'
              : 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3';

  return (
    <section
      className={`grid-section grid-section--${variant} py-8 px-4`}
      style={Object.keys(style).length ? style : undefined}
    >
      <div className="max-w-6xl mx-auto">
        {props.title && (
          <h2 className="grid-section__title text-2xl font-semibold mb-6" data-webu-field="title">{props.title}</h2>
        )}
        <div className={`grid gap-6 ${gridClass}`}>
          {items.map((item, i) => (
            <div key={i} className="grid-section__item" data-webu-field-scope={`items.${i}`}>
              {item.image && (
                <img
                  src={item.image}
                  alt={item.imageAlt ?? item.title ?? ''}
                  className="w-full h-40 object-cover rounded-lg"
                  data-webu-field="image"
                />
              )}
              <div className="mt-2">
                {item.link ? (
                  <Link href={item.link} className="font-medium hover:underline" data-webu-field="title" data-webu-field-url="link">
                    {item.title}
                  </Link>
                ) : (
                  <span className="font-medium" data-webu-field="title">{item.title}</span>
                )}
              </div>
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}

export default Grid;
