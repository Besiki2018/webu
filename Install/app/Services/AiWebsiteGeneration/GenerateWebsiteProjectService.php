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
 * When ultra_cheap_mode: templates + copy bank + presets only (no main model).
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
        protected UltraCheapCopyBank $copyBank,
        protected UltraCheapTemplateMatrix $templateMatrix,
        protected CmsRepositoryContract $repository,
        protected CmsSectionBindingService $sectionBindings,
        protected ProjectWorkspaceService $projectWorkspace,
        protected CodebaseScanner $codebaseScanner,
        protected ImageSearchService $stockImageSearch,
        protected ImageImportService $stockImageImport
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

        $this->reportProgress($progress, 'planning', 'Understanding your website brief.');

        $brief = $this->resolveBrief($userPrompt, $input);
        $language = $input['language'] ?? $brief['language'];
        $style = $input['style'] ?? $brief['style'];
        $websiteType = $input['websiteType'] ?? $brief['websiteType'];
        $brandName = $input['brandName'] ?? $brief['brandName'];

        $brief['language'] = $language;
        $brief['style'] = $style;
        $brief['websiteType'] = $websiteType;
        $brief['brandName'] = $brandName;

        $project->forceFill([
            'tenant_id' => $tenantId,
            'name' => $brandName,
            'initial_prompt' => $project->initial_prompt ?: $userPrompt,
            'last_viewed_at' => now(),
        ])->save();

        $category = $brief['category'] ?? 'general';
        if ($ultraCheapMode) {
            $brief['templateId'] = $this->templateMatrix->resolve($websiteType, $category, $style);
        }

        $this->reportProgress($progress, ProjectGenerationRun::STATUS_SCAFFOLDING, 'Generating the site structure and content.');

        $structure = $this->structureGenerator->generate($brief);
        $pagesPlan = $structure['pages'];

        if ($ultraCheapMode) {
            $contentPatches = $this->contentPatchesFromCopyBank($brief, $pagesPlan);
        } else {
            $contentPatches = $this->contentGenerator->generate($brief, $pagesPlan);
        }

        $theme = $this->themeGenerator->generate($brief);
        $seoPlan = $this->seoGenerator->generate($brief, $pagesPlan, $language === 'both' ? 'ka' : $language);

        $this->reportProgress($progress, ProjectGenerationRun::STATUS_WRITING_FILES, 'Writing project files to the workspace.');

        $result = DB::transaction(function () use ($project, $userId, $tenantId, $brief, $pagesPlan, $contentPatches, $theme, $seoPlan) {
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
                    $settings = $this->applyGeneratedStockImagePatch(
                        $settings,
                        $stockImagePatches[$pageIndex][$secOrder] ?? []
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
                        $this->buildGeneratedSectionAuthorityBinding($sec, $localId, $settings, $userId)
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
            ];
        });

        $project = $result['project'];
        $this->projectWorkspace->initializeProjectCodebase($project, [
            'active_generation_run_id' => isset($input['generationRunId']) && is_string($input['generationRunId'])
                ? trim((string) $input['generationRunId'])
                : null,
            'phase' => ProjectGenerationRun::STATUS_WRITING_FILES,
        ]);
        $scan = $this->codebaseScanner->scan($project);
        $this->codebaseScanner->writeIndex($project, $scan);
        $this->reportProgress($progress, ProjectGenerationRun::STATUS_BUILDING_PREVIEW, 'Building the preview and validating workspace readiness.');

        return $result;
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
     * Build content patches from copy bank (no AI).
     */
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
    private function buildGeneratedSectionAuthorityBinding(array $sectionPlan, string $localId, array $settings, int $userId): array
    {
        $cmsFields = array_values(array_filter(array_map(static function ($field): string {
            if (! is_array($field)) {
                return '';
            }

            return is_string($field['key'] ?? null) ? trim((string) $field['key']) : '';
        }, is_array($sectionPlan['cms_fields'] ?? null) ? $sectionPlan['cms_fields'] : [])));
        $visualFields = array_values(array_filter(array_keys(is_array($sectionPlan['style_json'] ?? null) ? $sectionPlan['style_json'] : [])));
        $detectedMediaFields = array_values(array_filter(array_keys($settings), static function ($key): bool {
            if (! is_string($key)) {
                return false;
            }

            $normalized = trim(Str::lower($key));

            return $normalized !== '' && (
                preg_match('/(^|_)(image|photo|picture|thumbnail|avatar|cover|backgroundimage|background_image|logo)(_|$)/', $normalized) === 1
                || preg_match('/^image_\d+_url$/', $normalized) === 1
            );
        }));
        $cmsFields = array_values(array_unique([...$cmsFields, ...$detectedMediaFields]));
        $staticDefaults = array_values(array_filter(array_keys($settings), static function ($key) use ($cmsFields, $visualFields): bool {
            return ! in_array($key, $cmsFields, true) && ! in_array($key, $visualFields, true);
        }));

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

                $queryTargets = $this->buildGeneratedSectionImageQueries(
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

                    $results = $this->stockImageSearch->search(
                        $query,
                        6,
                        ['orientation' => $target['orientation'] ?? null]
                    );

                    if ($results === []) {
                        continue;
                    }

                    $best = $results[0];
                    if (! is_array($best)) {
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
                            'page_slug' => is_string($pagePlan['slug'] ?? null) ? (string) $pagePlan['slug'] : null,
                            'query' => $query,
                        ]);
                    } catch (\Throwable) {
                        continue;
                    }

                    $patches[$pageIndex][$sectionIndex][$path] = [
                        'asset_url' => route('public.sites.assets', ['site' => $site->id, 'path' => $media->path]),
                        'media_id' => $media->id,
                        'provider' => $best['provider'] ?? null,
                        'query' => $query,
                    ];
                }
            }
        }

        return $patches;
    }

    /**
     * @param  array<string, mixed>  $brief
     * @param  array<string, mixed>  $pagePlan
     * @param  array<string, mixed>  $sectionPlan
     * @param  array<string, mixed>  $settings
     * @return array<int, array{path: string, query: string, orientation: string}>
     */
    private function buildGeneratedSectionImageQueries(array $brief, array $pagePlan, array $sectionPlan, array $settings): array
    {
        $sectionType = Str::lower(trim((string) ($sectionPlan['section_type'] ?? '')));
        $role = $this->inferSectionImageRole($sectionType);
        if ($role === null) {
            return [];
        }

        $paths = $this->resolveGeneratedSectionImagePaths($sectionType, $settings);
        if ($paths === []) {
            return [];
        }

        $query = $this->buildGeneratedStockImageQuery($brief, $pagePlan, $settings, $role);
        if ($query === '') {
            return [];
        }

        $orientation = match ($role) {
            'hero', 'cta' => 'landscape',
            'team', 'testimonials' => 'portrait',
            default => 'square',
        };

        return array_map(static fn (string $path): array => [
            'path' => $path,
            'query' => $query,
            'orientation' => $orientation,
        ], $paths);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    private function resolveGeneratedSectionImagePaths(string $sectionType, array $settings): array
    {
        $paths = [];

        foreach (array_keys($settings) as $key) {
            if (! is_string($key)) {
                continue;
            }

            $normalizedKey = Str::lower(trim($key));
            if ($normalizedKey === '') {
                continue;
            }

            if (
                in_array($normalizedKey, ['image', 'image_url', 'backgroundimage', 'background_image', 'hero_image_url', 'cover_image', 'thumbnail', 'avatar'], true)
                || preg_match('/^image_\d+_url$/', $normalizedKey) === 1
            ) {
                $paths[] = $key;
            }
        }

        $normalizedSectionType = Str::lower(trim($sectionType));
        if ($paths === []) {
            if (str_contains($normalizedSectionType, 'hero')) {
                $paths[] = 'image';
            } elseif (str_contains($normalizedSectionType, 'banner') || str_contains($normalizedSectionType, 'cta')) {
                $paths[] = 'backgroundImage';
            }
        }

        return array_values(array_unique($paths));
    }

    private function inferSectionImageRole(string $sectionType): ?string
    {
        return match (true) {
            str_contains($sectionType, 'hero'),
            str_contains($sectionType, 'banner') => 'hero',
            str_contains($sectionType, 'feature'),
            str_contains($sectionType, 'service') => 'features',
            str_contains($sectionType, 'gallery'),
            str_contains($sectionType, 'portfolio') => 'gallery',
            str_contains($sectionType, 'testimonial'),
            str_contains($sectionType, 'review') => 'testimonials',
            str_contains($sectionType, 'team'),
            str_contains($sectionType, 'staff') => 'team',
            str_contains($sectionType, 'cta'),
            str_contains($sectionType, 'contact'),
            str_contains($sectionType, 'form') => 'cta',
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $brief
     * @param  array<string, mixed>  $pagePlan
     * @param  array<string, mixed>  $settings
     */
    private function buildGeneratedStockImageQuery(array $brief, array $pagePlan, array $settings, string $role): string
    {
        $style = trim((string) ($brief['style'] ?? 'modern'));
        $subject = trim((string) ($brief['businessType'] ?? $brief['category'] ?? $brief['websiteType'] ?? 'business'));
        $pageTitle = trim((string) ($pagePlan['title'] ?? $pagePlan['slug'] ?? ''));
        $headline = trim((string) ($settings['headline'] ?? $settings['title'] ?? ''));

        $prefix = match ($role) {
            'hero' => "{$style} {$subject}",
            'features' => "{$subject} services",
            'gallery' => "{$subject} lifestyle",
            'team' => "{$subject} professional portrait",
            'testimonials' => "happy {$subject} customers",
            'cta' => "{$subject} consultation",
            default => $subject,
        };

        $query = trim(implode(' ', array_filter([
            $prefix,
            $pageTitle !== '' && ! str_contains(Str::lower($prefix), Str::lower($pageTitle)) ? $pageTitle : null,
            $headline !== '' && ! str_contains(Str::lower($prefix), Str::lower($headline)) ? $headline : null,
        ])));

        return preg_replace('/\s+/', ' ', $query) ?: '';
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
