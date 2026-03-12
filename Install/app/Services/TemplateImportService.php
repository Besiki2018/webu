<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\SectionLibrary;
use App\Models\Template;
use DOMDocument;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

class TemplateImportService
{
    /**
     * @param  array<int, int>|null  $planIds
     * @return array<string, mixed>
     */
    public function import(string $sourcePath, string $themeSlug = 'custom-template', string $themeName = 'Custom Template', ?array $planIds = null): array
    {
        $resolvedSource = realpath($sourcePath) ?: $sourcePath;

        if (! is_dir($resolvedSource)) {
            throw new \RuntimeException("Template source path not found: {$sourcePath}");
        }

        $htmlFiles = collect(File::files($resolvedSource))
            ->filter(fn (\SplFileInfo $file): bool => strtolower($file->getExtension()) === 'html')
            ->map(fn (\SplFileInfo $file): string => $file->getFilename())
            ->values()
            ->all();

        if ($htmlFiles === []) {
            throw new \RuntimeException('No HTML files were found in import source folder.');
        }

        $templateRoot = base_path('templates/'.$themeSlug);
        $sourceDir = $templateRoot.'/source';
        $runtimeDir = $templateRoot.'/runtime';

        File::deleteDirectory($templateRoot);
        File::ensureDirectoryExists($sourceDir);
        File::ensureDirectoryExists($runtimeDir);

        File::copyDirectory($resolvedSource, $sourceDir);
        File::copyDirectory($resolvedSource, $runtimeDir);

        $publicThemeRelative = 'themes/'.$themeSlug;
        $publicThemeDir = public_path($publicThemeRelative);

        File::deleteDirectory($publicThemeDir);
        File::ensureDirectoryExists($publicThemeDir);
        File::copyDirectory($runtimeDir, $publicThemeDir);

        $this->sanitizeImportedHtml($runtimeDir, $themeSlug, $themeName);
        $this->sanitizeImportedHtml($publicThemeDir, $themeSlug, $themeName);

        $pageMap = $this->resolvePageMap($runtimeDir);
        $this->prepareRuntimeBindingFiles($runtimeDir, $pageMap, $themeSlug);
        $this->prepareRuntimeBindingFiles($publicThemeDir, $pageMap, $themeSlug);

        $components = $this->extractComponents($runtimeDir, $pageMap);
        $sections = $this->upsertSections($components);

        $manifest = $this->buildManifest(
            themeSlug: $themeSlug,
            themeName: $themeName,
            pageMap: $pageMap,
            sections: $sections,
            components: $components,
        );
        $mapping = $this->buildMapping($pageMap);

        File::put(
            $templateRoot.'/template.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );
        File::put(
            $templateRoot.'/mapping.json',
            json_encode($mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $zipPath = $this->createRuntimeZip($runtimeDir, $themeSlug, $manifest);

        $template = Template::query()->updateOrCreate(
            ['slug' => $themeSlug],
            [
                'name' => $themeName,
                'description' => 'Custom ecommerce template package with CMS and storefront bindings.',
                'category' => 'ecommerce',
                'version' => '1.0.0',
                'is_system' => false,
                'zip_path' => $zipPath,
                'thumbnail' => null,
                'metadata' => [
                    'vertical' => 'ecommerce',
                    'framework' => 'html',
                    'module_flags' => [
                        'cms_pages' => true,
                        'cms_menus' => true,
                        'cms_settings' => true,
                        'media_library' => true,
                        'domains' => true,
                        'database' => true,
                        'ecommerce' => true,
                        'payments' => true,
                        'shipping' => true,
                        'ecommerce_inventory' => true,
                        'ecommerce_accounting' => true,
                        'ecommerce_rs' => true,
                        'booking' => false,
                    ],
                    'typography_tokens' => [
                        'heading' => 'tbc_contractica',
                        'body' => 'tbc_contractica',
                        'button' => 'tbc_contractica',
                    ],
                    'default_pages' => $manifest['pages'],
                    'default_sections' => $manifest['default_sections'],
                    'live_demo' => [
                        'path' => $publicThemeRelative.'/'.($pageMap['home'] ?? 'index.html'),
                    ],
                    'branding' => [
                        'provider' => 'webu',
                        'label' => $themeName,
                    ],
                    'section_inventory' => [
                        'summary' => [
                            'total' => count($sections),
                            'mapped' => count($sections),
                            'unmapped' => 0,
                        ],
                        'items' => array_values(array_map(
                            static fn (array $section): array => [
                                'key' => $section['key'],
                                'mapped_key' => $section['key'],
                                'category' => $section['category'],
                            ],
                            $sections
                        )),
                        'unmapped_keys' => [],
                    ],
                ],
            ]
        );

        $resolvedPlanIds = $planIds;
        if ($resolvedPlanIds === null || $resolvedPlanIds === []) {
            $resolvedPlanIds = Plan::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        }

        if ($resolvedPlanIds !== []) {
            $template->plans()->sync($resolvedPlanIds);
        }

        return [
            'template_id' => $template->id,
            'slug' => $template->slug,
            'name' => $template->name,
            'source_path' => $resolvedSource,
            'template_root' => $templateRoot,
            'public_theme_path' => $publicThemeDir,
            'zip_path' => $zipPath,
            'page_count' => count($manifest['pages']),
            'section_count' => count($sections),
            'pages' => array_keys($pageMap),
            'sections' => array_map(static fn (array $section): string => $section['key'], $sections),
        ];
    }

    private function sanitizeImportedHtml(string $directory, string $themeSlug, string $themeName): void
    {
        $files = File::allFiles($directory);

        foreach ($files as $file) {
            if (strtolower($file->getExtension()) !== 'html') {
                continue;
            }

            $path = $file->getPathname();
            $html = File::get($path);

            // Remove vendor markers from comments and visible fragments.
            $html = preg_replace('/<!--(?:(?!-->).)*(shop-design|pixio|themeforest)(?:(?!-->).)*-->/is', '', $html) ?? $html;
            $html = str_ireplace(['shop-design', 'themeforest', 'pixio'], [$themeName, 'Custom', 'Custom'], $html);

            $html = preg_replace(
                '/<title>.*?<\/title>/is',
                '<title>'.e($themeName).'</title>',
                $html,
                1
            ) ?? $html;

            if (stripos($html, 'data-webu-theme=') === false) {
                $html = preg_replace(
                    '/<body(.*?)>/is',
                    '<body$1 data-webu-theme="'.e($themeSlug).'">',
                    $html,
                    1
                ) ?? $html;
            }

            $html = str_replace(
                ['href="/assets/', 'src="/assets/', "url('/assets/", 'url("/assets/'],
                ['href="assets/', 'src="assets/', "url('assets/", 'url("assets/'],
                $html
            );

            // Normalize legacy template asset roots (e.g. /templates/<legacy>/assets/...) to local theme-relative assets.
            $html = preg_replace(
                [
                    '/href="\/templates\/[^"\/]+\/assets\//i',
                    '/src="\/templates\/[^"\/]+\/assets\//i',
                    '/url\(\s*([\'"])\/templates\/[^\'"\/]+\/assets\//i',
                ],
                [
                    'href="assets/',
                    'src="assets/',
                    'url($1assets/',
                ],
                $html
            ) ?? $html;

            // Catch any remaining legacy asset root references in data-* attributes and inline payloads.
            $html = preg_replace(
                '/\/templates\/[^\/"\'\)\s>]+\/assets\//i',
                'assets/',
                $html
            ) ?? $html;

            File::put($path, $html);
        }
    }

    /**
     * @param  array<string, string>  $pageMap
     */
    private function prepareRuntimeBindingFiles(string $directory, array $pageMap, string $themeSlug): void
    {
        $scriptRelativePath = 'assets/js/webu-theme-runtime.js';
        File::ensureDirectoryExists($directory.'/assets/js');
        File::put($directory.'/'.$scriptRelativePath, $this->buildThemeRuntimeScript($themeSlug));

        foreach ($pageMap as $pageKey => $fileName) {
            $path = $directory.'/'.$fileName;
            if (! is_file($path)) {
                continue;
            }

            $html = (string) File::get($path);
            $html = $this->annotateHtmlForBindings($html, $pageKey);
            $html = $this->injectThemeRuntimeScriptTag($html, $scriptRelativePath);
            File::put($path, $html);
        }
    }

    private function annotateHtmlForBindings(string $html, string $pageKey): string
    {
        $html = preg_replace('/<header\b(?![^>]*data-webu-section)([^>]*)>/i', '<header$1 data-webu-section="webu_header_01">', $html, 1) ?? $html;
        $html = preg_replace('/<header\b(?![^>]*data-webu-menu)([^>]*)>/i', '<header$1 data-webu-menu="header">', $html, 1) ?? $html;
        $html = preg_replace('/<footer\b(?![^>]*data-webu-section)([^>]*)>/i', '<footer$1 data-webu-section="webu_footer_01">', $html, 1) ?? $html;
        $html = preg_replace('/<footer\b(?![^>]*data-webu-menu)([^>]*)>/i', '<footer$1 data-webu-menu="footer">', $html, 1) ?? $html;

        $html = preg_replace('/<a\b(?![^>]*data-webu-site-name)([^>]*class="[^"]*(logo|brand|navbar-brand)[^"]*"[^>]*)>/i', '<a$1 data-webu-site-name>', $html, 1) ?? $html;
        $html = preg_replace('/<img\b(?![^>]*data-webu-logo)([^>]*class="[^"]*(logo|brand)[^"]*"[^>]*)>/i', '<img$1 data-webu-logo>', $html, 1) ?? $html;
        $html = preg_replace('/<a\b(?![^>]*data-webu-contact)([^>]*href="mailto:[^"]*"[^>]*)>/i', '<a$1 data-webu-contact="email">', $html, 1) ?? $html;
        $html = preg_replace('/<a\b(?![^>]*data-webu-contact)([^>]*href="tel:[^"]*"[^>]*)>/i', '<a$1 data-webu-contact="phone">', $html, 1) ?? $html;

        if ($pageKey === 'home') {
            $html = preg_replace('/<(section|div)\b(?![^>]*data-webu-section)([^>]*class="[^"]*(hero|banner|slider|intro)[^"]*"[^>]*)>/i', '<$1$2 data-webu-section="webu_hero_01">', $html, 1) ?? $html;
            $html = preg_replace('/<(section|div)\b(?![^>]*data-webu-section)([^>]*class="[^"]*(cta|offer|promo|call-to-action)[^"]*"[^>]*)>/i', '<$1$2 data-webu-section="webu_cta_banner_01">', $html, 1) ?? $html;
            $html = preg_replace('/<(section|div)\b(?![^>]*data-webu-section)([^>]*class="[^"]*(newsletter|subscribe)[^"]*"[^>]*)>/i', '<$1$2 data-webu-section="webu_newsletter_01">', $html, 1) ?? $html;
        }

        if (in_array($pageKey, ['home', 'shop'], true)) {
            $html = preg_replace('/<(section|div)\b(?![^>]*data-webu-section)([^>]*class="[^"]*(shop_banner|category|categories|collection)[^"]*"[^>]*)>/i', '<$1$2 data-webu-section="webu_category_list_01">', $html, 1) ?? $html;
            $html = preg_replace('/<(section|div)\b(?![^>]*data-webby-ecommerce-products)([^>]*class="[^"]*(shop_container|product-grid|products-grid|products|product_list)[^"]*"[^>]*)>/i', '<$1$2 data-webu-section="webu_product_grid_01" data-webby-ecommerce-products>', $html, 1) ?? $html;
            $html = preg_replace('/<(section|div)\b(?![^>]*data-webby-ecommerce-products)([^>]*class="[^"]*(product_slider)[^"]*"[^>]*)>/i', '<$1$2 data-webby-ecommerce-products>', $html) ?? $html;
        }

        if ($pageKey === 'product') {
            $html = preg_replace('/<(section|div|article)\b(?![^>]*data-webu-section)([^>]*class="[^"]*(product|detail|single|card)[^"]*"[^>]*)>/i', '<$1$2 data-webu-section="webu_product_card_01">', $html, 1) ?? $html;
        }

        if ($pageKey === 'cart') {
            $html = preg_replace('/<(section|div)\b(?![^>]*data-webby-ecommerce-cart)([^>]*class="[^"]*cart[^"]*"[^>]*)>/i', '<$1$2 data-webu-section="webu_product_grid_01" data-webby-ecommerce-cart>', $html, 1) ?? $html;
        }

        if ($pageKey === 'checkout') {
            $html = preg_replace('/<(section|div|form)\b(?![^>]*data-webby-ecommerce-checkout)([^>]*class="[^"]*checkout[^"]*"[^>]*)>/i', '<$1$2 data-webby-ecommerce-checkout>', $html, 1) ?? $html;
        }

        if ($pageKey === 'login') {
            $html = preg_replace('/<(section|div|form)\b(?![^>]*data-webby-ecommerce-auth)([^>]*class="[^"]*(login_register_wrap|login_wrap|login_form)[^"]*"[^>]*)>/i', '<$1$2 data-webby-ecommerce-auth>', $html, 1) ?? $html;
        }

        if ($pageKey === 'account') {
            $html = preg_replace('/<(div|section)\b(?![^>]*data-webby-ecommerce-account-profile)([^>]*class="[^"]*(dashboard_content|account-detail|account_profile|account-profile)[^"]*"[^>]*)>/i', '<$1$2 data-webby-ecommerce-account-profile>', $html, 1) ?? $html;
            $html = preg_replace('/<(div|section)\b(?![^>]*data-webby-ecommerce-account-security)([^>]*id="account-detail"[^>]*)>/i', '<$1$2 data-webby-ecommerce-account-security>', $html, 1) ?? $html;
        }

        if ($pageKey === 'orders') {
            $html = preg_replace('/<(div|section|table)\b(?![^>]*data-webby-ecommerce-orders-list)([^>]*id="orders"[^>]*)>/i', '<$1$2 data-webby-ecommerce-orders-list>', $html, 1) ?? $html;
            $html = preg_replace('/<(div|section|table)\b(?![^>]*data-webby-ecommerce-orders-list)([^>]*class="[^"]*(orders|order_table|order-table|table-responsive)[^"]*"[^>]*)>/i', '<$1$2 data-webby-ecommerce-orders-list>', $html, 1) ?? $html;
        }

        if ($pageKey === 'order') {
            $html = preg_replace('/<(div|section|article)\b(?![^>]*data-webby-ecommerce-order-detail)([^>]*class="[^"]*(order_complete|order-detail|order_detail|order-complete)[^"]*"[^>]*)>/i', '<$1$2 data-webby-ecommerce-order-detail>', $html, 1) ?? $html;
        }

        return $this->annotateGenericSections($html, $pageKey);
    }

    private function annotateGenericSections(string $html, string $pageKey): string
    {
        $normalizedPageKey = trim(Str::lower($pageKey));
        if ($normalizedPageKey === '') {
            return $html;
        }

        $counter = 0;
        $sectionPattern = '/<section\b(?![^>]*data-webu-section)([^>]*)>/i';
        $sectionDivPattern = '/<(div|article)\b(?![^>]*data-webu-section)([^>]*class="[^"]*\bsection\b[^"]*"[^>]*)>/i';

        $annotated = preg_replace_callback($sectionPattern, function (array $matches) use (&$counter, $normalizedPageKey): string {
            $counter++;
            $sectionKey = sprintf('webu_%s_section_%02d', $normalizedPageKey, $counter);

            return '<section'.$matches[1].' data-webu-section="'.$sectionKey.'">';
        }, $html) ?? $html;

        return preg_replace_callback($sectionDivPattern, function (array $matches) use (&$counter, $normalizedPageKey): string {
            $counter++;
            $sectionKey = sprintf('webu_%s_section_%02d', $normalizedPageKey, $counter);

            return '<'.$matches[1].$matches[2].' data-webu-section="'.$sectionKey.'">';
        }, $annotated) ?? $annotated;
    }

    private function injectThemeRuntimeScriptTag(string $html, string $scriptRelativePath): string
    {
        if (stripos($html, $scriptRelativePath) !== false) {
            return $html;
        }

        $script = '<script src="'.$scriptRelativePath.'" defer></script>';
        if (stripos($html, '</body>') !== false) {
            return preg_replace('/<\/body>/i', $script.'</body>', $html, 1) ?? $html;
        }

        return $html."\n".$script."\n";
    }

    private function buildThemeRuntimeScript(string $themeSlug): string
    {
        $slugLiteral = json_encode($themeSlug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return <<<JS
(function () {
    'use strict';

    var THEME_SLUG = {$slugLiteral};
    var query = new URLSearchParams(window.location.search || '');
    var state = {
        siteId: null,
        locale: (query.get('locale') || 'ka').toLowerCase(),
        slug: normalizeSlug(query.get('slug') || deriveSlug(window.location.pathname)),
        draftMode: query.get('draft') === '1'
            || query.get('mode') === 'draft'
            || (query.has('site') && query.has('t') && query.get('draft') !== '0'),
        payload: null,
        cart: null,
        productsCache: null,
        currentProduct: null,
        cmsReadyCallbacks: []
    };

    function deriveSlug(pathname) {
        var normalized = String(pathname || '/').split('?')[0].split('#')[0];
        var parts = normalized.split('/').filter(function (part) {
            return part.length > 0;
        });
        var last = parts.length ? parts[parts.length - 1].toLowerCase() : 'index.html';
        last = last.replace(/\\.html?$/, '');

        if (last === '' || last === 'index' || /^index-\\d+$/.test(last)) {
            return 'home';
        }
        if (last.indexOf('shop-product-detail') === 0) {
            return 'product';
        }
        if (last.indexOf('shop-cart') === 0) {
            return 'cart';
        }
        if (last.indexOf('checkout') === 0) {
            return 'checkout';
        }
        if (last.indexOf('login') === 0 || last.indexOf('signup') === 0 || last.indexOf('register') === 0) {
            return 'login';
        }
        if (last.indexOf('my-account') === 0 || last.indexOf('account') === 0) {
            return 'account';
        }
        if (last.indexOf('orders') === 0) {
            return 'orders';
        }
        if (last.indexOf('order-completed') === 0 || last.indexOf('order-detail') === 0 || last === 'order') {
            return 'order';
        }
        if (last.indexOf('contact') === 0) {
            return 'contact';
        }
        if (last.indexOf('shop') === 0) {
            return 'shop';
        }

        return last.replace(/[^a-z0-9-]/g, '') || 'home';
    }

    function normalizeSlug(slug) {
        var normalized = String(slug || '').trim().toLowerCase();
        if (normalized === '' || normalized === 'index' || /^index-\\d+$/.test(normalized)) {
            return 'home';
        }
        if (normalized === 'order-completed' || normalized === 'order-detail') {
            return 'order';
        }
        if (normalized === 'my-account') {
            return 'account';
        }
        if (normalized === 'auth' || normalized === 'register' || normalized === 'signup') {
            return 'login';
        }
        return normalized;
    }

    function jsonFetch(url, options) {
        var request = Object.assign({
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json'
            }
        }, options || {});

        return fetch(url, request).then(function (response) {
            if (!response.ok) {
                return response.json().catch(function () { return null; }).then(function (payload) {
                    var error = new Error('HTTP_' + response.status + ' @ ' + url);
                    error.status = response.status;
                    error.payload = payload;
                    throw error;
                });
            }
            return response.json();
        });
    }

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function toNumber(value) {
        var number = Number.parseFloat(String(value == null ? '0' : value));
        if (!Number.isFinite(number)) {
            return 0;
        }

        return number;
    }

    function formatMoney(value, currency) {
        var amount = toNumber(value);
        var iso = String(currency || 'GEL').toUpperCase();

        try {
            return new Intl.NumberFormat(state.locale || 'ka', {
                style: 'currency',
                currency: iso,
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }).format(amount);
        } catch (_error) {
            return amount.toFixed(2) + ' ' + iso;
        }
    }

    function normalizeHostValue(host) {
        return String(host || '')
            .trim()
            .toLowerCase()
            .replace(/^https?:\\/\\//, '')
            .replace(/:\\d+$/, '')
            .replace(/\\/+$/, '');
    }

    function isLoopbackHost(host) {
        var normalized = normalizeHostValue(host);
        return normalized === 'localhost'
            || normalized === '127.0.0.1'
            || normalized === '0.0.0.0'
            || normalized === '::1'
            || normalized === '[::1]';
    }

    function resolveSiteId() {
        var explicitSite = query.get('site') || document.body.getAttribute('data-webu-site');
        if (explicitSite) {
            return Promise.resolve(explicitSite);
        }

        if (window.__WEBBY_CMS__ && window.__WEBBY_CMS__.site_id) {
            return Promise.resolve(window.__WEBBY_CMS__.site_id);
        }

        var resolveByTemplate = function () {
            return jsonFetch('/public/templates/' + encodeURIComponent(THEME_SLUG) + '/default-site')
                .then(function (payload) {
                    if (!payload.site_id) {
                        throw new Error('SITE_ID_MISSING');
                    }
                    return payload.site_id;
                });
        };

        // Local development domains (127.0.0.1/localhost) typically do not map via
        // /public/sites/resolve, so skip that call to avoid noisy 404 requests.
        if (isLoopbackHost(window.location.hostname)) {
            return resolveByTemplate();
        }

        return jsonFetch('/public/sites/resolve?domain=' + encodeURIComponent(window.location.host))
            .then(function (payload) {
                if (!payload.site_id) {
                    throw new Error('SITE_ID_MISSING');
                }
                return payload.site_id;
            })
            .catch(function () {
                return resolveByTemplate();
            });
    }

    function resolveFirstSelector(selectors) {
        for (var i = 0; i < selectors.length; i++) {
            var node = document.querySelector(selectors[i]);
            if (node) {
                return node;
            }
        }
        return null;
    }

    function setText(selectors, value) {
        if (typeof value !== 'string' || value.trim() === '') {
            return;
        }

        selectors.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (node) {
                node.textContent = value;
            });
        });
    }

    function setLogo(logoUrl) {
        if (typeof logoUrl !== 'string' || logoUrl.trim() === '') {
            return;
        }

        var selectors = ['[data-webu-logo]', 'header .logo img', '.navbar-brand img', '.logo img'];
        selectors.forEach(function (selector) {
            document.querySelectorAll(selector).forEach(function (img) {
                img.setAttribute('src', logoUrl);
                img.setAttribute('alt', img.getAttribute('alt') || 'Webu');
            });
        });
    }

    function renderMenu(items, menuType) {
        if (!Array.isArray(items) || items.length === 0) {
            return;
        }

        var selectors = menuType === 'header'
            ? ['[data-webu-menu="header"] ul', 'header nav ul', 'header ul']
            : ['[data-webu-menu="footer"] ul', 'footer nav ul', 'footer ul'];
        var container = resolveFirstSelector(selectors);
        if (!container) {
            return;
        }

        container.innerHTML = items.map(function (item) {
            var label = escapeHtml(item.label || item.title || 'Link');
            var url = escapeHtml(item.url || '/');
            return '<li><a href="' + url + '">' + label + '</a></li>';
        }).join('');
    }

    function applyContact(contact) {
        if (!contact || typeof contact !== 'object') {
            return;
        }

        if (contact.email) {
            setText(['[data-webu-contact="email"]'], String(contact.email));
            document.querySelectorAll('a[href^="mailto:"]').forEach(function (node) {
                node.setAttribute('href', 'mailto:' + contact.email);
                if (!node.getAttribute('data-webu-contact')) {
                    node.textContent = contact.email;
                }
            });
        }

        if (contact.phone) {
            setText(['[data-webu-contact="phone"]'], String(contact.phone));
            document.querySelectorAll('a[href^="tel:"]').forEach(function (node) {
                node.setAttribute('href', 'tel:' + String(contact.phone).replace(/\\s+/g, ''));
                if (!node.getAttribute('data-webu-contact')) {
                    node.textContent = contact.phone;
                }
            });
        }

        if (contact.address) {
            setText(['[data-webu-contact="address"]', '.contact-address', 'footer .address'], String(contact.address));
        }
    }

    function toBoolean(value, fallback) {
        if (typeof value === 'boolean') {
            return value;
        }

        if (typeof value === 'number') {
            return value !== 0;
        }

        if (typeof value === 'string') {
            var normalized = value.trim().toLowerCase();
            if (['1', 'true', 'yes', 'on'].indexOf(normalized) !== -1) {
                return true;
            }
            if (['0', 'false', 'no', 'off'].indexOf(normalized) !== -1) {
                return false;
            }
        }

        return !!fallback;
    }

    function applyPopupModalSettings(popupSettings) {
        var popupModal = document.querySelector('#onload-popup');
        if (!popupModal) {
            return;
        }

        var settings = popupSettings && typeof popupSettings === 'object' ? popupSettings : {};
        var enabled = toBoolean(settings.enabled, true);

        if (!enabled) {
            popupModal.setAttribute('data-webu-popup-enabled', '0');
            popupModal.classList.remove('show');
            popupModal.setAttribute('aria-hidden', 'true');
            popupModal.style.display = 'none';

            if (document.body) {
                document.body.classList.remove('modal-open');
                document.body.style.removeProperty('padding-right');
            }
            document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) {
                backdrop.remove();
            });
            return;
        }

        popupModal.setAttribute('data-webu-popup-enabled', '1');
        if (popupModal.style.display === 'none') {
            popupModal.style.display = '';
        }

        var heading = typeof settings.headline === 'string' && settings.headline.trim() !== ''
            ? settings.headline
            : (typeof settings.title === 'string' ? settings.title : '');
        var description = typeof settings.description === 'string' && settings.description.trim() !== ''
            ? settings.description
            : (typeof settings.body === 'string' ? settings.body : '');
        var buttonLabel = typeof settings.button_label === 'string' && settings.button_label.trim() !== ''
            ? settings.button_label
            : (typeof settings.cta_label === 'string' && settings.cta_label.trim() !== ''
                ? settings.cta_label
                : (typeof settings.button === 'string' ? settings.button : ''));

        if (heading.trim() !== '') {
            setText(['#onload-popup .popup-text h4'], heading);
        }
        if (description.trim() !== '') {
            setText(['#onload-popup .popup-text p'], description);
        }
        if (buttonLabel.trim() !== '') {
            setText(['#onload-popup button[type="submit"]'], buttonLabel);
        }
    }

    function normalizeMenuSource(value, fallback) {
        if (typeof value !== 'string') {
            return fallback;
        }

        var normalized = String(value).trim().toLowerCase();
        if (!/^[a-z0-9_-]{1,64}$/.test(normalized)) {
            return fallback;
        }

        return normalized;
    }

    function findSectionContainer(type) {
        var normalizedType = String(type || '').trim();
        if (!normalizedType) {
            return null;
        }

        var escapedType = normalizedType.replace(/"/g, '\\"');
        var directMatch = resolveFirstSelector(['[data-webu-section="' + escapedType + '"]']);
        if (directMatch) {
            return directMatch;
        }

        var map = {
            webu_header_01: ['[data-webu-section="webu_header_01"]', 'header'],
            webu_hero_01: ['[data-webu-section="webu_hero_01"]', '.hero', '.banner', '.slider', '.intro'],
            webu_category_list_01: ['[data-webu-section="webu_category_list_01"]', '.categories', '.category-list'],
            webu_product_grid_01: ['[data-webu-section="webu_product_grid_01"]', '[data-webby-ecommerce-products]', '.products', '.product-grid'],
            webu_product_card_01: ['[data-webu-section="webu_product_card_01"]', '.product-detail', '.single-product'],
            webu_cta_banner_01: ['[data-webu-section="webu_cta_banner_01"]', '.cta', '.promo'],
            webu_newsletter_01: ['[data-webu-section="webu_newsletter_01"]', '.newsletter', '.subscribe'],
            webu_footer_01: ['[data-webu-section="webu_footer_01"]', 'footer']
        };

        var selectors = map[normalizedType] || map[normalizedType.toLowerCase()] || [];
        return resolveFirstSelector(selectors);
    }

    function normalizePrimitive(value) {
        if (typeof value === 'string' || typeof value === 'number') {
            var stringValue = String(value);
            return stringValue.trim() === '' ? null : stringValue;
        }

        return null;
    }

    function normalizeObjectPayload(value) {
        if (!value || typeof value !== 'object' || Array.isArray(value)) {
            return null;
        }

        return {
            label: normalizePrimitive(value.label) || normalizePrimitive(value.text) || normalizePrimitive(value.title) || normalizePrimitive(value.value),
            url: normalizePrimitive(value.url) || normalizePrimitive(value.href) || normalizePrimitive(value.src),
            alt: normalizePrimitive(value.alt) || normalizePrimitive(value.label) || normalizePrimitive(value.title)
        };
    }

    function sanitizeCssFontFamily(value) {
        if (typeof value !== 'string') {
            return '';
        }
        return value.trim().replace(/["\\\\]/g, '');
    }

    function sanitizeCssFontStyle(value) {
        if (typeof value !== 'string') {
            return 'normal';
        }
        var normalized = value.trim().toLowerCase();
        if (normalized === 'italic' || normalized === 'oblique' || normalized === 'normal') {
            return normalized;
        }
        return 'normal';
    }

    function sanitizeCssFontDisplay(value) {
        if (typeof value !== 'string') {
            return 'swap';
        }
        var normalized = value.trim().toLowerCase();
        if (['auto', 'swap', 'block', 'fallback', 'optional'].indexOf(normalized) !== -1) {
            return normalized;
        }
        return 'swap';
    }

    function sanitizeCssFontWeight(value) {
        var parsed = parseInt(value, 10);
        var allowed = [100, 200, 300, 400, 500, 600, 700, 800, 900];
        if (allowed.indexOf(parsed) === -1) {
            return '400';
        }
        return String(parsed);
    }

    function sanitizeCssFontFormat(value) {
        if (typeof value !== 'string') {
            return 'woff2';
        }
        var normalized = value.trim().toLowerCase();
        if (['woff2', 'woff', 'truetype', 'opentype'].indexOf(normalized) !== -1) {
            return normalized;
        }
        return 'woff2';
    }

    function sanitizeCssUrl(value) {
        if (typeof value !== 'string') {
            return '';
        }
        var url = value.trim();
        if (url === '') {
            return '';
        }
        if (!/^https?:\\/\\//i.test(url) && url.charAt(0) !== '/') {
            return '';
        }
        return url.replace(/"/g, '%22');
    }

    function buildFontFaceRulesFromFaces(faces) {
        if (!Array.isArray(faces)) {
            return '';
        }

        var rules = [];
        var dedupe = Object.create(null);
        faces.forEach(function (face) {
            if (!face || typeof face !== 'object') {
                return;
            }
            var family = sanitizeCssFontFamily(face.font_family);
            var srcUrl = sanitizeCssUrl(face.src_url);
            if (!family || !srcUrl) {
                return;
            }

            var format = sanitizeCssFontFormat(face.format);
            var weight = sanitizeCssFontWeight(face.font_weight);
            var style = sanitizeCssFontStyle(face.font_style);
            var display = sanitizeCssFontDisplay(face.font_display);
            var signature = [family, srcUrl, format, weight, style, display].join('|');
            if (dedupe[signature]) {
                return;
            }
            dedupe[signature] = true;
            rules.push('@font-face{font-family:"' + family + '";src:url("' + srcUrl + '") format("' + format + '");font-style:' + style + ';font-weight:' + weight + ';font-display:' + display + ';}');
        });

        return rules.join('');
    }

    function ensureRuntimeFontFaceRules(faces) {
        var rules = buildFontFaceRulesFromFaces(faces);
        if (!rules) {
            return;
        }

        var styleId = 'webu-runtime-font-faces';
        var style = document.getElementById(styleId);
        if (!style) {
            style = document.createElement('style');
            style.id = styleId;
            document.head && document.head.appendChild(style);
        }

        var current = style.textContent || '';
        if (current.indexOf(rules) !== -1) {
            return;
        }
        style.textContent = (current + '\\n' + rules).trim();
    }

    function parseTextTypographyStyle(value) {
        if (!value || typeof value !== 'object' || Array.isArray(value)) {
            return null;
        }

        var style = {};
        var copyString = function (key) {
            var raw = value[key];
            if (typeof raw === 'string' && raw.trim() !== '') {
                style[key] = raw.trim();
            }
        };
        var copyNumber = function (key) {
            var raw = value[key];
            var parsed = typeof raw === 'number' ? raw : Number(raw);
            if (!isNaN(parsed) && isFinite(parsed)) {
                style[key] = parsed;
            }
        };

        copyString('font_key');
        copyString('font_stack');
        copyString('font_style');
        copyString('text_transform');
        copyString('text_align');
        copyString('color');
        copyNumber('font_size_px');
        copyNumber('line_height');
        copyNumber('letter_spacing_px');
        copyNumber('font_weight');

        if (Array.isArray(value.font_faces)) {
            style.font_faces = value.font_faces.filter(function (face) {
                return face && typeof face === 'object'
                    && typeof face.font_family === 'string' && face.font_family.trim() !== ''
                    && typeof face.src_url === 'string' && face.src_url.trim() !== '';
            });
        }

        return Object.keys(style).length > 0 ? style : null;
    }

    function isTypographyCompanionFieldKey(key) {
        return typeof key === 'string' && /_typography$/i.test(key.trim());
    }

    function baseFieldKeyFromTypographyCompanion(key) {
        if (!isTypographyCompanionFieldKey(key)) {
            return null;
        }
        var normalized = String(key || '').trim();
        return normalized.slice(0, -'_typography'.length);
    }

    function applyTypographyStyleToNode(node, value) {
        if (!(node instanceof HTMLElement)) {
            return;
        }

        node.style.fontFamily = '';
        node.style.fontSize = '';
        node.style.lineHeight = '';
        node.style.letterSpacing = '';
        node.style.fontWeight = '';
        node.style.fontStyle = '';
        node.style.textTransform = '';
        node.style.textAlign = '';
        node.style.color = '';

        var style = parseTextTypographyStyle(value);
        if (!style) {
            return;
        }

        if (Array.isArray(style.font_faces) && style.font_faces.length > 0) {
            ensureRuntimeFontFaceRules(style.font_faces);
        }
        if (typeof style.font_stack === 'string' && style.font_stack.trim() !== '') {
            node.style.fontFamily = style.font_stack.trim();
        }
        if (typeof style.font_size_px === 'number') {
            node.style.fontSize = String(style.font_size_px) + 'px';
        }
        if (typeof style.line_height === 'number') {
            node.style.lineHeight = String(style.line_height);
        }
        if (typeof style.letter_spacing_px === 'number') {
            node.style.letterSpacing = String(style.letter_spacing_px) + 'px';
        }
        if (typeof style.font_weight === 'number') {
            node.style.fontWeight = String(style.font_weight);
        }
        if (typeof style.font_style === 'string' && style.font_style.trim() !== '') {
            node.style.fontStyle = style.font_style.trim();
        }
        if (typeof style.text_transform === 'string' && style.text_transform.trim() !== '') {
            node.style.textTransform = style.text_transform.trim();
        }
        if (typeof style.text_align === 'string' && style.text_align.trim() !== '') {
            node.style.textAlign = style.text_align.trim();
        }
        if (typeof style.color === 'string' && style.color.trim() !== '') {
            node.style.color = style.color.trim();
        }
    }

    function applyValueToNode(node, key, value) {
        if (!node) {
            return;
        }

        var nodeTag = (node.tagName || '').toUpperCase();
        var objectPayload = normalizeObjectPayload(value);
        var primitive = normalizePrimitive(value);
        var keyLower = String(key || '').toLowerCase();
        var urlLikeKey = /(url|href|link|src|image|logo)/i.test(keyLower);

        if (objectPayload) {
            if (nodeTag === 'IMG') {
                if (objectPayload.url) {
                    node.setAttribute('src', objectPayload.url);
                }
                if (objectPayload.alt) {
                    node.setAttribute('alt', objectPayload.alt);
                }
                return;
            }

            if (nodeTag === 'A') {
                if (objectPayload.url) {
                    node.setAttribute('href', objectPayload.url);
                }
                if (objectPayload.label) {
                    node.textContent = objectPayload.label;
                }
                return;
            }

            if (objectPayload.label) {
                node.textContent = objectPayload.label;
            }

            if (objectPayload.url && node instanceof HTMLElement && /^(DIV|SECTION|ARTICLE)$/.test(nodeTag)) {
                node.style.backgroundImage = 'url("' + objectPayload.url.replace(/"/g, '\\"') + '")';
            }

            return;
        }

        if (primitive === null) {
            return;
        }

        if (node instanceof HTMLElement && /(icon_class|class_name)$/i.test(keyLower)) {
            node.className = primitive;
            return;
        }

        if (nodeTag === 'INPUT' || nodeTag === 'TEXTAREA') {
            if (/(placeholder)/i.test(keyLower)) {
                node.setAttribute('placeholder', primitive);
            } else {
                node.value = primitive;
            }
            return;
        }

        if (nodeTag === 'IMG') {
            if (urlLikeKey) {
                node.setAttribute('src', primitive);
            } else {
                node.setAttribute('alt', primitive);
            }
            return;
        }

        if (nodeTag === 'A') {
            if (urlLikeKey) {
                node.setAttribute('href', primitive);
            } else {
                node.textContent = primitive;
            }
            return;
        }

        if (urlLikeKey && node instanceof HTMLElement && /^(DIV|SECTION|ARTICLE)$/.test(nodeTag)) {
            node.style.backgroundImage = 'url("' + primitive.replace(/"/g, '\\"') + '")';
            return;
        }

        node.textContent = primitive;
    }

    function applyFieldByKey(container, key, value) {
        var safeKey = String(key || '').trim();
        if (safeKey === '') {
            return;
        }

        var typographyBaseKey = baseFieldKeyFromTypographyCompanion(safeKey);
        if (typographyBaseKey) {
            var encodedTypographyBase = typographyBaseKey.replace(/"/g, '\\"');
            var typographyNodes = container.querySelectorAll('[data-webu-field="' + encodedTypographyBase + '"]');
            if (typographyNodes.length > 0) {
                typographyNodes.forEach(function (node) {
                    applyTypographyStyleToNode(node, value);
                });
                return;
            }

            var typographyBaseLower = typographyBaseKey.toLowerCase();
            var typographyFallbackSelectors = [];
            if (['headline', 'title', 'heading'].indexOf(typographyBaseLower) !== -1) {
                typographyFallbackSelectors = ['h1', 'h2', 'h3'];
            } else if (['subtitle', 'body', 'description', 'text'].indexOf(typographyBaseLower) !== -1) {
                typographyFallbackSelectors = ['p'];
            } else if (/(cta|button|link|label)/.test(typographyBaseLower)) {
                typographyFallbackSelectors = ['a.btn', 'a.button', 'button', 'a'];
            } else if (/caption/.test(typographyBaseLower)) {
                typographyFallbackSelectors = ['figcaption', 'p', 'span'];
            }

            if (typographyFallbackSelectors.length > 0) {
                var typographyFallbackNode = resolveFirstSelector(typographyFallbackSelectors.map(function (selector) {
                    return '[data-webu-section="' + (container.getAttribute('data-webu-section') || '') + '"] ' + selector;
                })) || container.querySelector(typographyFallbackSelectors.join(', '));

                if (typographyFallbackNode) {
                    applyTypographyStyleToNode(typographyFallbackNode, value);
                }
            }
            return;
        }

        var encodedKey = safeKey.replace(/"/g, '\\"');
        var fieldSelector = '[data-webu-field="' + encodedKey + '"]';
        var nodes = container.querySelectorAll(fieldSelector);

        if (nodes.length > 0) {
            nodes.forEach(function (node) {
                applyValueToNode(node, safeKey, value);
            });
            return;
        }

        var keyLower = safeKey.toLowerCase();
        var fallbackSelectors = [];

        if (['headline', 'title', 'heading'].indexOf(keyLower) !== -1) {
            fallbackSelectors = ['h1', 'h2', 'h3'];
        } else if (['subtitle', 'body', 'description', 'text'].indexOf(keyLower) !== -1) {
            fallbackSelectors = ['p'];
        } else if (/(cta|button|link)/.test(keyLower)) {
            fallbackSelectors = ['a.btn', 'a.button', 'button', 'a'];
        } else if (/(image|logo|thumbnail|photo)/.test(keyLower)) {
            fallbackSelectors = ['img'];
        }

        if (fallbackSelectors.length === 0) {
            return;
        }

        var fallbackNode = resolveFirstSelector(fallbackSelectors.map(function (selector) {
            return '[data-webu-section="' + (container.getAttribute('data-webu-section') || '') + '"] ' + selector;
        })) || container.querySelector(fallbackSelectors.join(', '));

        if (fallbackNode) {
            applyValueToNode(fallbackNode, safeKey, value);
        }
    }

    function readRuntimeStringProp(props, key) {
        if (!props || typeof props !== 'object' || Array.isArray(props)) {
            return null;
        }
        var value = props[key];
        return typeof value === 'string' && value.trim() !== '' ? value : null;
    }

    function readRuntimeLinkLabel(props, key) {
        if (!props || typeof props !== 'object' || Array.isArray(props)) {
            return null;
        }
        var value = props[key];
        if (!value || typeof value !== 'object' || Array.isArray(value)) {
            return null;
        }
        return typeof value.label === 'string' && value.label.trim() !== '' ? value.label : null;
    }

    function readRuntimeLinkUrl(props, key) {
        if (!props || typeof props !== 'object' || Array.isArray(props)) {
            return null;
        }
        var value = props[key];
        if (!value || typeof value !== 'object' || Array.isArray(value)) {
            return null;
        }
        return typeof value.url === 'string' && value.url.trim() !== '' ? value.url : null;
    }

    function normalizeFixedSectionProps(type, props) {
        if (!props || typeof props !== 'object' || Array.isArray(props)) {
            return props;
        }

        var normalizedType = String(type || '').trim().toLowerCase();
        if (!/^webu_(header|footer)_/.test(normalizedType)) {
            return props;
        }

        var next = Object.assign({}, props);

        var setIfMissing = function (key, value) {
            var existing = next[key];
            var hasExisting = typeof existing === 'string'
                ? existing.trim() !== ''
                : (existing !== undefined && existing !== null);
            if (hasExisting || value === undefined || value === null) {
                return;
            }
            next[key] = value;
        };

        if (/^webu_header_/.test(normalizedType)) {
            setIfMissing('language_option_1', 'ქართული');
            setIfMissing('language_option_2', 'ფრანგული');
            setIfMissing('language_option_3', 'ინგლისური');
            setIfMissing('currency_option_1', 'USD');
            setIfMissing('currency_option_2', 'EUR');
            setIfMissing('currency_option_3', 'GBR');
            setIfMissing('compare_label', readRuntimeLinkLabel(next, 'link_01'));
            setIfMissing('compare_url', readRuntimeLinkUrl(next, 'link_01'));
            setIfMissing('compare_icon_class', 'ti-control-shuffle');
            setIfMissing('wishlist_label', readRuntimeLinkLabel(next, 'link_02'));
            setIfMissing('wishlist_url', readRuntimeLinkUrl(next, 'link_02'));
            setIfMissing('wishlist_icon_class', 'ti-heart');
            setIfMissing('login_label', readRuntimeLinkLabel(next, 'link_03'));
            setIfMissing('login_url', readRuntimeLinkUrl(next, 'link_03'));
            setIfMissing('login_icon_class', 'ti-user');
            setIfMissing('header_phone_icon_class', 'ti-mobile');
            setIfMissing('logo_link_url', readRuntimeLinkUrl(next, 'link_04'));
            setIfMissing('products_menu_label', readRuntimeLinkLabel(next, 'link_07'));
            setIfMissing('products_menu_url', readRuntimeLinkUrl(next, 'link_07'));
            setIfMissing('search_icon_class', 'linearicons-magnifier');
            setIfMissing('cart_icon_class', 'linearicons-cart');
            setIfMissing('cart_view_button', next.link_20 || next.cart_view_button);
            setIfMissing('cart_checkout_button', next.link_21 || next.cart_checkout_button);
            setIfMissing('search_placeholder', readRuntimeStringProp(next, 'input_placeholder_01'));
            setIfMissing('logo_link_url', '/');
            setIfMissing('products_menu_label', 'Shop');
            setIfMissing('products_menu_url', '/shop');
            setIfMissing('login_label', 'Login / Register');
            setIfMissing('login_url', '/account/login');
            setIfMissing('cart_view_button', { label: 'View Cart', url: '/cart' });
            setIfMissing('cart_checkout_button', { label: 'Checkout', url: '/checkout' });

            var cartTotalText = readRuntimeStringProp(next, 'paragraph_01');
            if (cartTotalText) {
                var colonIndex = cartTotalText.indexOf(':');
                setIfMissing('cart_total_label', colonIndex >= 0 ? cartTotalText.slice(0, colonIndex + 1).trim() : cartTotalText.trim());
            }
        } else if (/^webu_footer_/.test(normalizedType)) {
            setIfMissing('contact_title', readRuntimeStringProp(next, 'heading_01'));
            setIfMissing('links_title', readRuntimeStringProp(next, 'heading_02'));
            setIfMissing('account_title', readRuntimeStringProp(next, 'heading_03'));
            setIfMissing('headline', readRuntimeStringProp(next, 'heading_04'));
            setIfMissing('subtitle', readRuntimeStringProp(next, 'paragraph_03'));
            setIfMissing('newsletter_placeholder', readRuntimeStringProp(next, 'input_placeholder_01'));
            setIfMissing('newsletter_button_icon_class', 'icon-envelope-letter');
            setIfMissing('copyright_text', readRuntimeStringProp(next, 'copyright') || readRuntimeStringProp(next, 'paragraph_04'));
            setIfMissing('account_link_1', next.link_12 || next.account_link_1);
            setIfMissing('account_link_2', next.link_13 || next.account_link_2);
            setIfMissing('account_link_3', next.link_14 || next.account_link_3);
            setIfMissing('account_link_4', next.link_15 || next.account_link_4);
            setIfMissing('account_link_5', next.link_16 || next.account_link_5);
            setIfMissing('account_link_1', { label: 'Login / Register', url: '/account/login' });
            setIfMissing('account_link_2', { label: 'My Account', url: '/account' });
            setIfMissing('account_link_3', { label: 'Orders', url: '/account/orders' });
            setIfMissing('account_link_4', { label: 'Cart', url: '/cart' });
            setIfMissing('account_link_5', { label: 'Checkout', url: '/checkout' });
            setIfMissing('social_facebook_url', readRuntimeLinkUrl(next, 'link_02'));
            setIfMissing('social_twitter_url', readRuntimeLinkUrl(next, 'link_03'));
            setIfMissing('social_google_url', readRuntimeLinkUrl(next, 'link_04'));
            setIfMissing('social_youtube_url', readRuntimeLinkUrl(next, 'link_05'));
            setIfMissing('social_instagram_url', readRuntimeLinkUrl(next, 'link_06'));
            setIfMissing('social_facebook_icon_class', 'ion-social-facebook');
            setIfMissing('social_twitter_icon_class', 'ion-social-twitter');
            setIfMissing('social_google_icon_class', 'ion-social-googleplus');
            setIfMissing('social_youtube_icon_class', 'ion-social-youtube-outline');
            setIfMissing('social_instagram_icon_class', 'ion-social-instagram-outline');
            setIfMissing('contact_address_icon_class', 'ti-location-pin');
            setIfMissing('contact_email_icon_class', 'ti-email');
            setIfMissing('contact_phone_icon_class', 'ti-mobile');
            setIfMissing('payment_icon_1_url', readRuntimeLinkUrl(next, 'image_01'));
            setIfMissing('payment_icon_2_url', readRuntimeLinkUrl(next, 'image_02'));
            setIfMissing('payment_icon_3_url', readRuntimeLinkUrl(next, 'image_03'));
            setIfMissing('payment_icon_4_url', readRuntimeLinkUrl(next, 'image_04'));
            setIfMissing('payment_icon_5_url', readRuntimeLinkUrl(next, 'image_05'));
            setIfMissing('payment_icon_1_link_url', readRuntimeLinkUrl(next, 'link_17'));
            setIfMissing('payment_icon_2_link_url', readRuntimeLinkUrl(next, 'link_18'));
            setIfMissing('payment_icon_3_link_url', readRuntimeLinkUrl(next, 'link_19'));
            setIfMissing('payment_icon_4_link_url', readRuntimeLinkUrl(next, 'link_20'));
            setIfMissing('payment_icon_5_link_url', readRuntimeLinkUrl(next, 'link_21'));
            setIfMissing('scrollup_icon_class', 'ion-ios-arrow-up');
            setIfMissing('scrollup_link_url', '#');
        }

        Object.keys(next).forEach(function (key) {
            var normalizedKey = String(key || '').trim().toLowerCase();
            if (
                /^link_\\d+$/.test(normalizedKey)
                || /^heading_\\d+$/.test(normalizedKey)
                || /^paragraph_\\d+$/.test(normalizedKey)
                || /^button_\\d+$/.test(normalizedKey)
                || /^image_\\d+$/.test(normalizedKey)
                || /^icon_\\d+$/.test(normalizedKey)
                || /^alt_\\d+$/.test(normalizedKey)
                || /^option_\\d+$/.test(normalizedKey)
                || /^value_\\d+$/.test(normalizedKey)
                || normalizedKey === 'input_placeholder_01'
                || normalizedKey === 'cta_label'
                || normalizedKey === 'logo_text'
                || (normalizedKey === 'copyright' && /^webu_footer_/.test(normalizedType))
            ) {
                delete next[key];
            }
        });

        return next;
    }

    function isGeneralSectionType(type) {
        return /^webu_general_/.test(String(type || '').trim().toLowerCase());
    }

    function detectRuntimeViewportMode() {
        var width = 0;

        if (typeof window.innerWidth === 'number' && window.innerWidth > 0) {
            width = window.innerWidth;
        } else if (document.documentElement && typeof document.documentElement.clientWidth === 'number') {
            width = document.documentElement.clientWidth;
        }

        if (width > 0 && width <= 767) {
            return 'mobile';
        }
        if (width > 0 && width <= 1024) {
            return 'tablet';
        }

        return 'desktop';
    }

    function normalizeRuntimeInteractionState(value) {
        var normalized = String(value || '').trim().toLowerCase();
        if (['hover', 'focus', 'active'].indexOf(normalized) !== -1) {
            return normalized;
        }
        return 'normal';
    }

    function readGeneralStyleOverrideMap(value) {
        if (!value || typeof value !== 'object' || Array.isArray(value)) {
            return {};
        }

        var next = {};
        ['bg_color', 'overlay_color', 'fg_color', 'bd_color'].forEach(function (key) {
            var raw = value[key];
            if (typeof raw !== 'string') {
                return;
            }
            var trimmed = raw.trim();
            if (trimmed !== '') {
                next[key] = trimmed;
            }
        });

        ['overlay_opacity_percent', 'border_radius_px', 'padding_y_px', 'padding_x_px', 'margin_y_px'].forEach(function (key) {
            var raw = value[key];
            if (typeof raw === 'number' && isFinite(raw)) {
                next[key] = Math.trunc(raw);
                return;
            }
            if (typeof raw !== 'string') {
                return;
            }
            var trimmed = raw.trim();
            if (trimmed === '') {
                return;
            }
            var parsed = parseInt(trimmed, 10);
            if (!isNaN(parsed) && isFinite(parsed)) {
                next[key] = parsed;
            }
        });

        return next;
    }

    function resolveGeneralOverlayCssColor(colorValue, opacityPercentValue) {
        var overlayColor = typeof colorValue === 'string' ? colorValue.trim() : '';
        if (overlayColor === '') {
            return null;
        }

        var parsedOpacity = parseInt(String(opacityPercentValue == null ? '' : opacityPercentValue), 10);
        var opacityPercent = !isNaN(parsedOpacity) && isFinite(parsedOpacity)
            ? Math.max(0, Math.min(100, parsedOpacity))
            : 0;
        if (opacityPercent <= 0) {
            return null;
        }

        var cssColor = opacityPercent >= 100
            ? overlayColor
            : 'color-mix(in srgb, ' + overlayColor + ' ' + String(opacityPercent) + '%, transparent)';

        return {
            cssColor: cssColor,
            opacityPercent: opacityPercent,
            sourceColor: overlayColor
        };
    }

    function applyGeneralBackgroundOverlayRuntime(container, effectiveStyleProps) {
        if (!(container instanceof HTMLElement) || !effectiveStyleProps || typeof effectiveStyleProps !== 'object') {
            return { active: false, opacityPercent: null, color: null };
        }

        var overlay = resolveGeneralOverlayCssColor(effectiveStyleProps.overlay_color, effectiveStyleProps.overlay_opacity_percent);
        var baseBackgroundAttr = 'data-webu-runtime-base-background-image';
        var baseBackgroundImage = container.getAttribute(baseBackgroundAttr);
        var resolvedBaseBackgroundImage = baseBackgroundImage !== null
            ? baseBackgroundImage
            : (container.style.backgroundImage || '');

        if (baseBackgroundImage === null) {
            container.setAttribute(baseBackgroundAttr, resolvedBaseBackgroundImage);
        }

        if (!overlay) {
            container.style.backgroundImage = resolvedBaseBackgroundImage && resolvedBaseBackgroundImage !== 'none'
                ? resolvedBaseBackgroundImage
                : '';
            container.removeAttribute('data-webu-runtime-background-overlay');
            container.removeAttribute('data-webu-runtime-background-overlay-color');
            container.removeAttribute('data-webu-runtime-background-overlay-opacity');
            return { active: false, opacityPercent: null, color: null };
        }

        var overlayLayer = 'linear-gradient(' + overlay.cssColor + ', ' + overlay.cssColor + ')';
        container.style.backgroundImage = resolvedBaseBackgroundImage && resolvedBaseBackgroundImage !== 'none'
            ? (overlayLayer + ', ' + resolvedBaseBackgroundImage)
            : overlayLayer;
        container.setAttribute('data-webu-runtime-background-overlay', '1');
        container.setAttribute('data-webu-runtime-background-overlay-color', overlay.sourceColor);
        container.setAttribute('data-webu-runtime-background-overlay-opacity', String(overlay.opacityPercent));

        return {
            active: true,
            opacityPercent: overlay.opacityPercent,
            color: overlay.sourceColor
        };
    }

    var runtimeCustomCssScopeSequence = 0;

    function normalizeCustomCssScopingInput(raw) {
        return String(raw || '')
            .replace(/<\s*\/?\s*style\b[^>]*>/gi, '')
            .replace(/\/\*[\s\S]*?\*\//g, '')
            .replace(/@(?:import|charset|namespace)\b[\s\S]*?;/gi, '')
            .trim();
    }

    function splitCssSelectorListForScoping(selectorList) {
        var result = [];
        var current = '';
        var parenDepth = 0;
        var bracketDepth = 0;
        var quote = null;

        for (var index = 0; index < selectorList.length; index += 1) {
            var char = selectorList.charAt(index);
            var prev = index > 0 ? selectorList.charAt(index - 1) : '';

            if (quote) {
                current += char;
                if (char === quote && prev !== '\\') {
                    quote = null;
                }
                continue;
            }

            if (char === '"' || char === "'") {
                quote = char;
                current += char;
                continue;
            }

            if (char === '(') {
                parenDepth += 1;
                current += char;
                continue;
            }
            if (char === ')' && parenDepth > 0) {
                parenDepth -= 1;
                current += char;
                continue;
            }
            if (char === '[') {
                bracketDepth += 1;
                current += char;
                continue;
            }
            if (char === ']' && bracketDepth > 0) {
                bracketDepth -= 1;
                current += char;
                continue;
            }

            if (char === ',' && parenDepth === 0 && bracketDepth === 0) {
                var trimmedChunk = current.trim();
                if (trimmedChunk !== '') {
                    result.push(trimmedChunk);
                }
                current = '';
                continue;
            }

            current += char;
        }

        var trailing = current.trim();
        if (trailing !== '') {
            result.push(trailing);
        }

        return result;
    }

    function prefixCssSelectorForScope(selector, scopeSelector) {
        var trimmed = String(selector || '').trim();
        if (trimmed === '') {
            return '';
        }

        if (trimmed.indexOf('&') !== -1) {
            return trimmed.split('&').join(scopeSelector);
        }

        if (trimmed.indexOf(scopeSelector) === 0) {
            return trimmed;
        }

        if (/^(html|body|:root)\b/i.test(trimmed)) {
            return trimmed.replace(/^(html|body|:root)\b/i, scopeSelector);
        }

        return scopeSelector + ' ' + trimmed;
    }

    function findMatchingCssBraceIndex(css, openIndex) {
        var depth = 0;
        var quote = null;

        for (var index = openIndex; index < css.length; index += 1) {
            var char = css.charAt(index);
            var prev = index > 0 ? css.charAt(index - 1) : '';

            if (quote) {
                if (char === quote && prev !== '\\') {
                    quote = null;
                }
                continue;
            }

            if (char === '"' || char === "'") {
                quote = char;
                continue;
            }

            if (char === '{') {
                depth += 1;
                continue;
            }

            if (char === '}') {
                depth -= 1;
                if (depth === 0) {
                    return index;
                }
            }
        }

        return -1;
    }

    function scopeCustomCssTextRecursively(rawCss, scopeSelector) {
        var css = normalizeCustomCssScopingInput(rawCss);
        if (!css) {
            return '';
        }

        if (css.indexOf('{') === -1) {
            return scopeSelector + ' { ' + css + ' }';
        }

        var chunks = [];
        var cursor = 0;

        while (cursor < css.length) {
            var nextBraceIndex = css.indexOf('{', cursor);
            if (nextBraceIndex === -1) {
                var trailing = css.slice(cursor).trim();
                if (trailing !== '') {
                    chunks.push(scopeSelector + ' { ' + trailing + ' }');
                }
                break;
            }

            var header = css.slice(cursor, nextBraceIndex).trim();
            var closeBraceIndex = findMatchingCssBraceIndex(css, nextBraceIndex);
            if (closeBraceIndex === -1) {
                var fallback = css.slice(cursor).trim();
                if (fallback !== '') {
                    chunks.push(scopeSelector + ' { ' + fallback + ' }');
                }
                break;
            }

            var body = css.slice(nextBraceIndex + 1, closeBraceIndex);
            cursor = closeBraceIndex + 1;

            if (header === '') {
                continue;
            }

            if (/^@(?:keyframes|-webkit-keyframes|-moz-keyframes|font-face|property)\b/i.test(header)) {
                continue;
            }

            if (/^@(?:media|supports|container|layer)\b/i.test(header)) {
                var scopedNested = scopeCustomCssTextRecursively(body, scopeSelector);
                if (scopedNested.trim() !== '') {
                    chunks.push(header + ' { ' + scopedNested + ' }');
                }
                continue;
            }

            if (/^@/.test(header)) {
                continue;
            }

            var scopedSelectors = splitCssSelectorListForScoping(header)
                .map(function (selector) {
                    return prefixCssSelectorForScope(selector, scopeSelector);
                })
                .filter(function (selector) {
                    return selector !== '';
                });
            if (scopedSelectors.length === 0) {
                continue;
            }

            chunks.push(scopedSelectors.join(', ') + ' { ' + body.trim() + ' }');
        }

        return chunks.join('\n');
    }

    function upsertRuntimeScopedCustomCss(container, customCss, customCssSeed) {
        if (!(container instanceof HTMLElement)) {
            return;
        }

        var root = document.head || document.body || document.documentElement;
        if (!root) {
            return;
        }

        var scopeId = String(container.getAttribute('data-webu-runtime-custom-css-scope-id') || '').trim();
        if (scopeId === '') {
            runtimeCustomCssScopeSequence += 1;
            var normalizedSeed = typeof customCssSeed === 'string'
                ? customCssSeed.trim().toLowerCase().replace(/[^a-z0-9_-]+/g, '-').replace(/^-+|-+$/g, '')
                : '';
            scopeId = normalizedSeed !== ''
                ? 'webu-runtime-css-' + normalizedSeed + '-' + runtimeCustomCssScopeSequence
                : 'webu-runtime-css-' + runtimeCustomCssScopeSequence;
            container.setAttribute('data-webu-runtime-custom-css-scope-id', scopeId);
        }

        container.setAttribute('data-webu-runtime-custom-css-scope', scopeId);
        var styleSelector = 'style[data-webu-runtime-custom-css-style-for="' + scopeId.replace(/"/g, '\\"') + '"]';
        var styleNode = document.querySelector(styleSelector);
        var scopeSelector = '[data-webu-runtime-custom-css-scope="' + scopeId.replace(/\\/g, '\\\\').replace(/"/g, '\\"') + '"]';
        var scopedCss = scopeCustomCssTextRecursively(customCss, scopeSelector);

        if (scopedCss.trim() === '') {
            if (styleNode && styleNode.parentNode) {
                styleNode.parentNode.removeChild(styleNode);
            }
            container.removeAttribute('data-webu-runtime-custom-css-scoped');
            container.removeAttribute('data-webu-runtime-custom-css-scope-hash');
            return;
        }

        if (!styleNode) {
            styleNode = document.createElement('style');
            styleNode.setAttribute('data-webu-runtime-custom-css-style-for', scopeId);
            root.appendChild(styleNode);
        }

        if (styleNode.textContent !== scopedCss) {
            styleNode.textContent = scopedCss;
        }

        container.setAttribute('data-webu-runtime-custom-css-scoped', '1');
        container.setAttribute('data-webu-runtime-custom-css-scope-hash', scopeId + ':' + String(scopedCss.length));
    }

    function normalizeGeneralComponentPresetSelection(value, allowed) {
        var normalized = typeof value === 'string' ? value.trim().toLowerCase() : '';
        if (allowed.indexOf(normalized) !== -1) {
            return normalized;
        }
        return 'none';
    }

    function readGeneralComponentPresetSelections(value) {
        var input = value && typeof value === 'object' && !Array.isArray(value) ? value : {};
        return {
            button: normalizeGeneralComponentPresetSelection(input.button, ['none', 'solid-primary', 'outline-primary', 'soft-accent']),
            card: normalizeGeneralComponentPresetSelection(input.card, ['none', 'surface', 'outline', 'elevated']),
            input: normalizeGeneralComponentPresetSelection(input.input, ['none', 'default', 'filled', 'underline'])
        };
    }

    function applyGeneralButtonPresetToNode(node, preset) {
        if (!(node instanceof HTMLElement)) {
            return;
        }

        node.style.backgroundColor = '';
        node.style.color = '';
        node.style.borderColor = '';
        node.style.borderStyle = '';
        node.style.borderWidth = '';
        node.style.borderRadius = '';
        node.style.boxShadow = '';
        node.style.padding = '';

        if (preset === 'none') {
            return;
        }

        node.style.borderRadius = 'var(--webu-token-radius-button, var(--webu-token-radius-base, 8px))';
        node.style.padding = 'var(--webu-token-space-sm, 0.5rem) var(--webu-token-space-md, 0.75rem)';

        if (preset === 'solid-primary') {
            node.style.backgroundColor = 'var(--webu-token-color-primary, #2563eb)';
            node.style.color = 'var(--webu-token-color-on-primary, #ffffff)';
            node.style.borderColor = 'transparent';
            node.style.borderStyle = 'solid';
            node.style.borderWidth = '1px';
            node.style.boxShadow = 'var(--webu-token-shadow-card, 0 1px 2px rgba(15,23,42,0.12))';
            return;
        }

        if (preset === 'outline-primary') {
            node.style.backgroundColor = 'transparent';
            node.style.color = 'var(--webu-token-color-primary, #2563eb)';
            node.style.borderColor = 'var(--webu-token-color-primary, #2563eb)';
            node.style.borderStyle = 'solid';
            node.style.borderWidth = '1px';
            node.style.boxShadow = 'none';
            return;
        }

        if (preset === 'soft-accent') {
            node.style.backgroundColor = 'var(--webu-token-color-secondary, #eef2ff)';
            node.style.color = 'var(--webu-token-color-accent, #7c3aed)';
            node.style.borderColor = 'transparent';
            node.style.borderStyle = 'solid';
            node.style.borderWidth = '1px';
            node.style.boxShadow = 'none';
        }
    }

    function applyGeneralCardPresetToNode(node, preset) {
        if (!(node instanceof HTMLElement)) {
            return;
        }

        node.style.backgroundColor = '';
        node.style.borderColor = '';
        node.style.borderStyle = '';
        node.style.borderWidth = '';
        node.style.borderRadius = '';
        node.style.boxShadow = '';

        if (preset === 'none') {
            return;
        }

        node.style.borderRadius = 'var(--webu-token-radius-card, var(--webu-token-radius-base, 12px))';

        if (preset === 'surface') {
            node.style.backgroundColor = 'var(--webu-token-color-surface, #ffffff)';
            node.style.borderColor = 'var(--webu-token-color-muted, #e5e7eb)';
            node.style.borderStyle = 'solid';
            node.style.borderWidth = '1px';
            node.style.boxShadow = 'var(--webu-token-shadow-card, 0 1px 2px rgba(15,23,42,0.08))';
            return;
        }

        if (preset === 'outline') {
            node.style.backgroundColor = 'transparent';
            node.style.borderColor = 'var(--webu-token-color-muted, #e5e7eb)';
            node.style.borderStyle = 'solid';
            node.style.borderWidth = '1px';
            node.style.boxShadow = 'none';
            return;
        }

        if (preset === 'elevated') {
            node.style.backgroundColor = 'var(--webu-token-color-surface, #ffffff)';
            node.style.borderColor = 'var(--webu-token-color-muted, #e5e7eb)';
            node.style.borderStyle = 'solid';
            node.style.borderWidth = '1px';
            node.style.boxShadow = 'var(--webu-token-shadow-elevated, var(--webu-token-shadow-card, 0 8px 24px rgba(15,23,42,0.12)))';
        }
    }

    function applyGeneralInputPresetToNode(node, preset) {
        if (!(node instanceof HTMLElement)) {
            return;
        }

        node.style.backgroundColor = '';
        node.style.color = '';
        node.style.borderColor = '';
        node.style.borderStyle = '';
        node.style.borderWidth = '';
        node.style.borderRadius = '';
        node.style.boxShadow = '';
        node.style.padding = '';

        if (preset === 'none') {
            return;
        }

        node.style.color = 'var(--webu-token-color-foreground, #111827)';
        node.style.padding = 'var(--webu-token-space-sm, 0.5rem) var(--webu-token-space-md, 0.75rem)';

        if (preset === 'default') {
            node.style.backgroundColor = 'var(--webu-token-color-background, #ffffff)';
            node.style.borderColor = 'var(--webu-token-color-muted, #d1d5db)';
            node.style.borderStyle = 'solid';
            node.style.borderWidth = '1px';
            node.style.borderRadius = 'var(--webu-token-radius-base, 8px)';
            node.style.boxShadow = 'none';
            return;
        }

        if (preset === 'filled') {
            node.style.backgroundColor = 'var(--webu-token-color-secondary, #f3f4f6)';
            node.style.borderColor = 'transparent';
            node.style.borderStyle = 'solid';
            node.style.borderWidth = '1px';
            node.style.borderRadius = 'var(--webu-token-radius-base, 8px)';
            node.style.boxShadow = 'none';
            return;
        }

        if (preset === 'underline') {
            node.style.backgroundColor = 'transparent';
            node.style.borderColor = 'var(--webu-token-color-primary, #2563eb)';
            node.style.borderStyle = 'solid';
            node.style.borderWidth = '0 0 2px 0';
            node.style.borderRadius = '0';
            node.style.boxShadow = 'none';
        }
    }

    function applyGeneralComponentStylePresetsRuntime(container, advancedProps) {
        if (!(container instanceof HTMLElement)) {
            return { button: 'none', card: 'none', input: 'none' };
        }

        var componentPresetProps = advancedProps && typeof advancedProps.component_presets === 'object' && !Array.isArray(advancedProps.component_presets)
            ? advancedProps.component_presets
            : {};
        var selections = readGeneralComponentPresetSelections(componentPresetProps);

        var cardTargets = Array.prototype.slice.call(container.querySelectorAll('[data-webu-role="card"], [data-webu-card], .card'));
        if (cardTargets.length === 0) {
            cardTargets.push(container);
        }
        cardTargets.forEach(function (node) {
            applyGeneralCardPresetToNode(node, selections.card);
        });

        Array.prototype.slice.call(container.querySelectorAll('button, a.btn, a.button, a[role="button"], [data-webu-field="button"], [data-webu-field="primary_cta"]'))
            .forEach(function (node) {
                applyGeneralButtonPresetToNode(node, selections.button);
            });

        Array.prototype.slice.call(container.querySelectorAll('input, textarea, select'))
            .forEach(function (node) {
                applyGeneralInputPresetToNode(node, selections.input);
            });

        return selections;
    }

    function applyGeneralSectionStyleRuntime(container, props) {
        if (!(container instanceof HTMLElement) || !props || typeof props !== 'object' || Array.isArray(props)) {
            return;
        }

        var hasStyleControls = props.style && typeof props.style === 'object' && !Array.isArray(props.style);
        var hasResponsiveControls = props.responsive && typeof props.responsive === 'object' && !Array.isArray(props.responsive);
        var hasStateControls = props.state && typeof props.state === 'object' && !Array.isArray(props.state);
        var hasAdvancedControls = props.advanced && typeof props.advanced === 'object' && !Array.isArray(props.advanced);

        if (!hasStyleControls && !hasResponsiveControls && !hasStateControls && !hasAdvancedControls) {
            return;
        }

        var styleProps = hasStyleControls ? props.style : {};
        var responsiveProps = hasResponsiveControls ? props.responsive : {};
        var stateProps = hasStateControls ? props.state : {};
        var advancedProps = hasAdvancedControls ? props.advanced : {};
        var componentPresetSelections = applyGeneralComponentStylePresetsRuntime(container, advancedProps);

        var parseIntProp = function (value, fallback) {
            var parsed = parseInt(String(value == null ? '' : value), 10);
            return !isNaN(parsed) && isFinite(parsed) ? parsed : fallback;
        };
        var parseBooleanProp = function (value, fallback) {
            return toBoolean(value, fallback);
        };
        var parseOptionalIntProp = function (value) {
            if (value == null) {
                return null;
            }
            if (typeof value === 'number' && isFinite(value)) {
                return Math.trunc(value);
            }
            if (typeof value !== 'string') {
                return null;
            }
            var trimmed = value.trim();
            if (trimmed === '') {
                return null;
            }
            var parsed = parseInt(trimmed, 10);
            return !isNaN(parsed) && isFinite(parsed) ? parsed : null;
        };

        var viewportMode = detectRuntimeViewportMode();
        var runtimeInteractionState = normalizeRuntimeInteractionState(
            container.getAttribute('data-webu-runtime-interaction-state')
            || query.get('ui_state')
            || query.get('interaction_state')
            || ''
        );

        var responsiveDesktopStyleOverrides = readGeneralStyleOverrideMap(responsiveProps.desktop);
        var responsiveTabletStyleOverrides = readGeneralStyleOverrideMap(responsiveProps.tablet);
        var responsiveMobileStyleOverrides = readGeneralStyleOverrideMap(responsiveProps.mobile);
        var activeResponsiveStyleOverrides = viewportMode === 'mobile'
            ? responsiveMobileStyleOverrides
            : (viewportMode === 'tablet' ? responsiveTabletStyleOverrides : responsiveDesktopStyleOverrides);

        var normalStateStyleOverrides = readGeneralStyleOverrideMap(stateProps.normal);
        var hoverStateStyleOverrides = readGeneralStyleOverrideMap(stateProps.hover);
        var focusStateStyleOverrides = readGeneralStyleOverrideMap(stateProps.focus);
        var activeStateStyleOverrides = readGeneralStyleOverrideMap(stateProps.active);
        var interactionStateStyleOverrides = runtimeInteractionState === 'hover'
            ? hoverStateStyleOverrides
            : (runtimeInteractionState === 'focus'
                ? focusStateStyleOverrides
                : (runtimeInteractionState === 'active' ? activeStateStyleOverrides : {}));

        var effectiveStyleProps = Object.assign(
            {},
            styleProps,
            activeResponsiveStyleOverrides,
            normalStateStyleOverrides,
            interactionStateStyleOverrides
        );

        var backgroundOverride = typeof effectiveStyleProps.bg_color === 'string' ? effectiveStyleProps.bg_color.trim() : '';
        var backgroundOverlay = applyGeneralBackgroundOverlayRuntime(container, effectiveStyleProps);
        var textColorOverride = typeof effectiveStyleProps.fg_color === 'string' ? effectiveStyleProps.fg_color.trim() : '';
        var borderColorOverride = typeof effectiveStyleProps.bd_color === 'string' ? effectiveStyleProps.bd_color.trim() : '';

        var radiusPx = Math.max(0, parseIntProp(effectiveStyleProps.border_radius_px, 0));
        var paddingYPx = Math.max(0, parseIntProp(effectiveStyleProps.padding_y_px, 0));
        var paddingXPx = Math.max(0, parseIntProp(effectiveStyleProps.padding_x_px, 0));
        var marginYPx = Math.max(0, parseIntProp(effectiveStyleProps.margin_y_px, 16));

        var advancedVisibilityProps = advancedProps.visibility && typeof advancedProps.visibility === 'object' && !Array.isArray(advancedProps.visibility)
            ? advancedProps.visibility
            : {};
        var advancedPositioningProps = advancedProps.positioning && typeof advancedProps.positioning === 'object' && !Array.isArray(advancedProps.positioning)
            ? advancedProps.positioning
            : {};
        var advancedAttributesProps = advancedProps.attributes && typeof advancedProps.attributes === 'object' && !Array.isArray(advancedProps.attributes)
            ? advancedProps.attributes
            : {};

        var hideOnMobile = parseBooleanProp(responsiveProps.hide_on_mobile, false);
        var hideOnTablet = parseBooleanProp(responsiveProps.hide_on_tablet, false);
        var hideOnDesktop = parseBooleanProp(responsiveProps.hide_on_desktop, false);
        var advancedVisibleOnMobile = parseBooleanProp(advancedVisibilityProps.mobile, true);
        var advancedVisibleOnTablet = parseBooleanProp(advancedVisibilityProps.tablet, true);
        var advancedVisibleOnDesktop = parseBooleanProp(advancedVisibilityProps.desktop, true);
        var hiddenByResponsiveRule = (viewportMode === 'mobile' && hideOnMobile)
            || (viewportMode === 'tablet' && hideOnTablet)
            || (viewportMode === 'desktop' && hideOnDesktop);
        var hiddenByAdvancedVisibilityRule = (viewportMode === 'mobile' && !advancedVisibleOnMobile)
            || (viewportMode === 'tablet' && !advancedVisibleOnTablet)
            || (viewportMode === 'desktop' && !advancedVisibleOnDesktop);
        var hiddenByVisibilityRule = hiddenByResponsiveRule || hiddenByAdvancedVisibilityRule;

        if (hiddenByVisibilityRule) {
            container.setAttribute('data-webu-runtime-hidden-by-responsive', hiddenByResponsiveRule ? '1' : '0');
            if (hiddenByAdvancedVisibilityRule) {
                container.setAttribute('data-webu-runtime-hidden-by-advanced-visibility', '1');
            } else {
                container.removeAttribute('data-webu-runtime-hidden-by-advanced-visibility');
            }
            container.style.display = 'none';
        } else {
            if (container.getAttribute('data-webu-runtime-hidden-by-responsive') !== null) {
                container.style.display = '';
            }
            container.removeAttribute('data-webu-runtime-hidden-by-responsive');
            container.removeAttribute('data-webu-runtime-hidden-by-advanced-visibility');
        }

        if (backgroundOverride !== '') {
            container.style.backgroundColor = backgroundOverride;
        }
        if (textColorOverride !== '') {
            container.style.color = textColorOverride;
        }
        if (borderColorOverride !== '') {
            container.style.borderColor = borderColorOverride;
        }

        container.style.borderRadius = radiusPx > 0 ? String(radiusPx) + 'px' : '';
        container.style.paddingTop = paddingYPx > 0 ? String(paddingYPx) + 'px' : container.style.paddingTop;
        container.style.paddingBottom = paddingYPx > 0 ? String(paddingYPx) + 'px' : container.style.paddingBottom;
        container.style.paddingLeft = paddingXPx > 0 ? String(paddingXPx) + 'px' : container.style.paddingLeft;
        container.style.paddingRight = paddingXPx > 0 ? String(paddingXPx) + 'px' : container.style.paddingRight;
        container.style.marginTop = String(marginYPx) + 'px';
        container.style.marginBottom = String(marginYPx) + 'px';
        if (backgroundOverlay && backgroundOverlay.active) {
            container.setAttribute('data-webu-runtime-background-overlay-source', 'base>responsive>state');
        } else {
            container.removeAttribute('data-webu-runtime-background-overlay-source');
        }

        var disabledState = parseBooleanProp(stateProps.disabled, false);
        var mutedState = parseBooleanProp(stateProps.muted, false);
        var opacityPercent = Math.min(100, Math.max(5, parseIntProp(advancedProps.opacity_percent, 100)));
        var htmlId = typeof advancedProps.html_id === 'string' ? advancedProps.html_id.trim() : '';
        var cssClass = typeof advancedProps.css_class === 'string' ? advancedProps.css_class.trim() : '';
        var customCss = typeof advancedProps.custom_css === 'string' ? advancedProps.custom_css.trim() : '';
        var positionModeRaw = typeof advancedPositioningProps.position_mode === 'string'
            ? advancedPositioningProps.position_mode.trim().toLowerCase()
            : '';
        var positionMode = ['relative', 'absolute', 'sticky'].indexOf(positionModeRaw) !== -1 ? positionModeRaw : 'static';
        var positionTopPx = parseOptionalIntProp(advancedPositioningProps.top_px);
        var positionRightPx = parseOptionalIntProp(advancedPositioningProps.right_px);
        var positionBottomPx = parseOptionalIntProp(advancedPositioningProps.bottom_px);
        var positionLeftPx = parseOptionalIntProp(advancedPositioningProps.left_px);
        var positionZIndex = parseOptionalIntProp(advancedPositioningProps.z_index);
        var roleAttr = typeof advancedAttributesProps.role === 'string' ? advancedAttributesProps.role.trim() : '';
        var ariaLabelAttr = typeof advancedAttributesProps.aria_label === 'string' ? advancedAttributesProps.aria_label.trim() : '';
        var ariaDescriptionAttr = typeof advancedAttributesProps.aria_description === 'string' ? advancedAttributesProps.aria_description.trim() : '';
        var ariaLiveRaw = typeof advancedAttributesProps.aria_live === 'string' ? advancedAttributesProps.aria_live.trim().toLowerCase() : '';
        var ariaLiveAttr = ['off', 'polite', 'assertive'].indexOf(ariaLiveRaw) !== -1 ? ariaLiveRaw : '';
        var dataTestIdAttr = typeof advancedAttributesProps.data_testid === 'string' ? advancedAttributesProps.data_testid.trim() : '';
        var dataTrackingAttr = typeof advancedAttributesProps.data_tracking === 'string' ? advancedAttributesProps.data_tracking.trim() : '';
        var effectiveOpacity = Math.max(
            0.12,
            (opacityPercent / 100)
            * (mutedState ? 0.65 : 1)
            * (disabledState ? 0.7 : 1)
            * (hiddenByVisibilityRule ? 0.45 : 1)
        );

        container.style.opacity = String(effectiveOpacity);
        container.style.pointerEvents = disabledState ? 'none' : '';

        container.style.position = positionMode === 'static' ? '' : positionMode;
        container.style.top = positionMode !== 'static' && positionTopPx !== null ? String(positionTopPx) + 'px' : '';
        container.style.right = positionMode !== 'static' && positionRightPx !== null ? String(positionRightPx) + 'px' : '';
        container.style.bottom = positionMode !== 'static' && positionBottomPx !== null ? String(positionBottomPx) + 'px' : '';
        container.style.left = positionMode !== 'static' && positionLeftPx !== null ? String(positionLeftPx) + 'px' : '';
        container.style.zIndex = positionZIndex !== null ? String(positionZIndex) : '';
        container.setAttribute('data-webu-runtime-positioning-mode', positionMode);

        if (roleAttr !== '') {
            container.setAttribute('role', roleAttr);
        } else {
            container.removeAttribute('role');
        }
        if (ariaLabelAttr !== '') {
            container.setAttribute('aria-label', ariaLabelAttr);
        } else {
            container.removeAttribute('aria-label');
        }
        if (ariaDescriptionAttr !== '') {
            container.setAttribute('aria-description', ariaDescriptionAttr);
        } else {
            container.removeAttribute('aria-description');
        }
        if (ariaLiveAttr !== '') {
            container.setAttribute('aria-live', ariaLiveAttr);
        } else {
            container.removeAttribute('aria-live');
        }
        if (dataTestIdAttr !== '') {
            container.setAttribute('data-testid', dataTestIdAttr);
        } else {
            container.removeAttribute('data-testid');
        }
        if (dataTrackingAttr !== '') {
            container.setAttribute('data-tracking', dataTrackingAttr);
        } else {
            container.removeAttribute('data-tracking');
        }
        if (htmlId !== '') {
            container.setAttribute('data-webu-runtime-html-id', htmlId);
        } else {
            container.removeAttribute('data-webu-runtime-html-id');
        }
        if (cssClass !== '') {
            container.setAttribute('data-webu-runtime-css-class', cssClass);
        } else {
            container.removeAttribute('data-webu-runtime-css-class');
        }
        if (customCss !== '') {
            container.setAttribute('data-webu-runtime-custom-css-present', '1');
            container.setAttribute('data-webu-runtime-custom-css-bytes', String(customCss.length));
        } else {
            container.removeAttribute('data-webu-runtime-custom-css-present');
            container.removeAttribute('data-webu-runtime-custom-css-bytes');
        }
        upsertRuntimeScopedCustomCss(
            container,
            customCss,
            htmlId || container.getAttribute('data-webu-section') || 'general'
        );
        container.setAttribute(
            'data-webu-runtime-component-presets',
            'button:' + componentPresetSelections.button + ';card:' + componentPresetSelections.card + ';input:' + componentPresetSelections.input
        );
        container.setAttribute('data-webu-runtime-interaction-state-preview', runtimeInteractionState);
        container.setAttribute('data-webu-runtime-style-order', 'base>responsive>state');
    }

    function applySectionProps(type, props) {
        var container = findSectionContainer(type);
        if (!container || !props || typeof props !== 'object') {
            return;
        }

        var effectiveProps = normalizeFixedSectionProps(type, props) || props;

        if (isGeneralSectionType(type) && container instanceof HTMLElement) {
            applyGeneralSectionStyleRuntime(container, effectiveProps);
        }

        var heading = container.querySelector('[data-webu-field="headline"], [data-webu-field="title"], h1, h2, h3');
        var subtitle = container.querySelector('[data-webu-field="subtitle"], [data-webu-field="body"], p');
        var button = container.querySelector('[data-webu-field="button"], [data-webu-field="primary_cta"], button, a.btn, a.button');

        if (heading) {
            var headingValue = effectiveProps.headline || effectiveProps.title;
            if (typeof headingValue === 'string' && headingValue.trim() !== '') {
                heading.textContent = headingValue;
            }
        }

        if (subtitle) {
            var subtitleValue = effectiveProps.subtitle || effectiveProps.body;
            if (typeof subtitleValue === 'string' && subtitleValue.trim() !== '') {
                subtitle.textContent = subtitleValue;
            }
        }

        var ctaPayload = null;
        if (effectiveProps.primary_cta && typeof effectiveProps.primary_cta === 'object') {
            ctaPayload = effectiveProps.primary_cta;
        } else if (effectiveProps.button && typeof effectiveProps.button === 'object') {
            ctaPayload = effectiveProps.button;
        } else if (typeof effectiveProps.button === 'string' && effectiveProps.button.trim() !== '') {
            ctaPayload = { label: effectiveProps.button };
        } else if (typeof effectiveProps.cta_label === 'string' && effectiveProps.cta_label.trim() !== '') {
            ctaPayload = { label: effectiveProps.cta_label };
        }

        if (button && ctaPayload && typeof ctaPayload === 'object') {
            if (typeof ctaPayload.label === 'string' && ctaPayload.label.trim() !== '') {
                button.textContent = ctaPayload.label;
            }
            if (button.tagName === 'A' && typeof ctaPayload.url === 'string' && ctaPayload.url.trim() !== '') {
                button.setAttribute('href', ctaPayload.url);
            }
        }

        Object.keys(effectiveProps).forEach(function (key) {
            applyFieldByKey(container, key, effectiveProps[key]);
        });
    }

    function applyTypography(typography) {
        if (!typography || typeof typography !== 'object') {
            return;
        }

        if (Array.isArray(typography.font_faces)) {
            ensureRuntimeFontFaceRules(typography.font_faces);
        }

        var root = document.documentElement;
        if (typeof typography.font_stack === 'string' && typography.font_stack.trim() !== '') {
            root.style.setProperty('--webby-font-base', typography.font_stack.trim());
        }
        if (typeof typography.heading_font_stack === 'string' && typography.heading_font_stack.trim() !== '') {
            root.style.setProperty('--webby-font-heading', typography.heading_font_stack.trim());
        }
        if (typeof typography.body_font_stack === 'string' && typography.body_font_stack.trim() !== '') {
            root.style.setProperty('--webby-font-body', typography.body_font_stack.trim());
        }
        if (typeof typography.button_font_stack === 'string' && typography.button_font_stack.trim() !== '') {
            root.style.setProperty('--webby-font-button', typography.button_font_stack.trim());
        }
    }

    function normalizeCssVarSegment(value) {
        return String(value || '')
            .trim()
            .toLowerCase()
            .replace(/[^a-z0-9_-]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function pickEffectiveTokenColorMap(colors) {
        if (!colors || typeof colors !== 'object' || Array.isArray(colors)) {
            return {};
        }

        var modes = colors.modes && typeof colors.modes === 'object' && !Array.isArray(colors.modes)
            ? colors.modes
            : null;
        if (modes) {
            var preferredMode = document.documentElement.classList.contains('dark') ? 'dark' : 'light';
            var modeMap = modes[preferredMode] && typeof modes[preferredMode] === 'object' && !Array.isArray(modes[preferredMode])
                ? modes[preferredMode]
                : null;
            if (modeMap) {
                return modeMap;
            }
        }

        return colors;
    }

    function applyThemeTokenLayers(themeTokenLayers) {
        if (!themeTokenLayers || typeof themeTokenLayers !== 'object') {
            return;
        }

        var effective = themeTokenLayers.effective && typeof themeTokenLayers.effective === 'object' && !Array.isArray(themeTokenLayers.effective)
            ? themeTokenLayers.effective
            : null;
        if (!effective) {
            return;
        }

        var themeTokens = effective.theme_tokens && typeof effective.theme_tokens === 'object' && !Array.isArray(effective.theme_tokens)
            ? effective.theme_tokens
            : null;
        if (!themeTokens) {
            return;
        }

        var root = document.documentElement;
        var colorMap = pickEffectiveTokenColorMap(themeTokens.colors || {});

        Object.keys(colorMap).forEach(function (key) {
            if (key === 'modes') {
                return;
            }
            var cssVarKey = normalizeCssVarSegment(key);
            if (!cssVarKey) {
                return;
            }

            var rawValue = colorMap[key];
            if (typeof rawValue !== 'string' && typeof rawValue !== 'number') {
                return;
            }

            var value = String(rawValue).trim();
            if (!value) {
                return;
            }

            root.style.setProperty('--webu-token-color-' + cssVarKey, value);
        });

        var radii = themeTokens.radii && typeof themeTokens.radii === 'object' && !Array.isArray(themeTokens.radii)
            ? themeTokens.radii
            : null;
        if (radii) {
            Object.keys(radii).forEach(function (rawKey) {
                var cssKey = normalizeCssVarSegment(rawKey);
                if (!cssKey) {
                    return;
                }

                var rawValue = radii[rawKey];
                if (typeof rawValue !== 'string' && typeof rawValue !== 'number') {
                    return;
                }

                var value = String(rawValue).trim();
                if (!value) {
                    return;
                }

                root.style.setProperty('--webu-token-radius-' + cssKey, value);
            });
            var baseRadius = (typeof radii.base === 'string' || typeof radii.base === 'number')
                ? String(radii.base).trim()
                : '';
            if (baseRadius) {
                root.style.setProperty('--webu-token-radius-base', baseRadius);
                root.style.setProperty('--radius', baseRadius);
            }
        }

        [
            { key: 'spacing', cssPrefix: '--webu-token-space-' },
            { key: 'shadows', cssPrefix: '--webu-token-shadow-' },
            { key: 'breakpoints', cssPrefix: '--webu-token-breakpoint-' }
        ].forEach(function (groupSpec) {
            var groupValues = themeTokens[groupSpec.key] && typeof themeTokens[groupSpec.key] === 'object' && !Array.isArray(themeTokens[groupSpec.key])
                ? themeTokens[groupSpec.key]
                : null;
            if (!groupValues) {
                return;
            }

            Object.keys(groupValues).forEach(function (rawKey) {
                var cssKey = normalizeCssVarSegment(rawKey);
                if (!cssKey) {
                    return;
                }

                var rawValue = groupValues[rawKey];
                if (typeof rawValue !== 'string' && typeof rawValue !== 'number') {
                    return;
                }

                var value = String(rawValue).trim();
                if (!value) {
                    return;
                }

                root.style.setProperty(groupSpec.cssPrefix + cssKey, value);
            });
        });

        window.__WEBU_THEME_TOKEN_LAYERS__ = themeTokenLayers;
    }

    function onCmsReady(callback) {
        if (typeof callback !== 'function') {
            return;
        }

        state.cmsReadyCallbacks.push(callback);

        if (state.payload) {
            callback(state.payload);
        }
    }

    function notifyCmsReady(payload) {
        state.cmsReadyCallbacks.forEach(function (callback) {
            try {
                callback(payload);
            } catch (error) {
                console.warn('[webu-theme-runtime] cms ready callback failed', error);
            }
        });
    }

    function applyCmsPayload(payload) {
        state.payload = payload;
        state.siteId = payload.site_id || state.siteId;

        var siteName = (payload.site && payload.site.name) || (payload.global_settings && payload.global_settings.site_name);
        if (typeof siteName === 'string' && siteName.trim() !== '') {
            setText(['[data-webu-site-name]', '[data-webu-brand-name]', 'footer .brand-name'], siteName);
        }

        if (payload.page && payload.page.seo_title) {
            document.title = payload.page.seo_title;
        }

        var globalSettings = payload.global_settings || {};
        var themeSettings = payload.theme_settings || (payload.site && payload.site.theme_settings) || {};
        var layoutSettings = themeSettings && typeof themeSettings === 'object' && !Array.isArray(themeSettings)
            ? (themeSettings.layout && typeof themeSettings.layout === 'object' && !Array.isArray(themeSettings.layout) ? themeSettings.layout : {})
            : {};
        setLogo(globalSettings.logo_asset_url || null);
        applyContact(globalSettings.contact_json || {});

        var headerMenuSource = normalizeMenuSource(layoutSettings.header_menu_key, 'header');
        var headerItems = payload.menus && payload.menus[headerMenuSource] ? payload.menus[headerMenuSource].items_json : [];
        renderMenu(headerItems, 'header');
        applyPopupModalSettings(layoutSettings.popup_modal);

        var sections = (((payload.revision || {}).content_json || {}).sections || []);
        if (Array.isArray(sections)) {
            sections.forEach(function (section) {
                if (!section || typeof section !== 'object') {
                    return;
                }
                var type = String(section.type || section.key || '');
                if (!type) {
                    return;
                }
                applySectionProps(type, section.props || {});
            });
        }

        var headerSectionKey = typeof layoutSettings.header_section_key === 'string' && layoutSettings.header_section_key.trim() !== ''
            ? layoutSettings.header_section_key.trim()
            : 'webu_header_01';
        var footerSectionKey = typeof layoutSettings.footer_section_key === 'string' && layoutSettings.footer_section_key.trim() !== ''
            ? layoutSettings.footer_section_key.trim()
            : 'webu_footer_01';

        if (layoutSettings.header_props && typeof layoutSettings.header_props === 'object') {
            applySectionProps(headerSectionKey, layoutSettings.header_props);
        }
        if (layoutSettings.footer_props && typeof layoutSettings.footer_props === 'object') {
            applySectionProps(footerSectionKey, layoutSettings.footer_props);
        }

        applyThemeTokenLayers(payload.theme_token_layers || null);
        applyTypography(payload.typography || null);
        notifyCmsReady(payload);
    }

    function loadPayloadViaPublicApi(siteId) {
        var localePart = state.locale ? ('?locale=' + encodeURIComponent(state.locale)) : '';
        var base = '/public/sites/' + encodeURIComponent(siteId);
        var fetchPublicSettings = function () {
            return jsonFetch(base + '/settings' + localePart);
        };
        var fetchDraftSettings = function () {
            return jsonFetch('/panel/sites/' + encodeURIComponent(siteId) + '/settings')
                .then(function (payload) {
                    var site = payload && payload.site ? payload.site : {};
                    return {
                        name: site.name || 'Webu Site',
                        locale: site.locale || state.locale,
                        theme_settings: site.theme_settings && typeof site.theme_settings === 'object' ? site.theme_settings : {},
                        theme_token_layers: payload && payload.theme_token_layers && typeof payload.theme_token_layers === 'object'
                            ? payload.theme_token_layers
                            : null,
                        global_settings: payload && payload.global_settings ? payload.global_settings : {},
                        typography: payload && payload.typography ? payload.typography : null
                    };
                });
        };
        var fetchPublicMenu = function (key) {
            return jsonFetch(base + '/menu/' + encodeURIComponent(key) + localePart).catch(function () { return null; });
        };
        var fetchDraftMenu = function (key) {
            return jsonFetch('/panel/sites/' + encodeURIComponent(siteId) + '/menus/' + encodeURIComponent(key))
                .catch(function () { return null; });
        };
        var fetchPublicTypography = function () {
            return jsonFetch(base + '/theme/typography' + localePart).catch(function () { return null; });
        };
        var fetchDraftTypography = function () {
            return jsonFetch('/panel/sites/' + encodeURIComponent(siteId) + '/theme/typography')
                .catch(function () { return null; });
        };
        var fetchPublicPage = function () {
            return jsonFetch(base + '/pages/' + encodeURIComponent(state.slug) + localePart).catch(function () {
                if (state.slug === 'home') {
                    throw new Error('PAGE_NOT_FOUND');
                }
                return jsonFetch(base + '/pages/home' + localePart);
            });
        };
        var fetchDraftPage = function () {
            return jsonFetch('/panel/sites/' + encodeURIComponent(siteId) + '/pages')
                .then(function (payload) {
                    var pages = Array.isArray(payload && payload.pages) ? payload.pages : [];
                    var normalizedSlug = String(state.slug || 'home').toLowerCase();
                    var page = null;

                    for (var i = 0; i < pages.length; i++) {
                        if (String(pages[i] && pages[i].slug ? pages[i].slug : '').toLowerCase() === normalizedSlug) {
                            page = pages[i];
                            break;
                        }
                    }

                    if (!page && normalizedSlug !== 'home') {
                        for (var j = 0; j < pages.length; j++) {
                            if (String(pages[j] && pages[j].slug ? pages[j].slug : '').toLowerCase() === 'home') {
                                page = pages[j];
                                break;
                            }
                        }
                    }

                    if (!page || !page.id) {
                        throw new Error('DRAFT_PAGE_NOT_FOUND');
                    }

                    return jsonFetch('/panel/sites/' + encodeURIComponent(siteId) + '/pages/' + encodeURIComponent(String(page.id)));
                })
                .then(function (detail) {
                    return {
                        page: detail && detail.page ? detail.page : null,
                        revision: (detail && (detail.latest_revision || detail.published_revision)) || null
                    };
                });
        };
        var pagePromise = state.draftMode
            ? fetchDraftPage().catch(function () { return fetchPublicPage(); })
            : fetchPublicPage();
        var settingsPromise = state.draftMode
            ? fetchDraftSettings().catch(function () { return fetchPublicSettings(); })
            : fetchPublicSettings();
        var typographyPromise = state.draftMode
            ? fetchDraftTypography().then(function (typography) { return typography || fetchPublicTypography(); })
            : fetchPublicTypography();

        return settingsPromise.then(function (settings) {
            settings = settings || {};

            var themeSettings = settings.theme_settings && typeof settings.theme_settings === 'object'
                ? settings.theme_settings
                : {};
            var layoutSettings = themeSettings.layout && typeof themeSettings.layout === 'object'
                ? themeSettings.layout
                : {};
            var headerMenuKey = normalizeMenuSource(layoutSettings.header_menu_key, 'header');
            var menuKeys = [headerMenuKey, 'header'].filter(function (value, index, source) {
                return source.indexOf(value) === index;
            });

            var menuPromises = menuKeys.map(function (key) {
                var menuPromise = state.draftMode
                    ? fetchDraftMenu(key).then(function (menu) { return menu || fetchPublicMenu(key); })
                    : fetchPublicMenu(key);

                return menuPromise
                    .then(function (menu) {
                        return {
                            key: key,
                            menu: menu || { key: key, items_json: [] }
                        };
                    })
                    .catch(function () {
                        return {
                            key: key,
                            menu: { key: key, items_json: [] }
                        };
                    });
            });

            return Promise.all([
                Promise.all(menuPromises),
                pagePromise,
                typographyPromise
            ]).then(function (parts) {
                var menuEntries = Array.isArray(parts[0]) ? parts[0] : [];
                var page = parts[1] || {};
                var typography = parts[2] || {};
                var menusPayload = {};

                menuEntries.forEach(function (entry) {
                    if (!entry || typeof entry !== 'object' || !entry.key) {
                        return;
                    }
                    menusPayload[entry.key] = entry.menu || { key: entry.key, items_json: [] };
                });

                return {
                    site_id: siteId,
                    site: {
                        id: siteId,
                        name: settings.name || 'Webu Site',
                        locale: settings.locale || state.locale,
                        theme_settings: themeSettings
                    },
                    theme_settings: themeSettings,
                    theme_token_layers: settings.theme_token_layers || null,
                    global_settings: settings.global_settings || {},
                    menus: menusPayload,
                    page: page.page || null,
                    revision: page.revision || null,
                    typography: typography.typography || settings.typography || null,
                    meta: {
                        source: state.draftMode ? 'webu-theme-runtime-draft' : 'webu-theme-runtime'
                    }
                };
            });
        });
    }

    window.WebuCms = {
        getSiteId: function () { return state.siteId; },
        getLocale: function () { return state.locale; },
        getCurrentPageSlug: function () { return state.slug; },
        getPayload: function () { return state.payload; },
        onReady: onCmsReady,
        fetchSettings: function () {
            if (!state.siteId) {
                return Promise.reject(new Error('SITE_ID_MISSING'));
            }
            if (state.draftMode) {
                return jsonFetch('/panel/sites/' + encodeURIComponent(state.siteId) + '/settings')
                    .catch(function () {
                        return jsonFetch('/public/sites/' + encodeURIComponent(state.siteId) + '/settings?locale=' + encodeURIComponent(state.locale));
                    });
            }
            return jsonFetch('/public/sites/' + encodeURIComponent(state.siteId) + '/settings?locale=' + encodeURIComponent(state.locale));
        },
        fetchMenu: function (menuKey) {
            if (!state.siteId) {
                return Promise.reject(new Error('SITE_ID_MISSING'));
            }
            var key = String(menuKey || 'header');
            if (state.draftMode) {
                return jsonFetch('/panel/sites/' + encodeURIComponent(state.siteId) + '/menus/' + encodeURIComponent(key))
                    .catch(function () {
                        return jsonFetch('/public/sites/' + encodeURIComponent(state.siteId) + '/menu/' + encodeURIComponent(key) + '?locale=' + encodeURIComponent(state.locale));
                    });
            }
            return jsonFetch('/public/sites/' + encodeURIComponent(state.siteId) + '/menu/' + encodeURIComponent(key) + '?locale=' + encodeURIComponent(state.locale));
        },
        fetchPage: function (pageSlug) {
            if (!state.siteId) {
                return Promise.reject(new Error('SITE_ID_MISSING'));
            }
            var targetSlug = String(pageSlug || 'home');
            if (state.draftMode) {
                return jsonFetch('/panel/sites/' + encodeURIComponent(state.siteId) + '/pages')
                    .then(function (payload) {
                        var pages = Array.isArray(payload && payload.pages) ? payload.pages : [];
                        var normalizedSlug = targetSlug.toLowerCase();
                        var page = null;

                        for (var i = 0; i < pages.length; i++) {
                            if (String(pages[i] && pages[i].slug ? pages[i].slug : '').toLowerCase() === normalizedSlug) {
                                page = pages[i];
                                break;
                            }
                        }

                        if (!page || !page.id) {
                            throw new Error('DRAFT_PAGE_NOT_FOUND');
                        }

                        return jsonFetch('/panel/sites/' + encodeURIComponent(state.siteId) + '/pages/' + encodeURIComponent(String(page.id)));
                    })
                    .then(function (detail) {
                        return {
                            page: detail && detail.page ? detail.page : null,
                            revision: (detail && (detail.latest_revision || detail.published_revision)) || null
                        };
                    })
                    .catch(function () {
                        return jsonFetch('/public/sites/' + encodeURIComponent(state.siteId) + '/pages/' + encodeURIComponent(targetSlug) + '?locale=' + encodeURIComponent(state.locale) + '&draft=1');
                    });
            }
            return jsonFetch('/public/sites/' + encodeURIComponent(state.siteId) + '/pages/' + encodeURIComponent(targetSlug) + '?locale=' + encodeURIComponent(state.locale) + '&draft=1');
        },
        refresh: function () {
            var siteIdPromise = state.siteId ? Promise.resolve(state.siteId) : resolveSiteId();
            return siteIdPromise
                .then(function (resolvedSiteId) {
                    state.siteId = resolvedSiteId;
                    return loadPayloadViaPublicApi(resolvedSiteId);
                })
                .then(function (payload) {
                    applyCmsPayload(payload);
                    return payload;
                });
        }
    };

    function ecommerceBase(siteId) {
        return '/public/sites/' + encodeURIComponent(siteId) + '/ecommerce';
    }

    function ecommerceUrl(siteId, path, queryString) {
        var suffix = typeof queryString === 'string' ? queryString : '';
        var url = ecommerceBase(siteId) + path + suffix;

        if (typeof document !== 'undefined' && document.location && typeof document.location.search === 'string' && /(?:^|[?&])draft=1(?:&|$)/.test(document.location.search)) {
            if (!/(?:^|[?&])draft=1(?:&|$)/.test(url)) {
                url += (url.indexOf('?') === -1 ? '?draft=1' : '&draft=1');
            }
        }

        return url;
    }

    function setCartId(cartId) {
        if (!cartId) {
            return;
        }
        try {
            window.localStorage.setItem('webu-ecommerce-cart:' + THEME_SLUG, String(cartId));
        } catch (_error) {}
    }

    function getCartId() {
        try {
            return window.localStorage.getItem('webu-ecommerce-cart:' + THEME_SLUG);
        } catch (_error) {
            return null;
        }
    }

    function ensureEcommerceBridge(siteId) {
        if (!siteId) {
            return;
        }

        if (window.WebbyEcommerce && window.WebbyEcommerce.__siteId === siteId) {
            return;
        }

        function listProducts(params) {
            var queryString = '';
            if (params && typeof params === 'object') {
                var qs = new URLSearchParams();
                Object.keys(params).forEach(function (key) {
                    if (params[key] != null && params[key] !== '') {
                        qs.set(key, String(params[key]));
                    }
                });
                queryString = qs.toString() ? ('?' + qs.toString()) : '';
            }

            return jsonFetch(ecommerceUrl(siteId, '/products', queryString));
        }

        function listProductsCached(params) {
            return listProducts(params).then(function (response) {
                state.productsCache = Array.isArray(response.products) ? response.products : [];
                return response;
            });
        }

        function getProduct(slug) {
            return jsonFetch(ecommerceUrl(siteId, '/products/' + encodeURIComponent(slug)));
        }

        function getPaymentOptions() {
            return jsonFetch(ecommerceUrl(siteId, '/payment-options'));
        }

        function getShippingOptions(cartId, payload) {
            return jsonFetch(ecommerceUrl(siteId, '/carts/' + encodeURIComponent(cartId) + '/shipping/options'), {
                method: 'POST',
                body: JSON.stringify(payload || {})
            });
        }

        function updateShippingSelection(cartId, payload) {
            return jsonFetch(ecommerceUrl(siteId, '/carts/' + encodeURIComponent(cartId) + '/shipping'), {
                method: 'PUT',
                body: JSON.stringify(payload || {})
            }).then(function (response) {
                if (response && response.cart) {
                    state.cart = response.cart;
                }

                return response;
            });
        }

        function createCart(payload) {
            return jsonFetch(ecommerceUrl(siteId, '/carts'), {
                method: 'POST',
                body: JSON.stringify(payload || {})
            }).then(function (response) {
                if (response && response.cart && response.cart.id) {
                    setCartId(response.cart.id);
                    state.cart = response.cart;
                }
                return response;
            });
        }

        function getCart(cartId) {
            var targetId = cartId || getCartId();
            if (!targetId) {
                return createCart({}).then(function (response) { return response; });
            }

            return jsonFetch(ecommerceUrl(siteId, '/carts/' + encodeURIComponent(targetId)))
                .then(function (response) {
                    if (response && response.cart && response.cart.id) {
                        setCartId(response.cart.id);
                        state.cart = response.cart;
                    }
                    return response;
                })
                .catch(function (error) {
                    if (String(error && error.message ? error.message : '').indexOf('HTTP_404') !== -1) {
                        return createCart({}).then(function (response) {
                            return response;
                        });
                    }

                    throw error;
                });
        }

        function addCartItem(cartId, payload) {
            return jsonFetch(ecommerceUrl(siteId, '/carts/' + encodeURIComponent(cartId) + '/items'), {
                method: 'POST',
                body: JSON.stringify(payload || {})
            }).then(function (response) {
                if (response && response.cart && response.cart.id) {
                    state.cart = response.cart;
                }
                return response;
            });
        }

        function updateCartItem(cartId, itemId, payload) {
            return jsonFetch(ecommerceUrl(siteId, '/carts/' + encodeURIComponent(cartId) + '/items/' + encodeURIComponent(itemId)), {
                method: 'PUT',
                body: JSON.stringify(payload || {})
            }).then(function (response) {
                if (response && response.cart && response.cart.id) {
                    state.cart = response.cart;
                }
                return response;
            });
        }

        function removeCartItem(cartId, itemId) {
            return jsonFetch(ecommerceUrl(siteId, '/carts/' + encodeURIComponent(cartId) + '/items/' + encodeURIComponent(itemId)), {
                method: 'DELETE'
            }).then(function (response) {
                if (response && response.cart && response.cart.id) {
                    state.cart = response.cart;
                }
                return response;
            });
        }

        function checkout(cartId, payload) {
            return jsonFetch(ecommerceUrl(siteId, '/carts/' + encodeURIComponent(cartId) + '/checkout'), {
                method: 'POST',
                body: JSON.stringify(payload || {})
            });
        }

        function startPayment(orderId, payload) {
            return jsonFetch(ecommerceUrl(siteId, '/orders/' + encodeURIComponent(orderId) + '/payments/start'), {
                method: 'POST',
                body: JSON.stringify(payload || {})
            });
        }

        function customerMe() {
            return jsonFetch('/public/sites/' + encodeURIComponent(siteId) + '/customers/me');
        }

        function customerLogin(payload) {
            return jsonFetch('/public/sites/' + encodeURIComponent(siteId) + '/customers/login', {
                method: 'POST',
                body: JSON.stringify(payload || {})
            });
        }

        function customerMeUpdate(payload) {
            return jsonFetch('/public/sites/' + encodeURIComponent(siteId) + '/customers/me', {
                method: 'PUT',
                body: JSON.stringify(payload || {})
            });
        }

        function getOrders(params) {
            var queryString = '';
            if (params && typeof params === 'object') {
                var qs = new URLSearchParams();
                Object.keys(params).forEach(function (key) {
                    if (params[key] != null && params[key] !== '') {
                        qs.set(key, String(params[key]));
                    }
                });
                queryString = qs.toString() ? ('?' + qs.toString()) : '';
            }

            return jsonFetch(ecommerceUrl(siteId, '/customer-orders', queryString));
        }

        function getOrder(orderId) {
            return jsonFetch(ecommerceUrl(siteId, '/customer-orders/' + encodeURIComponent(orderId)));
        }

        function fileForSlug(slug) {
            switch (String(slug || '').toLowerCase()) {
                case 'home':
                    return 'index-3.html';
                case 'shop':
                    return 'shop-left-sidebar.html';
                case 'product':
                    return 'shop-product-detail.html';
                case 'cart':
                    return 'shop-cart.html';
                case 'checkout':
                    return 'checkout.html';
                case 'login':
                    return 'login.html';
                case 'account':
                case 'orders':
                    return 'my-account.html';
                case 'order':
                    return 'order-completed.html';
                case 'contact':
                    return 'contact.html';
                default:
                    return 'index-3.html';
            }
        }

        function pageUrlForSlug(slug, extraParams) {
            var params = new URLSearchParams();
            params.set('site', String(siteId));
            params.set('slug', String(slug || 'home'));

            var extras = extraParams && typeof extraParams === 'object' ? extraParams : {};
            Object.keys(extras).forEach(function (key) {
                if (extras[key] == null || extras[key] === '') {
                    return;
                }
                params.set(key, String(extras[key]));
            });

            return fileForSlug(slug) + '?' + params.toString();
        }

        function resolveErrorMessage(error, fallback) {
            if (error && error.payload && typeof error.payload.error === 'string' && error.payload.error.trim() !== '') {
                return error.payload.error;
            }

            return fallback;
        }

        function getProductDetailUrl(product) {
            var extras = {};
            if (product && product.slug) {
                extras.product_slug = String(product.slug);
            }
            if (product && product.id) {
                extras.product_id = String(product.id);
            }

            return pageUrlForSlug('product', extras);
        }

        function resolveProductPrice(product) {
            if (!product || typeof product !== 'object') {
                return formatMoney(0, 'GEL');
            }

            return formatMoney(product.price, product.currency || 'GEL');
        }

        function syncMiniCartWidgets(cart) {
            var items = Array.isArray(cart && cart.items) ? cart.items : [];
            var totalQuantity = items.reduce(function (carry, item) {
                return carry + (Number.parseInt(String(item.quantity || 0), 10) || 0);
            }, 0);

            document.querySelectorAll('.cart_count').forEach(function (node) {
                node.textContent = String(totalQuantity);
            });

            var miniCartBoxes = document.querySelectorAll('.cart_box, [data-webby-ecommerce-cart]');
            miniCartBoxes.forEach(function (box) {
                if (!box.classList.contains('cart_box') && box.querySelector('.shop_cart_table')) {
                    return;
                }

                var listNode = box.querySelector('.cart_list');
                var totalNode = box.querySelector('.cart_total .cart_price');
                var buttonsNode = box.querySelector('.cart_buttons');

                if (!listNode || !totalNode || !buttonsNode) {
                    return;
                }

                if (items.length === 0) {
                    listNode.innerHTML = '<li class="text-center py-2">' + escapeHtml('Cart is empty') + '</li>';
                    totalNode.textContent = formatMoney(0, (cart && cart.currency) || 'GEL');
                } else {
                    listNode.innerHTML = items.slice(0, 5).map(function (item) {
                        var image = item.meta_json && item.meta_json.image_url ? item.meta_json.image_url : '';
                        return '<li>'
                            + '<a href="#" class="item_remove" data-webu-remove-item="' + escapeHtml(item.id) + '"><i class="ion-close"></i></a>'
                            + '<a href="' + escapeHtml(item.product_url || '#') + '">'
                            + (image ? '<img src="' + escapeHtml(image) + '" alt="' + escapeHtml(item.name || 'item') + '">' : '')
                            + escapeHtml(item.name || 'Item')
                            + '</a>'
                            + '<span class="cart_quantity">' + escapeHtml(item.quantity || 1) + ' x <span class="cart_amount">' + escapeHtml(formatMoney(item.unit_price, cart.currency || 'GEL')) + '</span></span>'
                            + '</li>';
                    }).join('');
                    totalNode.textContent = formatMoney(cart.grand_total, cart.currency || 'GEL');
                }

                listNode.querySelectorAll('[data-webu-remove-item]').forEach(function (button) {
                    if (button.getAttribute('data-webu-remove-bound') === '1') {
                        return;
                    }

                    button.setAttribute('data-webu-remove-bound', '1');
                    button.addEventListener('click', function (event) {
                        event.preventDefault();
                        var itemId = button.getAttribute('data-webu-remove-item');
                        if (!itemId) {
                            return;
                        }

                        removeCartItem(cart.id, itemId).then(function (response) {
                            if (response && response.cart) {
                                syncMiniCartWidgets(response.cart);
                            }
                        }).catch(function (error) {
                            console.warn('[webu-theme-runtime] mini cart remove failed', error);
                        });
                    });
                });

                var viewCart = buttonsNode.querySelector('.view-cart');
                var checkoutButton = buttonsNode.querySelector('.checkout');
                if (viewCart) {
                    viewCart.setAttribute('href', pageUrlForSlug('cart'));
                }
                if (checkoutButton) {
                    checkoutButton.setAttribute('href', pageUrlForSlug('checkout'));
                }
            });
        }

        function getOrCreateCart() {
            return getCart().then(function (payload) {
                if (payload && payload.cart) {
                    return payload.cart;
                }

                throw new Error('CART_NOT_AVAILABLE');
            });
        }

        function addProductToCart(productId, quantity) {
            var amount = Number.parseInt(String(quantity || 1), 10);
            if (!Number.isFinite(amount) || amount <= 0) {
                amount = 1;
            }

            return getCart()
                .then(function (cartPayload) {
                    var cartId = cartPayload && cartPayload.cart ? cartPayload.cart.id : null;
                    if (!cartId) {
                        throw new Error('CART_NOT_AVAILABLE');
                    }

                    return addCartItem(cartId, {
                        product_id: productId,
                        quantity: amount
                    });
                })
                .then(function (response) {
                    if (response && response.cart) {
                        syncMiniCartWidgets(response.cart);
                    }

                    return response;
                });
        }

        function findProductById(productId) {
            if (!Array.isArray(state.productsCache)) {
                return null;
            }

            for (var i = 0; i < state.productsCache.length; i++) {
                var candidate = state.productsCache[i];
                if (String(candidate.id) === String(productId)) {
                    return candidate;
                }
            }

            return null;
        }

        function findProductFromElement(element) {
            var productId = element.getAttribute('data-webu-product-id')
                || element.getAttribute('data-webu-add-cart')
                || (element.closest('[data-webu-product-id]') ? element.closest('[data-webu-product-id]').getAttribute('data-webu-product-id') : null);

            if (productId) {
                var fromId = findProductById(productId);
                if (fromId) {
                    return fromId;
                }
            }

            var productSlug = element.getAttribute('data-webu-product-slug')
                || (element.closest('[data-webu-product-slug]') ? element.closest('[data-webu-product-slug]').getAttribute('data-webu-product-slug') : null);

            if (productSlug && Array.isArray(state.productsCache)) {
                for (var i = 0; i < state.productsCache.length; i++) {
                    var candidate = state.productsCache[i];
                    if (String(candidate.slug || '') === String(productSlug)) {
                        return candidate;
                    }
                }
            }

            return state.currentProduct;
        }

        function bindAddToCartButtons(scope) {
            var root = scope || document;
            var selectors = ['[data-webu-add-cart]', '.btn-addtocart', '.add-to-cart a'];
            root.querySelectorAll(selectors.join(', ')).forEach(function (button) {
                if (button.getAttribute('data-webu-cart-bound') === '1') {
                    return;
                }

                button.setAttribute('data-webu-cart-bound', '1');
                button.addEventListener('click', function (event) {
                    event.preventDefault();

                    var explicitId = button.getAttribute('data-webu-add-cart');
                    var quantityInput = button.closest('.cart_extra') ? button.closest('.cart_extra').querySelector('input.qty') : null;
                    var quantity = quantityInput ? Number.parseInt(String(quantityInput.value || '1'), 10) : 1;
                    var product = explicitId ? findProductById(explicitId) : findProductFromElement(button);
                    var productId = explicitId || (product ? product.id : null);

                    if (!productId) {
                        console.warn('[webu-theme-runtime] product id not resolved for add-to-cart');
                        return;
                    }

                    addProductToCart(productId, quantity)
                        .catch(function (error) {
                            console.warn('[webu-theme-runtime] add to cart failed', error);
                        });
                });
            });
        }

        function patchProductCard(card, product) {
            if (!card || !product) {
                return;
            }

            card.setAttribute('data-webu-product-id', String(product.id));
            if (product.slug) {
                card.setAttribute('data-webu-product-slug', String(product.slug));
            }

            var productUrl = getProductDetailUrl(product);
            var imageNode = card.querySelector('.product_img img, img');
            if (imageNode && product.primary_image_url) {
                imageNode.setAttribute('src', product.primary_image_url);
                imageNode.setAttribute('alt', product.name || 'Product');
            }

            var titleLink = card.querySelector('.product_title a, h6 a, h5 a, a');
            if (titleLink) {
                titleLink.textContent = product.name || 'Product';
                titleLink.setAttribute('href', productUrl);
            }

            var imageLink = card.querySelector('.product_img > a');
            if (imageLink) {
                imageLink.setAttribute('href', productUrl);
            }

            var priceNode = card.querySelector('.product_price .price, .product_price span.price');
            if (priceNode) {
                priceNode.textContent = resolveProductPrice(product);
            }

            var compareNode = card.querySelector('.product_price del');
            if (compareNode) {
                if (product.compare_at_price) {
                    compareNode.textContent = formatMoney(product.compare_at_price, product.currency || 'GEL');
                    compareNode.style.removeProperty('display');
                } else {
                    compareNode.style.display = 'none';
                }
            }

            var descNode = card.querySelector('.pr_desc p');
            if (descNode) {
                descNode.textContent = product.short_description || '';
            }

            card.querySelectorAll('.add-to-cart a, .btn-addtocart').forEach(function (button) {
                button.setAttribute('data-webu-add-cart', String(product.id));
                if (product.slug) {
                    button.setAttribute('data-webu-product-slug', String(product.slug));
                }
            });
        }

        function renderProducts(container) {
            listProductsCached({ limit: 24 })
                .then(function (response) {
                    var products = Array.isArray(response.products) ? response.products : [];
                    if (products.length === 0) {
                        container.innerHTML = '<p>No products available.</p>';
                        return;
                    }

                    var existingCards = container.querySelectorAll('.product, .product_box');
                    if (existingCards.length > 0) {
                        container.querySelectorAll('[data-webu-generated-product="1"]').forEach(function (node) {
                            node.remove();
                        });

                        var templateCard = existingCards[0];
                        var templateHolder = templateCard ? (templateCard.closest('.col-lg-3, .col-md-4, .col-6, .col-sm-6, .col') || templateCard) : null;

                        products.forEach(function (product, index) {
                            var card = index < existingCards.length ? existingCards[index] : null;
                            if (!card && templateHolder) {
                                var generatedHolder = templateHolder.cloneNode(true);
                                generatedHolder.setAttribute('data-webu-generated-product', '1');
                                generatedHolder.style.removeProperty('display');
                                container.appendChild(generatedHolder);
                                card = generatedHolder.matches('.product, .product_box')
                                    ? generatedHolder
                                    : generatedHolder.querySelector('.product, .product_box');
                            }

                            if (!card) {
                                return;
                            }

                            var holder = card.closest('.col-lg-3, .col-md-4, .col-6, .col-sm-6, .col');
                            if (holder) {
                                holder.style.removeProperty('display');
                            } else {
                                card.style.removeProperty('display');
                            }

                            patchProductCard(card, product);
                        });

                        existingCards.forEach(function (card, index) {
                            if (index < products.length) {
                                return;
                            }

                            var holder = card.closest('.col-lg-3, .col-md-4, .col-6, .col-sm-6, .col');
                            if (holder) {
                                holder.style.display = 'none';
                            } else {
                                card.style.display = 'none';
                            }
                        });

                        bindAddToCartButtons(container);
                        return;
                    }

                    container.innerHTML = products.map(function (product) {
                        var image = product.primary_image_url || '';
                        return '<article class="webu-product-card" data-webu-product-id="' + escapeHtml(product.id) + '" data-webu-product-slug="' + escapeHtml(product.slug || '') + '">'
                            + (image ? '<a href="' + escapeHtml(getProductDetailUrl(product)) + '"><img src="' + escapeHtml(image) + '" alt="' + escapeHtml(product.name || 'Product') + '" loading="lazy" /></a>' : '')
                            + '<h4>' + escapeHtml(product.name || 'Product') + '</h4>'
                            + '<p>' + escapeHtml(product.short_description || '') + '</p>'
                            + '<strong>' + escapeHtml(resolveProductPrice(product)) + '</strong>'
                            + '<button type="button" data-webu-add-cart="' + escapeHtml(product.id) + '">Add to cart</button>'
                            + '</article>';
                    }).join('');

                    bindAddToCartButtons(container);
                })
                .catch(function (error) {
                    container.innerHTML = '<p>Failed to load products.</p>';
                    console.warn('[webu-theme-runtime] listProducts failed', error);
                });
        }

        function renderCart(container) {
            getCart()
                .then(function (response) {
                    var cart = response && response.cart ? response.cart : null;
                    if (!cart) {
                        container.innerHTML = '<p>Cart is empty.</p>';
                        return;
                    }

                    syncMiniCartWidgets(cart);

                    var items = Array.isArray(cart.items) ? cart.items : [];
                    if (items.length === 0) {
                        container.innerHTML = '<p>Cart is empty.</p>';
                        return;
                    }

                    if (container.classList.contains('cart_box')) {
                        return;
                    }

                    container.innerHTML = '<div class="webu-cart-items">'
                        + items.map(function (item) {
                            return '<div class="webu-cart-item"><span>'
                                + escapeHtml(item.name || 'Item')
                                + '</span><span>x'
                                + escapeHtml(item.quantity || 1)
                                + '</span><span>'
                                + escapeHtml(formatMoney(item.line_total, cart.currency || 'GEL'))
                                + '</span></div>';
                        }).join('')
                        + '<div class="webu-cart-total"><strong>Total: '
                        + escapeHtml(formatMoney(cart.grand_total, cart.currency || 'GEL'))
                        + '</strong></div>'
                        + '</div>';
                })
                .catch(function (error) {
                    container.innerHTML = '<p>Failed to load cart.</p>';
                    console.warn('[webu-theme-runtime] getCart failed', error);
                });
        }

        function renderCartPage() {
            var tableBody = document.querySelector('.shop_cart_table table tbody');
            if (!tableBody) {
                return;
            }

            getCart()
                .then(function (response) {
                    var cart = response && response.cart ? response.cart : null;
                    if (!cart) {
                        return;
                    }

                    syncMiniCartWidgets(cart);
                    var items = Array.isArray(cart.items) ? cart.items : [];

                    tableBody.innerHTML = items.map(function (item) {
                        var unitPrice = formatMoney(item.unit_price, cart.currency || 'GEL');
                        var lineTotal = formatMoney(item.line_total, cart.currency || 'GEL');
                        var image = (item.meta_json && item.meta_json.image_url) ? item.meta_json.image_url : '';

                        return '<tr data-webu-cart-item-id="' + escapeHtml(item.id) + '">'
                            + '<td class="product-thumbnail"><a href="' + escapeHtml(item.product_url || '#') + '">' + (image ? '<img src="' + escapeHtml(image) + '" alt="' + escapeHtml(item.name || 'Item') + '">' : '') + '</a></td>'
                            + '<td class="product-name" data-title="Product"><a href="' + escapeHtml(item.product_url || '#') + '">' + escapeHtml(item.name || 'Item') + '</a></td>'
                            + '<td class="product-price" data-title="Price">' + escapeHtml(unitPrice) + '</td>'
                            + '<td class="product-quantity" data-title="Quantity"><div class="quantity"><input type="button" value="-" class="minus"><input type="text" name="quantity" value="' + escapeHtml(item.quantity || 1) + '" title="Qty" class="qty" size="4"><input type="button" value="+" class="plus"></div></td>'
                            + '<td class="product-subtotal" data-title="Total">' + escapeHtml(lineTotal) + '</td>'
                            + '<td class="product-remove" data-title="Remove"><a href="#" data-webu-remove-item="' + escapeHtml(item.id) + '"><i class="ti-close"></i></a></td>'
                            + '</tr>';
                    }).join('');

                    var totalNodes = document.querySelectorAll('.cart_total_amount');
                    if (totalNodes.length >= 3) {
                        totalNodes[0].textContent = formatMoney(cart.subtotal, cart.currency || 'GEL');
                        totalNodes[1].textContent = toNumber(cart.shipping_total) > 0
                            ? formatMoney(cart.shipping_total, cart.currency || 'GEL')
                            : 'Free Shipping';
                        var totalStrong = totalNodes[2].querySelector('strong');
                        if (totalStrong) {
                            totalStrong.textContent = formatMoney(cart.grand_total, cart.currency || 'GEL');
                        } else {
                            totalNodes[2].textContent = formatMoney(cart.grand_total, cart.currency || 'GEL');
                        }
                    }

                    var proceedButton = document.querySelector('a.btn.btn-fill-out');
                    if (proceedButton && proceedButton.textContent && proceedButton.textContent.toLowerCase().indexOf('checkout') !== -1) {
                        proceedButton.setAttribute('href', 'checkout.html?site=' + encodeURIComponent(String(siteId)) + '&slug=checkout&cart_id=' + encodeURIComponent(String(cart.id)));
                    }

                    tableBody.querySelectorAll('[data-webu-remove-item]').forEach(function (button) {
                        button.addEventListener('click', function (event) {
                            event.preventDefault();
                            var itemId = button.getAttribute('data-webu-remove-item');
                            if (!itemId) {
                                return;
                            }

                            removeCartItem(cart.id, itemId).then(function () {
                                renderCartPage();
                            }).catch(function (error) {
                                console.warn('[webu-theme-runtime] remove cart item failed', error);
                            });
                        });
                    });

                    tableBody.querySelectorAll('tr').forEach(function (row) {
                        var itemId = row.getAttribute('data-webu-cart-item-id');
                        if (!itemId) {
                            return;
                        }

                        var minus = row.querySelector('.minus');
                        var plus = row.querySelector('.plus');
                        var qtyInput = row.querySelector('input.qty');

                        function applyQuantity(delta) {
                            var current = Number.parseInt(String(qtyInput && qtyInput.value ? qtyInput.value : '1'), 10) || 1;
                            var next = Math.max(1, current + delta);
                            if (qtyInput) {
                                qtyInput.value = String(next);
                            }

                            updateCartItem(cart.id, itemId, { quantity: next })
                                .then(function () {
                                    renderCartPage();
                                })
                                .catch(function (error) {
                                    console.warn('[webu-theme-runtime] update cart quantity failed', error);
                                });
                        }

                        if (minus) {
                            minus.addEventListener('click', function (event) {
                                event.preventDefault();
                                applyQuantity(-1);
                            });
                        }

                        if (plus) {
                            plus.addEventListener('click', function (event) {
                                event.preventDefault();
                                applyQuantity(1);
                            });
                        }
                    });
                })
                .catch(function (error) {
                    console.warn('[webu-theme-runtime] render cart page failed', error);
                });
        }

        function collectCheckoutFormSnapshot(formNode) {
            var form = formNode || document.querySelector('.col-md-6 form');
            var firstName = form ? (form.querySelector('input[name="fname"]') ? form.querySelector('input[name="fname"]').value.trim() : '') : '';
            var lastName = form ? (form.querySelector('input[name="lname"]') ? form.querySelector('input[name="lname"]').value.trim() : '') : '';
            var customerName = (firstName + ' ' + lastName).trim();
            var customerEmail = form ? (form.querySelector('input[name="email"]') ? form.querySelector('input[name="email"]').value.trim() : '') : '';
            var customerPhone = form ? (form.querySelector('input[name="phone"]') ? form.querySelector('input[name="phone"]').value.trim() : '') : '';
            var countrySelect = form ? form.querySelector('select[name="countrys"], select[name="country"], select[name="countries"]') : null;
            var notesNode = form ? form.querySelector('textarea, textarea[name="notes"]') : document.querySelector('textarea');
            var billingAddress = {
                address_1: form ? (form.querySelector('input[name="billing_address"]') ? form.querySelector('input[name="billing_address"]').value.trim() : '') : '',
                address_2: form ? (form.querySelector('input[name="billing_address2"]') ? form.querySelector('input[name="billing_address2"]').value.trim() : '') : '',
                city: form ? (form.querySelector('input[name="city"]') ? form.querySelector('input[name="city"]').value.trim() : '') : '',
                state: form ? (form.querySelector('input[name="state"]') ? form.querySelector('input[name="state"]').value.trim() : '') : '',
                zip: form ? (form.querySelector('input[name="zipcode"]') ? form.querySelector('input[name="zipcode"]').value.trim() : '') : '',
                country: countrySelect ? String(countrySelect.value || '').trim() : ''
            };

            return {
                customerName: customerName,
                customerEmail: customerEmail,
                customerPhone: customerPhone,
                billingAddress: billingAddress,
                notes: notesNode ? String(notesNode.value || '').trim() : ''
            };
        }

        function renderCheckoutOrderTable(orderTable, cart) {
            var tbody = orderTable ? orderTable.querySelector('tbody') : null;
            var tfoot = orderTable ? orderTable.querySelector('tfoot') : null;
            if (!cart) {
                return;
            }

            if (tbody) {
                var items = Array.isArray(cart.items) ? cart.items : [];
                tbody.innerHTML = items.map(function (item) {
                    return '<tr><td>' + escapeHtml(item.name || 'Item') + ' <span class="product-qty">x ' + escapeHtml(item.quantity || 1) + '</span></td><td>' + escapeHtml(formatMoney(item.line_total, cart.currency || 'GEL')) + '</td></tr>';
                }).join('');
            }

            if (tfoot) {
                var tfootRows = tfoot.querySelectorAll('tr');
                if (tfootRows.length >= 3) {
                    var subtotalCell = tfootRows[0].querySelector('td');
                    var shippingCell = tfootRows[1].querySelector('td');
                    var totalCell = tfootRows[2].querySelector('td');
                    if (subtotalCell) {
                        subtotalCell.textContent = formatMoney(cart.subtotal, cart.currency || 'GEL');
                    }
                    if (shippingCell) {
                        shippingCell.textContent = toNumber(cart.shipping_total) > 0
                            ? formatMoney(cart.shipping_total, cart.currency || 'GEL')
                            : 'Free Shipping';
                    }
                    if (totalCell) {
                        totalCell.textContent = formatMoney(cart.grand_total, cart.currency || 'GEL');
                    }
                }
            }
        }

        function ensureCheckoutShippingContainer(orderReview) {
            var existing = orderReview.querySelector('[data-webu-shipping-options]');
            if (existing) {
                return existing;
            }

            var paymentMethod = orderReview.querySelector('.payment_method');
            var container = document.createElement('div');
            container.className = 'shipping_method mt-3';
            container.setAttribute('data-webu-shipping-options', '1');
            container.innerHTML = '<div class="heading_s1"><h4>Shipping</h4></div><div class="payment_option"><p class="payment-text">Shipping options will appear after cart load.</p></div>';

            if (paymentMethod && paymentMethod.parentNode) {
                paymentMethod.parentNode.insertBefore(container, paymentMethod);
            } else {
                orderReview.appendChild(container);
            }

            return container;
        }

        function renderCheckoutPage() {
            var orderReview = document.querySelector('.order_review');
            if (!orderReview) {
                return;
            }

            var placeOrderButton = orderReview.querySelector('a.btn.btn-fill-out.btn-block');
            var orderTable = orderReview.querySelector('.order_table table');
            if (!placeOrderButton || !orderTable) {
                return;
            }

            var checkoutState = {
                cart: null,
                shipping: {
                    provider: null,
                    rateId: null
                }
            };

            function setCheckoutBusy(isBusy) {
                if (isBusy) {
                    placeOrderButton.classList.add('disabled');
                    placeOrderButton.setAttribute('aria-disabled', 'true');
                    placeOrderButton.textContent = 'Processing...';
                    return;
                }

                placeOrderButton.classList.remove('disabled');
                placeOrderButton.removeAttribute('aria-disabled');
                placeOrderButton.textContent = 'Place Order';
            }

            function resolveSelectedPayment() {
                var selectedPayment = orderReview.querySelector('input[name="webu_payment_provider"]:checked');
                var selectedValue = selectedPayment ? String(selectedPayment.value || 'manual|full') : 'manual|full';
                var selectedParts = selectedValue.split('|');
                return {
                    provider: selectedParts[0] || 'manual',
                    mode: selectedParts[1] || 'full'
                };
            }

            function resolveSelectedShipping() {
                var selectedShipping = orderReview.querySelector('input[name="webu_shipping_rate"]:checked');
                if (!selectedShipping) {
                    return {
                        provider: checkoutState.shipping.provider,
                        rateId: checkoutState.shipping.rateId
                    };
                }

                var rawValue = String(selectedShipping.value || '');
                var parts = rawValue.split('|');
                return {
                    provider: parts[0] || null,
                    rateId: parts[1] || null
                };
            }

            function renderPaymentOptions(providers) {
                var paymentContainer = orderReview.querySelector('.payment_option');
                if (!paymentContainer) {
                    return;
                }

                var normalizedProviders = Array.isArray(providers) && providers.length > 0
                    ? providers
                    : [{ slug: 'manual', name: 'Manual', description: 'Manual or offline payment collection.', modes: ['full'] }];

                paymentContainer.innerHTML = normalizedProviders.map(function (provider, providerIndex) {
                    var modes = Array.isArray(provider.modes) && provider.modes.length > 0 ? provider.modes : ['full'];
                    return modes.map(function (mode, modeIndex) {
                        var value = String(provider.slug) + '|' + String(mode);
                        var inputId = 'webu-payment-' + providerIndex + '-' + modeIndex;
                        var checked = providerIndex === 0 && modeIndex === 0 ? ' checked="checked"' : '';
                        var modeLabel = mode === 'installment' ? 'Installment' : 'Full Payment';

                        return '<div class="custome-radio">'
                            + '<input class="form-check-input" type="radio" name="webu_payment_provider" id="' + escapeHtml(inputId) + '" value="' + escapeHtml(value) + '"' + checked + '>'
                            + '<label class="form-check-label" for="' + escapeHtml(inputId) + '">' + escapeHtml(provider.name || provider.slug || 'Manual') + ' · ' + escapeHtml(modeLabel) + '</label>'
                            + (provider.description ? '<p class="payment-text">' + escapeHtml(provider.description) + '</p>' : '')
                            + '</div>';
                    }).join('');
                }).join('');
            }

            function renderShippingOptions(cart, billingAddress) {
                var shippingContainer = ensureCheckoutShippingContainer(orderReview);
                var optionsHost = shippingContainer.querySelector('.payment_option') || shippingContainer;
                optionsHost.innerHTML = '<p class="payment-text">Loading shipping options...</p>';

                return getShippingOptions(cart.id, {
                    shipping_address_json: billingAddress
                }).then(function (shippingResponse) {
                    var shippingPayload = shippingResponse && shippingResponse.shipping ? shippingResponse.shipping : {};
                    var providers = Array.isArray(shippingPayload.providers) ? shippingPayload.providers : [];
                    var selectedRate = shippingPayload.selected_rate && typeof shippingPayload.selected_rate === 'object'
                        ? shippingPayload.selected_rate
                        : null;
                    var rates = [];

                    providers.forEach(function (providerPayload) {
                        var providerRates = Array.isArray(providerPayload.rates) ? providerPayload.rates : [];
                        providerRates.forEach(function (rate) {
                            rates.push({
                                provider: providerPayload.provider || rate.provider,
                                provider_name: providerPayload.name || rate.provider_name || providerPayload.provider || 'Shipping',
                                service_name: rate.service_name || 'Standard',
                                rate_id: rate.rate_id,
                                amount: rate.amount,
                                currency: rate.currency || (cart.currency || 'GEL'),
                                estimated_days: rate.estimated_days || null
                            });
                        });
                    });

                    if (rates.length === 0) {
                        checkoutState.shipping.provider = null;
                        checkoutState.shipping.rateId = null;
                        optionsHost.innerHTML = '<p class="payment-text">Shipping will be confirmed manually.</p>';
                        return;
                    }

                    var selectedKey = selectedRate
                        ? String(selectedRate.provider || '') + '|' + String(selectedRate.rate_id || '')
                        : String(rates[0].provider || '') + '|' + String(rates[0].rate_id || '');

                    optionsHost.innerHTML = rates.map(function (rate, index) {
                        var rateKey = String(rate.provider || '') + '|' + String(rate.rate_id || '');
                        var inputId = 'webu-shipping-' + index;
                        var checked = rateKey === selectedKey ? ' checked="checked"' : '';
                        var etaLabel = rate.estimated_days && typeof rate.estimated_days === 'object'
                            ? ' (' + String(rate.estimated_days.min || 1) + '-' + String(rate.estimated_days.max || rate.estimated_days.min || 1) + ' days)'
                            : '';

                        return '<div class="custome-radio">'
                            + '<input class="form-check-input" type="radio" name="webu_shipping_rate" id="' + escapeHtml(inputId) + '" value="' + escapeHtml(rateKey) + '"' + checked + '>'
                            + '<label class="form-check-label" for="' + escapeHtml(inputId) + '">' + escapeHtml(rate.provider_name || 'Shipping') + ' · ' + escapeHtml(rate.service_name || 'Standard') + etaLabel + '</label>'
                            + '<p class="payment-text">' + escapeHtml(formatMoney(rate.amount, rate.currency || (cart.currency || 'GEL'))) + '</p>'
                            + '</div>';
                    }).join('');

                    checkoutState.shipping.provider = rates[0].provider || null;
                    checkoutState.shipping.rateId = rates[0].rate_id || null;

                    function applySelectedShipping() {
                        var selected = resolveSelectedShipping();
                        if (!selected.provider || !selected.rateId) {
                            return Promise.resolve();
                        }

                        checkoutState.shipping.provider = selected.provider;
                        checkoutState.shipping.rateId = selected.rateId;

                        return updateShippingSelection(cart.id, {
                            shipping_provider: selected.provider,
                            shipping_rate_id: selected.rateId,
                            shipping_address_json: billingAddress
                        }).then(function (shippingUpdateResponse) {
                            if (shippingUpdateResponse && shippingUpdateResponse.cart) {
                                checkoutState.cart = shippingUpdateResponse.cart;
                                renderCheckoutOrderTable(orderTable, checkoutState.cart);
                                syncMiniCartWidgets(checkoutState.cart);
                            }
                        }).catch(function (error) {
                            console.warn('[webu-theme-runtime] shipping update failed', error);
                        });
                    }

                    optionsHost.querySelectorAll('input[name="webu_shipping_rate"]').forEach(function (radio) {
                        if (radio.getAttribute('data-webu-shipping-bound') === '1') {
                            return;
                        }

                        radio.setAttribute('data-webu-shipping-bound', '1');
                        radio.addEventListener('change', function () {
                            applySelectedShipping();
                        });
                    });

                    return applySelectedShipping();
                }).catch(function (error) {
                    checkoutState.shipping.provider = null;
                    checkoutState.shipping.rateId = null;
                    optionsHost.innerHTML = '<p class="payment-text">Shipping options are unavailable.</p>';
                    console.warn('[webu-theme-runtime] shipping options failed', error);
                });
            }

            function bootstrapCheckout() {
                var cartIdFromQuery = query.get('cart_id');
                var cartPromise = cartIdFromQuery ? getCart(cartIdFromQuery) : getCart();

                return Promise.all([cartPromise, getPaymentOptions().catch(function () { return null; })])
                    .then(function (parts) {
                        var cartPayload = parts[0] || {};
                        var optionsPayload = parts[1] || {};
                        var cart = cartPayload.cart || null;
                        var providers = Array.isArray(optionsPayload.providers) ? optionsPayload.providers : [];
                        if (!cart) {
                            return;
                        }

                        checkoutState.cart = cart;
                        renderCheckoutOrderTable(orderTable, cart);
                        syncMiniCartWidgets(cart);
                        renderPaymentOptions(providers);

                        var formSnapshot = collectCheckoutFormSnapshot(document.querySelector('.col-md-6 form'));
                        return renderShippingOptions(cart, formSnapshot.billingAddress);
                    })
                    .catch(function (error) {
                        console.warn('[webu-theme-runtime] checkout page binding failed', error);
                    });
            }

            if (placeOrderButton.getAttribute('data-webu-checkout-bound') !== '1') {
                placeOrderButton.setAttribute('data-webu-checkout-bound', '1');
                placeOrderButton.addEventListener('click', function (event) {
                    event.preventDefault();
                    if (placeOrderButton.classList.contains('disabled')) {
                        return;
                    }

                    var activeCart = checkoutState.cart;
                    if (!activeCart || !activeCart.id) {
                        console.warn('[webu-theme-runtime] cart is missing for checkout');
                        return;
                    }

                    setCheckoutBusy(true);

                    var formSnapshot = collectCheckoutFormSnapshot(document.querySelector('.col-md-6 form'));
                    var paymentSelection = resolveSelectedPayment();
                    var shippingSelection = resolveSelectedShipping();

                    checkout(activeCart.id, {
                        customer_name: formSnapshot.customerName || null,
                        customer_email: formSnapshot.customerEmail || null,
                        customer_phone: formSnapshot.customerPhone || null,
                        billing_address_json: formSnapshot.billingAddress,
                        shipping_address_json: formSnapshot.billingAddress,
                        shipping_provider: shippingSelection.provider || null,
                        shipping_rate_id: shippingSelection.rateId || null,
                        notes: formSnapshot.notes || null
                    }).then(function (checkoutResponse) {
                        var order = checkoutResponse && checkoutResponse.order ? checkoutResponse.order : null;
                        if (!order || !order.id) {
                            throw new Error('ORDER_NOT_CREATED');
                        }

                        return startPayment(order.id, {
                            provider: paymentSelection.provider,
                            method: paymentSelection.mode,
                            is_installment: paymentSelection.mode === 'installment'
                        }).then(function (paymentResponse) {
                            return {
                                order: order,
                                payment: paymentResponse
                            };
                        });
                    }).then(function (result) {
                        var session = result && result.payment ? result.payment.payment_session : null;
                        if (session && session.redirect_url) {
                            window.location.href = session.redirect_url;
                            return;
                        }

                        window.location.href = pageUrlForSlug('order', {
                            order_id: String(result.order.id || '')
                        });
                    }).catch(function (error) {
                        console.warn('[webu-theme-runtime] checkout failed', error);
                        setCheckoutBusy(false);
                    });
                });
            }

            var billingForm = document.querySelector('.col-md-6 form');
            if (billingForm && billingForm.getAttribute('data-webu-shipping-watch-bound') !== '1') {
                billingForm.setAttribute('data-webu-shipping-watch-bound', '1');
                var watchedFields = billingForm.querySelectorAll('input[name="billing_address"], input[name="billing_address2"], input[name="city"], input[name="state"], input[name="zipcode"], select[name="countrys"], select[name="country"], select[name="countries"]');
                watchedFields.forEach(function (field) {
                    field.addEventListener('change', function () {
                        if (!checkoutState.cart || !checkoutState.cart.id) {
                            return;
                        }

                        var formSnapshot = collectCheckoutFormSnapshot(billingForm);
                        renderShippingOptions(checkoutState.cart, formSnapshot.billingAddress);
                    });
                });
            }

            bootstrapCheckout();
        }

        function syncNavigationAccountLinks(authPayload) {
            var isAuthenticated = !!(authPayload && authPayload.authenticated);
            var destination = isAuthenticated ? pageUrlForSlug('account') : pageUrlForSlug('login');
            var label = isAuthenticated ? 'ჩემი ანგარიში' : 'შესვლა';

            document.querySelectorAll('a').forEach(function (anchor) {
                if (!anchor || !anchor.querySelector('i.ti-user')) {
                    return;
                }

                anchor.setAttribute('href', destination);
                var labelNode = anchor.querySelector('span');
                if (labelNode) {
                    labelNode.textContent = label;
                }
            });
        }

        function mountAuthWidget(container) {
            if (!container || container.getAttribute('data-webu-auth-bound') === '1') {
                return;
            }

            container.setAttribute('data-webu-auth-bound', '1');

            var form = container.matches('form')
                ? container
                : (container.querySelector('.login_wrap form') || container.querySelector('form'));

            var statusNode = container.querySelector('[data-webu-auth-status]');
            if (!statusNode) {
                statusNode = document.createElement('div');
                statusNode.setAttribute('data-webu-auth-status', '1');
                statusNode.style.marginTop = '12px';
                statusNode.style.fontSize = '13px';
                statusNode.style.color = '#64748b';
                if (form && form.parentNode) {
                    form.parentNode.appendChild(statusNode);
                } else {
                    container.appendChild(statusNode);
                }
            }

            function setStatus(message, tone) {
                statusNode.textContent = message || '';
                statusNode.style.color = tone === 'error'
                    ? '#b91c1c'
                    : (tone === 'success' ? '#166534' : '#64748b');
            }

            customerMe()
                .then(function (payload) {
                    syncNavigationAccountLinks(payload);
                    if (payload && payload.authenticated) {
                        var customer = payload.customer && typeof payload.customer === 'object' ? payload.customer : {};
                        setStatus('Logged in as ' + String(customer.email || customer.name || 'customer'), 'success');
                    }
                })
                .catch(function () {
                    // keep silent - login form can still work
                });

            if (!form || form.getAttribute('data-webu-auth-submit-bound') === '1') {
                return;
            }

            form.setAttribute('data-webu-auth-submit-bound', '1');
            form.addEventListener('submit', function (event) {
                event.preventDefault();

                var emailInput = form.querySelector('input[name="email"]');
                var passwordInput = form.querySelector('input[name="password"]');
                var rememberInput = form.querySelector('input[type="checkbox"]');
                var email = emailInput ? String(emailInput.value || '').trim() : '';
                var password = passwordInput ? String(passwordInput.value || '') : '';
                var remember = !!(rememberInput && rememberInput.checked);

                if (email === '' || password === '') {
                    setStatus('Please provide email and password.', 'error');
                    return;
                }

                setStatus('Signing in...', 'muted');
                customerLogin({
                    email: email,
                    password: password,
                    remember: remember
                }).then(function (payload) {
                    syncNavigationAccountLinks(payload);
                    setStatus('Login successful. Redirecting...', 'success');
                    window.setTimeout(function () {
                        window.location.href = pageUrlForSlug('account');
                    }, 200);
                }).catch(function (error) {
                    setStatus(resolveErrorMessage(error, 'Login failed. Please verify credentials.'), 'error');
                });
            });
        }

        function mountAccountProfileWidget(container) {
            if (!container || container.getAttribute('data-webu-account-profile-bound') === '1') {
                return;
            }

            container.setAttribute('data-webu-account-profile-bound', '1');

            var statusNode = container.querySelector('[data-webu-account-profile-status]');
            if (!statusNode) {
                statusNode = document.createElement('div');
                statusNode.setAttribute('data-webu-account-profile-status', '1');
                statusNode.style.marginTop = '10px';
                statusNode.style.fontSize = '13px';
                statusNode.style.color = '#64748b';
                container.appendChild(statusNode);
            }

            function setStatus(message, tone) {
                statusNode.textContent = message || '';
                statusNode.style.color = tone === 'error'
                    ? '#b91c1c'
                    : (tone === 'success' ? '#166534' : '#64748b');
            }

            var accountForm = container.querySelector('#account-detail form')
                || container.querySelector('form[name="enq"]')
                || container.querySelector('form');
            var nameInput = accountForm ? accountForm.querySelector('input[name="name"]') : null;
            var displayNameInput = accountForm ? accountForm.querySelector('input[name="dname"]') : null;
            var emailInput = accountForm ? accountForm.querySelector('input[name="email"]') : null;

            customerMe()
                .then(function (payload) {
                    syncNavigationAccountLinks(payload);
                    if (!payload || !payload.authenticated) {
                        setStatus('Login required to view account details.', 'error');
                        return;
                    }

                    var customer = payload.customer && typeof payload.customer === 'object' ? payload.customer : {};
                    if (nameInput && String(nameInput.value || '').trim() === '') {
                        nameInput.value = String(customer.name || '');
                    }
                    if (displayNameInput && String(displayNameInput.value || '').trim() === '') {
                        displayNameInput.value = String(customer.name || '');
                    }
                    if (emailInput && String(emailInput.value || '').trim() === '') {
                        emailInput.value = String(customer.email || '');
                    }
                    setStatus('Account data loaded.', 'success');
                })
                .catch(function (error) {
                    setStatus(resolveErrorMessage(error, 'Failed to load account profile.'), 'error');
                });

            if (!accountForm || accountForm.getAttribute('data-webu-account-profile-submit-bound') === '1') {
                return;
            }

            accountForm.setAttribute('data-webu-account-profile-submit-bound', '1');
            accountForm.addEventListener('submit', function (event) {
                event.preventDefault();

                var nextName = displayNameInput && String(displayNameInput.value || '').trim() !== ''
                    ? String(displayNameInput.value || '').trim()
                    : (nameInput ? String(nameInput.value || '').trim() : '');
                var nextEmail = emailInput ? String(emailInput.value || '').trim() : '';

                if (nextName === '' && nextEmail === '') {
                    setStatus('Please provide at least one field to update.', 'error');
                    return;
                }

                setStatus('Saving account details...', 'muted');
                customerMeUpdate({
                    name: nextName || null,
                    email: nextEmail || null
                }).then(function (payload) {
                    syncNavigationAccountLinks(payload);
                    setStatus('Account updated successfully.', 'success');
                }).catch(function (error) {
                    setStatus(resolveErrorMessage(error, 'Failed to update account profile.'), 'error');
                });
            });
        }

        function mountAccountSecurityWidget(container) {
            if (!container || container.getAttribute('data-webu-account-security-bound') === '1') {
                return;
            }

            container.setAttribute('data-webu-account-security-bound', '1');
            if (container.querySelector('[data-webu-account-security-note]')) {
                return;
            }

            var note = document.createElement('p');
            note.setAttribute('data-webu-account-security-note', '1');
            note.style.marginTop = '10px';
            note.style.fontSize = '12px';
            note.style.color = '#64748b';
            note.textContent = 'Security settings follow platform auth/session policies.';
            container.appendChild(note);
        }

        function mountOrdersListWidget(container) {
            if (!container || container.getAttribute('data-webu-orders-list-bound') === '1') {
                return;
            }

            container.setAttribute('data-webu-orders-list-bound', '1');

            var tableBody = container.querySelector('table tbody');
            var statusNode = container.querySelector('[data-webu-orders-status]');
            if (!statusNode) {
                statusNode = document.createElement('div');
                statusNode.setAttribute('data-webu-orders-status', '1');
                statusNode.style.marginTop = '10px';
                statusNode.style.fontSize = '13px';
                statusNode.style.color = '#64748b';
                container.appendChild(statusNode);
            }

            function setStatus(message, tone) {
                statusNode.textContent = message || '';
                statusNode.style.color = tone === 'error'
                    ? '#b91c1c'
                    : (tone === 'success' ? '#166534' : '#64748b');
            }

            setStatus('Loading orders...', 'muted');
            getOrders({ limit: 20, page: 1 })
                .then(function (response) {
                    var orders = Array.isArray(response && response.orders) ? response.orders : [];
                    if (orders.length === 0) {
                        if (tableBody) {
                            tableBody.innerHTML = '<tr><td colspan="5">No orders found.</td></tr>';
                        } else {
                            container.innerHTML = '<div class="card"><div class="card-body">No orders found.</div></div>';
                        }
                        setStatus('No orders found.', 'muted');
                        return;
                    }

                    if (tableBody) {
                        tableBody.innerHTML = orders.map(function (order) {
                            var createdAt = order && order.created_at ? String(order.created_at) : '';
                            var createdLabel = createdAt ? createdAt.slice(0, 10) : '-';
                            var itemCount = Number.parseInt(String(order && order.items_count ? order.items_count : 0), 10) || 0;
                            var total = formatMoney(order && order.grand_total ? order.grand_total : 0, order && order.currency ? order.currency : 'GEL');

                            return '<tr>'
                                + '<td>' + escapeHtml(String(order && order.order_number ? order.order_number : ('#' + String(order && order.id ? order.id : '')))) + '</td>'
                                + '<td>' + escapeHtml(createdLabel) + '</td>'
                                + '<td>' + escapeHtml(String(order && order.status ? order.status : 'pending')) + '</td>'
                                + '<td>' + escapeHtml(total + ' for ' + String(Math.max(1, itemCount)) + ' item(s)') + '</td>'
                                + '<td><a class="btn btn-fill-out btn-sm" href="' + escapeHtml(pageUrlForSlug('order', { order_id: order && order.id ? order.id : '' })) + '">View</a></td>'
                                + '</tr>';
                        }).join('');
                    } else {
                        container.innerHTML = orders.map(function (order) {
                            return '<a class="btn btn-line-fill btn-sm mb-2 me-2" href="' + escapeHtml(pageUrlForSlug('order', { order_id: order && order.id ? order.id : '' })) + '">'
                                + escapeHtml(String(order && order.order_number ? order.order_number : ('Order #' + String(order && order.id ? order.id : ''))))
                                + '</a>';
                        }).join('');
                    }

                    setStatus('Orders loaded.', 'success');
                })
                .catch(function (error) {
                    var isAuth = error && error.status === 401;
                    if (tableBody) {
                        tableBody.innerHTML = '<tr><td colspan="5">' + escapeHtml(isAuth ? 'Login required to view orders.' : 'Failed to load orders.') + '</td></tr>';
                    }
                    setStatus(resolveErrorMessage(error, isAuth ? 'Login required to view orders.' : 'Failed to load orders.'), isAuth ? 'muted' : 'error');
                    if (isAuth) {
                        window.setTimeout(function () {
                            window.location.href = pageUrlForSlug('login');
                        }, 600);
                    }
                });
        }

        function mountOrderDetailWidget(container) {
            if (!container || container.getAttribute('data-webu-order-detail-bound') === '1') {
                return;
            }

            container.setAttribute('data-webu-order-detail-bound', '1');

            var orderId = query.get('order_id') || query.get('id');
            if (!orderId) {
                container.innerHTML = '<p>Order id is missing.</p>';
                return;
            }

            container.innerHTML = '<p>Loading order...</p>';
            getOrder(orderId)
                .then(function (response) {
                    var order = response && response.order ? response.order : null;
                    if (!order || !order.id) {
                        throw new Error('ORDER_NOT_FOUND');
                    }

                    var items = Array.isArray(order.items) ? order.items : [];
                    var itemsMarkup = items.length === 0
                        ? '<li>No items in this order.</li>'
                        : items.map(function (item) {
                            var lineTotal = formatMoney(item && item.line_total ? item.line_total : 0, order.currency || 'GEL');
                            var quantity = Number.parseInt(String(item && item.quantity ? item.quantity : 1), 10) || 1;
                            return '<li>' + escapeHtml(String(item && item.name ? item.name : 'Item')) + ' × ' + escapeHtml(String(quantity)) + ' - ' + escapeHtml(lineTotal) + '</li>';
                        }).join('');

                    container.innerHTML = '<i class="fas fa-check-circle"></i>'
                        + '<div class="heading_s1"><h3>Order ' + escapeHtml(String(order.order_number || ('#' + order.id))) + '</h3></div>'
                        + '<p>Status: ' + escapeHtml(String(order.status || 'pending')) + '</p>'
                        + '<p>Total: ' + escapeHtml(formatMoney(order.grand_total || 0, order.currency || 'GEL')) + '</p>'
                        + '<ul style="text-align:left;display:inline-block;margin-top:12px;">' + itemsMarkup + '</ul>'
                        + '<div style="margin-top:16px;"><a href="' + escapeHtml(pageUrlForSlug('orders')) + '" class="btn btn-fill-out">Back to orders</a></div>';
                })
                .catch(function (error) {
                    var isAuth = error && error.status === 401;
                    container.innerHTML = '<p>' + escapeHtml(resolveErrorMessage(error, isAuth ? 'Login required to view order.' : 'Failed to load order.')) + '</p>';
                    if (isAuth) {
                        window.setTimeout(function () {
                            window.location.href = pageUrlForSlug('login');
                        }, 600);
                    }
                });
        }

        function renderProductDetailPage() {
            var detailContainer = document.querySelector('.product_description');
            if (!detailContainer) {
                return;
            }

            var requestedProductSlug = query.get('product_slug');
            var requestedProductId = query.get('product_id');
            var productPromise = requestedProductSlug
                ? getProduct(requestedProductSlug).then(function (response) { return response.product || null; })
                : requestedProductId
                    ? listProductsCached({ limit: 100 }).then(function (response) {
                        var products = Array.isArray(response.products) ? response.products : [];
                        for (var index = 0; index < products.length; index++) {
                            if (String(products[index].id) === String(requestedProductId)) {
                                return products[index];
                            }
                        }
                        return null;
                    })
                : listProductsCached({ limit: 1 }).then(function (response) {
                    return Array.isArray(response.products) && response.products.length > 0 ? response.products[0] : null;
                });

            productPromise
                .then(function (product) {
                    if (!product) {
                        return;
                    }

                    state.currentProduct = product;

                    var titleNode = detailContainer.querySelector('.product_title a, .product_title');
                    if (titleNode) {
                        titleNode.textContent = product.name || 'Product';
                    }

                    var priceNode = detailContainer.querySelector('.product_price .price');
                    if (priceNode) {
                        priceNode.textContent = resolveProductPrice(product);
                    }

                    var compareNode = detailContainer.querySelector('.product_price del');
                    if (compareNode) {
                        if (product.compare_at_price) {
                            compareNode.textContent = formatMoney(product.compare_at_price, product.currency || 'GEL');
                            compareNode.style.removeProperty('display');
                        } else {
                            compareNode.style.display = 'none';
                        }
                    }

                    var descriptionNode = detailContainer.querySelector('.pr_desc p');
                    if (descriptionNode) {
                        descriptionNode.textContent = product.short_description || '';
                    }

                    var imageNode = document.querySelector('#product_img');
                    if (imageNode && product.primary_image_url) {
                        imageNode.setAttribute('src', product.primary_image_url);
                        imageNode.setAttribute('alt', product.name || 'Product');
                    }

                    document.querySelectorAll('.btn-addtocart, .add-to-cart a').forEach(function (button) {
                        button.setAttribute('data-webu-add-cart', String(product.id));
                        if (product.slug) {
                            button.setAttribute('data-webu-product-slug', String(product.slug));
                        }
                    });

                    bindAddToCartButtons(document);
                })
                .catch(function (error) {
                    console.warn('[webu-theme-runtime] product detail binding failed', error);
                });
        }

        function refreshStorefrontBindings() {
            document.querySelectorAll('[data-webby-ecommerce-products]').forEach(renderProducts);
            document.querySelectorAll('[data-webby-ecommerce-cart]').forEach(renderCart);
            document.querySelectorAll('[data-webby-ecommerce-auth]').forEach(mountAuthWidget);
            document.querySelectorAll('[data-webby-ecommerce-account-profile]').forEach(mountAccountProfileWidget);
            document.querySelectorAll('[data-webby-ecommerce-account-security]').forEach(mountAccountSecurityWidget);
            document.querySelectorAll('[data-webby-ecommerce-orders-list]').forEach(mountOrdersListWidget);
            document.querySelectorAll('[data-webby-ecommerce-order-detail]').forEach(mountOrderDetailWidget);

            bindAddToCartButtons(document);

            var hasMiniCartWidgets = document.querySelectorAll('.cart_box, [data-webby-ecommerce-cart], .cart_count, .cart_quantity').length > 0;
            if (hasMiniCartWidgets || state.slug === 'cart' || state.slug === 'checkout') {
                getCart().then(function (response) {
                    if (response && response.cart) {
                        syncMiniCartWidgets(response.cart);
                    }
                }).catch(function () {
                    // Ignore initial cart bootstrap failures.
                });
            }

            if (state.slug === 'cart') {
                renderCartPage();
            }

            if (state.slug === 'checkout') {
                renderCheckoutPage();
            }

            if (state.slug === 'product') {
                renderProductDetailPage();
            }

            if (state.slug === 'login' && document.querySelectorAll('[data-webby-ecommerce-auth]').length === 0) {
                var authHost = document.querySelector('.login_register_wrap')
                    || document.querySelector('.login_wrap')
                    || document.querySelector('.login_form');
                if (authHost) {
                    mountAuthWidget(authHost);
                }
            }

            if (state.slug === 'account' && document.querySelectorAll('[data-webby-ecommerce-account-profile]').length === 0) {
                var accountHost = document.querySelector('.dashboard_content')
                    || document.querySelector('#account-detail')
                    || document.querySelector('.dashboard_menu');
                if (accountHost) {
                    mountAccountProfileWidget(accountHost);
                }
            }

            if (state.slug === 'orders' && document.querySelectorAll('[data-webby-ecommerce-orders-list]').length === 0) {
                var ordersHost = document.querySelector('#orders')
                    || document.querySelector('.dashboard_content')
                    || document.querySelector('.table-responsive');
                if (ordersHost) {
                    mountOrdersListWidget(ordersHost);
                }
            }

            if (state.slug === 'order' && document.querySelectorAll('[data-webby-ecommerce-order-detail]').length === 0) {
                var orderHost = document.querySelector('.order_complete')
                    || document.querySelector('.main_content .section .container');
                if (orderHost) {
                    mountOrderDetailWidget(orderHost);
                }
            }
        }

        window.WebbyEcommerce = {
            listProducts: listProducts,
            listProductsCached: listProductsCached,
            getProduct: getProduct,
            createCart: createCart,
            getCart: getCart,
            addCartItem: addCartItem,
            updateCartItem: updateCartItem,
            removeCartItem: removeCartItem,
            getPaymentOptions: getPaymentOptions,
            getShippingOptions: getShippingOptions,
            updateShipping: updateShippingSelection,
            checkout: checkout,
            startPayment: startPayment,
            customerMe: customerMe,
            customerLogin: customerLogin,
            customerMeUpdate: customerMeUpdate,
            getOrders: getOrders,
            getOrder: getOrder,
            mountAuthWidget: mountAuthWidget,
            mountAccountProfileWidget: mountAccountProfileWidget,
            mountAccountSecurityWidget: mountAccountSecurityWidget,
            mountOrdersListWidget: mountOrdersListWidget,
            mountOrderDetailWidget: mountOrderDetailWidget,
            getCartId: getCartId,
            refreshBindings: refreshStorefrontBindings,
            __siteId: siteId
        };

        window.WebuStorefront = window.WebbyEcommerce;
        window.WebuStorefrontBindings = {
            renderProducts: renderProducts,
            renderCartWidget: renderCart,
            renderCartPage: renderCartPage,
            renderCheckoutPage: renderCheckoutPage,
            renderProductDetailPage: renderProductDetailPage,
            mountAuthWidget: mountAuthWidget,
            mountAccountProfileWidget: mountAccountProfileWidget,
            mountOrdersListWidget: mountOrdersListWidget,
            mountOrderDetailWidget: mountOrderDetailWidget,
            bindAddToCartButtons: bindAddToCartButtons,
            refresh: refreshStorefrontBindings
        };
        refreshStorefrontBindings();
    }

    function bootstrapViaExistingWebbyCms() {
        if (!window.WebbyCms || typeof window.WebbyCms.onReady !== 'function') {
            return false;
        }

        window.WebbyCms.onReady(function (payload) {
            applyCmsPayload(payload);
            ensureEcommerceBridge(payload && payload.site_id ? payload.site_id : state.siteId);
        });

        if (typeof window.WebbyCms.getState === 'function') {
            var snapshot = window.WebbyCms.getState();
            if (snapshot) {
                applyCmsPayload(snapshot);
                ensureEcommerceBridge(snapshot.site_id || state.siteId);
                return true;
            }
        }

        if (typeof window.WebbyCms.refresh === 'function') {
            window.WebbyCms.refresh().catch(function (error) {
                console.warn('[webu-theme-runtime] WebbyCms refresh failed', error);
            });
        }

        return true;
    }

    function bootstrapViaPublicApi() {
        resolveSiteId()
            .then(function (siteId) {
                state.siteId = siteId;
                return loadPayloadViaPublicApi(siteId);
            })
            .then(function (payload) {
                applyCmsPayload(payload);
                ensureEcommerceBridge(payload.site_id || state.siteId);
            })
            .catch(function (error) {
                console.warn('[webu-theme-runtime] bootstrap failed', error);
            });
    }

    if (!bootstrapViaExistingWebbyCms()) {
        bootstrapViaPublicApi();
    }
})();
JS;
    }

    /**
     * @return array<string, string>
     */
    private function resolvePageMap(string $runtimeDir): array
    {
        $blueprintResolved = $this->resolvePageMapFromBlueprints($runtimeDir);
        if ($blueprintResolved !== []) {
            return $blueprintResolved;
        }

        $candidates = [
            'home' => ['index.html', 'index-2.html', 'index-3.html', 'index-4.html', 'index-5.html', 'index-6.html'],
            'shop' => ['shop-list.html', 'shop-left-sidebar.html', 'shop-right-sidebar.html'],
            'product' => ['shop-product-detail.html', 'shop-product-detail-left-sidebar.html', 'shop-product-detail-right-sidebar.html'],
            'cart' => ['shop-cart.html'],
            'checkout' => ['checkout.html'],
            'contact' => ['contact.html'],
        ];

        $resolved = [];
        foreach ($candidates as $pageKey => $files) {
            foreach ($files as $file) {
                if (is_file($runtimeDir.'/'.$file)) {
                    $resolved[$pageKey] = $file;
                    break;
                }
            }
        }

        if (! isset($resolved['home'])) {
            throw new \RuntimeException('Unable to resolve home page from runtime folder (missing index.html).');
        }

        $knownFiles = array_values($resolved);
        sort($knownFiles);
        foreach (glob($runtimeDir.'/*.html') ?: [] as $filePath) {
            if (! is_string($filePath) || ! is_file($filePath)) {
                continue;
            }

            $fileName = basename($filePath);
            if ($fileName === '' || in_array($fileName, $knownFiles, true)) {
                continue;
            }

            $pageKey = $this->derivePageKeyFromFileName($fileName);
            if ($pageKey === '') {
                continue;
            }

            $candidateKey = $pageKey;
            $suffix = 2;
            while (isset($resolved[$candidateKey])) {
                $candidateKey = $pageKey.'-'.$suffix;
                $suffix++;
            }

            $resolved[$candidateKey] = $fileName;
            $knownFiles[] = $fileName;
        }

        return $resolved;
    }

    /**
     * @return array<string, string>
     */
    private function resolvePageMapFromBlueprints(string $runtimeDir): array
    {
        $pagesDir = $runtimeDir.'/pages';
        if (! is_dir($pagesDir)) {
            return [];
        }

        $manifestPageOrder = [];
        $manifestPath = $runtimeDir.'/template.json';
        if (is_file($manifestPath)) {
            $manifest = json_decode((string) File::get($manifestPath), true);
            if (is_array($manifest) && is_array($manifest['pages'] ?? null)) {
                foreach ($manifest['pages'] as $pageKey) {
                    $normalized = trim(Str::slug((string) $pageKey));
                    if ($normalized !== '') {
                        $manifestPageOrder[] = $normalized;
                    }
                }
            }
        }

        $entries = [];
        foreach (glob($pagesDir.'/*.json') ?: [] as $jsonPath) {
            if (! is_string($jsonPath) || ! is_file($jsonPath)) {
                continue;
            }

            $decoded = json_decode((string) File::get($jsonPath), true);
            if (! is_array($decoded)) {
                continue;
            }

            $fileName = basename((string) ($decoded['file'] ?? ''));
            if ($fileName === '' || ! is_file($runtimeDir.'/'.$fileName)) {
                continue;
            }

            $stemKey = trim(Str::slug((string) pathinfo($jsonPath, PATHINFO_FILENAME)));
            $slugPath = trim((string) ($decoded['slug'] ?? ''));
            $resolvedKey = $stemKey;

            if ($slugPath === '/' || $slugPath === '') {
                $resolvedKey = 'home';
            } elseif ($resolvedKey === '') {
                $resolvedKey = $this->derivePageKeyFromFileName($fileName);
            }

            if ($resolvedKey === '') {
                continue;
            }

            $entries[$resolvedKey] = $fileName;
        }

        if ($entries === [] || ! isset($entries['home'])) {
            return [];
        }

        if ($manifestPageOrder === []) {
            ksort($entries);
            return $entries;
        }

        $ordered = [];
        foreach ($manifestPageOrder as $key) {
            if (isset($entries[$key])) {
                $ordered[$key] = $entries[$key];
            }
        }

        foreach ($entries as $key => $file) {
            if (! isset($ordered[$key])) {
                $ordered[$key] = $file;
            }
        }

        return $ordered;
    }

    private function derivePageKeyFromFileName(string $fileName): string
    {
        $base = Str::lower(pathinfo($fileName, PATHINFO_FILENAME));
        $base = trim($base);

        if ($base === '' || $base === 'index') {
            return '';
        }

        $slug = Str::slug($base);
        if ($slug !== '') {
            return $slug;
        }

        return preg_replace('/[^a-z0-9]+/i', '-', $base) ?: '';
    }

    /**
     * @param  array<string, string>  $pageMap
     * @return array<string, array{key:string,label:string,category:string,html_template:string,default_content:array<string,mixed>}>
     */
    private function extractComponents(string $runtimeDir, array $pageMap): array
    {
        $homeHtml = $this->readFile($runtimeDir, $pageMap['home']);
        $shopHtml = $this->readFile($runtimeDir, $pageMap['shop'] ?? $pageMap['home']);

        $components = [
            'webu_header_01' => [
                'key' => 'webu_header_01',
                'label' => 'Header',
                'category' => 'layout',
                'html_template' => $this->extractFirstMatch($homeHtml, [
                    '/<header\\b[\\s\\S]*?<\\/header>/i',
                    '/<(div|section)\\b[^>]*class="[^"]*(header|navbar|menu)[^"]*"[^>]*>[\\s\\S]*?<\\/\\1>/i',
                ]),
                'default_content' => [
                    'logo_text' => 'Custom Store',
                    'cta_label' => 'Shop Now',
                ],
            ],
            'webu_hero_01' => [
                'key' => 'webu_hero_01',
                'label' => 'Hero',
                'category' => 'hero',
                'html_template' => $this->extractFirstMatch($homeHtml, [
                    '/<(section|div)\\b[^>]*class="[^"]*(hero|banner|slider|intro)[^"]*"[^>]*>[\\s\\S]*?<\\/\\1>/i',
                    '/<section\\b[\\s\\S]*?<\\/section>/i',
                ]),
                'default_content' => [
                    'headline' => 'Custom Storefront',
                    'subtitle' => 'A modern ecommerce starter with editable builder sections.',
                    'primary_cta' => ['label' => 'Start Shopping', 'url' => '/shop'],
                ],
            ],
            'webu_category_list_01' => [
                'key' => 'webu_category_list_01',
                'label' => 'Category List',
                'category' => 'catalog',
                'html_template' => $this->extractFirstMatch($shopHtml, [
                    '/<(section|div)\\b[^>]*class="[^"]*(category|categories|collection)[^"]*"[^>]*>[\\s\\S]*?<\\/\\1>/i',
                ]),
                'default_content' => [
                    'title' => 'Popular Categories',
                ],
            ],
            'webu_product_grid_01' => [
                'key' => 'webu_product_grid_01',
                'label' => 'Product Grid',
                'category' => 'catalog',
                'html_template' => $this->extractFirstMatch($shopHtml, [
                    '/<(section|div)\\b[^>]*class="[^"]*(product|products|shop|grid)[^"]*"[^>]*>[\\s\\S]*?<\\/\\1>/i',
                ]),
                'default_content' => [
                    'title' => 'Featured Products',
                    'collection' => 'all-products',
                ],
            ],
            'webu_product_card_01' => [
                'key' => 'webu_product_card_01',
                'label' => 'Product Card',
                'category' => 'catalog',
                'html_template' => $this->extractFirstMatch($shopHtml, [
                    '/<(article|div)\\b[^>]*class="[^"]*(product|card|item)[^"]*"[^>]*>[\\s\\S]*?<\\/\\1>/i',
                ]),
                'default_content' => [
                    'name' => 'Sample Product',
                    'price' => '99.00',
                ],
            ],
            'webu_cta_banner_01' => [
                'key' => 'webu_cta_banner_01',
                'label' => 'CTA Banner',
                'category' => 'conversion',
                'html_template' => $this->extractFirstMatch($homeHtml, [
                    '/<(section|div)\\b[^>]*class="[^"]*(cta|promo|call-to-action|offer)[^"]*"[^>]*>[\\s\\S]*?<\\/\\1>/i',
                ]),
                'default_content' => [
                    'headline' => 'Start selling with Webu',
                    'button' => ['label' => 'Get Started', 'url' => '/checkout'],
                ],
            ],
            'webu_newsletter_01' => [
                'key' => 'webu_newsletter_01',
                'label' => 'Newsletter',
                'category' => 'conversion',
                'html_template' => $this->extractFirstMatch($homeHtml, [
                    '/<(section|div)\\b[^>]*class="[^"]*(newsletter|subscribe)[^"]*"[^>]*>[\\s\\S]*?<\\/\\1>/i',
                ]),
                'default_content' => [
                    'headline' => 'Subscribe for offers',
                    'placeholder' => 'Email address',
                ],
            ],
            'webu_footer_01' => [
                'key' => 'webu_footer_01',
                'label' => 'Footer',
                'category' => 'layout',
                'html_template' => $this->extractFirstMatch($homeHtml, [
                    '/<footer\\b[\\s\\S]*?<\\/footer>/i',
                ]),
                'default_content' => [
                    'copyright' => '© '.date('Y').' Webu',
                ],
            ],
        ];

        $boundFragments = [];
        foreach ($pageMap as $pageKey => $file) {
            $pageHtml = $this->readFile($runtimeDir, $file);
            foreach ($this->extractBoundSectionFragments($pageHtml) as $key => $fragment) {
                if (! isset($boundFragments[$key]) && trim($fragment) !== '') {
                    $boundFragments[$key] = $fragment;
                }
            }
        }

        $homeBoundKeys = array_values(array_filter(array_keys($boundFragments), static fn (string $key): bool => str_starts_with($key, 'webu_home_section_')));
        sort($homeBoundKeys);
        foreach ($homeBoundKeys as $index => $homeKey) {
            if (! isset($components[$homeKey])) {
                $components[$homeKey] = [
                    'key' => $homeKey,
                    'label' => 'Home Section '.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                    'category' => 'content',
                    'html_template' => $boundFragments[$homeKey],
                    'default_content' => [],
                ];
            }
        }

        foreach ($boundFragments as $key => $fragment) {
            if (! isset($components[$key])) {
                $components[$key] = [
                    'key' => $key,
                    'label' => Str::headline(str_replace('webu_', '', $key)),
                    'category' => $this->inferSectionCategory($key),
                    'html_template' => $fragment,
                    'default_content' => [],
                ];
            }
        }

        foreach ($components as $key => $component) {
            $fragment = trim($boundFragments[$key] ?? $component['html_template']);
            if ($fragment === '') {
                $fragment = '<section data-webu-section="'.$key.'"></section>';
            }

            $defaults = $this->deriveDefaultContentFromHtml($fragment);
            $components[$key]['html_template'] = $fragment;
            $components[$key]['default_content'] = $this->mergeContentDefaults(
                $defaults,
                $component['default_content']
            );
        }

        return $components;
    }

    /**
     * @param  array<string, array{key:string,label:string,category:string,html_template:string,default_content:array<string,mixed>}>  $components
     * @return array<int, array{key:string,category:string}>
     */
    private function upsertSections(array $components): array
    {
        $result = [];

        foreach ($components as $component) {
            $schemaProperties = $this->buildSchemaProperties($component['default_content']);

            SectionLibrary::query()->updateOrCreate(
                ['key' => $component['key']],
                [
                    'category' => $component['category'],
                    'enabled' => true,
                    'schema_json' => [
                        '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                        'type' => 'object',
                        'version' => 1,
                        'component_id' => $component['key'],
                        'title' => $component['label'],
                        'html_template' => $component['html_template'],
                        'default_content_json' => $component['default_content'],
                        'editable_props' => array_keys($component['default_content']),
                        'properties' => $schemaProperties,
                        'required' => [],
                        '_meta' => [
                            'label' => $component['label'],
                            'description' => 'Editable fields for '.$component['label'],
                            'design_variant' => 'default',
                            'binding_target' => 'content_json.sections[].props',
                        ],
                        'bindings' => [
                            'source' => 'cms',
                            'component' => $component['key'],
                        ],
                    ],
                ]
            );

            $result[] = [
                'key' => $component['key'],
                'category' => $component['category'],
            ];
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $pageMap
     * @param  array<int, array{key:string,category:string}>  $sections
     * @param  array<string, array{key:string,label:string,category:string,html_template:string,default_content:array<string,mixed>}>  $components
     * @return array<string, mixed>
     */
    private function buildManifest(string $themeSlug, string $themeName, array $pageMap, array $sections, array $components): array
    {
        $pageTitles = [
            'home' => 'მთავარი',
            'shop' => 'მაღაზია',
            'product' => 'პროდუქტი',
            'cart' => 'კალათა',
            'checkout' => 'გადახდა',
            'login' => 'შესვლა / რეგისტრაცია',
            'account' => 'ჩემი ანგარიში',
            'orders' => 'შეკვეთები',
            'order' => 'შეკვეთის დეტალები',
            'contact' => 'კონტაქტი',
        ];

        $pageSlugs = [
            'home' => 'home',
            'shop' => 'shop',
            'product' => 'product',
            'cart' => 'cart',
            'checkout' => 'checkout',
            'login' => 'login',
            'account' => 'account',
            'orders' => 'orders',
            'order' => 'order',
            'contact' => 'contact',
        ];

        $pagePaths = [
            'home' => '/',
            'shop' => '/shop',
            'product' => '/product/:id',
            'cart' => '/cart',
            'checkout' => '/checkout',
            'login' => '/account/login',
            'account' => '/account',
            'orders' => '/account/orders',
            'order' => '/account/orders/:id',
            'contact' => '/contact',
        ];

        $pages = [];
        foreach ($pageMap as $pageKey => $file) {
            $pages[] = [
                'key' => $pageKey,
                'title' => $pageTitles[$pageKey] ?? Str::headline($pageKey),
                'slug' => $pageSlugs[$pageKey] ?? $pageKey,
                'path' => $pagePaths[$pageKey] ?? '/'.trim($pageKey, '/'),
                'template_file' => $file,
            ];
        }

        $defaultSections = $this->buildDefaultSectionsByPage($pageMap, $components);

        return [
            'slug' => $themeSlug,
            'name' => $themeName,
            'category' => 'ecommerce',
            'version' => '1.0.0',
            'default_locale' => 'ka',
            'pages' => $pages,
            'modules' => [
                'cms' => true,
                'ecommerce' => true,
                'booking' => false,
                'shipping' => true,
            ],
            'typography_tokens' => [
                'heading' => 'tbc_contractica',
                'body' => 'tbc_contractica',
                'button' => 'tbc_contractica',
            ],
            'default_sections' => $defaultSections,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extractBoundSectionFragments(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = $this->loadHtmlFragmentDocument($html);
        if (! $document) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $nodes = $xpath->query('//*[@data-webu-section]');
        if (! $nodes) {
            return [];
        }

        $result = [];
        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }

            $key = trim(Str::lower((string) ($node->attributes?->getNamedItem('data-webu-section')?->nodeValue ?? '')));
            if ($key === '' || isset($result[$key])) {
                continue;
            }

            $fragment = trim((string) $document->saveHTML($node));
            if ($fragment === '') {
                continue;
            }

            $result[$key] = $fragment;
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function deriveDefaultContentFromHtml(string $html): array
    {
        if (trim($html) === '') {
            return [];
        }

        $document = $this->loadHtmlFragmentDocument($html);
        if (! $document) {
            return [];
        }

        $xpath = new DOMXPath($document);
        $defaults = [];

        $headingNodes = $xpath->query('//h1|//h2|//h3|//h4|//h5|//h6');
        $headingIndex = 1;
        if ($headingNodes) {
            foreach ($headingNodes as $node) {
                $text = $this->normalizeTextContent((string) $node->textContent);
                if ($text === '' || mb_strlen($text) > 180) {
                    continue;
                }

                $defaults['heading_'.str_pad((string) $headingIndex, 2, '0', STR_PAD_LEFT)] = $text;
                $headingIndex++;
                if ($headingIndex > 16) {
                    break;
                }
            }
        }

        $paragraphNodes = $xpath->query('//p');
        $paragraphIndex = 1;
        if ($paragraphNodes) {
            foreach ($paragraphNodes as $node) {
                $text = $this->normalizeTextContent((string) $node->textContent);
                if ($text === '' || mb_strlen($text) > 700) {
                    continue;
                }

                $defaults['paragraph_'.str_pad((string) $paragraphIndex, 2, '0', STR_PAD_LEFT)] = $text;
                $paragraphIndex++;
                if ($paragraphIndex > 24) {
                    break;
                }
            }
        }

        $linkNodes = $xpath->query('//a[@href]');
        $linkIndex = 1;
        if ($linkNodes) {
            foreach ($linkNodes as $node) {
                if (! $node instanceof DOMNode) {
                    continue;
                }

                $href = trim((string) ($node->attributes?->getNamedItem('href')?->nodeValue ?? ''));
                $label = $this->normalizeTextContent((string) $node->textContent);
                if ($href === '' && $label === '') {
                    continue;
                }

                $defaults['link_'.str_pad((string) $linkIndex, 2, '0', STR_PAD_LEFT)] = [
                    'label' => $label,
                    'url' => $href,
                ];

                $linkIndex++;
                if ($linkIndex > 24) {
                    break;
                }
            }
        }

        $buttonNodes = $xpath->query('//button');
        $buttonIndex = 1;
        if ($buttonNodes) {
            foreach ($buttonNodes as $node) {
                $label = $this->normalizeTextContent((string) $node->textContent);
                if ($label === '') {
                    continue;
                }

                $defaults['button_'.str_pad((string) $buttonIndex, 2, '0', STR_PAD_LEFT)] = [
                    'label' => $label,
                    'url' => '',
                ];

                $buttonIndex++;
                if ($buttonIndex > 12) {
                    break;
                }
            }
        }

        $imageNodes = $xpath->query('//img[@src]');
        $imageIndex = 1;
        if ($imageNodes) {
            foreach ($imageNodes as $node) {
                if (! $node instanceof DOMNode) {
                    continue;
                }

                $src = trim((string) ($node->attributes?->getNamedItem('src')?->nodeValue ?? ''));
                if ($src === '') {
                    continue;
                }

                $alt = trim((string) ($node->attributes?->getNamedItem('alt')?->nodeValue ?? ''));
                $defaults['image_'.str_pad((string) $imageIndex, 2, '0', STR_PAD_LEFT)] = [
                    'url' => $src,
                    'alt' => $alt,
                ];
                $imageIndex++;
                if ($imageIndex > 16) {
                    break;
                }
            }
        }

        $inputNodes = $xpath->query('//input[@placeholder]');
        $inputIndex = 1;
        if ($inputNodes) {
            foreach ($inputNodes as $node) {
                if (! $node instanceof DOMNode) {
                    continue;
                }

                $placeholder = trim((string) ($node->attributes?->getNamedItem('placeholder')?->nodeValue ?? ''));
                if ($placeholder === '') {
                    continue;
                }

                $defaults['input_placeholder_'.str_pad((string) $inputIndex, 2, '0', STR_PAD_LEFT)] = $placeholder;
                $inputIndex++;
                if ($inputIndex > 12) {
                    break;
                }
            }
        }

        return $defaults;
    }

    private function loadHtmlFragmentDocument(string $html): ?DOMDocument
    {
        $html = trim($html);
        if ($html === '') {
            return null;
        }

        // Force libxml HTML parsing to treat incoming fragments as UTF-8.
        $wrapped = '<?xml encoding="UTF-8"><!doctype html><html><head><meta charset="UTF-8"></head><body>'.$html.'</body></html>';

        $document = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $loaded = $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        return $loaded ? $document : null;
    }

    private function normalizeTextContent(string $value): string
    {
        $normalized = preg_replace('/\s+/u', ' ', trim($value));

        return is_string($normalized) ? trim($normalized) : '';
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function mergeContentDefaults(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && is_array($base[$key] ?? null)) {
                $base[$key] = $this->mergeContentDefaults($base[$key], $value);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, array<string, mixed>>
     */
    private function buildSchemaProperties(array $props): array
    {
        $result = [];
        foreach ($props as $key => $value) {
            $result[$key] = $this->buildSchemaDefinition($value, $key);
        }

        return $result;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSchemaDefinition(mixed $value, string $key): array
    {
        $title = Str::headline($key);

        if (is_bool($value)) {
            return [
                'type' => 'boolean',
                'title' => $title,
                'default' => $value,
            ];
        }

        if (is_int($value)) {
            return [
                'type' => 'integer',
                'title' => $title,
                'default' => $value,
            ];
        }

        if (is_float($value)) {
            return [
                'type' => 'number',
                'title' => $title,
                'default' => $value,
            ];
        }

        if (is_array($value)) {
            if ($this->isListArray($value)) {
                return [
                    'type' => 'array',
                    'title' => $title,
                    'default' => $value,
                    'items' => [],
                ];
            }

            return [
                'type' => 'object',
                'title' => $title,
                'default' => $value,
                'properties' => $this->buildSchemaProperties($value),
            ];
        }

        return [
            'type' => 'string',
            'title' => $title,
            'default' => is_scalar($value) || $value === null ? (string) ($value ?? '') : '',
        ];
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private function isListArray(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function inferSectionCategory(string $sectionKey): string
    {
        if (str_contains($sectionKey, 'header') || str_contains($sectionKey, 'footer')) {
            return 'layout';
        }

        if (str_contains($sectionKey, 'hero') || str_contains($sectionKey, 'banner')) {
            return 'hero';
        }

        if (str_contains($sectionKey, 'product') || str_contains($sectionKey, 'category') || str_contains($sectionKey, 'shop')) {
            return 'catalog';
        }

        if (str_contains($sectionKey, 'newsletter') || str_contains($sectionKey, 'cta')) {
            return 'conversion';
        }

        return 'content';
    }

    /**
     * @param  array<string, string>  $pageMap
     * @param  array<string, array{key:string,label:string,category:string,html_template:string,default_content:array<string,mixed>}>  $components
     * @return array<string, array<int, array{key:string, props:array<string,mixed>}>>
     */
    private function buildDefaultSectionsByPage(array $pageMap, array $components): array
    {
        $result = [];
        $componentKeys = array_keys($components);

        foreach ($pageMap as $pageKey => $_file) {
            $pageSectionKeys = [];
            $prefix = 'webu_'.$pageKey.'_section_';

            foreach ($componentKeys as $key) {
                if (str_starts_with($key, $prefix)) {
                    $pageSectionKeys[] = $key;
                }
            }

            $semanticByPage = match ($pageKey) {
                'home' => ['webu_header_01', 'webu_hero_01', 'webu_cta_banner_01', 'webu_newsletter_01', 'webu_footer_01'],
                'shop' => ['webu_header_01', 'webu_category_list_01', 'webu_product_grid_01', 'webu_footer_01'],
                'product' => ['webu_header_01', 'webu_product_card_01', 'webu_footer_01'],
                'cart' => ['webu_header_01', 'webu_footer_01'],
                'checkout' => ['webu_header_01', 'webu_footer_01'],
                'login' => ['webu_header_01', 'webu_footer_01'],
                'account' => ['webu_header_01', 'webu_footer_01'],
                'orders' => ['webu_header_01', 'webu_footer_01'],
                'order' => ['webu_header_01', 'webu_footer_01'],
                'contact' => ['webu_header_01', 'webu_footer_01'],
                default => ['webu_header_01', 'webu_footer_01'],
            };

            foreach ($semanticByPage as $semanticKey) {
                if (isset($components[$semanticKey])) {
                    $pageSectionKeys[] = $semanticKey;
                }
            }

            $pageSectionKeys = array_values(array_unique(array_values(array_filter(
                $pageSectionKeys,
                static fn (string $sectionKey): bool => isset($components[$sectionKey])
            ))));

            if ($pageSectionKeys === []) {
                $pageSectionKeys = array_values(array_map(static fn (array $component): string => $component['key'], $components));
            }

            $result[$pageKey] = array_values(array_map(function (string $sectionKey) use ($components): array {
                $defaults = $components[$sectionKey]['default_content'] ?? [];
                if (! is_array($defaults)) {
                    $defaults = [];
                }

                return [
                    'key' => $sectionKey,
                    'props' => $defaults,
                ];
            }, $pageSectionKeys));
        }

        return $result;
    }

    /**
     * @param  array<string, string>  $pageMap
     * @return array<string, mixed>
     */
    private function buildMapping(array $pageMap): array
    {
        $cmsSlugMap = [
            'home' => '/',
            'login' => '/account/login',
            'account' => '/account',
            'orders' => '/account/orders',
            'order' => '/account/orders/:id',
        ];

        $mappingPages = [];
        foreach ($pageMap as $pageKey => $file) {
            $mappingPages[$pageKey] = [
                'template_file' => $file,
                'cms_slug' => $cmsSlugMap[$pageKey] ?? '/'.$pageKey,
            ];
        }

        return [
            'menus' => [
                'header' => 'header',
                'footer' => 'footer',
            ],
            'settings' => [
                'logo' => 'logo_media_id',
                'contact' => 'contact_json',
                'social' => 'social_links_json',
            ],
            'pages' => $mappingPages,
            'ecommerce_bindings' => [
                'products' => 'window.WebbyEcommerce.listProducts',
                'product' => 'window.WebbyEcommerce.getProduct',
                'cart' => 'window.WebbyEcommerce.createCart/addItem/updateItem/removeItem',
                'checkout' => 'window.WebbyEcommerce.checkout/startPayment',
            ],
        ];
    }

    private function createRuntimeZip(string $runtimeDir, string $themeSlug, array $manifest): string
    {
        $zipRelativePath = 'templates/'.$themeSlug.'-template.zip';
        $zipAbsolutePath = storage_path('app/'.$zipRelativePath);

        File::ensureDirectoryExists(dirname($zipAbsolutePath));
        if (is_file($zipAbsolutePath)) {
            File::delete($zipAbsolutePath);
        }

        $zip = new ZipArchive;
        $opened = $zip->open($zipAbsolutePath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            throw new \RuntimeException('Failed to create runtime template zip archive.');
        }

        $zip->addFromString(
            'template.json',
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($runtimeDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $pathName = $file->getPathname();
            $relative = ltrim(str_replace('\\', '/', substr($pathName, strlen($runtimeDir))), '/');
            $zip->addFile($pathName, $relative);
        }

        $zip->close();

        return $zipRelativePath;
    }

    private function readFile(string $runtimeDir, string $file): string
    {
        $path = $runtimeDir.'/'.$file;

        return is_file($path) ? (string) File::get($path) : '';
    }

    /**
     * @param  array<int, string>  $patterns
     */
    private function extractFirstMatch(string $html, array $patterns): string
    {
        if (trim($html) === '') {
            return '';
        }

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches) === 1) {
                return trim((string) ($matches[0] ?? ''));
            }
        }

        return '';
    }
}
