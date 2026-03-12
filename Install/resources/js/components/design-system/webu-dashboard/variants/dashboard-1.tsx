import { Link } from '@inertiajs/react';
import type { WebuDashboardProps } from '../types';

function path(basePath: string | undefined, url: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const p = url.startsWith('/') ? url : `/${url}`;
  return base ? `${base}${p}` : p;
}

/** Data from CMS */
export function Dashboard1({ userName, menuItems, basePath }: WebuDashboardProps) {
  return (
    <section className="webu-dashboard webu-dashboard--dashboard-1">
      <div className="webu-dashboard__inner">
        {userName && <h2 className="webu-dashboard__title">Hello, {userName}</h2>}
        <nav className="webu-dashboard__nav">
          <ul className="webu-dashboard__list">
            {(menuItems ?? []).map((item, i) => (
              <li key={i}>
                <Link href={path(basePath, item.url)} className="webu-dashboard__link">{item.label}</Link>
              </li>
            ))}
          </ul>
        </nav>
      </div>
    </section>
  );
}
