<?php

namespace App\Services\UnifiedAgent;

use Illuminate\Support\Str;

/**
 * Georgian-first command normalizer and domain lexicon.
 * Handles colloquial Georgian, typos, mixed Georgian-English, builder/design/ecommerce terms.
 * Preserves requested Georgian copy exactly.
 */
class GeorgianCommandNormalizer
{
    /** @var array<string, string> Typos and colloquial variants → canonical form */
    private const TYPO_MAP = [
        'ქარტულად' => 'ქართულად',
        'ქარტული' => 'ქართული',
        'დიზიანი' => 'დიზაინი',
        'დიზიან' => 'დიზაინ',
        'ერტი' => 'ერთი',
        'ჰედერში' => 'ჰედერში',
        'ფუტერში' => 'ფუტერში',
        'მაღაზიის' => 'მაღაზიის',
        'გვერდზე' => 'გვერდზე',
        'გადააგდე' => 'გადააგდე',
        'ჩაასწორე' => 'ჩაასწორე',
        'დატოვე' => 'დატოვე',
        'დამიწერე' => 'დამიწერე',
        'ჩამიწერე' => 'ჩამიწერე',
        'დააწერე' => 'დააწერე',
        'ჩაწერე' => 'ჩაწერე',
        'გადამიტანე' => 'გადამიტანე',
        'ეს' => 'ეს',
    ];

    /** @var array<string, string> Georgian builder/design terms → English equivalents for AI context */
    private const BUILDER_LEXICON = [
        'ჰედერ' => 'header',
        'ჰედერში' => 'header',
        'ფუტერ' => 'footer',
        'ფუტერში' => 'footer',
        'დიზაინ' => 'design',
        'დიზიან' => 'design',
        'სტილი' => 'style',
        'განლაგება' => 'layout',
        'განლაგ' => 'layout',
        'სექცია' => 'section',
        'სექცი' => 'section',
        'ღილაკი' => 'button',
        'ღილაკ' => 'button',
        'სათაური' => 'title',
        'სათაურ' => 'title',
        'ტექსტი' => 'text',
        'ტექსტ' => 'text',
        'მენიუ' => 'menu',
        'ლოგო' => 'logo',
        'მაღაზია' => 'shop',
        'მაღაზიის' => 'shop',
        'გვერდი' => 'page',
        'გვერდზე' => 'page',
        'პროდუქტი' => 'product',
        'პროდუქტ' => 'product',
        'კალათა' => 'cart',
        'კალათ' => 'cart',
    ];

    /** @var array<string, string> Ecommerce terms */
    private const ECOMMERCE_LEXICON = [
        'ონლაინ მაღაზია' => 'online store',
        'მაღაზიის გვერდზე' => 'shop page',
        'პროდუქტების' => 'products',
        'კატეგორია' => 'category',
    ];

    /** @var array<string, string> Action verbs (Georgian → intent hint) */
    private const ACTION_VERBS = [
        'დამიწერე' => 'write',
        'ჩამიწერე' => 'write',
        'დააწერე' => 'write',
        'ჩაწერე' => 'write',
        'შეცვალე' => 'replace',
        'შემიცვალ' => 'replace',
        'გადამიტანე' => 'move',
        'გადააგდე' => 'move',
        'ჩაასწორე' => 'fix',
        'დატოვე' => 'keep',
        'წაშალე' => 'delete',
        'მოაშორე' => 'remove',
        'დაამატე' => 'add',
    ];

    public function __construct()
    {
    }

    /**
     * Normalize user prompt for interpretation. Does not alter requested copy.
     * Returns normalized prompt + resolved locale.
     *
     * @return array{normalized_prompt: string, locale: 'ka'|'en', is_georgian_primary: bool}
     */
    public function normalize(string $userPrompt): array
    {
        $trimmed = trim($userPrompt);
        if ($trimmed === '') {
            return [
                'normalized_prompt' => '',
                'locale' => 'ka',
                'is_georgian_primary' => true,
            ];
        }

        $locale = $this->resolveLocale($trimmed);
        $isGeorgianPrimary = $locale === 'ka';

        $normalized = $this->applyTypoCorrections($trimmed);
        $normalized = $this->expandDomainHints($normalized);

        return [
            'normalized_prompt' => $normalized,
            'locale' => $locale,
            'is_georgian_primary' => $isGeorgianPrimary,
        ];
    }

    /**
     * Resolve working locale from prompt (Georgian-first).
     */
    public function resolveLocale(string $prompt): string
    {
        if (preg_match('/[\x{10A0}-\x{10FF}]/u', $prompt) === 1) {
            return 'ka';
        }

        $lower = mb_strtolower(trim($prompt), 'UTF-8');
        $georgianHints = [
            'qartul', 'qartulad', 'qartuli', 'kartul', 'kartulad', 'kartuli',
            'gaakete', 'shecvale', 'gadaxede', 'magazia', 'heder', 'futer',
        ];
        foreach ($georgianHints as $hint) {
            if (str_contains($lower, $hint)) {
                return 'ka';
            }
        }

        if (preg_match('/[a-z]{3,}/i', $prompt) === 1) {
            return 'en';
        }

        return 'ka';
    }

    /**
     * Apply typo corrections for common Georgian misspellings.
     * Does not change user-requested visible copy (only command structure).
     */
    private function applyTypoCorrections(string $prompt): string
    {
        $result = $prompt;
        foreach (self::TYPO_MAP as $typo => $canonical) {
            $result = preg_replace('/\b'.preg_quote($typo, '/').'\b/u', $canonical, $result) ?? $result;
        }

        return $result;
    }

    /**
     * Expand domain hints for AI context (adds parenthetical hints, does not replace user copy).
     */
    private function expandDomainHints(string $prompt): string
    {
        return $prompt;
    }

    /**
     * Get builder/design lexicon for AI prompt context.
     *
     * @return array<string, string>
     */
    public function getBuilderLexicon(): array
    {
        return array_merge(self::BUILDER_LEXICON, self::ECOMMERCE_LEXICON);
    }

    /**
     * Get action verb mappings for intent resolution.
     *
     * @return array<string, string>
     */
    public function getActionVerbs(): array
    {
        return self::ACTION_VERBS;
    }

    /**
     * Check if prompt looks like a site creation request (Georgian or English).
     */
    public function looksLikeSiteCreation(string $prompt): bool
    {
        $lower = mb_strtolower(trim($prompt), 'UTF-8');
        $patterns = [
            'შემიქმენი',
            'შექმენი',
            'შექმენით',
            'შექმენი',
            'create.*(website|site)',
            'build.*(website|site)',
            'make.*(website|site)',
            'website for',
            'site for',
            'ონლაინ მაღაზია',
            'მაღაზია',
        ];
        foreach ($patterns as $p) {
            if (str_contains($lower, $p) || preg_match('/'.$p.'/u', $lower) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if prompt targets header/footer (site-wide).
     */
    public function targetsHeaderOrFooter(string $prompt): bool
    {
        $lower = mb_strtolower(trim($prompt), 'UTF-8');
        return str_contains($lower, 'ჰედერ')
            || str_contains($lower, 'header')
            || str_contains($lower, 'ფუტერ')
            || str_contains($lower, 'footer');
    }
}
