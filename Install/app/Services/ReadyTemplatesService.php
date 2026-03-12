<?php

namespace App\Services;

use App\Models\Template;
use App\Support\CreateTemplateCatalogVisibility;
use App\Support\OwnedTemplateCatalog;
use Illuminate\Support\Arr;

class ReadyTemplatesService
{
    /**
     * List ready templates (from catalog) for "Create from template" dropdown.
     *
     * @return array<int, array{slug: string, name: string, path: string, category?: string}>
     */
    public function list(): array
    {
        $slugs = OwnedTemplateCatalog::slugs();
        if ($slugs === []) {
            return [];
        }

        $templates = Template::query()
            ->whereIn('slug', $slugs)
            ->get(['id', 'slug', 'name', 'category']);

        $order = array_flip($slugs);
        $templates = $templates->sortBy(fn (Template $t) => $order[(string) $t->slug] ?? 999)->values();

        $baseUrl = rtrim(config('app.url'), '/');

        return $templates
            ->filter(static fn (Template $template): bool => CreateTemplateCatalogVisibility::allowsTemplate($template))
            ->map(function (Template $t) use ($baseUrl): array {
                return [
                    'slug' => (string) $t->slug,
                    'name' => (string) $t->name,
                    'path' => $baseUrl . '/template-demos/' . $t->slug,
                    'category' => $t->category ? (string) $t->category : null,
                ];
            })->values()->all();
    }

    /**
     * Load template data by slug for provisioning (theme_preset, default_pages).
     * Returns empty array if template not found or not in ready catalog.
     *
     * @return array<string, mixed>
     */
    public function loadBySlug(string $slug): array
    {
        $slug = trim($slug);
        if ($slug === '' || ! OwnedTemplateCatalog::contains($slug) || ! CreateTemplateCatalogVisibility::allowsSlug($slug)) {
            return [];
        }

        $template = Template::query()->where('slug', $slug)->first();
        if (! $template) {
            return [];
        }

        $metadata = is_array($template->metadata) ? $template->metadata : [];
        $defaultPagesMeta = Arr::get($metadata, 'default_pages', []);
        $defaultSectionsMeta = Arr::get($metadata, 'default_sections', []);

        $defaultPages = [];
        foreach ($defaultPagesMeta as $page) {
            if (! is_array($page)) {
                continue;
            }
            $pageSlug = trim((string) Arr::get($page, 'slug', ''));
            if ($pageSlug === '') {
                continue;
            }
            $title = trim((string) Arr::get($page, 'title', ''));
            if ($title === '') {
                $title = ucfirst(str_replace(['-', '_'], ' ', $pageSlug));
            }
            $sections = Arr::get($defaultSectionsMeta, $pageSlug, Arr::get($page, 'sections', []));
            if (! is_array($sections)) {
                $sections = [];
            }
            $defaultPages[] = [
                'slug' => $pageSlug,
                'title' => $title,
                'sections' => $sections,
            ];
        }

        $preset = trim((string) Arr::get($metadata, 'theme_preset', ''));
        if ($preset === '') {
            $preset = 'default';
        }

        return [
            'theme_preset' => $preset,
            'name' => (string) $template->name,
            'default_pages' => $defaultPages,
        ];
    }
}
