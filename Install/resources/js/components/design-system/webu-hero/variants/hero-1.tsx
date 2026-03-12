import React from 'react';
import { Link } from '@inertiajs/react';
import type { WebuHeroProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

export function Hero1({
  headline = '',
  title,
  subheading = '',
  subtitle,
  eyebrow = '',
  badgeText = '',
  ctaLabel = '',
  ctaUrl = '/',
  ctaSecondaryLabel = '',
  ctaSecondaryUrl = '',
  imageUrl,
  imageAlt = '',
  imageAltFallback = 'Hero',
  basePath,
  className,
  backgroundColor,
  textColor,
  alignment,
  padding,
  spacing,
}: WebuHeroProps) {
  const rootClassName = ['webu-hero', 'webu-hero--hero-1', className].filter(Boolean).join(' ');
  const style: React.CSSProperties = {
    backgroundColor: backgroundColor || 'var(--color-background, #ffffff)',
    color: textColor || 'var(--color-foreground, #0f172a)',
    textAlign: alignment as React.CSSProperties['textAlign'],
  };
  if (padding) style.padding = padding;
  if (spacing) style.margin = spacing;
  const resolvedHeadline = headline || title || '';
  const resolvedSubheading = subheading || subtitle || '';

  return (
    <section className={rootClassName} style={style}>
      <div className="webu-hero__inner webu-hero__inner--editorial">
        <div className="webu-hero__content webu-hero__content--editorial">
          {badgeText ? <span className="webu-hero__promo-badge" data-webu-field="eyebrow">{badgeText}</span> : null}
          {eyebrow ? <p className="webu-hero__eyebrow-copy" data-webu-field="eyebrow">{eyebrow}</p> : null}
          {resolvedHeadline ? <h1 className="webu-hero__title webu-hero__title--editorial" data-webu-field="title">{resolvedHeadline}</h1> : null}
          {resolvedSubheading ? <p className="webu-hero__subtitle webu-hero__subtitle--editorial" data-webu-field="subtitle">{resolvedSubheading}</p> : null}
          {(ctaLabel && ctaUrl) || (ctaSecondaryLabel && ctaSecondaryUrl) ? (
            <div className="webu-hero__ctas webu-hero__ctas--editorial">
              {ctaLabel && ctaUrl && (
                <Link href={path(basePath, ctaUrl)} className="webu-hero__cta webu-hero__cta--primary webu-hero__cta--editorial" data-webu-field="buttonText" data-webu-field-url="buttonLink">
                  {ctaLabel}
                </Link>
              )}
              {ctaSecondaryLabel && ctaSecondaryUrl && (
                <Link href={path(basePath, ctaSecondaryUrl)} className="webu-hero__cta webu-hero__cta--secondary webu-hero__cta--editorial-secondary" data-webu-field="ctaSecondaryLabel" data-webu-field-url="ctaSecondaryUrl">
                  {ctaSecondaryLabel}
                </Link>
              )}
            </div>
          ) : null}
        </div>
        {imageUrl ? (
          <div className="webu-hero__media webu-hero__media--editorial">
            <img src={imageUrl} alt={imageAlt || resolvedHeadline || imageAltFallback} className="webu-hero__image webu-hero__image--editorial" data-webu-field="image" />
          </div>
        ) : null}
        <div className="webu-hero__pagination" aria-hidden="true">
          <span className="webu-hero__pagination-dot webu-hero__pagination-dot--active" />
          <span className="webu-hero__pagination-dot" />
          <span className="webu-hero__pagination-dot" />
        </div>
      </div>
    </section>
  );
}
