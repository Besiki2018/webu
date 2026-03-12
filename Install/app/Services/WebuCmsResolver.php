<?php

namespace App\Services;

use App\Models\EcommerceCategory;
use App\Models\EcommerceProduct;
use App\Models\Menu;
use App\Models\Site;
use App\Models\Template;
use Illuminate\Support\Arr;

/**
 * Standard CMS resolver for Webu design-system components.
 * Provides site settings, navigation, products, and categories for dynamic content.
 * Use for header (logo, nav), footer, product grid/card, etc.
 */
class WebuCmsResolver
{
    public function __construct(
        protected CmsLocaleResolver $localeResolver,
        protected TemplateDemoService $templateDemoService
    ) {}

    /**
     * Site-level settings: logo, brand, CTA, theme layout keys.
     *
     * @return array{logo_url: string|null, logo_text: string, brand: string, cta_label: string|null, cta_url: string|null, locale: string}
     */
    public function getSiteSettings(?Site $site = null): array
    {
        if (! $site) {
            return [
                'logo_url' => null,
                'logo_text' => 'Store',
                'brand' => 'Store',
                'cta_label' => null,
                'cta_url' => null,
                'locale' => app()->getLocale(),
            ];
        }

        $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $branding = is_array(Arr::get($themeSettings, 'branding')) ? Arr::get($themeSettings, 'branding') : [];

        return [
            'logo_url' => $this->resolveLogoUrl($site, $branding),
            'logo_text' => trim((string) Arr::get($branding, 'logo_text', $site->name ?? 'Store')),
            'brand' => trim((string) Arr::get($branding, 'brand', $site->name ?? 'Store')),
            'cta_label' => trim((string) Arr::get($branding, 'cta_label', '')) ?: null,
            'cta_url' => trim((string) Arr::get($branding, 'cta_url', '')) ?: null,
            'locale' => $site->locale ?? app()->getLocale(),
        ];
    }

    /**
     * Navigation menu for header (from site menu or default).
     *
     * @return array<int, array{label: string, url: string, slug: string}>
     */
    public function getNavigation(?Site $site = null, ?Template $template = null, ?string $locale = null): array
    {
        $template = $template ?? Template::query()->where('slug', 'ecommerce')->first() ?? Template::query()->first();
        if (! $template) {
            return $this->defaultMenuItems();
        }

        $payload = $this->templateDemoService->buildPayload($template, null, $site, $locale, false);
        $headerMenuItems = $payload['header_menu_items'] ?? [];

        return $headerMenuItems !== [] ? $headerMenuItems : $this->defaultMenuItems();
    }

    /**
     * Products for product grid/card (from site catalog or demo).
     *
     * @param  array{limit?: int, category_slug?: string, featured?: bool}  $filter
     * @return array<int, array<string, mixed>>
     */
    public function getProducts(?Site $site = null, array $filter = []): array
    {
        $limit = (int) ($filter['limit'] ?? 12);
        $categorySlug = isset($filter['category_slug']) ? trim((string) $filter['category_slug']) : null;
        $featured = (bool) ($filter['featured'] ?? false);

        if ($site !== null) {
            $query = EcommerceProduct::query()->where('site_id', $site->id);
            if (method_exists(EcommerceProduct::class, 'scopeActive')) {
                $query->active();
            } elseif (\Schema::hasColumn((new EcommerceProduct)->getTable(), 'status')) {
                $query->where('status', 'active');
            }
            if ($categorySlug !== null && $categorySlug !== '') {
                $cat = EcommerceCategory::where('site_id', $site->id)->where('slug', $categorySlug)->first();
                if ($cat) {
                    $query->where('category_id', $cat->id);
                }
            }
            if ($featured && \Schema::hasColumn((new EcommerceProduct)->getTable(), 'is_featured')) {
                $query->where('is_featured', true);
            }
            $products = $query->orderBy('id')->limit($limit)->with(['images', 'category'])->get();
            return $this->formatProductsForCms($products, $site);
        }

        return $this->defaultProducts($limit);
    }

    /**
     * Categories for category grid / filters.
     *
     * @return array<int, array{name: string, slug: string, count?: int, image_url?: string|null}>
     */
    public function getCategories(?Site $site = null): array
    {
        if ($site !== null) {
            $query = EcommerceCategory::query()->where('site_id', $site->id);
            if (\Schema::hasColumn((new EcommerceCategory)->getTable(), 'status')) {
                $query->where('status', 'active');
            }
            $categories = $query->orderBy('sort_order')->orderBy('name')->get();

            return $categories->map(fn ($c) => [
                'name' => $c->name,
                'slug' => $c->slug,
                'count' => $c->relationLoaded('products') ? $c->products->count() : 0,
                'image_url' => $c->meta_json['image_url'] ?? null,
            ])->all();
        }

        return $this->defaultCategories();
    }

