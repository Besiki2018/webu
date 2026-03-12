<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Models\GlobalSetting;
use App\Models\Media;
use App\Models\Menu;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Site;
use App\Models\SiteCourierSetting;
use App\Models\SitePaymentGatewaySetting;

class UniversalContentStorageBridgeService
{
    public const SCHEMA_NAME = 'universal_content_storage_bridge';

    public const SCHEMA_VERSION = 1;

    /**
     * Build a read-only canonical snapshot over the current CMS storage tables.
     *
     * This is an incremental bridge for P5-F2-01 and intentionally does not write
     * to any universal tables yet.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function snapshot(Site $site, array $options = []): array
    {
        $includePayloads = (bool) ($options['include_payloads'] ?? true);

        $site = $site->fresh() ?? $site;
        $site->loadMissing([
            'project',
            'pages.revisions',
            'menus',
            'media',
            'blogPosts.coverMedia',
            'globalSettings',
            'paymentGatewaySettings',
            'courierSettings',
        ]);

        $pages = $site->pages
            ->sortBy(fn (Page $page): string => sprintf('%s|%020d', (string) $page->slug, (int) $page->id))
            ->values()
            ->map(fn (Page $page): array => $this->normalizePage($page, $includePayloads))
            ->all();

        $posts = $site->blogPosts
            ->sortBy(fn (BlogPost $post): string => sprintf('%s|%020d', (string) $post->slug, (int) $post->id))
            ->values()
            ->map(fn (BlogPost $post): array => $this->normalizeBlogPost($post, $includePayloads))
            ->all();

        $menus = $site->menus
            ->sortBy(fn (Menu $menu): string => sprintf('%s|%020d', (string) $menu->key, (int) $menu->id))
            ->values()
            ->map(fn (Menu $menu): array => $this->normalizeMenu($menu, $includePayloads))
            ->all();

        $media = $site->media
            ->sortBy(fn (Media $item): string => sprintf('%020d', (int) $item->id))
            ->values()
            ->map(fn (Media $item): array => $this->normalizeMedia($item, $includePayloads))
            ->all();

        $settings = $this->normalizeSettings($site, $includePayloads);

        return [
            'schema' => [
                'name' => self::SCHEMA_NAME,
                'version' => self::SCHEMA_VERSION,
                'task' => 'P5-F2-01',
            ],
            'site' => [
                'id' => (string) $site->id,
                'project_id' => (string) $site->project_id,
                'name' => (string) $site->name,
                'locale' => (string) $site->locale,
                'status' => (string) $site->status,
                'project_type' => (string) ($site->project?->getAttribute('type') ?? ''),
            ],
            'sources' => [
                'pages' => ['pages', 'page_revisions'],
                'posts' => ['blog_posts'],
                'menus' => ['menus'],
                'media' => ['media'],
                'settings' => ['sites.theme_settings', 'global_settings', 'site_payment_gateway_settings', 'site_courier_settings'],
            ],
            'counts' => [
                'pages' => count($pages),
                'posts' => count($posts),
                'menus' => count($menus),
                'media' => count($media),
                'settings' => count($settings),
            ],
            'content' => [
                'pages' => $pages,
                'posts' => $posts,
                'menus' => $menus,
                'media' => $media,
                'settings' => $settings,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePage(Page $page, bool $includePayloads): array
    {
        /** @var \Illuminate\Support\Collection<int, PageRevision> $revisions */
        $revisions = $page->revisions instanceof \Illuminate\Support\Collection
            ? $page->revisions
            : collect();

        $latestRevision = $revisions
            ->sortByDesc(fn (PageRevision $revision): int => (int) $revision->version)
            ->first();

        $latestPublishedRevision = $revisions
            ->filter(fn (PageRevision $revision): bool => $revision->published_at !== null)
            ->sortByDesc(fn (PageRevision $revision): int => $revision->published_at?->getTimestamp() ?? 0)
            ->first();

