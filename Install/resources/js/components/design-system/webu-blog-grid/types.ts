import type { BlogPost } from '../webu-blog-card/types';

export type BlogGridVariant = 'blog-grid-1' | 'blog-grid-2' | 'blog-grid-3';

export interface WebuBlogGridProps {
  variant?: BlogGridVariant;
  title?: string;
  posts: BlogPost[];
  basePath?: string;
  className?: string;
}
