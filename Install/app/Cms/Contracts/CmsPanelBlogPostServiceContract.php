<?php

namespace App\Cms\Contracts;

use App\Models\BlogPost;
use App\Models\Site;

interface CmsPanelBlogPostServiceContract
{
    /**
     * @return array{site_id: string, posts: array<int, array<string, mixed>>}
     */
    public function listPosts(Site $site): array;

    /**
     * @return array{site_id: string, post: array<string, mixed>}
     */
    public function getPost(Site $site, BlogPost $post): array;

    public function createPost(Site $site, array $payload, ?int $actorId): BlogPost;

    public function updatePost(Site $site, BlogPost $post, array $payload, ?int $actorId): BlogPost;

    public function deletePost(Site $site, BlogPost $post): void;
}

