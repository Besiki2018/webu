<?php

namespace App\Services;

use App\Models\AiProvider;
use App\Models\Plan;
use App\Models\SystemSetting;
use App\Models\Template;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TemplateClassifierService
{
    public const AI_FALLBACK_PROVIDER_NOT_CONFIGURED = 'ai_provider_not_configured';

    public const AI_FALLBACK_PROVIDER_INACTIVE = 'ai_provider_inactive';

    public const AI_FALLBACK_REQUEST_FAILED = 'ai_request_failed';

    public const AI_FALLBACK_RESPONSE_UNRECOGNIZED = 'ai_response_unrecognized';

    public const AI_FALLBACK_NO_KEYWORD_MATCH = 'no_keyword_match_default';

    /**
     * Valid template categories.
     */
    protected const VALID_CATEGORIES = [
        'booking' => 'Booking website template for appointments, services, staff scheduling, and reservation flow',
        'vet' => 'Veterinary clinic template with services, doctors, pet care content, and appointment booking',
        'grooming' => 'Pet grooming salon template with service catalog, gallery, and booking funnel',
        'medical' => 'Medical clinic template with departments, doctors, consultation booking, and trust sections',
        'restaurant' => 'Restaurant template with menu, online ordering, reservations, and delivery call-to-actions',
        'real_estate' => 'Real estate template with property listings, search filters, map integration, and property detail pages',
        'hotel' => 'Hotel template with room catalog, room details, availability checks, and reservation flow',
        'construction' => 'Construction company template with project showcase, services, and lead capture',
        'business' => 'Modern service-business template for consulting, agency, corporate, and professional services websites',
        'legal' => 'Legal services template for law firms, practice areas, consultation requests, and attorney profiles',
        'ecommerce' => 'E-commerce store template for online shops, product catalogs, shopping carts, checkout flows, order management',
        'dashboard' => 'Admin dashboard template for analytics, metrics, data visualization, management panels, admin interfaces',
        'cms' => 'Blog/CMS template for content management, blog posts, articles, publishing, news sites',
        'landing' => 'Landing page template for marketing, startup pages, SaaS products, promotional sites, waitlist pages',
        'portfolio' => 'Portfolio template for showcasing work, projects, galleries, personal sites, resumes',
        'default' => 'General purpose template for websites that don\'t fit other categories',
    ];

    /**
     * Keyword mappings for fallback classification.
     */
    protected const KEYWORD_MAPPINGS_EN = [
        // Keep order from more specific verticals to generic templates.
        'booking' => ['booking', 'appointment', 'appointments', 'calendar', 'reservation', 'service booking', 'schedule'],
        'vet' => ['vet', 'veterinary', 'pet clinic', 'animal clinic', 'pet hospital'],
        'grooming' => ['grooming', 'pet grooming', 'dog grooming', 'pet salon'],
        'medical' => ['medical clinic', 'clinic', 'doctor', 'hospital', 'consultation', 'appointment'],
        'restaurant' => ['restaurant', 'menu', 'food', 'table reservation', 'online order', 'delivery'],
        'real_estate' => ['real estate', 'property listing', 'property', 'realtor', 'apartment', 'villa', 'broker'],
        'hotel' => ['hotel', 'rooms', 'room booking', 'resort', 'hospitality', 'guest house', 'reservation'],
        'construction' => ['construction', 'builder', 'contractor', 'renovation', 'architecture'],
        'business' => ['corporate website', 'consulting company', 'consulting agency', 'professional services', 'service company', 'b2b services', 'yoga studio', 'pilates studio', 'fitness studio', 'wellness studio', 'gym website', 'personal trainer'],
        'legal' => ['legal', 'law firm', 'lawyer', 'attorney', 'legal services', 'consultation'],
        'landing' => ['landing', 'marketing', 'startup', 'saas', 'agency', 'launch', 'waitlist', 'promotional'],
        'portfolio' => ['portfolio', 'showcase', 'gallery', 'resume', 'cv', 'personal'],
        'cms' => ['blog', 'posts', 'articles', 'content', 'publish', 'editor', 'news', 'magazine', 'cms'],
        'dashboard' => ['dashboard', 'admin', 'analytics', 'metrics', 'stats', 'reports', 'monitoring', 'panel'],
        'ecommerce' => ['shop', 'store', 'cart', 'checkout', 'buy', 'sell', 'payment', 'order', 'inventory', 'e-commerce', 'ecommerce'],
    ];

    /**
     * Keyword mappings for Georgian locale fallback classification.
     */
    protected const KEYWORD_MAPPINGS_KA = [
        'booking' => ['ჯავშ', 'დაჯავშნ', 'კალენდარ', 'სლოტ', 'ჩანაწერ', 'აპოინთმენტ'],
        'vet' => ['ვეტ', 'ვეტერინარ', 'ცხოველების კლინიკა', 'პეტ კლინიკა'],
        'grooming' => ['გრუმინგ', 'ცხოველის მოვლა', 'პეტ სალონი'],
        'medical' => ['კლინიკა', 'მედიცინ', 'ექიმ', 'ჰოსპიტალ'],
        'restaurant' => ['რესტორან', 'მენიუ', 'საკვებ', 'დაჯავშნა', 'მიწოდება'],
        'real_estate' => ['უძრავი ქონებ', 'ქონებ', 'ბინ', 'რეალტორ', 'ქირავ', 'გაყიდვ'],
        'hotel' => ['სასტუმრ', 'ოთახ', 'რეზორტ', 'ჯავშნ', 'დაჯავშნ'],
        'construction' => ['სამშენებლო', 'მშენებლობ', 'რემონტ', 'არქიტექტურ'],
        'business' => ['კორპორატიული საიტი', 'კონსალტინგ კომპანია', 'სერვისული კომპანია', 'კომპანიის პრეზენტაცია', 'იოგას სტუდი', 'პილატეს სტუდი', 'ფიტნეს სტუდი', 'ველნეს სტუდი', 'პერსონალური ტრენერი'],
        'legal' => ['იურიდიულ', 'ადვოკატ', 'სამართ', 'იურისტ'],
        'landing' => ['ლენდინგ', 'სარეკლამო', 'სტარტაპ', 'საა', 'საას'],
        'portfolio' => ['პორტფოლიო', 'ნამუშევრ', 'გალერეა', 'რეზიუმე'],
        'cms' => ['ბლოგ', 'სტატი', 'კონტენტ', 'სიახლე', 'cms'],
        'dashboard' => ['დაშბორდ', 'ადმინ პანელ', 'ანალიტიკ', 'რეპორტ'],
        'ecommerce' => ['ონლაინ მაღაზი', 'ელ კომერცი', 'შეკვეთ', 'კალათა', 'გადახდ'],
    ];

    /**
     * Category-specific preferred template slugs (ordered by priority).
     *
     * @var array<string, array<int, string>>
     */
    protected const CATEGORY_PREFERRED_SLUGS = [
        'ecommerce' => ['ecommerce'],
        'business' => ['business-starter', 'default'],
        'booking' => ['booking-starter', 'default'],
    ];

    /**
     * Classify user goal using AI to determine best template.
     */
    public function classify(string $goal): ?string
    {
        return $this->classifyDetailed($goal)['category'] ?? null;
    }

    /**
     * Classify goal with locale-aware fallback metadata.
     *
     * @return array{
     *   category: string,
     *   confidence: float,
     *   fallback_reason: string|null,
     *   strategy: string,
     *   locale: string
     * }
     */
    public function classifyDetailed(string $goal, ?string $locale = null): array
    {
        $resolvedLocale = $this->resolveLocale($goal, $locale);

        $ai = $this->classifyWithAiDetailed($goal, $resolvedLocale);
        if ($ai['result'] !== null) {
            return $ai['result'];
        }

        return $this->keywordFallbackDetailed($goal, $resolvedLocale, $ai['failure_reason']);
    }

    /**
     * Classify using AI provider.
     */
    protected function classifyWithAiDetailed(string $goal, string $locale): array
    {
        $provider = $this->getProvider();
        if (! $provider) {
            return [
                'result' => null,
                'failure_reason' => self::AI_FALLBACK_PROVIDER_NOT_CONFIGURED,
            ];
        }

        if ($provider->status !== 'active') {
            return [
                'result' => null,
                'failure_reason' => self::AI_FALLBACK_PROVIDER_INACTIVE,
            ];
        }

        try {
            $prompt = $this->buildClassificationPrompt($goal, $locale);
            $model = $this->getModel($provider);

            $response = $this->callProvider($provider, $model, $prompt);

            if ($response !== null) {
                $result = $this->parseAiResponse($response, $locale);
                if ($result !== null) {
                    Log::info('Template classified by AI', [
                        'goal' => substr($goal, 0, 100),
                        'category' => $result['category'],
                        'confidence' => $result['confidence'],
                        'locale' => $locale,
                    ]);

                    return [
                        'result' => $result,
                        'failure_reason' => null,
                    ];
                }
            }
        } catch (\Exception $e) {
            Log::warning('AI template classification failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'result' => null,
                'failure_reason' => self::AI_FALLBACK_REQUEST_FAILED,
            ];
        }

        return [
            'result' => null,
            'failure_reason' => self::AI_FALLBACK_RESPONSE_UNRECOGNIZED,
        ];
    }

    /**
     * Build the classification prompt.
     */
    protected function buildClassificationPrompt(string $goal, string $locale): string
    {
        $templateList = collect(self::VALID_CATEGORIES)
            ->map(fn ($desc, $key) => "- {$key}: {$desc}")
            ->implode("\n");

        return <<<PROMPT
You are a template classifier for an AI website builder.
Given a user's project goal, determine the best template category.

Available templates:
{$templateList}

Locale hint: "{$locale}".
User's goal: "{$goal}"

Respond with STRICT JSON only:
{"category":"<template-category>","confidence":0.0}

Rules:
- category must be one of: booking, vet, grooming, medical, restaurant, real_estate, hotel, construction, business, legal, ecommerce, dashboard, cms, landing, portfolio, default
- confidence must be a float between 0 and 1
- no markdown, no extra keys, no explanation
PROMPT;
    }

    /**
     * Parse AI response into normalized classifier payload.
     *
     * @return array{
     *   category: string,
     *   confidence: float,
     *   fallback_reason: string|null,
     *   strategy: string,
     *   locale: string
     * }|null
     */
    protected function parseAiResponse(string $response, string $locale): ?array
    {
        $clean = trim($response);

        // Remove markdown wrappers if present.
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/i', $clean, $matches)) {
            $clean = trim($matches[1]);
        }

        $decoded = json_decode($clean, true);
        if (is_array($decoded)) {
            $category = strtolower(trim((string) ($decoded['category'] ?? '')));
            $confidence = $this->normalizeConfidence($decoded['confidence'] ?? null, 0.92);

            if (array_key_exists($category, self::VALID_CATEGORIES)) {
                return [
                    'category' => $category,
                    'confidence' => $confidence,
                    'fallback_reason' => null,
                    'strategy' => 'ai',
                    'locale' => $locale,
                ];
            }
        }

        // Fallback to legacy single-word response.
        $single = strtolower(trim($clean, "\"' \n\r\t"));
        if (array_key_exists($single, self::VALID_CATEGORIES)) {
            return [
                'category' => $single,
                'confidence' => 0.9,
                'fallback_reason' => null,
                'strategy' => 'ai',
                'locale' => $locale,
            ];
        }

        return null;
    }

    /**
     * Fallback keyword matching if AI is unavailable.
     */
    public function keywordFallback(string $goal): ?string
    {
        return $this->keywordFallbackDetailed($goal, $this->resolveLocale($goal), null)['category'] ?? null;
    }

    /**
     * Keyword fallback with locale and confidence metadata.
     *
     * @return array{
     *   category: string,
     *   confidence: float,
     *   fallback_reason: string|null,
     *   strategy: string,
     *   locale: string
     * }
     */
    public function keywordFallbackDetailed(string $goal, string $locale, ?string $fallbackReason): array
    {
        $normalizedGoal = mb_strtolower($goal);
        [$primaryMappings, $secondaryMappings] = $this->keywordMappingsByLocale($locale);

        $primaryMatch = $this->matchKeywordMappings($normalizedGoal, $primaryMappings);
        if ($primaryMatch !== null) {
            return $this->buildKeywordResult($primaryMatch, $locale, $fallbackReason);
        }

        $secondaryMatch = $this->matchKeywordMappings($normalizedGoal, $secondaryMappings);
        if ($secondaryMatch !== null) {
            return $this->buildKeywordResult($secondaryMatch, $locale, $fallbackReason);
        }

        $reason = $fallbackReason ?: self::AI_FALLBACK_NO_KEYWORD_MATCH;

        Log::debug('Template classifier default fallback', [
            'goal' => substr($goal, 0, 100),
            'locale' => $locale,
            'fallback_reason' => $reason,
        ]);

        return [
            'category' => 'default',
            'confidence' => 0.2,
            'fallback_reason' => $reason,
            'strategy' => 'default',
            'locale' => $locale,
        ];
    }

    /**
     * Get template by category, filtered by plan.
     */
    public function getTemplateByCategory(string $category, ?Plan $plan = null): ?Template
    {
        return $this->findPreferredTemplateByCategory($category, $plan, false);
    }

    /**
     * Find best template for category with preferred slug ordering.
     */
    public function findPreferredTemplateByCategory(string $category, ?Plan $plan = null, bool $adminBypass = false): ?Template
    {
        $query = $adminBypass
            ? Template::query()
            : Template::forPlan($plan);

        $query->where('category', $category);
        $this->applyPreferredSlugOrdering($query, $category);

        return $query->orderBy('id')->first();
    }

    /**
     * @return array<int, string>
     */
    public function preferredSlugsForCategory(string $category): array
    {
        $normalized = trim(strtolower($category));

        return self::CATEGORY_PREFERRED_SLUGS[$normalized] ?? [];
    }

    private function applyPreferredSlugOrdering($query, string $category): void
    {
        $preferredSlugs = $this->preferredSlugsForCategory($category);
        if ($preferredSlugs === []) {
            return;
        }

        $cases = [];
        $bindings = [];
        foreach (array_values($preferredSlugs) as $index => $slug) {
            $cases[] = "WHEN slug = ? THEN {$index}";
            $bindings[] = $slug;
        }

        $query->orderByRaw(
            'CASE '.implode(' ', $cases).' ELSE '.count($preferredSlugs).' END',
            $bindings
        );
    }

    /**
     * Get the configured AI provider.
     */
    protected function getProvider(): ?AiProvider
    {
        $providerId = SystemSetting::get('internal_ai_provider_id');

        if (! $providerId) {
            return null;
        }

        $provider = AiProvider::find($providerId);

        if (! $provider) {
            return null;
        }

        return $provider;
    }

    /**
     * Get the model to use for classification.
     */
    protected function getModel(AiProvider $provider): string
    {
        $customModel = SystemSetting::get('internal_ai_model');

        if (! empty($customModel)) {
            return $customModel;
        }

        return $provider->getDefaultModel();
    }

    /**
     * Call the appropriate AI provider API.
     */
    protected function callProvider(AiProvider $provider, string $model, string $prompt): ?string
    {
        return match ($provider->type) {
            AiProvider::TYPE_OPENAI,
            AiProvider::TYPE_GROK,
            AiProvider::TYPE_DEEPSEEK => $this->callOpenAiCompatible($provider, $model, $prompt),

            AiProvider::TYPE_ANTHROPIC,
            AiProvider::TYPE_CLAUDE,
            AiProvider::TYPE_ZHIPU => $this->callAnthropic($provider, $model, $prompt),

            default => null,
        };
    }

    /**
     * Call OpenAI-compatible API (OpenAI, Grok, DeepSeek).
     */
    protected function callOpenAiCompatible(AiProvider $provider, string $model, string $prompt): ?string
    {
        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ];
        $tokenLimitParameter = AiProvider::resolveTokenLimitParameter($provider->type, $model);
        $payload[$tokenLimitParameter] = 120;

        $response = Http::withHeaders([
            'Authorization' => 'Bearer '.$provider->getApiKey(),
            'Content-Type' => 'application/json',
        ])->timeout(10)->post($provider->getBaseUrl().'/chat/completions', $payload);

        if (! $response->successful()) {
            Log::warning('OpenAI-compatible template classification failed', [
                'provider' => $provider->type,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->json('choices.0.message.content');
    }

    /**
     * Call Anthropic-compatible API (Anthropic, ZhipuAI).
     */
    protected function callAnthropic(AiProvider $provider, string $model, string $prompt): ?string
    {
        $baseUrl = $provider->getBaseUrl();
        if (! str_ends_with($baseUrl, '/v1')) {
            $baseUrl .= '/v1';
        }

        $response = Http::withHeaders([
            'x-api-key' => $provider->getApiKey(),
            'anthropic-version' => '2023-06-01',
            'Content-Type' => 'application/json',
        ])->timeout(10)->post($baseUrl.'/messages', [
            'model' => $model,
            'max_tokens' => 120,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
        ]);

        if (! $response->successful()) {
            Log::warning('Anthropic-compatible template classification failed', [
                'provider' => $provider->type,
                'status' => $response->status(),
            ]);

            return null;
        }

        return $response->json('content.0.text');
    }

    /**
     * @return array{0: array<string, array<int, string>>, 1: array<string, array<int, string>>}
     */
    private function keywordMappingsByLocale(string $locale): array
    {
        if ($locale === 'ka') {
            return [self::KEYWORD_MAPPINGS_KA, self::KEYWORD_MAPPINGS_EN];
        }

        return [self::KEYWORD_MAPPINGS_EN, self::KEYWORD_MAPPINGS_KA];
    }

    /**
     * @param  array<string, array<int, string>>  $mappings
     * @return array{category: string, keyword: string, score_hits: int}|null
     */
    private function matchKeywordMappings(string $goal, array $mappings): ?array
    {
        $bestMatch = null;

        foreach ($mappings as $category => $keywords) {
            $matches = 0;
            $firstKeyword = null;

            foreach ($keywords as $keyword) {
                if (str_contains($goal, mb_strtolower($keyword))) {
                    $matches++;
                    $firstKeyword ??= $keyword;
                }
            }

            if ($matches > 0) {
                $candidate = [
                    'category' => $category,
                    'keyword' => (string) $firstKeyword,
                    'score_hits' => $matches,
                ];

                if ($bestMatch === null || $matches > $bestMatch['score_hits']) {
                    $bestMatch = $candidate;
                }
            }
        }

        return $bestMatch;
    }

    /**
     * @param  array{category: string, keyword: string, score_hits: int}  $match
     * @return array{
     *   category: string,
     *   confidence: float,
     *   fallback_reason: string|null,
     *   strategy: string,
     *   locale: string
     * }
     */
    private function buildKeywordResult(array $match, string $locale, ?string $fallbackReason): array
    {
        $confidence = min(0.89, 0.58 + (($match['score_hits'] - 1) * 0.08));
        $reason = $fallbackReason ?? 'keyword_match';

        Log::debug('Template classified by keyword fallback', [
            'category' => $match['category'],
            'matched_keyword' => $match['keyword'],
            'matched_hits' => $match['score_hits'],
            'locale' => $locale,
            'fallback_reason' => $reason,
        ]);

        return [
            'category' => $match['category'],
            'confidence' => round($confidence, 2),
            'fallback_reason' => $reason,
            'strategy' => 'keyword',
            'locale' => $locale,
        ];
    }

    private function resolveLocale(string $goal, ?string $locale = null): string
    {
        $normalized = strtolower(substr((string) $locale, 0, 2));
        if (in_array($normalized, ['ka', 'en'], true)) {
            return $normalized;
        }

        return preg_match('/\p{Georgian}/u', $goal) === 1 ? 'ka' : 'en';
    }

    private function normalizeConfidence(mixed $value, float $default): float
    {
        $numeric = is_numeric($value) ? (float) $value : $default;
        if ($numeric < 0) {
            $numeric = 0;
        } elseif ($numeric > 1) {
            $numeric = 1;
        }

        return round($numeric, 2);
    }
}
