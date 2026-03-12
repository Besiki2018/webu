import { Link } from '@inertiajs/react';
import type { WebuHeaderProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

export function Header1({
  logo = '',
  logoUrl = '/',
  logoImageUrl,
  logoFallback = 'Logo',
  menu = [],
  ctaLabel,
  ctaUrl,
  basePath,
  navAriaLabel = 'Main navigation',
  backgroundColor,
  textColor,
}: WebuHeaderProps) {
  const style = { backgroundColor: backgroundColor || undefined, color: textColor || undefined };
  const resolvedLogo = logo || logoFallback;
  return (
    <header className="webu-header webu-header--header-1" style={style}>
      <div className="webu-header__inner">
        <Link href={path(basePath, logoUrl)} className="webu-header__logo" data-webu-field="logoText">
          {logoImageUrl ? (
            <img src={logoImageUrl} alt={resolvedLogo} className="webu-header__logo-img" data-webu-field="logo_url" />
          ) : (
            resolvedLogo
          )}
        </Link>
        <nav className="webu-nav" aria-label={navAriaLabel} data-webu-field="menu_items">
          {menu.map((item, i) => (
            <Link key={item.slug ?? item.url} href={path(basePath, item.url)} className="webu-nav__link" data-webu-field-scope={`menu_items.${i}`} data-webu-field="label" data-webu-field-url="url">
              {item.label}
            </Link>
          ))}
        </nav>
        {ctaLabel && ctaUrl && (
          <a href={path(basePath, ctaUrl)} className="webu-header__cta" data-webu-field="ctaText" data-webu-field-url="ctaLink">
            {ctaLabel}
          </a>
        )}
      </div>
    </header>
  );
}
