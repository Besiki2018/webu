<?php

namespace App\Services\AiWebsiteGeneration;

use App\Cms\Contracts\CmsRepositoryContract;
use App\Models\User;
use App\Models\Project;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\Website;
use App\Models\WebsitePage;
use App\Models\WebsiteSeo;
use App\Models\WebsiteRevision;
use App\Models\PageSection;
use App\Models\ProjectGenerationRun;
use App\Services\Assets\ImageImportService;
use App\Services\Assets\ImageSearchService;
use App\Services\CmsSectionBindingService;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use App\Services\WebuCodex\CodebaseScanner;
use App\Support\CmsSectionLocalId;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ultimate AI generation engine: CMS-first, single flow.
 * Prompt-based generation now resolves the home page through the builder blueprint pipeline
 * and only uses legacy CMS scaffolding for supplemental pages.
 */
class GenerateWebsiteProjectService
{
    private const PAGE_BINDING_ROOT_KEY = 'webu_cms_binding';

    public function __construct(
        protected WebsiteBriefExtractor $briefExtractor,
        protected StructureGenerator $structureGenerator,
        protected ThemeGenerator $themeGenerator,
        protected ContentGenerator $contentGenerator,
        protected SeoGenerator $seoGenerator,
        protected BuilderBlueprintGenerationService $builderBlueprintGeneration,
        protected UltraCheapCopyBank $copyBank,
        protected CmsRepositoryContract $repository,
        protected CmsSectionBindingService $sectionBindings,
        protected ProjectWorkspaceService $projectWorkspace,
        protected CodebaseScanner $codebaseScanner,
        protected GeneratedSectionImagePlanner $generatedSectionImagePlanner,
        protected ImageSearchService $stockImageSearch,
        protected ImageImportService $stockImageImport,
        protected UltraCheapThemePreset $themePresetResolver
    ) {}

    /**
     * @param  array{userPrompt: string, language?: string, style?: string, websiteType?: string, brandName?: string, currency?: string, user_id?: int, ultra_cheap_mode?: bool}  $input
     * @return array{website: Website, project: Project, site: Site, pages: array}
     */
    public function generate(array $input): array
    {
        $userPrompt = (string) ($input['userPrompt'] ?? '');
        $userId = (int) ($input['user_id'] ?? 0);
        if ($userId < 1) {
            throw new \InvalidArgumentException('user_id required.');
        }

        $project = $this->createProjectShell($userId, $userPrompt);

        return $this->generateIntoProject($project, $input);
    }

    public function createProjectShell(int $userId, string $userPrompt): Project
    {
        $prompt = trim($userPrompt);

        return Project::query()->create([
            'tenant_id' => $this->resolveTenantIdForUser($userId),
            'user_id' => $userId,
            'name' => str($prompt)->limit(50, '...')->toString(),
            'initial_prompt' => $prompt,
            'last_viewed_at' => now(),
        ]);
    }

