import { Link } from '@inertiajs/react';
import type { WebuAnnouncementProps } from '../types';

function path(basePath: string | undefined, p: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const pathname = p.startsWith('/') ? p : `/${p}`;
  return base ? `${base}${pathname}` : pathname;
}

/** Simple announcement bar – data from CMS */
export function Announcement1({ text, linkUrl, linkLabel, basePath }: WebuAnnouncementProps) {
  return (
    <div className="webu-announcement webu-announcement--announcement-1">
      <div className="webu-announcement__inner">
        <span className="webu-announcement__text">{text}</span>
        {linkLabel && linkUrl && (
          <Link href={path(basePath, linkUrl)} className="webu-announcement__link">{linkLabel}</Link>
        )}
      </div>
    </div>
  );
}
