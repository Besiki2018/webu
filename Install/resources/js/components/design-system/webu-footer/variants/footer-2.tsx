import { Link } from '@inertiajs/react';
import type { WebuFooterProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

export function Footer2({
  logo = 'Logo',
  logoUrl = '/',
  logoFallback = 'Logo',
  menus = {},
  copyright = `© ${new Date().getFullYear()}`,
  basePath,
  footerNavAriaLabel = 'Footer',
}: WebuFooterProps) {
  const menuKeys = Object.keys(menus);
  return (
    <footer className="webu-footer webu-footer--footer-2">
      <div className="webu-footer__inner webu-footer__inner--compact">
        <Link href={path(basePath, logoUrl)} className="webu-footer__logo">
          {logo || logoFallback}
        </Link>
        <nav className="webu-footer__nav-inline" aria-label={footerNavAriaLabel}>
          {menuKeys.flatMap((key) =>
            menus[key].map((item) => (
              <Link key={`${key}-${item.url}`} href={path(basePath, item.url)} className="webu-footer__link">
                {item.label}
              </Link>
            ))
          )}
        </nav>
        <span className="webu-footer__copyright">{copyright}</span>
      </div>
    </footer>
  );
}
