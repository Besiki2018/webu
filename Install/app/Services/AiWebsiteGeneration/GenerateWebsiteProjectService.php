<?php

namespace App\Services\AiWebsiteGeneration;

use App\Cms\Contracts\CmsRepositoryContract;
use App\Models\Project;
use App\Models\Site;
use App\Models\Tenant;
use App\Models\Website;
use App\Models\WebsitePage;
use App\Models\WebsiteSeo;
use App\Models\WebsiteRevision;
use App\Models\PageSection;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use App\Services\UniversalCmsSyncService;
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
        protected UniversalCmsSyncService $cmsSync,
        protected ProjectWorkspaceService $projectWorkspace,
        protected CodebaseScanner $codebaseScanner
    ) {}

    /**
     * @param  array{userPrompt: string, language?: string, style?: string, websiteType?: string, brandName?: string, currency?: string}  $input
     * @return array{website: Website, project: Project, site: Site, pages: array}
     */
    public function generate(array $input): array
    {
        $userPrompt = (string) ($input['userPrompt'] ?? '');
        $userId = (int) ($input['user_id'] ?? 0);
        $ultraCheapMode = (bool) ($input['ultra_cheap_mode'] ?? true);
        if ($userId < 1) {
            throw new \InvalidArgumentException('user_id required.');
        }
        $tenantId = $this->resolveTenantIdForUser($userId);

        $brief = $this->resolveBrief($userPrompt, $input);
        $language = $input['language'] ?? $brief['language'];
        $style = $input['style'] ?? $brief['style'];
        $websiteType = $input['websiteType'] ?? $brief['websiteType'];
        $brandName = $input['brandName'] ?? $brief['brandName'];

        $brief['language'] = $language;
        $brief['style'] = $style;
        $brief['websiteType'] = $websiteType;
        $brief['brandName'] = $brandName;

        $category = $brief['category'] ?? 'general';
        if ($ultraCheapMode) {
            $brief['templateId'] = $this->templateMatrix->resolve($websiteType, $category, $style);
        }

        $structure = $this->structureGenerator->generate($brief);
        $pagesPlan = $structure['pages'];

        if ($ultraCheapMode) {
            $contentPatches = $this->contentPatchesFromCopyBank($brief, $pagesPlan);
        } else {
            $contentPatches = $this->contentGenerator->generate($brief, $pagesPlan);
        }

        $theme = $this->themeGenerator->generate($brief);
        $seoPlan = $this->seoGenerator->generate($brief, $pagesPlan, $language === 'both' ? 'ka' : $language);

        $result = DB::transaction(function () use ($userId, $tenantId, $brief, $pagesPlan, $contentPatches, $theme, $seoPlan) {
            // 1) CMS-first: create Website (no site_id yet)
            $website = Website::create([
                'user_id' => $userId,
                'tenant_id' => $tenantId,
                'name' => $brief['brandName'],
                'domain' => null,
                'theme' => $theme,
                'site_id' => null,
            ]);

            $websitePages = [];
            foreach ($pagesPlan as $pageIndex => $plan) {
                $slug = $plan['slug'];
                $title = $plan['title'];

                $websitePage = WebsitePage::create([
                    'website_id' => $website->id,
                    'tenant_id' => $tenantId,
                    'slug' => $slug,
                    'title' => $title,
                    'order' => $pageIndex,
                    'page_id' => null,
                ]);

                foreach ($plan['sections'] ?? [] as $secOrder => $sec) {
                    $content = $sec['content_json'] ?? [];
                    $patch = $contentPatches[$pageIndex][$secOrder] ?? [];
                    $settings = array_merge($content, $patch);
                    PageSection::create([
                        'page_id' => $websitePage->id,
                        'tenant_id' => $tenantId,
                        'website_id' => $website->id,
                        'section_type' => $sec['section_type'],
                        'order' => $secOrder,
                        'settings_json' => $settings,
                    ]);
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

                $websitePages[] = $websitePage;
            }

            // 2) Create Project + Site, then link: create Page + Revision per website_page
            $project = Project::create([
                'tenant_id' => $tenantId,
                'user_id' => $userId,
                'name' => $brief['brandName'],
                'initial_prompt' => $brief['brandName'],
                'last_viewed_at' => now(),
            ]);

            $site = $this->repository->findSiteByProject($project);
            if ($site) {
                $site = $this->repository->updateSite($site, [
                    'name' => $brief['brandName'],
                    'status' => 'draft',
                    'locale' => $brief['language'] === 'en' ? 'en' : 'ka',
                    'theme_settings' => $theme,
                ]);
            } else {
                $site = $this->repository->createSiteForProject($project, [
                    'name' => $brief['brandName'],
                    'status' => 'draft',
                    'locale' => $brief['language'] === 'en' ? 'en' : 'ka',
                    'theme_settings' => $theme,
                ]);
            }

            $website->update(['site_id' => $site->id]);

            foreach ($websitePages as $pageIndex => $websitePage) {
                $plan = $pagesPlan[$pageIndex];
                $slug = $plan['slug'];
                $title = $plan['title'];
                $contentJson = ['sections' => []];
                foreach ($websitePage->sections()->orderBy('order')->get()->values() as $sectionIndex => $sec) {
                    $contentJson['sections'][] = [
                        'type' => $sec->section_type,
                        'props' => $sec->settings_json ?? [],
                        'localId' => CmsSectionLocalId::fallbackForIndex((int) $sectionIndex),
                    ];
                }

                $page = $this->repository->firstOrCreatePage($site, $slug, [
                    'title' => $title,
                    'status' => 'draft',
                    'seo_title' => $seoPlan[$pageIndex]['seo_title'] ?? $title,
                    'seo_description' => $seoPlan[$pageIndex]['meta_description'] ?? '',
                ]);
                $nextVersion = max(1, $this->repository->maxRevisionVersion($site, $page) + 1);
                $this->repository->createRevision($site, $page, [
                    'version' => $nextVersion,
                    'content_json' => $contentJson,
                    'created_by' => $userId,
                    'published_at' => null,
                ]);
                $websitePage->update(['page_id' => $page->id]);
            }

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
