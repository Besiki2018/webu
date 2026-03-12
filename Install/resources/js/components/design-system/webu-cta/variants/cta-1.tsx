import React from 'react';
import { Link } from '@inertiajs/react';
import type { WebuCtaProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

/** Simple CTA – data from CMS */
export function Cta1({ title, subtitle, buttonLabel, buttonUrl, basePath, backgroundColor, textColor, padding, spacing }: WebuCtaProps) {
  const style: React.CSSProperties = {};
  if (backgroundColor) style.backgroundColor = backgroundColor;
  if (textColor) style.color = textColor;
  if (padding) style.padding = padding;
  if (spacing) style.margin = spacing;
  return (
    <section className="webu-cta webu-cta--cta-1" style={Object.keys(style).length ? style : undefined}>
      <div className="webu-cta__inner">
        <h2 className="webu-cta__title" data-webu-field="title">{title}</h2>
        {subtitle && <p className="webu-cta__subtitle" data-webu-field="subtitle">{subtitle}</p>}
        {buttonLabel && buttonUrl && (
          <Link href={path(basePath, buttonUrl)} className="webu-cta__button" data-webu-field="buttonLabel" data-webu-field-url="buttonUrl">{buttonLabel}</Link>
        )}
      </div>
    </section>
  );
}
