<?php

namespace App\Services;

class CmsLocaleResolver
{
    public const PRIMARY_LOCALE = 'ka';

    public const SECONDARY_FALLBACK_LOCALE = 'en';

    /**
     * Normalize a locale identifier (e.g. ka, en, en-us) or return null.
     */
    public function normalizeLocale(?string $locale): ?string
    {
        $normalized = trim(strtolower((string) $locale));
        if ($normalized === '') {
            return null;
        }

        if (! preg_match('/^[a-z]{2}(?:-[a-z]{2})?$/', $normalized)) {
            return null;
        }

        return $normalized;
    }

    /**
     * Resolve content payload for requested locale with ka->en fallback policy.
     *
     * @return array{
     *   content: mixed,
     *   requested_locale: string|null,
     *   resolved_locale: string,
     *   fallback_locale: string,
     *   secondary_fallback_locale: string,
     *   available_locales: array<int, string>,
     *   localized: bool
     * }
     */
    public function resolvePayload(mixed $payload, ?string $requestedLocale, ?string $siteLocale = null): array
    {
        $requested = $this->normalizeLocale($requestedLocale);
        $site = $this->normalizeLocale($siteLocale) ?? self::PRIMARY_LOCALE;

        $resolvedLocale = $this->resolveLocale($requested, $site);
        $localeMap = $this->extractLocaleMap($payload);

        if ($localeMap === null) {
            return [
                'content' => $payload,
                'requested_locale' => $requested,
                'resolved_locale' => $resolvedLocale,
                'fallback_locale' => self::PRIMARY_LOCALE,
                'secondary_fallback_locale' => self::SECONDARY_FALLBACK_LOCALE,
                'available_locales' => [$resolvedLocale],
                'localized' => false,
            ];
        }

        $normalizedMap = [];
        foreach ($localeMap as $locale => $value) {
            $normalized = $this->normalizeLocale(is_string($locale) ? $locale : null);
            if ($normalized !== null) {
                $normalizedMap[$normalized] = $value;
            }
        }

        if ($normalizedMap === []) {
            return [
                'content' => $payload,
                'requested_locale' => $requested,
                'resolved_locale' => $resolvedLocale,
                'fallback_locale' => self::PRIMARY_LOCALE,
                'secondary_fallback_locale' => self::SECONDARY_FALLBACK_LOCALE,
                'available_locales' => [$resolvedLocale],
                'localized' => false,
            ];
        }

        $candidates = $this->buildLocaleCandidates($requested, $site);
        $availableLocales = array_values(array_keys($normalizedMap));
        $matchedLocale = null;

        foreach ($candidates as $candidate) {
            if (array_key_exists($candidate, $normalizedMap)) {
                $matchedLocale = $candidate;
                break;
            }
        }

        if ($matchedLocale === null) {
            $matchedLocale = $availableLocales[0];
        }

        return [
            'content' => $normalizedMap[$matchedLocale],
            'requested_locale' => $requested,
            'resolved_locale' => $matchedLocale,
            'fallback_locale' => self::PRIMARY_LOCALE,
            'secondary_fallback_locale' => self::SECONDARY_FALLBACK_LOCALE,
            'available_locales' => $availableLocales,
            'localized' => true,
        ];
    }

    /**
     * Resolve locale string for response metadata.
     */
    public function resolveLocale(?string $requestedLocale, ?string $siteLocale = null): string
    {
        $requested = $this->normalizeLocale($requestedLocale);
        $site = $this->normalizeLocale($siteLocale) ?? self::PRIMARY_LOCALE;

        if ($requested !== null) {
            if (
                $requested === $site
                || $requested === self::PRIMARY_LOCALE
                || $requested === self::SECONDARY_FALLBACK_LOCALE
            ) {
                return $requested;
            }

            if (str_starts_with($requested, self::PRIMARY_LOCALE.'-')) {
                return self::PRIMARY_LOCALE;
            }

            if (str_starts_with($requested, self::SECONDARY_FALLBACK_LOCALE.'-')) {
                return self::SECONDARY_FALLBACK_LOCALE;
            }
        }

        return $site;
    }

    /**
     * Pick final locale from multiple localized payload results.
     *
     * @param  array<int, string|null>  $resolvedLocales
     */
    public function pickResolvedLocale(array $resolvedLocales, ?string $requestedLocale, ?string $siteLocale = null): string
    {
        $candidates = $this->buildLocaleCandidates(
            $this->normalizeLocale($requestedLocale),
            $this->normalizeLocale($siteLocale) ?? self::PRIMARY_LOCALE
        );

        $normalizedResolved = array_values(array_filter(
            array_map(fn ($locale): ?string => $this->normalizeLocale(is_string($locale) ? $locale : null), $resolvedLocales)
        ));

        foreach ($candidates as $candidate) {
            if (in_array($candidate, $normalizedResolved, true)) {
                return $candidate;
            }
        }

        return $normalizedResolved[0] ?? $this->resolveLocale($requestedLocale, $siteLocale);
    }

    /**
     * @param  array<int, array<int, string>>  $localeLists
     * @return array<int, string>
     */
    public function mergeAvailableLocales(array $localeLists, ?string $siteLocale = null): array
    {
        $flat = [];
        foreach ($localeLists as $list) {
            foreach ($list as $locale) {
                $normalized = $this->normalizeLocale($locale);
                if ($normalized !== null) {
                    $flat[] = $normalized;
                }
            }
        }

        $site = $this->normalizeLocale($siteLocale) ?? self::PRIMARY_LOCALE;
        $flat[] = self::PRIMARY_LOCALE;
        $flat[] = self::SECONDARY_FALLBACK_LOCALE;
        $flat[] = $site;

        return array_values(array_unique($flat));
    }

    /**
     * @return array<int, string>
     */
    private function buildLocaleCandidates(?string $requestedLocale, string $siteLocale): array
    {
        $candidates = [];

        foreach ([$requestedLocale, self::PRIMARY_LOCALE, self::SECONDARY_FALLBACK_LOCALE, $siteLocale] as $locale) {
            $normalized = $this->normalizeLocale($locale);
            if ($normalized !== null) {
                $candidates[] = $normalized;
            }
        }

        if ($candidates === []) {
            $candidates[] = self::PRIMARY_LOCALE;
        }

        return array_values(array_unique($candidates));
    }

    /**
     * Supported formats:
     * 1) ['locales' => ['ka' => ..., 'en' => ...]]
     * 2) ['ka' => ..., 'en' => ...]
     */
    private function extractLocaleMap(mixed $payload): ?array
    {
        if (! is_array($payload) || $payload === []) {
            return null;
        }

        if (array_key_exists('locales', $payload) && is_array($payload['locales']) && $this->isLocaleMap($payload['locales'])) {
            return $payload['locales'];
        }

        if ($this->isLocaleMap($payload)) {
            return $payload;
        }

        return null;
    }

    private function isLocaleMap(array $candidate): bool
    {
        if ($candidate === []) {
            return false;
        }

        foreach ($candidate as $key => $value) {
            if (! is_string($key) || $this->normalizeLocale($key) === null) {
                return false;
            }
        }

        return true;
    }
}
