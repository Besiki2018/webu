import { Link } from '@inertiajs/react';
import type { WebuBlogCardProps } from '../types';

function path(basePath: string | undefined, url: string): string {
  const base = (basePath ?? '').replace(/\/$/, '');
  const p = url.startsWith('/') ? url : `/${url}`;
  return base ? `${base}${p}` : p;
}

/** Classic – data from CMS */
export function BlogCard1({ post, basePath }: WebuBlogCardProps) {
  const href = post.url ? path(basePath, post.url) : undefined;
  return (
    <article className="webu-blog-card webu-blog-card--blog-card-1">
      {post.image && (
        <div className="webu-blog-card__media">
          {href ? <Link href={href}><img src={post.image} alt={post.title} className="webu-blog-card__img" /></Link> : <img src={post.image} alt={post.title} className="webu-blog-card__img" />}
        </div>
      )}
      <div className="webu-blog-card__body">
        {post.date && <time className="webu-blog-card__date">{post.date}</time>}
        {href ? <Link href={href} className="webu-blog-card__title">{post.title}</Link> : <h3 className="webu-blog-card__title">{post.title}</h3>}
        {post.excerpt && <p className="webu-blog-card__excerpt">{post.excerpt}</p>}
        {post.author && <span className="webu-blog-card__author">{post.author}</span>}
      </div>
    </article>
  );
}
