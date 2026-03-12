<?php

namespace App\Services;

use App\Models\Project;
use App\Models\SectionLibrary;
use App\Models\Template;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class InternalAssetRetrievalService
{
    public function __construct(
        protected TemplateClassifierService $classifier
    ) {}

    /**
     * Build retrieval context for builder prompts. Internal catalog assets
     * are always preferred before generic generation fallback.
     *
     * @return array<string, mixed>
     */
    public function buildContext(Project $project, string $prompt, ?string $templateId = null): array
    {
        $prompt = trim($prompt);
        $locale = $this->resolveLocale($project);
        $ownerPlan = $project->user?->getCurrentPlan();
        $classification = $this->classifier->classifyDetailed($prompt, $locale);

        $templateCandidates = $this->templateCandidates(
            project: $project,
            templateId: $templateId,
            classifiedCategory: (string) ($classification['category'] ?? 'default'),
            limit: 6
        );

        $keywordBag = $this->keywordBag($prompt);
        $sectionCandidates = $this->sectionCandidates($keywordBag, 12);
        $projectAssetHints = $this->projectAssetHints($project);

        $matchedInternal = count($templateCandidates) > 0
            || count($sectionCandidates) > 0
            || count($projectAssetHints) > 0;

        return [
            'source' => 'internal_catalog',
            'fallback_to_generic' => ! $matchedInternal,
            'locale' => $locale,
            'query' => Str::limit($prompt, 400, ''),
            'classification' => [
                'category' => (string) ($classification['category'] ?? 'default'),
                'confidence' => (float) ($classification['confidence'] ?? 0.0),
                'strategy' => (string) ($classification['strategy'] ?? 'default'),
                'fallback_reason' => $classification['fallback_reason'] ?? null,
            ],
            'catalog' => [
                'plan_id' => $ownerPlan?->id,
                'templates' => $templateCandidates,
                'sections' => $sectionCandidates,
                'project_assets' => $projectAssetHints,
            ],
            'provenance' => [
                'template_source' => count($templateCandidates) > 0 ? 'internal_catalog' : 'none',
                'section_source' => count($sectionCandidates) > 0 ? 'internal_catalog' : 'none',
                'project_source' => count($projectAssetHints) > 0 ? 'project_workspace' : 'none',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function templateCandidates(Project $project, ?string $templateId, string $classifiedCategory, int $limit): array
    {
        if ($templateId !== null && $templateId !== '') {
            $explicit = Template::query()->find($templateId);
            if ($explicit) {
                return [$this->mapTemplate($explicit)];
            }
        }

        if ($project->template_id) {
            $current = Template::query()->find($project->template_id);
            if ($current) {
                return [$this->mapTemplate($current)];
            }
        }

        $ownerPlan = $project->user?->getCurrentPlan();
        $query = Template::query()->forPlan($ownerPlan)->orderBy('is_system', 'desc')->orderBy('name');

        if ($classifiedCategory !== '' && $classifiedCategory !== 'default') {
            $templates = (clone $query)
                ->where('category', $classifiedCategory)
                ->limit($limit)
                ->get();

            if ($templates->isNotEmpty()) {
                return $templates->map(fn (Template $template): array => $this->mapTemplate($template))
                    ->values()
                    ->all();
            }
        }

        return $query
            ->limit($limit)
            ->get()
            ->map(fn (Template $template): array => $this->mapTemplate($template))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $keywords
     * @return array<int, array<string, mixed>>
     */
    private function sectionCandidates(array $keywords, int $limit): array
    {
        $query = SectionLibrary::query()
            ->where('enabled', true);

        if ($keywords !== []) {
            $query->where(function ($builder) use ($keywords): void {
                foreach ($keywords as $keyword) {
                    $builder->orWhere('key', 'like', "%{$keyword}%")
                        ->orWhere('category', 'like', "%{$keyword}%");
                }
            });
        }

        return $query
            ->orderBy('category')
            ->orderBy('key')
            ->limit($limit)
            ->get()
            ->map(fn (SectionLibrary $section): array => [
                'id' => $section->id,
                'key' => $section->key,
                'category' => $section->category,
                'schema_keys' => array_keys(is_array($section->schema_json) ? $section->schema_json : []),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function projectAssetHints(Project $project): array
    {
        $site = $project->site;
        if (! $site) {
            return [];
        }

        $pages = $site->pages()
            ->orderBy('id')
            ->limit(20)
            ->get(['id', 'slug', 'title', 'status']);

        return $pages->map(fn ($page): array => [
            'type' => 'cms_page',
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $page->title,
            'status' => $page->status,
        ])->values()->all();
    }

    /**
     * @return array<int, string>
     */
    private function keywordBag(string $prompt): array
    {
        return collect(preg_split('/[\s,.;:!?\-_\(\)\[\]{}]+/u', mb_strtolower($prompt)) ?: [])
            ->filter(fn (?string $word): bool => is_string($word) && mb_strlen($word) >= 3)
            ->map(fn (string $word): string => trim($word))
            ->unique()
            ->take(12)
            ->values()
            ->all();
    }

    private function resolveLocale(Project $project): string
    {
        $siteLocale = trim((string) Arr::get($project->site?->toArray(), 'locale', ''));
        if ($siteLocale !== '') {
            return $siteLocale;
        }

        $userLocale = trim((string) ($project->user?->locale ?? ''));
        if ($userLocale !== '') {
            return $userLocale;
        }

        return trim((string) config('app.locale', 'ka')) ?: 'ka';
    }

    /**
     * @return array<string, mixed>
     */
    private function mapTemplate(Template $template): array
    {
        $metadata = is_array($template->metadata) ? $template->metadata : [];

        return [
            'id' => (string) $template->id,
            'slug' => (string) $template->slug,
            'name' => (string) $template->name,
            'category' => (string) ($template->category ?? 'default'),
            'module_flags' => Arr::get($metadata, 'module_flags', []),
            'default_pages' => Arr::get($metadata, 'default_pages', []),
            'default_sections' => Arr::get($metadata, 'default_sections', []),
            'typography_tokens' => Arr::get($metadata, 'typography_tokens', []),
        ];
    }
}

