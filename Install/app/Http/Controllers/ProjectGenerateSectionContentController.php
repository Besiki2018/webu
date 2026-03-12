<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\InternalAiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Part 10 — Automatic Design Upgrade: generate section content (hero, features, cta)
 * for use when the user says "Improve hero" (replace variant + regenerate content + spacing).
 */
class ProjectGenerateSectionContentController extends Controller
{
    public function __construct(
        protected InternalAiService $ai
    ) {}

    /**
     * POST /panel/projects/{project}/generate-section-content
     * Body: { section_type: 'hero'|'features'|'cta', project_type?: 'landing', industry?: '', language?: 'en', tone?: '' }
     * Returns: { success: bool, content?: object, error?: string }
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $this->authorize('update', $project);

        $validated = $request->validate([
            'section_type' => ['required', 'string', 'in:hero,features,cta'],
            'project_type' => ['sometimes', 'string', 'max:64'],
            'industry' => ['sometimes', 'nullable', 'string', 'max:128'],
            'language' => ['sometimes', 'string', 'max:10'],
            'tone' => ['sometimes', 'nullable', 'string', 'max:64'],
            'brand_name' => ['sometimes', 'nullable', 'string', 'max:128'],
        ]);

        $sectionType = $validated['section_type'];
        $projectType = $validated['project_type'] ?? 'landing';
        $industry = $validated['industry'] ?? null;
        $language = $validated['language'] ?? 'en';
        $tone = $validated['tone'] ?? null;
        $brandName = $validated['brand_name'] ?? null;

        if (! $this->ai->isConfigured()) {
            return response()->json([
                'success' => false,
                'error' => 'AI is not configured.',
            ], 400);
        }

        $prompt = $this->buildPrompt($sectionType, $projectType, $industry, $language, $tone, $brandName);
        $raw = $this->ai->complete($prompt, 2000);

        if ($raw === null || trim($raw) === '') {
            return response()->json([
                'success' => false,
                'error' => 'AI did not return content.',
            ], 502);
        }

        $content = $this->parseResponse($sectionType, $raw);
        if ($content === null) {
            return response()->json([
                'success' => false,
                'error' => 'Could not parse AI response as valid content.',
            ], 502);
        }

        return response()->json([
            'success' => true,
            'content' => $content,
        ]);
    }

    private function buildPrompt(string $sectionType, string $projectType, ?string $industry, string $language, ?string $tone, ?string $brandName): string
    {
        $langNote = $language !== 'en' ? " Write all content in language code \"{$language}\"." : '';
        $industryPhrase = $industry
            ? ' for a '.$industry.' '.($projectType === 'ecommerce' ? 'store' : ($projectType === 'restaurant' ? 'venue' : 'business'))
            : " for a {$projectType} project";
        $brandPhrase = $brandName ? " Brand name: {$brandName}." : '';
        $tonePhrase = $tone ? " Tone: {$tone}." : '';

        return match ($sectionType) {
            'hero' => "Generate hero section content{$industryPhrase}.{$brandPhrase}{$tonePhrase}{$langNote}\n\n"
                ."Return a JSON object only, no markdown or explanation, with these exact keys:\n"
                ."- \"title\" (string): main headline, compelling and concise\n"
                ."- \"subtitle\" (string): supporting line, one sentence\n"
                ."- \"cta\" (string): primary button text, 1-3 words\n"
                ."- \"eyebrow\" (string, optional): small label above the title\n"
                ."- \"ctaSecondary\" (string, optional): secondary button text if needed\n\n"
                .'Example: {"title": "Beautiful Furniture for Modern Living", "subtitle": "Discover handcrafted pieces designed for comfort and style.", "cta": "Shop Now"}',
            'features' => "Generate features section content{$industryPhrase}.{$brandPhrase}{$tonePhrase}{$langNote}\n\n"
                ."Return a JSON object only with:\n"
                ."- \"title\" (string): section heading\n"
                ."- \"items\" (array): 3-4 objects, each with \"title\" (string) and \"description\" (string, one sentence)",
            'cta' => "Generate CTA section content{$industryPhrase}.{$brandPhrase}{$tonePhrase}{$langNote}\n\n"
                ."Return a JSON object only with:\n"
                ."- \"title\" (string): main line\n"
                ."- \"subtitle\" (string, optional): supporting line\n"
                ."- \"buttonLabel\" (string): button text, 1-3 words",
            default => "Generate {$sectionType} section content{$industryPhrase}.{$langNote}\n\nReturn a JSON object only.",
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseResponse(string $sectionType, string $raw): ?array
    {
        $json = $this->extractJson($raw);
        if ($json === null) {
            return null;
        }
        $data = json_decode($json, true);
        if (! is_array($data)) {
            return null;
        }
        return match ($sectionType) {
            'hero' => [
                'title' => isset($data['title']) ? (string) $data['title'] : 'Headline',
                'subtitle' => isset($data['subtitle']) ? (string) $data['subtitle'] : '',
                'cta' => isset($data['cta']) ? (string) $data['cta'] : 'Learn more',
                'eyebrow' => isset($data['eyebrow']) ? (string) $data['eyebrow'] : null,
                'ctaSecondary' => isset($data['ctaSecondary']) ? (string) $data['ctaSecondary'] : null,
            ],
            'features' => [
                'title' => isset($data['title']) ? (string) $data['title'] : 'Features',
                'items' => $this->parseFeatureItems($data['items'] ?? []),
            ],
            'cta' => [
                'title' => isset($data['title']) ? (string) $data['title'] : 'Ready to get started?',
                'subtitle' => isset($data['subtitle']) ? (string) $data['subtitle'] : null,
                'buttonLabel' => isset($data['buttonLabel']) ? (string) $data['buttonLabel'] : 'Get started',
            ],
            default => $data,
        };
    }

    /**
     * @param  mixed  $items
     * @return array<int, array{title: string, description: string}>
     */
    private function parseFeatureItems($items): array
    {
        if (! is_array($items)) {
            return [['title' => 'Quality', 'description' => 'Built to last.'], ['title' => 'Design', 'description' => 'Thoughtfully crafted.'], ['title' => 'Support', 'description' => 'Here when you need us.']];
        }
        $out = [];
        foreach ($items as $x) {
            if (is_array($x)) {
                $out[] = [
                    'title' => isset($x['title']) ? (string) $x['title'] : 'Feature',
                    'description' => isset($x['description']) ? (string) $x['description'] : '',
                ];
            }
        }
        if ($out === []) {
            return [['title' => 'Quality', 'description' => 'Built to last.'], ['title' => 'Design', 'description' => 'Thoughtfully crafted.'], ['title' => 'Support', 'description' => 'Here when you need us.']];
        }
        return $out;
    }

    private function extractJson(string $raw): ?string
    {
        $trimmed = trim($raw);
        if (Str::startsWith($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/', '', $trimmed);
            $trimmed = preg_replace('/\s*```\s*$/', '', $trimmed);
        }
        $start = strpos($trimmed, '{');
        if ($start === false) {
            return null;
        }
        $depth = 0;
        $end = null;
        for ($i = $start; $i < strlen($trimmed); $i++) {
            $c = $trimmed[$i];
            if ($c === '{') {
                $depth++;
            } elseif ($c === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i + 1;
                    break;
                }
            }
        }
        if ($end === null) {
            return null;
        }
        return substr($trimmed, $start, $end - $start);
    }
}
