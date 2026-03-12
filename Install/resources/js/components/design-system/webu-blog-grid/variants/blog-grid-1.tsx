import { WebuBlogCard } from '@/components/design-system/webu-blog-card';
import type { WebuBlogGridProps } from '../types';

/** Data from CMS */
export function BlogGrid1({ title, posts, basePath }: WebuBlogGridProps) {
  return (
    <section className="webu-blog-grid webu-blog-grid--blog-grid-1">
      <div className="webu-blog-grid__inner">
        {title && <h2 className="webu-blog-grid__title">{title}</h2>}
        <div className="webu-blog-grid__grid">
          {posts.map((post) => (
            <WebuBlogCard key={post.id} post={post} basePath={basePath} />
          ))}
        </div>
      </div>
    </section>
  );
}
