<?php

namespace App\Cms\Services;

use App\Cms\Contracts\CmsPublicSiteServiceContract;
use App\Cms\Contracts\CmsRepositoryContract;
use App\Cms\Exceptions\CmsDomainException;
use App\Models\Media;
use App\Models\Page;
use App\Models\Site;
use App\Models\User;
use App\Services\CmsLocaleResolver;
use App\Services\CmsThemeTokenLayerResolver;
use App\Services\CmsTypographyService;
use App\Services\DomainSettingService;
use Illuminate\Support\Facades\Storage;

class CmsPublicSiteService implements CmsPublicSiteServiceContract
{
    public function __construct(
        protected CmsRepositoryContract $repository,
        protected CmsLocaleResolver $localeResolver,
        protected CmsTypographyService $typography,
        protected CmsThemeTokenLayerResolver $themeTokenLayers,
        protected DomainSettingService $domainSettingService
    ) {}

    public function resolve(string $domain, ?string $requestedLocale = null, ?User $viewer = null): array
    {
        $normalizedDomain = $this->normalizeDomain($domain);
        if ($normalizedDomain === null) {
            throw new CmsDomainException('Invalid domain.', 422);
        }

        $site = $this->findSiteByDomain($normalizedDomain);
        if (! $site || $site->status === 'archived' || ! $this->isSiteVisible($site, $viewer)) {
            throw new CmsDomainException('Site not found for this domain.', 404);
        }

        $resolvedLocale = $this->localeResolver->resolveLocale(
            $requestedLocale,
            $site->locale ?? CmsLocaleResolver::PRIMARY_LOCALE
        );

        return [
            'site_id' => $site->id,
            'status' => $site->status,
            'locale' => $resolvedLocale,
            'meta' => [
                'requested_locale' => $requestedLocale,
                'resolved_locale' => $resolvedLocale,
                'fallback_locale' => CmsLocaleResolver::PRIMARY_LOCALE,
                'secondary_fallback_locale' => CmsLocaleResolver::SECONDARY_FALLBACK_LOCALE,
                'site_locale' => $site->locale ?? CmsLocaleResolver::PRIMARY_LOCALE,
            ],
        ];
    }

    public function settings(Site $site, ?string $requestedLocale = null, ?User $viewer = null): array
    {
        $this->assertVisible($site, $viewer, 'Site not found.');
        $site = $this->repository->loadSiteGlobalSettings($site);

        $global = $site->globalSettings;
        $logoPath = $global?->logoMedia?->path;
        $siteLocale = $site->locale ?? CmsLocaleResolver::PRIMARY_LOCALE;

        $themePayload = $this->localeResolver->resolvePayload($site->theme_settings ?? [], $requestedLocale, $siteLocale);
        $contactPayload = $this->localeResolver->resolvePayload($global?->contact_json ?? [], $requestedLocale, $siteLocale);
        $socialPayload = $this->localeResolver->resolvePayload($global?->social_links_json ?? [], $requestedLocale, $siteLocale);
        $analyticsPayload = $this->localeResolver->resolvePayload($global?->analytics_ids_json ?? [], $requestedLocale, $siteLocale);

        $resolvedLocale = $this->pickLocalizedLocale(
            [
                $contactPayload,
                $themePayload,
                $socialPayload,
                $analyticsPayload,
            ],
            $requestedLocale,
            $siteLocale
        );

        $availableLocales = $this->localeResolver->mergeAvailableLocales(
            [
                $themePayload['available_locales'] ?? [],
                $contactPayload['available_locales'] ?? [],
                $socialPayload['available_locales'] ?? [],
                $analyticsPayload['available_locales'] ?? [],
            ],
            $siteLocale
        );

        return [
            'site_id' => $site->id,
            'name' => $site->name,
            'locale' => $resolvedLocale,
            'theme_settings' => $themePayload['content'],
            'typography' => $this->typography->resolveTypography($site->theme_settings ?? [], $site),
            'theme_token_layers' => $this->themeTokenLayers->resolveForSite($site),
            'global_settings' => [
                'logo_media_id' => $global?->logo_media_id,
                'logo_asset_url' => $logoPath ? route('public.sites.assets', ['site' => $site->id, 'path' => $logoPath]) : null,
                'contact_json' => $contactPayload['content'],
                'social_links_json' => $socialPayload['content'],
                'analytics_ids_json' => $analyticsPayload['content'],
            ],
            'updated_at' => $site->updated_at?->toISOString(),
            'meta' => [
                'requested_locale' => $requestedLocale,
                'resolved_locale' => $resolvedLocale,
                'fallback_locale' => CmsLocaleResolver::PRIMARY_LOCALE,
                'secondary_fallback_locale' => CmsLocaleResolver::SECONDARY_FALLBACK_LOCALE,
                'site_locale' => $siteLocale,
                'available_locales' => $availableLocales,
            ],
        ];
    }

