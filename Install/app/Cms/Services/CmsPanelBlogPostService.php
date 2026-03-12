<?php

namespace App\Cms\Services;

use App\Cms\Contracts\CmsPanelBlogPostServiceContract;
use App\Cms\Contracts\CmsRepositoryContract;
use App\Cms\Exceptions\CmsDomainException;
use App\Models\BlogPost;
use App\Models\Site;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class CmsPanelBlogPostService implements CmsPanelBlogPostServiceContract
{
    public function __construct(
        protected CmsRepositoryContract $repository
    ) {}

    public function listPosts(Site $site): array
    {
        return [
            'site_id' => $site->id,
            'posts' => $this->repository
                ->listBlogPosts($site)
                ->map(fn (BlogPost $post): array => $this->serializePost($post))
                ->values()
                ->all(),
        ];
    }

    public function getPost(Site $site, BlogPost $post): array
    {
        $resolved = $this->ensurePostBelongsToSite($site, $post);

        return [
            'site_id' => $site->id,
            'post' => $this->serializePost($resolved, withContent: true),
        ];
    }

    public function createPost(Site $site, array $payload, ?int $actorId): BlogPost
    {
        $coverMediaId = $payload['cover_media_id'] ?? null;
        if ($coverMediaId !== null && ! $this->repository->findMediaById($site, $coverMediaId)) {
            throw new CmsDomainException('Selected cover media does not belong to this site.', 422);
        }

        $status = $payload['status'] ?? 'draft';
        $publishedAt = $status === 'published' ? now() : null;

        $post = $this->repository->createBlogPost($site, [
            'title' => $payload['title'],
            'slug' => $payload['slug'],
            'excerpt' => $payload['excerpt'] ?? null,
            'content' => $payload['content'] ?? null,
            'status' => $status,
            'cover_media_id' => $coverMediaId,
            'published_at' => $publishedAt,
            'created_by' => $actorId,
            'updated_by' => $actorId,
        ]);

        return $this->ensurePostBelongsToSite($site, $post);
    }

    public function updatePost(Site $site, BlogPost $post, array $payload, ?int $actorId): BlogPost
    {
        $resolved = $this->ensurePostBelongsToSite($site, $post);

        $coverMediaId = array_key_exists('cover_media_id', $payload)
            ? $payload['cover_media_id']
            : $resolved->cover_media_id;
        if ($coverMediaId !== null && ! $this->repository->findMediaById($site, $coverMediaId)) {
            throw new CmsDomainException('Selected cover media does not belong to this site.', 422);
        }

        $status = $payload['status'] ?? $resolved->status;
        $publishedAt = $status === 'published'
            ? ($resolved->published_at ?? now())
            : null;

        $updated = $this->repository->updateBlogPost($resolved, [
            'title' => $payload['title'] ?? $resolved->title,
            'slug' => $payload['slug'] ?? $resolved->slug,
            'excerpt' => array_key_exists('excerpt', $payload) ? ($payload['excerpt'] ?? null) : $resolved->excerpt,
            'content' => array_key_exists('content', $payload) ? ($payload['content'] ?? null) : $resolved->content,
            'status' => $status,
            'cover_media_id' => $coverMediaId,
            'published_at' => $publishedAt,
            'updated_by' => $actorId,
        ]);

        return $this->ensurePostBelongsToSite($site, $updated);
    }

    public function deletePost(Site $site, BlogPost $post): void
    {
        $resolved = $this->ensurePostBelongsToSite($site, $post);

        $this->repository->deleteBlogPost($resolved);
    }

    private function ensurePostBelongsToSite(Site $site, BlogPost $post): BlogPost
    {
        $resolved = $this->repository->findBlogPostBySiteAndId($site, $post->id);
        if (! $resolved) {
            throw (new ModelNotFoundException)->setModel(BlogPost::class, [(string) $post->id]);
        }

        return $resolved;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePost(BlogPost $post, bool $withContent = true): array
    {
        $coverMedia = $post->coverMedia;

        return [
            'id' => $post->id,
            'site_id' => $post->site_id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'content' => $withContent ? $post->content : null,
            'status' => $post->status,
            'cover_media_id' => $post->cover_media_id,
            'cover_media_url' => $coverMedia?->path
                ? route('public.sites.assets', ['site' => $post->site_id, 'path' => $coverMedia->path])
                : null,
            'published_at' => $post->published_at?->toISOString(),
            'created_at' => $post->created_at?->toISOString(),
            'updated_at' => $post->updated_at?->toISOString(),
        ];
    }
}
