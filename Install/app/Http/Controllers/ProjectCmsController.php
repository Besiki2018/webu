<?php

namespace App\Http\Controllers;

use App\Cms\Contracts\CmsModuleRegistryServiceContract;
use App\Cms\Contracts\CmsPanelMenuServiceContract;
use App\Http\Controllers\Concerns\BuildsProjectGenerationPayload;
use App\Models\Project;
use App\Models\Template;
use App\Services\CmsComponentLibraryCatalogService;
use App\Services\DomainSettingService;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use App\Services\ProjectWorkspace\WorkspaceSectionRegistryService;
use App\Services\SiteProvisioningService;
use App\Cms\Services\CmsSiteVisibilityService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;

class ProjectCmsController extends Controller
{
    use BuildsProjectGenerationPayload;

    public function __construct(
        protected SiteProvisioningService $siteProvisioningService,
        protected CmsModuleRegistryServiceContract $moduleRegistry,
        protected CmsPanelMenuServiceContract $panelMenuService,
        protected DomainSettingService $domainSettingService,
        protected CmsComponentLibraryCatalogService $componentLibraryCatalogService,
        protected WorkspaceSectionRegistryService $workspaceSectionRegistry,
        protected CmsSiteVisibilityService $siteVisibility,
        protected ProjectWorkspaceService $projectWorkspace,
    ) {}

