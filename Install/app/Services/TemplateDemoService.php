<?php

namespace App\Services;

use App\Models\EcommerceCategory;
use App\Models\EcommerceProduct;
use App\Models\Menu;
use App\Models\Page;
use App\Models\Site;
use App\Models\Template;
use App\Support\CmsSectionLocalId;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class TemplateDemoService
{
    public function __construct(
        protected CmsLocaleResolver $localeResolver,
        protected FixedLayoutComponentService $fixedLayoutComponents
    ) {}

    /**
     * Build backend-driven demo payload for admin template preview.
     *
     * @return array<string, mixed>
     */
    /**
     * Build demo payload. Demo is project-scoped only when $site is set (URL has site=).
     * - With $site: use that project's site pages/revisions (editable only in that project's builder).
     * - Without $site: use template default_pages/default_sections (generic read-only demo; no builder edits).
     */
    public function buildPayload(
        Template $template,
        ?string $requestedPage = null,
        ?Site $site = null,
        ?string $locale = null,
        bool $draft = false,
        ?string $headerVariantOverride = null,
        ?string $footerVariantOverride = null
    ): array
    {
        $metadata = is_array($template->metadata) ? $template->metadata : [];
        $pageTemplateFiles = $this->resolveTemplatePageFiles($metadata);
        $pages = $this->normalizePagesFromSite($site, $template, $pageTemplateFiles, $locale, $draft);
        $source = $pages !== []
            ? 'backend-template-demo-site'
            : 'backend-template-demo';

        if ($pages === []) {
            $pages = $this->normalizePages($metadata);
        }

        $isEkkaTemplate = strtolower((string) $template->slug) === 'ekka-demo-8';
        if ($isEkkaTemplate && $pages === []) {
            $pages = $this->ekkaDefaultPages($pageTemplateFiles);
        }

        if ($site === null) {
            $pages = $this->ensureCanonicalTemplateCoveragePages($template, $pages, $pageTemplateFiles);
        }

        if ($pages === []) {
            $pages = $isEkkaTemplate
                ? $this->ekkaDefaultPages($pageTemplateFiles)
                : [
                    [
                        'slug' => 'home',
                        'title' => 'Home',
                        'template_file' => 'index.html',
                        'sections' => [
                            ['key' => 'hero', 'enabled' => true, 'props' => []],
                            ['key' => 'services', 'enabled' => true, 'props' => []],
                            ['key' => 'contact', 'enabled' => true, 'props' => []],
                        ],
                    ],
                ];
        }

        $assets = $this->demoAssets($site, $template);
        $moduleFlags = is_array(Arr::get($metadata, 'module_flags')) ? Arr::get($metadata, 'module_flags') : [];
        $typographyTokens = is_array(Arr::get($metadata, 'typography_tokens')) ? Arr::get($metadata, 'typography_tokens') : [];

        $enrichedPages = [];
        foreach ($pages as $pageIndex => $page) {
            $enrichedPages[] = $this->enrichPage(
                template: $template,
                page: $page,
                assets: $assets,
                pageIndex: (int) $pageIndex,
                site: $site,
                pageTemplateFiles: $pageTemplateFiles,
                locale: $locale,
                draft: $draft
            );
        }

        // Build layout header and footer from site theme_settings so builder header/footer variant and layout_variant apply in preview
        $layoutHeaderSection = null;
        $layoutFooterSection = null;
        if ($site !== null && $enrichedPages !== []) {
            $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
            $layout = is_array(Arr::get($themeSettings, 'layout')) ? Arr::get($themeSettings, 'layout') : [];
            $firstPage = $enrichedPages[0];

            $headerSectionKey = trim((string) Arr::get($layout, 'header_section_key', ''));
            if ($headerSectionKey === '') {
                $headerSectionKey = 'webu_header_01';
            }
            $headerProps = is_array(Arr::get($layout, 'header_props')) ? Arr::get($layout, 'header_props') : [];
            if ($headerVariantOverride !== null && $headerVariantOverride !== '') {
                $headerProps['layout_variant'] = $headerVariantOverride;
            }
            $headerProps = $this->fixedLayoutComponents->normalizeProps($headerSectionKey, $headerProps);
            $headerSection = ['key' => $headerSectionKey, 'enabled' => true, 'props' => $headerProps];
            $layoutHeaderSection = $this->buildSectionDemo($template, $firstPage, $headerSection, $assets, 0, 0, $site, $locale);
            if ($layoutHeaderSection !== null && isset($layoutHeaderSection['data']) && is_array($layoutHeaderSection['data'])) {
                $branding = is_array(Arr::get($themeSettings, 'branding')) ? Arr::get($themeSettings, 'branding') : [];
                $logoUrl = trim((string) Arr::get($branding, 'logo_url', ''));
                if ($logoUrl === '' && is_string(Arr::get($branding, 'logo_path')) && Arr::get($branding, 'logo_path') !== '') {
                    $path = (string) Arr::get($branding, 'logo_path');
                    $logoUrl = str_starts_with($path, 'http') ? $path : asset('storage/'.ltrim($path, '/'));
                }
                if ($logoUrl === '') {
                    $site->loadMissing('globalSettings.logoMedia');
                    $logoPath = $site->globalSettings?->logoMedia?->path;
                    if (is_string($logoPath) && $logoPath !== '') {
                        $logoUrl = route('public.sites.assets', ['site' => $site->id, 'path' => $logoPath]);
                    }
                }
                if ($logoUrl !== '') {
                    $layoutHeaderSection['data']['logo_image_url'] = $logoUrl;
                }
            }

            $footerSectionKey = trim((string) Arr::get($layout, 'footer_section_key', ''));
            if ($footerSectionKey === '') {
                $footerSectionKey = 'webu_footer_01';
            }
            $footerProps = is_array(Arr::get($layout, 'footer_props')) ? Arr::get($layout, 'footer_props') : [];
            if ($footerVariantOverride !== null && $footerVariantOverride !== '') {
                $footerProps['layout_variant'] = $footerVariantOverride;
            }
            $footerProps = $this->fixedLayoutComponents->normalizeProps($footerSectionKey, $footerProps);
            $footerSection = ['key' => $footerSectionKey, 'enabled' => true, 'props' => $footerProps];
            $layoutFooterSection = $this->buildSectionDemo($template, $firstPage, $footerSection, $assets, 0, 0, $site, $locale);
        }

        $activeSlug = $this->resolveActiveSlug($enrichedPages, $requestedPage);
        $activePage = collect($enrichedPages)->firstWhere('slug', $activeSlug) ?? $enrichedPages[0];

        $totalSections = collect($enrichedPages)->sum(static fn (array $page): int => count($page['sections']));

        $footerMenus = [];
        $footerLayout = [];
        if ($site !== null) {
            $footerData = $this->buildFooterDataForDemo($site, $locale);
            $footerMenus = $footerData['menus'];
            $footerLayout = $footerData['layout'];
        }

        $headerMenuItems = $this->buildHeaderMenuItemsForDemo($template, $site, $locale);

        return [
            'template' => [
                'id' => $template->id,
                'slug' => $template->slug,
                'name' => $template->name,
                'description' => $template->description,
                'category' => $template->category,
                'version' => $template->version,
                'thumbnail' => $template->thumbnail,
                'thumbnail_url' => $template->preview_image_url ?? $this->resolveThumbnailUrl($template->thumbnail),
            ],
            'meta' => [
                'generated_at' => now()->toIso8601String(),
                'source' => $source,
                'requested_page' => $requestedPage,
                'active_page_slug' => $activePage['slug'],
                'title' => $activePage['title'] ?? null,
            ],
            'demo_site' => $site ? [
                'id' => (string) $site->id,
                'project_id' => $site->project_id ? (string) $site->project_id : null,
                'locale' => $site->locale,
            ] : null,
            'module_flags' => $moduleFlags,
            'typography_tokens' => $typographyTokens,
            'assets' => [
                'logos' => $assets['logos'],
                'hero_images' => $assets['hero_images'],
                'gallery_images' => $assets['gallery_images'],
                'products' => $assets['products'],
                'categories' => $assets['categories'],
            ],
            'stats' => [
                'page_count' => count($enrichedPages),
            ],
            'pages' => $enrichedPages,
            'active_page' => $activePage,
            'layout_header' => $layoutHeaderSection,
            'layout_footer' => $layoutFooterSection,
            'header_menu_items' => $headerMenuItems,
            'footer_menus' => $footerMenus,
            'footer_layout' => $footerLayout,
        ];
    }

    /**
     * Header menu items for demo (from site menu or default demo menu).
     *
     * @return array<int, array{label: string, url: string, slug: string}>
     */
    private function buildHeaderMenuItemsForDemo(Template $template, ?Site $site, ?string $locale): array
    {
        $templateSlug = strtolower((string) $template->slug);

        if ($site !== null) {
            $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
            $layout = is_array(Arr::get($themeSettings, 'layout')) ? Arr::get($themeSettings, 'layout') : [];
            $headerMenuKey = $this->normalizeMenuKey(Arr::get($layout, 'header_menu_key'), 'header');
            $menu = Menu::where('site_id', $site->id)->where('key', $headerMenuKey)->first();
            if ($menu !== null && is_array($menu->items_json)) {
                $siteLocale = $site->locale ?? 'ka';
                $requestedLocale = $locale !== null && $locale !== '' ? $locale : $siteLocale;
                $resolved = $this->localeResolver->resolvePayload($menu->items_json, $requestedLocale, $siteLocale);
                $content = is_array($resolved['content'] ?? null) ? $resolved['content'] : [];
                $items = [];
                foreach ($content as $item) {
                    $label = (string) (is_array($item) ? ($item['label'] ?? 'Link') : 'Link');
                    $url = (string) (is_array($item) ? ($item['url'] ?? '#') : '#');
                    $slug = $this->slugFromUrl($url, $items);
                    $items[] = ['label' => $label, 'url' => $url, 'slug' => $slug];
                }

                return $items;
            }
        }

        return $this->defaultDemoHeaderMenuItems($templateSlug);
    }

    /**
     * Derive a unique slug from URL for active state (path segment or fallback).
     */
    private function slugFromUrl(string $url, array $existingItems): string
    {
        if ($url === '' || $url === '#') {
            return 'page-'.(string) count($existingItems);
        }
        $path = parse_url($url, PHP_URL_PATH);
        if (is_string($path) && $path !== '') {
            $seg = trim($path, '/');
            if ($seg !== '') {
                $last = Str::afterLast($seg, '/');

                return $last !== '' ? Str::slug($last) : Str::slug($seg);
            }
        }

        return 'page-'.(string) count($existingItems);
    }

    /**
     * Default header nav items when no site or empty header menu (demo-only).
     *
     * @return array<int, array{label: string, url: string, slug: string}>
     */
    private function defaultDemoHeaderMenuItems(string $templateSlug): array
    {
        $isEcommerce = $templateSlug === 'ecommerce';
        if ($isEcommerce) {
            return [
                ['label' => 'Home', 'url' => '#', 'slug' => 'home'],
                ['label' => 'Shop', 'url' => '#', 'slug' => 'shop'],
                ['label' => 'Blog', 'url' => '#', 'slug' => 'blog'],
                ['label' => 'Post Layout', 'url' => '#', 'slug' => 'post-layout'],
                ['label' => 'Portfolio', 'url' => '#', 'slug' => 'portfolio'],
                ['label' => 'Pages', 'url' => '#', 'slug' => 'pages'],
                ['label' => 'My Account', 'url' => '#', 'slug' => 'account'],
            ];
        }

        return [
            ['label' => 'Home', 'url' => '#', 'slug' => 'home'],
            ['label' => 'Services', 'url' => '#', 'slug' => 'services'],
            ['label' => 'Contact', 'url' => '#', 'slug' => 'contact'],
        ];
    }

    /**
     * Public API for WebuCmsResolver: footer menus and layout for a site.
     *
     * @return array{menus: array<string, array<int, array{label: string, url: string}>>, layout: array{contact_address: string, menu_key_column2: string, menu_key_column3: string, menu_key_column4: string, menu_key_column5: string}}
     */
    public function getFooterDataForSite(Site $site, ?string $locale = null): array
    {
        return $this->buildFooterDataForDemo($site, $locale);
    }

    /**
     * Build footer menus keyed by menu key and footer layout (contact + which menu per column).
     *
     * @return array{menus: array<string, array<int, array{label: string, url: string}>>, layout: array{contact_address: string, menu_key_column2: string, menu_key_column3: string, menu_key_column4: string, menu_key_column5: string}}
     */
    private function buildFooterDataForDemo(Site $site, ?string $locale = null): array
    {
        $siteLocale = $site->locale ?? 'ka';
        $requestedLocale = $locale !== null && $locale !== '' ? $locale : $siteLocale;

        $menus = Menu::where('site_id', $site->id)->get();
        $menusByKey = [];
        foreach ($menus as $menu) {
            $resolved = $this->localeResolver->resolvePayload($menu->items_json ?? [], $requestedLocale, $siteLocale);
            $content = is_array($resolved['content'] ?? null) ? $resolved['content'] : [];
            $items = [];
            foreach ($content as $item) {
                $items[] = [
                    'label' => (string) (is_array($item) ? ($item['label'] ?? 'Link') : 'Link'),
                    'url' => (string) (is_array($item) ? ($item['url'] ?? '#') : '#'),
                ];
            }
            $menusByKey[$menu->key] = $items;
        }

        $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $layout = is_array(Arr::get($themeSettings, 'layout')) ? Arr::get($themeSettings, 'layout') : [];

        return [
            'menus' => $menusByKey,
            'layout' => [
                'contact_address' => trim((string) Arr::get($layout, 'footer_contact_address', '')),
                'menu_key_column2' => $this->normalizeMenuKey(Arr::get($layout, 'footer_menu_key_column2'), 'recent-posts'),
                'menu_key_column3' => $this->normalizeMenuKey(Arr::get($layout, 'footer_menu_key_column3'), 'our-stores'),
                'menu_key_column4' => $this->normalizeMenuKey(Arr::get($layout, 'footer_menu_key_column4'), 'useful-links'),
                'menu_key_column5' => $this->normalizeMenuKey(Arr::get($layout, 'footer_menu_key_column5'), 'footer'),
            ],
        ];
    }

    private function normalizeMenuKey(mixed $value, string $fallback): string
    {
        $v = is_string($value) ? trim(strtolower($value)) : '';
        if ($v === '' || ! preg_match('/^[a-z0-9_-]{1,64}$/', $v)) {
            return $fallback;
        }

        return $v;
    }

    /**
     * Export a site's page content into template default_pages / default_sections format.
     * Use to sync a project's refined demo into the template so the generic demo (no site=) shows the same content.
     *
     * @return array{default_pages: array<int, array<string, mixed>>, default_sections: array<string, array<int, array<string, mixed>>>}
     */
    public function exportDefaultContentFromSite(Site $site, Template $template): array
    {
        $site->loadMissing('project');
        if (! $site->project || (int) $site->project->template_id !== (int) $template->id) {
            return [
                'default_pages' => [],
                'default_sections' => [],
            ];
        }

        $metadata = is_array($template->metadata) ? $template->metadata : [];
        $pageTemplateFiles = $this->resolveTemplatePageFiles($metadata);
        $existingPages = $this->normalizePages($metadata);
        if ($existingPages === []) {
            return ['default_pages' => [], 'default_sections' => []];
        }

        $sitePages = Page::query()
            ->where('site_id', $site->id)
            ->orderBy('id')
            ->with([
                'revisions' => function ($query): void {
                    $query->select(['id', 'page_id', 'content_json'])
                        ->orderByDesc('id')->limit(1);
                },
            ])
            ->get();

        $siteSectionsBySlug = [];
        foreach ($sitePages as $page) {
            $slug = Str::slug(trim((string) $page->slug), '');
            if ($slug === '') {
                continue;
            }
            $revision = $page->revisions->first();
            $sections = $this->extractSectionsFromRevisionPayload(
                $revision?->content_json ?? [],
                null,
                $site->locale
            );
            $siteSectionsBySlug[$slug] = $sections;
        }

        $defaultPages = [];
        $defaultSections = [];
        foreach ($existingPages as $page) {
            $slug = (string) ($page['slug'] ?? '');
            $title = (string) ($page['title'] ?? '');
            $templateFile = (string) ($page['template_file'] ?? $this->resolveTemplateFileForPage($slug, $pageTemplateFiles));
            $defaultPages[] = [
                'slug' => $slug,
                'title' => $title,
                'template_file' => $templateFile,
            ];
            $sections = $siteSectionsBySlug[$slug] ?? $page['sections'] ?? [];
            $defaultSections[$slug] = $sections;
        }

        return [
            'default_pages' => $defaultPages,
            'default_sections' => $defaultSections,
        ];
    }

    /**
     * @param  array<string, mixed>  $metadata
     * @return array<int, array{slug: string, title: string, sections: array<int, array<string, mixed>>}>
     */
    private function normalizePages(array $metadata): array
    {
        $pages = [];
        $pageTemplateFiles = $this->resolveTemplatePageFiles($metadata);

        $defaultPages = Arr::get($metadata, 'default_pages', []);
        if (is_array($defaultPages)) {
            foreach ($defaultPages as $index => $page) {
                if (! is_array($page)) {
                    continue;
                }

                $title = trim((string) ($page['title'] ?? $page['slug'] ?? 'Page '.($index + 1)));
                $slug = $this->slugify((string) ($page['slug'] ?? $title), 'page-'.($index + 1));
                $sections = $this->normalizeSections($page['sections'] ?? []);

                $pages[$slug] = [
                    'slug' => $slug,
                    'title' => $title !== '' ? $title : 'Page '.($index + 1),
                    'template_file' => $this->resolveTemplateFileForPage($slug, $pageTemplateFiles),
                    'sections' => $sections,
                ];
            }
        }

        $defaultSections = Arr::get($metadata, 'default_sections', []);
        if (is_array($defaultSections)) {
            foreach ($defaultSections as $pageSlug => $rawSections) {
                if (! is_array($rawSections)) {
                    continue;
                }

                $slug = $this->slugify((string) $pageSlug, 'page');
                $sections = $this->normalizeSections($rawSections);

                if (! isset($pages[$slug])) {
                    $pages[$slug] = [
                        'slug' => $slug,
                        'title' => $this->humanize((string) $pageSlug),
                        'template_file' => $this->resolveTemplateFileForPage($slug, $pageTemplateFiles),
                        'sections' => [],
                    ];
                }

                $pages[$slug]['sections'] = $this->mergeSections($pages[$slug]['sections'], $sections);
            }
        }

        return array_values($pages);
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, string>
     */
    private function resolveTemplatePageFiles(?array $metadata): array
    {
        $pages = is_array(Arr::get($metadata, 'default_pages')) ? Arr::get($metadata, 'default_pages') : [];
        $map = [];

        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            $rawSlug = trim((string) ($page['slug'] ?? $page['key'] ?? ''));
            if ($rawSlug === '') {
                continue;
            }

            $slug = $this->slugify($rawSlug, $rawSlug);
            $templateFile = trim((string) ($page['template_file'] ?? ''));
            if ($templateFile === '') {
                continue;
            }

            $map[$slug] = ltrim($templateFile, '/');
        }

        return $map;
    }

    /**
     * @param  array<string, string>  $pageTemplateFiles
     * @return array<int, array{slug: string, title: string, template_file: string, sections: array<int, array<string, mixed>>}>
     */
    private function normalizePagesFromSite(
        ?Site $site,
        Template $template,
        array $pageTemplateFiles,
        ?string $locale,
        bool $draft
    ): array {
        if (! $site) {
            return [];
        }

        $site->loadMissing('project');
        if (! $site->project) {
            return [];
        }

        /**
         * Builder/chat previews can intentionally request a different shell slug than the project's
         * stored template (for example `default` instead of `ecommerce` when the home page contains
         * non-ecommerce sections). In that case we still need to render the site's real page revisions,
         * otherwise the preview falls back to generic demo content and selection/localId mapping breaks.
         */

        $pages = Page::query()
            ->where('site_id', $site->id)
            ->orderBy('id')
            ->with([
                'revisions' => function ($query): void {
                    $query
                        ->select(['id', 'page_id', 'content_json', 'published_at'])
                        ->orderByDesc('id');
                },
            ])
            ->get();

        if ($pages->isEmpty()) {
            return [];
        }

        $resolved = [];
        foreach ($pages as $index => $page) {
            $slug = $this->slugify((string) ($page->slug ?? ''), 'page-'.($index + 1));
            $title = trim((string) ($page->title ?? ''));
            if ($title === '') {
                $title = $this->humanize($slug);
            }

            $revision = $this->pickRevision($page, $draft);
            $sections = $this->extractSectionsFromRevisionPayload(
                $revision?->content_json ?? [],
                $locale,
                $site->locale
            );

            $resolved[] = [
                'slug' => $slug,
                'title' => $title,
                'template_file' => $this->resolveTemplateFileForPage($slug, $pageTemplateFiles),
                'sections' => $sections,
            ];
        }

        return $resolved;
    }

    private function pickRevision(Page $page, bool $draft): mixed
    {
        $revisions = $page->revisions;
        if (! $revisions || $revisions->isEmpty()) {
            return null;
        }

        if ($draft) {
            return $revisions->first();
        }

        return $revisions->first(static fn ($revision): bool => $revision->published_at !== null)
            ?? $revisions->first();
    }

    /**
     * @return array<int, array{key: string, enabled: bool, props: array<string, mixed>, localId?: string|null}>
     */
    private function extractSectionsFromRevisionPayload(
        mixed $revisionContent,
        ?string $locale,
        ?string $siteLocale
    ): array {
        $resolved = $this->localeResolver->resolvePayload($revisionContent, $locale, $siteLocale);
        $content = is_array($resolved['content'] ?? null) ? $resolved['content'] : [];
        $sections = $content['sections'] ?? [];

        return $this->normalizeSections($sections);
    }

    /**
     * @param  array<string, string>  $pageTemplateFiles
     */
    private function resolveTemplateFileForPage(string $slug, array $pageTemplateFiles): string
    {
        $templateFile = trim((string) ($pageTemplateFiles[$slug] ?? ''));

        return $templateFile !== '' ? $templateFile : 'index.html';
    }

    /**
     * Ensure canonical page coverage for vertical templates so demo navigation always includes required routes.
     *
     * @param  array<int, array{slug: string, title: string, sections: array<int, array<string, mixed>>}>  $pages
     * @param  array<string, string>  $pageTemplateFiles
     * @return array<int, array{slug: string, title: string, template_file: string, sections: array<int, array<string, mixed>>}>
     */
    private function ensureCanonicalTemplateCoveragePages(Template $template, array $pages, array $pageTemplateFiles): array
    {
        $isEcommerceTemplate = strtolower((string) $template->slug) === 'ecommerce'
            || strtolower((string) $template->category) === 'ecommerce';
        $isEkkaTemplate = strtolower((string) $template->slug) === 'ekka-demo-8';

        if (! $isEcommerceTemplate) {
            return array_map(function (array $page) use ($pageTemplateFiles): array {
                $slug = $this->slugify((string) ($page['slug'] ?? ''), 'home');

                return [
                    'slug' => $slug,
                    'title' => (string) ($page['title'] ?? $this->humanize($slug)),
                    'template_file' => (string) ($page['template_file'] ?? $this->resolveTemplateFileForPage($slug, $pageTemplateFiles)),
                    'sections' => $this->normalizeSections($page['sections'] ?? []),
                ];
            }, $pages);
        }

        $ekkaHomeSections = [
            'webu_general_heading_01', 'webu_ekka_header_01', 'webu_ekka_slider_01',
            'webu_ecom_category_list_01', 'webu_ekka_sidebar_categories_01', 'webu_ekka_sidebar_bestsellers_01',
            'webu_ekka_product_tabs_01', 'webu_ekka_deal_01', 'webu_ekka_new_products_tab_01',
            'webu_ecom_product_grid_01', 'webu_ekka_footer_01',
        ];
        $required = [
            ['slug' => 'home', 'title' => 'Home', 'sections' => $isEkkaTemplate ? $ekkaHomeSections : ['webu_general_heading_01', 'webu_general_text_01', 'webu_ecom_product_search_01', 'webu_ecom_category_list_01', 'webu_ecom_product_grid_01', 'webu_ecom_cart_icon_01']],
            ['slug' => 'shop', 'title' => 'Shop', 'sections' => ['webu_ecom_product_search_01', 'webu_ecom_category_list_01', 'webu_ecom_product_grid_01', 'webu_ecom_cart_icon_01']],
            ['slug' => 'product', 'title' => 'Product Detail', 'sections' => ['webu_ecom_product_gallery_01', 'webu_ecom_product_detail_01', 'webu_ecom_add_to_cart_button_01', 'webu_ecom_product_tabs_01']],
            ['slug' => 'cart', 'title' => 'Cart', 'sections' => ['webu_ecom_cart_icon_01', 'webu_ecom_cart_page_01', 'webu_ecom_coupon_ui_01', 'webu_ecom_order_summary_01']],
            ['slug' => 'checkout', 'title' => 'Checkout', 'sections' => ['webu_ecom_checkout_form_01', 'webu_ecom_shipping_selector_01', 'webu_ecom_payment_selector_01', 'webu_ecom_order_summary_01']],
            ['slug' => 'payments', 'title' => 'Payment Methods', 'sections' => ['webu_general_heading_01', 'webu_general_text_01', 'webu_ecom_payment_selector_01']],
            ['slug' => 'login', 'title' => 'Login / Register', 'sections' => ['webu_ecom_auth_01']],
            ['slug' => 'account', 'title' => 'My Account', 'sections' => ['webu_ecom_account_dashboard_01', 'webu_ecom_account_profile_01', 'webu_ecom_account_security_01']],
            ['slug' => 'orders', 'title' => 'Orders', 'sections' => ['webu_ecom_orders_list_01']],
            ['slug' => 'order', 'title' => 'Order Detail', 'sections' => ['webu_ecom_order_detail_01']],
            ['slug' => 'delivery-returns', 'title' => 'Delivery & Returns', 'sections' => ['webu_general_heading_01', 'webu_general_text_01', 'faq_accordion_plus']],
            ['slug' => 'contact', 'title' => 'Contact', 'sections' => ['contact_split_form', 'map_contact_block']],
        ];

        $existingBySlug = [];
        $extraPages = [];

        foreach ($pages as $page) {
            $slug = $this->slugify((string) ($page['slug'] ?? ''), 'page');
            $normalized = [
                'slug' => $slug,
                'title' => (string) ($page['title'] ?? $this->humanize($slug)),
                'template_file' => (string) ($page['template_file'] ?? $this->resolveTemplateFileForPage($slug, $pageTemplateFiles)),
                'sections' => $this->normalizeSections($page['sections'] ?? []),
            ];

            if (isset($existingBySlug[$slug])) {
                continue;
            }

            $existingBySlug[$slug] = $normalized;
            $extraPages[] = $slug;
        }

        $covered = [];
        foreach ($required as $requiredPage) {
            $slug = (string) $requiredPage['slug'];
            $requiredSections = $this->normalizeSections((array) $requiredPage['sections']);
            $existing = $existingBySlug[$slug] ?? null;

            if ($existing) {
                // Use the site's sections as-is for builder–demo parity (no merge with required defaults).
                $covered[] = [
                    'slug' => $slug,
                    'title' => $existing['title'] !== '' ? $existing['title'] : (string) $requiredPage['title'],
                    'template_file' => (string) ($existing['template_file'] ?? $this->resolveTemplateFileForPage($slug, $pageTemplateFiles)),
                    'sections' => $existing['sections'],
                ];
                unset($existingBySlug[$slug]);
                continue;
            }

            $covered[] = [
                'slug' => $slug,
                'title' => (string) $requiredPage['title'],
                'template_file' => $this->resolveTemplateFileForPage($slug, $pageTemplateFiles),
                'sections' => $requiredSections,
            ];
        }

        foreach ($extraPages as $slug) {
            if (! isset($existingBySlug[$slug])) {
                continue;
            }

            $covered[] = $existingBySlug[$slug];
        }

        return $covered;
    }

    /**
     * @param  mixed  $sections
     * @return array<int, array{key: string, enabled: bool, props: array<string, mixed>, localId?: string|null}>
     */
    private function normalizeSections(mixed $sections): array
    {
        if (! is_array($sections)) {
            return [];
        }

        $normalized = [];

        foreach ($sections as $index => $section) {
            $key = '';
            $enabled = true;
            $props = [];
            $localId = CmsSectionLocalId::fallbackForIndex((int) $index);

            if (is_string($section)) {
                $key = trim($section);
            } elseif (is_array($section)) {
                $key = trim((string) ($section['key'] ?? $section['type'] ?? 'section-'.$index));
                $enabled = (bool) ($section['enabled'] ?? true);
                $candidateProps = $section['props'] ?? [];
                if (is_array($candidateProps)) {
                    $props = $candidateProps;
                }
                $localId = CmsSectionLocalId::resolve($section, (int) $index);
            }

            if ($key === '') {
                continue;
            }

            $normalized[] = [
                'key' => $key,
                'enabled' => $enabled,
                'props' => $props,
                'localId' => $localId,
            ];
        }

        return $normalized;
    }

    /**
     * @param  array<int, array{key: string, enabled: bool, props: array<string, mixed>, localId?: string|null}>  $base
     * @param  array<int, array{key: string, enabled: bool, props: array<string, mixed>, localId?: string|null}>  $extra
     * @return array<int, array{key: string, enabled: bool, props: array<string, mixed>, localId?: string|null}>
     */
    private function mergeSections(array $base, array $extra): array
    {
        if ($extra === []) {
            return $base;
        }

        if ($base === []) {
            return $extra;
        }

        // Merge by index so that duplicate keys (e.g. multiple webu_general_heading_01) get the correct props.
        $merged = [];
        $baseValues = array_values($base);
        $extraValues = array_values($extra);
        $baseCount = count($baseValues);
        $extraCount = count($extraValues);

        for ($i = 0; $i < $baseCount; $i++) {
            $baseSection = $baseValues[$i] ?? ['key' => '', 'enabled' => true, 'props' => [], 'localId' => null];
            $extraSection = $extraValues[$i] ?? null;
            $key = (string) ($baseSection['key'] ?? '');
            $enabled = (bool) ($baseSection['enabled'] ?? true);
            $props = is_array($baseSection['props'] ?? null) ? $baseSection['props'] : [];
            $localId = is_string($baseSection['localId'] ?? null) && trim((string) $baseSection['localId']) !== ''
                ? trim((string) $baseSection['localId'])
                : null;

            if ($extraSection !== null && (string) ($extraSection['key'] ?? '') !== '') {
                $enabled = (bool) ($extraSection['enabled'] ?? $enabled);
                $extraProps = is_array($extraSection['props'] ?? null) ? $extraSection['props'] : [];
                $props = array_replace_recursive($props, $extraProps);
                $extraLocalId = is_string($extraSection['localId'] ?? null) && trim((string) $extraSection['localId']) !== ''
                    ? trim((string) $extraSection['localId'])
                    : null;
                if ($extraLocalId !== null) {
                    $localId = $extraLocalId;
                }
            }

            $merged[] = [
                'key' => $key !== '' ? $key : (string) ($extraSection['key'] ?? 'section-'.$i),
                'enabled' => $enabled,
                'props' => $props,
                'localId' => $localId,
            ];
        }

        for ($i = $baseCount; $i < $extraCount; $i++) {
            $merged[] = $extraValues[$i];
        }

        return $merged;
    }

    /**
     * @param  array{slug: string, title: string, sections: array<int, array<string, mixed>>}  $page
     * @param  array<string, mixed>  $assets
     * @return array<string, mixed>
     */
    private function enrichPage(
        Template $template,
        array $page,
        array $assets,
        int $pageIndex,
        ?Site $site = null,
        array $pageTemplateFiles = [],
        ?string $locale = null,
        bool $draft = false
    ): array
    {
        $sections = [];
        foreach ($page['sections'] as $sectionIndex => $section) {
            $sections[] = $this->buildSectionDemo($template, $page, $section, $assets, $pageIndex, (int) $sectionIndex, $site, $locale);
        }

        $slug = (string) ($page['slug'] ?? 'home');
        $templateFile = trim((string) ($page['template_file'] ?? $this->resolveTemplateFileForPage($slug, $pageTemplateFiles)));

        return [
            'slug' => $slug,
            'title' => $page['title'],
            'path' => $this->resolvePagePath($slug),
            'template_file' => $templateFile,
            'preview_url' => $site
                ? $this->resolvePreviewUrl($template, $site, $slug, $templateFile, $locale, $draft)
                : $this->resolvePreviewUrlTemplateDefault($template, $slug, $templateFile),
            'sections' => $sections,
        ];
    }

    private function resolvePreviewUrlTemplateDefault(Template $template, string $pageSlug, string $templateFile): string
    {
        $filePath = ltrim(trim($templateFile), '/');
        $routeParams = ['templateSlug' => $template->slug];
        if ($filePath !== '' && $filePath !== 'index.html') {
            $routeParams['path'] = $filePath;
        }
        $url = route('template-demos.show', $routeParams, false);

        return $url.'?'.http_build_query(['slug' => $pageSlug]);
    }

    private function resolvePagePath(string $slug): string
    {
        return $slug === '' || $slug === 'home'
            ? '/'
            : '/'.trim($slug, '/');
    }

    /**
     * Default home page with Ekka demo sections (used when template is ekka-demo-8 and metadata has no pages).
     *
     * @param  array<string, string>  $pageTemplateFiles
     * @return array<int, array{slug: string, title: string, template_file: string, sections: array<int, array{key: string, enabled: bool, props: array<string, mixed>}>}>
     */
    private function ekkaDefaultPages(array $pageTemplateFiles): array
    {
        $templateFile = $pageTemplateFiles['home'] ?? 'index.html';

        return [
            [
                'slug' => 'home',
                'title' => 'Home',
                'template_file' => $templateFile,
                'sections' => $this->normalizeSections([
                    'webu_general_heading_01',
                    'webu_ekka_header_01',
                    'webu_ekka_slider_01',
                    'webu_ecom_category_list_01',
                    'webu_ekka_sidebar_categories_01',
                    'webu_ekka_sidebar_bestsellers_01',
                    'webu_ekka_product_tabs_01',
                    'webu_ekka_deal_01',
                    'webu_ekka_new_products_tab_01',
                    'webu_ecom_product_grid_01',
                    'webu_ekka_footer_01',
                ]),
            ],
        ];
    }

    private function resolvePreviewUrl(
        Template $template,
        Site $site,
        string $pageSlug,
        string $templateFile,
        ?string $locale,
        bool $draft
    ): string {
        $filePath = ltrim(trim($templateFile), '/');
        $routeParams = ['templateSlug' => $template->slug];
        if ($filePath !== '') {
            $routeParams['path'] = $filePath;
        }

        $url = route('template-demos.show', $routeParams, false);
        $query = [
            'site' => (string) $site->id,
            'slug' => $pageSlug,
        ];

        $normalizedLocale = trim((string) $locale);
        if ($normalizedLocale !== '') {
            $query['locale'] = $normalizedLocale;
        }

        if ($draft) {
            $query['draft'] = '1';
        }

        return $url.'?'.http_build_query($query);
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $section
     * @param  array<string, mixed>  $assets
     * @return array<string, mixed>
     */
    private function buildSectionDemo(
        Template $template,
        array $page,
        array $section,
        array $assets,
        int $pageIndex,
        int $sectionIndex,
        ?Site $site = null,
        ?string $locale = null
    ): array {
        $key = (string) ($section['key'] ?? 'section-'.$sectionIndex);
        $props = is_array($section['props'] ?? null) ? $section['props'] : [];
        $component = $this->resolveComponent($key);
        $data = $this->buildComponentData(
            component: $component,
            key: $key,
            template: $template,
            page: $page,
            props: $props,
            assets: $assets,
            pageIndex: $pageIndex,
            sectionIndex: $sectionIndex
        );
        if ($props !== []) {
            $data = array_replace_recursive($data, $props);
        }
        if ($site !== null && strtolower($key) === 'webu_header_01') {
            $menuSource = trim((string) ($data['menu_source'] ?? ''));
            if ($menuSource === '') {
                $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
                $layout = is_array(Arr::get($themeSettings, 'layout')) ? Arr::get($themeSettings, 'layout') : [];
                $menuSource = $this->normalizeMenuKey(Arr::get($layout, 'header_menu_key'), 'header');
            }
            if ($menuSource !== '') {
                $menu = Menu::where('site_id', $site->id)->where('key', $menuSource)->first();
                if ($menu !== null && is_array($menu->items_json)) {
                    $siteLocale = $site->locale ?? 'ka';
                    $requestedLocale = $locale !== null && $locale !== '' ? $locale : $siteLocale;
                    $resolved = $this->localeResolver->resolvePayload($menu->items_json ?? [], $requestedLocale, $siteLocale);
                    $content = is_array($resolved['content'] ?? null) ? $resolved['content'] : [];
                    $data['menu_items'] = array_values(array_map(
                        fn (mixed $item): array => [
                            'label' => (string) (is_array($item) ? ($item['label'] ?? '') : ''),
                            'url' => (string) (is_array($item) ? ($item['url'] ?? '#') : '#'),
                        ],
                        $content
                    ));
                }
            }
        }
        $data = $this->enrichCanonicalSectionData(
            $template,
            $key,
            $data,
            $assets,
            (string) ($page['slug'] ?? '')
        );

        $layoutPrimitiveKeys = ['container', 'grid', 'section'];
        if (in_array(strtolower($key), $layoutPrimitiveKeys, true)) {
            $childSectionSources = is_array($data['sections'] ?? null) ? $data['sections'] : (is_array($props['sections'] ?? null) ? $props['sections'] : []);
            if ($childSectionSources !== []) {
                $normalizedChildren = $this->normalizeSections($childSectionSources);
                $data['sections'] = [];
                foreach ($normalizedChildren as $childIndex => $childSection) {
                    $data['sections'][] = $this->buildSectionDemo($template, $page, $childSection, $assets, $pageIndex, $childIndex, $site, $locale);
                }
            }
        }

        $label = $this->humanize($key);
        if ($key === 'webu_ecom_product_grid_01' && trim((string) ($data['title'] ?? '')) !== '') {
            $label = trim((string) $data['title']);
        }
        if ($key === 'webu_ecom_product_carousel_01' && trim((string) ($data['title'] ?? '')) !== '') {
            $label = trim((string) $data['title']);
        }

        return [
            'key' => $key,
            'label' => $label,
            'enabled' => (bool) ($section['enabled'] ?? true),
            'component' => $component,
            'props' => $props,
            'data' => $data,
            'localId' => is_string($section['localId'] ?? null) && trim((string) $section['localId']) !== ''
                ? trim((string) $section['localId'])
                : null,
        ];
    }

    /**
     * Build a single section demo for admin component preview (design workflow).
     * Uses ecommerce template and demo assets so the section renders with realistic data.
     * When no templates exist in DB, uses a synthetic template so preview still works.
     *
     * @return array{key: string, label: string, enabled: bool, component: string, props: array, data: array}|null
     */
    public function buildSingleSectionDemoForPreview(string $sectionKey): ?array
    {
        $template = Template::query()->where('slug', 'ecommerce')->first()
            ?? Template::query()->first();

        if (! $template) {
            $template = $this->syntheticTemplateForPreview();
        }

        $assets = $this->demoAssets(null, $template);
        $page = ['slug' => 'home', 'title' => 'Home', 'sections' => [['key' => $sectionKey, 'enabled' => true, 'props' => []]]];
        $section = ['key' => $sectionKey, 'enabled' => true, 'props' => []];

        return $this->buildSectionDemo($template, $page, $section, $assets, 0, 0);
    }

    /**
     * Build a single section demo for admin component preview with custom props (e.g. layout_variant).
     * Used to render each variant separately on the component library preview page.
     *
     * @param  array<string, mixed>  $props
     * @return array{key: string, label: string, enabled: bool, component: string, props: array, data: array}|null
     */
    public function buildSingleSectionDemoForPreviewWithProps(string $sectionKey, array $props = []): ?array
    {
        $template = Template::query()->where('slug', 'ecommerce')->first()
            ?? Template::query()->first();

        if (! $template) {
            $template = $this->syntheticTemplateForPreview();
        }

        $assets = $this->demoAssets(null, $template);
        $section = ['key' => $sectionKey, 'enabled' => true, 'props' => $props];

        return $this->buildSectionDemo(
            $template,
            ['slug' => 'home', 'title' => 'Home', 'sections' => [$section]],
            $section,
            $assets,
            0,
            0
        );
    }

    /**
     * Minimal template instance for admin component preview when no templates exist in DB.
     * Public so the controller can use it for theme tokens and view data.
     */
    public function getSyntheticTemplateForPreview(): Template
    {
        $template = new Template;
        $template->forceFill([
            'id' => null,
            'slug' => 'ecommerce',
            'name' => 'Webu',
            'description' => null,
            'thumbnail' => null,
            'category' => 'ecommerce',
            'metadata' => [],
        ]);

        return $template;
    }

    /**
     * @see getSyntheticTemplateForPreview()
     */
    private function syntheticTemplateForPreview(): Template
    {
        return $this->getSyntheticTemplateForPreview();
    }

    /**
     * Add backend-owned defaults for canonical section keys, so demo templates stay fully data-driven.
     *
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $assets
     * @return array<string, mixed>
     */
    private function enrichCanonicalSectionData(
        Template $template,
        string $sectionKey,
        array $data,
        array $assets,
        ?string $pageSlug = null
    ): array
    {
        $key = strtolower(trim($sectionKey));
        $normalizedPageSlug = Str::lower(trim((string) $pageSlug));
        $products = is_array($data['products'] ?? null) && $data['products'] !== []
            ? $data['products']
            : (is_array($assets['products'] ?? null) ? $assets['products'] : []);
        $categories = is_array($data['categories'] ?? null) && $data['categories'] !== []
            ? $data['categories']
            : (is_array($assets['categories'] ?? null) ? $assets['categories'] : []);
        $faqItems = is_array($data['items'] ?? null) && $data['items'] !== []
            ? $data['items']
            : (is_array($assets['faq'] ?? null) ? $assets['faq'] : []);
        $heroImage = (string) ($this->resolveThumbnailUrl($template->thumbnail) ?: ($assets['hero_images'][0] ?? ''));

        if ($key === 'hero') {
            $layoutVariant = Str::lower((string) ($data['layout_variant'] ?? $data['hero_variant'] ?? 'hero-1'));
            $defaults = [
                'headline' => 'Build your storefront with Webu',
                'subheading' => 'Products, cart, checkout, and payments are all mapped and editable.',
                'hero_cta_label' => 'Shop now',
                'hero_cta_url' => '/shop',
                'hero_cta_secondary_label' => '',
                'hero_cta_secondary_url' => '',
                'hero_image_url' => $heroImage,
                'hero_image_alt' => 'Hero',
                'layout_variant' => 'hero-1',
            ];
            if ($layoutVariant === 'hero-7') {
                $defaults = array_replace_recursive($defaults, [
                    'eyebrow' => 'Exclusive Finance Apps',
                    'headline' => "Financial Consult\nThat Leads you\nto Your Goals",
                    'subheading' => 'Finance can deliver newsitescover a moving experience like no other at Outgrid beyond merely business investmenr transporting items of manual tracking spreadWego shee.',
                    'hero_cta_label' => 'Take Our Services',
                    'hero_cta_url' => '/contact',
                    'hero_image_url' => 'https://www.radiustheme.com/demo/wordpress/themes/finwave/wp-content/uploads/2025/07/home11-herobanner.webp',
                    'hero_image_alt' => 'Finwave hero banner',
                    'hero_overlay_image_url' => 'https://www.radiustheme.com/demo/wordpress/themes/finwave/wp-content/uploads/2025/07/total-revenue-last-manth.webp',
                    'hero_overlay_image_alt' => 'Revenue overview card',
                    'hero_stat_value' => '15',
                    'hero_stat_unit' => 'k+',
                    'hero_stat_label' => 'Active Users',
                    'hero_stat_avatars' => [
                        ['url' => '/demo/people/person-1.svg', 'alt' => 'User 1'],
                        ['url' => '/demo/people/person-2.svg', 'alt' => 'User 2'],
                        ['url' => '/demo/people/person-3.svg', 'alt' => 'User 3'],
                        ['url' => '/demo/people/person-1.svg', 'alt' => 'User 4'],
                    ],
                    'layout_variant' => 'hero-7',
                ]);
            }

            return array_replace_recursive($defaults, $data);
        }

        $isHeaderKey = str_contains($key, 'header') && ! str_contains($key, 'footer');
        if ($key === 'header' || $isHeaderKey) {
            $layoutVariant = $data['layout_variant'] ?? $data['variant'] ?? 'header-1';
            $defaults = [
                'logo_text' => 'Webu Demo',
                'logo_url' => '/',
                'menu_items' => [
                    ['label' => 'მთავარი', 'url' => '/'],
                    ['label' => 'მაღაზია', 'url' => '/shop'],
                    ['label' => 'კონტაქტი', 'url' => '/contact'],
                ],
                'cta_label' => 'დაწყება',
                'cta_url' => '/contact',
                'layout_variant' => 'header-1',
            ];
            if ($layoutVariant === 'header-3') {
                $defaults['logo_text'] = 'Finwave';
                $defaults['menu_items'] = [
                    ['label' => 'Home', 'url' => '/'],
                    ['label' => 'Service', 'url' => '/service'],
                    ['label' => 'Pages', 'url' => '/pages'],
                    ['label' => 'Elements', 'url' => '/elements'],
                    ['label' => 'Blog', 'url' => '/blog'],
                    ['label' => 'Contact', 'url' => '/contact'],
                ];
                $defaults['top_bar_login_label'] = 'Log In';
                $defaults['top_bar_login_url'] = '/account/login';
                $defaults['top_bar_social_links'] = [
                    ['label' => 'Facebook', 'url' => 'https://facebook.com', 'icon' => 'facebook'],
                    ['label' => 'X', 'url' => 'https://x.com', 'icon' => 'x'],
                    ['label' => 'Instagram', 'url' => 'https://instagram.com', 'icon' => 'instagram'],
                    ['label' => 'Pinterest', 'url' => 'https://pinterest.com', 'icon' => 'pinterest'],
                ];
                $defaults['top_bar_location_text'] = 'Location: 57 Park Ave, New York';
                $defaults['top_bar_location_url'] = '/contact';
                $defaults['top_bar_email_text'] = 'Mail: info@gmail.com';
                $defaults['top_bar_email_url'] = 'mailto:info@gmail.com';
                $defaults['hotline_eyebrow'] = 'Hotline';
                $defaults['hotline_label'] = '+123-7767-8989';
                $defaults['hotline_url'] = 'tel:+12377678989';
                $defaults['search_url'] = '/search';
                $defaults['menu_drawer_side'] = 'right';
                $defaults['menu_drawer_title'] = 'Finwave';
                $defaults['menu_drawer_subtitle'] = 'Browse services, pages and utility links from the Finwave navigation.';
            }
            if ($layoutVariant === 'header-4') {
                $defaults['logo_text'] = 'machic®';
                $defaults['menu_items'] = [
                    ['label' => 'Home', 'url' => '/'],
                    ['label' => 'Shop', 'url' => '/shop'],
                    ['label' => 'Cell Phones', 'url' => '/cell-phones'],
                    ['label' => 'Headphones', 'url' => '/headphones'],
                    ['label' => 'Blog', 'url' => '/blog'],
                    ['label' => 'Contact', 'url' => '/contact'],
                ];
                $defaults['strip_right_links'] = [
                    ['label' => 'About Us', 'url' => '/about'],
                    ['label' => 'My account', 'url' => '/account'],
                    ['label' => 'Featured Products', 'url' => '/shop'],
                    ['label' => 'Wishlist', 'url' => '/wishlist'],
                ];
                $defaults['department_menu_items'] = [
                    ['label' => 'Cell Phones', 'url' => '/cell-phones', 'description' => 'Latest smartphones and mobile accessories'],
                    ['label' => 'Headphones', 'url' => '/headphones', 'description' => 'Wireless, studio and everyday audio picks'],
                    ['label' => 'Smart Watches', 'url' => '/smart-watches', 'description' => 'Wearables, fitness trackers and smart gear'],
                    ['label' => 'Cameras', 'url' => '/cameras', 'description' => 'Photo and video essentials for creators'],
                ];
                $defaults['top_bar_right_tracking'] = 'Order Tracking';
                $defaults['top_bar_right_tracking_url'] = '/account/orders';
                $defaults['top_bar_right_lang'] = 'English';
                $defaults['top_bar_right_currency'] = 'USD';
                $defaults['account_url'] = '/account';
                $defaults['search_url'] = '/search';
                $defaults['wishlist_url'] = '/wishlist';
                $defaults['cart_url'] = '/cart';
                $defaults['search_placeholder'] = 'Search your favorite product...';
                $defaults['search_category_label'] = 'All';
                $defaults['search_button_label'] = 'Search';
                $defaults['department_label'] = 'All Departments';
                $defaults['promo_eyebrow'] = 'Only this weekend';
                $defaults['promo_label'] = 'Super Discount';
                $defaults['promo_url'] = '/shop';
                $defaults['account_eyebrow'] = 'Sign In';
                $defaults['account_label'] = 'Account';
                $defaults['cart_label'] = 'Total';
                $defaults['menu_drawer_side'] = 'left';
                $defaults['menu_drawer_title'] = 'All Departments';
                $defaults['menu_drawer_subtitle'] = 'Browse departments, highlighted collections and key shopping destinations.';
                $defaults['wishlist_count'] = 16;
                $defaults['cart_count'] = 0;
                $defaults['cart_total'] = '$0.00';
            }
            if ($layoutVariant === 'header-5') {
                $defaults['logo_text'] = 'Clotya®';
                $defaults['menu_items'] = [
                    ['label' => 'HOME', 'url' => '/'],
                    ['label' => 'SHOP', 'url' => '/shop'],
                    ['label' => 'WOMEN', 'url' => '/women'],
                ];
                $defaults['top_strip_text'] = 'SUMMER SALE FOR ALL SWIM SUITS AND FREE EXPRESS INTERNATIONAL DELIVERY - OFF 50%!';
                $defaults['announcement_cta_label'] = 'SHOP NOW';
                $defaults['announcement_cta_url'] = '/shop';
                $defaults['account_url'] = '/account';
                $defaults['search_url'] = '/search';
                $defaults['wishlist_url'] = '/wishlist';
                $defaults['cart_url'] = '/cart';
                $defaults['menu_drawer_side'] = 'left';
                $defaults['menu_drawer_title'] = 'Clotya®';
                $defaults['menu_drawer_subtitle'] = 'Browse featured departments, collections and main navigation links.';
                $defaults['wishlist_count'] = 0;
                $defaults['cart_count'] = 0;
                $defaults['cart_total'] = '$0.00';
            }
            if ($layoutVariant === 'header-6') {
                $defaults['logo_text'] = 'Clotya®';
                $defaults['menu_items'] = [
                    ['label' => 'Home', 'url' => '/'],
                    ['label' => 'Shop', 'url' => '/shop'],
                    ['label' => 'Women', 'url' => '/women'],
                    ['label' => 'Men', 'url' => '/men'],
                    ['label' => 'Outerwear', 'url' => '/outerwear'],
                    ['label' => 'Blog', 'url' => '/blog'],
                    ['label' => 'Contact', 'url' => '/contact'],
                ];
                $defaults['top_strip_text'] = 'SUMMER SALE FOR ALL SWIM SUITS AND FREE EXPRESS INTERNATIONAL DELIVERY - OFF 50%!';
                $defaults['announcement_cta_label'] = 'SHOP NOW';
                $defaults['announcement_cta_url'] = '/shop';
                $defaults['top_bar_left_text'] = 'Free Shipping World wide for all orders over $199.';
                $defaults['top_bar_left_cta'] = 'Click and Shop Now.';
                $defaults['top_bar_left_cta_url'] = '/shop';
                $defaults['social_followers'] = '3.1M Followers';
                $defaults['social_url'] = '#';
                $defaults['top_bar_right_tracking'] = 'Order Tracking';
                $defaults['top_bar_right_tracking_url'] = '/account/orders';
                $defaults['top_bar_right_lang'] = 'English';
                $defaults['top_bar_right_currency'] = 'USD';
                $defaults['account_url'] = '/account';
                $defaults['search_url'] = '/search';
                $defaults['wishlist_url'] = '/wishlist';
                $defaults['cart_url'] = '/cart';
                $defaults['menu_drawer_side'] = 'left';
                $defaults['menu_drawer_title'] = 'Clotya®';
                $defaults['menu_drawer_subtitle'] = 'Browse featured departments, collections and utility pages.';
                $defaults['wishlist_count'] = 4;
                $defaults['cart_count'] = 0;
                $defaults['cart_total'] = '$0.00';
            }
            if ($layoutVariant === 'header-7') {
                $defaults['logo_text'] = 'GLOWING';
                $defaults['menu_items'] = [
                    ['label' => 'HOME', 'url' => '/'],
                    ['label' => 'ELEMENTS', 'url' => '/elements'],
                    ['label' => 'SHOP', 'url' => '/shop'],
                    ['label' => 'BLOG', 'url' => '/blog'],
                    ['label' => 'PAGES', 'url' => '/pages'],
                ];
                $defaults['top_strip_text'] = 'FREE SHIPPING ON ALL U.S. ORDERS $50+';
            }

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_general_offcanvas_menu_01') {
            $defaults = [
                'trigger_label' => 'Open menu',
                'title' => 'Shop navigation',
                'subtitle' => 'Reusable drawer for desktop hamburger and mobile navigation.',
                'description' => 'Trigger and panel are bundled so the same component can be reused in headers, sidebars and mobile menus.',
                'side' => 'left',
                'open_by_default' => true,
                'show_close' => true,
                'footer_label' => 'Shop all',
                'footer_url' => '/shop',
                'menu_items' => [
                    ['label' => 'New arrivals', 'url' => '/shop', 'description' => 'Fresh seasonal edits'],
                    ['label' => 'Women', 'url' => '/women', 'description' => 'Curated womenswear'],
                    ['label' => 'Outerwear', 'url' => '/outerwear', 'description' => 'Layering essentials'],
                    ['label' => 'Contact', 'url' => '/contact', 'description' => 'Store support'],
                ],
            ];

            return array_replace_recursive($defaults, $data);
        }

        $isFooterKey = str_contains($key, 'footer');
        if ($key === 'footer' || $isFooterKey) {
            $defaults = [
                'logo_text' => 'Webu Demo',
                'logo_url' => '/',
                'menus' => [
                    'ლინკები' => [
                        ['label' => 'კონფიდენციალობა', 'url' => '/privacy'],
                        ['label' => 'კონტაქტი', 'url' => '/contact'],
                    ],
                    'მაღაზია' => [
                        ['label' => 'კატალოგი', 'url' => '/shop'],
                        ['label' => 'კალათა', 'url' => '/cart'],
                    ],
                ],
                'contact_address' => 'თბილისი, საქართველო',
                'copyright' => '© ' . date('Y') . ' Webu Demo. ყველა უფლება დაცულია.',
                'layout_variant' => 'footer-1',
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'banner') {
            $defaults = [
                'title' => 'Special offer',
                'subtitle' => 'Discover our best deals.',
                'cta_label' => 'Learn more',
                'cta_url' => '/shop',
            ];

            return array_replace_recursive($defaults, $data);
        }

        $isHeroSplitImage = $key === 'hero_split_image' || str_contains($key, 'hero_split');
        if ($isHeroSplitImage) {
            $defaults = [
                'left_background_color' => '#e8ffbd',
                'right_background_color' => '#e8d0ff',
                'right_image_url' => $heroImage ?: 'https://images.unsplash.com/photo-1571330735066-03aaa9429d89?auto=format&fit=crop&w=800&q=80',
                'right_image_alt' => 'Hero image',
                'slides' => [
                    [
                        'eyebrow' => 'Exclusive Offer',
                        'badge_text' => '-20% Off',
                        'headline' => 'Super Fast Performance',
                        'description' => 'We have prepared special discounts for you on electronic products. Don\'t miss these opportunities.',
                        'cta_label' => 'Shop Now',
                        'cta_url' => '/shop',
                        'image_url' => '',
                        'image_alt' => '',
                    ],
                ],
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_general_heading_01') {
            $layoutVariant = Str::lower((string) ($data['layout_variant'] ?? $data['hero_variant'] ?? 'hero-1'));
            $homeDefaults = [
                'badge' => 'New Collection',
                'headline' => 'Make your fashion look more charming',
                'subheading' => 'Sell globally in minutes with localized currencies, languages, and experiences in every market.',
                'chips' => ['Catalog', 'Cart', 'Checkout', 'Payments', 'Orders'],
                'hero_variant' => 'fashion_split_hero',
                'left_image_url' => 'https://images.unsplash.com/photo-1521572163474-6864f9cf17ab?auto=format&fit=crop&w=1100&q=80',
                'left_image_alt' => 'Fashion model left',
                'right_image_url' => 'https://images.unsplash.com/photo-1487412720507-e7ab37603c6f?auto=format&fit=crop&w=1100&q=80',
                'right_image_alt' => 'Fashion model right',
                'hero_cta_label' => 'ADD TO CART',
                'hero_cta_url' => '/cart',
                'hero_cta_secondary_label' => 'VIEW DETAILS',
                'hero_cta_secondary_url' => '/shop',
                'top_strip_text' => 'Autumn Collection. A New Season. A New Perspective. Buy Now!',
                'contact_phone' => '+1800 354 4321',
                'contact_email' => 'info@fashionshop.com',
                'brand_text' => 'ORIMA.',
            ];
            $finwaveDefaults = [
                'eyebrow' => 'Exclusive Finance Apps',
                'headline' => "Financial Consult\nThat Leads you\nto Your Goals",
                'subheading' => 'Finance can deliver newsitescover a moving experience like no other at Outgrid beyond merely business investmenr transporting items of manual tracking spreadWego shee.',
                'layout_variant' => 'hero-7',
                'hero_image_url' => 'https://www.radiustheme.com/demo/wordpress/themes/finwave/wp-content/uploads/2025/07/home11-herobanner.webp',
                'hero_image_alt' => 'Finwave hero banner',
                'hero_overlay_image_url' => 'https://www.radiustheme.com/demo/wordpress/themes/finwave/wp-content/uploads/2025/07/total-revenue-last-manth.webp',
                'hero_overlay_image_alt' => 'Revenue overview card',
                'hero_cta_label' => 'Take Our Services',
                'hero_cta_url' => '/contact',
                'hero_cta_secondary_label' => '',
                'hero_cta_secondary_url' => '',
                'hero_stat_value' => '15',
                'hero_stat_unit' => 'k+',
                'hero_stat_label' => 'Active Users',
                'hero_stat_avatars' => [
                    ['url' => '/demo/people/person-1.svg', 'alt' => 'User 1'],
                    ['url' => '/demo/people/person-2.svg', 'alt' => 'User 2'],
                    ['url' => '/demo/people/person-3.svg', 'alt' => 'User 3'],
                    ['url' => '/demo/people/person-1.svg', 'alt' => 'User 4'],
                ],
            ];
            $defaults = [
                'badge' => 'Storefront',
                'headline' => 'Build your ecommerce storefront with canonical builder sections.',
                'subheading' => 'Products, cart, checkout, payments, account, and orders are all mapped and editable.',
                'chips' => ['Catalog', 'Cart', 'Checkout', 'Payments', 'Orders'],
                'hero_variant' => 'classic',
                'layout_variant' => 'hero-1',
                'hero_image_url' => $heroImage,
                'hero_image_alt' => 'Storefront hero',
                'hero_cta_label' => 'Shop now',
                'hero_cta_url' => '/shop',
                'hero_cta_secondary_label' => '',
                'hero_cta_secondary_url' => '',
                'top_strip_text' => 'Autumn Collection. A New Season. A New Perspective. Buy Now!',
                'contact_phone' => '+1800 354 4321',
                'contact_email' => 'info@fashionshop.com',
                'brand_text' => 'ORIMA.',
            ];

            if ($normalizedPageSlug === 'home') {
                $defaults = array_replace_recursive($defaults, $homeDefaults);
            }
            if ($layoutVariant === 'hero-7') {
                $defaults = array_replace_recursive($defaults, $finwaveDefaults);
            }

            $isEkka = strtolower((string) $template->slug) === 'ekka-demo-8';
            if ($isEkka) {
                $defaults['follow_label'] = 'Follow us on:';
                $defaults['social_links'] = [
                    ['label' => 'Facebook', 'url' => '#', 'icon' => 'eci-facebook', 'class' => 'hdr-facebook'],
                    ['label' => 'Twitter', 'url' => '#', 'icon' => 'eci-twitter', 'class' => 'hdr-twitter'],
                    ['label' => 'Instagram', 'url' => '#', 'icon' => 'eci-instagram', 'class' => 'hdr-instagram'],
                    ['label' => 'LinkedIn', 'url' => '#', 'icon' => 'eci-linkedin', 'class' => 'hdr-linkedin'],
                ];
                $defaults['logo_url'] = '';
                $defaults['logo_dark_url'] = '';
            }

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_general_text_01') {
            $defaults = [
                'body' => 'This storefront uses canonical webu components so each page can be edited in builder without breaking product, order, and payment bindings.',
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_product_search_01') {
            $dynamicQueryPreview = $this->buildProductSearchPreview($products);
            $dynamicTrending = $this->buildTrendingTokens($products);
            $defaults = [
                'search_label' => 'Search query',
                'query_preview' => $dynamicQueryPreview,
                'scope_label' => 'Search scope',
                'scope_options' => ['All products', 'Category only', 'Title + SKU'],
                'trending' => $dynamicTrending,
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_category_list_01') {
            $defaults = [
                'categories' => $categories,
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_product_grid_01') {
            $defaults = [
                'products' => $products,
                'add_to_cart_label' => 'Add to Cart',
                'title' => 'Most popular products',
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_product_carousel_01') {
            $defaults = [
                'products' => $products,
                'add_to_cart_label' => 'Add to Cart',
                'title' => 'Featured Products',
                'subtitle' => 'Discover our handpicked selection.',
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ekka_slider_01') {
            $defaultSlides = [
                [
                    'stitle' => 'Sale offer',
                    'title' => 'New Fashion Summer Sale',
                    'desc' => 'starting at $ 29.99',
                    'cta_label' => 'Shop Now',
                    'cta_url' => '#',
                    'slide_class' => 'slide-1',
                    'image' => ($assets['hero_images'][0] ?? '').'',
                ],
                [
                    'stitle' => 'Trending item',
                    'title' => "Women's latest fashion sale",
                    'desc' => 'starting at $ 20.00',
                    'cta_label' => 'Shop Now',
                    'cta_url' => '#',
                    'slide_class' => 'slide-2',
                    'image' => ($assets['hero_images'][1] ?? $assets['hero_images'][0] ?? '').'',
                ],
                [
                    'stitle' => 'Trending accessories',
                    'title' => 'Modern Sunglasses',
                    'desc' => 'starting at $ 15.00',
                    'cta_label' => 'Shop Now',
                    'cta_url' => '#',
                    'slide_class' => 'slide-3',
                    'image' => ($assets['hero_images'][2] ?? $assets['hero_images'][0] ?? '').'',
                ],
            ];
            $defaults = [
                'slides' => is_array($data['slides'] ?? null) && $data['slides'] !== [] ? $data['slides'] : $defaultSlides,
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ekka_header_01') {
            $defaults = [
                'nav_links' => [
                    ['label' => 'Home', 'url' => '#'],
                    ['label' => 'Categories', 'url' => '#'],
                    ['label' => 'Products', 'url' => '#'],
                    ['label' => 'Pages', 'url' => '#'],
                    ['label' => 'Contact', 'url' => '#'],
                ],
                'search_placeholder' => 'Enter Your Product Name...',
                'wishlist_count' => 0,
                'cart_count' => 0,
                'user_menu_links' => [
                    ['label' => 'Register', 'url' => '#'],
                    ['label' => 'Checkout', 'url' => '#'],
                    ['label' => 'Login', 'url' => '#'],
                ],
                'currency_label' => 'Currency',
                'language_label' => 'Language',
                'currency_options' => [
                    ['label' => 'USD $', 'url' => '#', 'active' => true],
                    ['label' => 'EUR €', 'url' => '#', 'active' => false],
                ],
                'language_options' => [
                    ['label' => 'English', 'url' => '#', 'active' => true],
                    ['label' => 'Italiano', 'url' => '#', 'active' => false],
                ],
                'cart_title' => 'My Cart',
                'cart_empty_text' => 'Your cart is empty.',
                'view_cart_label' => 'View Cart',
                'checkout_label' => 'Checkout',
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ekka_sidebar_categories_01') {
            $defaults = [
                'title' => 'Category',
                'groups' => [
                    ['label' => 'Clothes', 'icon' => 'dress-8.png', 'children' => [
                        ['label' => 'Shirt', 'url' => '#', 'count' => '25'],
                        ['label' => 'shorts & jeans', 'url' => '#', 'count' => '52'],
                        ['label' => 'jacket', 'url' => '#', 'count' => '500'],
                        ['label' => 'dress & frock', 'url' => '#', 'count' => '35'],
                    ]],
                    ['label' => 'Footwear', 'icon' => 'shoes-8.png', 'children' => [
                        ['label' => 'Sports', 'url' => '#', 'count' => '25'],
                        ['label' => 'Formal', 'url' => '#', 'count' => '52'],
                        ['label' => 'Casual', 'url' => '#', 'count' => '40'],
                        ['label' => 'safety shoes', 'url' => '#', 'count' => '35'],
                    ]],
                    ['label' => 'jewelry', 'icon' => 'jewelry-8.png', 'children' => [
                        ['label' => 'Earrings', 'url' => '#', 'count' => '50'],
                        ['label' => 'Couple Rings', 'url' => '#', 'count' => '35'],
                        ['label' => 'Necklace', 'url' => '#', 'count' => '40'],
                    ]],
                ],
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ekka_sidebar_bestsellers_01') {
            $defaults = [
                'title' => 'Best Sellers',
                'products' => array_slice($products, 0, 8),
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ekka_product_tabs_01') {
            $chunk = array_chunk($products, 4);
            $defaults = [
                'tabs' => [
                    ['id' => 'new', 'label' => 'New Arrivals', 'products' => $chunk[0] ?? []],
                    ['id' => 'trending', 'label' => 'Trending', 'products' => $chunk[1] ?? []],
                    ['id' => 'top', 'label' => 'Top Rated', 'products' => $chunk[2] ?? $chunk[0] ?? []],
                ],
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ekka_deal_01') {
            $dealProducts = array_slice($products, 0, 2);
            $defaultItems = [
                [
                    'title' => 'Rose Gold diamonds Earring',
                    'url' => '#',
                    'image_url' => '',
                    'description' => 'Lorem ipsum dolor sit amet consectetur.',
                    'new_price' => '$1990.00',
                    'old_price' => '$2000.00',
                    'sold' => 15,
                    'available' => 40,
                    'countdown_id' => 'ec-spe-count-1',
                ],
                [
                    'title' => 'Shampoo, conditioner & facewash packs',
                    'url' => '#',
                    'image_url' => '',
                    'description' => 'Lorem ipsum dolor sit amet consectetur.',
                    'new_price' => '$150.00',
                    'old_price' => '$200.00',
                    'sold' => 20,
                    'available' => 40,
                    'countdown_id' => 'ec-spe-count-2',
                ],
            ];
            if ($dealProducts !== []) {
                $defaultItems = array_map(static function (array $p): array {
                    return [
                        'title' => $p['name'] ?? 'Deal product',
                        'url' => $p['url'] ?? $p['slug'] ?? '#',
                        'image_url' => $p['image_url'] ?? $p['image'] ?? '',
                        'description' => 'Lorem ipsum dolor sit amet consectetur.',
                        'new_price' => $p['price'] ?? $p['formatted_price'] ?? '$0',
                        'old_price' => $p['old_price'] ?? null,
                        'sold' => 15,
                        'available' => 40,
                        'countdown_id' => 'ec-spe-count-1',
                    ];
                }, $dealProducts);
            }
            $defaults = [
                'title' => 'Deal of the day',
                'items' => is_array($data['items'] ?? null) && $data['items'] !== [] ? $data['items'] : $defaultItems,
                'add_to_cart_label' => 'Add To Cart',
                'sold_label' => 'Already Sold:',
                'available_label' => 'Available:',
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ekka_new_products_tab_01') {
            $defaults = [
                'title' => 'New Products',
                'tab_labels' => [
                    ['id' => 'all', 'label' => 'All'],
                    ['id' => 'clothes', 'label' => 'Clothes'],
                    ['id' => 'footwear', 'label' => 'Footwear'],
                    ['id' => 'accessories', 'label' => 'accessories'],
                ],
                'products' => $products,
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ekka_footer_01') {
            $defaults = [
                'columns' => [
                    ['heading' => 'Popular Categories', 'links' => [
                        ['label' => 'Fashion', 'url' => '#'],
                        ['label' => 'Electronic', 'url' => '#'],
                        ['label' => 'Cosmetic', 'url' => '#'],
                        ['label' => 'Health', 'url' => '#'],
                        ['label' => 'Watches', 'url' => '#'],
                    ]],
                    ['heading' => 'Products', 'links' => [
                        ['label' => 'Prices drop', 'url' => '#'],
                        ['label' => 'New products', 'url' => '#'],
                        ['label' => 'Best sales', 'url' => '#'],
                        ['label' => 'Contact us', 'url' => '#'],
                        ['label' => 'Sitemap', 'url' => '#'],
                    ]],
                    ['heading' => 'Our Company', 'links' => [
                        ['label' => 'Delivery', 'url' => '#'],
                        ['label' => 'Legal Notice', 'url' => '#'],
                        ['label' => 'Terms and conditions', 'url' => '#'],
                        ['label' => 'About us', 'url' => '#'],
                        ['label' => 'Secure payment', 'url' => '#'],
                    ]],
                    ['heading' => 'Services', 'links' => [
                        ['label' => 'Support', 'url' => '#'],
                        ['label' => 'FAQ', 'url' => '#'],
                        ['label' => 'Warranty', 'url' => '#'],
                    ]],
                ],
                'copyright_text' => '© '.date('Y').' '.config('app.name', 'Webu').'. All rights reserved.',
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_cart_icon_01') {
            $miniItems = array_slice($products, 0, 2);
            $estimatedSubtotal = $this->formatGel(array_sum(array_map(
                fn (array $item): float => $this->parseMoneyValue($item['price'] ?? null),
                $miniItems
            )));
            $defaults = [
                'items_label' => 'Items in cart',
                'items_count' => count($miniItems),
                'subtotal_label' => 'Estimated subtotal',
                'estimated_subtotal' => $estimatedSubtotal,
                'chips' => ['Mini cart', 'Quick checkout'],
                'products' => $products,
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_product_gallery_01') {
            $main = $products[0] ?? [];
            $galleryItems = array_slice($products, 0, 4);
            $defaults = [
                'main_image_url' => (string) ($main['image_url'] ?? ($assets['gallery_images'][0] ?? '')),
                'main_image_alt' => 'Main product image',
                'gallery_items' => array_map(static function (array $item): array {
                    return [
                        'image_url' => $item['image_url'] ?? '',
                        'alt' => (string) ($item['name'] ?? 'Gallery image'),
                    ];
                }, $galleryItems),
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_product_detail_01') {
            $product = $products[0] ?? [];
            $defaults = [
                'product' => [
                    'name' => $product['name'] ?? 'Product detail',
                    'sku' => $product['sku'] ?? 'SKU-000',
                    'price' => $product['price'] ?? '0 GEL',
                    'old_price' => $product['old_price'] ?? null,
                    'stock_text' => 'Stock: 24',
                ],
                'description' => 'Technical specs, short description, and dynamic variant pricing can be controlled from this component.',
                'highlights' => [
                    ['label' => 'Variant', 'value' => 'Default'],
                    ['label' => 'Delivery ETA', 'value' => '2-3 days'],
                    ['label' => 'Return policy', 'value' => '14 days'],
                ],
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_add_to_cart_button_01') {
            $defaults = [
                'quantity_label' => 'Quantity',
                'quantity_default' => 1,
                'add_to_cart_label' => 'Add to Cart',
                'wishlist_label' => 'Wishlist',
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_product_tabs_01') {
            $defaults = [
                'tabs' => ['Description', 'Specifications', 'Reviews'],
                'description' => 'Tab content area. This block usually renders dynamic product fields, specs matrix, and customer feedback list.',
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_cart_page_01') {
            $cartItems = array_map(static function (array $item): array {
                return [
                    'name' => $item['name'] ?? 'Item',
                    'price' => $item['price'] ?? '0 GEL',
                    'qty' => 1,
                    'total' => $item['price'] ?? '0 GEL',
                ];
            }, array_slice($products, 0, 3));
            $defaults = [
                'table_headers' => ['Product', 'Price', 'Qty', 'Total'],
                'cart_items' => $cartItems,
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_coupon_ui_01') {
            $defaults = [
                'coupon_label' => 'Coupon code',
                'coupon_preview' => 'WELCOME10',
                'apply_label' => 'Apply coupon',
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_order_summary_01') {
            $cartItems = array_slice($products, 0, 3);
            $subtotal = array_sum(array_map(
                fn (array $item): float => $this->parseMoneyValue($item['price'] ?? null),
                $cartItems
            ));
            $shipping = 15.0;
            $tax = round($subtotal * 0.18, 2);
            $total = $subtotal + $shipping + $tax;
            $defaults = [
                'rows' => [
                    ['label' => 'Subtotal', 'value' => $this->formatGel($subtotal)],
                    ['label' => 'Shipping', 'value' => $this->formatGel($shipping)],
                    ['label' => 'Tax', 'value' => $this->formatGel($tax)],
                ],
                'total_label' => 'Total',
                'total_value' => $this->formatGel($total),
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_checkout_form_01') {
            $defaults = [
                'fields' => [
                    ['label' => 'First name', 'value' => 'Nino'],
                    ['label' => 'Last name', 'value' => 'Giorgadze'],
                    ['label' => 'Email', 'value' => 'demo.customer@webu.local'],
                    ['label' => 'Phone', 'value' => '+995 555 00 00 00'],
                    ['label' => 'Address', 'value' => 'Tbilisi, Demo Street 12', 'full_width' => true],
                ],
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_shipping_selector_01') {
            $defaults = [
                'options' => [
                    ['label' => 'Standard delivery', 'price' => '15 GEL', 'selected' => true],
                    ['label' => 'Express delivery', 'price' => '25 GEL', 'selected' => false],
                    ['label' => 'Pickup point', 'price' => 'Free', 'selected' => false],
                ],
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_payment_selector_01') {
            $defaults = [
                'options' => [
                    ['label' => 'Card payment', 'status' => 'Enabled', 'status_variant' => 'ok', 'selected' => true],
                    ['label' => 'Cash on delivery', 'status' => 'Conditional', 'status_variant' => 'warn', 'selected' => false],
                    ['label' => 'Installments', 'status' => 'Enabled', 'status_variant' => 'ok', 'selected' => false],
                ],
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_auth_01') {
            $defaults = [
                'login' => [
                    'title' => 'Login',
                    'email_label' => 'Email',
                    'email_value' => 'customer@example.com',
                    'password_label' => 'Password',
                    'password_value' => '********',
                    'button_label' => 'Sign in',
                ],
                'register' => [
                    'title' => 'Register',
                    'name_label' => 'Full name',
                    'name_value' => 'New User',
                    'email_label' => 'Email',
                    'email_value' => 'new.user@example.com',
                    'button_label' => 'Create account',
                ],
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_account_dashboard_01') {
            $defaults = [
                'cards' => [
                    ['title' => 'Open orders', 'value' => '3 active'],
                    ['title' => 'Saved addresses', 'value' => '2 addresses'],
                    ['title' => 'Loyalty points', 'value' => '1,420 pts'],
                ],
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_account_profile_01') {
            $defaults = [
                'fields' => [
                    ['label' => 'Full name', 'value' => 'Nino Giorgadze'],
                    ['label' => 'Phone', 'value' => '+995 555 00 00 00'],
                    ['label' => 'Marketing preference', 'value' => 'Subscribed', 'full_width' => true],
                ],
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_account_security_01') {
            $defaults = [
                'policy_title' => 'Password policy',
                'policy_items' => ['Min length: 10', '2FA: Enabled', 'Recent sessions: 4'],
                'actions_title' => 'Security actions',
                'action_chips' => ['Reset password', 'Revoke all sessions', 'Backup codes'],
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_orders_list_01') {
            $defaults = [
                'headers' => ['Order', 'Date', 'Status', 'Total'],
                'orders' => [
                    ['number' => '#10045', 'date' => '2026-02-20', 'status' => 'Delivered', 'status_variant' => 'ok', 'total' => '428.00 GEL'],
                    ['number' => '#10044', 'date' => '2026-02-18', 'status' => 'In Transit', 'status_variant' => 'warn', 'total' => '179.00 GEL'],
                    ['number' => '#10043', 'date' => '2026-02-14', 'status' => 'Payment Failed', 'status_variant' => 'err', 'total' => '89.00 GEL'],
                ],
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'webu_ecom_order_detail_01') {
            $defaults = [
                'headers' => ['Item', 'Qty', 'Price'],
                'items' => array_map(static function (array $item): array {
                    return [
                        'name' => $item['name'] ?? 'Product',
                        'qty' => 1,
                        'price' => $item['price'] ?? '0 GEL',
                    ];
                }, array_slice($products, 0, 2)),
                'timeline_title' => 'Order timeline',
                'timeline' => [
                    ['label' => 'Placed', 'value' => '2026-02-18 11:20'],
                    ['label' => 'Packed', 'value' => '2026-02-18 16:10'],
                    ['label' => 'Shipped', 'value' => '2026-02-19 09:30'],
                    ['label' => 'Delivered', 'value' => '2026-02-20 14:15'],
                ],
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'faq_accordion_plus') {
            $defaults = [
                'items' => $faqItems,
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'contact_split_form') {
            $defaults = [
                'form' => [
                    'title' => 'Contact form',
                    'fields' => [
                        ['label' => 'Name', 'value' => 'Demo User'],
                        ['label' => 'Email', 'value' => 'demo@example.com'],
                    ],
                    'button_label' => 'Send message',
                ],
                'channels' => [
                    'title' => 'Support channels',
                    'items' => ['Email: support@demo.store', 'Phone: +995 555 11 22 33', 'Hours: 10:00 - 19:00'],
                ],
            ];

            return array_replace_recursive($defaults, $data);
        }

        if ($key === 'map_contact_block') {
            $defaults = [
                'image_url' => (string) ($assets['gallery_images'][0] ?? ''),
                'image_alt' => 'Map preview',
                'title' => 'Pickup point',
                'address' => 'Tbilisi, Demo Street 12',
                'chips' => ['Parking available', 'Same-day pickup'],
            ];

            return array_replace_recursive($defaults, $data);
        }

        return $data;
    }

    private function parseMoneyValue(mixed $value): float
    {
        $raw = is_string($value) || is_numeric($value)
            ? (string) $value
            : '';
        $normalized = preg_replace('/[^0-9.]/', '', $raw) ?: '0';
        $parsed = (float) $normalized;

        return is_finite($parsed) ? $parsed : 0.0;
    }

    private function formatGel(float $amount): string
    {
        return number_format($amount, 2).' GEL';
    }

    /**
     * @param  array<string, mixed>  $page
     * @param  array<string, mixed>  $props
     * @param  array<string, mixed>  $assets
     * @return array<string, mixed>
     */
    private function buildComponentData(
        string $component,
        string $key,
        Template $template,
        array $page,
        array $props,
        array $assets,
        int $pageIndex,
        int $sectionIndex
    ): array {
        $templateName = trim((string) $template->name) !== '' ? (string) $template->name : 'Template';
        $pageTitle = (string) ($page['title'] ?? 'Demo Page');

        $heroImage = $assets['hero_images'][($pageIndex + $sectionIndex) % max(count($assets['hero_images']), 1)] ?? null;

        return match ($component) {
            'hero' => [
                'eyebrow' => Arr::get($props, 'eyebrow', 'Featured'),
                'headline' => Arr::get($props, 'headline', "{$templateName} demo for {$pageTitle}"),
                'subtitle' => Arr::get($props, 'subtitle', "Explore {$templateName} through a polished {$pageTitle} experience."),
                'image_url' => $heroImage,
                'primary_cta' => Arr::get($props, 'primary_cta', ['label' => 'Start now', 'url' => '/contact']),
                'secondary_cta' => Arr::get($props, 'secondary_cta', ['label' => 'View more', 'url' => '/shop']),
            ],
            'logos' => [
                'title' => Arr::get($props, 'title', 'Trusted partners'),
                'logos' => $assets['logos'],
            ],
            'products' => [
                'title' => Arr::get($props, 'title', 'Featured products'),
                'collection' => Arr::get($props, 'collection', 'default'),
                'show_filters' => (bool) Arr::get($props, 'show_filters', true),
                'products' => $assets['products'],
            ],
            'categories' => [
                'title' => Arr::get($props, 'title', 'Categories'),
                'categories' => Arr::get($props, 'categories', $assets['categories']),
            ],
            'trust' => [
                'title' => Arr::get($props, 'title', 'Why choose us'),
                'badges' => Arr::get($props, 'badges', $assets['badges']),
            ],
            'testimonials' => [
                'title' => Arr::get($props, 'title', 'Customer feedback'),
                'autoplay' => (bool) Arr::get($props, 'autoplay', false),
                'items' => Arr::get($props, 'items', $assets['testimonials']),
            ],
            'faq' => [
                'title' => Arr::get($props, 'title', 'FAQ'),
                'items' => Arr::get($props, 'items', $assets['faq']),
            ],
            'contact' => [
                'title' => Arr::get($props, 'title', 'Contact us'),
                'contact' => Arr::get($props, 'contact', [
                    'email' => 'demo@example.com',
                    'phone' => '+995 555 00 00 00',
                    'address' => 'Tbilisi, Georgia',
                ]),
                'form_fields' => ['name', 'email', 'phone', 'message'],
            ],
            'map' => [
                'title' => Arr::get($props, 'title', 'Location'),
                'city' => 'Tbilisi',
                'address' => 'Demo Street 12',
                'image_url' => $assets['gallery_images'][0] ?? null,
            ],
            'stats' => [
                'title' => Arr::get($props, 'title', 'Key metrics'),
                'items' => Arr::get($props, 'items', [
                    ['label' => 'Projects', 'value' => '180+'],
                    ['label' => 'Clients', 'value' => '120+'],
                    ['label' => 'NPS', 'value' => '74'],
                ]),
            ],
            'team' => [
                'title' => Arr::get($props, 'title', 'Team'),
                'members' => $assets['team'],
            ],
            'timeline' => [
                'title' => Arr::get($props, 'title', 'Process timeline'),
                'steps' => Arr::get($props, 'steps', [
                    ['title' => 'Discovery', 'description' => 'Goals and constraints are aligned.'],
                    ['title' => 'Build', 'description' => 'Implementation with reusable sections.'],
                    ['title' => 'Launch', 'description' => 'Go-live and continuous optimization.'],
                ]),
            ],
            'gallery' => [
                'title' => Arr::get($props, 'title', 'Visual gallery'),
                'items' => $assets['gallery'],
            ],
            'cta' => [
                'headline' => Arr::get($props, 'headline', 'Ready to launch?'),
                'subtitle' => Arr::get($props, 'subtitle', 'This CTA comes from backend-generated demo payload.'),
                'button' => Arr::get($props, 'button', ['label' => 'Get started', 'url' => '/contact']),
            ],
            'table' => [
                'title' => Arr::get($props, 'title', 'Comparison'),
                'columns' => Arr::get($props, 'columns', ['Feature', 'Basic', 'Pro', 'Enterprise']),
                'rows' => Arr::get($props, 'rows', [
                    ['Support', 'Email', 'Priority', '24/7 Dedicated'],
                    ['Reports', 'Basic', 'Advanced', 'Custom'],
                    ['Integrations', 'Limited', 'Standard', 'Unlimited'],
                ]),
            ],
            'text' => [
                'title' => Arr::get($props, 'title', $this->humanize($key)),
                'body' => Arr::get($props, 'body', 'This rich text block is rendered from backend demo content for detailed template walkthrough.'),
            ],
            'slider' => [
                'slides' => Arr::get($props, 'slides', []),
            ],
            'hero_split_image' => [
                'left_background_color' => Arr::get($props, 'left_background_color', '#e8ffbd'),
                'right_background_color' => Arr::get($props, 'right_background_color', '#e8d0ff'),
                'slides' => Arr::get($props, 'slides', []),
                'right_image_url' => Arr::get($props, 'right_image_url', ''),
                'right_image_alt' => Arr::get($props, 'right_image_alt', ''),
            ],
            default => [
                'title' => Arr::get($props, 'title', $this->humanize($key)),
                'summary' => 'This section is ready for real project content.',
                'details' => [
                    'template' => $templateName,
                    'page' => $pageTitle,
                    'section' => $key,
                ],
            ],
        };
    }

    private function resolveComponent(string $key): string
    {
        $normalized = Str::of($key)->lower()->value();

        if ($normalized === 'hero_split_image' || Str::contains($normalized, 'hero_split')) {
            return 'hero_split_image';
        }
        if ($this->containsAny($normalized, ['hero'])) {
            return 'hero';
        }
        if ($this->containsAny($normalized, ['header']) && ! $this->containsAny($normalized, ['footer'])) {
            return 'header';
        }
        if ($this->containsAny($normalized, ['footer'])) {
            return 'footer';
        }
        if ($this->containsAny($normalized, ['logo', 'sponsor'])) {
            return 'logos';
        }
        if ($this->containsAny($normalized, ['product', 'shop', 'cart', 'checkout'])) {
            return 'products';
        }
        if ($this->containsAny($normalized, ['category'])) {
            return 'categories';
        }
        if ($this->containsAny($normalized, ['trust', 'badge'])) {
            return 'trust';
        }
        if ($this->containsAny($normalized, ['testimonial', 'review'])) {
            return 'testimonials';
        }
        if ($this->containsAny($normalized, ['faq', 'question'])) {
            return 'faq';
        }
        if ($this->containsAny($normalized, ['contact', 'form'])) {
            return 'contact';
        }
        if ($this->containsAny($normalized, ['map', 'location'])) {
            return 'map';
        }
        if ($this->containsAny($normalized, ['stat', 'metric'])) {
            return 'stats';
        }
        if ($this->containsAny($normalized, ['team', 'member', 'profile'])) {
            return 'team';
        }
        if ($this->containsAny($normalized, ['timeline', 'process', 'step'])) {
            return 'timeline';
        }
        if ($this->containsAny($normalized, ['gallery', 'portfolio', 'showcase'])) {
            return 'gallery';
        }
        if ($this->containsAny($normalized, ['cta', 'banner'])) {
            return 'cta';
        }
        if ($this->containsAny($normalized, ['table', 'comparison'])) {
            return 'table';
        }
        if ($this->containsAny($normalized, ['text', 'rich'])) {
            return 'text';
        }
        if ($this->containsAny($normalized, ['slider', 'slide', 'carousel'])) {
            return 'slider';
        }

        return 'generic';
    }

    private function containsAny(string $haystack, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($haystack, (string) $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     */
    private function resolveActiveSlug(array $pages, ?string $requestedPage): string
    {
        if ($requestedPage !== null && trim($requestedPage) !== '') {
            $needle = $this->slugify($requestedPage, $requestedPage);
            foreach ($pages as $page) {
                if (($page['slug'] ?? null) === $needle) {
                    return $needle;
                }
            }
        }

        return (string) ($pages[0]['slug'] ?? 'home');
    }

    private function slugify(string $value, string $fallback): string
    {
        $slug = Str::slug($value);

        return $slug !== '' ? $slug : (Str::slug($fallback, '-') ?: 'page');
    }

    private function humanize(string $value): string
    {
        return Str::of($value)
            ->replace(['_', '-'], ' ')
            ->squish()
            ->title()
            ->value();
    }

    private function resolveThumbnailUrl(?string $thumbnail): ?string
    {
        if (! $thumbnail) {
            return null;
        }

        if (str_starts_with($thumbnail, 'http://') || str_starts_with($thumbnail, 'https://') || str_starts_with($thumbnail, '/')) {
            return $thumbnail;
        }

        return asset('storage/'.$thumbnail);
    }

    /**
     * Product list with theme-assets images for Ekka demo-8 (from theme/assets/images/product-image/).
     *
     * @return array<int, array<string, mixed>>
     */
    private function ekkaThemeProducts(): array
    {
        $base = rtrim(asset('theme-assets'), '/').'/images/product-image/';
        return [
            ['sku' => 'SKU-88', 'name' => 'Relaxed Short full Sleeve T-Shirt', 'category' => 'T-Shirt', 'price' => '$58.00', 'old_price' => '$65.00', 'image_url' => $base.'88_1.jpg', 'url' => '#'],
            ['sku' => 'SKU-97', 'name' => 'Running & Trekking Shoes', 'category' => 'Sports', 'price' => '$150.00', 'old_price' => null, 'image_url' => $base.'97_1.jpg', 'url' => '#'],
            ['sku' => 'SKU-111', 'name' => 'Rose Gold Peacock Earrings', 'category' => 'jewellery', 'price' => '$20.00', 'old_price' => '$30.00', 'image_url' => $base.'111_1.jpg', 'url' => '#'],
            ['sku' => 'SKU-106', 'name' => 'Pocket Watch Leather Pouch', 'category' => 'watches', 'price' => '$50.00', 'old_price' => '$55.00', 'image_url' => $base.'106_1.jpg', 'url' => '#'],
            ['sku' => 'SKU-107', 'name' => 'Silver Deer Heart Necklace', 'category' => 'jewellery', 'price' => '$52.00', 'old_price' => '$55.00', 'image_url' => $base.'107_1.jpg', 'url' => '#'],
            ['sku' => 'SKU-108', 'name' => 'Titan 100 Ml Womens Perfume', 'category' => 'perfume', 'price' => '$10.00', 'old_price' => '$11.00', 'image_url' => $base.'108_1.jpg', 'url' => '#'],
            ['sku' => 'SKU-109', 'name' => "Men's Leather Reversible Belt", 'category' => 'belts', 'price' => '$42.00', 'old_price' => '$45.00', 'image_url' => $base.'109_1.jpg', 'url' => '#'],
            ['sku' => 'SKU-110', 'name' => 'Shampoo, conditioner & facewash packs', 'category' => 'cosmetics', 'price' => '$150.00', 'old_price' => '$200.00', 'image_url' => $base.'110_1.jpg', 'url' => '#'],
            ['sku' => 'SKU-89', 'name' => 'Girls Pink Embro design Top', 'category' => 'Clothes', 'price' => '$62.00', 'old_price' => '$65.00', 'image_url' => $base.'89_1.jpg', 'url' => '#'],
            ['sku' => 'SKU-1', 'name' => 'Baby fabric shoes', 'category' => 'Footwear', 'price' => '$4.00', 'old_price' => '$5.00', 'image_url' => $base.'1.jpg', 'url' => '#'],
            ['sku' => 'SKU-2', 'name' => "Men's hoodies t-shirt", 'category' => 'Clothes', 'price' => '$7.00', 'old_price' => '$10.00', 'image_url' => $base.'2.jpg', 'url' => '#'],
            ['sku' => 'SKU-3', 'name' => 'Girls t-shirt', 'category' => 'Clothes', 'price' => '$3.00', 'old_price' => '$5.00', 'image_url' => $base.'3.jpg', 'url' => '#'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function demoAssets(?Site $site = null, ?Template $template = null): array
    {
        $isEkka = $template && strtolower((string) $template->slug) === 'ekka-demo-8';
        $defaultProducts = $isEkka ? $this->ekkaThemeProducts() : [
            ['sku' => 'SKU-001', 'name' => 'Urban Jacket', 'price' => '219 GEL', 'old_price' => '259 GEL', 'image_url' => $this->demoAssetUrl('products/product-1.svg')],
            ['sku' => 'SKU-002', 'name' => 'Classic Sneakers', 'price' => '189 GEL', 'old_price' => '229 GEL', 'image_url' => $this->demoAssetUrl('products/product-2.svg')],
            ['sku' => 'SKU-003', 'name' => 'Minimal Backpack', 'price' => '149 GEL', 'old_price' => '179 GEL', 'image_url' => $this->demoAssetUrl('products/product-3.svg')],
            ['sku' => 'SKU-004', 'name' => 'Smart Watch', 'price' => '329 GEL', 'old_price' => null, 'image_url' => $this->demoAssetUrl('products/product-4.svg')],
            ['sku' => 'SKU-005', 'name' => 'Street Tee', 'price' => '79 GEL', 'old_price' => null, 'image_url' => $this->demoAssetUrl('products/product-5.svg')],
            ['sku' => 'SKU-006', 'name' => 'Travel Bag', 'price' => '199 GEL', 'old_price' => '239 GEL', 'image_url' => $this->demoAssetUrl('products/product-6.svg')],
            ['sku' => 'SKU-007', 'name' => 'Daily Cap', 'price' => '49 GEL', 'old_price' => null, 'image_url' => $this->demoAssetUrl('products/product-7.svg')],
            ['sku' => 'SKU-008', 'name' => 'Premium Hoodie', 'price' => '129 GEL', 'old_price' => '159 GEL', 'image_url' => $this->demoAssetUrl('products/product-8.svg')],
        ];

        $logos = [
            ['name' => 'Brand 1', 'image_url' => $this->demoAssetUrl('logos/logo-1.svg')],
            ['name' => 'Brand 2', 'image_url' => $this->demoAssetUrl('logos/logo-2.svg')],
            ['name' => 'Brand 3', 'image_url' => $this->demoAssetUrl('logos/logo-3.svg')],
            ['name' => 'Brand 4', 'image_url' => $this->demoAssetUrl('logos/logo-4.svg')],
        ];

        $products = $defaultProducts;

        $team = [
            ['name' => 'Nino K.', 'role' => 'Creative Lead', 'avatar_url' => $this->demoAssetUrl('people/person-1.svg')],
            ['name' => 'Giorgi L.', 'role' => 'Operations Lead', 'avatar_url' => $this->demoAssetUrl('people/person-2.svg')],
            ['name' => 'Mariam D.', 'role' => 'Customer Success', 'avatar_url' => $this->demoAssetUrl('people/person-3.svg')],
            ['name' => 'Luka S.', 'role' => 'Product Manager', 'avatar_url' => $this->demoAssetUrl('people/person-4.svg')],
            ['name' => 'Ana B.', 'role' => 'Marketing', 'avatar_url' => $this->demoAssetUrl('people/person-5.svg')],
        ];

        $categories = [
            ['name' => 'New In', 'slug' => 'new-in'],
            ['name' => 'Top Picks', 'slug' => 'top-picks'],
            ['name' => 'Sale', 'slug' => 'sale'],
            ['name' => 'Accessories', 'slug' => 'accessories'],
        ];

        $badges = [
            ['label' => 'Secure payments'],
            ['label' => 'Fast delivery'],
            ['label' => 'Easy returns'],
            ['label' => 'Live order tracking'],
        ];

        $faq = [
            ['q' => 'Can I edit content from admin panel?', 'a' => 'Yes, demo data and structure are both backend-driven and editable.'],
            ['q' => 'Does checkout support installments?', 'a' => 'Yes, available providers/modes depend on plan and gateway configuration.'],
            ['q' => 'Can I change sections per page?', 'a' => 'Yes, section lists come from template metadata and can be customized.'],
        ];

        $testimonials = [
            ['quote' => 'The template is modern and easy to manage.', 'author' => 'Nino G.'],
            ['quote' => 'Great admin visibility with full page walkthrough.', 'author' => 'Irakli T.'],
            ['quote' => 'Backend mock data helped us prototype fast.', 'author' => 'Saba M.'],
        ];

        $gallery = [
            ['title' => 'Showcase 1', 'image_url' => $this->demoAssetUrl('gallery/gallery-1.svg')],
            ['title' => 'Showcase 2', 'image_url' => $this->demoAssetUrl('gallery/gallery-2.svg')],
            ['title' => 'Showcase 3', 'image_url' => $this->demoAssetUrl('gallery/gallery-3.svg')],
            ['title' => 'Showcase 4', 'image_url' => $this->demoAssetUrl('gallery/gallery-4.svg')],
        ];

        $assets = [
            'logos' => $logos,
            'products' => $products,
            'team' => $team,
            'categories' => $categories,
            'badges' => $badges,
            'faq' => $faq,
            'testimonials' => $testimonials,
            'gallery' => $gallery,
            'hero_images' => [
                $this->demoAssetUrl('hero/hero-1.svg'),
                $this->demoAssetUrl('hero/hero-2.svg'),
                $this->demoAssetUrl('hero/hero-3.svg'),
            ],
            'gallery_images' => [
                $this->demoAssetUrl('gallery/gallery-1.svg'),
                $this->demoAssetUrl('gallery/gallery-2.svg'),
                $this->demoAssetUrl('gallery/gallery-3.svg'),
                $this->demoAssetUrl('gallery/gallery-4.svg'),
            ],
        ];

        if (! $site) {
            return $assets;
        }

        $siteCatalog = $this->siteCatalogAssets($site, $template);
        if ($siteCatalog === []) {
            return $assets;
        }

        if ($siteCatalog['products'] !== []) {
            $assets['products'] = $siteCatalog['products'];
        }

        if ($siteCatalog['categories'] !== []) {
            $assets['categories'] = $siteCatalog['categories'];
        }

        if ($siteCatalog['hero_images'] !== []) {
            $assets['hero_images'] = $siteCatalog['hero_images'];
        }

        if ($siteCatalog['gallery_images'] !== []) {
            $assets['gallery_images'] = $siteCatalog['gallery_images'];
        }

        return $assets;
    }

    private function demoAssetUrl(string $path): string
    {
        return asset('demo/'.$path);
    }

    /**
     * Resolve product/category media from real ecommerce tables for a site.
     *
     * @return array{
     *   products: array<int, array<string, mixed>>,
     *   categories: array<int, array<string, mixed>>,
     *   hero_images: array<int, string>,
     *   gallery_images: array<int, string>
     * }
     */
    private function siteCatalogAssets(Site $site, ?Template $template = null): array
    {
        $products = EcommerceProduct::query()
            ->where('site_id', $site->id)
            ->where('status', 'active')
            ->whereNotNull('published_at')
            ->with([
                'category:id,site_id,name,slug',
                'images:id,site_id,product_id,path,alt_text,sort_order,is_primary',
            ])
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(24)
            ->get();

        if ($products->isEmpty()) {
            $products = EcommerceProduct::query()
                ->where('site_id', $site->id)
                ->where('status', 'active')
                ->with([
                    'category:id,site_id,name,slug',
                    'images:id,site_id,product_id,path,alt_text,sort_order,is_primary',
                ])
                ->orderByDesc('id')
                ->limit(24)
                ->get();
        }

        $categories = EcommerceCategory::query()
            ->where('site_id', $site->id)
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($categories->isEmpty()) {
            $categories = EcommerceCategory::query()
                ->where('site_id', $site->id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();
        }

        if ($products->isEmpty() && $categories->isEmpty()) {
            return [];
        }

        $isEkka = $template && strtolower((string) $template->slug) === 'ekka-demo-8';
        $ekkaThemeList = $isEkka ? $this->ekkaThemeProducts() : [];

        $mappedProducts = $products->values()->map(function (EcommerceProduct $product, int $index) use ($site, $isEkka, $ekkaThemeList): array {
            $primaryImage = $product->images
                ->sortBy([
                    fn ($item) => $item->is_primary ? 0 : 1,
                    fn ($item) => $item->sort_order,
                    fn ($item) => $item->id,
                ])
                ->first();

            $imagePath = is_object($primaryImage) ? (string) ($primaryImage->path ?? '') : '';
            $imageUrl = $this->resolveSiteAssetUrl($site, $imagePath);
            if ($imageUrl === null) {
                $imageUrl = $isEkka && isset($ekkaThemeList[$index])
                    ? ($ekkaThemeList[$index]['image_url'] ?? null)
                    : null;
            }
            if ($imageUrl === null) {
                $imageUrl = $this->demoAssetUrl('products/product-'.(($index % 8) + 1).'.svg');
            }

            $currency = $this->normalizeCurrencyCode($product->currency);

            return [
                'id' => (int) $product->id,
                'slug' => (string) ($product->slug ?? ''),
                'sku' => (string) ($product->sku ?? ''),
                'name' => (string) ($product->name ?? 'Product'),
                'category_name' => (string) ($product->category?->name ?? ''),
                'category_slug' => (string) ($product->category?->slug ?? ''),
                'price' => $this->formatMoneyWithCurrency($product->price, $currency),
                'old_price' => $product->compare_at_price !== null
                    ? $this->formatMoneyWithCurrency($product->compare_at_price, $currency)
                    : null,
                'price_raw' => (float) $product->price,
                'currency' => $currency,
                'image_url' => $imageUrl,
                'image_alt' => (string) ($primaryImage->alt_text ?? $product->name ?? 'Product image'),
                'stock_quantity' => (int) ($product->stock_quantity ?? 0),
            ];
        })->all();

        $mappedCategories = $categories->values()->map(static function (EcommerceCategory $category): array {
            $name = trim((string) $category->name);
            $slug = trim((string) $category->slug);

            return [
                'id' => (int) $category->id,
                'name' => $name !== '' ? $name : 'Category',
                'slug' => $slug !== '' ? $slug : Str::slug($name !== '' ? $name : 'category'),
            ];
        })->all();

        $images = array_values(array_filter(array_map(
            static fn (array $item): string => (string) ($item['image_url'] ?? ''),
            $mappedProducts
        )));

        return [
            'products' => $mappedProducts,
            'categories' => $mappedCategories,
            'hero_images' => array_slice($images, 0, 3),
            'gallery_images' => array_slice($images, 0, 4),
        ];
    }

    private function resolveSiteAssetUrl(Site $site, string $path): ?string
    {
        $normalized = trim($path);
        if ($normalized === '') {
            return null;
        }

        if (str_starts_with($normalized, 'http://') || str_starts_with($normalized, 'https://') || str_starts_with($normalized, '/')) {
            return $normalized;
        }

        // CMS can store theme product images as theme-assets/... so they show on front
        if (str_starts_with($normalized, 'theme-assets/')) {
            return asset($normalized);
        }

        return route('public.sites.assets', ['site' => $site->id, 'path' => $normalized]);
    }

    private function normalizeCurrencyCode(mixed $value): string
    {
        $currency = strtoupper(trim((string) $value));

        return preg_match('/^[A-Z]{3}$/', $currency) === 1 ? $currency : 'GEL';
    }

    private function formatMoneyWithCurrency(mixed $value, string $currency): string
    {
        $amount = number_format((float) $value, 2, '.', '');
        $symbol = $currency === 'GEL' ? 'GEL' : $currency;

        return $amount.' '.$symbol;
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     */
    private function buildProductSearchPreview(array $products): string
    {
        $tokens = array_values(array_filter(array_map(static function (array $product): string {
            return trim((string) ($product['name'] ?? ''));
        }, array_slice($products, 0, 3))));

        if ($tokens === []) {
            return 'Sneakers, jackets, backpacks...';
        }

        return implode(', ', $tokens).'...';
    }

    /**
     * @param  array<int, array<string, mixed>>  $products
     * @return array<int, string>
     */
    private function buildTrendingTokens(array $products): array
    {
        $tokens = array_values(array_filter(array_map(static function (array $product): string {
            return trim((string) ($product['name'] ?? ''));
        }, array_slice($products, 0, 3))));

        return $tokens !== [] ? $tokens : ['Hoodie', 'Smart Watch', 'Travel Bag'];
    }
}
