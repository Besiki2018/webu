export type BreadcrumbVariant = 'breadcrumb-1' | 'breadcrumb-2';

export interface BreadcrumbItem {
  label: string;
  url?: string;
}

export interface WebuBreadcrumbProps {
  variant?: BreadcrumbVariant;
  items: BreadcrumbItem[];
  basePath?: string;
  className?: string;
}