    public function typography(Site $site, ?string $requestedLocale = null, ?User $viewer = null): array
    {
        $this->assertVisible($site, $viewer, 'Site not found.');

        $siteLocale = $site->locale ?? CmsLocaleResolver::PRIMARY_LOCALE;
        $resolvedLocale = $this->localeResolver->resolveLocale($requestedLocale, $siteLocale);

        return [
            'site_id' => $site->id,
            'locale' => $resolvedLocale,
            'typography' => $this->typography->resolveTypography($site->theme_settings ?? [], $site),
            'available_fonts' => $this->typography->availableFonts($site),
            'meta' => [
                'requested_locale' => $requestedLocale,
                'resolved_locale' => $resolvedLocale,
                'fallback_locale' => CmsLocaleResolver::PRIMARY_LOCALE,
                'secondary_fallback_locale' => CmsLocaleResolver::SECONDARY_FALLBACK_LOCALE,
                'site_locale' => $siteLocale,
            ],
        ];
    }

    public function menu(Site $site, string $key, ?string $requestedLocale = null, ?User $viewer = null): array
    {
        $this->assertVisible($site, $viewer, 'Site not found.');

        $menu = $this->repository->findMenuByKey($site, $key);
        if (! $menu) {
            throw new CmsDomainException('Menu not found.', 404);
        }

        $siteLocale = $site->locale ?? CmsLocaleResolver::PRIMARY_LOCALE;
        $menuPayload = $this->localeResolver->resolvePayload($menu->items_json ?? [], $requestedLocale, $siteLocale);
        $availableLocales = $this->localeResolver->mergeAvailableLocales(
            [$menuPayload['available_locales'] ?? []],
            $siteLocale
        );

        return [
            'site_id' => $site->id,
            'locale' => $menuPayload['resolved_locale'],
            'key' => $menu->key,
            'items_json' => $menuPayload['content'],
            'updated_at' => $menu->updated_at?->toISOString(),
            'meta' => [
                'requested_locale' => $requestedLocale,
                'resolved_locale' => $menuPayload['resolved_locale'],
                'fallback_locale' => CmsLocaleResolver::PRIMARY_LOCALE,
                'secondary_fallback_locale' => CmsLocaleResolver::SECONDARY_FALLBACK_LOCALE,
                'site_locale' => $siteLocale,
                'available_locales' => $availableLocales,
            ],
        ];
    }

