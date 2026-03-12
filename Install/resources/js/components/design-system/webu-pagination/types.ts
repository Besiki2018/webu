export type PaginationVariant = 'pagination-1' | 'pagination-2' | 'pagination-3';

export interface WebuPaginationProps {
  variant?: PaginationVariant;
  currentPage: number;
  totalPages: number;
  basePath?: string;
  /** For infinite scroll: onLoadMore callback */
  onLoadMore?: () => void;
  hasMore?: boolean;
  className?: string;
}
