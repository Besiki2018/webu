<?php

namespace App\Services\AiTools;

use App\Models\Project;
use App\Services\InternalAiService;
use App\Services\WebuCodex\CodebaseScanner;
use Illuminate\Support\Facades\Log;

/**
 * Generates a structured website plan from a user prompt.
 * Uses codebase scanner for context and existing AI providers (OpenAI, Claude).
 * Does not execute file changes; output is consumed by the tools/execution pipeline.
 */
class SitePlannerService
{
    /** Default section names when project has none (fallback plan). */
    private const DEFAULT_SECTIONS = [
        'HeroSection',
        'FeaturesSection',
        'CTASection',
        'FormWrapperSection',
    ];

    public function __construct(
        protected CodebaseScanner $scanner,
        protected InternalAiService $ai,
        protected DesignRulesService $designRules,
        protected SectionNameNormalizer $sectionNameNormalizer
    ) {}

    /**
     * Generate a site plan from user prompt. Runs codebase scanner first, then AI.
     *
     * @param  array<int, string>|null  $designPatternHints  Optional hints from design memory (e.g. preferred section order for website type)
     * @return array{success: bool, plan: array{siteName: string, pages: array}, from_fallback?: bool, error?: string}
     */
    public function generate(Project $project, string $userPrompt, ?array $preScanned = null, ?array $designPatternHints = null): array
    {
        $userPrompt = trim($userPrompt);
        if ($userPrompt === '') {
            return [
                'success' => true,
                'plan' => $this->getFallbackPlan(self::DEFAULT_SECTIONS),
                'from_fallback' => true,
            ];
        }

        $scan = $preScanned;
        try {
            if ($scan === null) {
                $scan = $this->scanner->getScanFromIndex($project);
                if ($scan === null) {
                    $scan = $this->scanner->scan($project);
                    $this->scanner->writeIndex($project, $scan);
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Site planner: scan failed', ['project_id' => $project->id, 'error' => $e->getMessage()]);
        }

        $availableSections = $this->getAvailableSectionNames($scan ?? []);
        if ($availableSections === []) {
            $availableSections = self::DEFAULT_SECTIONS;
        }

        if (! $this->ai->isConfigured()) {
            return [
                'success' => true,
                'plan' => $this->getFallbackPlan($availableSections),
                'from_fallback' => true,
            ];
        }

        $prompt = $this->buildPrompt($userPrompt, $availableSections, $designPatternHints ?? []);
        $response = $this->ai->completeForProjectEdit($prompt, 4000);

        if ($response === null || trim($response) === '') {
            return [
                'success' => true,
                'plan' => $this->getFallbackPlan($availableSections),
                'from_fallback' => true,
            ];
        }

        $parsed = $this->parsePlanResponse($response, $availableSections);
        if ($parsed !== null) {
            return [
                'success' => true,
                'plan' => $parsed,
                'from_fallback' => false,
            ];
        }

        return [
            'success' => true,
            'plan' => $this->getFallbackPlan($availableSections),
            'from_fallback' => true,
        ];
    }

    /**
     * @param  array{pages?: array, sections?: array}  $scan
     * @return array<int, string>
     */
    private function getAvailableSectionNames(array $scan): array
    {
        $names = [];
        foreach ($scan['sections'] ?? [] as $path) {
            $base = basename($path, '.tsx');
            if ($base !== '' && ! in_array($base, $names, true)) {
                $names[] = $base;
            }
        }
        sort($names);

        return array_values($names);
    }

    /**
     * @param  array<int, string>  $availableSections
     * @param  array<int, string>  $designPatternHints
     */
    private function buildPrompt(string $userPrompt, array $availableSections, array $designPatternHints = []): string
    {
        $designRulesFragment = $this->designRules->getPromptFragment();
        $sectionsLine = implode(', ', $availableSections);
        $memoryHintsBlock = $designPatternHints !== []
            ? "\n\nDesign memory hints (prefer these patterns when they fit the request):\n".implode("\n", array_map(static fn (string $h): string => '- '.$h, $designPatternHints))
            : '';

        return $designRulesFragment.<<<PROMPT

---

You are a website structure planner. The user will describe the kind of website they want (e.g. "Create website for restaurant", "SaaS landing page", "Portfolio"). You must return ONLY a JSON object with this exact shape. Reuse existing section components whenever they already fit. When a required section is missing, return the canonical PascalCase component name ending in "Section" so Webu can generate it later. Plan section order according to the Webu Design System section composition rules above.
{$memoryHintsBlock}

Existing section components already available in this project:
{$sectionsLine}

Canonical naming rules for missing sections:
- Use PascalCase names ending with Section.
- Examples: PricingSection, TestimonialsSection, TeamSection, ServicesSection, GallerySection, StorySection, MenuSection.
- Reuse an existing component only when it is actually the same or clearly equivalent.
- Do not invent filenames outside src/sections.

Reuse hints when an equivalent component already exists:
- Hero -> HeroSection
- Services -> ServicesSection or FeaturesSection
- Social proof / reviews -> TestimonialsSection
- Contact form -> FormWrapperSection
- Gallery / portfolio -> GallerySection or ImageSection
- About story -> StorySection or TextSection

Layout rules:
- Landing pages: Hero, Features, Social Proof (Testimonials/Features), CTA.
- Business sites: home, about, services or products, gallery or portfolio, contact.
- Order sections logically: Hero first, CTA near end, contact form on contact page.

User request: {$userPrompt}

Return ONLY this JSON (no markdown, no code fence):
{"siteName":"Restaurant Website","pages":[{"name":"home","title":"Home","sections":["HeroSection","FeaturesSection","TestimonialsSection","CTASection"]},{"name":"about","title":"About","sections":["StorySection","TeamSection"]},{"name":"menu","title":"Menu","sections":["HeroSection","MenuSection","CTASection"]},{"name":"contact","title":"Contact","sections":["FormWrapperSection","CTASection"]}]}

Rules: "siteName" = short title for the site. "name" = URL slug (lowercase, no spaces). Include at least "home". Every entry in "sections" must be a PascalCase section component name ending with "Section". Prefer existing section names from the project list above whenever possible. Order sections logically.
PROMPT;
    }

    /**
     * @param  array<int, string>  $availableSections
     * @return array{siteName: string, pages: array<int, array{name: string, title: string, sections: array<int, string>, section_intents: array<int, array{requested: string, section: string, exists: bool}>}>}|null
     */
    private function parsePlanResponse(string $response, array $availableSections): ?array
    {
        $json = $this->extractJson($response);
        if (! is_array($json) || empty($json['pages']) || ! is_array($json['pages'])) {
            return null;
        }

        $siteName = isset($json['siteName']) && is_string($json['siteName'])
            ? trim($json['siteName'])
            : 'Website';
        if ($siteName === '') {
            $siteName = 'Website';
        }

        $pages = [];
        foreach ($json['pages'] as $p) {
            $name = isset($p['name']) ? preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $p['name'])) : '';
            if ($name === '') {
                continue;
            }
            $sections = [];
            $sectionIntents = [];
            foreach ($p['sections'] ?? [] as $s) {
                $intent = $this->resolveSectionIntent(trim((string) $s), $availableSections);
                if ($intent === null) {
                    continue;
                }

                $sectionIntents[] = $intent;
                if (! in_array($intent['section'], $sections, true)) {
                    $sections[] = $intent['section'];
                }
            }
            if ($sections === []) {
                $fallbackSection = $availableSections[0] ?? self::DEFAULT_SECTIONS[0];
                $sections[] = $fallbackSection;
                $sectionIntents[] = [
                    'requested' => $fallbackSection,
                    'section' => $fallbackSection,
                    'exists' => in_array($fallbackSection, $availableSections, true),
                ];
            }
            $pages[] = [
                'name' => $name,
                'title' => isset($p['title']) && is_string($p['title']) ? trim($p['title']) : ucfirst($name),
                'sections' => $sections,
                'section_intents' => $sectionIntents,
            ];
        }

        if ($pages === []) {
            return null;
        }

        return [
            'siteName' => $siteName,
            'pages' => $pages,
        ];
    }

