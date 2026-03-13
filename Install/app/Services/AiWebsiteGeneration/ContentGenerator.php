<?php

namespace App\Services\AiWebsiteGeneration;

/**
 * Generates SHORT copy for sections. No lorem ipsum; all editable.
 * Patches content_json only; keeps style_json intact.
 */
class ContentGenerator
{
    /**
     * @param  array{brandName: string, websiteType: string, cta: string|null, businessType?: string|null}  $brief
     * @param  array<int, array{slug: string, title: string, sections: array}>  $pages
     * @return array<int, array<int, array<string, mixed>>>  page index => section index => content patch
     */
    public function generate(array $brief, array $pages): array
    {
        $brand = $brief['brandName'] ?? 'Your Brand';
        $cta = $brief['cta'] ?? 'Get in touch';
        $out = [];
        foreach ($pages as $pageIndex => $page) {
            $slug = $page['slug'] ?? 'home';
            $out[$pageIndex] = [];
            foreach ($page['sections'] ?? [] as $secIndex => $section) {
                $type = $section['section_type'] ?? 'content';
                $out[$pageIndex][$secIndex] = $this->contentForSectionType($type, $brand, $cta, $slug, (string) ($brief['websiteType'] ?? 'business'));
            }
        }
        return $out;
    }

    /** @return array<string, mixed> */
    private function contentForSectionType(string $type, string $brand, string $cta, string $pageSlug, string $websiteType): array
    {
        return match ($type) {
            'webu_general_heading_01' => [
                'headline' => $this->headlineForPage($brand, $pageSlug, $websiteType),
                'title' => $this->headlineForPage($brand, $pageSlug, $websiteType),
                'subtitle' => $this->shortSubtitle($brand, $pageSlug, $websiteType),
                'eyebrow' => $pageSlug === 'home' ? $this->eyebrowForType($websiteType) : '',
                'layout_variant' => $pageSlug === 'home' ? 'centered' : 'compact',
                'style_variant' => $pageSlug === 'home' ? 'minimal' : 'soft',
            ],
            'webu_general_text_01' => [
                'title' => $this->sectionTitleForPage($pageSlug),
                'body' => $this->bodyForPage($brand, $pageSlug, $websiteType),
            ],
            'banner' => [
                'headline' => $this->bannerHeadline($brand, $websiteType),
                'title' => $this->bannerHeadline($brand, $websiteType),
                'subtitle' => $this->bannerSubtitle($brand, $websiteType),
                'cta_label' => $cta,
                'cta_url' => $websiteType === 'ecommerce' ? '/shop' : '/contact',
            ],
            'webu_ecom_product_grid_01' => [
                'title' => $this->productGridTitle($pageSlug),
                'subtitle' => $pageSlug === 'shop' ? 'Browse the collection and refine by category.' : '',
                'add_to_cart_label' => 'Add to cart',
                'products_per_page' => $pageSlug === 'home' ? 8 : 12,
                'show_filters' => $pageSlug !== 'home',
                'show_sort' => $pageSlug !== 'home',
                'pagination_mode' => 'pagination',
            ],
            default => [],
        };
    }

    private function headlineForPage(string $brand, string $pageSlug, string $websiteType): string
    {
        return match ($pageSlug) {
            'home' => match ($websiteType) {
                'ecommerce' => "{$brand} products, curated for everyday use.",
                'portfolio' => "{$brand} crafts work that stands out.",
                'booking' => "{$brand} makes booking simple and clear.",
                default => "{$brand} helps customers move forward with confidence.",
            },
            'about' => "Get to know {$brand}.",
            'services' => "What {$brand} offers.",
            'contact' => "Talk to {$brand}.",
            'shop' => "Browse {$brand}'s collection.",
            'product' => 'Find the right product faster.',
            'cart' => 'Review your cart before checkout.',
            'checkout' => 'Complete your order with confidence.',
            'work' => "Selected work from {$brand}.",
            'book' => "Choose a time that works for you.",
            default => $this->sectionTitleForPage($pageSlug),
        };
    }

