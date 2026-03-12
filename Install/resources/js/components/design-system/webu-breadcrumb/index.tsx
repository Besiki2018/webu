import type { WebuBreadcrumbProps, BreadcrumbItem } from './types';
import { Breadcrumb1 } from './variants/breadcrumb-1';

const VARIANTS = ['breadcrumb-1', 'breadcrumb-2'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'breadcrumb-1';

export type { WebuBreadcrumbProps, BreadcrumbItem };

export function WebuBreadcrumb({ variant, ...props }: WebuBreadcrumbProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Breadcrumb1 {...props} />;
}