    /**
     * @param  array<int, string>  $available
     */
    private function resolveSectionIntent(string $planned, array $available): ?array
    {
        $requested = $this->sectionNameNormalizer->normalize($planned);
        if ($requested === '') {
            return null;
        }

        if (in_array($requested, $available, true)) {
            return [
                'requested' => $requested,
                'section' => $requested,
                'exists' => true,
            ];
        }

        $similar = $this->findSimilarAvailableSection($requested, $available);
        if ($similar !== null) {
            return [
                'requested' => $requested,
                'section' => $similar,
                'exists' => true,
            ];
        }

        return [
            'requested' => $requested,
            'section' => $requested,
            'exists' => false,
        ];
    }

    /**
     * @param  array<int, string>  $available
     */
    private function findSimilarAvailableSection(string $requested, array $available): ?string
    {
        $group = $this->sectionGroup($requested);
        $candidateGroups = [
            'hero' => ['HeroSection'],
            'features' => ['FeaturesSection', 'ServicesSection'],
            'services' => ['ServicesSection', 'FeaturesSection'],
            'testimonials' => ['TestimonialsSection'],
            'socialproof' => ['TestimonialsSection'],
            'gallery' => ['GallerySection', 'ImageSection', 'ProductGallerySection'],
            'portfolio' => ['PortfolioSection', 'GallerySection', 'ImageSection', 'FeaturesSection'],
            'contact' => ['ContactSection', 'FormWrapperSection'],
            'story' => ['StorySection', 'TextSection'],
            'about' => ['StorySection', 'TextSection'],
            'menu' => ['MenuSection', 'ProductGridSection', 'FeaturesSection'],
            'newsletter' => ['NewsletterSection'],
        ];

        foreach ($candidateGroups[$group] ?? [] as $candidate) {
            if (in_array($candidate, $available, true)) {
                return $candidate;
            }
        }

        return null;
    }

