import { Link } from '@inertiajs/react';
import type { WebuBreadcrumbProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

/** Data from CMS */
export function Breadcrumb1({ items, basePath }: WebuBreadcrumbProps) {
  return (
    <nav className="webu-breadcrumb webu-breadcrumb--breadcrumb-1" aria-label="Breadcrumb">
      <ol className="webu-breadcrumb__list">
        {items.map((item, i) => (
          <li key={i} className="webu-breadcrumb__item">
            {i > 0 && <span className="webu-breadcrumb__sep" aria-hidden>/</span>}
            {item.url ? (
              <Link href={path(basePath, item.url)} className="webu-breadcrumb__link">{item.label}</Link>
            ) : (
              <span className="webu-breadcrumb__current">{item.label}</span>
            )}
          </li>
        ))}
      </ol>
    </nav>
  );
}
