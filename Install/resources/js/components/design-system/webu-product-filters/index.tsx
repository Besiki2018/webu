import type { WebuProductFiltersProps, FilterGroup, FilterOption } from './types';
import { Filters1 } from './variants/filters-1';

const VARIANTS = ['filters-1', 'filters-2', 'filters-3'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'filters-1';

export type { WebuProductFiltersProps, FilterGroup, FilterOption };

export function WebuProductFilters({ variant, ...props }: WebuProductFiltersProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Filters1 {...props} />;
}