        return [
            'kind' => 'page',
            'source' => [
                'table' => 'pages',
                'id' => (int) $page->id,
            ],
            'key' => 'page:'.$page->slug,
            'slug' => (string) $page->slug,
            'title' => (string) $page->title,
            'status' => (string) $page->status,
            'seo' => [
                'title' => $page->seo_title,
                'description' => $page->seo_description,
            ],
            'revisions' => [
                'latest' => $this->normalizePageRevision($latestRevision, $includePayloads),
                'published' => $this->normalizePageRevision($latestPublishedRevision, $includePayloads),
                'count' => $revisions->count(),
            ],
            'updated_at' => $page->updated_at?->toISOString(),
            'created_at' => $page->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function normalizePageRevision(?PageRevision $revision, bool $includePayloads): ?array
    {
        if (! $revision) {
            return null;
        }

        return [
            'source' => [
                'table' => 'page_revisions',
                'id' => (int) $revision->id,
            ],
            'site_id' => (string) $revision->site_id,
            'page_id' => (int) $revision->page_id,
            'version' => (int) $revision->version,
            'is_published' => $revision->published_at !== null,
            'published_at' => $revision->published_at?->toISOString(),
            'content_json' => $includePayloads ? ($revision->content_json ?? []) : null,
            'content_stats' => [
                'has_payload' => is_array($revision->content_json ?? null),
                'top_level_keys' => is_array($revision->content_json ?? null) ? array_keys($revision->content_json ?? []) : [],
            ],
            'created_at' => $revision->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeBlogPost(BlogPost $post, bool $includePayloads): array
    {
        return [
            'kind' => 'post',
            'source' => [
                'table' => 'blog_posts',
                'id' => (int) $post->id,
            ],
            'key' => 'post:'.$post->slug,
            'slug' => (string) $post->slug,
            'title' => (string) $post->title,
            'status' => (string) $post->status,
            'excerpt' => $post->excerpt,
            'content' => $includePayloads ? $post->content : null,
            'content_stats' => [
                'length' => mb_strlen((string) ($post->content ?? '')),
            ],
            'cover_media_id' => $post->cover_media_id ? (int) $post->cover_media_id : null,
            'cover_media_path' => $post->coverMedia?->path,
            'published_at' => $post->published_at?->toISOString(),
            'updated_at' => $post->updated_at?->toISOString(),
            'created_at' => $post->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMenu(Menu $menu, bool $includePayloads): array
    {
        return [
            'kind' => 'menu',
            'source' => [
                'table' => 'menus',
                'id' => (int) $menu->id,
            ],
            'key' => 'menu:'.$menu->key,
            'menu_key' => (string) $menu->key,
            'items_json' => $includePayloads ? ($menu->items_json ?? []) : null,
            'items_count' => is_array($menu->items_json) ? count($menu->items_json) : 0,
            'updated_at' => $menu->updated_at?->toISOString(),
            'created_at' => $menu->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeMedia(Media $media, bool $includePayloads): array
    {
        return [
            'kind' => 'media',
            'source' => [
                'table' => 'media',
                'id' => (int) $media->id,
            ],
            'key' => 'media:'.$media->id,
            'path' => (string) $media->path,
            'mime' => (string) $media->mime,
            'size' => (int) ($media->size ?? 0),
            'meta_json' => $includePayloads ? ($media->meta_json ?? []) : null,
            'updated_at' => $media->updated_at?->toISOString(),
            'created_at' => $media->created_at?->toISOString(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSettings(Site $site, bool $includePayloads): array
    {
        $settings = [];

        $settings[] = [
            'kind' => 'setting',
            'type' => 'site_theme_settings',
            'source' => [
                'table' => 'sites',
                'column' => 'theme_settings',
                'id' => (string) $site->id,
            ],
            'key' => 'site.theme_settings',
            'payload' => $includePayloads ? (is_array($site->theme_settings) ? $site->theme_settings : []) : null,
            'updated_at' => $site->updated_at?->toISOString(),
        ];

        $globalSettings = $site->globalSettings;
        $settings[] = $this->normalizeGlobalSettings($globalSettings, $includePayloads, (string) $site->id);

        foreach ($site->paymentGatewaySettings->sortBy('provider_slug')->values() as $gateway) {
            $settings[] = $this->normalizePaymentGatewaySetting($gateway, $includePayloads);
        }

        foreach ($site->courierSettings->sortBy('courier_slug')->values() as $courier) {
            $settings[] = $this->normalizeCourierSetting($courier, $includePayloads);
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeGlobalSettings(?GlobalSetting $setting, bool $includePayloads, string $siteId): array
    {
        if (! $setting) {
            return [
                'kind' => 'setting',
                'type' => 'global_settings',
                'source' => [
                    'table' => 'global_settings',
                    'id' => null,
                ],
                'key' => 'global_settings',
                'exists' => false,
                'site_id' => $siteId,
                'payload' => $includePayloads ? [] : null,
                'updated_at' => null,
            ];
        }

        return [
            'kind' => 'setting',
            'type' => 'global_settings',
            'source' => [
                'table' => 'global_settings',
                'id' => (int) $setting->id,
            ],
            'key' => 'global_settings',
            'exists' => true,
            'site_id' => (string) $setting->site_id,
            'logo_media_id' => $setting->logo_media_id ? (int) $setting->logo_media_id : null,
            'payload' => $includePayloads ? [
                'contact_json' => $setting->contact_json ?? [],
                'social_links_json' => $setting->social_links_json ?? [],
                'analytics_ids_json' => $setting->analytics_ids_json ?? [],
            ] : null,
            'updated_at' => $setting->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePaymentGatewaySetting(SitePaymentGatewaySetting $setting, bool $includePayloads): array
    {
        return [
            'kind' => 'setting',
            'type' => 'payment_gateway_setting',
            'source' => [
                'table' => 'site_payment_gateway_settings',
                'id' => (int) $setting->id,
            ],
            'key' => 'payment_gateway:'.$setting->provider_slug,
            'site_id' => (string) $setting->site_id,
            'provider_slug' => (string) $setting->provider_slug,
            'availability' => (string) $setting->availability,
            'payload' => $includePayloads ? (is_array($setting->config) ? $setting->config : []) : null,
            'updated_at' => $setting->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeCourierSetting(SiteCourierSetting $setting, bool $includePayloads): array
    {
        return [
            'kind' => 'setting',
            'type' => 'courier_setting',
            'source' => [
                'table' => 'site_courier_settings',
                'id' => (int) $setting->id,
            ],
            'key' => 'courier:'.$setting->courier_slug,
            'site_id' => (string) $setting->site_id,
            'courier_slug' => (string) $setting->courier_slug,
            'availability' => (string) $setting->availability,
            'payload' => $includePayloads ? (is_array($setting->config) ? $setting->config : []) : null,
            'updated_at' => $setting->updated_at?->toISOString(),
        ];
    }
}
