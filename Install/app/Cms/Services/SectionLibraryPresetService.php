<?php

namespace App\Cms\Services;

use App\Models\SectionLibrary;

class SectionLibraryPresetService
{
    /**
     * @return array<int, array{key:string,category:string,schema_json:array<string,mixed>,enabled:bool}>
     */
    public function definitions(): array
    {
        return [
            $this->definition(
                key: 'hero_split_image',
                category: 'marketing',
                label: 'Hero Split Image',
                description: 'Two-column hero with content and supporting image.',
                designVariant: 'hero/split-image',
                bindings: [
                    'headline' => 'content.headline',
                    'subtitle' => 'content.subtitle',
                    'primary_cta' => 'content.primary_cta',
                    'secondary_cta' => 'content.secondary_cta',
                    'background_image' => 'media.hero_image',
                ],
                properties: [
                    'eyebrow' => $this->stringField(),
                    'headline' => $this->stringField(),
                    'subtitle' => $this->stringField(),
                    'primary_cta' => $this->actionField(),
                    'secondary_cta' => $this->actionField(),
                    'background_image' => $this->stringField(),
                ],
                required: ['headline', 'primary_cta']
            ),
            $this->definition(
                key: 'hero_centered_gradient',
                category: 'marketing',
                label: 'Hero Centered Gradient',
                description: 'Centered headline with gradient background and single CTA.',
                designVariant: 'hero/centered-gradient',
                bindings: [
                    'headline' => 'content.headline',
                    'subtitle' => 'content.subtitle',
                    'primary_cta' => 'content.primary_cta',
                ],
                properties: [
                    'headline' => $this->stringField(),
                    'subtitle' => $this->stringField(),
                    'primary_cta' => $this->actionField(),
                    'accent_badge' => $this->stringField(),
                ],
                required: ['headline']
            ),
            $this->definition(
                key: 'logo_cloud_wall',
                category: 'marketing',
                label: 'Logo Cloud Wall',
                description: 'Partner/client logo wall for social proof.',
                designVariant: 'trust/logo-cloud-wall',
                bindings: [
                    'title' => 'content.title',
                    'logos' => 'content.logos',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'logos' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'stats_cards',
                category: 'marketing',
                label: 'Stats Cards',
                description: 'KPI/statistics cards section.',
                designVariant: 'trust/stats-cards',
                bindings: [
                    'title' => 'content.title',
                    'items' => 'content.stats',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'items' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'testimonial_slider',
                category: 'marketing',
                label: 'Testimonial Slider',
                description: 'Rotating customer testimonials.',
                designVariant: 'testimonials/slider',
                bindings: [
                    'title' => 'content.title',
                    'items' => 'content.testimonials',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'items' => $this->arrayField(),
                    'autoplay' => $this->booleanField(),
                ]
            ),
            $this->definition(
                key: 'testimonial_masonry',
                category: 'marketing',
                label: 'Testimonial Masonry',
                description: 'Masonry grid testimonials layout.',
                designVariant: 'testimonials/masonry',
                bindings: [
                    'title' => 'content.title',
                    'items' => 'content.testimonials',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'items' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'pricing_tiers_pro',
                category: 'marketing',
                label: 'Pricing Tiers',
                description: 'Tiered pricing cards with feature lists.',
                designVariant: 'pricing/tiers-pro',
                bindings: [
                    'title' => 'content.title',
                    'subtitle' => 'content.subtitle',
                    'tiers' => 'billing.display_tiers',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'subtitle' => $this->stringField(),
                    'tiers' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'faq_accordion_plus',
                category: 'content',
                label: 'FAQ Accordion',
                description: 'Expandable frequently asked questions.',
                designVariant: 'faq/accordion-plus',
                bindings: [
                    'title' => 'content.title',
                    'items' => 'content.faq_items',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'items' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'cta_banner_bold',
                category: 'marketing',
                label: 'CTA Banner Bold',
                description: 'High-contrast call-to-action banner.',
                designVariant: 'cta/banner-bold',
                bindings: [
                    'headline' => 'content.headline',
                    'subtitle' => 'content.subtitle',
                    'button' => 'content.primary_cta',
                ],
                properties: [
                    'headline' => $this->stringField(),
                    'subtitle' => $this->stringField(),
                    'button' => $this->actionField(),
                ]
            ),
            $this->definition(
                key: 'trust_badges_inline',
                category: 'marketing',
                label: 'Trust Badges Inline',
                description: 'Inline compliance/payment/security badges.',
                designVariant: 'trust/badges-inline',
                bindings: [
                    'title' => 'content.title',
                    'badges' => 'content.badges',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'badges' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'services_grid_cards',
                category: 'business',
                label: 'Services Grid Cards',
                description: 'Grid of service cards with icons.',
                designVariant: 'services/grid-cards',
                bindings: [
                    'title' => 'content.title',
                    'items' => 'content.services',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'items' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'services_split_feature',
                category: 'business',
                label: 'Services Split Feature',
                description: 'Split layout highlighting one featured service.',
                designVariant: 'services/split-feature',
                bindings: [
                    'title' => 'content.title',
                    'featured' => 'content.featured_service',
                    'items' => 'content.services',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'featured' => $this->objectField(),
                    'items' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'process_timeline_steps',
                category: 'business',
                label: 'Process Timeline',
                description: 'Step-by-step timeline section.',
                designVariant: 'process/timeline-steps',
                bindings: [
                    'title' => 'content.title',
                    'steps' => 'content.steps',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'steps' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'team_profile_cards',
                category: 'business',
                label: 'Team Profile Cards',
                description: 'Team member profile cards.',
                designVariant: 'team/profile-cards',
                bindings: [
                    'title' => 'content.title',
                    'members' => 'content.team_members',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'members' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'portfolio_masonry_grid',
                category: 'business',
                label: 'Portfolio Masonry Grid',
                description: 'Masonry style project portfolio.',
                designVariant: 'portfolio/masonry-grid',
                bindings: [
                    'title' => 'content.title',
                    'projects' => 'content.projects',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'projects' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'before_after_showcase',
                category: 'business',
                label: 'Before/After Showcase',
                description: 'Side-by-side before and after comparisons.',
                designVariant: 'portfolio/before-after',
                bindings: [
                    'title' => 'content.title',
                    'items' => 'content.before_after_items',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'items' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'contact_split_form',
                category: 'business',
                label: 'Contact Split Form',
                description: 'Contact section with form + contact details.',
                designVariant: 'contact/split-form',
                bindings: [
                    'title' => 'content.title',
                    'contact' => 'global_settings.contact_json',
                    'form' => 'lead.capture_form',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'contact' => $this->objectField(),
                    'form' => $this->objectField(),
                ]
            ),
            $this->definition(
                key: 'map_contact_block',
                category: 'business',
                label: 'Map + Contact',
                description: 'Map embed section with address and contacts.',
                designVariant: 'contact/map-block',
                bindings: [
                    'title' => 'content.title',
                    'map_embed' => 'content.map_embed',
                    'contact' => 'global_settings.contact_json',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'map_embed' => $this->stringField(),
                    'contact' => $this->objectField(),
                ]
            ),
            $this->definition(
                key: 'rich_text_block',
                category: 'content',
                label: 'Rich Text Block',
                description: 'Long-form rich text/content section.',
                designVariant: 'content/rich-text',
                bindings: [
                    'title' => 'content.title',
                    'body' => 'content.body',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'body' => $this->stringField(),
                ]
            ),
            $this->definition(
                key: 'feature_comparison_table',
                category: 'content',
                label: 'Feature Comparison Table',
                description: 'Feature matrix table for plans/services.',
                designVariant: 'content/comparison-table',
                bindings: [
                    'title' => 'content.title',
                    'columns' => 'content.columns',
                    'rows' => 'content.rows',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'columns' => $this->arrayField(),
                    'rows' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'blog_cards_preview',
                category: 'content',
                label: 'Blog Cards Preview',
                description: 'Recent blog/article cards.',
                designVariant: 'content/blog-cards',
                bindings: [
                    'title' => 'content.title',
                    'items' => 'content.posts',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'items' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'gallery_mosaic',
                category: 'content',
                label: 'Gallery Mosaic',
                description: 'Image gallery mosaic layout.',
                designVariant: 'media/gallery-mosaic',
                bindings: [
                    'title' => 'content.title',
                    'images' => 'content.gallery_images',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'images' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'ecommerce_product_grid',
                category: 'ecommerce',
                label: 'Ecommerce Product Grid',
                description: 'Product grid section with filters.',
                designVariant: 'ecommerce/product-grid',
                bindings: [
                    'title' => 'content.title',
                    'collection' => 'ecommerce.products.list',
                    'show_filters' => 'ecommerce.products.filters',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'collection' => $this->stringField(),
                    'show_filters' => $this->booleanField(),
                ]
            ),
            $this->definition(
                key: 'ecommerce_featured_product',
                category: 'ecommerce',
                label: 'Ecommerce Featured Product',
                description: 'Single highlighted product block.',
                designVariant: 'ecommerce/featured-product',
                bindings: [
                    'product_sku' => 'ecommerce.products.featured',
                    'headline' => 'content.headline',
                ],
                properties: [
                    'headline' => $this->stringField(),
                    'product_sku' => $this->stringField(),
                    'cta' => $this->actionField(),
                ]
            ),
            $this->definition(
                key: 'ecommerce_category_tiles',
                category: 'ecommerce',
                label: 'Ecommerce Category Tiles',
                description: 'Category collection tiles.',
                designVariant: 'ecommerce/category-tiles',
                bindings: [
                    'title' => 'content.title',
                    'categories' => 'ecommerce.categories.list',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'categories' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'ecommerce_collection_banner',
                category: 'ecommerce',
                label: 'Ecommerce Collection Banner',
                description: 'Promotional collection banner.',
                designVariant: 'ecommerce/collection-banner',
                bindings: [
                    'headline' => 'content.headline',
                    'collection' => 'ecommerce.collections.selected',
                    'button' => 'content.primary_cta',
                ],
                properties: [
                    'headline' => $this->stringField(),
                    'subtitle' => $this->stringField(),
                    'collection' => $this->stringField(),
                    'button' => $this->actionField(),
                ]
            ),
            $this->definition(
                key: 'ecommerce_cart_summary_strip',
                category: 'ecommerce',
                label: 'Ecommerce Cart Summary Strip',
                description: 'Sticky cart summary + checkout CTA.',
                designVariant: 'ecommerce/cart-summary-strip',
                bindings: [
                    'label' => 'content.label',
                    'checkout_url' => 'ecommerce.checkout.url',
                ],
                properties: [
                    'label' => $this->stringField(),
                    'checkout_url' => $this->stringField(),
                    'show_item_count' => $this->booleanField(),
                ]
            ),
            $this->definition(
                key: 'ecommerce_checkout_faq',
                category: 'ecommerce',
                label: 'Checkout FAQ',
                description: 'Checkout-related FAQ/support section.',
                designVariant: 'ecommerce/checkout-faq',
                bindings: [
                    'title' => 'content.title',
                    'items' => 'content.faq_items',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'items' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'booking_widget_inline',
                category: 'booking',
                label: 'Booking Widget Inline',
                description: 'Inline booking widget launcher.',
                designVariant: 'booking/widget-inline',
                bindings: [
                    'headline' => 'content.headline',
                    'service_ids' => 'booking.services.ids',
                    'booking_url' => 'booking.widget.url',
                ],
                properties: [
                    'headline' => $this->stringField(),
                    'service_ids' => $this->arrayField(),
                    'booking_url' => $this->stringField(),
                ]
            ),
            $this->definition(
                key: 'booking_steps_timeline',
                category: 'booking',
                label: 'Booking Steps Timeline',
                description: 'How booking works in timeline format.',
                designVariant: 'booking/steps-timeline',
                bindings: [
                    'title' => 'content.title',
                    'steps' => 'booking.flow.steps',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'steps' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'booking_staff_cards',
                category: 'booking',
                label: 'Booking Staff Cards',
                description: 'Staff/resource cards with availability cues.',
                designVariant: 'booking/staff-cards',
                bindings: [
                    'title' => 'content.title',
                    'staff' => 'booking.staff.list',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'staff' => $this->arrayField(),
                ]
            ),
            $this->definition(
                key: 'booking_calendar_embed',
                category: 'booking',
                label: 'Booking Calendar Embed',
                description: 'Embedded availability calendar section.',
                designVariant: 'booking/calendar-embed',
                bindings: [
                    'title' => 'content.title',
                    'calendar_source' => 'booking.calendar.source',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'calendar_source' => $this->stringField(),
                ]
            ),
            $this->definition(
                key: 'booking_service_pricing',
                category: 'booking',
                label: 'Booking Service Pricing',
                description: 'Booking services and prices overview.',
                designVariant: 'booking/service-pricing',
                bindings: [
                    'title' => 'content.title',
                    'services' => 'booking.services.pricing',
                ],
                properties: [
                    'title' => $this->stringField(),
                    'services' => $this->arrayField(),
                ]
            ),
        ];
    }

    public function syncDefaults(bool $enabled = true): int
    {
        $definitions = $this->definitions();

        foreach ($definitions as $definition) {
            $definition['enabled'] = $enabled;

            SectionLibrary::query()->updateOrCreate(
                ['key' => $definition['key']],
                $definition
            );
        }

        return count($definitions);
    }

    public function defaultsCount(): int
    {
        return count($this->definitions());
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  array<int, string>  $required
     * @param  array<string, string>  $bindings
     * @return array{key:string,category:string,schema_json:array<string,mixed>,enabled:bool}
     */
    private function definition(
        string $key,
        string $category,
        string $label,
        string $description,
        string $designVariant,
        array $properties,
        array $required = [],
        array $bindings = []
    ): array {
        return [
            'key' => $key,
            'category' => $category,
            'schema_json' => [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                'type' => 'object',
                'properties' => $properties,
                'required' => $required,
                'additionalProperties' => true,
                '_meta' => [
                    'label' => $label,
                    'description' => $description,
                    'design_variant' => $designVariant,
                    'backend_updatable' => true,
                    'binding_target' => 'content_json.sections[].props',
                    'bindings' => $bindings,
                ],
            ],
            'enabled' => true,
        ];
    }

    /**
     * @return array{type:string}
     */
    private function stringField(): array
    {
        return ['type' => 'string'];
    }

    /**
     * @return array{type:string}
     */
    private function booleanField(): array
    {
        return ['type' => 'boolean'];
    }

    /**
     * @return array{type:string}
     */
    private function arrayField(): array
    {
        return ['type' => 'array'];
    }

    /**
     * @return array{type:string}
     */
    private function objectField(): array
    {
        return ['type' => 'object'];
    }

    /**
     * @return array<string, mixed>
     */
    private function actionField(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'label' => ['type' => 'string'],
                'url' => ['type' => 'string'],
            ],
            'required' => ['label', 'url'],
        ];
    }
}
