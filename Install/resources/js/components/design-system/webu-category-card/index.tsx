import type { WebuCategoryCardProps, WebuCategoryCardCategory } from './types';
import { Category1 } from './variants/category-1';

const VARIANTS = ['category-1', 'category-2', 'category-3', 'category-4'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'category-1';

export type { WebuCategoryCardProps, WebuCategoryCardCategory };

export function WebuCategoryCard({ variant, ...props }: WebuCategoryCardProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <Category1 {...props} />;
}
