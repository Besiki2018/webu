import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import type { WebuHeroProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

function isExternalHref(href: string): boolean {
  return /^(https?:|mailto:|tel:|#)/i.test(href);
}

function SmartLink({
  href,
  basePath,
  className,
  children,
}: {
  href: string;
  basePath?: string;
  className?: string;
  children: ReactNode;
}) {
  if (isExternalHref(href)) {
    return <a href={href} className={className}>{children}</a>;
  }

  return <Link href={path(basePath, href)} className={className}>{children}</Link>;
}

export function Hero7({
  headline = '',
  title,
  subheading = '',
  subtitle,
  eyebrow = '',
  ctaLabel = '',
  ctaUrl = '/',
  imageUrl,
  imageAlt = '',
  imageAltFallback = 'Hero',
  overlayImageUrl,
  overlayImageAlt = 'Overlay',
  statValue = '',
  statUnit = '',
  statLabel = '',
  statAvatars = [],
  basePath,
  className,
  backgroundColor,
  textColor,
}: WebuHeroProps) {
  const rootClassName = ['webu-hero', 'webu-hero--hero-7', className].filter(Boolean).join(' ');
  const hasStat = Boolean(statValue || statUnit || statLabel || statAvatars.length > 0);
  const style = { backgroundColor: backgroundColor || undefined, color: textColor || undefined };
  const resolvedHeadline = headline || title || '';
  const resolvedSubheading = subheading || subtitle || '';

  return (
    <section className={rootClassName} style={style}>
      <div className="webu-hero__inner webu-hero__inner--finwave">
        <div className="webu-hero__content webu-hero__content--finwave">
          {eyebrow ? <span className="webu-hero__eyebrow webu-hero__eyebrow--finwave">{eyebrow}</span> : null}
          {resolvedHeadline ? <h1 className="webu-hero__title webu-hero__title--finwave">{resolvedHeadline}</h1> : null}
          {resolvedSubheading ? <p className="webu-hero__subtitle webu-hero__subtitle--finwave">{resolvedSubheading}</p> : null}

          {(ctaLabel && ctaUrl) || hasStat ? (
            <div className="webu-hero__cta-row webu-hero__cta-row--finwave">
              {ctaLabel && ctaUrl && (
                <SmartLink href={ctaUrl} basePath={basePath} className="webu-hero__cta webu-hero__cta--finwave">
                  {ctaLabel}
                </SmartLink>
              )}

              {hasStat && (
                <div className="webu-hero__stat-card">
                  {statAvatars.length > 0 && (
                    <div className="webu-hero__stat-avatars" aria-hidden="true">
                      {statAvatars.slice(0, 4).map((avatar, index) => (
                        <span className="webu-hero__stat-avatar" key={`${avatar.url}-${index}`}>
                          <img src={avatar.url} alt={avatar.alt ?? ''} />
                        </span>
                      ))}
                    </div>
                  )}
                  <div className="webu-hero__stat-copy">
                    {(statValue || statUnit) && (
                      <span className="webu-hero__stat-value">
                        {statValue}
                        {statUnit && <span className="webu-hero__stat-unit">{statUnit}</span>}
                      </span>
                    )}
                    {statLabel && <span className="webu-hero__stat-label">{statLabel}</span>}
                  </div>
                </div>
              )}
            </div>
          ) : null}
        </div>

        <div className="webu-hero__media webu-hero__media--finwave">
          {imageUrl ? <img src={imageUrl} alt={imageAlt || resolvedHeadline || imageAltFallback} className="webu-hero__image webu-hero__image--finwave" /> : null}
          {overlayImageUrl ? (
            <div className="webu-hero__floating-card">
              <img src={overlayImageUrl} alt={overlayImageAlt} />
            </div>
          ) : null}
        </div>
      </div>
    </section>
  );
}
