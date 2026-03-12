<?php

namespace App\Services\AiTools;

use App\Models\Project;
use App\Services\InternalAiService;
use App\Services\WebuCodex\CodebaseScanner;
use Illuminate\Support\Facades\Log;

/**
 * Generates new React section components when a required component does not exist.
 * Follows Webu Design Intelligence rules and integrates with Site Planner and Agent Tools.
 */
class ComponentGeneratorService
{
    /** Section types for content hints (testimonials, pricing, team, services, etc.). */
    private const CONTENT_HINTS = [
        'testimonials' => 'Customer quotes with author name and position; 3–4 testimonial cards.',
        'pricing' => '2–3 pricing tiers with title, price, feature list, and CTA button each.',
        'team' => 'Team member cards: name, role, optional short bio; use grid layout.',
        'services' => 'Service cards with icon placeholder or emoji, title, short description.',
        'gallery' => 'Image grid or masonry; placeholder images or img with alt; responsive grid.',
        'features' => 'Feature items with icon/title/description in a grid (3 or 4 columns).',
        'cta' => 'Single call-to-action: heading, short text, one primary button.',
        'hero' => 'Full-width hero: main heading, subtext, one CTA button.',
        'contact' => 'Contact form or contact info block: heading, form fields or address/email/phone.',
        'faq' => 'Accordion or list of question/answer pairs.',
        'default' => 'Section with heading and 2–3 content blocks; use semantic HTML.',
    ];

    public function __construct(
        protected CodebaseScanner $scanner,
        protected InternalAiService $ai,
        protected DesignRulesService $designRules,
        protected SectionNameNormalizer $sectionNameNormalizer
    ) {}

    /**
     * Generate component TSX for a section if it does not already exist.
     *
     * @return array{success: bool, already_exists?: bool, path?: string, content?: string, error?: string}
     */
    public function generate(Project $project, string $sectionName, string $userPrompt = ''): array
    {
        $sectionName = $this->normalizeSectionName($sectionName);
        if ($sectionName === '') {
            return ['success' => false, 'error' => 'Invalid section name'];
        }

        $path = 'src/sections/'.$sectionName.'.tsx';

        $scan = null;
        try {
            $scan = $this->scanner->getScanFromIndex($project);
            if ($scan === null) {
                $scan = $this->scanner->scan($project);
                $this->scanner->writeIndex($project, $scan);
            }
        } catch (\Throwable $e) {
            Log::warning('Component generator: scan failed', ['project_id' => $project->id, 'error' => $e->getMessage()]);
        }

        $existingSections = $this->getSectionNamesFromScan($scan ?? []);
        if (in_array($sectionName, $existingSections, true)) {
            return [
                'success' => true,
                'already_exists' => true,
                'path' => $path,
            ];
        }

        if (! $this->ai->isConfigured()) {
            return [
                'success' => false,
                'error' => 'AI is not configured. Cannot generate component.',
            ];
        }

        $prompt = $this->buildPrompt($sectionName, $userPrompt, $existingSections);
        $response = $this->ai->completeForProjectEdit($prompt, 4000);

        if ($response === null || trim($response) === '') {
            return [
                'success' => false,
                'error' => 'AI did not return component content.',
            ];
        }

        $content = $this->extractTsxContent($response);
        if ($content === null || ! $this->isValidSectionContent($content, $sectionName)) {
            $content = $this->fallbackSectionContent($sectionName);
        }

        return [
            'success' => true,
            'already_exists' => false,
            'path' => $path,
            'content' => $content,
        ];
    }

    private function normalizeSectionName(string $name): string
    {
        return $this->sectionNameNormalizer->normalize($name);
    }

    /**
     * @param  array{sections?: array}  $scan
     * @return array<int, string>
     */
    private function getSectionNamesFromScan(array $scan): array
    {
        $names = [];
        foreach ($scan['sections'] ?? [] as $p) {
            $base = basename($p, '.tsx');
            if ($base !== '' && ! in_array($base, $names, true)) {
                $names[] = $base;
            }
        }

        return $names;
    }

