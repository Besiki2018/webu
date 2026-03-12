import type { WebuBlogCardProps, BlogPost } from './types';
import { BlogCard1 } from './variants/blog-card-1';

const VARIANTS = ['blog-card-1', 'blog-card-2', 'blog-card-3'] as const;
const DEFAULT: (typeof VARIANTS)[number] = 'blog-card-1';

export type { WebuBlogCardProps, BlogPost };

export function WebuBlogCard({ variant, ...props }: WebuBlogCardProps) {
  const v = variant && VARIANTS.includes(variant as (typeof VARIANTS)[number]) ? variant : DEFAULT;
  return <BlogCard1 {...props} />;
}
