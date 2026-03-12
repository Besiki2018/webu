import { Link } from '@inertiajs/react';
import type { WebuFooterProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

/** Ecommerce footer */
export function Footer3({ logo, logoUrl = '/', logoFallback = 'Store', menus = {}, copyright, basePath }: WebuFooterProps) {
  const menuKeys = Object.keys(menus);
  return (
    <footer className="webu-footer webu-footer--footer-3">
      <div className="webu-footer__inner">
        <div className="webu-footer__grid webu-footer__grid--ecom">
          <div className="webu-footer__brand">
            <Link href={path(basePath, logoUrl)} className="webu-footer__logo">{logo ?? logoFallback}</Link>
          </div>
          {menuKeys.map((key) => (
            <nav key={key} className="webu-footer__nav" aria-label={key}>
              {menus[key].map((item) => (
                <Link key={item.url} href={path(basePath, item.url)} className="webu-footer__link">{item.label}</Link>
              ))}
            </nav>
          ))}
        </div>
        <div className="webu-footer__bottom">
          <span className="webu-footer__copyright">{copyright ?? `© ${new Date().getFullYear()}`}</span>
        </div>
      </div>
    </footer>
  );
}