    public function show(Request $request, Project $project): Response|RedirectResponse
    {
        $this->authorize('update', $project);

        $project->loadMissing('latestGenerationRun');
        $generationPayload = $this->buildGenerationPayload(
            $project->latestGenerationRun,
            $project,
            $this->projectWorkspace
        );
        if ($this->generationRequiresCompletionGate($generationPayload)) {
            return redirect()->route('project.generation', [
                'project' => $project,
            ]);
        }

        $site = $this->siteProvisioningService->provisionForProject($project);
        $project->loadMissing('template');
        $resolvedTemplate = $project->template;
        if (! $resolvedTemplate) {
            // Avoid sorting full template rows (which may include large metadata/html payloads)
            // because MySQL can fail with "Out of sort memory" on `select * ... order by ... limit 1`.
            $fallbackTemplateId = Template::query()
                ->orderByDesc('is_system')
                ->orderBy('id')
                ->value('id');

            $resolvedTemplate = $fallbackTemplateId
                ? Template::query()->find($fallbackTemplateId)
                : null;

            // Self-heal legacy projects created before template binding was enforced.
            if ($resolvedTemplate && ! $project->template_id) {
                $project->forceFill(['template_id' => $resolvedTemplate->id])->saveQuietly();
                $project->setRelation('template', $resolvedTemplate);
            }
        }
        $resolvedTemplateSlug = trim((string) ($resolvedTemplate?->slug ?? ''));
        if ($resolvedTemplateSlug === '') {
            $resolvedTemplateSlug = trim((string) Template::query()
                ->whereNotNull('slug')
                ->where('slug', '!=', '')
                ->orderByDesc('is_system')
                ->orderBy('id')
                ->value('slug'));
        }
        if ($resolvedTemplateSlug === '') {
            // Final fallback: keep CMS visual builder preview available even when template rows are missing.
            $resolvedTemplateSlug = 'ecommerce';
        }
        $templateMetadata = is_array($resolvedTemplate?->metadata) ? $resolvedTemplate->metadata : [];
        $user = $request->user();
        $plan = $user?->getCurrentPlan();
        $fileStorageEnabled = (bool) ($user?->canUseFileStorage() ?? false);
        $maxFileSizeMb = (int) ($plan?->getMaxFileSizeMb() ?? 50);
        if ($user?->hasAdminBypass()) {
            $maxFileSizeMb = max($maxFileSizeMb, 50);
        }
        $remainingStorageBytes = (int) ($user?->getRemainingStorageBytes() ?? 0);
        $unlimitedStorage = $remainingStorageBytes === -1 || (bool) ($plan?->hasUnlimitedStorage() ?? false);
        $modulesPayload = $this->moduleRegistry->modules($site, $user);
        $entitlementsPayload = $this->moduleRegistry->entitlements($site, $user);
        $customDomainsGloballyEnabled = $this->domainSettingService->isCustomDomainsEnabled();
        $customDomainSettings = null;
        if ($customDomainsGloballyEnabled && $user) {
            $hasAdminBypass = (bool) ($user->hasAdminBypass() ?? false);
            $customDomainSettings = [
                'enabled' => $hasAdminBypass ? true : (bool) $user->canUseCustomDomains(),
                'canCreateMore' => $hasAdminBypass ? true : (bool) $user->canCreateMoreCustomDomains(),
                'usage' => $user->getCustomDomainUsage(),
                'baseDomain' => $this->domainSettingService->getBaseDomain(),
            ];
        }
        // Section library = admin component library plus project workspace overlays.
        // Generated project sections must show up in the builder without replacing the existing catalog path.
        $sectionLibrary = $this->buildSectionLibraryFromComponentLibrary();
        $sectionLibrary = $this->mergeWorkspaceSectionsIntoSectionLibrary($sectionLibrary, $project);

        $componentVariants = $this->buildComponentVariantsForBuilder();
        $controlDefinitions = $this->loadControlDefinitions();
        $sectionLibrary = $this->mergeControlDefinitionsIntoSectionLibrary($sectionLibrary, $controlDefinitions);
        // Ensure every section type that has a control-definition appears in the library so the builder shows editable fields for all components.
        $sectionLibrary = $this->injectSectionLibraryEntriesFromControlDefinitions($sectionLibrary, $controlDefinitions);

        $navigationMenusPayload = $this->panelMenuService->index($site, $request->query('locale'));

        $requiredEcommercePageSlugs = $this->siteVisibility->hasCapability($site, 'ecommerce')
            ? (array) config('ecommerce-required-pages.slugs', [])
            : [];

        return Inertia::render('Project/Cms', [
            'project' => [
                'id' => $project->id,
                'name' => $project->name,
                'subdomain' => $project->subdomain,
                'custom_domain' => $project->custom_domain,
                'custom_domain_verified' => (bool) $project->custom_domain_verified,
                'custom_domain_ssl_status' => $project->custom_domain_ssl_status,
                'custom_domain_ssl_attempts' => (int) ($project->custom_domain_ssl_attempts ?? 0),
                'custom_domain_ssl_next_retry_at' => $project->custom_domain_ssl_next_retry_at?->toISOString(),
                'custom_domain_ssl_last_error' => $project->custom_domain_ssl_last_error,
            ],
            'site' => [
                'id' => $site->id,
                'name' => $site->name,
                'status' => $site->status,
                'locale' => $site->locale,
                'subdomain' => $site->subdomain,
                'primary_domain' => $site->primary_domain,
                'theme_settings' => $site->theme_settings ?? [],
            ],
            'sectionLibrary' => $sectionLibrary,
            'mediaCapabilities' => [
                'enabled' => $fileStorageEnabled,
                'maxFileSizeMb' => $maxFileSizeMb,
                'remainingBytes' => $remainingStorageBytes,
                'unlimited' => $unlimitedStorage,
            ],
            'moduleRegistry' => $modulesPayload,
            'moduleEntitlements' => $entitlementsPayload,
            'customDomainConfig' => $customDomainSettings,
            'customDomainsGloballyEnabled' => $customDomainsGloballyEnabled,
            'templateBlueprint' => [
                'template_id' => $resolvedTemplate?->id,
                'template_slug' => $resolvedTemplateSlug,
                'default_pages' => data_get($templateMetadata, 'default_pages', []),
                'default_sections' => data_get($templateMetadata, 'default_sections', []),
            ],
            'controlDefinitions' => $controlDefinitions,
            'navigationMenus' => $navigationMenusPayload['menus'] ?? [],
            'layoutPrimitives' => config('layout-primitives', []),
            'componentVariants' => $componentVariants,
            'requiredEcommercePageSlugs' => $requiredEcommercePageSlugs,
            'canonicalBindingNamespaces' => config('cms-binding-namespaces', []),
        ]);
    }

