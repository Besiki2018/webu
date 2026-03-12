import React from 'react';
import type { WebuFeaturesProps } from '../types';

/** Icon grid – data from CMS */
export function Features1({ title, items, backgroundColor, textColor, padding, spacing }: WebuFeaturesProps) {
  const style: React.CSSProperties = {};
  if (backgroundColor) style.backgroundColor = backgroundColor;
  if (textColor) style.color = textColor;
  if (padding) style.padding = padding;
  if (spacing) style.margin = spacing;
  return (
    <section className="webu-features webu-features--features-1" style={Object.keys(style).length ? style : undefined}>
      <div className="webu-features__inner">
        {title && <h2 className="webu-features__title" data-webu-field="title">{title}</h2>}
        <div className="webu-features__grid">
          {items.map((item, i) => (
            <div key={i} className="webu-features__item" data-webu-field-scope={`items.${i}`}>
              {item.icon && <span className="webu-features__icon" aria-hidden data-webu-field="icon">{item.icon}</span>}
              <h3 className="webu-features__item-title" data-webu-field="title">{item.title}</h3>
              {item.description && <p className="webu-features__item-desc" data-webu-field="description">{item.description}</p>}
            </div>
          ))}
        </div>
      </div>
    </section>
  );
}
