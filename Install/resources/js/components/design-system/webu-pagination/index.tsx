import type { WebuPaginationProps } from './types';
import { Pagination1 } from './variants/pagination-1';

const VARIANTS = ['pagination-1', 'pagination-2', 'pagination-3'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'pagination-1';

export type { WebuPaginationProps };

export function WebuPagination({ variant, ...props }: WebuPaginationProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Pagination1 {...props} />;
}
