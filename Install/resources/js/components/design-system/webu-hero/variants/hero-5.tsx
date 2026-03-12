import { Link } from '@inertiajs/react';
import type { WebuHeroProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

/** Slider hero – one slide from CMS; bindings: title, subtitle, button, hero_image */
export function Hero5({
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
}: WebuHeroProps) {
  const style = { backgroundColor: backgroundColor || undefined, color: textColor || undefined };
  const resolvedHeadline = headline || title || '';
  const resolvedSubheading = subheading || subtitle || '';
  return (
    <section className="webu-hero webu-hero--hero-5" style={style}>
      <div className="webu-hero__slider">
        <div className="webu-hero__slide">
          {imageUrl ? <img src={imageUrl} alt={imageAlt || resolvedHeadline || imageAltFallback} className="webu-hero__slide-img" /> : null}
          <div className="webu-hero__slide-content">
            {resolvedHeadline ? <h1 className="webu-hero__title">{resolvedHeadline}</h1> : null}
            {resolvedSubheading ? <p className="webu-hero__subtitle">{resolvedSubheading}</p> : null}
            {ctaLabel && ctaUrl ? (
              <Link href={path(basePath, ctaUrl)} className="webu-hero__cta webu-hero__cta--primary">{ctaLabel}</Link>
            ) : null}
          </div>
        </div>
      </div>
    </section>
  );
}