    /**
     * Footer data: menus by key + layout (contact address, column menu keys).
     *
     * @return array{menus: array<string, array<int, array{label: string, url: string}>>, layout: array{contact_address: string, menu_key_column2: string, menu_key_column3: string, menu_key_column4: string, menu_key_column5: string}}
     */
    public function getFooterData(?Site $site = null, ?string $locale = null): array
    {
        if (! $site) {
            return [
                'menus' => [],
                'layout' => [
                    'contact_address' => '',
                    'menu_key_column2' => 'recent-posts',
                    'menu_key_column3' => 'our-stores',
                    'menu_key_column4' => 'useful-links',
                    'menu_key_column5' => 'footer',
                ],
            ];
        }

        return $this->templateDemoService->getFooterDataForSite($site, $locale);
    }

    /**
     * Testimonials for webu-testimonials (demo when no site).
     *
     * @return array<int, array{user_name: string, avatar?: string, rating?: int, text: string}>
     */
    public function getTestimonials(?Site $site = null): array
    {
        if ($site !== null) {
            // TODO: from site content or CMS when available
            return [];
        }
        return [
            ['user_name' => 'Alex', 'rating' => 5, 'text' => 'Great quality and fast delivery. Very happy with my purchase.'],
            ['user_name' => 'Jordan', 'rating' => 5, 'text' => 'The best experience. Will definitely order again.'],
        ];
    }

    /**
     * Features for webu-features (demo when no site).
     *
     * @return array<int, array{icon?: string, title: string, description?: string}>
     */
    public function getFeatures(?Site $site = null): array
    {
        if ($site !== null) {
            return [];
        }
        return [
            ['title' => 'Free shipping', 'description' => 'On orders over $50'],
            ['title' => 'Secure payment', 'description' => '100% protected checkout'],
            ['title' => 'Easy returns', 'description' => '30-day return policy'],
        ];
    }

    /**
     * FAQ items for webu-faq (demo when no site).
     *
     * @return array<int, array{question: string, answer: string}>
     */
    public function getFaq(?Site $site = null): array
    {
        if ($site !== null) {
            return [];
        }
        return [
            ['question' => 'How do I return an item?', 'answer' => 'Contact support with your order number to start a return.'],
            ['question' => 'What payment methods do you accept?', 'answer' => 'We accept major cards and PayPal.'],
        ];
    }

    /**
     * Blog posts for webu-blog-grid (demo when no site).
     *
     * @return array<int, array{id: string, title: string, excerpt?: string, image?: string, url?: string, date?: string, author?: string}>
     */
    public function getBlogPosts(?Site $site = null, int $limit = 6): array
    {
        if ($site !== null) {
            return [];
        }
        $posts = [
            ['id' => '1', 'title' => 'Welcome to our blog', 'excerpt' => 'First post about quality and style.', 'url' => '/blog/1', 'date' => '2024-01-15', 'author' => 'Admin'],
            ['id' => '2', 'title' => 'New collection tips', 'excerpt' => 'How to choose the right pieces.', 'url' => '/blog/2', 'date' => '2024-01-10', 'author' => 'Admin'],
        ];
        return array_slice($posts, 0, $limit);
    }

    /**
     * Announcement bar (demo when no site).
     *
     * @return array{text: string, linkUrl?: string, linkLabel?: string, countdownEnd?: string}|null
     */
    public function getAnnouncement(?Site $site = null): ?array
    {
        if ($site !== null) {
            return null;
        }
        return [
            'text' => 'Free shipping on orders over $50',
            'linkUrl' => '/shop',
            'linkLabel' => 'Shop now',
        ];
    }

    /**
     * Stats for webu-stats (demo when no site).
     *
     * @return array<int, array{label: string, value: string}>
     */
    public function getStats(?Site $site = null): array
    {
        if ($site !== null) {
            return [];
        }
        return [
            ['label' => 'Happy customers', 'value' => '10k+'],
            ['label' => 'Products', 'value' => '500+'],
            ['label' => 'Years', 'value' => '5+'],
        ];
    }

    /**
     * Team members for webu-team (demo when no site).
     *
     * @return array<int, array{name: string, role?: string, avatar?: string}>
     */
    public function getTeam(?Site $site = null): array
    {
        if ($site !== null) {
            return [];
        }
        return [
            ['name' => 'Jane Doe', 'role' => 'Founder'],
            ['name' => 'John Smith', 'role' => 'Lead Developer'],
        ];
    }

