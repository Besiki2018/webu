/**
 * Cards — builder section: title + list of cards.
 * All content from props; no hardcoded values.
 */

import { Link } from '@inertiajs/react';
import { CARDS_DEFAULT_VARIANT, type CardsVariantId } from './Cards.variants';

export interface CardItem {
  image?: string;
  imageAlt?: string;
  title: string;
  description?: string;
  link?: string;
}

export interface CardsProps {
  title?: string;
  items?: CardItem[];
  variant?: CardsVariantId;
  backgroundColor?: string;
  textColor?: string;
  padding?: string;
  spacing?: string;
  className?: string;
}

export function Cards(props: CardsProps) {
  const variant = (props.variant ?? CARDS_DEFAULT_VARIANT) as CardsVariantId;
  const items = Array.isArray(props.items) ? props.items : [];
  const style: React.CSSProperties = {};
  if (props.backgroundColor) style.backgroundColor = props.backgroundColor;
  if (props.textColor) style.color = props.textColor;
  if (props.padding) style.padding = props.padding;
  if (props.spacing) style.margin = props.spacing;

  return (
    <section
      className={`cards cards--${variant} py-8 px-4`}
      style={Object.keys(style).length ? style : undefined}
    >
      <div className="max-w-6xl mx-auto">
        {props.title && (
          <h2 className="cards__title text-2xl font-semibold mb-6" data-webu-field="title">{props.title}</h2>
        )}
        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
          {items.map((card, i) => (
            <article key={i} className="cards__item border border-slate-200 rounded-lg overflow-hidden bg-white" data-webu-field-scope={`items.${i}`}>
              {card.image && (
                <img
                  src={card.image}
                  alt={card.imageAlt ?? card.title ?? ''}
                  className="w-full h-48 object-cover"
                  data-webu-field="image"
                />
              )}
              <div className="p-4">
                <h3 className="text-lg font-medium">
                  {card.link ? (
                    <Link href={card.link} className="hover:underline" data-webu-field="title" data-webu-field-url="link">
                      {card.title}
                    </Link>
                  ) : (
                    <span data-webu-field="title">{card.title}</span>
                  )}
                </h3>
                {card.description && (
                  <p className="mt-2 text-slate-600 text-sm" data-webu-field="description">{card.description}</p>
                )}
              </div>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

export default Cards;