    private function sectionGroup(string $sectionName): string
    {
        $normalized = strtolower(preg_replace('/section$/i', '', $sectionName) ?? $sectionName);
        $normalized = preg_replace('/[^a-z0-9]+/', '', $normalized) ?? $normalized;

        return match ($normalized) {
            'hero' => 'hero',
            'features', 'featuregrid' => 'features',
            'services', 'service' => 'services',
            'testimonials', 'reviews' => 'testimonials',
            'socialproof' => 'socialproof',
            'gallery' => 'gallery',
            'portfolio' => 'portfolio',
            'contact', 'contactform', 'formwrapper', 'form' => 'contact',
            'story' => 'story',
            'about' => 'about',
            'menu' => 'menu',
            'newsletter' => 'newsletter',
            default => $normalized,
        };
    }

    private function extractJson(string $text): ?array
    {
        $text = trim($text);
        if (preg_match('/```(?:json)?\s*([\s\S]*?)```/', $text, $m)) {
            $text = trim($m[1]);
        }
        if (preg_match('/\{[\s\S]*\}/', $text, $m)) {
            $text = $m[0];
        }
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Fallback plan when AI fails or is not configured.
     *
     * @param  array<int, string>  $availableSections
     * @return array{siteName: string, pages: array<int, array{name: string, title: string, sections: array<int, string>, section_intents: array<int, array{requested: string, section: string, exists: bool}>}>}
     */
    private function getFallbackPlan(array $availableSections): array
    {
        $homeIntents = array_values(array_filter(array_map(
            fn (string $section): ?array => $this->resolveSectionIntent($section, $availableSections),
            ['HeroSection', 'FeaturesSection', 'CTASection']
        )));
        $contactIntents = array_values(array_filter(array_map(
            fn (string $section): ?array => $this->resolveSectionIntent($section, $availableSections),
            ['HeroSection', 'FormWrapperSection', 'CTASection']
        )));
        if ($homeIntents === []) {
            $homeIntents = [[
                'requested' => self::DEFAULT_SECTIONS[0],
                'section' => self::DEFAULT_SECTIONS[0],
                'exists' => in_array(self::DEFAULT_SECTIONS[0], $availableSections, true),
            ]];
        }
        if ($contactIntents === []) {
            $contactIntents = [[
                'requested' => self::DEFAULT_SECTIONS[0],
                'section' => self::DEFAULT_SECTIONS[0],
                'exists' => in_array(self::DEFAULT_SECTIONS[0], $availableSections, true),
            ]];
        }

        return [
            'siteName' => 'Website',
            'pages' => [
                [
                    'name' => 'home',
                    'title' => 'Home',
                    'sections' => array_values(array_map(static fn (array $intent): string => $intent['section'], $homeIntents)),
                    'section_intents' => $homeIntents,
                ],
                [
                    'name' => 'contact',
                    'title' => 'Contact',
                    'sections' => array_values(array_map(static fn (array $intent): string => $intent['section'], $contactIntents)),
                    'section_intents' => $contactIntents,
                ],
            ],
        ];
    }
}
