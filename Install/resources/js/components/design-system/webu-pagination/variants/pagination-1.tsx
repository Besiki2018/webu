import { Link } from '@inertiajs/react';
import type { WebuPaginationProps } from '../types';

function path(basePath: string | undefined, page: number): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const q = page === 1 ? '' : `?page=${page}`;
  return base ? `${base}${q}` : (page === 1 ? '/' : `/?page=${page}`);
}

/** Classic – data from CMS / router */
export function Pagination1({ currentPage, totalPages, basePath }: WebuPaginationProps) {
  const prev = currentPage > 1 ? currentPage - 1 : null;
  const next = currentPage < totalPages ? currentPage + 1 : null;
  return (
    <nav className="webu-pagination webu-pagination--pagination-1" aria-label="Pagination">
      <div className="webu-pagination__inner">
        {prev !== null && (
          <Link href={path(basePath, prev)} className="webu-pagination__prev" rel="prev">Previous</Link>
        )}
        <span className="webu-pagination__info">
          Page {currentPage} of {totalPages}
        </span>
        {next !== null && (
          <Link href={path(basePath, next)} className="webu-pagination__next" rel="next">Next</Link>
        )}
      </div>
    </nav>
  );
}