    private function resolveLogoUrl(Site $site, array $branding): ?string
    {
        $url = trim((string) Arr::get($branding, 'logo_url', ''));
        if ($url !== '') {
            return $url;
        }
        $path = Arr::get($branding, 'logo_path');
        if (is_string($path) && $path !== '') {
            return str_starts_with($path, 'http') ? $path : asset('storage/'.ltrim($path, '/'));
        }

        $site->loadMissing('globalSettings.logoMedia');
        $logoPath = $site->globalSettings?->logoMedia?->path;
        if (is_string($logoPath) && $logoPath !== '') {
            return route('public.sites.assets', ['site' => $site->id, 'path' => $logoPath]);
        }

        return null;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, EcommerceProduct>  $products
     * @return array<int, array<string, mixed>>
     */
    private function formatProductsForCms($products, Site $site): array
    {
        $baseUrl = rtrim(url('/'), '/');
        $out = [];
        foreach ($products as $p) {
            $price = $p->price !== null ? (string) $p->price : '0';
            $compareAt = $p->compare_at_price ?? null;
            $firstImage = null;
            if ($p->relationLoaded('images') && $p->images->isNotEmpty()) {
                $firstImage = $p->images->first()->path ?? null;
                if ($firstImage && is_string($firstImage) && ! str_starts_with($firstImage, 'http')) {
                    $firstImage = asset('storage/'.ltrim($firstImage, '/'));
                }
            }
            $out[] = [
                'id' => $p->id,
                'name' => $p->name,
                'slug' => $p->slug,
                'sku' => $p->sku ?? '',
                'price' => $price,
                'old_price' => $compareAt ? (string) $compareAt : null,
                'regular_price' => $p->price,
                'discount_price' => $compareAt,
                'image_url' => $firstImage,
                'url' => $baseUrl.'/shop/'.$p->slug,
                'stock_text' => $p->stock_quantity > 0 ? 'in_stock' : 'out_of_stock',
            ];
        }

        return $out;
    }

    /**
     * Demo products when no site (design-system playground).
     *
     * @return array<int, array<string, mixed>>
     */
    private function defaultProducts(int $limit): array
    {
        $base = rtrim(url('/'), '/');
        $demo = [
            ['id' => 1, 'name' => 'Demo Product 1', 'slug' => 'demo-1', 'price' => '49', 'old_price' => null, 'image_url' => null, 'url' => $base.'/shop/demo-1'],
            ['id' => 2, 'name' => 'Demo Product 2', 'slug' => 'demo-2', 'price' => '29', 'old_price' => null, 'image_url' => null, 'url' => $base.'/shop/demo-2'],
            ['id' => 3, 'name' => 'Demo Product 3', 'slug' => 'demo-3', 'price' => '79', 'old_price' => '89', 'image_url' => null, 'url' => $base.'/shop/demo-3'],
            ['id' => 4, 'name' => 'Demo Product 4', 'slug' => 'demo-4', 'price' => '39', 'old_price' => null, 'image_url' => null, 'url' => $base.'/shop/demo-4'],
            ['id' => 5, 'name' => 'Demo Product 5', 'slug' => 'demo-5', 'price' => '59', 'old_price' => null, 'image_url' => null, 'url' => $base.'/shop/demo-5'],
            ['id' => 6, 'name' => 'Demo Product 6', 'slug' => 'demo-6', 'price' => '19', 'old_price' => null, 'image_url' => null, 'url' => $base.'/shop/demo-6'],
        ];

        return array_slice($demo, 0, $limit);
    }

    /**
     * Demo categories when no site (design-system playground).
     *
     * @return array<int, array{name: string, slug: string, count?: int, image_url?: string|null}>
     */
    private function defaultCategories(): array
    {
        return [
            ['name' => 'New In', 'slug' => 'new-in'],
            ['name' => 'Top Picks', 'slug' => 'top-picks'],
            ['name' => 'Sale', 'slug' => 'sale'],
            ['name' => 'Accessories', 'slug' => 'accessories'],
        ];
    }

    /**
     * @return array<int, array{label: string, url: string, slug: string}>
     */
    private function defaultMenuItems(): array
    {
        return [
            ['label' => 'Home', 'url' => '/', 'slug' => 'home'],
            ['label' => 'Shop', 'url' => '/shop', 'slug' => 'shop'],
            ['label' => 'Contact', 'url' => '/contact', 'slug' => 'contact'],
        ];
    }
}
