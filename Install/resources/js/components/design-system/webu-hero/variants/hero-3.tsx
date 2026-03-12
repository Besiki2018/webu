import { Link } from '@inertiajs/react';
import type { WebuHeroProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

export function Hero3({
  headline = '',
  title,
  subheading = '',
  subtitle,
  ctaLabel = '',
  ctaUrl = '/',
  imageUrl,
  imageAlt = '',
  imageAltFallback = 'Hero',
  basePath,
  backgroundColor,
  textColor,
  alignment,
}: WebuHeroProps) {
  const style = { backgroundColor: backgroundColor || undefined, color: textColor || undefined, textAlign: alignment };
  const resolvedHeadline = headline || title || '';
  const resolvedSubheading = subheading || subtitle || '';
  return (
    <section className="webu-hero webu-hero--hero-3" style={style}>
      <div className="webu-hero__inner webu-hero__inner--split">
        <div className="webu-hero__content">
          {resolvedHeadline ? <h1 className="webu-hero__title">{resolvedHeadline}</h1> : null}
          {resolvedSubheading ? <p className="webu-hero__subtitle">{resolvedSubheading}</p> : null}
          {ctaLabel && ctaUrl ? (
            <Link href={path(basePath, ctaUrl)} className="webu-hero__cta webu-hero__cta--primary">
              {ctaLabel}
            </Link>
          ) : null}
        </div>
        {imageUrl ? (
          <div className="webu-hero__media">
            <img src={imageUrl} alt={imageAlt || resolvedHeadline || imageAltFallback} className="webu-hero__image" />
          </div>
        ) : null}
      </div>
    </section>
  );
}
