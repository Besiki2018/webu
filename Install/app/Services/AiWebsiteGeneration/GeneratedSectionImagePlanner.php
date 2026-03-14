<?php

namespace App\Services\AiWebsiteGeneration;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class GeneratedSectionImagePlanner
{
    /**
     * @var array<string, array<int, array{path: string, role: string, orientation: string}>>
     */
    private const SLOT_MAP = [
        'banner' => [
            ['path' => 'backgroundImage', 'role' => 'hero', 'orientation' => 'landscape'],
        ],
        'webu_general_banner_01' => [
            ['path' => 'backgroundImage', 'role' => 'hero', 'orientation' => 'landscape'],
        ],
        'webu_general_hero_01' => [
            ['path' => 'image', 'role' => 'hero', 'orientation' => 'landscape'],
            ['path' => 'overlayImageUrl', 'role' => 'hero', 'orientation' => 'landscape'],
            ['path' => 'backgroundImage', 'role' => 'hero', 'orientation' => 'landscape'],
            ['path' => 'statAvatars.*.url', 'role' => 'team', 'orientation' => 'portrait'],
        ],
        'webu_general_cards_01' => [
            ['path' => 'items.*.image', 'role' => 'features', 'orientation' => 'landscape'],
        ],
        'webu_general_grid_01' => [
            ['path' => 'items.*.image', 'role' => 'gallery', 'orientation' => 'square'],
        ],
        'webu_general_cta_01' => [
            ['path' => 'backgroundImage', 'role' => 'cta', 'orientation' => 'landscape'],
        ],
        'webu_general_image_01' => [
            ['path' => 'image_url', 'role' => 'content', 'orientation' => 'landscape'],
        ],
        'webu_general_card_01' => [
            ['path' => 'image_url', 'role' => 'features', 'orientation' => 'landscape'],
        ],
        'webu_general_testimonials_01' => [
            ['path' => 'items.*.avatar', 'role' => 'testimonials', 'orientation' => 'portrait'],
            ['path' => 'items.*.image_url', 'role' => 'testimonials', 'orientation' => 'portrait'],
        ],
    ];

    /**
     * @var array<string, array<int, string>>
     */
    private const PLACEHOLDERS = [
        'hero' => [
            'demo/hero/hero-1.svg',
            'demo/hero/hero-2.svg',
            'demo/hero/hero-3.svg',
        ],
        'gallery' => [
            'demo/gallery/gallery-1.svg',
            'demo/gallery/gallery-2.svg',
            'demo/gallery/gallery-3.svg',
            'demo/gallery/gallery-4.svg',
        ],
        'features' => [
            'demo/gallery/gallery-1.svg',
            'demo/gallery/gallery-2.svg',
            'demo/gallery/gallery-3.svg',
        ],
        'team' => [
            'demo/people/person-1.svg',
            'demo/people/person-2.svg',
            'demo/people/person-3.svg',
            'demo/people/person-4.svg',
            'demo/people/person-5.svg',
        ],
        'testimonials' => [
            'demo/people/person-1.svg',
            'demo/people/person-2.svg',
            'demo/people/person-3.svg',
            'demo/people/person-4.svg',
        ],
        'cta' => [
            'demo/hero/hero-1.svg',
            'demo/hero/hero-2.svg',
        ],
        'content' => [
            'demo/gallery/gallery-1.svg',
            'demo/gallery/gallery-2.svg',
        ],
    ];

