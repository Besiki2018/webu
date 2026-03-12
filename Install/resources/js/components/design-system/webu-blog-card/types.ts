export type BlogCardVariant = 'blog-card-1' | 'blog-card-2' | 'blog-card-3';

export interface BlogPost {
  id: string;
  title: string;
  excerpt?: string;
  image?: string;
  url?: string;
  date?: string;
  author?: string;
}

export interface WebuBlogCardProps {
  variant?: BlogCardVariant;
  post: BlogPost;
  basePath?: string;
  className?: string;
}