    /**
     * @param  array{userPrompt: string, language?: string|null, style?: string|null, websiteType?: string|null, brandName?: string|null, currency?: string|null, generationRunId?: string|null, user_id?: int, ultra_cheap_mode?: bool}  $input
     * @return array{website: Website, project: Project, site: Site, pages: array}
     */
    public function generateIntoProject(Project $project, array $input, ?callable $progress = null): array
    {
        $userPrompt = trim((string) ($input['userPrompt'] ?? ''));
        $userId = (int) ($input['user_id'] ?? $project->user_id ?? 0);
        $ultraCheapMode = (bool) ($input['ultra_cheap_mode'] ?? true);
        if ($userId < 1) {
            throw new \InvalidArgumentException('user_id required.');
        }

        $tenantId = (string) ($project->tenant_id ?: $this->resolveTenantIdForUser($userId));

        $this->reportProgress($progress, ProjectGenerationRun::STATUS_ANALYZING_PROMPT, 'Analyzing your prompt.');

        $brief = $this->resolveBrief($userPrompt, $input);
        $language = $input['language'] ?? $brief['language'];
        $style = $input['style'] ?? $brief['style'];
        $websiteType = $input['websiteType'] ?? $brief['websiteType'];
        $brandName = $input['brandName'] ?? $brief['brandName'];

        $brief['language'] = $language;
        $brief['style'] = $style;
        $brief['websiteType'] = $websiteType;
        $brief['brandName'] = $brandName;
        $brief['sourcePrompt'] = $userPrompt;

        $project->forceFill([
            'tenant_id' => $tenantId,
            'name' => $brandName,
            'initial_prompt' => $project->initial_prompt ?: $userPrompt,
            'last_viewed_at' => now(),
        ])->save();

        $this->reportProgress($progress, ProjectGenerationRun::STATUS_PLANNING_STRUCTURE, 'Planning the site structure.');
        $builderGeneration = $this->generatePagesPlanFromBuilderBlueprint($userPrompt, $brief, $input);
        $pagesPlan = $builderGeneration['pages'];

        if (isset($builderGeneration['project_type']) && is_string($builderGeneration['project_type']) && trim((string) $builderGeneration['project_type']) !== '') {
            $project->forceFill([
                'type' => trim((string) $builderGeneration['project_type']),
            ])->save();
        }

        $this->reportProgress($progress, ProjectGenerationRun::STATUS_SELECTING_COMPONENTS, 'Selecting sections and components.');

        $this->reportProgress($progress, ProjectGenerationRun::STATUS_GENERATING_CONTENT, 'Generating content for each section.');
        $contentPatches = $this->buildContentPatchesForPagesPlan($brief, $pagesPlan, $ultraCheapMode);

        $theme = $this->themeGenerator->generate($brief);
        $resolvedThemePreset = $this->resolveThemePresetId($brief, null);
        if ($resolvedThemePreset !== null) {
            $brief['themePreset'] = $resolvedThemePreset;
            $theme['preset'] = $resolvedThemePreset;
            $project->forceFill(['theme_preset' => $resolvedThemePreset])->save();
        }
        $seoPlan = $this->seoGenerator->generate($brief, $pagesPlan, $language === 'both' ? 'ka' : $language);

        $this->reportProgress($progress, ProjectGenerationRun::STATUS_ASSEMBLING_PAGE, 'Assembling the page tree and writing files.');

        $result = DB::transaction(function () use ($project, $userId, $tenantId, $brief, $pagesPlan, $contentPatches, $theme, $seoPlan, $builderGeneration) {
            $site = $this->prepareSiteForGeneration($project, $brief, $theme);
            $stockImagePatches = $this->resolveGeneratedSectionStockImages(
                $project,
                $site,
                $brief,
                $pagesPlan,
                $contentPatches,
                $userId
            );

            $this->resetGeneratedContent($site);

            $website = Website::create([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'name' => $brief['brandName'],
                'domain' => null,
                'theme' => $theme,
                'site_id' => $site->id,
            ]);

            $websitePages = [];
            foreach ($pagesPlan as $pageIndex => $plan) {
                $slug = (string) $plan['slug'];
                $title = (string) $plan['title'];

                $websitePage = WebsitePage::create([
                    'website_id' => $website->id,
                    'tenant_id' => $tenantId,
                    'slug' => $slug,
                    'title' => $title,
                    'order' => $pageIndex,
                    'page_id' => null,
                ]);

                $contentJson = ['sections' => []];

                foreach ($plan['sections'] ?? [] as $secOrder => $sec) {
                    $content = is_array($sec['content_json'] ?? null) ? $sec['content_json'] : [];
                    $patch = is_array($contentPatches[$pageIndex][$secOrder] ?? null) ? $contentPatches[$pageIndex][$secOrder] : [];
                    $settings = array_replace_recursive($content, $patch);
                    $localId = CmsSectionLocalId::fallbackForIndex((int) $secOrder);
                    $stockImagePatch = is_array($stockImagePatches[$pageIndex][$secOrder] ?? null)
                        ? $stockImagePatches[$pageIndex][$secOrder]
                        : [];
                    $settings = $this->applyGeneratedStockImagePatch(
                        $settings,
                        $stockImagePatch
                    );

                    PageSection::create([
                        'page_id' => $websitePage->id,
                        'tenant_id' => $tenantId,
                        'website_id' => $website->id,
                        'section_type' => $sec['section_type'],
                        'order' => $secOrder,
                        'settings_json' => $settings,
                    ]);

                    $sectionPayload = $this->sectionBindings->buildSectionPayload((string) $sec['section_type'], $settings);
                    $sectionPayload['binding'] = array_replace_recursive(
                        is_array($sectionPayload['binding'] ?? null) ? $sectionPayload['binding'] : [],
                        $this->buildGeneratedSectionAuthorityBinding(
                            $project,
                            $slug,
                            $sec,
                            $localId,
                            $settings,
                            $userId,
                            $stockImagePatch
                        )
                    );
                    $sectionPayload['localId'] = $localId;
                    $contentJson['sections'][] = $sectionPayload;
                }

                $contentJson[self::PAGE_BINDING_ROOT_KEY] = $this->buildGeneratedPageAuthorityPayload(
                    $project,
                    $slug,
                    $title,
                    $seoPlan[$pageIndex] ?? [],
                    is_array($contentJson['sections'] ?? null) ? $contentJson['sections'] : [],
                    $userId
                );

                WebsiteSeo::create([
                    'tenant_id' => $tenantId,
                    'website_id' => $website->id,
                    'website_page_id' => $websitePage->id,
                    'seo_title' => $seoPlan[$pageIndex]['seo_title'] ?? $title,
                    'meta_description' => $seoPlan[$pageIndex]['meta_description'] ?? '',
                    'og_title' => $seoPlan[$pageIndex]['og_title'] ?? null,
                    'og_image' => $seoPlan[$pageIndex]['og_image'] ?? null,
                    'locale' => $brief['language'] === 'en' ? 'en' : 'ka',
                ]);

                $page = $this->repository->createPage($site, [
                    'slug' => $slug,
                    'title' => $title,
                    'status' => 'draft',
                    'seo_title' => $seoPlan[$pageIndex]['seo_title'] ?? $title,
                    'seo_description' => $seoPlan[$pageIndex]['meta_description'] ?? '',
                ]);

                $this->repository->createRevision($site, $page, [
                    'version' => 1,
                    'content_json' => $contentJson,
                    'created_by' => $userId,
                    'published_at' => null,
                ]);

                $websitePage->update(['page_id' => $page->id]);
                $websitePages[] = $websitePage->fresh();
            }

            $this->syncMenusFromPagesPlan($site, $pagesPlan);
            $this->snapshotRevision($website->fresh(), 'ai', $userId);

            return [
                'website' => $website->fresh(),
                'project' => $project->fresh(),
                'site' => $site->fresh(),
                'pages' => $websitePages,
                'builder_generation' => [
                    'blueprint' => $builderGeneration['blueprint'] ?? [],
                    'project_type' => $builderGeneration['project_type'] ?? null,
                    'diagnostics' => $builderGeneration['diagnostics'] ?? null,
                    'generation_log' => $builderGeneration['generation_log'] ?? [],
                ],
            ];
        });

        $project = $result['project'];
        $this->projectWorkspace->initializeProjectCodebase($project, [
            'active_generation_run_id' => isset($input['generationRunId']) && is_string($input['generationRunId'])
                ? trim((string) $input['generationRunId'])
                : null,
            'phase' => ProjectGenerationRun::STATUS_ASSEMBLING_PAGE,
        ]);
        $scan = $this->codebaseScanner->scan($project);
        $this->codebaseScanner->writeIndex($project, $scan);
        $this->reportProgress($progress, ProjectGenerationRun::STATUS_RENDERING_PREVIEW, 'Rendering the preview and validating the workspace.');

        return $result;
    }