    /**
     * Build component variant options for the builder inspector (layout_variant / style_variant selectors).
     * Only section keys that have layout_variants or style_variants in config are included.
     *
     * @return array<string, array{layout_variants: array<int, string>, style_variants: array<int, string>, default_layout: string, default_style: string}>
     */
    private function buildComponentVariantsForBuilder(): array
    {
        $config = config('component-variants', []);
        $out = [];
        foreach (array_keys($config) as $key) {
            if ($key === 'design_rules' || $key === 'allowed_section_keys') {
                continue;
            }
            $entry = $config[$key];
            if (! is_array($entry)) {
                continue;
            }
            $layoutVariants = isset($entry['layout_variants']) && is_array($entry['layout_variants'])
                ? array_values(array_filter($entry['layout_variants'], fn ($v) => is_string($v)))
                : [];
            $styleVariants = isset($entry['style_variants']) && is_array($entry['style_variants'])
                ? array_values(array_filter($entry['style_variants'], fn ($v) => is_string($v)))
                : [];
            if ($layoutVariants !== [] || $styleVariants !== []) {
                $layoutLabels = isset($entry['variant_labels']) && is_array($entry['variant_labels'])
                    ? array_values(array_filter($entry['variant_labels'], fn ($v) => is_string($v)))
                    : [];
                $out[$key] = [
                    'layout_variants' => $layoutVariants,
                    'style_variants' => $styleVariants,
                    'layout_variant_labels' => array_slice($layoutLabels, 0, count($layoutVariants)),
                    'default_layout' => is_string($entry['default_layout'] ?? null) ? $entry['default_layout'] : '',
                    'default_style' => is_string($entry['default_style'] ?? null) ? $entry['default_style'] : '',
                ];
            }
        }

        return $out;
    }

    /**
     * Load control definitions from resources/schemas/control-definitions (hero, product_grid, header, etc.).
     * Keys by "component_key" or "type" so that definitions are the single source of truth for builder controls.
     *
     * @return array<string, array<string, mixed>>
     */
    private function loadControlDefinitions(): array
    {
        $basePath = resource_path('schemas/control-definitions');
        if (! File::isDirectory($basePath)) {
            return [];
        }
        $definitions = [];
        foreach (File::files($basePath) as $file) {
            if ($file->getExtension() !== 'json') {
                continue;
            }
            $json = File::get($file->getPathname());
            $data = json_decode($json, true);
            if (! is_array($data)) {
                continue;
            }
            $key = $data['component_key'] ?? $data['type'] ?? null;
            if (is_string($key) && trim($key) !== '') {
                $definitions[trim($key)] = $data;
            }
        }

        return $definitions;
    }

    /**
     * Elementor-style advanced spacing: padding/margin per side, applied to component wrapper.
     * Used in Advanced tab and in responsive (Desktop/Tablet/Mobile). Values are CSS (e.g. "16px", "1rem").
     *
     * @return array<string, array<string, mixed>>
     */
    private function getGlobalAdvancedSpacingProperties(): array
    {
        return [
            'padding_top' => ['type' => 'string', 'title' => 'Padding Top', 'default' => '', 'control_group' => 'advanced'],
            'padding_right' => ['type' => 'string', 'title' => 'Padding Right', 'default' => '', 'control_group' => 'advanced'],
            'padding_bottom' => ['type' => 'string', 'title' => 'Padding Bottom', 'default' => '', 'control_group' => 'advanced'],
            'padding_left' => ['type' => 'string', 'title' => 'Padding Left', 'default' => '', 'control_group' => 'advanced'],
            'margin_top' => ['type' => 'string', 'title' => 'Margin Top', 'default' => '', 'control_group' => 'advanced'],
            'margin_right' => ['type' => 'string', 'title' => 'Margin Right', 'default' => '', 'control_group' => 'advanced'],
            'margin_bottom' => ['type' => 'string', 'title' => 'Margin Bottom', 'default' => '', 'control_group' => 'advanced'],
            'margin_left' => ['type' => 'string', 'title' => 'Margin Left', 'default' => '', 'control_group' => 'advanced'],
        ];
    }

    /**
     * Advanced tab: z-index and custom CSS class (spacing is in getGlobalAdvancedSpacingProperties).
     *
     * @return array<string, array<string, mixed>>
     */
    private function getGlobalAdvancedNonSpacingProperties(): array
    {
        return [
            'z_index' => ['type' => 'integer', 'title' => 'Z-Index', 'default' => null, 'control_group' => 'advanced'],
            'custom_class' => ['type' => 'string', 'title' => 'Custom CSS class', 'default' => '', 'control_group' => 'advanced'],
        ];
    }

