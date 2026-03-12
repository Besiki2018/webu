<?php

namespace App\Cms\Services;

use App\Cms\Contracts\CmsPanelMenuServiceContract;
use App\Cms\Contracts\CmsRepositoryContract;
use App\Cms\Exceptions\CmsDomainException;
use App\Cms\Support\LocalizedCmsPayload;
use App\Models\Menu;
use App\Models\Site;
use App\Services\CmsLocaleResolver;

class CmsPanelMenuService implements CmsPanelMenuServiceContract
{
    private const SYSTEM_MENU_KEYS = ['header', 'footer'];

    public function __construct(
        protected CmsRepositoryContract $repository,
        protected LocalizedCmsPayload $localizedPayload
    ) {}

    public function index(Site $site, ?string $requestedLocale = null): array
    {
        foreach (self::SYSTEM_MENU_KEYS as $systemKey) {
            $this->repository->firstOrCreateMenu(
                $site,
                $systemKey,
                ['items_json' => []]
            );
        }

        return [
            'site_id' => $site->id,
            'locale' => $this->localizedPayload->normalizeLocale($requestedLocale, $site->locale ?? CmsLocaleResolver::PRIMARY_LOCALE),
            'menus' => $this->repository
                ->listMenus($site)
                ->map(fn (Menu $menu): array => $this->serializeMenu($site, $menu, $requestedLocale))
                ->values()
                ->all(),
        ];
    }

    public function store(Site $site, string $key, array $items = [], ?string $locale = null): array
    {
        $key = $this->normalizeMenuKey($key);
        $menu = $this->repository->findMenuByKey($site, $key);
        if ($menu) {
            throw new CmsDomainException('Menu key already exists.', 422, [
                'key' => $key,
            ]);
        }

        $siteLocale = $this->localizedPayload->normalizeLocale($site->locale, CmsLocaleResolver::PRIMARY_LOCALE);
        $targetLocale = $this->localizedPayload->normalizeLocale($locale, $siteLocale);
        $payload = $locale !== null
            ? $this->localizedPayload->mergeForLocale([], $targetLocale, $items, $siteLocale)
            : $items;

        $menu = $this->repository->updateOrCreateMenu(
            $site,
            $key,
            ['items_json' => $payload]
        );

        return [
            'message' => 'Menu created successfully.',
            'menu' => $this->serializeMenu($site, $menu, $targetLocale),
        ];
    }

    public function show(Site $site, string $key, ?string $requestedLocale = null): array
    {
        $key = $this->normalizeMenuKey($key);

        $menu = $this->repository->firstOrCreateMenu(
            $site,
            $key,
            ['items_json' => []]
        );

        return $this->serializeMenu($site, $menu, $requestedLocale) + [
            'site_id' => $site->id,
        ];
    }

    public function update(Site $site, string $key, array $items, ?string $locale = null): array
    {
        $key = $this->normalizeMenuKey($key);
        $siteLocale = $this->localizedPayload->normalizeLocale($site->locale, CmsLocaleResolver::PRIMARY_LOCALE);
        $targetLocale = $this->localizedPayload->normalizeLocale($locale, $siteLocale);
        $existing = $this->repository->findMenuByKey($site, $key);

        $payload = $locale !== null
            ? $this->localizedPayload->mergeForLocale(
                $existing?->items_json ?? [],
                $targetLocale,
                $items,
                $siteLocale
            )
            : $items;

        $menu = $this->repository->updateOrCreateMenu(
            $site,
            $key,
            ['items_json' => $payload]
        );

        return [
            'message' => 'Menu updated successfully.',
            'menu' => $this->serializeMenu($site, $menu, $targetLocale),
        ];
    }

    public function destroy(Site $site, string $key): array
    {
        $key = $this->normalizeMenuKey($key);

        if (in_array($key, self::SYSTEM_MENU_KEYS, true)) {
            throw new CmsDomainException('System menu cannot be deleted.', 422, [
                'key' => $key,
            ]);
        }

        $menu = $this->repository->findMenuByKey($site, $key);
        if (! $menu) {
            throw new CmsDomainException('Menu not found.', 404, [
                'key' => $key,
            ]);
        }

        $this->repository->deleteMenu($menu);

        return [
            'message' => 'Menu deleted successfully.',
            'deleted_key' => $key,
        ];
    }

    private function normalizeMenuKey(string $key): string
    {
        $key = trim(strtolower($key));

        if (! preg_match('/^[a-z0-9_-]{1,64}$/', $key)) {
            throw new CmsDomainException('Invalid menu key.', 422);
        }

        return $key;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMenu(Site $site, Menu $menu, ?string $requestedLocale = null): array
    {
        $isSystem = in_array($menu->key, self::SYSTEM_MENU_KEYS, true);
        $siteLocale = $this->localizedPayload->normalizeLocale($site->locale, CmsLocaleResolver::PRIMARY_LOCALE);
        $resolvedLocale = $this->localizedPayload->normalizeLocale($requestedLocale, $siteLocale);
        $localized = $this->localizedPayload->resolve($menu->items_json ?? [], $resolvedLocale, $siteLocale);

        return [
            'id' => $menu->id,
            'site_id' => $menu->site_id,
            'locale' => $localized['resolved_locale'],
            'key' => $menu->key,
            'items_json' => is_array($localized['content']) ? $localized['content'] : [],
            'updated_at' => $menu->updated_at?->toISOString(),
            'is_system' => $isSystem,
            'meta' => [
                'requested_locale' => $requestedLocale,
                'resolved_locale' => $localized['resolved_locale'],
                'available_locales' => $localized['available_locales'],
                'localized' => $localized['localized'],
            ],
        ];
    }
}
