<?php

namespace App\Cms\Contracts;

use App\Models\GlobalSetting;
use App\Models\BlogPost;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\Site;
use Illuminate\Support\Collection;

interface CmsRepositoryContract
{
    public function findSiteByPrimaryDomain(string $domain): ?Site;

    public function findSiteBySubdomain(string $subdomain): ?Site;

    public function findSiteByProject(Project $project): ?Site;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createSiteForProject(Project $project, array $attributes): Site;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateSite(Site $site, array $attributes): Site;

    public function loadSiteGlobalSettings(Site $site): Site;

    /**
     * @param  array<string, mixed>  $defaults
     */
    public function firstOrCreateGlobalSetting(Site $site, array $defaults = []): GlobalSetting;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateGlobalSetting(GlobalSetting $globalSetting, array $attributes): GlobalSetting;

    public function findMenuByKey(Site $site, string $key): ?Menu;

    /**
     * @param  array<string, mixed>  $defaults
     */
    public function firstOrCreateMenu(Site $site, string $key, array $defaults = []): Menu;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateOrCreateMenu(Site $site, string $key, array $attributes): Menu;

    /**
     * @return Collection<int, Menu>
     */
    public function listMenus(Site $site): Collection;

    public function deleteMenu(Menu $menu): void;

    /**
     * @return Collection<int, Page>
     */
    public function listPages(Site $site): Collection;

    public function findPageBySiteAndId(Site $site, string|int $pageId): ?Page;

    public function findPageBySiteAndSlug(Site $site, string $slug): ?Page;

    public function findPublishedPageBySlug(Site $site, string $slug): ?Page;

    /**
     * @param  array<string, mixed>  $defaults
     */
    public function firstOrCreatePage(Site $site, string $slug, array $defaults = []): Page;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createPage(Site $site, array $attributes): Page;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updatePage(Page $page, array $attributes): Page;

    public function deletePage(Page $page): void;

    /**
     * @return Collection<int, BlogPost>
     */
    public function listBlogPosts(Site $site): Collection;

    public function findBlogPostBySiteAndId(Site $site, string|int $postId): ?BlogPost;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createBlogPost(Site $site, array $attributes): BlogPost;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateBlogPost(BlogPost $post, array $attributes): BlogPost;

    public function deleteBlogPost(BlogPost $post): void;

    public function countPageRevisions(Site $site, Page $page): int;

    public function latestRevision(Site $site, Page $page): ?PageRevision;

    public function latestPublishedRevision(Site $site, Page $page): ?PageRevision;

    public function maxRevisionVersion(Site $site, Page $page): int;

    public function findRevisionById(Site $site, Page $page, string|int $revisionId): ?PageRevision;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createRevision(Site $site, Page $page, array $attributes): PageRevision;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateRevision(PageRevision $revision, array $attributes): PageRevision;

    public function clearPublishedRevisions(Site $site, Page $page): void;

    /**
     * @return Collection<int, Media>
     */
    public function listMedia(Site $site): Collection;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function createMedia(Site $site, array $attributes): Media;

    public function findMediaById(Site $site, string|int $mediaId): ?Media;

    public function findMediaByPath(Site $site, string $path): ?Media;

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function updateMedia(Media $media, array $attributes): Media;

    public function deleteMedia(Media $media): void;
}
