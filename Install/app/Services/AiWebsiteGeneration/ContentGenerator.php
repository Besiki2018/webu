<?php

namespace App\Services\AiWebsiteGeneration;

/**
 * Generates concise editable copy for canonical reusable sections.
 * This keeps non-ultra-cheap generation aligned with the same component
 * composition that the CMS builder and workspace projection understand.
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
        $websiteType = (string) ($brief['websiteType'] ?? 'business');
        $out = [];

        foreach ($pages as $pageIndex => $page) {
            $slug = (string) ($page['slug'] ?? 'home');
            $out[$pageIndex] = [];

            foreach ($page['sections'] ?? [] as $secIndex => $section) {
                $type = (string) ($section['section_type'] ?? 'webu_general_text_01');
                $out[$pageIndex][$secIndex] = $this->contentForSectionType($type, $brand, $cta, $slug, $websiteType);
            }
        }

        return $out;
    }

    /** @return array<string, mixed> */
    private function contentForSectionType(string $type, string $brand, string $cta, string $pageSlug, string $websiteType): array
    {
        return match ($type) {
            'webu_general_hero_01' => $this->heroContent($brand, $cta, $pageSlug, $websiteType),
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
            'webu_general_cards_01' => $this->cardsContent($brand, $pageSlug, $websiteType),
            'webu_general_grid_01' => $this->gridContent($brand, $pageSlug, $websiteType),
            'webu_general_cta_01' => $this->ctaContent($brand, $cta, $pageSlug, $websiteType),
            'webu_general_testimonials_01' => $this->testimonialsContent($brand, $websiteType),
            'webu_general_banner_01' => [
                'title' => $this->bannerHeadline($brand, $websiteType),
                'subtitle' => $this->bannerSubtitle($brand, $websiteType),
                'cta_label' => $cta,
                'cta_url' => $this->primaryRouteForType($websiteType),
            ],
            'webu_ecom_product_grid_01' => [
                'title' => $this->productGridTitle($pageSlug),
                'subtitle' => $pageSlug === 'shop' ? 'Browse the collection and refine by category.' : 'Discover featured picks and current best-sellers.',
                'add_to_cart_label' => 'Add to cart',
                'products_per_page' => $pageSlug === 'home' ? 8 : 12,
                'show_filters' => $pageSlug !== 'home',
                'show_sort' => $pageSlug !== 'home',
                'pagination_mode' => 'pagination',
            ],
            default => [],
        };
    }

    /** @return array<string, mixed> */
    private function heroContent(string $brand, string $cta, string $pageSlug, string $websiteType): array
    {
        $primaryRoute = $this->primaryRouteForType($websiteType);

        return [
            'eyebrow' => $this->eyebrowForType($websiteType),
            'title' => $this->headlineForPage($brand, $pageSlug, $websiteType),
            'subtitle' => $this->shortSubtitle($brand, $pageSlug, $websiteType),
            'description' => $this->bodyForPage($brand, $pageSlug, $websiteType),
            'buttonText' => $cta,
            'buttonLink' => $primaryRoute,
            'secondaryButtonText' => $pageSlug === 'home' ? $this->secondaryHeroAction($websiteType) : '',
            'secondaryButtonLink' => $this->secondaryRouteForType($websiteType),
            'imageAlt' => $brand.' hero image',
            'variant' => 'hero-1',
            'alignment' => 'left',
        ];
    }

    /** @return array<string, mixed> */
    private function cardsContent(string $brand, string $pageSlug, string $websiteType): array
    {
        return [
            'title' => $this->cardsTitle($pageSlug, $websiteType),
            'items' => $this->cardsItems($brand, $pageSlug, $websiteType),
            'variant' => 'cards-1',
        ];
    }

    /** @return array<string, mixed> */
    private function gridContent(string $brand, string $pageSlug, string $websiteType): array
    {
        return [
            'title' => $this->gridTitle($pageSlug, $websiteType),
            'items' => $this->gridItems($brand, $pageSlug, $websiteType),
            'columns' => $websiteType === 'portfolio' ? 2 : 3,
            'variant' => 'grid-1',
        ];
    }

    /** @return array<string, mixed> */
    private function ctaContent(string $brand, string $cta, string $pageSlug, string $websiteType): array
    {
        return [
            'title' => $this->ctaTitle($brand, $pageSlug, $websiteType),
            'subtitle' => $this->ctaSubtitle($brand, $websiteType),
            'buttonText' => $cta,
            'buttonLink' => $this->primaryRouteForType($websiteType),
            'variant' => 'cta-1',
        ];
    }

    /** @return array<string, mixed> */
    private function testimonialsContent(string $brand, string $websiteType): array
    {
        return [
            'title' => 'What people say about '.$brand,
            'items' => [
                [
                    'author' => 'Nino A.',
                    'role' => $this->testimonialRole($websiteType),
                    'quote' => "{$brand} made the experience feel clear, polished, and easy to trust from the very first visit.",
                    'avatar' => '',
                ],
                [
                    'author' => 'Giorgi M.',
                    'role' => $this->testimonialRole($websiteType),
                    'quote' => 'The team communicates well, moves quickly, and keeps the next step obvious.',
                    'avatar' => '',
                ],
                [
                    'author' => 'Salome K.',
                    'role' => $this->testimonialRole($websiteType),
                    'quote' => "We needed something modern and practical, and {$brand} delivered exactly that.",
                    'avatar' => '',
                ],
            ],
            'variant' => 'testimonials-1',
        ];
    }

    /** @return array<int, array<string, string>> */
    private function cardsItems(string $brand, string $pageSlug, string $websiteType): array
    {
        return match ($websiteType) {
            'ecommerce' => [
                ['title' => 'New arrivals', 'description' => "Fresh picks from {$brand}'s current collection.", 'image' => '', 'imageAlt' => 'New arrivals', 'link' => '/shop'],
                ['title' => 'Best sellers', 'description' => 'Popular items customers come back for.', 'image' => '', 'imageAlt' => 'Best sellers', 'link' => '/shop'],
                ['title' => 'Everyday essentials', 'description' => 'Reliable products for daily use.', 'image' => '', 'imageAlt' => 'Everyday essentials', 'link' => '/shop'],
            ],
            'portfolio' => [
                ['title' => 'Brand systems', 'description' => "{$brand} shapes identities with clarity and consistency.", 'image' => '', 'imageAlt' => 'Brand systems', 'link' => '/work'],
                ['title' => 'Web experiences', 'description' => 'Pages designed to feel polished and easy to use.', 'image' => '', 'imageAlt' => 'Web experiences', 'link' => '/work'],
                ['title' => 'Campaign assets', 'description' => 'Content and visuals built to support launches and promotions.', 'image' => '', 'imageAlt' => 'Campaign assets', 'link' => '/work'],
            ],
            'booking' => [
                ['title' => 'Clear service options', 'description' => "Visitors can quickly understand what {$brand} offers.", 'image' => '', 'imageAlt' => 'Service options', 'link' => '/services'],
                ['title' => 'Simple booking path', 'description' => 'The next step stays visible from every key page.', 'image' => '', 'imageAlt' => 'Booking path', 'link' => '/book'],
                ['title' => 'Friendly support', 'description' => 'Questions and follow-up stay easy to manage.', 'image' => '', 'imageAlt' => 'Friendly support', 'link' => '/contact'],
            ],
            default => [
                ['title' => 'Focused offer', 'description' => "{$brand} presents its core value with clarity.", 'image' => '', 'imageAlt' => 'Focused offer', 'link' => '/services'],
                ['title' => 'Trusted process', 'description' => 'Each step is explained in a way customers can follow.', 'image' => '', 'imageAlt' => 'Trusted process', 'link' => '/about'],
                ['title' => 'Clear next step', 'description' => 'Visitors always know how to continue the conversation.', 'image' => '', 'imageAlt' => 'Clear next step', 'link' => '/contact'],
            ],
        };
    }

    /** @return array<int, array<string, string>> */
    private function gridItems(string $brand, string $pageSlug, string $websiteType): array
    {
        return match ($websiteType) {
            'portfolio' => [
                ['title' => 'Studio launch', 'image' => '', 'imageAlt' => 'Studio launch', 'link' => '/work'],
                ['title' => 'Campaign refresh', 'image' => '', 'imageAlt' => 'Campaign refresh', 'link' => '/work'],
                ['title' => 'Editorial concept', 'image' => '', 'imageAlt' => 'Editorial concept', 'link' => '/work'],
                ['title' => 'Brand rollout', 'image' => '', 'imageAlt' => 'Brand rollout', 'link' => '/work'],
            ],
            default => [
                ['title' => $brand.' in action', 'image' => '', 'imageAlt' => $brand.' in action', 'link' => '#'],
                ['title' => 'Customer experience', 'image' => '', 'imageAlt' => 'Customer experience', 'link' => '#'],
                ['title' => 'Detail highlight', 'image' => '', 'imageAlt' => 'Detail highlight', 'link' => '#'],
                ['title' => 'Behind the scenes', 'image' => '', 'imageAlt' => 'Behind the scenes', 'link' => '#'],
            ],
        };
    }

    private function headlineForPage(string $brand, string $pageSlug, string $websiteType): string
    {
        return match ($pageSlug) {
            'home' => match ($websiteType) {
                'ecommerce' => "{$brand} products, curated for everyday use.",
                'portfolio' => "{$brand} creates work with a strong visual point of view.",
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
                default => "{$brand} presents the offer clearly, highlights credibility, and keeps the next step obvious.",
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
                ? 'The shopping flow is designed to stay clear at every step, from discovery and selection to checkout and follow-up.'
                : "{$brand} keeps this page useful and easy to navigate.",
            'book' => 'Choose the service that fits, pick a convenient time, and confirm the booking without friction.',
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
            'ecommerce' => 'Browse the latest selection and complete your order with a cleaner shopping flow.',
            'portfolio' => "Share the brief, review the work, and see how {$brand} approaches delivery.",
            'booking' => 'Choose a service and confirm a slot with minimal friction.',
            default => 'See the offer, understand the value, and take the next step with confidence.',
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

    private function cardsTitle(string $pageSlug, string $websiteType): string
    {
        if ($websiteType === 'ecommerce') {
            return 'Shop by focus';
        }

        return match ($pageSlug) {
            'services' => 'Services designed to help',
            'about' => 'What shapes the work',
            default => 'Why people choose this team',
        };
    }

    private function gridTitle(string $pageSlug, string $websiteType): string
    {
        return match ($websiteType) {
            'portfolio' => $pageSlug === 'work' ? 'Selected projects' : 'Recent work',
            default => $pageSlug === 'about' ? 'A closer look' : 'Gallery',
        };
    }

    private function ctaTitle(string $brand, string $pageSlug, string $websiteType): string
    {
        if ($pageSlug === 'contact') {
            return "Start a conversation with {$brand}.";
        }

        return match ($websiteType) {
            'ecommerce' => "Shop {$brand} today.",
            'portfolio' => "Plan the next project with {$brand}.",
            'booking' => "Book your next visit with {$brand}.",
            default => "Take the next step with {$brand}.",
        };
    }

    private function ctaSubtitle(string $brand, string $websiteType): string
    {
        return match ($websiteType) {
            'ecommerce' => 'Move from browsing to checkout with clear product discovery and a cleaner purchase flow.',
            'portfolio' => "Review the work, share the brief, and see how {$brand} approaches delivery.",
            'booking' => 'Choose a service, pick a time, and confirm the booking without unnecessary friction.',
            default => "See how {$brand} can help, understand the offer, and make contact with confidence.",
        };
    }

    private function primaryRouteForType(string $websiteType): string
    {
        return match ($websiteType) {
            'ecommerce' => '/shop',
            'portfolio' => '/work',
            'booking' => '/book',
            default => '/contact',
        };
    }

    private function secondaryRouteForType(string $websiteType): string
    {
        return match ($websiteType) {
            'ecommerce' => '/contact',
            'portfolio' => '/about',
            'booking' => '/services',
            default => '/about',
        };
    }

    private function secondaryHeroAction(string $websiteType): string
    {
        return match ($websiteType) {
            'ecommerce' => 'Ask a question',
            'portfolio' => 'See the process',
            'booking' => 'Explore services',
            default => 'Learn more',
        };
    }

    private function testimonialRole(string $websiteType): string
    {
        return match ($websiteType) {
            'ecommerce' => 'Customer',
            'portfolio' => 'Client',
            'booking' => 'Guest',
            default => 'Client',
        };
    }
}
