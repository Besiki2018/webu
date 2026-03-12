import { Link } from '@inertiajs/react';
import type { WebuHeaderProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

export function Header2({
  logo = '',
  logoUrl = '/',
  logoImageUrl,
  logoFallback = 'Logo',
  menu = [],
  basePath,
  navAriaLabel = 'Main navigation',
  backgroundColor,
  textColor,
}: WebuHeaderProps) {
  const style = { backgroundColor: backgroundColor || undefined, color: textColor || undefined };
  const resolvedLogo = logo || logoFallback;
  return (
    <header className="webu-header webu-header--header-2" style={style}>
      <div className="webu-header__inner">
        <Link href={path(basePath, logoUrl)} className="webu-header__logo">
          {logoImageUrl ? (
            <img src={logoImageUrl} alt={resolvedLogo} className="webu-header__logo-img" />
          ) : (
            resolvedLogo
          )}
        </Link>
        <nav className="webu-nav webu-nav--compact" aria-label={navAriaLabel}>
          {menu.map((item) => (
            <Link key={item.slug ?? item.url} href={path(basePath, item.url)} className="webu-nav__link">
              {item.label}
            </Link>
          ))}
        </nav>
      </div>
    </header>
  );
}