    /**
     * Build a canonical schema_json from a control-definition (content / style / advanced with responsive).
     * Content fields are flattened to top-level properties so storage stays flat (e.g. props.headline).
     * Style desktop/tablet/mobile are mapped to responsive.* so they match the builder's responsive overrides.
     * Every component gets global advanced spacing (padding/margin per side, z_index, custom_class) for Elementor-style controls.
     *
     * @param  array<string, mixed>  $def
     * @return array<string, mixed>
     */
    private function buildCanonicalSchemaFromControlDefinition(array $def): array
    {
        $content = isset($def['content']) && is_array($def['content']) ? $def['content'] : [];
        $layout = isset($def['layout']) && is_array($def['layout']) ? $def['layout'] : [];
        $style = isset($def['style']) && is_array($def['style']) ? $def['style'] : [];
        $advanced = isset($def['advanced']) && is_array($def['advanced']) ? $def['advanced'] : [];

        $properties = [];

        if ($content !== []) {
            foreach ($this->normalizeControlDefinitionProperties($content) as $key => $prop) {
                $properties[$key] = $prop;
            }
        }

        if ($layout !== []) {
            $properties['layout'] = [
                'type' => 'object',
                'properties' => $this->normalizeControlDefinitionProperties($layout),
            ];
        }

        $spacingProps = $this->getGlobalAdvancedSpacingProperties();
        $advancedOnlyProps = $this->getGlobalAdvancedNonSpacingProperties();

        $styleDesktop = isset($style['desktop']) && is_array($style['desktop']) ? $this->normalizeControlDefinitionProperties($style['desktop']) : [];
        $styleTablet = isset($style['tablet']) && is_array($style['tablet']) ? $this->normalizeControlDefinitionProperties($style['tablet']) : [];
        $styleMobile = isset($style['mobile']) && is_array($style['mobile']) ? $this->normalizeControlDefinitionProperties($style['mobile']) : [];
        $styleDesktop = array_merge($spacingProps, $styleDesktop);
        $styleTablet = array_merge($spacingProps, $styleTablet);
        $styleMobile = array_merge($spacingProps, $styleMobile);

        $properties['responsive'] = [
            'type' => 'object',
            'properties' => [
                'desktop' => ['type' => 'object', 'properties' => $styleDesktop],
                'tablet' => ['type' => 'object', 'properties' => $styleTablet],
                'mobile' => ['type' => 'object', 'properties' => $styleMobile],
            ],
        ];

        $advancedMerged = array_merge($spacingProps, $advancedOnlyProps, $this->normalizeControlDefinitionProperties($advanced));
        $properties['advanced'] = [
            'type' => 'object',
            'properties' => $advancedMerged,
        ];

        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * Normalize control-definition field map to schema property definitions (each value has type/title).
     *
     * @param  array<string, mixed>  $props
     * @return array<string, array<string, mixed>>
     */
    private function normalizeControlDefinitionProperties(array $props): array
    {
        $out = [];
        foreach ($props as $key => $value) {
            if (is_array($value) && isset($value['type'])) {
                $out[$key] = $value;
            } else {
                $out[$key] = [
                    'type' => is_string($value) ? $value : 'string',
                    'title' => ucfirst(str_replace('_', ' ', $key)),
                ];
            }
        }

        return $out;
    }

    /**
     * Merge control-definitions into section library so each section's schema_json is driven by its definition when present.
     *
     * @param  array<int, array<string, mixed>>  $sectionLibrary
     * @param  array<string, array<string, mixed>>  $controlDefinitions
     * @return array<int, array<string, mixed>>
     */
    private function mergeControlDefinitionsIntoSectionLibrary(array $sectionLibrary, array $controlDefinitions): array
    {
        if ($controlDefinitions === []) {
            return $sectionLibrary;
        }

        return array_map(function (array $item): array {
            $key = $item['key'] ?? '';
            if (! is_string($key) || $key === '') {
                return $item;
            }
            $def = $controlDefinitions[$key] ?? $controlDefinitions[trim(strtolower($key))] ?? null;
            if (! is_array($def)) {
                return $item;
            }
            $canonical = $this->buildCanonicalSchemaFromControlDefinition($def);
            $existing = is_array($item['schema_json'] ?? null) ? $item['schema_json'] : [];
            $mergedProperties = array_merge(
                isset($existing['properties']) && is_array($existing['properties']) ? $existing['properties'] : [],
                isset($canonical['properties']) && is_array($canonical['properties']) ? $canonical['properties'] : []
            );
            $item['schema_json'] = array_merge($existing, [
                'type' => 'object',
                'properties' => $mergedProperties,
            ]);

            return $item;
        }, $sectionLibrary);
    }

    /**
     * Build section library from the same source as admin component library
     * (SectionLibrary DB + canonical Webu config + synthetic entries).
     * Builder shows only and exactly the components that appear in /admin/component-library; same design, same list.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSectionLibraryFromComponentLibrary(): array
    {
        $sectionLibrary = array_map(static function (array $item): array {
            return [
                'id' => $item['id'],
                'key' => $item['key'],
                'category' => $item['category'],
                'schema_json' => $item['schema_json'],
                'label' => $item['label'],
                'description' => $item['description'],
                'design_variant' => null,
                'enabled' => (bool) ($item['enabled'] ?? true),
            ];
        }, $this->componentLibraryCatalogService->buildCatalog());

        $existingKeys = array_fill_keys(array_column($sectionLibrary, 'key'), true);
        $syntheticId = $this->nextSyntheticId($sectionLibrary);
        $layoutPrimitiveKeys = config('webu-builder-components.layout_primitive_keys', ['container', 'grid', 'section']);
        $layoutPrimitivesConfig = config('layout-primitives', []);
        foreach ($layoutPrimitiveKeys as $layoutKey) {
            if (isset($existingKeys[$layoutKey])) {
                continue;
            }
            $config = is_array($layoutPrimitivesConfig) ? ($layoutPrimitivesConfig['section_keys'][$layoutKey] ?? $layoutPrimitivesConfig[$layoutKey] ?? null) : null;
            $label = is_array($config) ? ($config['label'] ?? ucfirst($layoutKey)) : ucfirst($layoutKey);
            $description = is_array($config) ? ($config['description'] ?? '') : '';
            $schemaJson = $this->buildLayoutPrimitiveSchemaJson($layoutKey, $label, $description, is_array($layoutPrimitivesConfig) ? $layoutPrimitivesConfig : []);
            $sectionLibrary[] = [
                'id' => $syntheticId--,
                'key' => $layoutKey,
                'category' => 'layout',
                'schema_json' => $schemaJson,
                'label' => $label,
                'description' => $description,
                'design_variant' => null,
                'enabled' => true,
            ];
            $existingKeys[$layoutKey] = true;
        }

        return $sectionLibrary;
    }

    /**
     * Add workspace-backed reusable sections so the visual builder can insert generated project components.
     *
     * @param  array<int, array<string, mixed>>  $sectionLibrary
     * @return array<int, array<string, mixed>>
     */
    private function mergeWorkspaceSectionsIntoSectionLibrary(array $sectionLibrary, Project $project): array
    {
        $existingKeys = array_fill_keys(array_column($sectionLibrary, 'key'), true);
        $syntheticId = $this->nextSyntheticId($sectionLibrary);

        foreach ($this->workspaceSectionRegistry->builderItems($project) as $workspaceSection) {
            $key = trim((string) ($workspaceSection['key'] ?? ''));
            if ($key === '' || isset($existingKeys[$key])) {
                continue;
            }

            $label = trim((string) ($workspaceSection['label'] ?? $key));
            $path = trim((string) ($workspaceSection['path'] ?? ''));
            $schemaJson = is_array($workspaceSection['schema_json'] ?? null)
                ? $workspaceSection['schema_json']
                : [
                    'type' => 'object',
                    'properties' => [],
                ];
            $schemaMeta = is_array($schemaJson['_meta'] ?? null) ? $schemaJson['_meta'] : [];
            $schemaJson['_meta'] = array_merge($schemaMeta, [
                'label' => $label !== '' ? $label : $key,
                'description' => 'Project workspace section',
                'workspace_path' => $path !== '' ? $path : null,
                'source' => 'workspace',
            ]);

            $sectionLibrary[] = [
                'id' => $syntheticId--,
                'key' => $key,
                'category' => 'workspace',
                'schema_json' => $schemaJson,
                'label' => $label !== '' ? $label : $key,
                'description' => 'Project workspace section',
                'design_variant' => null,
                'enabled' => true,
            ];
            $existingKeys[$key] = true;
        }

        return $sectionLibrary;
    }

    /**
     * Build section library from webu folder config only (legacy; used when SectionLibrary is not the source of truth).
     *
     * @param  array<int, string>  $templateSectionKeys
     * @return array<int, array<string, mixed>>
     */
    private function buildSectionLibraryFromWebuConfig(array $templateSectionKeys): array
    {
        $webuComponents = config('webu-builder-components.components', []);
        $layoutPrimitiveKeys = config('webu-builder-components.layout_primitive_keys', ['container', 'grid', 'section']);
        $syntheticId = -1;
        $sectionLibrary = [];

        foreach ($webuComponents as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $key = trim((string) ($entry['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            if ($templateSectionKeys !== [] && ! in_array($key, $templateSectionKeys, true)) {
                continue;
            }
            $label = $entry['label'] ?? $key;
            $description = $entry['description'] ?? null;
            $category = $entry['category'] ?? 'general';
            $sectionLibrary[] = [
                'id' => $syntheticId--,
                'key' => $key,
                'category' => $category,
                'schema_json' => [
                    'type' => 'object',
                    'properties' => [],
                    '_meta' => [
                        'label' => $label,
                        'description' => $description,
                        'webu_folder' => $entry['folder'] ?? null,
                    ],
                ],
                'label' => $label,
                'description' => $description,
                'design_variant' => null,
                'enabled' => true,
            ];
        }

        $layoutPrimitivesConfig = config('layout-primitives', []);
        $existingKeys = array_column($sectionLibrary, 'key');
        foreach ($layoutPrimitiveKeys as $layoutKey) {
            if (in_array($layoutKey, $existingKeys, true)) {
                continue;
            }
            $config = is_array($layoutPrimitivesConfig) ? ($layoutPrimitivesConfig['section_keys'][$layoutKey] ?? $layoutPrimitivesConfig[$layoutKey] ?? null) : null;
            $label = is_array($config) ? ($config['label'] ?? ucfirst($layoutKey)) : ucfirst($layoutKey);
            $description = is_array($config) ? ($config['description'] ?? '') : '';
            $schemaJson = $this->buildLayoutPrimitiveSchemaJson($layoutKey, $label, $description, is_array($layoutPrimitivesConfig) ? $layoutPrimitivesConfig : []);
            $sectionLibrary[] = [
                'id' => $syntheticId--,
                'key' => $layoutKey,
                'category' => 'layout',
                'schema_json' => $schemaJson,
                'label' => $label,
                'description' => $description,
                'design_variant' => null,
                'enabled' => true,
            ];
        }

        return $sectionLibrary;
    }

    /**
     * Add synthetic section library entries for control-definition keys not already in the library.
     * Ensures the frontend receives the canonical schema for hero, product_grid, header, etc. as the single source of truth.
     *
     * @param  array<int, array<string, mixed>>  $sectionLibrary
     * @param  array<string, array<string, mixed>>  $controlDefinitions
     * @return array<int, array<string, mixed>>
     */
    private function injectSectionLibraryEntriesFromControlDefinitions(array $sectionLibrary, array $controlDefinitions): array
    {
        $existingKeys = array_fill_keys(array_column($sectionLibrary, 'key'), true);
        $syntheticId = $this->nextSyntheticId($sectionLibrary);
        foreach ($controlDefinitions as $sectionKey => $def) {
            if (! is_string($sectionKey) || trim($sectionKey) === '') {
                continue;
            }
            $key = trim($sectionKey);
            if (isset($existingKeys[$key])) {
                continue;
            }
            $label = $def['label'] ?? $key;
            $canonical = $this->buildCanonicalSchemaFromControlDefinition($def);
            $sectionLibrary[] = [
                'id' => $syntheticId--,
                'key' => $key,
                'category' => 'general',
                'schema_json' => $canonical,
                'label' => is_string($label) ? $label : $key,
                'description' => $def['description'] ?? null,
                'design_variant' => null,
                'enabled' => true,
            ];
            $existingKeys[$key] = true;
        }

        return $sectionLibrary;
    }

    /**
     * Find the next available negative synthetic id for builder-only entries.
     *
     * @param  array<int, array<string, mixed>>  $sectionLibrary
     */
    private function nextSyntheticId(array $sectionLibrary): int
    {
        $numericIds = [];

        foreach ($sectionLibrary as $item) {
            $id = $item['id'] ?? null;

            if (is_int($id)) {
                $numericIds[] = $id;
                continue;
            }

            if (is_string($id) && preg_match('/^-?\d+$/', $id) === 1) {
                $numericIds[] = (int) $id;
            }
        }

        if ($numericIds === []) {
            return -1;
        }

        $minId = min($numericIds);

        return $minId <= 0 ? $minId - 1 : -1;
    }

    /**
     * Build JSON Schema for a layout primitive so the builder panel shows properties with dropdowns.
     *
     * @param  array<string, mixed>  $layoutPrimitivesConfig
     * @return array<string, mixed>
     */
    private function buildLayoutPrimitiveSchemaJson(string $layoutKey, string $label, string $description, array $layoutPrimitivesConfig): array
    {
        $spacingKeys = array_keys($layoutPrimitivesConfig['spacing_scale'] ?? ['xs' => 8, 'sm' => 12, 'md' => 20, 'lg' => 32, 'xl' => 48, '2xl' => 72]);
        $spaceTokens = array_map(fn ($k) => 'space-'.$k, $spacingKeys);

        $properties = [];
        $defaults = [];

        if ($layoutKey === 'container') {
            $maxWidths = $layoutPrimitivesConfig['container']['max_widths'] ?? ['sm' => 640, 'md' => 768, 'lg' => 1024, 'xl' => 1280, '2xl' => 1400];
            $maxWidthKeys = array_keys($maxWidths);
            $properties['max_width'] = [
                'type' => 'string',
                'title' => 'Max width',
                'enum' => $maxWidthKeys,
                'default' => 'xl',
                'control_meta' => ['group' => 'content'],
            ];
            $properties['padding_token'] = [
                'type' => 'string',
                'title' => 'Padding',
                'enum' => $spaceTokens,
                'default' => 'space-md',
                'control_meta' => ['group' => 'content'],
            ];
            $properties['sections'] = [
                'type' => 'array',
                'title' => 'Nested sections',
                'description' => 'Optional child sections rendered inside this container.',
                'control_meta' => ['group' => 'advanced'],
            ];
            $defaults = ['max_width' => 'xl', 'padding_token' => 'space-md'];
        } elseif ($layoutKey === 'grid') {
            $properties['columns_desktop'] = [
                'type' => 'integer',
                'title' => 'Columns (desktop)',
                'default' => 12,
                'control_meta' => ['group' => 'content'],
            ];
            $properties['gap_token'] = [
                'type' => 'string',
                'title' => 'Gap',
                'enum' => $spaceTokens,
                'default' => 'space-md',
                'control_meta' => ['group' => 'content'],
            ];
            $properties['sections'] = [
                'type' => 'array',
                'title' => 'Nested sections',
                'description' => 'Optional child sections rendered inside this grid.',
                'control_meta' => ['group' => 'advanced'],
            ];
            $defaults = ['columns_desktop' => 12, 'gap_token' => 'space-md'];
        } elseif ($layoutKey === 'section') {
            $properties['vertical_rhythm_token'] = [
                'type' => 'string',
                'title' => 'Vertical rhythm',
                'enum' => $spaceTokens,
                'default' => 'space-lg',
                'control_meta' => ['group' => 'content'],
            ];
            $variants = $layoutPrimitivesConfig['section']['background_variants'] ?? ['default', 'muted', 'primary_soft', 'transparent'];
            $properties['background_variant'] = [
                'type' => 'string',
                'title' => 'Background',
                'enum' => $variants,
                'default' => 'default',
                'control_meta' => ['group' => 'content'],
            ];
            $properties['sections'] = [
                'type' => 'array',
                'title' => 'Nested sections',
                'description' => 'Optional child sections rendered inside this section.',
                'control_meta' => ['group' => 'advanced'],
            ];
            $defaults = ['vertical_rhythm_token' => 'space-lg', 'background_variant' => 'default'];
        }

        return [
            'properties' => $properties,
            '_meta' => [
                'label' => $label,
                'description' => $description,
            ],
            '_defaults' => $defaults,
        ];
    }

    /**
     * Return the builder component registry for AI and builder UI.
     * GET /panel/projects/{project}/builder/component-registry
     */
    public function componentRegistry(Project $project): \Illuminate\Http\JsonResponse
    {
        $this->authorize('update', $project);

        $componentIds = config('builder-component-registry.component_ids', []);
        $categoryOrder = config('builder-component-registry.category_order', []);
        $workspaceSections = $this->workspaceSectionRegistry->builderItems($project);

        return response()->json([
            'components' => array_values($componentIds),
            'categories' => $categoryOrder,
            'workspace_sections' => $workspaceSections,
            'registry' => [
                'component_ids' => $componentIds,
                'category_order' => $categoryOrder,
                'workspace_sections' => $workspaceSections,
            ],
        ]);
    }
}