    private function shortSubtitle(string $brand, string $pageSlug, string $websiteType): string
    {
        return match ($pageSlug) {
            'home' => match ($websiteType) {
                'ecommerce' => "Discover featured products, trusted picks, and a smoother way to shop with {$brand}.",
                'portfolio' => "See recent projects, the thinking behind the work, and how {$brand} approaches each brief.",
                'booking' => "Explore services, understand the process, and book time with {$brand} in just a few steps.",
                default => "{$brand} presents the core offer clearly, highlights credibility, and keeps the next step obvious.",
            },
            'about' => "{$brand} focuses on quality, clarity, and a strong customer experience.",
            'services' => "A concise view of the services, outcomes, and value {$brand} brings to each client.",
            'contact' => "Use the details below to start a conversation with {$brand}.",
            'shop' => "Explore the latest products, compare options, and find the right fit.",
            'product' => "View product highlights, pricing, and buying details in one place.",
            'cart' => "Review items, quantities, and totals before you continue.",
            'checkout' => "Finish the purchase with clear pricing, delivery, and payment details.",
            'work' => "A focused selection of projects that shows the range and quality of {$brand}.",
            'book' => "Pick a service and secure a time without unnecessary steps.",
            default => "{$brand} keeps this page concise, useful, and easy to act on.",
        };
    }

    private function bodyForPage(string $brand, string $pageSlug, string $websiteType): string
    {
        return match ($pageSlug) {
            'about' => "{$brand} is built around a simple promise: useful work, clear communication, and a polished experience from first visit to final result.",
            'services' => "{$brand} structures each service around clear outcomes, practical execution, and a process that is easy for customers to understand.",
            'contact' => "Reach out to {$brand} for questions, quotes, orders, or support. A short message is enough to get the conversation started.",
            'work' => "Each project reflects {$brand}'s attention to detail, consistency, and a preference for solutions that are both attractive and practical.",
            'shop', 'product', 'cart', 'checkout' => $websiteType === 'ecommerce'
                ? "The shopping flow is designed to stay clear at every step, from discovery and selection to checkout and follow-up."
                : "{$brand} keeps this page useful and easy to navigate.",
            'book' => "Choose the service that fits, pick a convenient time, and confirm the booking without friction.",
            default => "{$brand} uses this section to add context, trust, and the information a visitor needs before taking the next step.",
        };
    }

    private function sectionTitleForPage(string $pageSlug): string
    {
        return match ($pageSlug) {
            'about' => 'About',
            'services' => 'Services',
            'contact' => 'Contact',
            'work' => 'Work',
            'book' => 'Book',
            'shop' => 'Shop',
            'product' => 'Product Details',
            'cart' => 'Cart',
            'checkout' => 'Checkout',
            default => ucfirst(str_replace('-', ' ', $pageSlug)),
        };
    }

    private function eyebrowForType(string $websiteType): string
    {
        return match ($websiteType) {
            'ecommerce' => 'Featured collection',
            'portfolio' => 'Creative portfolio',
            'booking' => 'Easy online booking',
            default => 'Professional online presence',
        };
    }

    private function bannerHeadline(string $brand, string $websiteType): string
    {
        return match ($websiteType) {
            'ecommerce' => "Ready to explore {$brand}?",
            'portfolio' => "Start the next project with {$brand}.",
            'booking' => "Book time with {$brand}.",
            default => "Move the conversation forward with {$brand}.",
        };
    }

    private function bannerSubtitle(string $brand, string $websiteType): string
    {
        return match ($websiteType) {
            'ecommerce' => "Browse the latest selection and complete your order with a cleaner shopping flow.",
            'portfolio' => "Share the brief, review the work, and see how {$brand} approaches delivery.",
            'booking' => "Choose a service and confirm a slot with minimal friction.",
            default => "See the offer, understand the value, and take the next step with confidence.",
        };
    }

    private function productGridTitle(string $pageSlug): string
    {
        return match ($pageSlug) {
            'home' => 'Featured Products',
            'shop' => 'All Products',
            'product' => 'Related Products',
            'cart' => 'You May Also Like',
            'checkout' => 'Recommended Add-ons',
            default => 'Products',
        };
    }
}
