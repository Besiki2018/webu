<?php

namespace App\Services\AiWebsiteGeneration;

use App\Cms\Contracts\CmsRepositoryContract;
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
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use App\Services\WebuCodex\CodebaseScanner;
use App\Support\CmsSectionLocalId;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ultimate AI generation engine: CMS-first, single flow.
 * When ultra_cheap_mode: templates + copy bank + presets only (no main model).
 */
class GenerateWebsiteProjectService
{
    public function __construct(
        protected WebsiteBriefExtractor $briefExtractor,
        protected StructureGenerator $structureGenerator,
        protected ThemeGenerator $themeGenerator,
        protected ContentGenerator $contentGenerator,
        protected SeoGenerator $seoGenerator,
        protected UltraCheapCopyBank $copyBank,
        protected UltraCheapTemplateMatrix $templateMatrix,
        protected CmsRepositoryContract $repository,
        protected ProjectWorkspaceService $projectWorkspace,
        protected CodebaseScanner $codebaseScanner
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
     * @param  array{userPrompt: string, language?: string|null, style?: string|null, websiteType?: string|null, brandName?: string|null, currency?: string|null, user_id?: int, ultra_cheap_mode?: bool}  $input
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

        $this->reportProgress($progress, 'generating', 'Generating pages, sections, and content.');

        $structure = $this->structureGenerator->generate($brief);
        $pagesPlan = $structure['pages'];

        if ($ultraCheapMode) {
            $contentPatches = $this->contentPatchesFromCopyBank($brief, $pagesPlan);
        } else {
            $contentPatches = $this->contentGenerator->generate($brief, $pagesPlan);
        }

        $theme = $this->themeGenerator->generate($brief);
        $seoPlan = $this->seoGenerator->generate($brief, $pagesPlan, $language === 'both' ? 'ka' : $language);

        $this->reportProgress($progress, 'finalizing', 'Saving the project and preparing the builder.');

        $result = DB::transaction(function () use ($project, $userId, $tenantId, $brief, $pagesPlan, $contentPatches, $theme, $seoPlan) {
            $site = $this->prepareSiteForGeneration($project, $brief, $theme);

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

                    PageSection::create([
                        'page_id' => $websitePage->id,
                        'tenant_id' => $tenantId,
                        'website_id' => $website->id,
                        'section_type' => $sec['section_type'],
                        'order' => $secOrder,
                        'settings_json' => $settings,
                    ]);

                    $contentJson['sections'][] = [
                        'type' => $sec['section_type'],
                        'props' => $settings,
                        'localId' => CmsSectionLocalId::fallbackForIndex((int) $secOrder),
                    ];
                }

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
        $this->projectWorkspace->initializeProjectCodebase($project);
        $scan = $this->codebaseScanner->scan($project);
        $this->codebaseScanner->writeIndex($project, $scan);

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
