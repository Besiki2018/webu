<?php

/**
 * Webu Builder Components — ერთი წყარო design-system-თან და CMS-თან.
 *
 * ბილდერში ჩანს მხოლოდ ის კომპონენტები, რომლებიც ამ კონფიგშია.
 * პრევიუ რენდერდება იმავე კლასებით (webu-header, webu-hero, webu-footer, webu-banner, webu-newsletter)
 * და იმავე მონაცემების ველებით, რაც resources/js/components/design-system/ და resources/css/design-system/components.css.
 * სექციის მონაცემი ($section['data']) ივსება CMS-იდან (defaultSectionProps, enrichCanonicalSectionData).
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Builder section keys = Webu folder. key = სექციის ID ბილდერში/რევიზიაში.
    | folder = webu/components/ ფოლდერის სახელი. label = ბილდერში ჩანს.
    |--------------------------------------------------------------------------
    */
    'components' => [
        [
            'key' => 'hero',
            'folder' => 'Hero',
            'label' => 'ჰირო',
            'category' => 'general',
            'description' => 'მთავარი ჰერო ბანერი',
            'variants' => ['hero-1', 'hero-2', 'hero-3', 'hero-4', 'hero-5', 'hero-6', 'hero-7'],
            'default_variant' => 'hero-1',
        ],
        /* ჰედერი: კატალოგში და preview-ში მხოლოდ webu_header_01 (იხ. syntheticEntries + component-variants) */
        [
            'key' => 'banner',
            'folder' => 'Banner',
            'label' => 'ბანერი',
            'category' => 'general',
            'description' => 'ბანერი ან მოწვევა მოქმედებისთვის',
        ],
        [
            'key' => 'footer',
            'folder' => 'Footer',
            'label' => 'ფუტერი',
            'category' => 'general',
            'description' => 'საიტის ფუტერი',
            'variants' => ['footer-1', 'footer-2', 'footer-3', 'footer-4'],
            'default_variant' => 'footer-1',
        ],
        [
            'key' => 'webu_ecom_product_grid_01',
            'folder' => 'ProductGrid',
            'label' => 'პროდუქტების ბადე',
            'category' => 'ecommerce',
            'description' => 'პროდუქტების ბადე',
        ],
        [
            'key' => 'webu_ecom_product_card_01',
            'folder' => 'ProductCard',
            'label' => 'პროდუქტის ბარათი',
            'category' => 'ecommerce',
            'description' => 'პროდუქტის ბარათი (ვარიანტები: classic, minimal, modern, premium, compact)',
            'variants' => ['classic', 'minimal', 'modern', 'premium', 'compact'],
            'default_variant' => 'classic',
        ],
        [
            'key' => 'webu_ecom_category_list_01',
            'folder' => 'CategoryGrid',
            'label' => 'კატეგორიების ბადე',
            'category' => 'ecommerce',
            'description' => 'კატეგორიების სექცია',
        ],
        [
            'key' => 'webu_ecom_category_card_01',
            'folder' => 'CategoryCard',
            'label' => 'კატეგორიის ბარათი',
            'category' => 'ecommerce',
            'description' => 'კატეგორიის ბარათი',
        ],
        [
            'key' => 'webu_ecom_cart_page_01',
            'folder' => 'Cart',
            'label' => 'კალათა',
            'category' => 'ecommerce',
            'description' => 'კალათის სექცია',
        ],
        [
            'key' => 'webu_ecom_checkout_01',
            'folder' => 'Checkout',
            'label' => 'ჩექაუთი',
            'category' => 'ecommerce',
            'description' => 'შეკვეთის ფორმა',
        ],
        [
            'key' => 'webu_ecom_product_details_01',
            'folder' => 'ProductDetails',
            'label' => 'პროდუქტის დეტალი',
            'category' => 'ecommerce',
            'description' => 'პროდუქტის დეტალები',
        ],
        [
            'key' => 'webu_general_newsletter_01',
            'folder' => 'Newsletter',
            'label' => 'ნიუსლეტერი',
            'category' => 'general',
            'description' => 'ნიუსლეტერის ფორმა',
        ],
        [
            'key' => 'webu_general_placeholder_01',
            'folder' => 'Placeholder',
            'label' => 'ცარიელი ბლოკი',
            'category' => 'general',
            'description' => 'ცარიელი ბლოკი / placeholder',
            'production_ready' => false,
            'temporary' => true,
            'hidden_in_builder' => true,
        ],
        // Legacy keys used by existing templates (same folder as above)
        [
            'key' => 'webu_general_heading_01',
            'folder' => 'Hero',
            'label' => 'სათაური',
            'category' => 'general',
            'description' => 'ჰერო / სათაურის ბლოკი (legacy key)',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Layout primitives — always available; not from webu/components.
    |--------------------------------------------------------------------------
    */
    'layout_primitive_keys' => ['container', 'grid', 'section'],
];
