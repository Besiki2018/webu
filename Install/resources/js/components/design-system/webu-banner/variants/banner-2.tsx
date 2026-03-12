import { Link } from '@inertiajs/react';
import type { WebuBannerProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

export function Banner2({ title, subtitle, ctaLabel, ctaUrl, backgroundImage, basePath }: WebuBannerProps) {
  const style = backgroundImage ? { backgroundImage: `url(${backgroundImage})` } : undefined;
  return (
    <section className="webu-banner webu-banner--banner-2 webu-banner--has-bg" style={style}>
      <div className="webu-banner__inner">
        <h2 className="webu-banner__title" data-webu-field="title">{title}</h2>
        {subtitle && <p className="webu-banner__subtitle" data-webu-field="subtitle">{subtitle}</p>}
        {ctaLabel && ctaUrl && (
          <Link href={path(basePath, ctaUrl)} className="webu-banner__cta" data-webu-field="ctaLabel" data-webu-field-url="ctaUrl">
            {ctaLabel}
          </Link>
        )}
      </div>
    </section>
  );
}
