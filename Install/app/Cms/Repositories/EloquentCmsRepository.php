<?php

namespace App\Cms\Repositories;

use App\Cms\Contracts\CmsRepositoryContract;
use App\Models\BlogPost;
use App\Models\GlobalSetting;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\Site;
use Illuminate\Support\Collection;

class EloquentCmsRepository implements CmsRepositoryContract
{
    public function findSiteByPrimaryDomain(string $domain): ?Site
    {
        return Site::query()
            ->where('primary_domain', $domain)
            ->first();
    }

    public function findSiteBySubdomain(string $subdomain): ?Site
    {
        return Site::query()
            ->where('subdomain', $subdomain)
            ->first();
    }

    public function findSiteByProject(Project $project): ?Site
    {
        if ($project->relationLoaded('site') && $project->site) {
            return $project->site;
        }

        return $project->site()->first();
    }

    public function createSiteForProject(Project $project, array $attributes): Site
    {
        return Site::query()->create([
            ...$attributes,
            'project_id' => $project->id,
        ]);
    }

    public function updateSite(Site $site, array $attributes): Site
    {
        if ($attributes === []) {
            return $site;
        }

        $site->update($attributes);

        return $site->refresh();
    }

    public function loadSiteGlobalSettings(Site $site): Site
    {
        $site->load('globalSettings.logoMedia');

        return $site;
    }

    public function firstOrCreateGlobalSetting(Site $site, array $defaults = []): GlobalSetting
    {
        return GlobalSetting::query()->firstOrCreate(
            ['site_id' => $site->id],
            $defaults
        );
    }

    public function updateGlobalSetting(GlobalSetting $globalSetting, array $attributes): GlobalSetting
    {
        if ($attributes === []) {
            return $globalSetting;
        }

        $globalSetting->update($attributes);

        return $globalSetting->refresh();
    }

    public function findMenuByKey(Site $site, string $key): ?Menu
    {
        return Menu::query()
            ->where('site_id', $site->id)
            ->where('key', $key)
            ->first();
    }

    public function firstOrCreateMenu(Site $site, string $key, array $defaults = []): Menu
    {
        return Menu::query()->firstOrCreate(
            ['site_id' => $site->id, 'key' => $key],
            $defaults
        );
    }

    public function updateOrCreateMenu(Site $site, string $key, array $attributes): Menu
    {
        return Menu::query()->updateOrCreate(
            ['site_id' => $site->id, 'key' => $key],
            $attributes
        );
    }

    public function listMenus(Site $site): Collection
    {
        return Menu::query()
            ->where('site_id', $site->id)
            ->orderBy('key')
            ->get();
    }

    public function deleteMenu(Menu $menu): void
    {
        $menu->delete();
    }

    public function listPages(Site $site): Collection
    {
        return Page::query()
            ->where('site_id', $site->id)
            ->orderBy('created_at')
            ->get();
    }

    public function findPageBySiteAndId(Site $site, string|int $pageId): ?Page
    {
        return Page::query()
            ->where('site_id', $site->id)
            ->where('id', $pageId)
            ->first();
    }

    public function findPageBySiteAndSlug(Site $site, string $slug): ?Page
    {
        return Page::query()
            ->where('site_id', $site->id)
            ->where('slug', $slug)
            ->first();
    }

    public function findPublishedPageBySlug(Site $site, string $slug): ?Page
    {
        return Page::query()
            ->where('site_id', $site->id)
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();
    }

    public function firstOrCreatePage(Site $site, string $slug, array $defaults = []): Page
    {
        return Page::query()->firstOrCreate(
            ['site_id' => $site->id, 'slug' => $slug],
            $defaults
        );
    }

    public function createPage(Site $site, array $attributes): Page
    {
        return Page::query()->create([
            ...$attributes,
            'site_id' => $site->id,
        ]);
    }

    public function updatePage(Page $page, array $attributes): Page
    {
        if ($attributes === []) {
            return $page;
        }

        $page->update($attributes);

        return $page->refresh();
    }

    public function deletePage(Page $page): void
    {
        $page->delete();
    }

    public function listBlogPosts(Site $site): Collection
    {
        return BlogPost::query()
            ->where('site_id', $site->id)
            ->with('coverMedia')
            ->latest('created_at')
            ->get();
    }

    public function findBlogPostBySiteAndId(Site $site, string|int $postId): ?BlogPost
    {
        return BlogPost::query()
            ->where('site_id', $site->id)
            ->where('id', $postId)
            ->with('coverMedia')
            ->first();
    }

    public function createBlogPost(Site $site, array $attributes): BlogPost
    {
        return BlogPost::query()->create([
            ...$attributes,
            'site_id' => $site->id,
        ]);
    }

    public function updateBlogPost(BlogPost $post, array $attributes): BlogPost
    {
        if ($attributes === []) {
            return $post;
        }

        $post->update($attributes);

        return $post->refresh();
    }

    public function deleteBlogPost(BlogPost $post): void
    {
        $post->delete();
    }

    public function countPageRevisions(Site $site, Page $page): int
    {
        return PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->count();
    }

    public function latestRevision(Site $site, Page $page): ?PageRevision
    {
        return PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->first();
    }

    public function latestPublishedRevision(Site $site, Page $page): ?PageRevision
    {
        return PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->first();
    }

    public function maxRevisionVersion(Site $site, Page $page): int
    {
        return (int) PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->max('version');
    }

    public function findRevisionById(Site $site, Page $page, string|int $revisionId): ?PageRevision
    {
        return PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->where('id', $revisionId)
            ->first();
    }

    public function createRevision(Site $site, Page $page, array $attributes): PageRevision
    {
        return PageRevision::query()->create([
            ...$attributes,
            'site_id' => $site->id,
            'page_id' => $page->id,
        ]);
    }

    public function updateRevision(PageRevision $revision, array $attributes): PageRevision
    {
        if ($attributes === []) {
            return $revision;
        }

        $revision->update($attributes);

        return $revision->refresh();
    }

    public function clearPublishedRevisions(Site $site, Page $page): void
    {
        PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->whereNotNull('published_at')
            ->update(['published_at' => null]);
    }

    public function listMedia(Site $site): Collection
    {
        return Media::query()
            ->where('site_id', $site->id)
            ->latest('id')
            ->get();
    }

    public function createMedia(Site $site, array $attributes): Media
    {
        return Media::query()->create([
            ...$attributes,
            'site_id' => $site->id,
        ]);
    }

    public function findMediaById(Site $site, string|int $mediaId): ?Media
    {
        return Media::query()
            ->where('site_id', $site->id)
            ->where('id', $mediaId)
            ->first();
    }

    public function findMediaByPath(Site $site, string $path): ?Media
    {
        return Media::query()
            ->where('site_id', $site->id)
            ->where('path', $path)
            ->first();
    }

    public function updateMedia(Media $media, array $attributes): Media
    {
        if ($attributes === []) {
            return $media;
        }

        $media->update($attributes);

        return $media->refresh();
    }

    public function deleteMedia(Media $media): void
    {
        $media->delete();
    }
}
