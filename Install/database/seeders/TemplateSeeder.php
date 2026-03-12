<?php

namespace Database\Seeders;

use App\Models\Plan;
use App\Models\Template;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class TemplateSeeder extends Seeder
{
    /**
     * Legacy templates that must be removed from the owned catalog.
     *
     * @var array<int, string>
     */
    private const PURGED_IMPORTED_SLUGS = [
        'pixio-shop',
        'eliah-nextjs',
        'webu-shop',
        'webu-shop-01',
        'business-modern',
        'booking-services',
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->purgeImportedTemplates();

        $seededSlugs = [];

        foreach ($this->ecommerceTemplatesFromJson() as $templateData) {
            $slug = (string) Arr::pull($templateData, 'slug');
            if ($slug === '') {
                continue;
            }
            Template::updateOrCreate(
                ['slug' => $slug],
                $templateData
            );
            $seededSlugs[] = $slug;
        }

        foreach ($this->namedProductionTemplatesFromJson() as $templateData) {
            $slug = (string) Arr::pull($templateData, 'slug');
            if ($slug === '') {
                continue;
            }
            Template::updateOrCreate(
                ['slug' => $slug],
                $templateData
            );
            $seededSlugs[] = $slug;
        }

        foreach ($this->templateBlueprints() as $templateData) {
            $slug = (string) Arr::pull($templateData, 'slug');
            if ($slug === '') {
                continue;
            }

            Template::updateOrCreate(
                ['slug' => $slug],
                $templateData
            );

            $seededSlugs[] = $slug;
        }

        $this->syncTemplateAssignments($seededSlugs);
    }

    private function purgeImportedTemplates(): void
    {
        $templates = Template::query()
            ->whereIn('slug', self::PURGED_IMPORTED_SLUGS)
            ->where('is_system', false)
            ->get();

        foreach ($templates as $template) {
            $this->deleteTemplateAssetsAndRow($template);
        }
    }

    private function deleteTemplateAssetsAndRow(Template $template): void
    {
        $zipPath = trim((string) $template->getRawOriginal('zip_path'));
        if ($zipPath !== '') {
            Storage::disk('local')->delete($zipPath);
        }

        $thumbnail = trim((string) $template->thumbnail);
        if ($thumbnail !== '') {
            Storage::disk('public')->delete($thumbnail);
        }

        $liveDemoPath = trim((string) Arr::get($template->metadata, 'live_demo.path', ''));
        if ($liveDemoPath !== '' && str_starts_with($liveDemoPath, 'template-demos/')) {
            File::deleteDirectory(public_path($liveDemoPath));
        }

        File::deleteDirectory(public_path('themes/'.trim((string) $template->slug)));
        File::deleteDirectory(base_path('templates/'.trim((string) $template->slug)));

        $template->delete();
    }

    /**
     * Load ecommerce template records from JSON files in resources/templates (ecommerce-*.json).
     *
     * @return array<int, array<string, mixed>>
     */
    private function ecommerceTemplatesFromJson(): array
    {
        $templates = [];
        $path = resource_path('templates');
        if (! File::isDirectory($path)) {
            return $templates;
        }
        $files = File::glob($path.'/ecommerce-*.json');
        foreach ($files as $file) {
            $slug = pathinfo($file, PATHINFO_FILENAME);
            $contents = File::get($file);
            $data = json_decode($contents, true);
            if (! is_array($data) || $slug === '') {
                continue;
            }
            $name = trim((string) Arr::get($data, 'name', $slug));
            $themePreset = trim((string) Arr::get($data, 'theme_preset', 'default'));
            $defaultPages = Arr::get($data, 'default_pages', []);
            if (! is_array($defaultPages)) {
                $defaultPages = [];
            }
            $previewImage = trim((string) Arr::get($data, 'preview_image', ''));
            if ($previewImage === '') {
                $previewImage = 'images/template-previews/'.$slug.'.jpg';
            }
            $templates[] = [
                'slug' => $slug,
                'name' => $name,
                'description' => 'Ecommerce storefront with Home, Shop, Product, Cart, Checkout, Contact. Built with Webu components and theme tokens.',
                'category' => 'ecommerce',
                'keywords' => ['ecommerce', 'shop', 'store'],
                'is_system' => true,
                'metadata' => [
                    'vertical' => 'ecommerce',
                    'theme_preset' => $themePreset,
                    'default_pages' => $defaultPages,
                    'module_flags' => $this->moduleFlags(['ecommerce' => true]),
                    'preview_image' => $previewImage,
                ],
            ];
        }

        return $templates;
    }

    /**
     * Load named production templates: service-booking.json, portfolio-agency.json.
     * Used for "Start from template" (E-commerce, Service Booking, Portfolio).
     *
     * @return array<int, array<string, mixed>>
     */
    private function namedProductionTemplatesFromJson(): array
    {
        $templates = [];
        $path = resource_path('templates');
        if (! File::isDirectory($path)) {
            return $templates;
        }
        $files = [
            'service-booking.json' => 'booking',
            'portfolio-agency.json' => 'portfolio',
        ];
        foreach ($files as $filename => $category) {
            $file = $path.'/'.$filename;
            if (! File::exists($file)) {
                continue;
            }
            $slug = pathinfo($filename, PATHINFO_FILENAME);
            $contents = File::get($file);
            $data = json_decode($contents, true);
            if (! is_array($data) || $slug === '') {
                continue;
            }
            $name = trim((string) Arr::get($data, 'name', $slug));
            $themePreset = trim((string) Arr::get($data, 'theme_preset', 'default'));
            $defaultPages = Arr::get($data, 'default_pages', []);
            if (! is_array($defaultPages)) {
                $defaultPages = [];
            }
            $templates[] = [
                'slug' => $slug,
                'name' => $name,
                'description' => $category === 'booking'
                    ? 'Service booking business with Home, Services, Book Now, Contact. Built with Webu components.'
                    : 'Portfolio / Agency with Home, Projects, Contact. Built with Webu components.',
                'category' => $category,
                'keywords' => $category === 'booking' ? ['booking', 'services', 'appointments'] : ['portfolio', 'agency', 'showcase'],
                'is_system' => true,
                'metadata' => [
                    'theme_preset' => $themePreset,
                    'default_pages' => $defaultPages,
                    'module_flags' => $this->moduleFlags($category === 'booking' ? ['booking' => true] : []),
                ],
            ];
        }

        return $templates;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templateBlueprints(): array
    {
        return [
            [
                'slug' => 'ecommerce',
                'name' => 'Premium Ecommerce',
                'description' => 'Premium, modern, high-conversion eCommerce homepage. Built only with Webu components: Header, Hero, Featured Categories, Product Grid, Promo Split, Best Sellers, Testimonials, Newsletter, Footer. Clean luxury minimal style, neutral palette, 1200px container.',
                'category' => 'ecommerce',
                'keywords' => ['ecommerce', 'shop', 'store', 'premium', 'minimal', 'conversion', 'ელ-კომერცია'],
                'is_system' => true,
                'metadata' => [
                    'vertical' => 'ecommerce',
                    'module_flags' => $this->moduleFlags(['ecommerce' => true]),
                    'default_pages' => [
                        $this->page('home', 'Home', [
                            'webu_general_heading_01',
                            'webu_ecom_category_list_01',
                            'webu_ecom_product_grid_01',
                            'webu_general_card_01',
                            'webu_ecom_product_grid_01',
                            'webu_general_testimonials_01',
                            'webu_general_card_01',
                        ]),
                        $this->page('shop', 'Shop', [
                            'webu_ecom_product_search_01',
                            'webu_ecom_category_list_01',
                            'webu_ecom_product_grid_01',
                            'webu_ecom_cart_icon_01',
                        ]),
                        $this->page('product', 'Product Detail', [
                            'webu_ecom_product_gallery_01',
                            'webu_ecom_product_detail_01',
                            'webu_ecom_add_to_cart_button_01',
                            'webu_ecom_product_tabs_01',
                        ]),
                        $this->page('cart', 'Cart', [
                            'webu_ecom_cart_icon_01',
                            'webu_ecom_cart_page_01',
                            'webu_ecom_coupon_ui_01',
                            'webu_ecom_order_summary_01',
                        ]),
                        $this->page('checkout', 'Checkout', [
                            'webu_ecom_checkout_form_01',
                            'webu_ecom_shipping_selector_01',
                            'webu_ecom_payment_selector_01',
                            'webu_ecom_order_summary_01',
                        ]),
                        $this->page('contact', 'Contact', ['contact_split_form', 'map_contact_block']),
                    ],
                    'default_sections' => [
                        'home' => [
                            $this->sectionBlueprint('webu_general_heading_01', [
                                'top_strip_text' => 'Free shipping on orders over $75',
                                'brand_text' => 'Store',
                                'contact_phone' => '',
                                'contact_email' => '',
                                'hero_variant' => 'classic',
                                'headline' => 'New Collection',
                                'subheading' => 'Discover quality pieces for your everyday style.',
                                'hero_cta_label' => 'Shop Now',
                                'hero_cta_url' => '/shop',
                                'hero_cta_secondary_label' => 'Discover Collection',
                                'hero_cta_secondary_url' => '/shop',
                                'badge' => '',
                                'chips' => [],
                            ]),
                            $this->sectionBlueprint('webu_ecom_category_list_01', ['title' => 'Shop by Category']),
                            $this->sectionBlueprint('webu_ecom_product_grid_01', ['title' => 'Featured Products', 'add_to_cart_label' => 'Add to Cart']),
                            $this->sectionBlueprint('webu_general_card_01', [
                                'title' => 'Season Sale',
                                'body' => 'Up to 40% off selected items. Limited time.',
                                'button' => 'Shop Sale',
                                'button_url' => '/shop',
                            ]),
                            $this->sectionBlueprint('webu_ecom_product_grid_01', ['title' => 'Best Sellers', 'add_to_cart_label' => 'Add to Cart']),
                            $this->sectionBlueprint('webu_general_testimonials_01', []),
                            $this->sectionBlueprint('webu_general_card_01', [
                                'title' => 'Stay Updated',
                                'body' => 'Subscribe for new arrivals and exclusive offers.',
                                'button' => 'Subscribe',
                                'button_url' => '#newsletter',
                            ]),
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param  array<int, string>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function sectionDefinitions(array $sections): array
    {
        return array_values(array_map(
            static fn (string $key): array => [
                'key' => $key,
                'enabled' => true,
            ],
            $sections
        ));
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{key:string,enabled:bool,props:array<string,mixed>}
     */
    private function sectionBlueprint(string $key, array $props = []): array
    {
        return [
            'key' => $key,
            'enabled' => true,
            'props' => $props,
        ];
    }

    /**
     * @param  array<int, string>  $sections
     * @return array<string, mixed>
     */
    private function page(string $slug, string $title, array $sections): array
    {
        return [
            'slug' => $slug,
            'title' => $title,
            'sections' => array_values($sections),
        ];
    }

    /**
     * @param  array<string, bool>  $overrides
     * @return array<string, bool>
     */
    private function moduleFlags(array $overrides = []): array
    {
        return array_merge([
            'cms_pages' => true,
            'cms_menus' => true,
            'cms_settings' => true,
            'media_library' => true,
            'domains' => true,
            'database' => true,
            'ecommerce' => false,
            'booking' => false,
            'payments' => false,
            'shipping' => false,
        ], $overrides);
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array{heading: string, body: string, button: string}
     */
    private function typographyTokens(array $overrides = []): array
    {
        return array_merge([
            'heading' => 'heading',
            'body' => 'body',
            'button' => 'body',
        ], $overrides);
    }

    /**
     * @param  array<int, string>  $seededSlugs
     */
    private function syncTemplateAssignments(array $seededSlugs): void
    {
        if ($seededSlugs === []) {
            return;
        }

        $templateIds = Template::query()
            ->whereIn('slug', $seededSlugs)
            ->where('is_system', false)
            ->pluck('id')
            ->all();

        if ($templateIds === []) {
            return;
        }

        $eligiblePlanIds = $this->eligiblePlanIdsForTemplates();
        if ($eligiblePlanIds === []) {
            return;
        }

        Plan::query()
            ->whereIn('id', $eligiblePlanIds)
            ->get(['id'])
            ->each(fn (Plan $plan) => $plan->templates()->syncWithoutDetaching($templateIds));
    }

    /**
     * Plans that include "Custom templates" get all seeded non-system templates by default.
     *
     * @return array<int, int>
     */
    private function eligiblePlanIdsForTemplates(): array
    {
        $plans = Plan::query()->get(['id', 'features']);

        if ($plans->isEmpty()) {
            return [];
        }

        $eligible = $plans
            ->filter(function (Plan $plan): bool {
                $features = is_array($plan->features) ? $plan->features : [];

                foreach ($features as $feature) {
                    $name = Str::of((string) Arr::get($feature, 'name', ''))->lower();
                    if ($name->contains('custom template') && (bool) Arr::get($feature, 'included', false)) {
                        return true;
                    }
                }

                return false;
            })
            ->pluck('id')
            ->all();

        return $eligible !== [] ? $eligible : $plans->pluck('id')->all();
    }
}