    /**
     * @param  array{brandName: string, mustHavePages: array<int, string>, websiteType: string}  $brief
     * @param  array{websiteType?: string|null}  $input
     * @return array{
     *   pages: array<int, array<string, mixed>>,
     *   blueprint: array<string, mixed>,
     *   project_type: string|null,
     *   diagnostics: array<string, mixed>|null,
     *   generation_log: array<int, array<string, mixed>>
     * }
     */
    private function generatePagesPlanFromBuilderBlueprint(string $userPrompt, array $brief, array $input): array
    {
        $builderGeneration = $this->builderBlueprintGeneration->generate($userPrompt, [
            'projectType' => isset($input['websiteType']) && is_string($input['websiteType']) && trim((string) $input['websiteType']) !== ''
                ? trim((string) $input['websiteType'])
                : null,
            'brandName' => $brief['brandName'] ?? null,
        ]);

        $generatedPages = $this->pagesPlanFromBuilderSitePlan($builderGeneration['sitePlan'] ?? []);
        if ($generatedPages === []) {
            throw new \RuntimeException('Builder blueprint generation returned no CMS pages.');
        }

        $supplementalPages = $this->structureGenerator->generate($brief)['pages'] ?? [];
        $pages = $this->mergeGeneratedPagesWithSupplementalScaffolding($generatedPages, $supplementalPages);

        return [
            'pages' => $pages,
            'blueprint' => is_array($builderGeneration['blueprint'] ?? null) ? $builderGeneration['blueprint'] : [],
            'project_type' => is_string($builderGeneration['projectType'] ?? null) ? (string) $builderGeneration['projectType'] : null,
            'diagnostics' => is_array($builderGeneration['diagnostics'] ?? null) ? $builderGeneration['diagnostics'] : null,
            'generation_log' => is_array($builderGeneration['generationLog'] ?? null) ? $builderGeneration['generationLog'] : [],
        ];
    }