    public function page(
        Site $site,
        string $slug,
        ?string $requestedLocale = null,
        ?User $viewer = null,
        bool $allowDraftPreview = false
    ): array
    {
        $this->assertVisible($site, $viewer, 'Site not found.');

        $draftPreviewAllowedForViewer = $allowDraftPreview && $this->canPreviewDraftPage($site, $viewer);
        $requestedSlug = $this->normalizePageSlug($slug);
        [$page, $resolvedSlug] = $this->resolvePageWithModeFallback($site, $requestedSlug, $draftPreviewAllowedForViewer);

        if (! $page) {
            throw new CmsDomainException('Published page not found.', 404);
        }

        if (! $draftPreviewAllowedForViewer && $page->status !== 'published') {
            throw new CmsDomainException('Published page not found.', 404);
        }

        $revision = $draftPreviewAllowedForViewer
            ? ($this->repository->latestRevision($site, $page) ?? $this->repository->latestPublishedRevision($site, $page))
            : $this->repository->latestPublishedRevision($site, $page);

        if (! $revision) {
            throw new CmsDomainException(
                $draftPreviewAllowedForViewer ? 'Page content not found.' : 'Published page content not found.',
                404
            );
        }

        $siteLocale = $site->locale ?? CmsLocaleResolver::PRIMARY_LOCALE;
        $contentPayload = $this->localeResolver->resolvePayload($revision->content_json ?? [], $requestedLocale, $siteLocale);
        $availableLocales = $this->localeResolver->mergeAvailableLocales(
            [$contentPayload['available_locales'] ?? []],
            $siteLocale
        );

        return [
            'site_id' => $site->id,
            'locale' => $contentPayload['resolved_locale'],
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'status' => $page->status,
            ],
            'revision' => [
                'id' => $revision->id,
                'version' => $revision->version,
                'published_at' => $revision->published_at?->toISOString(),
                'content_json' => $contentPayload['content'],
            ],
            'meta' => [
                'requested_locale' => $requestedLocale,
                'resolved_locale' => $contentPayload['resolved_locale'],
                'fallback_locale' => CmsLocaleResolver::PRIMARY_LOCALE,
                'secondary_fallback_locale' => CmsLocaleResolver::SECONDARY_FALLBACK_LOCALE,
                'site_locale' => $siteLocale,
                'available_locales' => $availableLocales,
                'draft_preview' => $draftPreviewAllowedForViewer,
                'requested_slug' => $requestedSlug,
                'resolved_slug' => $resolvedSlug,
                'slug_fallback' => $requestedSlug !== $resolvedSlug,
            ],
        ];
    }

    public function asset(Site $site, string $path, ?User $viewer = null): Media
    {
        $this->assertVisible($site, $viewer, 'Site not found.');

        $decodedPath = ltrim(urldecode($path), '/');
        $media = $this->repository->findMediaByPath($site, $decodedPath);

        if (! $media || ! Storage::disk('public')->exists($media->path)) {
            throw new CmsDomainException('Asset not found.', 404);
        }

        return $media;
    }

    private function assertVisible(Site $site, ?User $viewer, string $message): void
    {
        if ($site->status === 'archived' || ! $this->isSiteVisible($site, $viewer)) {
            throw new CmsDomainException($message, 404);
        }
    }

    private function isSiteVisible(Site $site, ?User $viewer): bool
    {
        $project = $site->relationLoaded('project')
            ? $site->project
            : $site->project()->first();

        if (! $project || ! $project->published_at) {
            return false;
        }

        if ($project->published_visibility !== 'private') {
            return true;
        }

        return $viewer && (string) $viewer->id === (string) $project->user_id;
    }

    private function canPreviewDraftPage(Site $site, ?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        if (method_exists($viewer, 'hasAdminBypass') && $viewer->hasAdminBypass()) {
            return true;
        }

        $project = $site->relationLoaded('project')
            ? $site->project
            : $site->project()->first();

        if (! $project) {
            return false;
        }

        return (string) $viewer->id === (string) $project->user_id;
    }

    private function normalizePageSlug(string $slug): string
    {
        $slug = trim(strtolower($slug));
        $slug = trim($slug, '/');

        return $slug !== '' ? $slug : 'home';
    }

    /**
     * @return array{0: Page|null, 1: string}
     */
    private function resolvePageWithModeFallback(Site $site, string $requestedSlug, bool $allowDraftPreview): array
    {
        $page = $allowDraftPreview
            ? $this->repository->findPageBySiteAndSlug($site, $requestedSlug)
            : $this->repository->findPublishedPageBySlug($site, $requestedSlug);

        if ($page) {
            return [$page, $requestedSlug];
        }

        if ($requestedSlug !== 'home') {
            $fallback = $allowDraftPreview
                ? $this->repository->findPageBySiteAndSlug($site, 'home')
                : $this->repository->findPublishedPageBySlug($site, 'home');

            if ($fallback) {
                return [$fallback, 'home'];
            }
        }

        return [null, $requestedSlug];
    }

    private function normalizeDomain(string $domain): ?string
    {
        $domain = trim(strtolower($domain));
        if ($domain === '') {
            return null;
        }

        if (str_contains($domain, '://')) {
            $parsed = parse_url($domain, PHP_URL_HOST);
            if (! is_string($parsed) || $parsed === '') {
                return null;
            }
            $domain = $parsed;
        }

        $domain = preg_replace('/:\d+$/', '', $domain);
        $domain = rtrim($domain, '.');

        return $domain !== '' ? $domain : null;
    }

    private function findSiteByDomain(string $domain): ?Site
    {
        $site = $this->repository->findSiteByPrimaryDomain($domain);
        if ($site) {
            return $site;
        }

        $site = $this->repository->findSiteBySubdomain($domain);
        if ($site) {
            return $site;
        }

        $baseDomain = $this->domainSettingService->getBaseDomain();
        if (! $baseDomain) {
            return null;
        }

        $suffix = '.'.strtolower($baseDomain);
        if (! str_ends_with($domain, $suffix)) {
            return null;
        }

        $label = substr($domain, 0, -strlen($suffix));
        if ($label === '' || str_contains($label, '.')) {
            return null;
        }

        return $this->repository->findSiteBySubdomain($label);
    }

    /**
     * @param  array<int, array<string, mixed>>  $payloads
     */
    private function pickLocalizedLocale(array $payloads, ?string $requestedLocale, string $siteLocale): string
    {
        foreach ($payloads as $payload) {
            $isLocalized = (bool) ($payload['localized'] ?? false);
            $resolved = $payload['resolved_locale'] ?? null;
            if ($isLocalized && is_string($resolved)) {
                return $resolved;
            }
        }

        return $this->localeResolver->resolveLocale($requestedLocale, $siteLocale);
    }
}
