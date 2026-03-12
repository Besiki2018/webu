<?php

namespace App\Services;

use App\Cms\Support\LocalizedCmsPayload;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\Site;
use App\Support\CmsSectionLocalId;
use Illuminate\Support\Str;

/**
 * Provides page/section structure for the AI Site Editor (content analysis).
 * Returns editable structure so the AI interpreter has context.
 */
class AiSiteEditorAnalyzeService
{
    public function __construct(
        protected SiteProvisioningService $siteProvisioning,
        protected CmsComponentLibraryCatalogService $catalog,
        protected FixedLayoutComponentService $fixedLayoutComponents,
        protected LocalizedCmsPayload $localizedPayload
    ) {}

    /**
     * Analyze project site: pages with sections, editable parameters, and global components.
     * Used by the AI agent to know page structure before executing changes.
     *
     * @return array{
     *   pages: array<int, array{id: int, slug: string, title: string, sections: array<int, array{id: string, type: string, label: string, editable_fields?: string[]}>}>,
     *   global_components: array<int, array{id: string, label: string, editable_fields?: string[]}>
     * }
     */
    public function analyze(Project $project, ?string $locale = null): array
    {
        $site = $this->siteProvisioning->provisionForProject($project);
        $pages = Page::query()
            ->where('site_id', $site->id)
            ->orderByRaw("CASE WHEN slug = 'home' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();

        $catalogByKey = [];
        foreach ($this->catalog->buildCatalog() as $item) {
            $key = Str::lower(trim((string) ($item['key'] ?? '')));
            if ($key !== '') {
                $catalogByKey[$key] = $item;
            }
        }

        $result = [];
        foreach ($pages as $page) {
            $result[] = $this->analyzePage($site, $page, $catalogByKey, $locale);
        }

        $globalComponents = $this->analyzeGlobalComponents($site);

        return [
            'pages' => $result,
            'global_components' => $globalComponents,
        ];
    }

    /**
     * Return header and footer as global components so the AI knows they are editable site-wide.
     *
     * @return array<int, array{id: string, label: string, editable_fields?: string[]}>
     */
    private function analyzeGlobalComponents(Site $site): array
    {
        $theme = is_array($site->theme_settings) ? $site->theme_settings : [];
        $layout = is_array($theme['layout'] ?? null) ? $theme['layout'] : [];
        $out = [];
        $headerProps = $layout['header_props'] ?? [];
        if (is_array($headerProps)) {
            $headerSectionKey = trim((string) ($layout['header_section_key'] ?? 'webu_header_01'));
            $normalizedHeaderProps = $this->fixedLayoutComponents->normalizeProps($headerSectionKey, $headerProps);
            $out[] = [
                'id' => 'header',
                'label' => 'Header',
                'editable_fields' => $this->fixedLayoutComponents->editableFields($headerSectionKey, $normalizedHeaderProps),
            ];
        }
        $footerProps = $layout['footer_props'] ?? [];
        if (is_array($footerProps)) {
            $footerSectionKey = trim((string) ($layout['footer_section_key'] ?? 'webu_footer_01'));
            $normalizedFooterProps = $this->fixedLayoutComponents->normalizeProps($footerSectionKey, $footerProps);
            $out[] = [
                'id' => 'footer',
                'label' => 'Footer',
                'editable_fields' => $this->fixedLayoutComponents->editableFields($footerSectionKey, $normalizedFooterProps),
            ];
        }
        return $out;
    }

    /**
     * @param  array<string, array<string, mixed>>  $catalogByKey
     * @return array{id: int, slug: string, title: string, sections: array<int, array{id: string, type: string, label: string, editable_fields?: string[]}>}
     */
    private function analyzePage(Site $site, Page $page, array $catalogByKey, ?string $locale = null): array
    {
        $revision = PageRevision::query()
            ->where('site_id', $site->id)
            ->where('page_id', $page->id)
            ->latest('version')
            ->first();

        $content = is_array($revision?->content_json) ? $revision->content_json : [];
        $resolvedPayload = $this->localizedPayload->resolve($content, $locale, $site->locale);
        $resolvedContent = is_array($resolvedPayload['content'] ?? null) ? $resolvedPayload['content'] : [];
        $sections = is_array($resolvedContent['sections'] ?? null) ? $resolvedContent['sections'] : [];

        $sectionList = [];
        foreach ($sections as $index => $section) {
            if (! is_array($section)) {
                continue;
            }
            $type = trim((string) ($section['type'] ?? $section['key'] ?? ''));
            if ($type === '') {
                continue;
            }
            $normalizedType = Str::lower(Str::replace(['-', '_'], '', $type));
            $localId = CmsSectionLocalId::resolve($section, is_int($index) ? $index : count($sectionList));
            $catalogEntry = $catalogByKey[$normalizedType] ?? $catalogByKey[$type] ?? null;
            $label = $catalogEntry['label'] ?? $type;
            $editableFields = null;
            if ($catalogEntry !== null && isset($catalogEntry['schema_json']['properties'])) {
                $props = $catalogEntry['schema_json']['properties'];
                $editableFields = is_array($props) ? array_keys($props) : [];
            }
            if ($editableFields === null || $editableFields === []) {
                $editableFields = $this->fallbackEditableFieldsForSection($type);
            }
            $props = $section['props'] ?? null;
            if (! is_array($props) && ! empty($section['propsText'] ?? '')) {
                $decoded = json_decode((string) $section['propsText'], true);
                $props = is_array($decoded) ? $decoded : [];
            }
            $props = is_array($props) ? $props : [];

            $sectionList[] = array_filter([
                'id' => $localId,
                'type' => $type,
                'label' => is_string($label) ? $label : $type,
                'editable_fields' => $editableFields,
                'props' => $props,
            ], static fn ($v) => $v !== null);
        }

        return [
            'id' => $page->id,
            'slug' => $page->slug ?? 'home',
            'title' => $page->title ?? $page->slug ?? 'Page',
            'sections' => $sectionList,
        ];
    }

    /**
     * @return array<int, string>
     */
    private function fallbackEditableFieldsForSection(string $sectionType): array
    {
        return match (Str::lower(trim($sectionType))) {
            'webu_general_heading_01' => ['headline', 'color', 'background_color'],
            'webu_general_text_01' => ['body', 'color', 'background_color'],
            'webu_general_image_01' => ['image_url', 'image_alt', 'image_link'],
            'webu_general_button_01' => ['button', 'url', 'style_variant', 'background_color', 'text_color'],
            default => [],
        };
    }
}
