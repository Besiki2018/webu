<?php

namespace App\Services;

use App\Cms\Contracts\CmsRepositoryContract;
use App\Models\Page;
use App\Models\Project;
use App\Models\Site;
use Illuminate\Support\Facades\Schema;

class CmsRuntimePayloadService
{
    public function __construct(
        protected CmsRepositoryContract $repository,
        protected CmsLocaleResolver $localeResolver,
        protected CmsTypographyService $typography,
        protected CmsThemeTokenLayerResolver $themeTokenLayers
    ) {}

    /**
     * Build runtime bootstrap payload for a project's CMS-aware frontend.
     *
     * @param  array<string, mixed>  $routeParams
     * @return array<string, mixed>
     */
    public function buildBootstrapPayload(
        Project $project,
        string $slug,
        ?string $locale = null,
        ?string $resolvedDomain = null,
        array $routeParams = [],
        bool $allowDraftPreview = false
    ): array {
        $site = $this->resolveSite($project);
        $normalizedSlug = $this->normalizeSlug($slug);
        $siteLocale = $site->locale ?? CmsLocaleResolver::PRIMARY_LOCALE;
        $requestedLocale = $this->normalizeLocale($locale, $siteLocale);

        $pagePayload = $this->buildPagePayload($site, $normalizedSlug, $requestedLocale, $siteLocale, $allowDraftPreview);
        $globalPayload = $this->buildGlobalSettingsPayload($site, $requestedLocale, $siteLocale);
        $menuKeys = $this->resolveMenuKeys($site);
        $menuPayloads = [];
        foreach ($menuKeys as $menuKey) {
            $menuPayloads[$menuKey] = $this->buildMenuPayload($site, $menuKey, $requestedLocale, $siteLocale);
        }

        $resolvedLocale = $this->pickLocalizedLocale(
            [
                $pagePayload,
                ...array_values($menuPayloads),
                $globalPayload,
            ],
            $requestedLocale,
            $siteLocale
        );

        $availableLocales = $this->localeResolver->mergeAvailableLocales(
            [
                $pagePayload['available_locales'] ?? [],
                $globalPayload['available_locales'] ?? [],
                ...array_map(
                    fn (array $menuPayload): array => $menuPayload['available_locales'] ?? [],
                    array_values($menuPayloads)
                ),
            ],
            $siteLocale
        );
        $typographyPayload = $this->typography->resolveTypography($site->theme_settings ?? [], $site);
        $menuData = [];
        foreach ($menuPayloads as $menuKey => $menuPayload) {
            $menuData[$menuKey] = $menuPayload['data'];
        }

        return [
            'project_id' => $project->id,
            'site_id' => $site->id,
            'resolved_domain' => $resolvedDomain,
            'slug' => $pagePayload['slug'],
            'requested_slug' => $normalizedSlug,
            'locale' => $resolvedLocale,
            'route' => [
                'slug' => $pagePayload['slug'],
                'requested_slug' => $normalizedSlug,
                'locale' => $resolvedLocale,
                'domain' => $resolvedDomain,
                'params' => $this->normalizeRouteParams($routeParams),
            ],
            'site' => $this->buildSitePayload($site),
            'typography' => $typographyPayload,
            'theme_token_layers' => $this->themeTokenLayers->resolveForSite($site, $project),
            'layout_primitives' => config('layout-primitives', []),
            'global_settings' => $globalPayload['data'],
            'menus' => $menuData,
            'page' => $pagePayload['page'],
            'revision' => $pagePayload['revision'],
            'meta' => [
                'generated_at' => now()->toISOString(),
                'source' => 'cms-runtime-bridge',
                'draft_preview' => $allowDraftPreview,
                'requested_locale' => $requestedLocale,
                'resolved_locale' => $resolvedLocale,
                'fallback_locale' => CmsLocaleResolver::PRIMARY_LOCALE,
                'secondary_fallback_locale' => CmsLocaleResolver::SECONDARY_FALLBACK_LOCALE,
                'site_locale' => $siteLocale,
                'available_locales' => $availableLocales,
                'endpoints' => [
                    'resolve' => route('public.sites.resolve'),
                    'settings' => route('public.sites.settings', ['site' => $site->id]),
                    'typography' => route('public.sites.theme.typography', ['site' => $site->id]),
                    'menu' => str_replace('__key__', '{key}', route('public.sites.menu', ['site' => $site->id, 'key' => '__key__'])),
                    'header_menu' => route('public.sites.menu', ['site' => $site->id, 'key' => 'header']),
                    'footer_menu' => route('public.sites.menu', ['site' => $site->id, 'key' => 'footer']),
                    'page' => route('public.sites.page', ['site' => $site->id, 'slug' => $pagePayload['slug']]),
                    'ecommerce_products' => route('public.sites.ecommerce.products.index', ['site' => $site->id]),
                    'ecommerce_product' => str_replace('__slug__', '{slug}', route('public.sites.ecommerce.products.show', ['site' => $site->id, 'slug' => '__slug__'])),
                    'ecommerce_create_cart' => route('public.sites.ecommerce.carts.store', ['site' => $site->id]),
                    'ecommerce_cart' => str_replace('__cart__', '{cart_id}', route('public.sites.ecommerce.carts.show', ['site' => $site->id, 'cart' => '__cart__'])),
                    'ecommerce_add_cart_item' => str_replace('__cart__', '{cart_id}', route('public.sites.ecommerce.carts.items.store', ['site' => $site->id, 'cart' => '__cart__'])),
                    'ecommerce_update_cart_item' => str_replace(
                        ['__cart__', '__item__'],
                        ['{cart_id}', '{item_id}'],
                        route('public.sites.ecommerce.carts.items.update', ['site' => $site->id, 'cart' => '__cart__', 'item' => '__item__'])
                    ),
                    'ecommerce_remove_cart_item' => str_replace(
                        ['__cart__', '__item__'],
                        ['{cart_id}', '{item_id}'],
                        route('public.sites.ecommerce.carts.items.destroy', ['site' => $site->id, 'cart' => '__cart__', 'item' => '__item__'])
                    ),
                    'ecommerce_shipping_options' => str_replace(
                        '__cart__',
                        '{cart_id}',
                        route('public.sites.ecommerce.carts.shipping.options', ['site' => $site->id, 'cart' => '__cart__'])
                    ),
                    'ecommerce_shipping_update' => str_replace(
                        '__cart__',
                        '{cart_id}',
                        route('public.sites.ecommerce.carts.shipping.update', ['site' => $site->id, 'cart' => '__cart__'])
                    ),
                    'ecommerce_shipment_tracking' => route('public.sites.ecommerce.shipments.track', ['site' => $site->id]),
                    'ecommerce_checkout' => str_replace('__cart__', '{cart_id}', route('public.sites.ecommerce.carts.checkout', ['site' => $site->id, 'cart' => '__cart__'])),
                    'ecommerce_payment_start' => str_replace('__order__', '{order_id}', route('public.sites.ecommerce.orders.payment.start', ['site' => $site->id, 'order' => '__order__'])),
                    'booking_services' => route('public.sites.booking.services', ['site' => $site->id]),
                    'booking_slots' => route('public.sites.booking.slots', ['site' => $site->id]),
                    'booking_create' => route('public.sites.booking.bookings.store', ['site' => $site->id]),
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $routeParams
     * @return array<string, mixed>
     */
    private function normalizeRouteParams(array $routeParams): array
    {
        $normalized = [];

        foreach ($routeParams as $key => $value) {
            $key = trim((string) $key);
            if ($key === '') {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $normalized[$key] = $value === null ? null : (string) $value;

                continue;
            }

            if (! is_array($value)) {
                continue;
            }

            $nested = [];
            foreach ($value as $nestedKey => $nestedValue) {
                if (! is_scalar($nestedValue) && $nestedValue !== null) {
                    continue;
                }
                $nested[(string) $nestedKey] = $nestedValue === null ? null : (string) $nestedValue;
            }

            if ($nested !== []) {
                $normalized[$key] = $nested;
            }
        }

        // Canonical alias for builder/runtime route param binding on PDP pages.
        if (! array_key_exists('slug', $normalized) && isset($normalized['product_slug']) && is_string($normalized['product_slug'])) {
            $normalized['slug'] = $normalized['product_slug'];
        }
        if (! array_key_exists('slug', $normalized) && isset($normalized['category_slug']) && is_string($normalized['category_slug'])) {
            $normalized['slug'] = $normalized['category_slug'];
        }
        if (! array_key_exists('id', $normalized) && isset($normalized['order_id']) && is_string($normalized['order_id'])) {
            $normalized['id'] = $normalized['order_id'];
        }

        if (! array_key_exists('requested_slug', $normalized) && isset($normalized['page_slug']) && is_string($normalized['page_slug'])) {
            $normalized['requested_slug'] = $normalized['page_slug'];
        }

        return $normalized;
    }

    /**
     * Normalize requested slug from path/query.
     */
    public function normalizeSlug(string $slug): string
    {
        $slug = trim(strtolower($slug));
        $slug = trim($slug, '/');

        if ($slug === '') {
            return 'home';
        }

        $segments = array_values(array_filter(explode('/', $slug)));
        if ($segments === []) {
            return 'home';
        }

        $first = $segments[0] ?? '';
        $second = $segments[1] ?? '';

        // Canonical storefront route mapping for dynamic routes.
        if ($first === 'shop' || $first === 'category' || $first === 'categories') {
            return 'shop';
        }
        if ($first === 'product' || $first === 'products') {
            return 'product';
        }
        if ($first === 'cart') {
            return 'cart';
        }
        if ($first === 'checkout') {
            return 'checkout';
        }
        if (in_array($first, ['login', 'register', 'auth'], true)) {
            return 'login';
        }
        if ($first === 'account') {
            if (in_array($second, ['login', 'register'], true)) {
                return 'login';
            }

            return $second === 'orders' && isset($segments[2]) ? 'order' : ($second === 'orders' ? 'orders' : 'account');
        }
        if ($first === 'orders') {
            return isset($segments[1]) ? 'order' : 'orders';
        }

        // Legacy behavior: use last segment for simple page routes.
        $leaf = $segments[count($segments) - 1] ?? 'home';

        // Fallback to "home" if malformed.
        return preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $leaf) ? $leaf : 'home';
    }

    /**
     * @return array<int, string>
     */
    private function resolveMenuKeys(Site $site): array
    {
        $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $layout = is_array(data_get($themeSettings, 'layout')) ? data_get($themeSettings, 'layout') : [];

        $headerKey = $this->normalizeConfigMenuKey((string) data_get($layout, 'header_menu_key', ''), 'header');

        return array_values(array_unique([
            $headerKey,
            'header',
        ]));
    }

    private function normalizeConfigMenuKey(string $value, string $fallback): string
    {
        $value = trim(strtolower($value));
        if ($value === '' || ! preg_match('/^[a-z0-9_-]{1,64}$/', $value)) {
            return $fallback;
        }

        return $value;
    }

    private function normalizeLocale(?string $locale, string $default): string
    {
        $fallback = trim(strtolower($default)) ?: 'ka';
        $locale = trim(strtolower((string) $locale));

        if ($locale === '') {
            return $fallback;
        }

        // Keep locale compact to avoid arbitrary values.
        if (! preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $locale)) {
            return $fallback;
        }

        return $locale;
    }

    private function resolveSite(Project $project): Site
    {
        if (! Schema::hasTable('sites')) {
            throw new \RuntimeException('CMS runtime is unavailable because sites table does not exist.');
        }

        $site = $this->repository->findSiteByProject($project);
        if ($site) {
            return $site;
        }

        return app(SiteProvisioningService::class)->provisionForProject($project);
    }

    private function buildSitePayload(Site $site): array
    {
        return [
            'id' => $site->id,
            'project_id' => $site->project_id,
            'name' => $site->name,
            'status' => $site->status,
            'locale' => $site->locale,
            'primary_domain' => $site->primary_domain,
            'subdomain' => $site->subdomain,
            'theme_settings' => $site->theme_settings ?? [],
            'typography' => $this->typography->resolveTypography($site->theme_settings ?? [], $site),
            'updated_at' => $site->updated_at?->toISOString(),
        ];
    }

    /**
     * @return array{
     *   data: array<string, mixed>,
     *   resolved_locale: string,
     *   available_locales: array<int, string>,
     *   localized: bool
     * }
     */
    private function buildGlobalSettingsPayload(Site $site, string $requestedLocale, string $siteLocale): array
    {
        $site->loadMissing('globalSettings.logoMedia');

        $global = $site->globalSettings;
        $logoPath = $global?->logoMedia?->path;

        $contactPayload = $this->localeResolver->resolvePayload($global?->contact_json ?? [], $requestedLocale, $siteLocale);
        $socialPayload = $this->localeResolver->resolvePayload($global?->social_links_json ?? [], $requestedLocale, $siteLocale);
        $analyticsPayload = $this->localeResolver->resolvePayload($global?->analytics_ids_json ?? [], $requestedLocale, $siteLocale);

        $resolvedLocale = $this->pickLocalizedLocale(
            [
                [
                    'localized' => $contactPayload['localized'],
                    'resolved_locale' => $contactPayload['resolved_locale'],
                ],
                [
                    'localized' => $socialPayload['localized'],
                    'resolved_locale' => $socialPayload['resolved_locale'],
                ],
                [
                    'localized' => $analyticsPayload['localized'],
                    'resolved_locale' => $analyticsPayload['resolved_locale'],
                ],
            ],
            $requestedLocale,
            $siteLocale
        );

        $availableLocales = $this->localeResolver->mergeAvailableLocales(
            [
                $contactPayload['available_locales'] ?? [],
                $socialPayload['available_locales'] ?? [],
                $analyticsPayload['available_locales'] ?? [],
            ],
            $siteLocale
        );

        return [
            'data' => [
                'logo_media_id' => $global?->logo_media_id,
                'logo_asset_url' => $logoPath
                    ? route('public.sites.assets', ['site' => $site->id, 'path' => $logoPath])
                    : null,
                'contact_json' => $contactPayload['content'],
                'social_links_json' => $socialPayload['content'],
                'analytics_ids_json' => $analyticsPayload['content'],
            ],
            'resolved_locale' => $resolvedLocale,
            'available_locales' => $availableLocales,
            'localized' => (bool) ($contactPayload['localized'] || $socialPayload['localized'] || $analyticsPayload['localized']),
        ];
    }

    /**
     * @return array{
     *   data: array<string, mixed>,
     *   resolved_locale: string,
     *   available_locales: array<int, string>,
     *   localized: bool
     * }
     */
    private function buildMenuPayload(Site $site, string $key, string $requestedLocale, string $siteLocale): array
    {
        $menu = $this->repository->findMenuByKey($site, $key);

        $menuPayload = $this->localeResolver->resolvePayload($menu?->items_json ?? [], $requestedLocale, $siteLocale);

        return [
            'data' => [
                'key' => $key,
                'items_json' => $menuPayload['content'],
                'updated_at' => $menu?->updated_at?->toISOString(),
            ],
            'resolved_locale' => $menuPayload['resolved_locale'],
            'available_locales' => $menuPayload['available_locales'],
            'localized' => $menuPayload['localized'],
        ];
    }

    /**
     * @return array{slug: string, page: array<string, mixed>|null, revision: array<string, mixed>|null, resolved_locale: string, available_locales: array<int, string>, localized: bool}
     */
    private function buildPagePayload(Site $site, string $requestedSlug, string $requestedLocale, string $siteLocale, bool $allowDraftPreview = false): array
    {
        [$page, $resolvedSlug] = $allowDraftPreview
            ? $this->resolvePageForDraft($site, $requestedSlug)
            : $this->resolvePublishedPage($site, $requestedSlug);

        if (! $page) {
            $resolvedLocale = $this->localeResolver->resolveLocale($requestedLocale, $siteLocale);

            return [
                'slug' => $resolvedSlug,
                'page' => null,
                'revision' => null,
                'resolved_locale' => $resolvedLocale,
                'available_locales' => [$resolvedLocale],
                'localized' => false,
            ];
        }

        $revision = $allowDraftPreview
            ? ($this->repository->latestRevision($site, $page) ?? $this->repository->latestPublishedRevision($site, $page))
            : $this->repository->latestPublishedRevision($site, $page);

        $contentPayload = $this->localeResolver->resolvePayload(
            $revision?->content_json ?? ['sections' => []],
            $requestedLocale,
            $siteLocale
        );

        return [
            'slug' => $resolvedSlug,
            'page' => [
                'id' => $page->id,
                'title' => $page->title,
                'slug' => $page->slug,
                'seo_title' => $page->seo_title,
                'seo_description' => $page->seo_description,
                'status' => $page->status,
            ],
            'revision' => $revision ? [
                'id' => $revision->id,
                'version' => $revision->version,
                'published_at' => $revision->published_at?->toISOString(),
                'content_json' => $contentPayload['content'] ?? ['sections' => []],
            ] : null,
            'resolved_locale' => $contentPayload['resolved_locale'],
            'available_locales' => $contentPayload['available_locales'],
            'localized' => $contentPayload['localized'],
        ];
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

    /**
     * @return array{0: Page|null, 1: string}
     */
    private function resolvePublishedPage(Site $site, string $requestedSlug): array
    {
        $page = $this->repository->findPublishedPageBySlug($site, $requestedSlug);

        if ($page) {
            return [$page, $requestedSlug];
        }

        if ($requestedSlug !== 'home') {
            $fallback = $this->repository->findPublishedPageBySlug($site, 'home');

            if ($fallback) {
                return [$fallback, 'home'];
            }
        }

        return [null, $requestedSlug];
    }

    /**
     * Resolve page for draft preview (any page by slug, fallback to home).
     *
     * @return array{0: Page|null, 1: string}
     */
    private function resolvePageForDraft(Site $site, string $requestedSlug): array
    {
        $page = $this->repository->findPageBySiteAndSlug($site, $requestedSlug);

        if ($page) {
            return [$page, $requestedSlug];
        }

        if ($requestedSlug !== 'home') {
            $fallback = $this->repository->findPageBySiteAndSlug($site, 'home');

            if ($fallback) {
                return [$fallback, 'home'];
            }
        }

        return [null, $requestedSlug];
    }
}
