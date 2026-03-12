<?php

namespace App\Cms\Support;

use App\Services\CmsLocaleResolver;

class LocalizedCmsPayload
{
    public function __construct(
        private CmsLocaleResolver $localeResolver
    ) {}

    public function normalizeLocale(?string $locale, ?string $fallback = null): string
    {
        return $this->localeResolver->normalizeLocale($locale)
            ?? $this->localeResolver->normalizeLocale($fallback)
            ?? CmsLocaleResolver::PRIMARY_LOCALE;
    }

    public function normalizeLocaleOrNull(?string $locale): ?string
    {
        return $this->localeResolver->normalizeLocale($locale);
    }

    /**
     * @return array{
     *   content: mixed,
     *   requested_locale: string|null,
     *   resolved_locale: string,
     *   available_locales: array<int, string>,
     *   localized: bool
     * }
     */
    public function resolve(mixed $payload, ?string $requestedLocale, ?string $siteLocale = null): array
    {
        $resolved = $this->localeResolver->resolvePayload($payload, $requestedLocale, $siteLocale);

        return [
            'content' => $resolved['content'],
            'requested_locale' => $resolved['requested_locale'],
            'resolved_locale' => $resolved['resolved_locale'],
            'available_locales' => $resolved['available_locales'],
            'localized' => $resolved['localized'],
        ];
    }

    /**
     * Merge locale-specific content into payload.
     * Always stores localized payload in ['locales' => [...]] format.
     *
     * @param  array<int, string>  $knownLocales
     */
    public function mergeForLocale(
        mixed $existingPayload,
        string $locale,
        mixed $content,
        ?string $siteLocale = null,
        array $knownLocales = []
    ): array {
        $normalizedLocale = $this->normalizeLocale($locale, $siteLocale);
        $normalizedSiteLocale = $this->normalizeLocale($siteLocale, CmsLocaleResolver::PRIMARY_LOCALE);
        $map = $this->extractLocaleMap($existingPayload) ?? [];

        if ($map === [] && $existingPayload !== null && $existingPayload !== []) {
            $map[$normalizedSiteLocale] = $existingPayload;
        }

        $map[$normalizedLocale] = $content;

        foreach ($knownLocales as $knownLocale) {
            $normalized = $this->localeResolver->normalizeLocale($knownLocale);
            if ($normalized !== null && ! array_key_exists($normalized, $map)) {
                $map[$normalized] = $map[$normalizedSiteLocale] ?? $content;
            }
        }

        return [
            'locales' => $this->sortLocaleMap($map, $normalizedSiteLocale),
        ];
    }

    /**
     * @param  array<int, string>  $configuredLocales
     * @return array<int, string>
     */
    public function mergeLocaleList(mixed $payload, array $configuredLocales = [], ?string $siteLocale = null): array
    {
        $map = $this->extractLocaleMap($payload) ?? [];
        $fromPayload = array_keys($map);

        return $this->localeResolver->mergeAvailableLocales(
            [$fromPayload, $configuredLocales],
            $this->normalizeLocale($siteLocale, CmsLocaleResolver::PRIMARY_LOCALE)
        );
    }

    /**
     * @return array<string, mixed>|null
     */
    public function extractLocaleMap(mixed $payload): ?array
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        if (
            array_key_exists('locales', $payload)
            && is_array($payload['locales'])
            && $this->isLocaleMap($payload['locales'])
        ) {
            return $this->normalizeLocaleMap($payload['locales']);
        }

        if ($this->isLocaleMap($payload)) {
            return $this->normalizeLocaleMap($payload);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $map
     * @return array<string, mixed>
     */
    private function sortLocaleMap(array $map, string $siteLocale): array
    {
        $normalized = $this->normalizeLocaleMap($map);

        // Keep configured site locale first for readability in DB payloads.
        if (array_key_exists($siteLocale, $normalized)) {
            $siteContent = $normalized[$siteLocale];
            unset($normalized[$siteLocale]);
            $normalized = [$siteLocale => $siteContent] + $normalized;
        }

        return $normalized;
    }

    /**
     * @param  array<mixed, mixed>  $candidate
     */
    private function isLocaleMap(array $candidate): bool
    {
        if ($candidate === []) {
            return false;
        }

        foreach ($candidate as $key => $value) {
            if (! is_string($key) || $this->localeResolver->normalizeLocale($key) === null) {
                return false;
            }

            unset($value);
        }

        return true;
    }

    /**
     * @param  array<mixed, mixed>  $map
     * @return array<string, mixed>
     */
    private function normalizeLocaleMap(array $map): array
    {
        $normalized = [];
        foreach ($map as $locale => $value) {
            if (! is_string($locale)) {
                continue;
            }

            $normalizedLocale = $this->localeResolver->normalizeLocale($locale);
            if ($normalizedLocale === null) {
                continue;
            }

            $normalized[$normalizedLocale] = $value;
        }

        return $normalized;
    }
}