    /**
     * Resolve brief (cached by prompt hash in Ultra Cheap Mode).
     *
     * @param  array{language?: string, style?: string, websiteType?: string, brandName?: string}  $input
     * @return array{websiteType: string, category: string, brandName: string, style: string, language: string, mustHavePages: array, primaryGoal: ?string, cta: ?string}
     */
    private function resolveBrief(string $userPrompt, array $input): array
    {
        $cacheKey = 'ultra_cheap.brief.'.md5($userPrompt);
        $ttl = (int) config('ultra_cheap.cache_ttl_seconds', 604800);

        return Cache::remember($cacheKey, $ttl, function () use ($userPrompt) {
            return $this->briefExtractor->extract($userPrompt);
        });
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<int, array{key: string, label: string, type: string}>
     */
    private function inferCmsFieldsFromTemplateSection(string $sectionType, array $props): array
    {
        $binding = $this->sectionBindings->resolveBinding($sectionType);
        $editableFields = array_values(array_filter(array_map(static function ($field): string {
            return is_string($field) ? trim($field) : '';
        }, is_array($binding['editable_fields'] ?? null) ? $binding['editable_fields'] : [])));
        $propFields = array_values(array_filter(array_map(static function ($field): string {
            return is_string($field) ? trim($field) : '';
        }, array_keys($props))));
        $fieldKeys = array_values(array_unique([...$editableFields, ...$propFields]));

        return array_values(array_map(static function (string $key): array {
            return [
                'key' => $key,
                'label' => Str::headline(str_replace(['.', '_', '-'], ' ', $key)),
                'type' => 'text',
            ];
        }, $fieldKeys));
    }

    /**
     * @param  array<string, mixed>  $sitePlan
     * @return array<int, array{slug: string, title: string, sections: array<int, array<string, mixed>>}>
     */
    private function pagesPlanFromBuilderSitePlan(array $sitePlan): array
    {
        $pages = [];

        foreach (is_array($sitePlan['pages'] ?? null) ? $sitePlan['pages'] : [] as $pageIndex => $page) {
            if (! is_array($page)) {
                continue;
            }

            $pageName = is_string($page['name'] ?? null) ? trim((string) $page['name']) : '';
            $slug = $pageName !== '' ? Str::slug($pageName) : '';
            if ($slug === '') {
                $slug = $pageIndex === 0 ? 'home' : 'page-'.($pageIndex + 1);
            }

            $sections = [];
            foreach (is_array($page['sections'] ?? null) ? $page['sections'] : [] as $section) {
                if (! is_array($section)) {
                    continue;
                }

                $sectionType = is_string($section['componentKey'] ?? null) ? trim((string) $section['componentKey']) : '';
                if ($sectionType === '') {
                    continue;
                }

                $props = is_array($section['props'] ?? null) ? $section['props'] : [];
                if (! array_key_exists('variant', $props) && is_string($section['variant'] ?? null) && trim((string) $section['variant']) !== '') {
                    $props['variant'] = trim((string) $section['variant']);
                }

                $sections[] = [
                    'section_type' => $sectionType,
                    'cms_fields' => $this->inferCmsFieldsFromTemplateSection($sectionType, $props),
                    'content_json' => $props,
                    'style_json' => [],
                    'prefilled_content' => true,
                ];
            }

            if ($sections === []) {
                continue;
            }

            $pages[] = [
                'slug' => $slug,
                'title' => $slug === 'home' ? 'Home' : Str::headline(str_replace(['-', '_'], ' ', $slug)),
                'sections' => $sections,
            ];
        }

        return $pages;
    }

    /**
     * @param  array<int, array{slug: string, title: string, sections: array<int, array<string, mixed>>}>  $generatedPages
     * @param  array<int, array{slug: string, title: string, sections: array<int, array<string, mixed>>}>  $supplementalPages
     * @return array<int, array{slug: string, title: string, sections: array<int, array<string, mixed>>}>
     */
    private function mergeGeneratedPagesWithSupplementalScaffolding(array $generatedPages, array $supplementalPages): array
    {
        if ($generatedPages === []) {
            return $supplementalPages;
        }

        $generatedBySlug = [];
        foreach ($generatedPages as $pageIndex => $page) {
            $slug = trim((string) ($page['slug'] ?? ''));
            if ($slug === '' && $pageIndex === 0) {
                $slug = 'home';
                $page['slug'] = 'home';
                $page['title'] = 'Home';
            }

            if ($slug === '') {
                continue;
            }

            $generatedBySlug[$slug] = $page;
        }

        if (! array_key_exists('home', $generatedBySlug)) {
            $firstGeneratedPage = array_shift($generatedBySlug);
            if (is_array($firstGeneratedPage)) {
                $firstGeneratedPage['slug'] = 'home';
                $firstGeneratedPage['title'] = 'Home';
                $generatedBySlug = ['home' => $firstGeneratedPage, ...$generatedBySlug];
            }
        }

        $pages = [];
        foreach ($supplementalPages as $page) {
            $slug = trim((string) ($page['slug'] ?? ''));
            if ($slug !== '' && array_key_exists($slug, $generatedBySlug)) {
                $pages[] = $generatedBySlug[$slug];
                unset($generatedBySlug[$slug]);
                continue;
            }

            $pages[] = $page;
        }

        foreach ($generatedBySlug as $page) {
            $pages[] = $page;
        }

        return $pages;
    }

    /**
     * @param  array{theme_preset?: string|null}|null  $generationBlueprint
     */
    private function resolveThemePresetId(array $brief, ?array $generationBlueprint): ?string
    {
        $templatePreset = is_array($generationBlueprint)
            ? $this->normalizeThemePreset((string) ($generationBlueprint['theme_preset'] ?? ''))
            : null;
        if ($templatePreset !== null) {
            return $templatePreset;
        }

        $category = trim((string) ($brief['category'] ?? 'general'));
        $style = trim((string) ($brief['style'] ?? 'modern'));
        $themePreset = $this->themePresetResolver->resolve($category !== '' ? $category : 'general', $style !== '' ? $style : 'modern');

        return $this->normalizeThemePreset((string) ($themePreset['preset_id'] ?? ''));
    }

    private function normalizeThemePreset(?string $preset): ?string
    {
        $normalized = trim(Str::lower((string) $preset));
        if ($normalized === '') {
            return null;
        }

        $catalog = config('theme-presets', []);
        if (! is_array($catalog) || ! array_key_exists($normalized, $catalog)) {
            return null;
        }

        return $normalized;
    }

    /**
     * Build content patches from copy bank (no AI).
     */
    private function buildContentPatchesForPagesPlan(array $brief, array $pagesPlan, bool $ultraCheapMode): array
    {
        $patches = $ultraCheapMode
            ? $this->contentPatchesFromCopyBank($brief, $pagesPlan)
            : $this->contentGenerator->generate($brief, $pagesPlan);

        foreach ($pagesPlan as $pageIndex => $page) {
            foreach (($page['sections'] ?? []) as $sectionIndex => $section) {
                if (($section['prefilled_content'] ?? false) === true) {
                    $patches[$pageIndex][$sectionIndex] = [];
                }
            }
        }

        return $patches;
    }

    private function contentPatchesFromCopyBank(array $brief, array $pagesPlan): array
    {
        $category = $brief['category'] ?? 'general';
        $lang = $brief['language'] === 'en' ? 'en' : 'ka';
        if ($brief['language'] === 'both') {
            $lang = 'ka';
        }
        $copy = $this->copyBank->getForCategoryAndLang($category, $lang);
        $brandName = $brief['brandName'] ?? 'My Website';
        $out = [];
        foreach ($pagesPlan as $pageIndex => $page) {
            $out[$pageIndex] = [];
            foreach ($page['sections'] ?? [] as $secIndex => $section) {
                $type = $section['section_type'] ?? 'content';
                $defaultContent = $section['content_json'] ?? [];
                $out[$pageIndex][$secIndex] = $this->copyBank->fillSectionContent(
                    $type,
                    $defaultContent,
                    $copy,
                    $brandName
                );
            }
        }

        return $out;
    }

    private function reportProgress(?callable $progress, string $status, string $message): void
    {
        if ($progress === null) {
            return;
        }

        $progress($status, $message);
    }

    private function snapshotRevision(Website $website, string $changeType, ?int $createdBy = null): void
    {
        $maxVersion = (int) $website->revisions()->max('version');
        $snapshot = [
            'website_id' => $website->id,
            'pages' => $website->websitePages()->with('sections')->get()->map(fn ($p) => [
                'id' => $p->id,
                'slug' => $p->slug,
                'title' => $p->title,
                'order' => $p->order,
                'sections' => $p->sections->map(fn ($s) => [
                    'id' => $s->id,
                    'section_type' => $s->section_type,
                    'order' => $s->order,
                    'settings_json' => $s->settings_json,
                ])->all(),
            ])->all(),
        ];
        WebsiteRevision::create([
            'tenant_id' => $website->tenant_id,
            'website_id' => $website->id,
            'version' => $maxVersion + 1,
            'snapshot_json' => $snapshot,
            'change_type' => $changeType,
            'created_by' => $createdBy,
        ]);
    }

    /**
     * @param  array{brandName: string, language: string}  $brief
     * @param  array<string, mixed>  $theme
     */
    private function prepareSiteForGeneration(Project $project, array $brief, array $theme): Site
    {
        $attributes = [
            'name' => $brief['brandName'],
            'status' => 'draft',
            'locale' => $brief['language'] === 'en' ? 'en' : 'ka',
            'theme_settings' => $theme,
        ];

        $site = $this->repository->findSiteByProject($project);
        if ($site) {
            return $this->repository->updateSite($site, $attributes);
        }

        return $this->repository->createSiteForProject($project, $attributes);
    }

    private function resetGeneratedContent(Site $site): void
    {
        Website::query()
            ->where('site_id', $site->id)
            ->delete();

        PageRevision::query()
            ->where('site_id', $site->id)
            ->delete();

        Page::query()
            ->where('site_id', $site->id)
            ->delete();
    }

    /**
     * @param  array<int, array{slug: string, title: string}>  $pagesPlan
     */
    private function syncMenusFromPagesPlan(Site $site, array $pagesPlan): void
    {
        $headerItems = array_values(array_filter(array_map(static function (array $page): ?array {
            $slug = trim((string) ($page['slug'] ?? ''));
            $title = trim((string) ($page['title'] ?? ''));

            if ($slug === '' || $title === '') {
                return null;
            }

            return [
                'label' => $title,
                'slug' => $slug,
                'url' => $slug === 'home' ? '/' : '/'.$slug,
            ];
        }, $pagesPlan)));

        if ($headerItems === []) {
            $headerItems = [
                ['label' => 'Home', 'slug' => 'home', 'url' => '/'],
            ];
        }

        $this->repository->updateOrCreateMenu($site, 'header', ['items_json' => $headerItems]);
        $this->repository->updateOrCreateMenu($site, 'footer', [
            'items_json' => [
                ['label' => 'Privacy', 'url' => '/privacy'],
                ['label' => 'Terms', 'url' => '/terms'],
            ],
        ]);
    }

    /**
     * @param  array<string, mixed>  $sectionPlan
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function buildGeneratedSectionAuthorityBinding(
        Project $project,
        string $pageSlug,
        array $sectionPlan,
        string $localId,
        array $settings,
        int $userId,
        array $stockImagePatch = []
    ): array
    {
        $cmsFields = array_values(array_filter(array_map(static function ($field): string {
            if (! is_array($field)) {
                return '';
            }

            return is_string($field['key'] ?? null) ? trim((string) $field['key']) : '';
        }, is_array($sectionPlan['cms_fields'] ?? null) ? $sectionPlan['cms_fields'] : [])));
        $visualFields = array_values(array_filter(array_keys(is_array($sectionPlan['style_json'] ?? null) ? $sectionPlan['style_json'] : [])));
        $detectedMediaFields = $this->collectGeneratedMediaFieldPaths($settings);
        $generatedPatchFields = array_values(array_filter(array_keys($stockImagePatch), static fn ($key): bool => is_string($key) && trim($key) !== ''));
        $cmsFields = array_values(array_unique([...$cmsFields, ...$detectedMediaFields, ...$generatedPatchFields]));
        $staticDefaults = array_values(array_filter(array_keys($settings), static function ($key) use ($cmsFields, $visualFields): bool {
            return ! in_array($key, $cmsFields, true) && ! in_array($key, $visualFields, true);
        }));
        $timestamp = now()->toIso8601String();
        $mediaFields = [];

        foreach ($stockImagePatch as $path => $patch) {
            if (! is_string($path) || trim($path) === '' || ! is_array($patch)) {
                continue;
            }

            $assetUrl = trim((string) ($patch['asset_url'] ?? ''));
            if ($assetUrl === '') {
                continue;
            }

            $mediaFields[$path] = [
                'owner' => 'cms',
                'asset_url' => $assetUrl,
                'media_id' => isset($patch['media_id']) ? (string) $patch['media_id'] : null,
                'provider' => isset($patch['provider']) && is_string($patch['provider']) ? trim((string) $patch['provider']) : null,
                'provider_image_id' => isset($patch['provider_image_id']) && is_string($patch['provider_image_id']) ? trim((string) $patch['provider_image_id']) : null,
                'imported_by' => 'ai',
                'component' => is_string($sectionPlan['section_type'] ?? null) ? trim((string) $sectionPlan['section_type']) : null,
                'prop_path' => $path,
                'qualified_prop_path' => $path,
                'nested_section_path' => null,
                'project_id' => (string) $project->id,
                'section_local_id' => $localId,
                'page_slug' => $pageSlug,
                'source' => is_string($patch['source'] ?? null) ? trim((string) $patch['source']) : 'stock_image',
                'updated_at' => $timestamp,
                'query' => isset($patch['query']) && is_string($patch['query']) ? trim((string) $patch['query']) : null,
            ];
        }

        return [
            'webu_v2' => [
                'schema_version' => 1,
                'cms_backed' => true,
                'section_local_id' => $localId,
                'section_type' => is_string($sectionPlan['section_type'] ?? null) ? trim((string) $sectionPlan['section_type']) : 'section',
                'content_owner' => 'cms',
                'sync_direction' => 'cms_to_workspace',
                'conflict_status' => 'clean',
                'content_fields' => $cmsFields,
                'visual_fields' => $visualFields,
                'code_owned_fields' => [],
                'static_default_fields' => $staticDefaults,
                'media_fields' => $mediaFields,
                'provenance' => [
                    'created_by' => 'ai',
                    'last_editor' => 'ai',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                    'generated_default' => true,
                    'user_customized' => false,
                    'requires_manual_merge' => false,
                    'user_id' => $userId,
                ],
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $seo
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function buildGeneratedPageAuthorityPayload(
        Project $project,
        string $slug,
        string $title,
        array $seo,
        array $sections,
        int $userId
    ): array {
        return [
            'schema_version' => 1,
            'authorities' => [
                'content' => 'cms',
                'layout' => 'cms_revision',
                'code' => 'workspace',
                'preview' => 'derived',
            ],
            'page' => [
                'project_id' => (string) $project->id,
                'slug' => $slug,
                'title' => $title,
                'seo_title' => is_string($seo['seo_title'] ?? null) ? (string) $seo['seo_title'] : $title,
                'seo_description' => is_string($seo['meta_description'] ?? null) ? (string) $seo['meta_description'] : '',
                'content_owner' => 'mixed',
                'sync_direction' => 'cms_to_workspace',
                'conflict_status' => 'clean',
                'page_fields' => [
                    ['key' => 'page.title', 'owner' => 'cms', 'persistence_location' => 'pages.title'],
                    ['key' => 'page.slug', 'owner' => 'builder_structure', 'persistence_location' => 'pages.slug'],
                    ['key' => 'page.seo_title', 'owner' => 'cms', 'persistence_location' => 'pages.seo_title'],
                    ['key' => 'page.seo_description', 'owner' => 'cms', 'persistence_location' => 'pages.seo_description'],
                ],
            ],
            'sections' => array_values(array_map(static function ($section): array {
                $binding = is_array($section['binding']['webu_v2'] ?? null) ? $section['binding']['webu_v2'] : [];

                return [
                    'local_id' => is_string($section['localId'] ?? null) ? trim((string) $section['localId']) : null,
                    'type' => is_string($section['type'] ?? null) ? trim((string) $section['type']) : null,
                    'content_owner' => is_string($binding['content_owner'] ?? null) ? (string) $binding['content_owner'] : 'cms',
                    'content_fields' => is_array($binding['content_fields'] ?? null) ? $binding['content_fields'] : [],
                    'visual_fields' => is_array($binding['visual_fields'] ?? null) ? $binding['visual_fields'] : [],
                    'code_owned_fields' => is_array($binding['code_owned_fields'] ?? null) ? $binding['code_owned_fields'] : [],
                ];
            }, $sections)),
            'provenance' => [
                'created_by' => 'ai',
                'last_editor' => 'ai',
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                'generated_default' => true,
                'user_customized' => false,
                'requires_manual_merge' => false,
                'user_id' => $userId,
            ],
        ];
    }

    /**
     * Import stock images for image-capable sections before the CMS revision is created.
     *
     * @param  array<int, array{slug: string, title: string, sections: array<int, array<string, mixed>>}>  $pagesPlan
     * @param  array<int, array<int, array<string, mixed>>>  $contentPatches
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function resolveGeneratedSectionStockImages(
        Project $project,
        Site $site,
        array $brief,
        array $pagesPlan,
        array $contentPatches,
        int $userId
    ): array {
        $user = User::query()->find($userId);
        if (! $user) {
            return [];
        }

        $patches = [];

        foreach ($pagesPlan as $pageIndex => $pagePlan) {
            foreach (($pagePlan['sections'] ?? []) as $sectionIndex => $sectionPlan) {
                $settings = array_replace_recursive(
                    is_array($sectionPlan['content_json'] ?? null) ? $sectionPlan['content_json'] : [],
                    is_array($contentPatches[$pageIndex][$sectionIndex] ?? null) ? $contentPatches[$pageIndex][$sectionIndex] : []
                );

                $queryTargets = $this->generatedSectionImagePlanner->buildTargets(
                    $brief,
                    is_array($pagePlan) ? $pagePlan : [],
                    is_array($sectionPlan) ? $sectionPlan : [],
                    $settings
                );

                if ($queryTargets === []) {
                    continue;
                }

                foreach ($queryTargets as $target) {
                    $query = trim((string) ($target['query'] ?? ''));
                    $path = trim((string) ($target['path'] ?? ''));

                    if ($query === '' || $path === '') {
                        continue;
                    }

                    try {
                        $results = $this->stockImageSearch->search(
                            $query,
                            (int) ($target['provider_limit'] ?? 5),
                            ['orientation' => $target['orientation'] ?? null]
                        );
                    } catch (\Throwable) {
                        $patches[$pageIndex][$sectionIndex][$path] = [
                            'asset_url' => (string) ($target['fallback_url'] ?? ''),
                            'media_id' => null,
                            'provider' => null,
                            'provider_image_id' => null,
                            'query' => $query,
                            'source' => 'placeholder',
                        ];
                        continue;
                    }

                    if ($results === []) {
                        $patches[$pageIndex][$sectionIndex][$path] = [
                            'asset_url' => (string) ($target['fallback_url'] ?? ''),
                            'media_id' => null,
                            'provider' => null,
                            'provider_image_id' => null,
                            'query' => $query,
                            'source' => 'placeholder',
                        ];
                        continue;
                    }

                    $best = $results[0];
                    if (! is_array($best)) {
                        $patches[$pageIndex][$sectionIndex][$path] = [
                            'asset_url' => (string) ($target['fallback_url'] ?? ''),
                            'media_id' => null,
                            'provider' => null,
                            'provider_image_id' => null,
                            'query' => $query,
                            'source' => 'placeholder',
                        ];
                        continue;
                    }

                    try {
                        $media = $this->stockImageImport->import($project, $user, [
                            'provider' => (string) ($best['provider'] ?? ''),
                            'image_id' => (string) ($best['id'] ?? ''),
                            'download_url' => (string) ($best['download_url'] ?? $best['full_url'] ?? ''),
                            'title' => (string) ($best['title'] ?? ''),
                            'author' => (string) ($best['author'] ?? ''),
                            'license' => (string) ($best['license'] ?? ''),
                            'imported_by' => 'ai',
                            'section_local_id' => CmsSectionLocalId::fallbackForIndex((int) $sectionIndex),
                            'component_key' => is_string($sectionPlan['section_type'] ?? null) ? (string) $sectionPlan['section_type'] : null,
                            'prop_path' => $path,
                            'page_slug' => is_string($pagePlan['slug'] ?? null) ? (string) $pagePlan['slug'] : null,
                            'query' => $query,
                        ]);
                    } catch (\Throwable) {
                        $patches[$pageIndex][$sectionIndex][$path] = [
                            'asset_url' => (string) ($target['fallback_url'] ?? ''),
                            'media_id' => null,
                            'provider' => null,
                            'provider_image_id' => null,
                            'query' => $query,
                            'source' => 'placeholder',
                        ];
                        continue;
                    }

                    $patches[$pageIndex][$sectionIndex][$path] = [
                        'asset_url' => route('public.sites.assets', ['site' => $site->id, 'path' => $media->path]),
                        'media_id' => $media->id,
                        'provider' => $best['provider'] ?? null,
                        'provider_image_id' => $best['id'] ?? null,
                        'query' => $query,
                        'source' => 'stock_image',
                    ];
                }
            }
        }

        return $patches;
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    private function collectGeneratedMediaFieldPaths(array $settings, string $prefix = ''): array
    {
        $paths = [];

        foreach ($settings as $key => $value) {
            if (! is_string($key)) {
                continue;
            }

            $path = $prefix === '' ? $key : $prefix.'.'.$key;
            $normalized = trim(Str::lower($key));

            if ($normalized !== '' && (
                preg_match('/(^|_)(image|photo|picture|thumbnail|avatar|cover|backgroundimage|background_image|logo)(_|$)/', $normalized) === 1
                || preg_match('/^image_\d+_url$/', $normalized) === 1
            )) {
                $paths[] = $path;
            }

            if (is_array($value)) {
                if (array_is_list($value)) {
                    foreach ($value as $index => $item) {
                        if (is_array($item)) {
                            $paths = [...$paths, ...$this->collectGeneratedMediaFieldPaths($item, $path.'.'.$index)];
                        }
                    }
                } else {
                    $paths = [...$paths, ...$this->collectGeneratedMediaFieldPaths($value, $path)];
                }
            }
        }

        return array_values(array_unique($paths));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @param  array<string, mixed>  $patch
     * @return array<string, mixed>
     */
    private function applyGeneratedStockImagePatch(array $settings, array $patch): array
    {
        $next = $settings;

        foreach ($patch as $path => $value) {
            if (! is_string($path) || $path === '' || ! is_array($value)) {
                continue;
            }

            $assetUrl = trim((string) ($value['asset_url'] ?? ''));
            if ($assetUrl === '') {
                continue;
            }

            Arr::set($next, $path, $assetUrl);
        }

        return $next;
    }

    private function resolveTenantIdForUser(int $userId): string
    {
        $tenant = Tenant::query()
            ->where('owner_user_id', $userId)
            ->orWhere('created_by_user_id', $userId)
            ->first();

        if ($tenant) {
            return (string) $tenant->id;
        }

        $baseSlug = 'user-'.$userId.'-'.substr((string) Str::uuid(), 0, 8);
        $slug = $baseSlug;
        $suffix = 1;
        while (Tenant::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        $tenant = Tenant::query()->create([
            'name' => "User {$userId}",
            'slug' => $slug,
            'status' => 'active',
            'owner_user_id' => $userId,
            'created_by_user_id' => $userId,
        ]);

        return (string) $tenant->id;
    }
}