    /**
     * @param  array<string, mixed>  $brief
     * @param  array<string, mixed>  $pagePlan
     * @param  array<string, mixed>  $sectionPlan
     * @param  array<string, mixed>  $settings
     * @return array<int, array{
     *   path: string,
     *   role: string,
     *   orientation: string,
     *   query: string,
     *   fallback_url: string,
     *   provider_limit: int
     * }>
     */
    public function buildTargets(array $brief, array $pagePlan, array $sectionPlan, array $settings): array
    {
        $sectionType = $this->normalizeText((string) ($sectionPlan['section_type'] ?? ''));
        $slotCandidates = $this->resolveSlotCandidates($sectionType, $settings);
        if ($slotCandidates === []) {
            return [];
        }

        $targets = [];
        $roleCounts = [];

        foreach ($slotCandidates as $slot) {
            $path = $this->normalizeText((string) ($slot['path'] ?? ''));
            $role = $this->normalizeText((string) ($slot['role'] ?? 'content'));
            $orientation = $this->normalizeText((string) ($slot['orientation'] ?? 'landscape')) ?: 'landscape';

            if ($path === '' || $role === 'logo') {
                continue;
            }

            if (! $this->isEmptyImageValue(Arr::get($settings, $path))) {
                continue;
            }

            $maxForRole = $this->maxTargetsForRole($role);
            $roleCount = $roleCounts[$role] ?? 0;
            if ($roleCount >= $maxForRole) {
                continue;
            }

            $query = $this->buildQuery($brief, $pagePlan, $settings, $path, $role);
            if ($query === '') {
                continue;
            }

            $roleCounts[$role] = $roleCount + 1;
            $targets[] = [
                'path' => $path,
                'role' => $role,
                'orientation' => $orientation,
                'query' => $query,
                'fallback_url' => $this->placeholderUrl($role, $path),
                'provider_limit' => 5,
            ];
        }

        return $targets;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array{path: string, role: string, orientation: string}>
     */
    private function resolveSlotCandidates(string $sectionType, array $settings): array
    {
        $normalizedSectionType = Str::lower($sectionType);
        $configured = self::SLOT_MAP[$normalizedSectionType] ?? $this->fallbackSlotsForSectionType($normalizedSectionType);
        $expanded = [];

        foreach ($configured as $slot) {
            $paths = $this->expandPathPattern($slot['path'], $settings);
            if ($paths === [] && ! str_contains($slot['path'], '*')) {
                $paths = [$slot['path']];
            }

            foreach ($paths as $path) {
                $expanded[] = [
                    'path' => $path,
                    'role' => $slot['role'],
                    'orientation' => $slot['orientation'],
                ];
            }
        }

        if ($expanded !== []) {
            return $expanded;
        }

        return $this->detectImageSlotsRecursively($settings);
    }

    /**
     * @return array<int, array{path: string, role: string, orientation: string}>
     */
    private function fallbackSlotsForSectionType(string $sectionType): array
    {
        return match (true) {
            str_contains($sectionType, 'hero'),
            str_contains($sectionType, 'banner') => [
                ['path' => 'image', 'role' => 'hero', 'orientation' => 'landscape'],
                ['path' => 'backgroundImage', 'role' => 'hero', 'orientation' => 'landscape'],
            ],
            str_contains($sectionType, 'feature'),
            str_contains($sectionType, 'service'),
            str_contains($sectionType, 'card') => [
                ['path' => 'items.*.image', 'role' => 'features', 'orientation' => 'landscape'],
                ['path' => 'image_url', 'role' => 'features', 'orientation' => 'landscape'],
            ],
            str_contains($sectionType, 'gallery'),
            str_contains($sectionType, 'grid'),
            str_contains($sectionType, 'portfolio') => [
                ['path' => 'items.*.image', 'role' => 'gallery', 'orientation' => 'square'],
                ['path' => 'image', 'role' => 'gallery', 'orientation' => 'square'],
            ],
            str_contains($sectionType, 'testimonial'),
            str_contains($sectionType, 'review'),
            str_contains($sectionType, 'team'),
            str_contains($sectionType, 'staff') => [
                ['path' => 'items.*.avatar', 'role' => 'testimonials', 'orientation' => 'portrait'],
                ['path' => 'items.*.image_url', 'role' => 'testimonials', 'orientation' => 'portrait'],
            ],
            str_contains($sectionType, 'cta'),
            str_contains($sectionType, 'contact'),
            str_contains($sectionType, 'form') => [
                ['path' => 'backgroundImage', 'role' => 'cta', 'orientation' => 'landscape'],
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    private function expandPathPattern(string $pattern, array $settings): array
    {
        $segments = array_values(array_filter(explode('.', $pattern), static fn (string $segment): bool => $segment !== ''));
        if ($segments === []) {
            return [];
        }

        return $this->expandPathSegments($segments, $settings);
    }

    /**
     * @param  array<int, string>  $segments
     * @return array<int, string>
     */
    private function expandPathSegments(array $segments, mixed $current, string $prefix = ''): array
    {
        if ($segments === []) {
            return $prefix !== '' ? [$prefix] : [];
        }

        $segment = array_shift($segments);
        if ($segment === null) {
            return [];
        }

        if ($segment === '*') {
            if (! is_array($current)) {
                return [];
            }

            $paths = [];
            foreach (array_keys($current) as $index) {
                if (! is_int($index) && ! ctype_digit((string) $index)) {
                    continue;
                }

                $nextPrefix = $prefix === '' ? (string) $index : $prefix.'.'.$index;
                $paths = [
                    ...$paths,
                    ...$this->expandPathSegments($segments, $current[$index] ?? null, $nextPrefix),
                ];
            }

            return $paths;
        }

        $nextPrefix = $prefix === '' ? $segment : $prefix.'.'.$segment;
        $nextValue = is_array($current) ? ($current[$segment] ?? null) : null;

        if ($segments === []) {
            return [$nextPrefix];
        }

        return $this->expandPathSegments($segments, $nextValue, $nextPrefix);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array{path: string, role: string, orientation: string}>
     */
    private function detectImageSlotsRecursively(array $settings, string $prefix = ''): array
    {
        $slots = [];

        foreach ($settings as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $path = $prefix === '' ? $key : $prefix.'.'.$key;
            $normalizedKey = Str::lower(trim($key));

            if ($this->looksLikeImageKey($normalizedKey)) {
                $role = $this->inferRoleFromPath($path);
                $slots[] = [
                    'path' => $path,
                    'role' => $role,
                    'orientation' => $role === 'gallery' ? 'square' : ($role === 'team' || $role === 'testimonials' ? 'portrait' : 'landscape'),
                ];
            }

            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $index => $item) {
                        if (is_array($item)) {
                            $slots = [
                                ...$slots,
                                ...$this->detectImageSlotsRecursively($item, $path.'.'.$index),
                            ];
                        }
                    }
                } else {
                    $slots = [
                        ...$slots,
                        ...$this->detectImageSlotsRecursively($value, $path),
                    ];
                }
            }
        }

        return $slots;
    }

    private function looksLikeImageKey(string $key): bool
    {
        return $key !== '' && (
            preg_match('/(^|_)(image|photo|picture|thumbnail|avatar|cover|backgroundimage|background_image|logo|overlayimageurl|imageurl)(_|$)/', $key) === 1
            || preg_match('/^image_\d+_url$/', $key) === 1
        );
    }

    private function inferRoleFromPath(string $path): string
    {
        $probe = Str::lower($path);

        return match (true) {
            str_contains($probe, 'avatar'),
            str_contains($probe, 'team'),
            str_contains($probe, 'staff') => 'team',
            str_contains($probe, 'testimonial'),
            str_contains($probe, 'review') => 'testimonials',
            str_contains($probe, 'gallery'),
            str_contains($probe, 'grid') => 'gallery',
            str_contains($probe, 'card'),
            str_contains($probe, 'service'),
            str_contains($probe, 'feature') => 'features',
            str_contains($probe, 'cta'),
            str_contains($probe, 'contact'),
            str_contains($probe, 'background') => 'cta',
            default => 'content',
        };
    }

    private function maxTargetsForRole(string $role): int
    {
        return match ($role) {
            'hero', 'cta', 'content' => 1,
            'features', 'team', 'testimonials' => 3,
            'gallery' => 4,
            default => 2,
        };
    }

    /**
     * @param  array<string, mixed>  $brief
     * @param  array<string, mixed>  $pagePlan
     * @param  array<string, mixed>  $settings
     */
    private function buildQuery(array $brief, array $pagePlan, array $settings, string $path, string $role): string
    {
        $subject = $this->resolveSubject($brief);
        $style = $this->normalizeText((string) ($brief['style'] ?? 'modern')) ?: 'modern';
        $pageContext = $this->normalizeText((string) ($pagePlan['title'] ?? $pagePlan['slug'] ?? ''));
        $itemContext = $this->resolveItemContext($settings, $path);

        $terms = match ($role) {
            'hero' => [$style, $subject, $this->heroQualifier($subject)],
            'features' => [$itemContext !== '' ? $itemContext : $subject, $itemContext !== '' ? null : 'service'],
            'gallery' => [$itemContext !== '' ? $itemContext : $subject, $this->galleryQualifier($subject)],
            'team', 'testimonials' => [$itemContext !== '' ? $itemContext : $subject, 'portrait'],
            'cta' => [$subject, $this->ctaQualifier($subject, $pageContext)],
            default => [$itemContext !== '' ? $itemContext : $subject, 'photo'],
        };

        if ($pageContext !== '' && ! in_array(Str::lower($pageContext), ['home', 'about', 'services', 'contact', 'shop', 'work', 'book'], true) && ! $this->containsNormalizedTerm($terms, $pageContext)) {
            $terms[] = $pageContext;
        }

        return $this->joinTerms($terms);
    }

    private function resolveSubject(array $brief): string
    {
        $prompt = $this->normalizeText((string) ($brief['sourcePrompt'] ?? ''));
        if ($prompt !== '') {
            $cleaned = preg_replace('/\b(create|build|make|generate|design|website|site|landing|page|for|with|using|need|want|a|an|the|modern|minimal|luxury|playful|corporate)\b/i', ' ', $prompt);
            $cleaned = is_string($cleaned) ? preg_replace('/\s+/', ' ', $cleaned) : null;
            $cleaned = is_string($cleaned) ? trim($cleaned) : '';
            if ($cleaned !== '') {
                return Str::limit($cleaned, 80, '');
            }
        }

        return $this->normalizeText((string) ($brief['businessType'] ?? $brief['category'] ?? $brief['websiteType'] ?? 'business')) ?: 'business';
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private function resolveItemContext(array $settings, string $path): string
    {
        $segments = explode('.', $path);
        $itemSegments = [];

        foreach ($segments as $index => $segment) {
            $itemSegments[] = $segment;
            if (ctype_digit($segment)) {
                break;
            }

            if ($index >= count($segments) - 2) {
                break;
            }
        }

        $contextCandidates = [];
        if ($itemSegments !== []) {
            $itemValue = Arr::get($settings, implode('.', $itemSegments));
            if (is_array($itemValue)) {
                $contextCandidates[] = $this->firstContextString($itemValue);
            }
        }

        $contextCandidates[] = $this->firstContextString($settings);

        foreach ($contextCandidates as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function firstContextString(array $value): string
    {
        foreach (['title', 'headline', 'name', 'label', 'heading', 'eyebrow', 'subtitle', 'role'] as $key) {
            $candidate = $this->normalizeText((string) ($value[$key] ?? ''));
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    private function heroQualifier(string $subject): string
    {
        return match (true) {
            Str::contains($subject, ['restaurant', 'cafe', 'bistro']) => 'dining interior',
            Str::contains($subject, ['clinic', 'veterinary', 'medical', 'dentist']) => 'interior',
            Str::contains($subject, ['salon', 'beauty', 'barber', 'grooming']) => 'salon interior',
            Str::contains($subject, ['product', 'fashion', 'electronics', 'shop', 'store']) => 'product showcase',
            default => 'interior',
        };
    }

    private function galleryQualifier(string $subject): string
    {
        return match (true) {
            Str::contains($subject, ['restaurant', 'cafe', 'bistro']) => 'dining interior',
            Str::contains($subject, ['clinic', 'veterinary', 'medical', 'dentist']) => 'interior',
            Str::contains($subject, ['salon', 'beauty', 'barber', 'grooming']) => 'lifestyle photography',
            default => 'lifestyle photography',
        };
    }

    private function ctaQualifier(string $subject, string $pageContext): string
    {
        if (Str::contains($subject, ['product', 'fashion', 'electronics', 'shop', 'store'])) {
            return 'product showcase';
        }

        if (Str::contains(Str::lower($pageContext), ['book', 'appointment', 'contact'])) {
            return 'consultation';
        }

        return 'consultation';
    }

    /**
     * @param  array<int, string|null>  $terms
     */
    private function joinTerms(array $terms): string
    {
        $normalized = [];

        foreach ($terms as $term) {
            $clean = $this->normalizeText((string) $term);
            if ($clean === '') {
                continue;
            }

            $key = Str::lower($clean);
            if (isset($normalized[$key])) {
                continue;
            }

            $normalized[$key] = $clean;
        }

        return implode(' ', array_values($normalized));
    }

    /**
     * @param  array<int, string|null>  $terms
     */
    private function containsNormalizedTerm(array $terms, string $value): bool
    {
        $needle = Str::lower($this->normalizeText($value));
        if ($needle === '') {
            return false;
        }

        foreach ($terms as $term) {
            if (Str::contains(Str::lower((string) $term), $needle)) {
                return true;
            }
        }

        return false;
    }

    private function placeholderUrl(string $role, string $path): string
    {
        $pool = self::PLACEHOLDERS[$role] ?? self::PLACEHOLDERS['content'];
        $index = abs(crc32($path)) % max(1, count($pool));

        return asset($pool[$index]);
    }

    private function isEmptyImageValue(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        if (is_string($value)) {
            return trim($value) === '';
        }

        if (is_array($value)) {
            $url = $value['url'] ?? $value['src'] ?? null;

            return $url === null || (is_string($url) && trim($url) === '');
        }

        return false;
    }

    private function normalizeText(string $value): string
    {
        return trim(preg_replace('/\s+/', ' ', $value) ?? '');
    }
}
