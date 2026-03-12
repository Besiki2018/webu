import { Link } from '@inertiajs/react';
import type { WebuHeroProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

/** Video hero – bindings: title, subtitle, button, hero_image (poster) or video_url */
export function Hero4({
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
    <section className="webu-hero webu-hero--hero-4" style={style}>
      <div className="webu-hero__media webu-hero__media--video">
        {imageUrl ? <div className="webu-hero__video-poster" style={{ backgroundImage: `url(${imageUrl})` }} /> : null}
      </div>
      <div className="webu-hero__inner webu-hero__inner--overlay">
        {resolvedHeadline ? <h1 className="webu-hero__title">{resolvedHeadline}</h1> : null}
        {resolvedSubheading ? <p className="webu-hero__subtitle">{resolvedSubheading}</p> : null}
        {ctaLabel && ctaUrl ? (
          <Link href={path(basePath, ctaUrl)} className="webu-hero__cta webu-hero__cta--primary">{ctaLabel}</Link>
        ) : null}
      </div>
    </section>
  );
}
