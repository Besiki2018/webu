<?php

namespace App\Services;

use App\Models\GlobalSetting;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Site;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CmsAiOutputSaveEngine
{
    public const CONTENT_MODEL_VERSION = 1;

    public function __construct(
        protected CmsAiSchemaValidationService $schemaValidator,
        protected CmsSectionBindingService $sectionBindings,
        protected CmsThemeTokenValueValidator $themeTokenValidator,
        protected CmsTypographyService $typography
    ) {}

    /**
     * Persist AI output v1 into current Webu storage channels only:
     * - site.theme_settings (+ layout/fixed sections)
     * - global_settings
     * - pages
     * - page_revisions.content_json
     *
     * @param  array<string, mixed>  $aiOutput
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function persistOutputForSite(Site $site, array $aiOutput, ?int $actorId = null, array $options = []): array
    {
        $validation = $this->schemaValidator->validateOutputPayload($aiOutput);
        if (! ($validation['valid'] ?? false)) {
            return [
                'ok' => false,
                'code' => 'invalid_ai_output',
                'errors' => is_array($validation['errors'] ?? null) ? $validation['errors'] : [],
                'warnings' => [],
                'validation' => [
                    'output' => $this->compactValidationReport($validation),
                ],
                'saved' => null,
            ];
        }

        $respectKeepExisting = (bool) ($options['respect_keep_existing'] ?? true);
        $publishSiteOnPublishedPage = (bool) ($options['publish_site_on_published_page'] ?? true);

        $warnings = [];
        $summary = DB::transaction(function () use (
            $site,
            $aiOutput,
            $actorId,
            $respectKeepExisting,
            $publishSiteOnPublishedPage,
            &$warnings
        ): array {
            $site = $site->fresh();

            $themeResult = $this->persistThemeAndFixedSections($site, is_array($aiOutput['theme'] ?? null) ? $aiOutput['theme'] : [], $aiOutput, $warnings);
            $pageResult = $this->persistPages($site, is_array($aiOutput['pages'] ?? null) ? $aiOutput['pages'] : [], $actorId, $respectKeepExisting, $publishSiteOnPublishedPage, $warnings);

            return [
                'site_id' => $site->id,
                'theme' => $themeResult,
                'pages' => $pageResult,
                'storage_channels' => [
                    'sites.theme_settings',
                    'global_settings',
                    'pages',
                    'page_revisions.content_json',
                ],
                'no_parallel_storage' => true,
            ];
        });

        return [
            'ok' => true,
            'warnings' => array_values(array_unique(array_filter($warnings, static fn ($v): bool => is_string($v) && trim($v) !== ''))),
            'errors' => [],
            'validation' => [
                'output' => $this->compactValidationReport($validation),
            ],
            'saved' => $summary,
        ];
    }

    /**
     * @param  array<string, mixed>  $theme
     * @param  array<string, mixed>  $aiOutput
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function persistThemeAndFixedSections(Site $site, array $theme, array $aiOutput, array &$warnings): array
    {
        $existingThemeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $themeSettingsPatch = is_array($theme['theme_settings_patch'] ?? null) ? $theme['theme_settings_patch'] : [];
        $siteLayoutPatch = is_array($theme['site_layout_patch'] ?? null) ? $theme['site_layout_patch'] : [];
        $globalSettingsPatch = is_array($theme['global_settings_patch'] ?? null) ? $theme['global_settings_patch'] : [];

        $nextThemeSettings = $this->mergePatch($existingThemeSettings, $themeSettingsPatch);

        if ($siteLayoutPatch !== []) {
            $layout = is_array($nextThemeSettings['layout'] ?? null) ? $nextThemeSettings['layout'] : [];
            $layout = $this->mergePatch($layout, $siteLayoutPatch);
            $nextThemeSettings['layout'] = $layout;
        }

        $layout = is_array($nextThemeSettings['layout'] ?? null) ? $nextThemeSettings['layout'] : [];
        $layout = $this->applyFixedSectionOutputToLayout($layout, 'header', is_array($aiOutput['header'] ?? null) ? $aiOutput['header'] : [], $warnings);
        $layout = $this->applyFixedSectionOutputToLayout($layout, 'footer', is_array($aiOutput['footer'] ?? null) ? $aiOutput['footer'] : [], $warnings);
        if ($layout !== []) {
            $layout['version'] = is_int($layout['version'] ?? null) ? (int) $layout['version'] : 1;
            $nextThemeSettings['layout'] = $layout;
        }

        $nextThemeSettings = $this->typography->normalizeThemeSettings($nextThemeSettings, $site);
        $this->themeTokenValidator->assertValidThemeSettings($nextThemeSettings);

        $themeUpdated = false;
        if ($nextThemeSettings !== $existingThemeSettings) {
            $site->update([
                'theme_settings' => $nextThemeSettings,
            ]);
            $themeUpdated = true;
        }

        $globalResult = $this->persistGlobalSettingsPatch($site, $globalSettingsPatch, $warnings);

        return [
            'theme_settings_updated' => $themeUpdated,
            'global_settings_updated' => (bool) ($globalResult['updated'] ?? false),
            'fixed_sections' => [
                'header_section_key' => data_get($nextThemeSettings, 'layout.header_section_key'),
                'footer_section_key' => data_get($nextThemeSettings, 'layout.footer_section_key'),
            ],
            'global_settings_fields_updated' => $globalResult['updated_fields'] ?? [],
        ];
    }

    /**
     * @param  array<string, mixed>  $layout
     * @param  array<string, mixed>  $fixedSection
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function applyFixedSectionOutputToLayout(array $layout, string $kind, array $fixedSection, array &$warnings): array
    {
        if ($fixedSection === []) {
            return $layout;
        }

        $prefix = $kind === 'footer' ? 'footer' : 'header';
        $enabled = (bool) ($fixedSection['enabled'] ?? false);
        $sectionType = is_string($fixedSection['section_type'] ?? null) ? trim((string) $fixedSection['section_type']) : '';
        $props = is_array($fixedSection['props'] ?? null) ? $fixedSection['props'] : [];
        $bindings = is_array($fixedSection['bindings'] ?? null) ? $fixedSection['bindings'] : [];
        $meta = is_array($fixedSection['meta'] ?? null) ? $fixedSection['meta'] : [];

        if ($enabled) {
            if ($sectionType !== '') {
                $layout[$prefix.'_section_key'] = $sectionType;
            } elseif (! array_key_exists($prefix.'_section_key', $layout)) {
                $warnings[] = sprintf('%s.enabled=true but section_type is missing; keeping layout.%s_section_key unchanged.', $prefix, $prefix);
            }
        } else {
            $layout[$prefix.'_section_key'] = null;
        }

        if ($props !== []) {
            $layout[$prefix.'_props'] = $props;
        } elseif (! $enabled) {
            $layout[$prefix.'_props'] = [];
        }

        if ($bindings !== [] || $meta !== [] || ! $enabled) {
            $layout[$prefix.'_meta'] = array_filter([
                'enabled' => $enabled,
                'bindings' => $bindings !== [] ? $bindings : null,
                'ai_output_meta' => $meta !== [] ? $meta : null,
            ], static fn ($value) => $value !== null);
        }

        return $layout;
    }

    /**
     * @param  array<string, mixed>  $patch
     * @param  array<int, string>  $warnings
     * @return array{updated: bool, updated_fields: array<int,string>}
     */
    private function persistGlobalSettingsPatch(Site $site, array $patch, array &$warnings): array
    {
        if ($patch === []) {
            return ['updated' => false, 'updated_fields' => []];
        }

        $global = GlobalSetting::query()->firstOrCreate(['site_id' => $site->id]);
        $allowedFields = ['logo_media_id', 'contact_json', 'social_links_json', 'analytics_ids_json'];
        $updates = [];
        $updatedFields = [];

        foreach ($patch as $field => $value) {
            if (! is_string($field)) {
                continue;
            }

            if (! in_array($field, $allowedFields, true)) {
                $warnings[] = "Skipped unsupported global_settings_patch field [{$field}] in P4-E2-04 baseline save engine.";
                continue;
            }

            if (in_array($field, ['contact_json', 'social_links_json', 'analytics_ids_json'], true)) {
                if (! is_array($value)) {
                    $warnings[] = "Skipped global_settings_patch.{$field}; expected object/array payload.";
                    continue;
                }
            }

            $updates[$field] = $value;
            $updatedFields[] = $field;
        }

        if ($updates === []) {
            return ['updated' => false, 'updated_fields' => []];
        }

        $global->fill($updates);
        $dirty = $global->isDirty();
        if ($dirty) {
            $global->save();
        }

        return [
            'updated' => $dirty,
            'updated_fields' => array_values($updatedFields),
        ];
    }

    /**
     * @param  array<int, mixed>  $pages
     * @param  array<int, string>  $warnings
     * @return array<string, mixed>
     */
    private function persistPages(Site $site, array $pages, ?int $actorId, bool $respectKeepExisting, bool $publishSiteOnPublishedPage, array &$warnings): array
    {
        $createdPages = 0;
        $updatedPages = 0;
        $createdRevisions = 0;
        $publishedRevisions = 0;
        $publishedAny = false;
        $skippedKeepExisting = 0;
        $pageResults = [];

        foreach ($pages as $index => $pageOutput) {
            if (! is_array($pageOutput)) {
                $warnings[] = "Skipped non-object page output at index {$index}.";
                continue;
            }

            $slug = $this->normalizePageSlug($pageOutput['slug'] ?? null);
            if ($slug === null) {
                $warnings[] = "Skipped page output at index {$index}; missing slug.";
                continue;
            }

            $source = is_string(data_get($pageOutput, 'meta.source')) ? (string) data_get($pageOutput, 'meta.source') : 'generated';
            if ($respectKeepExisting && $source === 'keep_existing') {
                $existing = Page::query()->where('site_id', $site->id)->where('slug', $slug)->first();
                if ($existing) {
                    $skippedKeepExisting++;
                    $pageResults[] = [
                        'slug' => $slug,
                        'page_id' => $existing->id,
                        'action' => 'skipped_keep_existing',
                        'revision_id' => null,
                        'version' => null,
                        'published' => false,
                    ];
                    continue;
                }

                $warnings[] = "Page [{$slug}] marked keep_existing but does not exist; saving generated fallback instead.";
            }

            $page = Page::query()
                ->where('site_id', $site->id)
                ->where('slug', $slug)
                ->first();

            $isNewPage = ! $page;
            if (! $page) {
                $page = new Page([
                    'site_id' => $site->id,
                    'slug' => $slug,
                ]);
                $createdPages++;
                $action = 'created';
            } else {
                $updatedPages++;
                $action = 'updated';
            }

            $page->title = $this->normalizeNonEmptyString($pageOutput['title'] ?? null) ?? Str::headline(str_replace('-', ' ', $slug));
            $page->seo_title = $this->normalizeNullableString(data_get($pageOutput, 'seo.seo_title'));
            $page->seo_description = $this->normalizeNullableString(data_get($pageOutput, 'seo.seo_description'));

            if ($isNewPage) {
                $page->status = 'draft';
            }

            $page->save();

            $latestVersion = (int) PageRevision::query()
                ->where('site_id', $site->id)
                ->where('page_id', $page->id)
                ->max('version');

            $contentJson = $this->contentJsonFromPageOutput($pageOutput);
            $revision = PageRevision::query()->create([
                'site_id' => $site->id,
                'page_id' => $page->id,
                'version' => $latestVersion + 1,
                'content_json' => $contentJson,
                'created_by' => $actorId,
                'published_at' => null,
            ]);
            $createdRevisions++;

            $shouldPublish = (string) ($pageOutput['status'] ?? 'draft') === 'published';
            if ($shouldPublish) {
                PageRevision::query()
                    ->where('site_id', $site->id)
                    ->where('page_id', $page->id)
                    ->whereKeyNot($revision->id)
                    ->update(['published_at' => null]);

                $revision->published_at = now();
                $revision->save();

                if ($page->status !== 'published') {
                    $page->status = 'published';
                    $page->save();
                }

                $publishedRevisions++;
                $publishedAny = true;
            }

            $pageResults[] = [
                'slug' => $slug,
                'page_id' => $page->id,
                'action' => $action,
                'revision_id' => $revision->id,
                'version' => $revision->version,
                'published' => $shouldPublish,
            ];
        }

        if ($publishSiteOnPublishedPage && $publishedAny && $site->status === 'draft') {
            $site->update(['status' => 'published']);
        }

        return [
            'created_pages' => $createdPages,
            'updated_pages' => $updatedPages,
            'created_revisions' => $createdRevisions,
            'published_revisions' => $publishedRevisions,
            'skipped_keep_existing_pages' => $skippedKeepExisting,
            'page_results' => $pageResults,
        ];
    }

    /**
     * Map AI output page artifact into the current page revision content model.
     *
     * @param  array<string, mixed>  $pageOutput
     * @return array<string, mixed>
     */
    private function contentJsonFromPageOutput(array $pageOutput): array
    {
        $builderNodes = is_array($pageOutput['builder_nodes'] ?? null) ? array_values($pageOutput['builder_nodes']) : [];
        $sections = [];

        foreach ($builderNodes as $node) {
            if (! is_array($node)) {
                continue;
            }

            $sections[] = $this->sectionPayloadFromCanonicalNode($node);
        }

        return [
            'schema_version' => self::CONTENT_MODEL_VERSION,
            'sections' => $sections,
            'ai_generation' => [
                'schema_version' => 1,
                'saved_via' => 'CmsAiOutputSaveEngine',
                'builder_nodes' => $builderNodes,
                'page_css' => is_string($pageOutput['page_css'] ?? null) ? (string) $pageOutput['page_css'] : '',
                'route' => [
                    'path' => is_string($pageOutput['path'] ?? null) ? (string) $pageOutput['path'] : null,
                    'route_pattern' => is_string($pageOutput['route_pattern'] ?? null) ? (string) $pageOutput['route_pattern'] : null,
                    'template_key' => is_string($pageOutput['template_key'] ?? null) ? (string) $pageOutput['template_key'] : null,
                ],
                'meta' => is_array($pageOutput['meta'] ?? null) ? $pageOutput['meta'] : [],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function sectionPayloadFromCanonicalNode(array $node): array
    {
        $type = $this->normalizeSectionType((string) ($node['type'] ?? 'section'));
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];

        $sectionProps = array_filter([
            'content' => is_array($props['content'] ?? null) ? $props['content'] : [],
            'data' => is_array($props['data'] ?? null) ? $props['data'] : [],
            'style' => is_array($props['style'] ?? null) ? $props['style'] : [],
            'advanced' => is_array($props['advanced'] ?? null) ? $props['advanced'] : [],
            'responsive' => is_array($props['responsive'] ?? null) ? $props['responsive'] : [],
            'states' => is_array($props['states'] ?? null) ? $props['states'] : [],
        ], static fn ($value): bool => is_array($value));

        if (is_array($node['children'] ?? null)) {
            $sectionProps['children'] = array_values(array_map(
                fn ($child) => is_array($child) ? $this->canonicalNodeSnapshot($child) : [],
                array_values(array_filter((array) $node['children'], 'is_array'))
            ));
        }

        $payload = $this->sectionBindings->buildSectionPayload($type, $sectionProps);

        if (is_array($node['bindings'] ?? null) && $node['bindings'] !== []) {
            $payload['binding']['ai_bindings'] = $node['bindings'];
        }

        if (is_array($node['meta'] ?? null) && $node['meta'] !== []) {
            $payload['meta'] = [
                'ai_canonical_node_meta' => $node['meta'],
            ];
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $node
     * @return array<string, mixed>
     */
    private function canonicalNodeSnapshot(array $node): array
    {
        $props = is_array($node['props'] ?? null) ? $node['props'] : [];

        $snapshot = [
            'type' => $this->normalizeSectionType((string) ($node['type'] ?? 'section')),
            'props' => [
                'content' => is_array($props['content'] ?? null) ? $props['content'] : [],
                'data' => is_array($props['data'] ?? null) ? $props['data'] : [],
                'style' => is_array($props['style'] ?? null) ? $props['style'] : [],
                'advanced' => is_array($props['advanced'] ?? null) ? $props['advanced'] : [],
                'responsive' => is_array($props['responsive'] ?? null) ? $props['responsive'] : [],
                'states' => is_array($props['states'] ?? null) ? $props['states'] : [],
            ],
            'bindings' => is_array($node['bindings'] ?? null) ? $node['bindings'] : [],
            'meta' => is_array($node['meta'] ?? null) ? $node['meta'] : [],
        ];

        if (is_array($node['children'] ?? null) && $node['children'] !== []) {
            $snapshot['children'] = array_values(array_map(
                fn ($child) => is_array($child) ? $this->canonicalNodeSnapshot($child) : [],
                array_values(array_filter((array) $node['children'], 'is_array'))
            ));
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $base
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function mergePatch(array $base, array $patch): array
    {
        foreach ($patch as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            if (
                array_key_exists($key, $base)
                && is_array($base[$key])
                && is_array($value)
                && ! array_is_list($base[$key])
                && ! array_is_list($value)
            ) {
                /** @var array<string, mixed> $baseValue */
                $baseValue = $base[$key];
                /** @var array<string, mixed> $patchValue */
                $patchValue = $value;
                $base[$key] = $this->mergePatch($baseValue, $patchValue);
                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function compactValidationReport(array $report): array
    {
        return [
            'valid' => (bool) ($report['valid'] ?? false),
            'schema' => $report['schema'] ?? null,
            'error_count' => (int) ($report['error_count'] ?? 0),
        ];
    }

    private function normalizePageSlug(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $slug = Str::slug((string) $value);
        return $slug !== '' ? $slug : null;
    }

    private function normalizeSectionType(string $type): string
    {
        // Preserve existing Webu section keys (e.g. `webu_hero_01`) to avoid breaking runtime lookups.
        $normalized = Str::of($type)->trim()->lower()->value();
        return $normalized !== '' ? $normalized : 'section';
    }

    private function normalizeNonEmptyString(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $normalized = trim((string) $value);
        return $normalized !== '' ? $normalized : null;
    }

    private function normalizeNullableString(mixed $value): ?string
    {
        return $this->normalizeNonEmptyString($value);
    }
}
