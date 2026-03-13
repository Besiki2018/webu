<?php

namespace App\Services\AiWebsiteGeneration;

use Illuminate\Support\Str;

/**
 * Extracts a structured WebsiteBrief from user prompt (fast intake).
 * Output is minimal and validated for downstream generators.
 */
class WebsiteBriefExtractor
{
    private const WEBSITE_TYPES = ['business', 'ecommerce', 'portfolio', 'booking'];

    private const STYLES = ['modern', 'minimal', 'luxury', 'playful', 'corporate'];

    private const TONE_MAP = [
        'professional' => 'professional',
        'friendly' => 'playful',
        'luxury' => 'luxury',
        'minimal' => 'minimal',
        'corporate' => 'corporate',
    ];

    /**
     * @return array{websiteType: string, businessType: string|null, brandName: string, tone: string, style: string, language: string, mustHavePages: array<int, string>, primaryGoal: string|null, cta: string|null}
     */
    public function extract(string $userPrompt): array
    {
        $lower = Str::lower($userPrompt);
        $websiteType = $this->detectWebsiteType($lower);
        $businessType = $this->detectBusinessType($lower);
        $brandName = $this->extractBrandName($userPrompt) ?? $this->fallbackBrandNameFromPrompt($userPrompt, $websiteType);
        $tone = $this->detectTone($lower);
        $style = $this->detectStyle($lower);
        $language = $this->detectLanguage($lower);
        $mustHavePages = $this->defaultPagesForType($websiteType);
        $primaryGoal = $this->primaryGoalForType($websiteType);
        $cta = $this->defaultCtaForType($websiteType);

        $category = $this->categoryForMatrix($websiteType, $businessType);

        return [
            'websiteType' => $websiteType,
            'businessType' => $businessType,
            'category' => $category,
            'brandName' => $brandName,
            'tone' => $tone,
            'style' => $style,
            'language' => $language,
            'mustHavePages' => $mustHavePages,
            'primaryGoal' => $primaryGoal,
            'cta' => $cta,
        ];
    }

    private function detectWebsiteType(string $lower): string
    {
        if (Str::contains($lower, ['shop', 'store', 'ecommerce', 'e-commerce', 'product', 'sell'])) {
            return 'ecommerce';
        }
        if (Str::contains($lower, ['portfolio', 'gallery', 'work', 'projects'])) {
            return 'portfolio';
        }
        if (Str::contains($lower, ['book', 'appointment', 'reservation', 'schedule'])) {
            return 'booking';
        }
        return 'business';
    }

    private function detectBusinessType(string $lower): ?string
    {
        $map = [
            'restaurant' => 'restaurant',
            'cafe' => 'cafe',
            'salon' => 'beauty',
            'beauty' => 'beauty',
            'clinic' => 'clinic',
            'dentist' => 'clinic',
            'lawyer' => 'legal',
            'legal' => 'legal',
            'electronics' => 'electronics',
            'fashion' => 'fashion',
        ];
        foreach ($map as $keyword => $type) {
            if (Str::contains($lower, $keyword)) {
                return $type;
            }
        }
        return null;
    }

    private function extractBrandName(string $prompt): ?string
    {
        $trimmed = trim($prompt);
        if (preg_match('/\b(called|named|brand|company)\s+["\']?([A-Za-z0-9\s]+)["\']?/i', $trimmed, $m)) {
            return trim(Str::limit($m[2], 60));
        }
        if (preg_match('/^([A-Z][a-z]+(?:\s+[A-Z][a-z]+)*)\s+(?:website|site|store)/i', $trimmed, $m)) {
            return trim(Str::limit($m[1], 60));
        }
        return null;
    }

    private function placeholderBrandName(string $websiteType): string
    {
        return match ($websiteType) {
            'ecommerce' => 'My Shop',
            'portfolio' => 'My Portfolio',
            'booking' => 'Bookings',
            default => 'My Website',
        };
    }

    private function fallbackBrandNameFromPrompt(string $prompt, string $websiteType): string
    {
        $normalized = preg_replace('/[^\p{L}\p{N}\s\-]+/u', ' ', trim($prompt));
        $normalized = is_string($normalized) ? preg_replace('/\s+/u', ' ', $normalized) : null;
        $normalized = is_string($normalized) ? trim($normalized) : '';

        if ($normalized !== '') {
            $stopWords = [
                'build', 'create', 'website', 'site', 'for', 'with', 'and', 'the', 'a', 'an',
                'make', 'modern', 'minimal', 'luxury', 'playful', 'corporate', 'business',
                'store', 'shop', 'portfolio', 'booking', 'online',
            ];
            $tokens = preg_split('/\s+/u', $normalized) ?: [];
            $filtered = array_values(array_filter($tokens, static function ($token) use ($stopWords): bool {
                $value = Str::lower(trim((string) $token));

                return $value !== '' && ! in_array($value, $stopWords, true);
            }));
            $candidate = trim(implode(' ', array_slice($filtered, 0, 4)));
            if ($candidate !== '') {
                return Str::title(Str::limit($candidate, 60, ''));
            }
        }

        return $this->placeholderBrandName($websiteType);
    }

    private function detectTone(string $lower): string
    {
        foreach (self::TONE_MAP as $keyword => $tone) {
            if (Str::contains($lower, $keyword)) {
                return $tone;
            }
        }
        return 'professional';
    }

    private function detectStyle(string $lower): string
    {
        foreach (self::STYLES as $style) {
            if (Str::contains($lower, $style)) {
                return $style;
            }
        }
        return 'modern';
    }

    private function detectLanguage(string $lower): string
    {
        if (Str::contains($lower, ['english', 'en only', 'in english'])) {
            return 'en';
        }
        if (Str::contains($lower, ['both', 'bilingual', 'ka and en', 'georgian and english'])) {
            return 'both';
        }
        return 'ka';
    }

    /** @return array<int, string> */
    private function defaultPagesForType(string $websiteType): array
    {
        return match ($websiteType) {
            'ecommerce' => ['home', 'shop', 'product', 'cart', 'checkout', 'contact'],
            'portfolio' => ['home', 'work', 'about', 'contact'],
            'booking' => ['home', 'services', 'book', 'contact'],
            default => ['home', 'about', 'services', 'contact'],
        };
    }

    private function primaryGoalForType(string $websiteType): ?string
    {
        return match ($websiteType) {
            'ecommerce' => 'sell_products',
            'booking' => 'book_services',
            'portfolio' => 'showcase_work',
            default => 'inform',
        };
    }

    private function defaultCtaForType(string $websiteType): ?string
    {
        return match ($websiteType) {
            'ecommerce' => 'Shop now',
            'booking' => 'Book now',
            'portfolio' => 'View work',
            default => 'Contact us',
        };
    }

    /**
     * Category key for template matrix (websiteType + businessType).
     */
    private function categoryForMatrix(string $websiteType, ?string $businessType): string
    {
        if ($businessType !== null && $businessType !== '') {
            return $businessType;
        }
        if ($websiteType === 'ecommerce' || $websiteType === 'portfolio' || $websiteType === 'booking') {
            return 'general';
        }

        return 'general';
    }
}