    /**
     * @param  array<int, string>  $existingSections
     */
    private function buildPrompt(string $sectionName, string $userPrompt, array $existingSections): string
    {
        $designRules = $this->designRules->getPromptFragment();
        $type = strtolower(preg_replace('/Section$/', '', $sectionName));
        $contentHint = self::CONTENT_HINTS[$type] ?? self::CONTENT_HINTS['default'];
        $existingList = $existingSections !== [] ? 'Existing sections (do not duplicate): '.implode(', ', $existingSections) : 'No existing sections.';

        return <<<PROMPT
{$designRules}

---

You are generating a single new React/TypeScript section component for a Webu project. The file must be the ONLY output (no markdown, no code fence, no explanation).

Requirements:
- File path: src/sections/{$sectionName}.tsx
- Default export: export default function {$sectionName}(props: {$sectionName}Props) { ... }
- MUST use: <section className="section"> and inside it <div className="container">. Follow the Webu Design System above (no fixed widths, use container, standard spacing).
- Use only React and standard HTML. If you need icons, use inline SVG or a simple placeholder; do not add new npm packages.
- Expose editable content through props instead of hardcoded strings. Include a TypeScript props type with builder-compatible fields like sectionId, title, subtitle, buttonText, buttonLink, image, imageAlt, and any extra primitive fields the section needs.
- Add Webu DOM mapping attributes: the section wrapper must include data-webu-section="{$sectionName}" and data-webu-section-local-id={sectionId}; editable nodes must include literal data-webu-field="fieldName" attributes.
- Do not emit starter copy, TODO text, "replace with your content", "coming soon", or placeholder-only labels.
- Content type: {$contentHint}
- {$existingList}

User context: {$userPrompt}

Output ONLY the complete TSX file content (no markdown wrapper).
PROMPT;
    }

    private function extractTsxContent(string $raw): ?string
    {
        $raw = trim($raw);
        if (preg_match('/```(?:tsx?|jsx?)?\s*([\s\S]*?)```/', $raw, $m)) {
            return trim($m[1]);
        }
        if (str_contains($raw, 'export default')) {
            return $raw;
        }

        return null;
    }

    private function isValidSectionContent(string $content, string $sectionName): bool
    {
        if (! str_contains($content, 'export default')) {
            return false;
        }
        if (! str_contains($content, $sectionName)) {
            return false;
        }
        if (! (str_contains($content, 'container') && str_contains($content, 'section'))) {
            return false;
        }

        return true;
    }

    private function fallbackSectionContent(string $sectionName): string
    {
        $title = $this->sectionNameNormalizer->humanize($sectionName);
        $type = strtolower(preg_replace('/Section$/', '', $sectionName));
        $subtitle = match ($type) {
            'contact' => 'Share one clear contact path and reassure visitors about what happens next.',
            'services' => 'Summarize the offer in one direct sentence and lead the visitor to the next decision.',
            'newsletter' => 'Invite visitors to subscribe with a short value-driven promise instead of filler copy.',
            'cta' => 'Keep the call to action focused so the next step is obvious.',
            default => 'Use this section to support the page goal with one clear sentence and a focused call to action.',
        };

        return <<<TSX
type {$sectionName}Props = {
  sectionId?: string;
  title?: string;
  subtitle?: string;
  buttonText?: string;
  buttonLink?: string;
};

export default function {$sectionName}({
  sectionId = '{$sectionName}-1',
  title = '{$title}',
  subtitle = '{$subtitle}',
  buttonText = 'Learn More',
  buttonLink = '#contact',
}: {$sectionName}Props) {
  return (
    <section className="section" data-webu-section="{$sectionName}" data-webu-section-local-id={sectionId}>
      <div className="container">
        <div className="section-copy">
          <h2 className="section-title" data-webu-field="title">{title}</h2>
          <p className="section-description" data-webu-field="subtitle">{subtitle}</p>
          <a className="button-primary" href={buttonLink} data-webu-field="buttonText">{buttonText}</a>
        </div>
      </div>
    </section>
  );
}
TSX;
    }
}
