<?php

namespace App\Cms\Services;

use App\Cms\Contracts\CmsRepositoryContract;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Site;

class CmsSiteVisibilityService
{
    private const PAGE_GROUP_SLUGS = [
        'ecommerce' => ['shop', 'product', 'products', 'cart', 'checkout', 'login', 'account', 'orders', 'order'],
        'booking' => ['booking', 'bookings', 'calendar', 'appointment', 'appointments', 'reservation', 'reservations'],
        'portfolio' => ['portfolio', 'portfolios', 'project', 'projects', 'case-study', 'case-studies'],
        'real_estate' => ['property', 'properties', 'listing', 'listings'],
        'restaurant' => ['menu'],
        'hotel' => ['room', 'rooms'],
        'blog' => ['blog', 'post', 'posts', 'news', 'article', 'articles'],
    ];

    /**
     * @var array<string, array<string, mixed>>
     */
    private array $siteCache = [];

    public function __construct(
        protected CmsRepositoryContract $repository
    ) {}

    /**
     * @return array{
     *   capabilities: array<string, bool>,
     *   has_structured_content: bool,
     *   project_type: array{key: string, source: string}|null,
     *   pages: array<int, array{
     *     id: int|string,
     *     slug: string,
     *     section_types: array<int, string>,
     *     visible: bool
     *   }>
     * }
     */
    public function inspect(Site $site): array
    {
        $cacheKey = (string) $site->id;
        if (isset($this->siteCache[$cacheKey])) {
            return $this->siteCache[$cacheKey];
        }

        $allSectionTypes = [];
        $pages = [];

        foreach ($this->repository->listPages($site) as $page) {
            $pageEntry = $this->buildPageEntry($site, $page);
            $pages[] = $pageEntry;
            $allSectionTypes = [...$allSectionTypes, ...$pageEntry['section_types']];
        }

        $capabilities = $this->detectCapabilities($allSectionTypes);
        $hasStructuredContent = $allSectionTypes !== [];
        $projectType = $this->detectProjectType($capabilities);

        foreach ($pages as $index => $pageEntry) {
            $pages[$index]['visible'] = $hasStructuredContent
                ? $this->isPageVisible($pageEntry['slug'], $pageEntry['section_types'], $capabilities)
                : true;
        }

        return $this->siteCache[$cacheKey] = [
            'capabilities' => $capabilities,
            'has_structured_content' => $hasStructuredContent,
            'project_type' => $projectType,
            'pages' => $pages,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function capabilities(Site $site): array
    {
        return $this->inspect($site)['capabilities'];
    }

    /**
     * @return array{key: string, source: string}|null
     */
    public function detectedProjectType(Site $site): ?array
    {
        return $this->inspect($site)['project_type'];
    }

    public function hasCapability(Site $site, string $capability): bool
    {
        return (bool) ($this->capabilities($site)[$capability] ?? false);
    }

    public function hasStructuredContent(Site $site): bool
    {
        return (bool) ($this->inspect($site)['has_structured_content'] ?? false);
    }

    /**
     * @return array<int, array{
     *   id: int|string,
     *   slug: string,
     *   section_types: array<int, string>,
     *   visible: bool
     * }>
     */
    public function visiblePages(Site $site): array
    {
        return array_values(array_filter(
            $this->inspect($site)['pages'],
            static fn (array $page): bool => (bool) ($page['visible'] ?? false)
        ));
    }

    /**
     * @return array{id: int|string, slug: string, section_types: array<int, string>, visible: bool}
     */
    private function buildPageEntry(Site $site, Page $page): array
    {
        $revision = $this->resolvePrimaryRevision($site, $page);
        $sectionTypes = $this->extractSectionTypes($revision?->content_json);

        return [
            'id' => $page->id,
            'slug' => $this->normalizeSlug($page->slug),
            'section_types' => $sectionTypes,
            'visible' => true,
        ];
    }

    private function resolvePrimaryRevision(Site $site, Page $page): ?PageRevision
    {
        return $this->repository->latestRevision($site, $page)
            ?? $this->repository->latestPublishedRevision($site, $page);
    }

    /**
     * @return array<int, string>
     */
    private function extractSectionTypes(mixed $contentJson): array
    {
        $types = [];
        $content = is_array($contentJson) ? $contentJson : [];
        $sections = is_array($content['sections'] ?? null) ? $content['sections'] : [];

        $this->collectSectionTypes($sections, $types);

        return array_values(array_unique(array_filter($types)));
    }

    /**
     * @param  array<int, mixed>  $sections
     * @param  array<int, string>  $types
     */
    private function collectSectionTypes(array $sections, array &$types): void
    {
        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $type = $this->normalizeSectionType($section['type'] ?? null);
            if ($type !== '') {
                $types[] = $type;
            }

            $props = is_array($section['props'] ?? null) ? $section['props'] : [];
            if (is_array($props['sections'] ?? null)) {
                $this->collectSectionTypes($props['sections'], $types);
            }

            if (is_array($section['sections'] ?? null)) {
                $this->collectSectionTypes($section['sections'], $types);
            }
        }
    }

    /**
     * @param  array<int, string>  $sectionTypes
     * @return array<string, bool>
     */
    private function detectCapabilities(array $sectionTypes): array
    {
        $set = array_fill_keys($sectionTypes, true);
        $hasPrefix = static function (string $prefix) use ($set): bool {
            foreach (array_keys($set) as $type) {
                if (str_starts_with($type, $prefix)) {
                    return true;
                }
            }

            return false;
        };
        $hasAny = static function (array $candidates) use ($set): bool {
            foreach ($candidates as $candidate) {
                if (isset($set[$candidate])) {
                    return true;
                }
            }

            return false;
        };

        $ecommerce = $hasPrefix('webu_ecom_');
        $booking = $hasPrefix('webu_book_')
            || $hasPrefix('webu_svc_')
            || $hasAny([
                'webu_rest_reservation_slots_01',
                'webu_rest_reservation_form_01',
                'webu_hotel_room_availability_01',
                'webu_hotel_reservation_form_01',
            ]);
        $portfolio = $hasPrefix('webu_portfolio_');
        $blog = $hasPrefix('webu_blog_');
        $realEstate = $hasPrefix('webu_realestate_');
        $restaurant = $hasPrefix('webu_rest_');
        $hotel = $hasPrefix('webu_hotel_');

        return [
            'ecommerce' => $ecommerce,
            'booking' => $booking,
            'payments' => $ecommerce || $booking,
            'shipping' => $ecommerce,
            'ecommerce_inventory' => $ecommerce,
            'ecommerce_accounting' => $ecommerce,
            'ecommerce_rs' => $ecommerce,
            'booking_team_scheduling' => $booking && $hasAny([
                'webu_book_calendar_01',
                'webu_svc_staff_grid_01',
            ]),
            'booking_finance' => $booking && $hasAny([
                'webu_book_finance_summary_01',
            ]),
            'booking_advanced_calendar' => $booking && $hasAny([
                'webu_book_calendar_01',
            ]),
            'portfolio' => $portfolio,
            'blog' => $blog,
            'real_estate' => $realEstate,
            'restaurant' => $restaurant,
            'hotel' => $hotel,
        ];
    }

    /**
     * @param  array<string, bool>  $capabilities
     * @return array{key: string, source: string}|null
     */
    private function detectProjectType(array $capabilities): ?array
    {
        $priority = [
            'restaurant',
            'hotel',
            'real_estate',
            'portfolio',
            'ecommerce',
            'booking',
        ];

        foreach ($priority as $key) {
            if (($capabilities[$key] ?? false) === true) {
                return [
                    'key' => $key,
                    'source' => 'site.content_sections',
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $pageSectionTypes
     * @param  array<string, bool>  $siteCapabilities
     */
    private function isPageVisible(string $slug, array $pageSectionTypes, array $siteCapabilities): bool
    {
        $pageCapabilities = $this->detectCapabilities($pageSectionTypes);
        foreach ($pageCapabilities as $key => $enabled) {
            if ($enabled) {
                return true;
            }
        }

        foreach (self::PAGE_GROUP_SLUGS as $capability => $slugs) {
            if (in_array($slug, $slugs, true) && ! ($siteCapabilities[$capability] ?? false)) {
                return false;
            }
        }

        return true;
    }

    private function normalizeSectionType(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return strtolower(trim($value));
    }

    private function normalizeSlug(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }

        return strtolower(trim($value));
    }
}
