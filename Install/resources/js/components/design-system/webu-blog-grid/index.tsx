import type { WebuBlogGridProps } from './types';
import { BlogGrid1 } from './variants/blog-grid-1';

const VARIANTS = ['blog-grid-1', 'blog-grid-2', 'blog-grid-3'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'blog-grid-1';

export type { WebuBlogGridProps };

export function WebuBlogGrid({ variant, ...props }: WebuBlogGridProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <BlogGrid1 {...props} />;
}
