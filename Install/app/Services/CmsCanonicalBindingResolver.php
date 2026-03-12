<?php

namespace App\Services;

use Illuminate\Support\Str;

class CmsCanonicalBindingResolver
{
    /**
     * @var array<int, string>
     */
    private const SUPPORTED_NAMESPACES = [
        'project',
        'site',
        'page',
        'route',
        'menu',
        'global',
        'customer',
        'ecommerce',
        'booking',
        'content',
        'system',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function inspect(array $payload, string $expression, mixed $fallback = null): array
    {
        $parsed = $this->parseExpression($expression);

        if (! $parsed['ok']) {
            return [
                'ok' => false,
                'parsed' => false,
                'resolved' => false,
                'deferred' => false,
                'fallback_used' => true,
                'raw' => $expression,
                'expression' => null,
                'path' => null,
                'namespace' => null,
                'source_path' => null,
                'value' => $fallback,
                'error' => $parsed['error'],
            ];
        }

        /** @var string $canonicalPath */
        $canonicalPath = $parsed['path'];
        $namespace = (string) Str::before($canonicalPath, '.');
        if (! in_array($namespace, self::SUPPORTED_NAMESPACES, true)) {
            return [
                'ok' => false,
                'parsed' => true,
                'resolved' => false,
                'deferred' => false,
                'fallback_used' => true,
                'raw' => $expression,
                'expression' => '{{'.$canonicalPath.'}}',
                'path' => $canonicalPath,
                'namespace' => $namespace,
                'source_path' => null,
                'value' => $fallback,
                'error' => 'unsupported_namespace',
            ];
        }

        $mapped = $this->mapCanonicalPathToPayloadPath($canonicalPath);
        if (($mapped['deferred'] ?? false) === true) {
            return [
                'ok' => true,
                'parsed' => true,
                'resolved' => false,
                'deferred' => true,
                'fallback_used' => true,
                'raw' => $expression,
                'expression' => '{{'.$canonicalPath.'}}',
                'path' => $canonicalPath,
                'namespace' => $namespace,
                'source_path' => null,
                'value' => $fallback,
                'error' => null,
            ];
        }

        $sourcePath = is_string($mapped['source_path'] ?? null) ? (string) $mapped['source_path'] : '';
        if ($sourcePath === '') {
            return [
                'ok' => true,
                'parsed' => true,
                'resolved' => false,
                'deferred' => false,
                'fallback_used' => true,
                'raw' => $expression,
                'expression' => '{{'.$canonicalPath.'}}',
                'path' => $canonicalPath,
                'namespace' => $namespace,
                'source_path' => null,
                'value' => $fallback,
                'error' => 'unmapped_path',
            ];
        }

        $exists = false;
        $value = $this->getValueByPath($payload, $sourcePath, $exists);

        return [
            'ok' => true,
            'parsed' => true,
            'resolved' => $exists,
            'deferred' => false,
            'fallback_used' => ! $exists,
            'raw' => $expression,
            'expression' => '{{'.$canonicalPath.'}}',
            'path' => $canonicalPath,
            'namespace' => $namespace,
            'source_path' => $sourcePath,
            'value' => $exists ? $value : $fallback,
            'error' => $exists ? null : 'unresolved_path',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function resolve(array $payload, string $expression, mixed $fallback = null): mixed
    {
        return $this->inspect($payload, $expression, $fallback)['value'] ?? $fallback;
    }

    public function isBindingExpression(string $value): bool
    {
        return preg_match('/^\s*\{\{\s*[^{}]+\s*\}\}\s*$/', $value) === 1;
    }

    /**
     * @return array{ok:bool,path?:string,error?:string}
     */
    public function parseExpression(string $expression): array
    {
        $candidate = trim($expression);
        if ($candidate === '') {
            return ['ok' => false, 'error' => 'empty_expression'];
        }

        if ($this->isBindingExpression($candidate)) {
            $candidate = preg_replace('/^\s*\{\{\s*|\s*\}\}\s*$/', '', $candidate) ?? '';
            $candidate = trim($candidate);
        }

        if ($candidate === '') {
            return ['ok' => false, 'error' => 'empty_expression'];
        }

        if (preg_match('/\s/', $candidate) === 1) {
            return ['ok' => false, 'error' => 'invalid_syntax'];
        }

        if (preg_match('/[^A-Za-z0-9_.\[\]-]/', $candidate) === 1) {
            return ['ok' => false, 'error' => 'invalid_syntax'];
        }

        $normalized = $this->normalizePathAliases($candidate);
        if ($normalized === null || $normalized === '') {
            return ['ok' => false, 'error' => 'invalid_syntax'];
        }

        return ['ok' => true, 'path' => $normalized];
    }

    public function normalizeExpression(string $expression): ?string
    {
        $parsed = $this->parseExpression($expression);

        return ($parsed['ok'] ?? false) ? '{{'.$parsed['path'].'}}' : null;
    }

    private function normalizePathAliases(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        $path = preg_replace('/\.{2,}/', '.', $path) ?? $path;
        $path = trim($path, '.');
        if ($path === '') {
            return null;
        }

        $directAliases = [
            'project_id' => 'project.id',
            'site_id' => 'site.id',
            'slug' => 'route.slug',
            'requested_slug' => 'route.requested_slug',
            'locale' => 'route.locale',
            'resolved_domain' => 'route.domain',
        ];
        if (isset($directAliases[$path])) {
            return $directAliases[$path];
        }

        if (Str::startsWith($path, 'typography.')) {
            $path = 'site.'.$path;
        }

        if ($path === 'typography') {
            $path = 'site.typography';
        }

        if (Str::startsWith($path, 'meta.endpoints.')) {
            $endpointKey = Str::after($path, 'meta.endpoints.');
            $mapped = $this->normalizeEndpointAliasPath($endpointKey);

            return $mapped ?? 'system.endpoints.'.$endpointKey;
        }

        if (Str::startsWith($path, 'menus.')) {
            $path = 'menu.'.Str::after($path, 'menus.');
        }

        if (Str::startsWith($path, 'global_settings.')) {
            $path = 'global.'.Str::after($path, 'global_settings.');
        }

        if (Str::startsWith($path, 'revision.content_json.sections')) {
            $path = 'page.sections'.Str::after($path, 'revision.content_json.sections');
        }

        if ($path === 'revision.content_json.sections') {
            $path = 'page.sections';
        }

        if ($path === 'global.logo_asset_url') {
            return 'global.logo.url';
        }

        if (Str::startsWith($path, 'global.contact_json.')) {
            return 'global.contact.'.Str::after($path, 'global.contact_json.');
        }

        if ($path === 'global.contact_json') {
            return 'global.contact';
        }

        if ($path === 'global.social_links_json') {
            return 'global.social.links';
        }

        if ($path === 'global.analytics_ids_json') {
            return 'global.analytics';
        }

        if ($path === 'page.seo_title') {
            return 'page.seo.title';
        }

        if ($path === 'page.seo_description') {
            return 'page.seo.description';
        }

        if ($path === 'page.sections') {
            return $path;
        }

        if (preg_match('/^menu\.([A-Za-z0-9_-]+)\.items_json(\..+)?$/', $path, $matches) === 1) {
            $suffix = isset($matches[2]) ? $matches[2] : '';

            return 'menu.'.$matches[1].'.items'.$suffix;
        }

        if (preg_match('/^menu\.([A-Za-z0-9_-]+)$/', $path, $matches) === 1) {
            return 'menu.'.$matches[1];
        }

        if (Str::startsWith($path, 'site.typography.')) {
            return $path;
        }

        if (Str::startsWith($path, 'site.theme_settings.')) {
            return $path;
        }

        if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*(?:[.\[][A-Za-z0-9_\]-]+)*$/', $path) !== 1) {
            return null;
        }

        return $path;
    }

    private function normalizeEndpointAliasPath(string $endpointKey): ?string
    {
        $endpointKey = trim($endpointKey);
        if ($endpointKey === '') {
            return null;
        }

        $ecommerceAliases = [
            'ecommerce_products' => 'ecommerce.endpoints.products',
            'ecommerce_product' => 'ecommerce.endpoints.product',
            'ecommerce_create_cart' => 'ecommerce.endpoints.create_cart',
            'ecommerce_cart' => 'ecommerce.endpoints.cart',
            'ecommerce_add_cart_item' => 'ecommerce.endpoints.add_cart_item',
            'ecommerce_update_cart_item' => 'ecommerce.endpoints.update_cart_item',
            'ecommerce_remove_cart_item' => 'ecommerce.endpoints.remove_cart_item',
            'ecommerce_shipping_options' => 'ecommerce.endpoints.shipping_options',
            'ecommerce_shipping_update' => 'ecommerce.endpoints.shipping_update',
            'ecommerce_shipment_tracking' => 'ecommerce.endpoints.shipment_tracking',
            'ecommerce_checkout' => 'ecommerce.endpoints.checkout',
            'ecommerce_payment_start' => 'ecommerce.endpoints.payment_start',
        ];
        if (isset($ecommerceAliases[$endpointKey])) {
            return $ecommerceAliases[$endpointKey];
        }

        $bookingAliases = [
            'booking_services' => 'booking.endpoints.services',
            'booking_slots' => 'booking.endpoints.slots',
            'booking_create' => 'booking.endpoints.create',
        ];
        if (isset($bookingAliases[$endpointKey])) {
            return $bookingAliases[$endpointKey];
        }

        return null;
    }

    /**
     * @return array{source_path?: string, deferred?: bool}
     */
    private function mapCanonicalPathToPayloadPath(string $canonicalPath): array
    {
        if ($canonicalPath === 'project.id') {
            return ['source_path' => 'project_id'];
        }

        if (Str::startsWith($canonicalPath, 'project.')) {
            return ['source_path' => 'project.'.Str::after($canonicalPath, 'project.')];
        }

        if ($canonicalPath === 'site.typography') {
            return ['source_path' => 'typography'];
        }

        if (Str::startsWith($canonicalPath, 'site.typography.')) {
            return ['source_path' => 'typography.'.Str::after($canonicalPath, 'site.typography.')];
        }

        if (Str::startsWith($canonicalPath, 'site.')) {
            return ['source_path' => 'site.'.Str::after($canonicalPath, 'site.')];
        }

        if ($canonicalPath === 'global.logo.url') {
            return ['source_path' => 'global_settings.logo_asset_url'];
        }
        if (Str::startsWith($canonicalPath, 'global.contact.')) {
            return ['source_path' => 'global_settings.contact_json.'.Str::after($canonicalPath, 'global.contact.')];
        }
        if ($canonicalPath === 'global.contact') {
            return ['source_path' => 'global_settings.contact_json'];
        }
        if ($canonicalPath === 'global.social.links') {
            return ['source_path' => 'global_settings.social_links_json'];
        }
        if ($canonicalPath === 'global.analytics') {
            return ['source_path' => 'global_settings.analytics_ids_json'];
        }
        if (Str::startsWith($canonicalPath, 'global.')) {
            return ['source_path' => 'global_settings.'.Str::after($canonicalPath, 'global.')];
        }

        if (preg_match('/^menu\.([A-Za-z0-9_-]+)(?:\.(.+))?$/', $canonicalPath, $matches) === 1) {
            $menuKey = $matches[1];
            $rest = $matches[2] ?? '';

            if ($rest === '' || $rest === null) {
                return ['source_path' => 'menus.'.$menuKey];
            }

            if ($rest === 'items') {
                return ['source_path' => 'menus.'.$menuKey.'.items_json'];
            }

            if (Str::startsWith($rest, 'items.')) {
                return ['source_path' => 'menus.'.$menuKey.'.items_json.'.Str::after($rest, 'items.')];
            }

            if (Str::startsWith($rest, 'items[')) {
                return ['source_path' => 'menus.'.$menuKey.'.items_json'.Str::after($rest, 'items')];
            }

            return ['source_path' => 'menus.'.$menuKey.'.'.$rest];
        }

        if ($canonicalPath === 'page.seo.title') {
            return ['source_path' => 'page.seo_title'];
        }
        if ($canonicalPath === 'page.seo.description') {
            return ['source_path' => 'page.seo_description'];
        }
        if ($canonicalPath === 'page.sections') {
            return ['source_path' => 'revision.content_json.sections'];
        }
        if (Str::startsWith($canonicalPath, 'page.sections.')) {
            return ['source_path' => 'revision.content_json.sections.'.Str::after($canonicalPath, 'page.sections.')];
        }
        if (Str::startsWith($canonicalPath, 'page.sections[')) {
            return ['source_path' => 'revision.content_json.sections'.Str::after($canonicalPath, 'page.sections')];
        }
        if (Str::startsWith($canonicalPath, 'page.')) {
            return ['source_path' => 'page.'.Str::after($canonicalPath, 'page.')];
        }

        if (Str::startsWith($canonicalPath, 'route.')) {
            $routePath = Str::after($canonicalPath, 'route.');

            if (Str::startsWith($routePath, 'params.')) {
                $paramKey = Str::after($routePath, 'params.');

                return match ($paramKey) {
                    'slug' => ['source_path' => 'route.params.slug'],
                    'requested_slug' => ['source_path' => 'route.params.requested_slug'],
                    'locale' => ['source_path' => 'route.params.locale'],
                    'domain' => ['source_path' => 'route.params.domain'],
                    default => ['source_path' => 'route.params.'.$paramKey],
                };
            }

            return match ($routePath) {
                'slug' => ['source_path' => 'slug'],
                'requested_slug' => ['source_path' => 'requested_slug'],
                'locale' => ['source_path' => 'locale'],
                'domain' => ['source_path' => 'resolved_domain'],
                default => ['source_path' => ''],
            };
        }

        if (Str::startsWith($canonicalPath, 'system.endpoints.')) {
            return ['source_path' => 'meta.endpoints.'.Str::after($canonicalPath, 'system.endpoints.')];
        }

        if (Str::startsWith($canonicalPath, 'ecommerce.endpoints.')) {
            $endpoint = Str::after($canonicalPath, 'ecommerce.endpoints.');
            $legacyKey = [
                'products' => 'ecommerce_products',
                'product' => 'ecommerce_product',
                'create_cart' => 'ecommerce_create_cart',
                'cart' => 'ecommerce_cart',
                'add_cart_item' => 'ecommerce_add_cart_item',
                'update_cart_item' => 'ecommerce_update_cart_item',
                'remove_cart_item' => 'ecommerce_remove_cart_item',
                'shipping_options' => 'ecommerce_shipping_options',
                'shipping_update' => 'ecommerce_shipping_update',
                'shipment_tracking' => 'ecommerce_shipment_tracking',
                'checkout' => 'ecommerce_checkout',
                'payment_start' => 'ecommerce_payment_start',
            ][$endpoint] ?? null;

            return $legacyKey !== null
                ? ['source_path' => 'meta.endpoints.'.$legacyKey]
                : ['source_path' => ''];
        }

        if (Str::startsWith($canonicalPath, 'booking.endpoints.')) {
            $endpoint = Str::after($canonicalPath, 'booking.endpoints.');
            $legacyKey = [
                'services' => 'booking_services',
                'slots' => 'booking_slots',
                'create' => 'booking_create',
            ][$endpoint] ?? null;

            return $legacyKey !== null
                ? ['source_path' => 'meta.endpoints.'.$legacyKey]
                : ['source_path' => ''];
        }

        if ($canonicalPath === 'ecommerce.products'
            || $canonicalPath === 'ecommerce.product'
            || $canonicalPath === 'ecommerce.cart'
            || Str::startsWith($canonicalPath, 'ecommerce.cart.')
            || Str::startsWith($canonicalPath, 'booking.services')
            || Str::startsWith($canonicalPath, 'booking.slots')
            || Str::startsWith($canonicalPath, 'booking.create')
            || Str::startsWith($canonicalPath, 'customer.')
            || Str::startsWith($canonicalPath, 'content.')
        ) {
            return ['deferred' => true];
        }

        return ['source_path' => ''];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function getValueByPath(array $payload, string $path, bool &$exists): mixed
    {
        $exists = false;
        $tokens = $this->tokenizePath($path);
        if ($tokens === []) {
            return null;
        }

        $value = $payload;
        foreach ($tokens as $token) {
            if (is_array($value)) {
                if (! array_key_exists($token, $value)) {
                    return null;
                }
                $value = $value[$token];
                continue;
            }

            if (is_object($value)) {
                if (! isset($value->{$token}) && ! property_exists($value, (string) $token)) {
                    return null;
                }
                $value = $value->{$token};
                continue;
            }

            return null;
        }

        $exists = true;

        return $value;
    }

    /**
     * @return array<int, int|string>
     */
    private function tokenizePath(string $path): array
    {
        $normalized = preg_replace('/\[(\d+)\]/', '.$1', $path) ?? $path;
        $normalized = trim((string) $normalized, '.');
        if ($normalized === '') {
            return [];
        }

        $segments = array_values(array_filter(explode('.', $normalized), static fn ($segment): bool => $segment !== ''));

        return array_map(static function (string $segment): int|string {
            return ctype_digit($segment) ? (int) $segment : $segment;
        }, $segments);
    }
}
