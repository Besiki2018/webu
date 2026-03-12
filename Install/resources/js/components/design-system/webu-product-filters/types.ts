export type ProductFiltersVariant = 'filters-1' | 'filters-2' | 'filters-3';

export interface FilterOption {
  value: string;
  label: string;
  count?: number;
}

export interface FilterGroup {
  key: string;
  label: string;
  options: FilterOption[];
}

export interface WebuProductFiltersProps {
  variant?: ProductFiltersVariant;
  filters: FilterGroup[];
  onFilterChange?: (key: string, value: string) => void;
  basePath?: string;
  className?: string;
}
