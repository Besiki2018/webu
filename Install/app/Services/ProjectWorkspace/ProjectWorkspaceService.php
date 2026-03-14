<?php

namespace App\Services\ProjectWorkspace;

use App\Cms\Support\LocalizedCmsPayload;
use App\Cms\Contracts\CmsRepositoryContract;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Project;
use App\Models\ProjectGenerationRun;
use App\Models\Site;
use App\Services\WebuCodex\PathRules;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

/**
 * Real project codebase per Webu project.
 *
 * Each project has a workspace at storage_path("workspaces/{project_id}") with:
 * - src/pages/{slug}/Page.tsx (real React pages from CMS)
 * - src/components/ (Header, Footer)
 * - src/sections/ (HeroSection, ProductGrid, etc.)
 * - src/layouts/SiteLayout.tsx
 * - src/styles/globals.css
 * - public/
 * - cms/pageStructure.json (snapshot for reference)
 *
 * CMS/PageRevision is the source of truth for visual builder state.
 * Workspace code is an auxiliary projection for project-edit/code-edit flows.
 */
class ProjectWorkspaceService
{
    private const CMS_AUTHORITY_FILE = '.webu/cms-authority.json';
    private const PAGE_BINDING_ROOT_KEY = 'webu_cms_binding';
    private const WORKSPACE_PROJECTION_FILE = '.webu/workspace-projection.json';
    private const WORKSPACE_MANIFEST_FILE = '.webu/workspace-manifest.json';
    private const WORKSPACE_OPERATION_LOG_FILE = '.webu/workspace-operation-log.json';
    private const WORKSPACE_OPERATION_LOG_LIMIT = 250;
    private const GENERATED_PROJECTION_MARKER = '@webu-generated-projection';
    private const PROTECTED_WORKSPACE_FILES = [
        'vite.config.ts',
        'tsconfig.json',
        'package.json',
        'package-lock.json',
        'components.json',
        'tailwind.config.ts',
        'tailwind.config.js',
        'postcss.config.js',
        'postcss.config.cjs',
        'index.html',
        'src/main.tsx',
        'src/index.css',
        'template.json',
        self::WORKSPACE_MANIFEST_FILE,
        self::WORKSPACE_OPERATION_LOG_FILE,
    ];

    /** Section type (CMS) → React component name for generated code. */
    private const SECTION_TYPE_TO_COMPONENT = [
        'webu_general_header_01' => 'Header',
        'webu_general_footer_01' => 'Footer',
        'webu_general_hero_01' => 'HeroSection',
        'webu_general_features_01' => 'FeaturesSection',
        'webu_general_cta_01' => 'CTASection',
        'webu_general_text_01' => 'TextSection',
        'webu_general_heading_01' => 'HeadingSection',
        'webu_general_button_01' => 'ButtonSection',
        'webu_general_spacer_01' => 'SpacerSection',
        'webu_general_image_01' => 'ImageSection',
        'webu_general_card_01' => 'CardSection',
        'webu_general_form_wrapper_01' => 'FormWrapperSection',
        'webu_general_newsletter_01' => 'NewsletterSection',
        'webu_ecom_product_grid_01' => 'ProductGridSection',
        'webu_ecom_featured_categories_01' => 'FeaturedCategoriesSection',
        'webu_ecom_category_list_01' => 'CategoryListSection',
        'webu_ecom_product_search_01' => 'ProductSearchSection',
        'webu_ecom_product_gallery_01' => 'ProductGallerySection',
        'webu_ecom_product_detail_01' => 'ProductDetailSection',
        'webu_ecom_add_to_cart_button_01' => 'AddToCartButtonSection',
        'webu_ecom_product_tabs_01' => 'ProductTabsSection',
        'webu_ecom_cart_icon_01' => 'CartIconSection',
        'webu_ecom_cart_page_01' => 'CartPageSection',
        'webu_ecom_coupon_ui_01' => 'CouponUISection',
        'webu_ecom_order_summary_01' => 'OrderSummarySection',
        'header' => 'Header',
        'footer' => 'Footer',
        'hero' => 'HeroSection',
        'features' => 'FeaturesSection',
        'products' => 'ProductGridSection',
        'testimonials' => 'TestimonialsSection',
        'cta' => 'CTASection',
        'newsletter' => 'NewsletterSection',
    ];

    public function __construct(
        protected CmsRepositoryContract $cmsRepository,
        protected LocalizedCmsPayload $localizedPayload
    ) {}

    /**
     * Ensure project workspace directory exists. Returns the workspace root path.
     */
    public function ensureWorkspaceRoot(Project $project): string
    {
        $root = storage_path('workspaces/'.(string) $project->id);
        if (! is_dir($root)) {
            File::ensureDirectoryExists($root, 0775, true);
        }

        return $root;
    }

    /**
     * Ensure the project has a real code workspace AI can inspect.
     * Seeds the scaffold when missing and regenerates the auxiliary workspace
     * whenever the authoritative CMS/PageRevision state is newer.
     *
     * @return array{
     *     root: string,
     *     scaffold_seeded: bool,
     *     generated_from_cms: bool,
     *     has_site: bool,
     *     has_page_files: bool,
     *     has_section_files: bool,
     *     ready: bool
     * }
     */
    public function ensureProjectCodebaseReady(Project $project): array
    {
        $root = $this->ensureWorkspaceRoot($project);

        $scaffoldSeeded = false;
        if (! $this->hasWorkspaceScaffold($root)) {
            $this->seedTemplate($project, false);
            $scaffoldSeeded = true;
        }

        $this->upgradeLegacyScaffolds($project);

        $hasPageFiles = $this->hasWorkspacePageFiles($root);
        $hasSectionFiles = $this->hasWorkspaceSectionFiles($root);

        if (! $hasSectionFiles) {
            $this->seedTemplate($project, false);
            $hasSectionFiles = $this->hasWorkspaceSectionFiles($root);
        }

        $site = $this->cmsRepository->findSiteByProject($project);
        $generatedFromCms = false;

        if ($site !== null && ($scaffoldSeeded || ! $hasPageFiles || $this->workspaceCmsProjectionIsStale($project, $root, $site))) {
            $this->generateFromCms($project);
            $generatedFromCms = true;
            $hasPageFiles = $this->hasWorkspacePageFiles($root);
            $hasSectionFiles = $this->hasWorkspaceSectionFiles($root);
        }

        if ($site !== null && $hasPageFiles && ! $this->workspaceManifestExists($project)) {
            $this->syncWorkspaceManifestFromProjection($project);
        }

        return [
            'root' => $root,
            'scaffold_seeded' => $scaffoldSeeded,
            'generated_from_cms' => $generatedFromCms,
            'has_site' => $site !== null,
            'has_page_files' => $hasPageFiles,
            'has_section_files' => $hasSectionFiles,
            'ready' => $this->hasWorkspaceScaffold($root) && $hasPageFiles,
        ];
    }

    /**
     * Seed workspace with full React project template (structure + minimal files).
     * Idempotent: does not overwrite existing files by default.
     */
    public function seedTemplate(Project $project, bool $overwrite = false): void
    {
        $root = $this->ensureWorkspaceRoot($project);

        $structure = [
            'package.json' => $this->templatePackageJson($project),
            'tsconfig.json' => $this->templateTsconfigJson(),
            'tsconfig.node.json' => $this->templateTsconfigNodeJson(),
            'vite.config.ts' => $this->templateViteConfig(),
            'index.html' => $this->templateIndexHtml($project),
            'src' => [
                'main.tsx' => $this->templateMainTsx(),
                'App.tsx' => $this->templateAppTsx(),
                'pages' => [],
                'components' => [
                    'Header.tsx' => $this->templateHeader($project),
                    'Footer.tsx' => $this->templateFooter($project),
                ],
                'sections' => [
                    'HeroSection.tsx' => $this->templateSection('HeroSection', 'hero'),
                    'ProductGridSection.tsx' => $this->templateSection('ProductGridSection', 'product_grid'),
                    'NewsletterSection.tsx' => $this->templateSection('NewsletterSection', 'newsletter'),
                    'CTASection.tsx' => $this->templateSection('CTASection', 'cta'),
                    'FeaturesSection.tsx' => $this->templateSection('FeaturesSection', 'features'),
                    'TestimonialsSection.tsx' => $this->templateSection('TestimonialsSection', 'testimonials'),
                    'FormWrapperSection.tsx' => $this->templateSection('FormWrapperSection', 'form'),
                ],
                'layouts' => [
                    'SiteLayout.tsx' => $this->templateSiteLayout(),
                ],
                'styles' => [
                    'globals.css' => $this->templateGlobalsCss(),
                ],
            ],
            'public' => [],
            'cms' => [],
        ];

        $structure['src']['pages']['home'] = [
            'Page.tsx' => $this->templateHomePage(),
        ];

        $this->writeStructure($root, $structure, $overwrite);

        // Ensure placeholder so directories are tracked
        $this->writeFileIfMissing($root.'/public/.gitkeep', '', $overwrite);
        $this->writeFileIfMissing($root.'/cms/.gitkeep', '', $overwrite);
    }

    /**
     * Generate real React code from current CMS pages and write to workspace.
     * Creates src/pages/{slug}/Page.tsx for each page and ensures section components exist.
     */
    public function generateFromCms(Project $project): void
    {
        $site = $this->cmsRepository->findSiteByProject($project);
        if (! $site) {
            Log::warning('ProjectWorkspaceService: no site for project', ['project_id' => $project->id]);

            return;
        }

        $root = $this->ensureWorkspaceRoot($project);
        $this->seedTemplate($project, false);
        $this->upgradeLegacyScaffolds($project);

        $pages = $site->pages()
            ->orderByRaw("CASE WHEN slug = 'home' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->get();

        $pageStructure = [];
        $pageProjectionEntries = [];
        $usedComponentProjectionMap = [];

        foreach ($pages as $page) {
            $revision = $page->revisions()->latest('version')->first();
            $content = $revision && is_array($revision->content_json) ? $revision->content_json : [];
            $resolvedPayload = $this->localizedPayload->resolve($content, $site->locale, $site->locale);
            $resolvedContent = is_array($resolvedPayload['content'] ?? null) ? $resolvedPayload['content'] : [];
            $sections = is_array($resolvedContent['sections'] ?? null) ? $resolvedContent['sections'] : [];
            $pageBinding = is_array($resolvedContent[self::PAGE_BINDING_ROOT_KEY] ?? null)
                ? $resolvedContent[self::PAGE_BINDING_ROOT_KEY]
                : [];

            $slug = (string) $page->slug;
            if ($slug === '') {
                $slug = 'page-'.$page->id;
            }
            $slug = preg_replace('/[^a-z0-9_-]/i', '_', $slug) ?: 'home';

            $pagePath = $root.'/src/pages/'.$slug;
            File::ensureDirectoryExists($pagePath, 0775, true);

            $sectionDetails = $this->mapSectionsForPage($sections);
            $pageProjection = $this->buildPageProjectionEntry($page, $slug, $sectionDetails, $pageBinding);
            $pageProjectionEntries[] = $pageProjection;
            $usedComponentProjectionMap = $this->mergeUsedComponentProjectionMap($usedComponentProjectionMap, $slug, $sectionDetails);

            $pageTsx = $this->buildPageTsx($slug, $sections, $site, $pageProjection);
            $pageFile = $pagePath.'/Page.tsx';
            if ($this->shouldWriteProjectionManagedPageFile($pageFile)) {
                File::put($pageFile, $pageTsx);
            }

            $sectionNames = $this->sectionTypesToComponentNames($sections);
            $pageStructure[] = [
                'slug' => $page->slug,
                'title' => $page->title,
                'path' => 'src/pages/'.$slug.'/Page.tsx',
                'sections' => $sectionNames,
                'section_details' => array_map(static fn (array $section): array => [
                    'component' => $section['componentName'],
                    'type' => $section['type'],
                    'local_id' => $section['localId'],
                    'props' => $section['props'],
                ], $sectionDetails),
            ];

            foreach ($sectionNames as $componentName) {
                $this->ensureSectionComponentExists($root, $componentName, $overwrite = false);
            }
        }

        $layoutProps = $this->buildLayoutProps($site);
        $layoutProjectionEntries = $this->buildLayoutProjectionEntries(
            $layoutProps,
            array_values(array_filter(array_map(
                static fn (array $entry): string => is_string($entry['slug'] ?? null) ? trim((string) $entry['slug']) : '',
                $pageProjectionEntries
            )))
        );
        $this->syncProjectionManagedLayoutFiles($root, $project, [
            'header_props' => is_array($layoutProps['headerProps'] ?? null) ? $layoutProps['headerProps'] : [],
            'footer_props' => is_array($layoutProps['footerProps'] ?? null) ? $layoutProps['footerProps'] : [],
        ]);
        foreach ($usedComponentProjectionMap as $componentName => $projection) {
            if (! is_string($componentName) || trim($componentName) === '' || ! is_array($projection)) {
                continue;
            }
            $this->syncProjectionManagedSectionFile($root, $componentName, $projection);
        }
        $this->pruneUnusedProjectionManagedFiles($root, $pageProjectionEntries, $usedComponentProjectionMap, $layoutProjectionEntries);
        $this->writeAppTsxFromPages($root, $pages);

        $cmsPath = $root.'/cms/pageStructure.json';
        File::ensureDirectoryExists(dirname($cmsPath), 0775, true);
        File::put($cmsPath, json_encode(['pages' => $pageStructure], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $webuPath = $root.'/.webu/page-structure.json';
        File::ensureDirectoryExists(dirname($webuPath), 0775, true);
        File::put($webuPath, json_encode(['pages' => $pageStructure], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $this->writeWorkspaceProjectionSnapshot($root, [
            'generated_at' => now()->toIso8601String(),
            'projection_source' => 'cms-authority',
            'pages' => $pageProjectionEntries,
            'components' => array_values($usedComponentProjectionMap),
            'layouts' => $layoutProjectionEntries,
            'files' => $this->buildWorkspaceProjectionFileCatalog($pageProjectionEntries, $usedComponentProjectionMap, $layoutProjectionEntries),
        ]);
        $this->writeCmsAuthoritySnapshot($root, $site);
    }

    /**
     * Initialize full project codebase: workspace + template + CMS-to-code.
     * Call when project/site is ready for AI-editable code.
     */
    public function initializeProjectCodebase(Project $project, array $context = []): string
    {
        $this->ensureWorkspaceRoot($project);
        $this->seedTemplate($project, false);
        $this->generateFromCms($project);
        $this->syncWorkspaceManifestFromProjection($project, [
            'active_generation_run_id' => isset($context['active_generation_run_id']) && is_string($context['active_generation_run_id'])
                ? trim((string) $context['active_generation_run_id'])
                : null,
            'phase' => isset($context['phase']) && is_string($context['phase'])
                ? trim((string) $context['phase'])
                : ProjectGenerationRun::STATUS_WRITING_FILES,
            'preview_ready' => false,
            'preview_url' => null,
            'error_message' => null,
        ]);

        return storage_path('workspaces/'.(string) $project->id);
    }

    /**
     * @return array<string, mixed>
     */
    public function readWorkspaceManifest(Project $project): array
    {
        return $this->readWorkspaceManifestDocument($project);
    }

    /**
     * @return array{
     *     exists: bool,
     *     ready_for_builder: bool,
     *     active_generation_run_id: string|null,
     *     generated_page_count: int,
     *     updated_at: string|null,
     *     preview: array{
     *         ready: bool,
     *         phase: string,
     *         preview_url: string|null,
     *         built_at: string|null,
     *         error_message: string|null
     *     }
     * }
     */
    public function getWorkspaceManifestSummary(Project $project, ?string $generationStatus = null): array
    {
        $exists = $this->workspaceManifestExists($project);
        $manifest = $this->readWorkspaceManifestDocument($project);
        $preview = is_array($manifest['preview'] ?? null) ? $manifest['preview'] : $this->defaultWorkspaceManifestPreview();
        $phase = $this->normalizeWorkspacePreviewPhase(is_string($preview['phase'] ?? null) ? (string) $preview['phase'] : null);
        $isLegacyReady = in_array(trim(strtolower((string) $generationStatus)), ['completed', 'complete'], true);
        $readyForBuilder = $exists
            ? ((bool) ($preview['ready'] ?? false) && $phase === ProjectGenerationRun::STATUS_READY)
            : $isLegacyReady;

        return [
            'exists' => $exists,
            'ready_for_builder' => $readyForBuilder,
            'active_generation_run_id' => is_string($manifest['activeGenerationRunId'] ?? null)
                ? (string) $manifest['activeGenerationRunId']
                : null,
            'generated_page_count' => count(is_array($manifest['generatedPages'] ?? null) ? $manifest['generatedPages'] : []),
            'updated_at' => is_string($manifest['updatedAt'] ?? null) ? (string) $manifest['updatedAt'] : null,
            'preview' => [
                'ready' => (bool) ($preview['ready'] ?? false),
                'phase' => $phase,
                'preview_url' => is_string($preview['previewUrl'] ?? null) ? (string) $preview['previewUrl'] : null,
                'built_at' => is_string($preview['builtAt'] ?? null) ? (string) $preview['builtAt'] : null,
                'error_message' => is_string($preview['errorMessage'] ?? null) ? (string) $preview['errorMessage'] : null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function syncInitialGenerationState(Project $project, array $context = []): array
    {
        if (! $this->workspaceManifestExists($project)) {
            $this->syncWorkspaceManifestFromProjection($project, $context);
        }

        $manifest = $this->readWorkspaceManifestDocument($project);
        $timestamp = now()->toIso8601String();
        $phase = $this->normalizeWorkspacePreviewPhase(is_string($context['phase'] ?? null) ? (string) $context['phase'] : (string) ($manifest['preview']['phase'] ?? 'idle'));
        $runId = isset($context['active_generation_run_id']) && is_string($context['active_generation_run_id']) && trim((string) $context['active_generation_run_id']) !== ''
            ? trim((string) $context['active_generation_run_id'])
            : (is_string($manifest['activeGenerationRunId'] ?? null) ? (string) $manifest['activeGenerationRunId'] : null);
        $isActivePhase = in_array($phase, [
            ProjectGenerationRun::STATUS_QUEUED,
            ProjectGenerationRun::STATUS_PLANNING,
            ProjectGenerationRun::STATUS_GENERATING,
            ProjectGenerationRun::STATUS_FINALIZING,
            ProjectGenerationRun::STATUS_SCAFFOLDING,
            ProjectGenerationRun::STATUS_WRITING_FILES,
            ProjectGenerationRun::STATUS_BUILDING_PREVIEW,
        ], true);

        $manifest['schemaVersion'] = 1;
        $manifest['projectId'] = (string) $project->id;
        $manifest['rootDir'] = $this->ensureWorkspaceRoot($project);
        $manifest['manifestPath'] = self::WORKSPACE_MANIFEST_FILE;
        $manifest['activeGenerationRunId'] = $isActivePhase ? $runId : null;
        $manifest['preview'] = is_array($manifest['preview'] ?? null) ? $manifest['preview'] : $this->defaultWorkspaceManifestPreview();
        $manifest['preview']['phase'] = $phase;
        $manifest['preview']['ready'] = $phase === ProjectGenerationRun::STATUS_READY;
        $manifest['preview']['previewUrl'] = isset($context['preview_url']) && is_string($context['preview_url']) && trim((string) $context['preview_url']) !== ''
            ? trim((string) $context['preview_url'])
            : (is_string($manifest['preview']['previewUrl'] ?? null) ? (string) $manifest['preview']['previewUrl'] : null);
        $manifest['preview']['builtAt'] = $phase === ProjectGenerationRun::STATUS_READY
            ? (is_string($manifest['preview']['builtAt'] ?? null) && trim((string) $manifest['preview']['builtAt']) !== ''
                ? (string) $manifest['preview']['builtAt']
                : $timestamp)
            : null;
        $manifest['preview']['errorMessage'] = $phase === ProjectGenerationRun::STATUS_FAILED
            ? (is_string($context['error_message'] ?? null) ? trim((string) $context['error_message']) : null)
            : null;
        $manifest['updatedAt'] = $timestamp;

        $this->writeWorkspaceManifestDocument($project, $manifest);

        return $manifest;
    }

    public function invalidateWorkspaceProjection(Project $project): void
    {
        $site = $this->cmsRepository->findSiteByProject($project);
        if (! $site) {
            return;
        }

        $this->generateFromCms($project);
    }

    /**
     * @return array<string, mixed>
     */
    public function readWorkspaceProjection(Project $project): array
    {
        $root = $this->ensureWorkspaceRoot($project);
        $path = $root.'/'.self::WORKSPACE_PROJECTION_FILE;
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) File::get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function workspaceCmsProjectionIsStale(Project $project, string $root, Site $site): bool
    {
        $current = $this->buildCmsAuthoritySnapshot($site);
        $path = $root.'/'.self::CMS_AUTHORITY_FILE;
        if (! is_file($path)) {
            return true;
        }

        $stored = json_decode((string) File::get($path), true);

        return ! is_array($stored) || ($stored['fingerprint'] ?? null) !== ($current['fingerprint'] ?? null);
    }

    private function writeCmsAuthoritySnapshot(string $root, Site $site): void
    {
        $path = $root.'/'.self::CMS_AUTHORITY_FILE;
        File::ensureDirectoryExists(dirname($path), 0775, true);
        File::put($path, json_encode($this->buildCmsAuthoritySnapshot($site), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    /**
     * @return array{site_id: int|string, site_updated_at: string|null, latest_revision_updated_at: string|null, latest_revision_id: int|null, page_count: int, fingerprint: string}
     */
    private function buildCmsAuthoritySnapshot(Site $site): array
    {
        $latestRevision = PageRevision::query()
            ->where('site_id', $site->id)
            ->latest('updated_at')
            ->latest('id')
            ->first();

        $pageCount = $site->pages()->count();
        $siteUpdatedAt = $site->updated_at?->toISOString();
        $latestRevisionUpdatedAt = $latestRevision?->updated_at?->toISOString();
        $latestRevisionId = $latestRevision?->id ? (int) $latestRevision->id : null;
        $fingerprintSource = json_encode([
            'site_id' => $site->id,
            'site_updated_at' => $siteUpdatedAt,
            'latest_revision_updated_at' => $latestRevisionUpdatedAt,
            'latest_revision_id' => $latestRevisionId,
            'page_count' => $pageCount,
            'theme_fingerprint' => sha1(json_encode($site->theme_settings ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '';

        return [
            'site_id' => $site->id,
            'site_updated_at' => $siteUpdatedAt,
            'latest_revision_updated_at' => $latestRevisionUpdatedAt,
            'latest_revision_id' => $latestRevisionId,
            'page_count' => $pageCount,
            'fingerprint' => sha1($fingerprintSource),
        ];
    }

    /**
     * Write a file to the workspace (for AI createFile / updateFile).
     * Path is relative to workspace root.
     *
     * Writing a CMS-managed projection file detaches it from future automatic
     * regeneration by removing the projection metadata banner.
     */
    public function writeFile(Project $project, string $relativePath, string $content, array $context = []): bool
    {
        $normalizedPath = PathRules::normalizePath($relativePath);
        $actor = is_string($context['actor'] ?? null) ? trim((string) $context['actor']) : 'user';
        $this->assertWorkspacePathWritable($normalizedPath, $actor);
        $previousContent = $this->readFile($project, $normalizedPath);
        $didExist = $previousContent !== null;

        $this->writeWorkspaceFileContents($project, $normalizedPath, $content);
        $this->recordWorkspaceMutation($project, [
            'actor' => $context['actor'] ?? 'user',
            'source' => $context['source'] ?? 'workspace_file_api',
            'operation_kind' => $context['operation_kind'] ?? ($didExist ? 'update_file' : 'create_file'),
            'path' => $normalizedPath,
            'previous_path' => $context['previous_path'] ?? null,
            'preview_refresh_requested' => $context['preview_refresh_requested'] ?? true,
            'reason' => $context['reason'] ?? null,
            'before_content' => $previousContent,
            'after_content' => $content,
        ]);

        return true;
    }

    /**
     * Read a file from the workspace.
     */
    public function readFile(Project $project, string $relativePath, bool $stripProjectionBanner = false): ?string
    {
        $root = $this->ensureWorkspaceRoot($project);
        $path = $root.'/'.ltrim(str_replace('..', '', $relativePath), '/');
        if (! is_file($path)) {
            return null;
        }

        $content = File::get($path);

        return $stripProjectionBanner ? $this->stripProjectionBanner($content) : $content;
    }

    /**
     * Read a file for code-edit / AI context without projection metadata comments.
     */
    public function readEditableFile(Project $project, string $relativePath): ?string
    {
        return $this->readFile($project, $relativePath, true);
    }

    /**
     * Delete a file in the workspace (for AI deleteFile).
     */
    public function deleteFile(Project $project, string $relativePath, array $context = []): bool
    {
        $normalizedPath = PathRules::normalizePath($relativePath);
        $actor = is_string($context['actor'] ?? null) ? trim((string) $context['actor']) : 'user';
        $this->assertWorkspacePathWritable($normalizedPath, $actor);
        $previousContent = $this->readFile($project, $normalizedPath);
        if ($previousContent === null) {
            return false;
        }

        $this->deleteWorkspaceFileContents($project, $normalizedPath);
        $this->recordWorkspaceMutation($project, [
            'actor' => $context['actor'] ?? 'user',
            'source' => $context['source'] ?? 'workspace_file_api',
            'operation_kind' => $context['operation_kind'] ?? 'delete_file',
            'path' => $normalizedPath,
            'previous_path' => $context['previous_path'] ?? null,
            'preview_refresh_requested' => $context['preview_refresh_requested'] ?? true,
            'reason' => $context['reason'] ?? null,
            'before_content' => $previousContent,
            'after_content' => null,
        ]);

        return true;
    }

    public function moveFile(Project $project, string $fromRelativePath, string $toRelativePath, array $context = []): bool
    {
        $fromPath = PathRules::normalizePath($fromRelativePath);
        $toPath = PathRules::normalizePath($toRelativePath);
        $actor = is_string($context['actor'] ?? null) ? trim((string) $context['actor']) : 'user';
        $this->assertWorkspacePathWritable($fromPath, $actor);
        $this->assertWorkspacePathWritable($toPath, $actor);
        $content = $this->readFile($project, $fromPath);
        if ($content === null) {
            return false;
        }

        $this->writeWorkspaceFileContents($project, $toPath, $content);
        $this->deleteWorkspaceFileContents($project, $fromPath);
        $this->recordWorkspaceMutation($project, [
            'actor' => $context['actor'] ?? 'user',
            'source' => $context['source'] ?? 'workspace_file_api',
            'operation_kind' => $context['operation_kind'] ?? 'move_file',
            'path' => $toPath,
            'previous_path' => $fromPath,
            'preview_refresh_requested' => $context['preview_refresh_requested'] ?? true,
            'reason' => $context['reason'] ?? null,
            'before_content' => $content,
            'after_content' => $content,
        ]);

        return true;
    }

    public function recordWorkspaceSyncOperations(Project $project, array $paths, array $context = []): void
    {
        $actor = is_string($context['actor'] ?? null) ? trim((string) $context['actor']) : 'visual_builder';
        $source = is_string($context['source'] ?? null) && trim((string) $context['source']) !== ''
            ? trim((string) $context['source'])
            : 'workspace_sync';
        $operationKind = is_string($context['operation_kind'] ?? null) && trim((string) $context['operation_kind']) !== ''
            ? trim((string) $context['operation_kind'])
            : 'apply_patch_set';
        $reason = is_string($context['reason'] ?? null) && trim((string) $context['reason']) !== ''
            ? trim((string) $context['reason'])
            : null;
        $previewRefreshRequested = (bool) ($context['preview_refresh_requested'] ?? true);
        $normalizedPaths = array_values(array_unique(array_filter(array_map(static function ($path): string {
            return is_string($path) ? PathRules::normalizePath($path) : '';
        }, $paths), static function (string $path): bool {
            return $path !== '' && PathRules::isAllowed($path);
        })));

        foreach ($normalizedPaths as $path) {
            $content = $this->readFile($project, $path);
            $effectiveOperationKind = $content === null ? 'delete_file' : $operationKind;
            $this->recordWorkspaceMutation($project, [
                'actor' => $actor,
                'source' => $source,
                'operation_kind' => $effectiveOperationKind,
                'path' => $path,
                'previous_path' => null,
                'preview_refresh_requested' => $previewRefreshRequested,
                'reason' => $reason,
                'before_content' => null,
                'after_content' => $content,
            ]);
        }
    }

    /**
     * List files under allowed dirs only (src/pages, components, sections, layouts, styles, public).
     * For Code tab: full project with content and design, same scope as Webu AI edits.
     *
     * @return array<int, array{
     *     path: string,
     *     name: string,
     *     size: int,
     *     is_dir: bool,
     *     mod_time: string,
     *     source_kind: string,
     *     is_editable: bool,
     *     is_generated_projection: bool,
     *     projection_role: string|null,
     *     projection_source: 'custom'|'cms-projection'|'detached-projection'|null
     * }>
     */
    public function listFiles(Project $project, int $maxFiles = 500): array
    {
        $root = $this->ensureWorkspaceRoot($project);
        $projectionCatalog = $this->workspaceProjectionFileCatalog($this->readWorkspaceProjection($project));
        $out = [];
        foreach (PathRules::ALLOWED_PREFIXES as $prefix) {
            $dir = $root.'/'.ltrim($prefix, '/');
            if (! is_dir($dir)) {
                continue;
            }
            $it = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS | \RecursiveDirectoryIterator::FOLLOW_SYMLINKS),
                \RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $file) {
                if (count($out) >= $maxFiles) {
                    break 2;
                }
                $fullPath = $file->getPathname();
                $relative = str_replace($root.'/', '', str_replace('\\', '/', $fullPath));
                if (! PathRules::isAllowed($relative)) {
                    continue;
                }
                $projectionMeta = is_array($projectionCatalog[$relative] ?? null) ? $projectionCatalog[$relative] : [];
                $hasProjectionCatalogEntry = $projectionMeta !== [];
                $hasProjectionMarker = $file->isFile() && $this->fileContainsProjectionMarker($fullPath);
                $isGeneratedProjection = $hasProjectionMarker;
                $projectionSource = $hasProjectionMarker
                    ? 'cms-projection'
                    : ($hasProjectionCatalogEntry && $file->isFile() ? 'detached-projection' : 'custom');
                $out[] = [
                    'path' => $relative,
                    'name' => $file->getFilename(),
                    'size' => $file->isFile() ? (int) $file->getSize() : 0,
                    'is_dir' => $file->isDir(),
                    'mod_time' => $file->isFile() ? date('c', (int) $file->getMTime()) : '',
                    'source_kind' => 'workspace',
                    'is_editable' => ! $this->isProtectedWorkspaceFile($relative),
                    'is_generated_projection' => $isGeneratedProjection,
                    'projection_role' => is_string($projectionMeta['projection_role'] ?? null) ? $projectionMeta['projection_role'] : null,
                    'projection_source' => $projectionSource,
                ];
            }
        }
        usort($out, static function ($a, $b) {
            if ($a['is_dir'] !== $b['is_dir']) {
                return $a['is_dir'] ? -1 : 1;
            }
            return strcasecmp($a['path'], $b['path']);
        });

        return array_values($out);
    }

    private function isProtectedWorkspaceFile(string $path): bool
    {
        return in_array(PathRules::normalizePath($path), self::PROTECTED_WORKSPACE_FILES, true);
    }

    private function isWorkspaceMetadataFile(string $path): bool
    {
        return in_array(PathRules::normalizePath($path), [
            self::WORKSPACE_MANIFEST_FILE,
            self::WORKSPACE_OPERATION_LOG_FILE,
        ], true);
    }

    private function assertWorkspacePathWritable(string $path, string $actor): void
    {
        $normalizedPath = PathRules::normalizePath($path);
        if (! $this->isProtectedWorkspaceFile($normalizedPath)) {
            return;
        }

        if ($this->isWorkspaceMetadataFile($normalizedPath) && in_array($actor, ['visual_builder', 'system'], true)) {
            return;
        }

        throw new \RuntimeException(sprintf('Protected workspace file cannot be modified: %s', $normalizedPath));
    }

    private function writeWorkspaceFileContents(Project $project, string $relativePath, string $content): void
    {
        $root = $this->ensureWorkspaceRoot($project);
        $this->seedTemplate($project, false);
        $path = $root.'/'.ltrim(str_replace('..', '', $relativePath), '/');
        $dir = dirname($path);
        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir, 0775, true);
        }
        if ($this->fileContainsProjectionMarker($path) || str_contains($content, self::GENERATED_PROJECTION_MARKER)) {
            $content = $this->stripProjectionBanner($content);
        }
        File::put($path, $content);
    }

    private function deleteWorkspaceFileContents(Project $project, string $relativePath): void
    {
        $root = $this->ensureWorkspaceRoot($project);
        $path = $root.'/'.ltrim(str_replace('..', '', $relativePath), '/');
        if (is_file($path)) {
            File::delete($path);
        }
    }

    private function workspaceManifestPath(Project $project): string
    {
        return $this->ensureWorkspaceRoot($project).'/'.self::WORKSPACE_MANIFEST_FILE;
    }

    private function workspaceManifestExists(Project $project): bool
    {
        return is_file($this->workspaceManifestPath($project));
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function syncWorkspaceManifestFromProjection(Project $project, array $context = []): array
    {
        $root = $this->ensureWorkspaceRoot($project);
        $existingManifest = $this->readWorkspaceManifestDocument($project);
        $projection = $this->readWorkspaceProjection($project);
        $projectionCatalog = $this->workspaceProjectionFileCatalog($projection);
        $timestamp = now()->toIso8601String();
        $activeGenerationRunId = isset($context['active_generation_run_id']) && is_string($context['active_generation_run_id']) && trim((string) $context['active_generation_run_id']) !== ''
            ? trim((string) $context['active_generation_run_id'])
            : (is_string($existingManifest['activeGenerationRunId'] ?? null) ? (string) $existingManifest['activeGenerationRunId'] : null);
        $phase = $this->normalizeWorkspacePreviewPhase(is_string($context['phase'] ?? null) ? (string) $context['phase'] : (string) ($existingManifest['preview']['phase'] ?? 'idle'));
        $previewReady = (bool) ($context['preview_ready'] ?? ($phase === ProjectGenerationRun::STATUS_READY));

        $manifest = [
            'schemaVersion' => 1,
            'projectId' => (string) $project->id,
            'rootDir' => $root,
            'manifestPath' => self::WORKSPACE_MANIFEST_FILE,
            'activeGenerationRunId' => in_array($phase, ProjectGenerationRun::activeStatuses(), true) ? $activeGenerationRunId : null,
            'generatedPages' => $this->buildWorkspaceManifestGeneratedPages($projection),
            'fileOwnership' => $this->buildWorkspaceManifestProjectionOwnershipEntries(
                $project,
                $projectionCatalog,
                $projection,
                $existingManifest,
                $activeGenerationRunId
            ),
            'componentProvenance' => $this->buildWorkspaceManifestComponentProvenanceEntries(
                $projection,
                $existingManifest,
                $activeGenerationRunId
            ),
            'preview' => [
                ...$this->defaultWorkspaceManifestPreview(),
                ...(is_array($existingManifest['preview'] ?? null) ? $existingManifest['preview'] : []),
                'ready' => $phase === ProjectGenerationRun::STATUS_READY ? $previewReady : false,
                'phase' => $phase,
                'previewUrl' => isset($context['preview_url']) && is_string($context['preview_url']) && trim((string) $context['preview_url']) !== ''
                    ? trim((string) $context['preview_url'])
                    : (is_string($existingManifest['preview']['previewUrl'] ?? null) ? (string) $existingManifest['preview']['previewUrl'] : null),
                'builtAt' => $phase === ProjectGenerationRun::STATUS_READY && $previewReady ? $timestamp : null,
                'errorMessage' => isset($context['error_message']) && is_string($context['error_message']) && trim((string) $context['error_message']) !== ''
                    ? trim((string) $context['error_message'])
                    : null,
            ],
            'cmsBinding' => is_array($existingManifest['cmsBinding'] ?? null)
                ? $existingManifest['cmsBinding']
                : (is_array($projection['pages'][0]['cms_binding'] ?? null) ? $projection['pages'][0]['cms_binding'] : null),
            'updatedAt' => $timestamp,
        ];

        $this->writeWorkspaceManifestDocument($project, $manifest);

        return $manifest;
    }

    private function normalizeWorkspacePreviewPhase(?string $phase): string
    {
        return match (trim(strtolower((string) $phase))) {
            ProjectGenerationRun::STATUS_QUEUED => ProjectGenerationRun::STATUS_QUEUED,
            ProjectGenerationRun::STATUS_PLANNING => ProjectGenerationRun::STATUS_PLANNING,
            ProjectGenerationRun::STATUS_GENERATING,
            ProjectGenerationRun::STATUS_SCAFFOLDING => ProjectGenerationRun::STATUS_SCAFFOLDING,
            ProjectGenerationRun::STATUS_FINALIZING,
            ProjectGenerationRun::STATUS_BUILDING_PREVIEW => ProjectGenerationRun::STATUS_BUILDING_PREVIEW,
            ProjectGenerationRun::STATUS_WRITING_FILES => ProjectGenerationRun::STATUS_WRITING_FILES,
            ProjectGenerationRun::STATUS_READY,
            ProjectGenerationRun::STATUS_COMPLETED => ProjectGenerationRun::STATUS_READY,
            ProjectGenerationRun::STATUS_FAILED => ProjectGenerationRun::STATUS_FAILED,
            default => 'idle',
        };
    }

    /**
     * @param  array<string, mixed>  $projection
     * @return array<int, array<string, mixed>>
     */
    private function buildWorkspaceManifestGeneratedPages(array $projection): array
    {
        $pages = is_array($projection['pages'] ?? null) ? $projection['pages'] : [];

        return array_values(array_filter(array_map(static function ($page): ?array {
            if (! is_array($page)) {
                return null;
            }

            $slug = is_string($page['slug'] ?? null) ? trim((string) $page['slug']) : '';
            if ($slug === '') {
                return null;
            }

            $sections = is_array($page['sections'] ?? null) ? $page['sections'] : [];

            return [
                'pageId' => isset($page['page_id']) ? (string) $page['page_id'] : '',
                'slug' => $slug,
                'title' => is_string($page['title'] ?? null) ? (string) $page['title'] : $slug,
                'routePath' => $slug === 'home' ? '/' : '/'.$slug,
                'entryFilePath' => is_string($page['path'] ?? null) ? (string) $page['path'] : null,
                'layoutId' => 'site-layout',
                'sectionIds' => array_values(array_filter(array_map(static fn ($section): string => (
                    is_array($section) && is_string($section['local_id'] ?? null)
                        ? trim((string) $section['local_id'])
                        : ''
                ), $sections))),
                'cmsBacked' => isset($page['cms_backed']) ? (bool) $page['cms_backed'] : true,
                'contentOwner' => is_string($page['content_owner'] ?? null) ? (string) $page['content_owner'] : 'mixed',
                'cmsFieldPaths' => is_array($page['content_field_paths'] ?? null) ? $page['content_field_paths'] : [],
                'visualFieldPaths' => is_array($page['visual_field_paths'] ?? null) ? $page['visual_field_paths'] : [],
                'codeFieldPaths' => is_array($page['code_field_paths'] ?? null) ? $page['code_field_paths'] : [],
                'syncDirection' => is_string($page['sync_direction'] ?? null) ? (string) $page['sync_direction'] : 'cms_to_workspace',
                'conflictStatus' => is_string($page['conflict_status'] ?? null) ? (string) $page['conflict_status'] : 'clean',
            ];
        }, $pages)));
    }

    /**
     * @param  array<string, array<string, mixed>>  $projectionCatalog
     * @param  array<string, mixed>  $projection
     * @param  array<string, mixed>  $existingManifest
     * @return array<int, array<string, mixed>>
     */
    private function buildWorkspaceManifestProjectionOwnershipEntries(
        Project $project,
        array $projectionCatalog,
        array $projection,
        array $existingManifest,
        ?string $activeGenerationRunId
    ): array {
        $generatedPages = $this->buildWorkspaceManifestGeneratedPages($projection);
        $pageIdBySlug = [];
        foreach ($generatedPages as $page) {
            $slug = is_string($page['slug'] ?? null) ? trim((string) $page['slug']) : '';
            if ($slug !== '') {
                $pageIdBySlug[$slug] = isset($page['pageId']) ? (string) $page['pageId'] : null;
            }
        }

        $existingEntries = [];
        foreach (is_array($existingManifest['fileOwnership'] ?? null) ? $existingManifest['fileOwnership'] : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $path = is_string($entry['path'] ?? null) ? trim((string) $entry['path']) : '';
            if ($path !== '') {
                $existingEntries[$path] = $entry;
            }
        }

        $entries = [];
        foreach ($projectionCatalog as $path => $meta) {
            if (! is_string($path) || trim($path) === '' || ! is_array($meta)) {
                continue;
            }

            $normalizedPath = PathRules::normalizePath($path);
            $existing = is_array($existingEntries[$normalizedPath] ?? null) ? $existingEntries[$normalizedPath] : [];
            $pageSlugs = array_values(array_filter(array_map(static fn ($slug): string => (
                is_string($slug) ? trim($slug) : ''
            ), is_array($meta['pages'] ?? null) ? $meta['pages'] : [])));
            $pageIds = array_values(array_filter(array_map(
                static fn (string $slug): ?string => isset($pageIdBySlug[$slug]) && is_string($pageIdBySlug[$slug]) && $pageIdBySlug[$slug] !== ''
                    ? $pageIdBySlug[$slug]
                    : null,
                $pageSlugs
            )));
            $originatingPageSlug = is_string($meta['page_slug'] ?? null)
                ? trim((string) $meta['page_slug'])
                : ($pageSlugs[0] ?? null);
            $originatingPageId = $originatingPageSlug !== null && isset($pageIdBySlug[$originatingPageSlug])
                ? $pageIdBySlug[$originatingPageSlug]
                : null;
            $componentName = is_string($meta['component_name'] ?? null) ? trim((string) $meta['component_name']) : '';
            $componentKeys = array_values(array_filter(array_map(static fn ($value): string => (
                is_string($value) ? trim($value) : ''
            ), is_array($meta['section_types'] ?? null) ? $meta['section_types'] : [])));
            if ($componentName !== '') {
                $componentKeys[] = $componentName;
            }

            $entries[] = [
                'path' => $normalizedPath,
                'kind' => $this->inferWorkspaceFileKind($normalizedPath),
                'ownerType' => $this->inferWorkspaceOwnerType($normalizedPath),
                'ownerId' => $this->resolveWorkspaceManifestOwnerId($normalizedPath, $meta, $originatingPageId, $originatingPageSlug),
                'generatedBy' => 'ai',
                'editState' => is_string($existing['editState'] ?? null) ? (string) $existing['editState'] : 'ai-generated',
                'pageIds' => $pageIds,
                'componentIds' => $componentName !== '' ? [$componentName] : [],
                'activeGenerationRunId' => $activeGenerationRunId,
                'checksum' => $this->workspaceFileChecksum($project, $normalizedPath),
                'sectionLocalIds' => is_array($existing['sectionLocalIds'] ?? null) ? $existing['sectionLocalIds'] : [],
                'componentKeys' => array_values(array_unique(array_filter([
                    ...(is_array($existing['componentKeys'] ?? null) ? $existing['componentKeys'] : []),
                    ...$componentKeys,
                ]))),
                'originatingPageId' => $originatingPageId,
                'originatingPageSlug' => $originatingPageSlug,
                'lastEditor' => is_string($existing['lastEditor'] ?? null) ? (string) $existing['lastEditor'] : 'ai',
                'dirty' => false,
                'updatedAt' => is_string($existing['updatedAt'] ?? null) ? (string) $existing['updatedAt'] : null,
                'locked' => isset($existing['locked']) ? (bool) $existing['locked'] : $this->isLockedWorkspaceFile($normalizedPath),
                'templateOwned' => isset($existing['templateOwned'])
                    ? (bool) $existing['templateOwned']
                    : $this->isTemplateOwnedWorkspaceFile($normalizedPath, $meta),
                'lastOperationId' => is_string($existing['lastOperationId'] ?? null) ? (string) $existing['lastOperationId'] : null,
                'lastOperationKind' => is_string($existing['lastOperationKind'] ?? null) ? (string) $existing['lastOperationKind'] : null,
                'cmsBacked' => isset($meta['cms_backed']) ? (bool) $meta['cms_backed'] : (bool) ($existing['cmsBacked'] ?? true),
                'contentOwner' => is_string($meta['content_owner'] ?? null)
                    ? (string) $meta['content_owner']
                    : (is_string($existing['contentOwner'] ?? null) ? (string) $existing['contentOwner'] : 'mixed'),
                'cmsFieldPaths' => array_values(array_unique(array_filter([
                    ...((is_array($existing['cmsFieldPaths'] ?? null) ? $existing['cmsFieldPaths'] : [])),
                    ...(is_array($meta['content_field_paths'] ?? null) ? $meta['content_field_paths'] : []),
                ]))),
                'visualFieldPaths' => array_values(array_unique(array_filter([
                    ...((is_array($existing['visualFieldPaths'] ?? null) ? $existing['visualFieldPaths'] : [])),
                    ...(is_array($meta['visual_field_paths'] ?? null) ? $meta['visual_field_paths'] : []),
                ]))),
                'codeFieldPaths' => array_values(array_unique(array_filter([
                    ...((is_array($existing['codeFieldPaths'] ?? null) ? $existing['codeFieldPaths'] : [])),
                    ...(is_array($meta['code_field_paths'] ?? null) ? $meta['code_field_paths'] : []),
                ]))),
                'syncDirection' => is_string($meta['sync_direction'] ?? null)
                    ? (string) $meta['sync_direction']
                    : (is_string($existing['syncDirection'] ?? null) ? (string) $existing['syncDirection'] : 'cms_to_workspace'),
                'conflictStatus' => is_string($meta['conflict_status'] ?? null)
                    ? (string) $meta['conflict_status']
                    : (is_string($existing['conflictStatus'] ?? null) ? (string) $existing['conflictStatus'] : 'clean'),
            ];
        }

        usort($entries, static fn (array $left, array $right): int => strcmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? '')));

        return array_values($entries);
    }

    /**
     * @param  array<string, mixed>  $projection
     * @param  array<string, mixed>  $existingManifest
     * @return array<int, array<string, mixed>>
     */
    private function buildWorkspaceManifestComponentProvenanceEntries(
        array $projection,
        array $existingManifest,
        ?string $activeGenerationRunId
    ): array {
        $pageIdBySlug = [];
        foreach ($this->buildWorkspaceManifestGeneratedPages($projection) as $page) {
            $slug = is_string($page['slug'] ?? null) ? trim((string) $page['slug']) : '';
            if ($slug !== '') {
                $pageIdBySlug[$slug] = isset($page['pageId']) ? (string) $page['pageId'] : null;
            }
        }

        $existingEntries = [];
        foreach (is_array($existingManifest['componentProvenance'] ?? null) ? $existingManifest['componentProvenance'] : [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $componentId = is_string($entry['componentId'] ?? null) ? trim((string) $entry['componentId']) : '';
            if ($componentId !== '') {
                $existingEntries[$componentId] = $entry;
            }
        }

        $components = [
            ...(is_array($projection['components'] ?? null) ? $projection['components'] : []),
            ...(is_array($projection['layouts'] ?? null) ? $projection['layouts'] : []),
        ];

        $entries = [];
        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }

            $componentName = is_string($component['component_name'] ?? null) ? trim((string) $component['component_name']) : '';
            $path = is_string($component['path'] ?? null) ? trim((string) $component['path']) : '';
            if ($componentName === '' && $path === '') {
                continue;
            }

            $componentId = $componentName !== '' ? $componentName : $path;
            $existing = is_array($existingEntries[$componentId] ?? null) ? $existingEntries[$componentId] : [];
            $pageSlug = is_array($component['pages'] ?? null) && is_string($component['pages'][0] ?? null)
                ? trim((string) $component['pages'][0])
                : null;
            $registryKey = is_array($component['types'] ?? null) && is_string($component['types'][0] ?? null)
                ? trim((string) $component['types'][0])
                : ($componentName !== '' ? $componentName : null);

            $entries[] = [
                'componentId' => $componentId,
                'registryKey' => $registryKey !== '' ? $registryKey : null,
                'pageId' => $pageSlug !== null && isset($pageIdBySlug[$pageSlug]) ? $pageIdBySlug[$pageSlug] : null,
                'sectionId' => is_array($component['local_ids'] ?? null) && is_string($component['local_ids'][0] ?? null)
                    ? trim((string) $component['local_ids'][0])
                    : null,
                'source' => 'ai',
                'filePaths' => $path !== '' ? [$path] : (is_array($existing['filePaths'] ?? null) ? $existing['filePaths'] : []),
                'runId' => $activeGenerationRunId,
                'lastEditor' => is_string($existing['lastEditor'] ?? null) ? (string) $existing['lastEditor'] : 'ai',
            ];
        }

        usort($entries, static fn (array $left, array $right): int => strcmp((string) ($left['componentId'] ?? ''), (string) ($right['componentId'] ?? '')));

        return array_values($entries);
    }

    /**
     * @param  array<string, mixed>  $projectionMeta
     */
    private function resolveWorkspaceManifestOwnerId(string $path, array $projectionMeta, ?string $originatingPageId, ?string $originatingPageSlug): ?string
    {
        if ($this->inferWorkspaceOwnerType($path) === 'page') {
            return $originatingPageId ?? $originatingPageSlug ?? $path;
        }

        if ($this->inferWorkspaceOwnerType($path) === 'component' || $this->inferWorkspaceOwnerType($path) === 'layout') {
            $componentName = is_string($projectionMeta['component_name'] ?? null) ? trim((string) $projectionMeta['component_name']) : '';

            return $componentName !== '' ? $componentName : $path;
        }

        return $path;
    }

    private function workspaceFileChecksum(Project $project, string $path): ?string
    {
        $content = $this->readFile($project, $path);

        return is_string($content) ? sha1($content) : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function readWorkspaceManifestDocument(Project $project): array
    {
        $root = $this->ensureWorkspaceRoot($project);
        $path = $root.'/'.self::WORKSPACE_MANIFEST_FILE;
        if (! is_file($path)) {
            return $this->defaultWorkspaceManifestDocument($project, $root);
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            return $this->defaultWorkspaceManifestDocument($project, $root);
        }

        $decoded['schemaVersion'] = 1;
        $decoded['projectId'] = (string) ($decoded['projectId'] ?? $project->id);
        $decoded['rootDir'] = (string) ($decoded['rootDir'] ?? $root);
        $decoded['manifestPath'] = (string) ($decoded['manifestPath'] ?? self::WORKSPACE_MANIFEST_FILE);
        $decoded['generatedPages'] = is_array($decoded['generatedPages'] ?? null) ? $decoded['generatedPages'] : [];
        $decoded['fileOwnership'] = is_array($decoded['fileOwnership'] ?? null) ? $decoded['fileOwnership'] : [];
        $decoded['componentProvenance'] = is_array($decoded['componentProvenance'] ?? null) ? $decoded['componentProvenance'] : [];
        $decoded['preview'] = is_array($decoded['preview'] ?? null) ? $decoded['preview'] : $this->defaultWorkspaceManifestPreview();
        $decoded['cmsBinding'] = is_array($decoded['cmsBinding'] ?? null) ? $decoded['cmsBinding'] : null;
        $decoded['updatedAt'] = is_string($decoded['updatedAt'] ?? null) ? $decoded['updatedAt'] : null;

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultWorkspaceManifestDocument(Project $project, string $root): array
    {
        return [
            'schemaVersion' => 1,
            'projectId' => (string) $project->id,
            'rootDir' => $root,
            'manifestPath' => self::WORKSPACE_MANIFEST_FILE,
            'activeGenerationRunId' => null,
            'generatedPages' => [],
            'fileOwnership' => [],
            'componentProvenance' => [],
            'preview' => $this->defaultWorkspaceManifestPreview(),
            'cmsBinding' => null,
            'updatedAt' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultWorkspaceManifestPreview(): array
    {
        return [
            'ready' => false,
            'phase' => 'idle',
            'buildId' => null,
            'previewUrl' => null,
            'artifactHash' => null,
            'workspaceHash' => null,
            'builtAt' => null,
            'errorMessage' => null,
        ];
    }

    private function writeWorkspaceManifestDocument(Project $project, array $manifest): void
    {
        $root = $this->ensureWorkspaceRoot($project);
        $path = $root.'/'.self::WORKSPACE_MANIFEST_FILE;
        File::ensureDirectoryExists(dirname($path), 0775, true);
        File::put($path, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @return array<string, mixed>
     */
    private function readWorkspaceOperationLogDocument(Project $project): array
    {
        $root = $this->ensureWorkspaceRoot($project);
        $path = $root.'/'.self::WORKSPACE_OPERATION_LOG_FILE;
        if (! is_file($path)) {
            return [
                'schemaVersion' => 1,
                'projectId' => (string) $project->id,
                'entries' => [],
                'updatedAt' => null,
            ];
        }

        $decoded = json_decode((string) File::get($path), true);
        if (! is_array($decoded)) {
            return [
                'schemaVersion' => 1,
                'projectId' => (string) $project->id,
                'entries' => [],
                'updatedAt' => null,
            ];
        }

        return [
            'schemaVersion' => 1,
            'projectId' => (string) ($decoded['projectId'] ?? $project->id),
            'entries' => is_array($decoded['entries'] ?? null) ? $decoded['entries'] : [],
            'updatedAt' => is_string($decoded['updatedAt'] ?? null) ? $decoded['updatedAt'] : null,
        ];
    }

    private function writeWorkspaceOperationLogDocument(Project $project, array $log): void
    {
        $root = $this->ensureWorkspaceRoot($project);
        $path = $root.'/'.self::WORKSPACE_OPERATION_LOG_FILE;
        File::ensureDirectoryExists(dirname($path), 0775, true);
        File::put($path, json_encode($log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function recordWorkspaceMutation(Project $project, array $context): void
    {
        $root = $this->ensureWorkspaceRoot($project);
        $manifest = $this->readWorkspaceManifestDocument($project);
        $log = $this->readWorkspaceOperationLogDocument($project);
        $projectionCatalog = $this->workspaceProjectionFileCatalog($this->readWorkspaceProjection($project));
        $normalized = $this->normalizeWorkspaceMutationContext($context);
        $timestamp = now()->toIso8601String();

        $manifest['updatedAt'] = $timestamp;
        $manifest['rootDir'] = $root;
        $manifest['projectId'] = (string) $project->id;
        $manifest['manifestPath'] = self::WORKSPACE_MANIFEST_FILE;
        $manifest['preview'] = is_array($manifest['preview'] ?? null) ? $manifest['preview'] : $this->defaultWorkspaceManifestPreview();
        $manifest['preview']['ready'] = false;
        $manifest['preview']['phase'] = 'building_preview';
        $manifest['preview']['builtAt'] = null;
        $manifest['preview']['errorMessage'] = null;

        $targetPath = $normalized['path'];
        $previousPath = $normalized['previous_path'];
        $currentOwnershipEntries = is_array($manifest['fileOwnership'] ?? null) ? $manifest['fileOwnership'] : [];
        $existingEntry = null;
        foreach ($currentOwnershipEntries as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $entryPath = (string) ($entry['path'] ?? '');
            if ($entryPath === $targetPath || ($previousPath !== null && $entryPath === $previousPath)) {
                $existingEntry = $entry;
                break;
            }
        }

        if (is_string($previousPath) && $previousPath !== '' && $previousPath !== $targetPath) {
            $manifest['fileOwnership'] = array_values(array_filter(
                $currentOwnershipEntries,
                static fn ($entry): bool => is_array($entry) && (string) ($entry['path'] ?? '') !== $previousPath
            ));
        }

        if ($normalized['operation_kind'] === 'delete_file') {
            $manifest['fileOwnership'] = array_values(array_filter(
                is_array($manifest['fileOwnership'] ?? null) ? $manifest['fileOwnership'] : [],
                static fn ($entry): bool => is_array($entry) && (string) ($entry['path'] ?? '') !== $targetPath
            ));
        } else {
            $manifest['fileOwnership'] = $this->upsertWorkspaceManifestOwnershipEntry(
                is_array($manifest['fileOwnership'] ?? null) ? $manifest['fileOwnership'] : [],
                $this->buildWorkspaceManifestOwnershipEntry(
                    $project,
                    $targetPath,
                    is_array($projectionCatalog[$targetPath] ?? null) ? $projectionCatalog[$targetPath] : [],
                    $normalized,
                    $timestamp,
                    is_array($existingEntry) ? $existingEntry : []
                )
            );
        }

        $operationId = $this->buildWorkspaceOperationId($targetPath, $normalized['operation_kind'], $timestamp);
        $logEntry = [
            'id' => $operationId,
            'timestamp' => $timestamp,
            'actor' => $normalized['actor'],
            'source' => $normalized['source'],
            'operation_kind' => $normalized['operation_kind'],
            'path' => $targetPath,
            'previous_path' => $previousPath,
            'reason' => $normalized['reason'],
            'preview_refresh_requested' => $normalized['preview_refresh_requested'],
            'before' => $this->buildWorkspaceOperationFileSnapshot($normalized['before_content']),
            'after' => $this->buildWorkspaceOperationFileSnapshot($normalized['after_content']),
        ];

        $log['entries'] = array_values(array_slice([
            $logEntry,
            ...(is_array($log['entries'] ?? null) ? $log['entries'] : []),
        ], 0, self::WORKSPACE_OPERATION_LOG_LIMIT));
        $log['updatedAt'] = $timestamp;
        $log['projectId'] = (string) $project->id;
        $log['schemaVersion'] = 1;

        $this->writeWorkspaceManifestDocument($project, $manifest);
        $this->writeWorkspaceOperationLogDocument($project, $log);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{
     *     actor: string,
     *     source: string,
     *     operation_kind: string,
     *     path: string,
     *     previous_path: string|null,
     *     preview_refresh_requested: bool,
     *     reason: string|null,
     *     before_content: string|null,
     *     after_content: string|null
     * }
     */
    private function normalizeWorkspaceMutationContext(array $context): array
    {
        $actor = is_string($context['actor'] ?? null) ? trim((string) $context['actor']) : 'user';
        if (! in_array($actor, ['ai', 'visual_builder', 'user', 'system'], true)) {
            $actor = 'user';
        }

        $source = is_string($context['source'] ?? null) && trim((string) $context['source']) !== ''
            ? trim((string) $context['source'])
            : 'workspace_file_api';
        $operationKind = is_string($context['operation_kind'] ?? null) && trim((string) $context['operation_kind']) !== ''
            ? trim((string) $context['operation_kind'])
            : 'update_file';

        $path = PathRules::normalizePath((string) ($context['path'] ?? ''));
        $previousPath = isset($context['previous_path']) && is_string($context['previous_path']) && trim((string) $context['previous_path']) !== ''
            ? PathRules::normalizePath((string) $context['previous_path'])
            : null;

        return [
            'actor' => $actor,
            'source' => $source,
            'operation_kind' => $operationKind,
            'path' => $path,
            'previous_path' => $previousPath,
            'preview_refresh_requested' => (bool) ($context['preview_refresh_requested'] ?? true),
            'reason' => isset($context['reason']) && is_string($context['reason']) && trim((string) $context['reason']) !== ''
                ? trim((string) $context['reason'])
                : null,
            'before_content' => isset($context['before_content']) && is_string($context['before_content']) ? $context['before_content'] : null,
            'after_content' => isset($context['after_content']) && is_string($context['after_content']) ? $context['after_content'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $projectionMeta
     * @param  array<string, mixed>  $mutation
     * @param  array<string, mixed>  $existingEntry
     * @return array<string, mixed>
     */
    private function buildWorkspaceManifestOwnershipEntry(
        Project $project,
        string $path,
        array $projectionMeta,
        array $mutation,
        string $timestamp,
        array $existingEntry = []
    ): array {
        $root = $this->ensureWorkspaceRoot($project);
        $existingGeneratedBy = is_string($existingEntry['generatedBy'] ?? null) ? (string) $existingEntry['generatedBy'] : null;
        $existingEditState = is_string($existingEntry['editState'] ?? null) ? (string) $existingEntry['editState'] : null;
        $generatedBy = $existingGeneratedBy ?? match ($mutation['actor']) {
            'ai' => 'ai',
            'system' => 'system',
            default => 'user',
        };
        $editState = match (true) {
            $existingEditState === 'mixed' => 'mixed',
            $generatedBy === 'ai' && in_array($mutation['actor'], ['user', 'visual_builder'], true) => 'mixed',
            $mutation['actor'] === 'ai', $mutation['actor'] === 'system' => 'ai-generated',
            default => 'user-edited',
        };
        $pathOnDisk = $root.'/'.ltrim($path, '/');
        $afterContent = $mutation['after_content'];

        return [
            'path' => $path,
            'kind' => $this->inferWorkspaceFileKind($path),
            'ownerType' => $this->inferWorkspaceOwnerType($path),
            'ownerId' => (string) ($existingEntry['ownerId'] ?? $path),
            'generatedBy' => $generatedBy,
            'editState' => $editState,
            'pageIds' => is_array($existingEntry['pageIds'] ?? null) ? $existingEntry['pageIds'] : [],
            'componentIds' => is_array($existingEntry['componentIds'] ?? null) ? $existingEntry['componentIds'] : [],
            'activeGenerationRunId' => is_string($existingEntry['activeGenerationRunId'] ?? null) ? (string) $existingEntry['activeGenerationRunId'] : null,
            'checksum' => is_string($afterContent) ? sha1($afterContent) : (is_file($pathOnDisk) ? sha1((string) File::get($pathOnDisk)) : null),
            'sectionLocalIds' => is_array($existingEntry['sectionLocalIds'] ?? null) ? $existingEntry['sectionLocalIds'] : [],
            'componentKeys' => is_array($existingEntry['componentKeys'] ?? null) ? $existingEntry['componentKeys'] : [],
            'originatingPageId' => isset($existingEntry['originatingPageId']) ? $existingEntry['originatingPageId'] : null,
            'originatingPageSlug' => is_string($existingEntry['originatingPageSlug'] ?? null)
                ? (string) $existingEntry['originatingPageSlug']
                : (is_string($projectionMeta['page_slug'] ?? null) ? (string) $projectionMeta['page_slug'] : null),
            'lastEditor' => $mutation['actor'],
            'dirty' => true,
            'updatedAt' => $timestamp,
            'locked' => isset($existingEntry['locked']) ? (bool) $existingEntry['locked'] : $this->isLockedWorkspaceFile($path),
            'templateOwned' => isset($existingEntry['templateOwned'])
                ? (bool) $existingEntry['templateOwned']
                : $this->isTemplateOwnedWorkspaceFile($path, $projectionMeta),
            'lastOperationId' => $this->buildWorkspaceOperationId($path, $mutation['operation_kind'], $timestamp),
            'lastOperationKind' => $mutation['operation_kind'],
            'cmsBacked' => isset($existingEntry['cmsBacked'])
                ? (bool) $existingEntry['cmsBacked']
                : (isset($projectionMeta['cms_backed']) ? (bool) $projectionMeta['cms_backed'] : true),
            'contentOwner' => is_string($existingEntry['contentOwner'] ?? null)
                ? (string) $existingEntry['contentOwner']
                : (is_string($projectionMeta['content_owner'] ?? null) ? (string) $projectionMeta['content_owner'] : 'mixed'),
            'cmsFieldPaths' => is_array($existingEntry['cmsFieldPaths'] ?? null)
                ? $existingEntry['cmsFieldPaths']
                : (is_array($projectionMeta['content_field_paths'] ?? null) ? $projectionMeta['content_field_paths'] : []),
            'visualFieldPaths' => is_array($existingEntry['visualFieldPaths'] ?? null)
                ? $existingEntry['visualFieldPaths']
                : (is_array($projectionMeta['visual_field_paths'] ?? null) ? $projectionMeta['visual_field_paths'] : []),
            'codeFieldPaths' => is_array($existingEntry['codeFieldPaths'] ?? null)
                ? $existingEntry['codeFieldPaths']
                : (is_array($projectionMeta['code_field_paths'] ?? null) ? $projectionMeta['code_field_paths'] : []),
            'syncDirection' => is_string($existingEntry['syncDirection'] ?? null)
                ? (string) $existingEntry['syncDirection']
                : (is_string($projectionMeta['sync_direction'] ?? null) ? (string) $projectionMeta['sync_direction'] : 'cms_to_workspace'),
            'conflictStatus' => is_string($existingEntry['conflictStatus'] ?? null)
                ? (string) $existingEntry['conflictStatus']
                : (is_string($projectionMeta['conflict_status'] ?? null) ? (string) $projectionMeta['conflict_status'] : 'clean'),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<string, mixed>  $entry
     * @return array<int, array<string, mixed>>
     */
    private function upsertWorkspaceManifestOwnershipEntry(array $entries, array $entry): array
    {
        $next = [];
        $updated = false;

        foreach ($entries as $existing) {
            if (! is_array($existing)) {
                continue;
            }

            if ((string) ($existing['path'] ?? '') === (string) ($entry['path'] ?? '')) {
                $next[] = [
                    ...$existing,
                    ...$entry,
                    'sectionLocalIds' => array_values(array_unique(array_filter([
                        ...((is_array($existing['sectionLocalIds'] ?? null) ? $existing['sectionLocalIds'] : [])),
                        ...((is_array($entry['sectionLocalIds'] ?? null) ? $entry['sectionLocalIds'] : [])),
                    ]))),
                    'componentKeys' => array_values(array_unique(array_filter([
                        ...((is_array($existing['componentKeys'] ?? null) ? $existing['componentKeys'] : [])),
                        ...((is_array($entry['componentKeys'] ?? null) ? $entry['componentKeys'] : [])),
                    ]))),
                    'cmsFieldPaths' => array_values(array_unique(array_filter([
                        ...((is_array($existing['cmsFieldPaths'] ?? null) ? $existing['cmsFieldPaths'] : [])),
                        ...((is_array($entry['cmsFieldPaths'] ?? null) ? $entry['cmsFieldPaths'] : [])),
                    ]))),
                    'visualFieldPaths' => array_values(array_unique(array_filter([
                        ...((is_array($existing['visualFieldPaths'] ?? null) ? $existing['visualFieldPaths'] : [])),
                        ...((is_array($entry['visualFieldPaths'] ?? null) ? $entry['visualFieldPaths'] : [])),
                    ]))),
                    'codeFieldPaths' => array_values(array_unique(array_filter([
                        ...((is_array($existing['codeFieldPaths'] ?? null) ? $existing['codeFieldPaths'] : [])),
                        ...((is_array($entry['codeFieldPaths'] ?? null) ? $entry['codeFieldPaths'] : [])),
                    ]))),
                ];
                $updated = true;
                continue;
            }

            $next[] = $existing;
        }

        if (! $updated) {
            $next[] = $entry;
        }

        usort($next, static fn (array $left, array $right): int => strcmp((string) ($left['path'] ?? ''), (string) ($right['path'] ?? '')));

        return array_values($next);
    }

    private function inferWorkspaceFileKind(string $path): string
    {
        if ($path === self::WORKSPACE_MANIFEST_FILE) {
            return 'manifest';
        }
        if (str_starts_with($path, 'src/pages/')) {
            return 'page';
        }
        if (str_starts_with($path, 'src/layouts/')) {
            return 'layout';
        }
        if (str_starts_with($path, 'src/sections/') || str_starts_with($path, 'src/components/')) {
            return 'component';
        }
        if (str_starts_with($path, 'src/styles/')) {
            return 'style';
        }
        if (str_starts_with($path, 'public/')) {
            return 'asset';
        }

        return 'other';
    }

    private function inferWorkspaceOwnerType(string $path): string
    {
        return match ($this->inferWorkspaceFileKind($path)) {
            'page' => 'page',
            'layout' => 'layout',
            'component' => 'component',
            'asset' => 'asset',
            default => 'project',
        };
    }

    /**
     * @param  array<string, mixed>  $projectionMeta
     */
    private function isTemplateOwnedWorkspaceFile(string $path, array $projectionMeta): bool
    {
        return $projectionMeta !== []
            || $path === 'src/components/Header.tsx'
            || $path === 'src/components/Footer.tsx'
            || str_starts_with($path, 'src/pages/')
            || str_starts_with($path, 'src/sections/')
            || str_starts_with($path, 'src/layouts/');
    }

    private function isLockedWorkspaceFile(string $path): bool
    {
        return in_array($path, self::PROTECTED_WORKSPACE_FILES, true);
    }

    private function buildWorkspaceOperationId(string $path, string $operationKind, string $timestamp): string
    {
        return sha1($path.'|'.$operationKind.'|'.$timestamp);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildWorkspaceOperationFileSnapshot(?string $content): array
    {
        if ($content === null) {
            return [
                'exists' => false,
                'checksum' => null,
                'size' => 0,
                'line_count' => 0,
            ];
        }

        return [
            'exists' => true,
            'checksum' => sha1($content),
            'size' => strlen($content),
            'line_count' => substr_count($content, "\n") + 1,
        ];
    }

    private function writeStructure(string $root, array $structure, bool $overwrite): void
    {
        foreach ($structure as $key => $value) {
            $path = $root.'/'.$key;
            if (is_array($value)) {
                if (! is_dir($path)) {
                    File::ensureDirectoryExists($path, 0775, true);
                }
                $this->writeStructure($path, $value, $overwrite);
            } else {
                $this->writeFileIfMissing($path, (string) $value, $overwrite);
            }
        }
    }

    private function writeFileIfMissing(string $path, string $content, bool $overwrite): void
    {
        $dir = dirname($path);
        if (! is_dir($dir)) {
            File::ensureDirectoryExists($dir, 0775, true);
        }
        if ($overwrite || ! is_file($path)) {
            File::put($path, $content);
        }
    }

    private function hasWorkspaceScaffold(string $root): bool
    {
        $requiredFiles = [
            'package.json',
            'src/main.tsx',
            'src/App.tsx',
            'src/layouts/SiteLayout.tsx',
            'src/styles/globals.css',
        ];

        foreach ($requiredFiles as $relativePath) {
            if (! is_file($root.'/'.$relativePath)) {
                return false;
            }
        }

        return true;
    }

    private function hasWorkspacePageFiles(string $root): bool
    {
        return count(File::glob($root.'/src/pages/*/Page.tsx') ?: []) > 0;
    }

    private function hasWorkspaceSectionFiles(string $root): bool
    {
        return count(File::glob($root.'/src/sections/*.tsx') ?: []) > 0;
    }

    private function buildPageTsx(string $slug, array $sections, Site $site, array $pageProjection = []): string
    {
        $renderSections = $this->mapSectionsForPage($sections);
        $componentNames = array_values(array_unique(array_map(
            static fn (array $section): string => $section['componentName'],
            $renderSections
        )));

        $imports = [];
        foreach ($componentNames as $name) {
            $imports[] = "import {$name} from '../../sections/{$name}';";
        }
        $imports[] = "import SiteLayout from '../../layouts/SiteLayout';";
        $importBlock = implode("\n", array_unique($imports));

        $layoutProps = $this->buildLayoutProps($site);
        $layoutLiteral = $layoutProps !== [] ? ' {...'.$this->toJsLiteral($layoutProps).'}' : '';

        $children = [];
        foreach ($renderSections as $section) {
            $props = $section['props'];
            $propsLiteral = $props !== [] ? ' {...'.$this->toJsLiteral($props).'}' : '';
            $children[] = "      <{$section['componentName']}{$propsLiteral} />";
        }
        $childrenBlock = implode("\n", $children);
        $pageName = 'Page'.ucfirst(preg_replace('/[^a-z0-9]/i', '', $slug) ?: 'Home');

        $content = <<<TSX
{$importBlock}

export default function {$pageName}() {
  return (
    <SiteLayout{$layoutLiteral}>
{$childrenBlock}
    </SiteLayout>
  );
}

TSX;

        return $this->withProjectionBanner($content, [
            'projection_role' => 'page',
            'projection_source' => 'cms-projection',
            'page_slug' => $slug,
            'section_components' => $componentNames,
            'section_local_ids' => array_values(array_map(
                static fn (array $section): string => (string) ($section['localId'] ?? ''),
                $renderSections
            )),
            'page_projection' => $pageProjection,
        ]);
    }

    /**
     * @param  array<int, array{componentName: string, type: string, localId: string, props: array<string, mixed>}>  $sectionDetails
     * @return array<string, mixed>
     */
    private function buildPageProjectionEntry(Page $page, string $slug, array $sectionDetails, array $pageBinding = []): array
    {
        $layoutFiles = [
            'src/layouts/SiteLayout.tsx',
            'src/components/Header.tsx',
            'src/components/Footer.tsx',
        ];

        return [
            'page_id' => $page->id,
            'slug' => $slug,
            'title' => (string) ($page->title ?? ''),
            'path' => 'src/pages/'.$slug.'/Page.tsx',
            'cms_binding' => $pageBinding,
            'cms_backed' => true,
            'content_owner' => is_string(data_get($pageBinding, 'page.content_owner'))
                ? (string) data_get($pageBinding, 'page.content_owner')
                : 'mixed',
            'content_field_paths' => $this->extractAuthorityFieldList($pageBinding, 'content_fields'),
            'visual_field_paths' => $this->extractAuthorityFieldList($pageBinding, 'visual_fields'),
            'code_field_paths' => $this->extractAuthorityFieldList($pageBinding, 'code_owned_fields'),
            'sync_direction' => is_string(data_get($pageBinding, 'page.sync_direction'))
                ? (string) data_get($pageBinding, 'page.sync_direction')
                : 'cms_to_workspace',
            'conflict_status' => is_string(data_get($pageBinding, 'page.conflict_status'))
                ? (string) data_get($pageBinding, 'page.conflict_status')
                : 'clean',
            'layout_files' => $layoutFiles,
            'section_files' => array_values(array_unique(array_map(
                static fn (array $section): string => 'src/sections/'.(string) ($section['componentName'] ?? '').'.tsx',
                array_filter($sectionDetails, static fn (array $section): bool => trim((string) ($section['componentName'] ?? '')) !== '')
            ))),
            'sections' => array_values(array_map(function (array $section): array {
                $props = is_array($section['props'] ?? null) ? $section['props'] : [];
                $componentName = (string) ($section['componentName'] ?? '');

                return [
                    'component_name' => $componentName,
                    'component_path' => $componentName !== '' ? 'src/sections/'.$componentName.'.tsx' : null,
                    'type' => (string) ($section['type'] ?? ''),
                    'local_id' => (string) ($section['localId'] ?? ''),
                    'prop_keys' => array_values(array_keys($props)),
                    'prop_paths' => $this->flattenProjectionPropPaths($props),
                    'sample_props' => $props,
                    'variants' => $this->extractVariantUsage($props),
                    'binding' => is_array($section['binding'] ?? null) ? $section['binding'] : [],
                    'cms_backed' => (bool) data_get($section, 'authority.cms_backed', true),
                    'content_owner' => is_string(data_get($section, 'authority.content_owner'))
                        ? (string) data_get($section, 'authority.content_owner')
                        : 'cms',
                    'content_field_paths' => is_array(data_get($section, 'authority.content_fields'))
                        ? data_get($section, 'authority.content_fields')
                        : [],
                    'visual_field_paths' => is_array(data_get($section, 'authority.visual_fields'))
                        ? data_get($section, 'authority.visual_fields')
                        : [],
                    'code_field_paths' => is_array(data_get($section, 'authority.code_owned_fields'))
                        ? data_get($section, 'authority.code_owned_fields')
                        : [],
                    'sync_direction' => is_string(data_get($section, 'authority.sync_direction'))
                        ? (string) data_get($section, 'authority.sync_direction')
                        : 'cms_to_workspace',
                    'conflict_status' => is_string(data_get($section, 'authority.conflict_status'))
                        ? (string) data_get($section, 'authority.conflict_status')
                        : 'clean',
                ];
            }, $sectionDetails)),
        ];
    }

    /**
     * @param  array<string, array<string, mixed>>  $current
     * @param  array<int, array{componentName: string, type: string, localId: string, props: array<string, mixed>}>  $sectionDetails
     * @return array<string, array<string, mixed>>
     */
    private function mergeUsedComponentProjectionMap(array $current, string $pageSlug, array $sectionDetails): array
    {
        foreach ($sectionDetails as $section) {
            $componentName = trim((string) ($section['componentName'] ?? ''));
            $type = trim((string) ($section['type'] ?? ''));
            $localId = trim((string) ($section['localId'] ?? ''));
            $props = is_array($section['props'] ?? null) ? $section['props'] : [];
            if ($componentName === '') {
                continue;
            }

            $entry = is_array($current[$componentName] ?? null) ? $current[$componentName] : [
                'component_name' => $componentName,
                'path' => 'src/sections/'.$componentName.'.tsx',
                'projection_role' => 'section',
                'projection_source' => 'cms-projection',
                'types' => [],
                'pages' => [],
                'page_paths' => [],
                'local_ids' => [],
                'prop_keys' => [],
                'prop_paths' => [],
                'sample_props' => [],
                'usage_count' => 0,
                'cms_backed' => true,
                'content_owner' => 'cms',
                'content_field_paths' => [],
                'visual_field_paths' => [],
                'code_field_paths' => [],
                'sync_direction' => 'cms_to_workspace',
                'conflict_status' => 'clean',
                'variants' => [
                    'layout' => [],
                    'style' => [],
                ],
            ];

            $entry['types'] = array_values(array_unique(array_filter([
                ...((is_array($entry['types'] ?? null) ? $entry['types'] : [])),
                $type,
            ])));
            $entry['pages'] = array_values(array_unique(array_filter([
                ...((is_array($entry['pages'] ?? null) ? $entry['pages'] : [])),
                $pageSlug,
            ])));
            $entry['page_paths'] = array_values(array_unique(array_filter([
                ...((is_array($entry['page_paths'] ?? null) ? $entry['page_paths'] : [])),
                'src/pages/'.$pageSlug.'/Page.tsx',
            ])));
            $entry['local_ids'] = array_values(array_unique(array_filter([
                ...((is_array($entry['local_ids'] ?? null) ? $entry['local_ids'] : [])),
                $localId,
            ])));
            $entry['prop_keys'] = array_values(array_unique(array_filter([
                ...((is_array($entry['prop_keys'] ?? null) ? $entry['prop_keys'] : [])),
                ...array_map(static fn ($key): string => (string) $key, array_keys($props)),
            ])));
            $entry['prop_paths'] = array_values(array_unique(array_filter([
                ...((is_array($entry['prop_paths'] ?? null) ? $entry['prop_paths'] : [])),
                ...$this->flattenProjectionPropPaths($props),
            ])));
            $entry['sample_props'] = $this->mergeProjectionSampleProps(
                is_array($entry['sample_props'] ?? null) ? $entry['sample_props'] : [],
                $props
            );
            $entry['usage_count'] = (int) ($entry['usage_count'] ?? 0) + 1;
            $entry['cms_backed'] = (bool) data_get($section, 'authority.cms_backed', true);
            $entry['content_owner'] = is_string(data_get($section, 'authority.content_owner'))
                ? (string) data_get($section, 'authority.content_owner')
                : (string) ($entry['content_owner'] ?? 'cms');
            $entry['content_field_paths'] = array_values(array_unique(array_filter([
                ...((is_array($entry['content_field_paths'] ?? null) ? $entry['content_field_paths'] : [])),
                ...(is_array(data_get($section, 'authority.content_fields')) ? data_get($section, 'authority.content_fields') : []),
            ])));
            $entry['visual_field_paths'] = array_values(array_unique(array_filter([
                ...((is_array($entry['visual_field_paths'] ?? null) ? $entry['visual_field_paths'] : [])),
                ...(is_array(data_get($section, 'authority.visual_fields')) ? data_get($section, 'authority.visual_fields') : []),
            ])));
            $entry['code_field_paths'] = array_values(array_unique(array_filter([
                ...((is_array($entry['code_field_paths'] ?? null) ? $entry['code_field_paths'] : [])),
                ...(is_array(data_get($section, 'authority.code_owned_fields')) ? data_get($section, 'authority.code_owned_fields') : []),
            ])));
            $entry['sync_direction'] = is_string(data_get($section, 'authority.sync_direction'))
                ? (string) data_get($section, 'authority.sync_direction')
                : (string) ($entry['sync_direction'] ?? 'cms_to_workspace');
            $entry['conflict_status'] = is_string(data_get($section, 'authority.conflict_status'))
                ? (string) data_get($section, 'authority.conflict_status')
                : (string) ($entry['conflict_status'] ?? 'clean');

            $variants = $this->extractVariantUsage($props);
            foreach (['layout', 'style'] as $variantKey) {
                $existing = is_array($entry['variants'][$variantKey] ?? null) ? $entry['variants'][$variantKey] : [];
                $incoming = is_array($variants[$variantKey] ?? null) ? $variants[$variantKey] : [];
                $entry['variants'][$variantKey] = array_values(array_unique(array_filter([
                    ...$existing,
                    ...$incoming,
                ])));
            }

            $current[$componentName] = $entry;
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>  $pageBinding
     * @return array<int, string>
     */
    private function extractAuthorityFieldList(array $pageBinding, string $key): array
    {
        $sections = is_array($pageBinding['sections'] ?? null) ? $pageBinding['sections'] : [];
        $collected = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $fields = is_array($section[$key] ?? null) ? $section[$key] : [];
            foreach ($fields as $field) {
                if (! is_string($field) || trim($field) === '') {
                    continue;
                }

                $collected[] = trim($field);
            }
        }

        return array_values(array_unique($collected));
    }

    /**
     * @param  array<int, array{type?: string, props?: array, localId?: string, binding?: array}>  $sections
     * @return array<int, array{componentName: string, type: string, localId: string, props: array<string, mixed>, binding: array<string, mixed>, authority: array<string, mixed>}>
     */
    private function mapSectionsForPage(array $sections): array
    {
        $mapped = [];

        foreach ($sections as $index => $section) {
            $type = trim((string) ($section['type'] ?? ''));
            if ($type === '') {
                continue;
            }

            $componentName = $this->componentNameForType($type);
            $rawProps = is_array($section['props'] ?? null) ? $section['props'] : [];
            $localId = trim((string) ($section['localId'] ?? ''));
            if ($localId === '') {
                $localId = 'section-'.($index + 1);
            }
            $binding = is_array($section['binding'] ?? null) ? $section['binding'] : [];
            $authority = is_array($binding['webu_v2'] ?? null) ? $binding['webu_v2'] : [];

            $normalizedProps = $this->normalizeSectionPropsForWorkspace($componentName, $rawProps);
            $props = array_merge($rawProps, $normalizedProps, ['sectionId' => $localId]);

            $mapped[] = [
                'componentName' => $componentName,
                'type' => $type,
                'localId' => $localId,
                'props' => $props,
                'binding' => $binding,
                'authority' => $authority,
            ];
        }

        return $mapped;
    }

    /**
     * @param  array<int, array{type?: string, props?: array}>  $sections
     * @return array<int, string>
     */
    private function sectionTypesToComponentNames(array $sections): array
    {
        $names = [];
        foreach ($sections as $section) {
            $type = trim((string) ($section['type'] ?? ''));
            if ($type === '') {
                continue;
            }
            $name = $this->componentNameForType($type);
            if (! in_array($name, $names, true)) {
                $names[] = $name;
            }
        }

        return $names;
    }

    private function componentNameForType(string $type): string
    {
        return self::SECTION_TYPE_TO_COMPONENT[strtolower($type)]
            ?? self::sectionTypeToPascalCase($type);
    }

    private static function sectionTypeToPascalCase(string $type): string
    {
        $parts = preg_split('/[-_\s]+/', $type, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $pascal = implode('', array_map(static fn (string $p): string => ucfirst(strtolower($p)), $parts));

        return $pascal !== '' ? $pascal.'Section' : 'Section';
    }

    private function ensureSectionComponentExists(string $root, string $componentName, bool $overwrite): void
    {
        $path = $root.'/src/sections/'.$componentName.'.tsx';
        if (! $overwrite && is_file($path)) {
            return;
        }
        $type = $this->componentNameToTemplateType($componentName);
        File::ensureDirectoryExists(dirname($path), 0775, true);
        File::put($path, $this->templateSection($componentName, $type));
    }

    /**
     * @param  array<string, mixed>  $layoutProjection
     */
    private function syncProjectionManagedLayoutFiles(string $root, Project $project, array $layoutProjection): void
    {
        $headerPath = $root.'/src/components/Header.tsx';
        if ($this->shouldWriteProjectionManagedFile($headerPath, 'Header')) {
            File::put($headerPath, $this->templateHeader($project, [
                'component_name' => 'Header',
                'projection_role' => 'layout-component',
                'projection_source' => 'cms-projection',
                'sample_props' => is_array($layoutProjection['header_props'] ?? null) ? $layoutProjection['header_props'] : [],
                'variants' => $this->extractVariantUsage(is_array($layoutProjection['header_props'] ?? null) ? $layoutProjection['header_props'] : []),
            ]));
        }

        $footerPath = $root.'/src/components/Footer.tsx';
        if ($this->shouldWriteProjectionManagedFile($footerPath, 'Footer')) {
            File::put($footerPath, $this->templateFooter($project, [
                'component_name' => 'Footer',
                'projection_role' => 'layout-component',
                'projection_source' => 'cms-projection',
                'sample_props' => is_array($layoutProjection['footer_props'] ?? null) ? $layoutProjection['footer_props'] : [],
                'variants' => $this->extractVariantUsage(is_array($layoutProjection['footer_props'] ?? null) ? $layoutProjection['footer_props'] : []),
            ]));
        }

        $layoutPath = $root.'/src/layouts/SiteLayout.tsx';
        if ($this->shouldWriteProjectionManagedFile($layoutPath, 'SiteLayout')) {
            File::put($layoutPath, $this->templateSiteLayout([
                'projection_role' => 'layout',
                'projection_source' => 'cms-projection',
                'uses_header' => true,
                'uses_footer' => true,
            ]));
        }
    }

    /**
     * @param  array<string, mixed>  $projection
     */
    private function syncProjectionManagedSectionFile(string $root, string $componentName, array $projection): void
    {
        $path = $root.'/src/sections/'.$componentName.'.tsx';
        if (! $this->shouldWriteProjectionManagedFile($path, $componentName)) {
            return;
        }

        File::ensureDirectoryExists(dirname($path), 0775, true);
        File::put($path, $this->templateSection($componentName, $this->componentNameToTemplateType($componentName), $projection));
    }

    /**
     * Remove unused CMS-projection files while preserving custom workspace code.
     *
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, array<string, mixed>>  $components
     * @param  array<int, array<string, mixed>>  $layouts
     */
    private function pruneUnusedProjectionManagedFiles(string $root, array $pages, array $components, array $layouts): void
    {
        $keep = [
            'src/App.tsx' => true,
            'src/main.tsx' => true,
        ];

        foreach ($pages as $page) {
            $path = is_string($page['path'] ?? null) ? trim((string) $page['path']) : '';
            if ($path !== '') {
                $keep[$path] = true;
            }
        }

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }
            $path = is_string($component['path'] ?? null) ? trim((string) $component['path']) : '';
            if ($path !== '') {
                $keep[$path] = true;
            }
        }

        foreach ($layouts as $layout) {
            if (! is_array($layout)) {
                continue;
            }
            $path = is_string($layout['path'] ?? null) ? trim((string) $layout['path']) : '';
            if ($path !== '') {
                $keep[$path] = true;
            }
        }

        foreach (['src/pages', 'src/sections', 'src/components', 'src/layouts'] as $directory) {
            $absoluteDirectory = $root.'/'.$directory;
            if (! is_dir($absoluteDirectory)) {
                continue;
            }

            foreach (File::allFiles($absoluteDirectory) as $file) {
                $relativePath = str_replace($root.'/', '', str_replace('\\', '/', $file->getPathname()));
                if (isset($keep[$relativePath])) {
                    continue;
                }

                if (! PathRules::isAllowed($relativePath)) {
                    continue;
                }

                $content = (string) File::get($file->getPathname());
                $componentName = basename($relativePath, '.tsx');
                $isDisposableProjection = $this->fileContainsProjectionMarker($file->getPathname())
                    || $this->shouldUpgradeLegacyScaffold($componentName, $content);
                if (! $isDisposableProjection) {
                    continue;
                }

                File::delete($file->getPathname());
            }
        }
    }

    /**
     * Write App.tsx that renders the current site pages (path-based routing).
     *
     * @param  \Illuminate\Database\Eloquent\Collection<int, Page>  $pages
     */
    private function writeAppTsxFromPages(string $root, $pages): void
    {
        $slugs = $pages->map(fn ($p) => preg_replace('/[^a-z0-9_-]/i', '_', (string) $p->slug) ?: 'home')->unique()->values()->all();
        if ($slugs === []) {
            $slugs = ['home'];
        }
        $imports = [];
        $cases = [];
        foreach ($slugs as $slug) {
            $pageName = 'Page'.ucfirst(preg_replace('/[^a-z0-9]/i', '', $slug) ?: 'Home');
            $imports[] = "import {$pageName} from './pages/{$slug}/Page';";
            $pathVar = $slug === 'home'
                ? "path === '' || path === '/home'"
                : "path === '/{$slug}'";
            $cases[] = "  if ({$pathVar}) return <{$pageName} />;";
        }
        $firstPageName = 'Page'.ucfirst(preg_replace('/[^a-z0-9]/i', '', $slugs[0]) ?: 'Home');
        $importBlock = implode("\n", $imports);
        $casesBlock = implode("\n", $cases);
        $appTsx = <<<TSX
{$importBlock}

export default function App() {
  const path = typeof window !== 'undefined' ? (window.location.pathname || '/').replace(/\/$/, '') || '/' : '/';
  {$casesBlock}
  return <{$firstPageName} />;
}
TSX;
        File::put($root.'/src/App.tsx', $appTsx);
    }

    private function templateHeader(Project $project, array $projection = []): string
    {
        $sampleProps = is_array($projection['sample_props'] ?? null) ? $projection['sample_props'] : [];
        $logoDefault = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['logoText', 'logo_text']) ?? (trim((string) $project->name) !== '' ? (string) $project->name : 'Webu')));
        $ctaTextDefault = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['ctaText', 'cta_text']) ?? 'Get Started'));
        $ctaLinkDefault = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['ctaLink', 'cta_link']) ?? '/contact'));
        $layoutVariantDefault = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['layoutVariant', 'layout_variant']) ?? 'default'));
        $styleVariantDefault = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['styleVariant', 'style_variant']) ?? 'light'));

        $content = <<<TSX
type HeaderMenuItem = { label?: string; href?: string };
type HeaderButton = { label?: string; href?: string; variant?: 'primary' | 'secondary' };

type HeaderProps = {
  sectionId?: string;
  logoUrl?: string;
  logoText?: string;
  menuItems?: HeaderMenuItem[];
  menu_items?: HeaderMenuItem[] | string;
  buttons?: HeaderButton[];
  sticky?: boolean;
  backgroundColor?: string;
  textColor?: string;
  layoutVariant?: 'default' | 'centered' | 'split';
  styleVariant?: 'light' | 'dark';
  menuLabel1?: string;
  menuLink1?: string;
  menuLabel2?: string;
  menuLink2?: string;
  menuLabel3?: string;
  menuLink3?: string;
  ctaText?: string;
  ctaLink?: string;
};

function parseHeaderItems(input: HeaderMenuItem[] | string | undefined): HeaderMenuItem[] {
  if (Array.isArray(input)) {
    return input;
  }

  if (typeof input !== 'string' || input.trim() === '') {
    return [];
  }

  try {
    const parsed = JSON.parse(input);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

export default function Header({
  sectionId = 'global-header',
  logoUrl = '',
  logoText = {$logoDefault},
  menuItems = [],
  menu_items = [],
  buttons = [],
  sticky = false,
  backgroundColor = '',
  textColor = '',
  layoutVariant = {$layoutVariantDefault},
  styleVariant = {$styleVariantDefault},
  menuLabel1 = 'Home',
  menuLink1 = '/home',
  menuLabel2 = 'About',
  menuLink2 = '/about',
  menuLabel3 = 'Contact',
  menuLink3 = '/contact',
  ctaText = {$ctaTextDefault},
  ctaLink = {$ctaLinkDefault},
}: HeaderProps) {
  const parsedMenuItems = menuItems.length > 0 ? menuItems : parseHeaderItems(menu_items);
  const resolvedMenuItems = parsedMenuItems.length > 0
    ? parsedMenuItems
    : [
        { label: menuLabel1, href: menuLink1 },
        { label: menuLabel2, href: menuLink2 },
        { label: menuLabel3, href: menuLink3 },
      ].filter((item) => (item.label ?? '').trim() !== '');
  const resolvedButtons = buttons.length > 0
    ? buttons
    : (ctaText ? [{ label: ctaText, href: ctaLink, variant: 'primary' as const }] : []);

  return (
    <header
      className={`site-header site-header--\${layoutVariant} site-header--\${styleVariant}`}
      data-webu-section="Header"
      data-webu-section-local-id={sectionId}
      style={{
        position: sticky ? 'sticky' : undefined,
        top: sticky ? 0 : undefined,
        backgroundColor: backgroundColor || undefined,
        color: textColor || undefined,
      }}
    >
      <div className="container site-header__inner">
        <div className="site-brand">
          {logoUrl ? <img src={logoUrl} alt={logoText} className="site-brand__logo" data-webu-field="logoUrl" /> : null}
          <span data-webu-field="logoText">{logoText}</span>
        </div>
        <nav className="site-nav" aria-label="Primary navigation">
          {resolvedMenuItems.map((item, index) => (
            <a
              key={index}
              href={item.href || '#'}
              data-webu-field={`menuItems.\${index}.label`}
              data-webu-field-url={`menuItems.\${index}.href`}
            >
              {item.label}
            </a>
          ))}
        </nav>
        <div className="site-header__actions">
          {resolvedButtons.map((button, index) => (
            <a
              key={index}
              className={button.variant === 'secondary' ? 'button-secondary button-secondary--compact' : 'button-primary button-primary--compact'}
              href={button.href || '#'}
              data-webu-field={`buttons.\${index}.label`}
              data-webu-field-url={`buttons.\${index}.href`}
            >
              {button.label}
            </a>
          ))}
        </div>
      </div>
    </header>
  );
}

TSX;

        return $this->withProjectionBanner($content, array_filter([
            'projection_role' => (string) ($projection['projection_role'] ?? 'layout-component'),
            'projection_source' => (string) ($projection['projection_source'] ?? 'cms-projection'),
            'component_name' => (string) ($projection['component_name'] ?? 'Header'),
            'observed_prop_keys' => array_values(array_keys($sampleProps)),
            'variants' => $projection['variants'] ?? null,
        ], static fn (mixed $value): bool => $value !== null));
    }

    private function templateFooter(Project $project, array $projection = []): string
    {
        $sampleProps = is_array($projection['sample_props'] ?? null) ? $projection['sample_props'] : [];
        $resolvedLogoText = (string) ($this->firstExistingValue($sampleProps, ['logoText', 'logo_text']) ?? (trim((string) $project->name) !== '' ? (string) $project->name : 'Webu'));
        $logoDefault = $this->toJsLiteral($resolvedLogoText);
        $copyrightDefault = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['copyrightText', 'copyright']) ?? ('© '.date('Y').' '.$resolvedLogoText)));
        $descriptionDefault = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['description', 'subtitle']) ?? 'Built with Webu.'));
        $styleVariantDefault = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['styleVariant', 'style_variant']) ?? 'dark'));

        $content = <<<TSX
type FooterItem = { label?: string; href?: string };

type FooterProps = {
  sectionId?: string;
  logoUrl?: string;
  logoText?: string;
  description?: string;
  links?: FooterItem[] | string;
  socialLinks?: FooterItem[] | string;
  backgroundColor?: string;
  textColor?: string;
  styleVariant?: 'light' | 'dark';
  linkLabel1?: string;
  linkHref1?: string;
  linkLabel2?: string;
  linkHref2?: string;
  contactEmail?: string;
  contactPhone?: string;
  copyrightText?: string;
};

function parseFooterItems(input: FooterItem[] | string | undefined): FooterItem[] {
  if (Array.isArray(input)) {
    return input;
  }

  if (typeof input !== 'string' || input.trim() === '') {
    return [];
  }

  try {
    const parsed = JSON.parse(input);
    return Array.isArray(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

export default function Footer({
  sectionId = 'global-footer',
  logoUrl = '',
  logoText = {$logoDefault},
  description = {$descriptionDefault},
  links = [],
  socialLinks = [],
  backgroundColor = '',
  textColor = '',
  styleVariant = {$styleVariantDefault},
  linkLabel1 = 'About',
  linkHref1 = '/about',
  linkLabel2 = 'Contact',
  linkHref2 = '/contact',
  contactEmail = 'hello@example.com',
  contactPhone = '+1 (555) 000-0000',
  copyrightText = {$copyrightDefault},
}: FooterProps) {
  const parsedLinks = parseFooterItems(links);
  const parsedSocialLinks = parseFooterItems(socialLinks);
  const resolvedLinks = parsedLinks.length > 0
    ? parsedLinks
    : [
        { label: linkLabel1, href: linkHref1 },
        { label: linkLabel2, href: linkHref2 },
      ].filter((item) => (item.label ?? '').trim() !== '');

  return (
    <footer
      className={`site-footer site-footer--\${styleVariant}`}
      data-webu-section="Footer"
      data-webu-section-local-id={sectionId}
      style={{
        backgroundColor: backgroundColor || undefined,
        color: textColor || undefined,
      }}
    >
      <div className="container site-footer__inner">
        <div className="site-footer__brand">
          <div className="site-brand">
            {logoUrl ? <img src={logoUrl} alt={logoText} className="site-brand__logo" data-webu-field="logoUrl" /> : null}
            <span data-webu-field="logoText">{logoText}</span>
          </div>
          <p className="site-footer__description" data-webu-field="description">{description}</p>
        </div>
        <div className="site-footer__links">
          {resolvedLinks.map((item, index) => (
            <a
              key={index}
              href={item.href || '#'}
              data-webu-field={`links.\${index}.label`}
              data-webu-field-url={`links.\${index}.href`}
            >
              {item.label}
            </a>
          ))}
        </div>
        <div className="site-footer__contact">
          <a href={`mailto:\${contactEmail}`} data-webu-field="contactEmail">{contactEmail}</a>
          <a href={`tel:\${contactPhone}`} data-webu-field="contactPhone">{contactPhone}</a>
          {parsedSocialLinks.map((item, index) => (
            <a
              key={index}
              href={item.href || '#'}
              data-webu-field={`socialLinks.\${index}.label`}
              data-webu-field-url={`socialLinks.\${index}.href`}
            >
              {item.label}
            </a>
          ))}
        </div>
      </div>
      <div className="container">
        <p className="site-footer__copyright" data-webu-field="copyrightText">{copyrightText}</p>
      </div>
    </footer>
  );
}

TSX;

        return $this->withProjectionBanner($content, array_filter([
            'projection_role' => (string) ($projection['projection_role'] ?? 'layout-component'),
            'projection_source' => (string) ($projection['projection_source'] ?? 'cms-projection'),
            'component_name' => (string) ($projection['component_name'] ?? 'Footer'),
            'observed_prop_keys' => array_values(array_keys($sampleProps)),
            'variants' => $projection['variants'] ?? null,
        ], static fn (mixed $value): bool => $value !== null));
    }

    private function templateSiteLayout(array $projection = []): string
    {
        $content = <<<'TSX'
import Header from '../components/Header';
import Footer from '../components/Footer';

type SiteLayoutProps = {
  children: React.ReactNode;
  headerProps?: React.ComponentProps<typeof Header>;
  footerProps?: React.ComponentProps<typeof Footer>;
};

export default function SiteLayout({ children, headerProps, footerProps }: SiteLayoutProps) {
  return (
    <div className="site-layout">
      <Header {...(headerProps ?? {})} />
      <main>{children}</main>
      <Footer {...(footerProps ?? {})} />
    </div>
  );
}

TSX;

        return $this->withProjectionBanner($content, array_filter([
            'projection_role' => (string) ($projection['projection_role'] ?? 'layout'),
            'projection_source' => (string) ($projection['projection_source'] ?? 'cms-projection'),
            'uses_header' => $projection['uses_header'] ?? true,
            'uses_footer' => $projection['uses_footer'] ?? true,
        ], static fn (mixed $value): bool => $value !== null));
    }

    private function templatePackageJson(Project $project): string
    {
        $name = strtolower((string) $project->id);

        return json_encode([
            'name' => 'webu-workspace-'.$name,
            'private' => true,
            'version' => '0.0.0',
            'type' => 'module',
            'scripts' => [
                'dev' => 'vite',
                'build' => 'vite build',
                'preview' => 'vite preview',
            ],
            'dependencies' => [
                'react' => '^18.3.1',
                'react-dom' => '^18.3.1',
            ],
            'devDependencies' => [
                '@types/react' => '^18.3.12',
                '@types/react-dom' => '^18.3.1',
                '@vitejs/plugin-react' => '^4.3.4',
                'typescript' => '^5.6.3',
                'vite' => '^5.4.10',
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n";
    }

    private function templateTsconfigJson(): string
    {
        return <<<'JSON'
{
  "compilerOptions": {
    "target": "ES2020",
    "useDefineForClassFields": true,
    "lib": ["DOM", "DOM.Iterable", "ES2020"],
    "allowJs": false,
    "skipLibCheck": true,
    "esModuleInterop": true,
    "allowSyntheticDefaultImports": true,
    "strict": false,
    "forceConsistentCasingInFileNames": true,
    "module": "ESNext",
    "moduleResolution": "Node",
    "resolveJsonModule": true,
    "isolatedModules": true,
    "noEmit": true,
    "jsx": "react-jsx"
  },
  "include": ["src"],
  "references": [{ "path": "./tsconfig.node.json" }]
}
JSON;
    }

    private function templateTsconfigNodeJson(): string
    {
        return <<<'JSON'
{
  "compilerOptions": {
    "composite": true,
    "module": "ESNext",
    "moduleResolution": "Node",
    "allowSyntheticDefaultImports": true
  },
  "include": ["vite.config.ts"]
}
JSON;
    }

    private function templateViteConfig(): string
    {
        return <<<'TS'
import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react()],
  server: {
    host: '0.0.0.0',
    port: 4173,
  },
});
TS;
    }

    private function templateIndexHtml(Project $project): string
    {
        $title = trim((string) $project->name) !== '' ? trim((string) $project->name) : 'Webu Workspace';
        $title = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{$title}</title>
  </head>
  <body>
    <div id="root"></div>
    <script type="module" src="/src/main.tsx"></script>
  </body>
</html>
HTML;
    }

    private function templateMainTsx(): string
    {
        return <<<'TSX'
import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import './styles/globals.css';

ReactDOM.createRoot(document.getElementById('root') as HTMLElement).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>
);
TSX;
    }

    private function templateAppTsx(): string
    {
        return <<<'TSX'
import HomePage from './pages/home/Page';

export default function App() {
  return <HomePage />;
}
TSX;
    }

    private function templateHomePage(): string
    {
        return <<<'TSX'
import SiteLayout from '../../layouts/SiteLayout';

export default function PageHome() {
  return (
    <SiteLayout>
      <section className="section" data-webu-section="WorkspaceHome" data-webu-section-local-id="workspace-home">
        <div className="container">
          <h1 data-webu-field="title">Start shaping your first page</h1>
          <p data-webu-field="subtitle">Add reusable sections from the builder or describe changes in chat to generate the first real layout.</p>
        </div>
      </section>
    </SiteLayout>
  );
}
TSX;
    }

    private function templateSection(string $name, string $type, array $projection = []): string
    {
        return match ($type) {
            'features' => $this->templateFeaturesSection($name, $projection),
            'testimonials' => $this->templateTestimonialsSection($name, $projection),
            'product_grid', 'featured_categories', 'category_list' => $this->templateProductGridSection($name, $type, $projection),
            'spacer' => $this->templateSpacerSection($name, $projection),
            'button' => $this->templateButtonSection($name, $projection),
            default => $this->templateGenericContentSection($name, $type, $projection),
        };
    }

    private function templateSpacerSection(string $name, array $projection = []): string
    {
        $sampleProps = is_array($projection['sample_props'] ?? null) ? $projection['sample_props'] : [];
        $heightDefault = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['height']) ?? '40px'));
        $heightMobileDefault = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['height_mobile']) ?? '24px'));
        $content = <<<TSX
type {$name}Props = {
  sectionId?: string;
  height?: string;
  height_mobile?: string;
};

export default function {$name}({
  sectionId = 'spacer-section',
  height = {$heightDefault},
  height_mobile = {$heightMobileDefault},
}: {$name}Props) {
  return (
    <section
      className="section section-spacer"
      data-webu-section="{$name}"
      data-webu-section-local-id={sectionId}
      style={{ minHeight: height }}
      aria-hidden
    >
      <div data-webu-field="height" style={{ height, minHeight: height }} />
    </section>
  );
}
TSX;

        return $this->withProjectionBanner($content, $this->buildSectionProjectionMetadata($name, $projection));
    }

    private function templateButtonSection(string $name, array $projection = []): string
    {
        $sampleProps = is_array($projection['sample_props'] ?? null) ? $projection['sample_props'] : [];
        $titleDefault = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['title', 'headline']) ?? ''));
        $buttonTextDefault = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['buttonText', 'button_text', 'ctaText']) ?? 'Learn more'));
        $buttonLinkDefault = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['buttonLink', 'button_link', 'ctaLink']) ?? '/contact'));
        $alignmentDefault = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['alignment']) ?? 'center'));
        $content = <<<TSX
type {$name}Props = {
  sectionId?: string;
  title?: string;
  buttonText?: string;
  buttonLink?: string;
  alignment?: 'left' | 'center' | 'right';
};

export default function {$name}({
  sectionId = 'button-section',
  title = {$titleDefault},
  buttonText = {$buttonTextDefault},
  buttonLink = {$buttonLinkDefault},
  alignment = {$alignmentDefault},
}: {$name}Props) {
  return (
    <section
      className="section section-button"
      data-webu-section="{$name}"
      data-webu-section-local-id={sectionId}
      style={{ textAlign: alignment }}
    >
      <div className="container">
        {title ? <h2 className="section-title" data-webu-field="title">{title}</h2> : null}
        <a className="button-primary" href={buttonLink || '#'} data-webu-field="buttonText" data-webu-field-url="buttonLink">
          {buttonText}
        </a>
      </div>
    </section>
  );
}
TSX;

        return $this->withProjectionBanner($content, $this->buildSectionProjectionMetadata($name, $projection));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLayoutProps(Site $site): array
    {
        $siteName = trim((string) $site->name) !== '' ? trim((string) $site->name) : 'Webu';
        $themeSettings = is_array($site->theme_settings) ? $site->theme_settings : [];
        $layout = is_array($themeSettings['layout'] ?? null) ? $themeSettings['layout'] : [];

        $headerProps = is_array($layout['header_props'] ?? null) ? $layout['header_props'] : [];
        $footerProps = is_array($layout['footer_props'] ?? null) ? $layout['footer_props'] : [];

        return [
            'headerProps' => array_merge([
                'sectionId' => 'global-header',
                'logoText' => $siteName,
            ], $this->normalizeFixedComponentPropsForWorkspace('header', $headerProps)),
            'footerProps' => array_merge([
                'sectionId' => 'global-footer',
                'logoText' => $siteName,
                'copyrightText' => '© '.date('Y').' '.$siteName,
            ], $this->normalizeFixedComponentPropsForWorkspace('footer', $footerProps)),
        ];
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private function normalizeFixedComponentPropsForWorkspace(string $kind, array $props): array
    {
        $mapped = [];

        if ($kind === 'header') {
            $mapped['logoUrl'] = $this->firstExistingValue($props, ['logoUrl', 'logo_url']);
            $mapped['logoText'] = $this->firstExistingValue($props, ['logoText', 'logo_text', 'logo', 'headline', 'title']) ?? null;
            $mapped['ctaText'] = $this->firstExistingValue($props, ['ctaText', 'cta_text', 'buttonText', 'button_text', 'button_label']) ?? null;
            $mapped['ctaLink'] = $this->firstExistingValue($props, ['ctaLink', 'cta_link', 'buttonLink', 'button_link', 'button_url']) ?? null;
            $mapped['menuItems'] = $this->extractStructuredList($props, ['menuItems', 'menu_items', 'navigation', 'nav_items']);
            if (isset($props['buttons']) && is_array($props['buttons'])) {
                $mapped['buttons'] = $props['buttons'];
            } elseif (($mapped['ctaText'] ?? null) !== null) {
                $mapped['buttons'] = [[
                    'label' => $mapped['ctaText'],
                    'href' => $mapped['ctaLink'] ?? '#',
                    'variant' => 'primary',
                ]];
            }
            $mapped['sticky'] = $props['sticky'] ?? null;
            $mapped['backgroundColor'] = $this->firstExistingValue($props, ['backgroundColor', 'background_color']) ?? null;
            $mapped['textColor'] = $this->firstExistingValue($props, ['textColor', 'text_color']) ?? null;
            $mapped['layoutVariant'] = $this->firstExistingValue($props, ['layoutVariant', 'layout_variant']) ?? null;
            $mapped['styleVariant'] = $this->firstExistingValue($props, ['styleVariant', 'style_variant']) ?? null;
        } else {
            $mapped['logoUrl'] = $this->firstExistingValue($props, ['logoUrl', 'logo_url']) ?? null;
            $mapped['logoText'] = $this->firstExistingValue($props, ['logoText', 'logo_text', 'logo', 'title']) ?? null;
            $mapped['description'] = $this->firstExistingValue($props, ['description', 'subtitle', 'body']) ?? null;
            $mapped['contactEmail'] = $this->firstExistingValue($props, ['contactEmail', 'email']) ?? null;
            $mapped['contactPhone'] = $this->firstExistingValue($props, ['contactPhone', 'phone']) ?? null;
            $mapped['copyrightText'] = $this->firstExistingValue($props, ['copyrightText', 'copyright']) ?? null;
            $mapped['links'] = $this->extractStructuredList($props, ['links', 'footer_links']);
            $mapped['socialLinks'] = $this->extractStructuredList($props, ['socialLinks', 'social_links']);
            $mapped['backgroundColor'] = $this->firstExistingValue($props, ['backgroundColor', 'background_color']) ?? null;
            $mapped['textColor'] = $this->firstExistingValue($props, ['textColor', 'text_color']) ?? null;
            $mapped['styleVariant'] = $this->firstExistingValue($props, ['styleVariant', 'style_variant']) ?? null;
        }

        return array_filter($mapped, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array<string, mixed>
     */
    private function normalizeSectionPropsForWorkspace(string $componentName, array $props): array
    {
        $mapped = [];

        $mapped['eyebrow'] = $this->firstExistingValue($props, ['eyebrow', 'kicker', 'badge']);
        $mapped['title'] = $this->firstExistingValue($props, ['title', 'headline', 'heading', 'name']);
        $mapped['subtitle'] = $this->firstExistingValue($props, ['subtitle']);
        $mapped['description'] = $this->firstExistingValue($props, ['description', 'body', 'text', 'copy', 'content']);
        $mapped['buttonText'] = $this->firstExistingValue($props, ['buttonText', 'button_text', 'ctaText', 'cta_text', 'button_label', 'cta_label']);
        $mapped['buttonLink'] = $this->firstExistingValue($props, ['buttonLink', 'button_link', 'ctaLink', 'cta_link', 'button_url', 'cta_url', 'url', 'href']);
        $mapped['image'] = $this->firstExistingValue($props, ['image', 'image_url', 'imageUrl', 'hero_image', 'heroImage', 'cover_image', 'coverImage']);
        $mapped['imageAlt'] = $this->firstExistingValue($props, ['imageAlt', 'image_alt', 'alt', 'alt_text']);
        $mapped['backgroundImage'] = $this->firstExistingValue($props, ['backgroundImage', 'background_image']);
        $mapped['alignment'] = $this->firstExistingValue($props, ['alignment', 'text_align', 'textAlign']);
        $mapped['layoutVariant'] = $this->firstExistingValue($props, ['layoutVariant', 'layout_variant']);
        $mapped['styleVariant'] = $this->firstExistingValue($props, ['styleVariant', 'style_variant']);

        $mapped['primaryCta'] = $this->extractCtaValue($props, ['primaryCta', 'primary_cta'], $mapped['buttonText'] ?? null, $mapped['buttonLink'] ?? null);
        $mapped['secondaryCta'] = $this->extractCtaValue($props, ['secondaryCta', 'secondary_cta']);

        $repeaterItems = null;
        foreach (['items', 'features', 'testimonials', 'reviews', 'products', 'categories'] as $key) {
            if (isset($props[$key]) && is_array($props[$key]) && $this->isSequentialArray($props[$key])) {
                $repeaterItems = $props[$key];
                break;
            }
        }

        if ($repeaterItems !== null) {
            if ($componentName === 'FeaturesSection') {
                $mapped['items'] = $this->normalizeRepeaterItems($repeaterItems, ['title', 'heading', 'name'], ['description', 'body', 'text']);
                $mapped = array_merge($mapped, $this->mapRepeaterLikeProps($repeaterItems, 'card', ['title', 'heading', 'name'], ['description', 'body', 'text']));
            } elseif ($componentName === 'TestimonialsSection') {
                $mapped['items'] = $this->normalizeRepeaterItems($repeaterItems, ['quote', 'text', 'body', 'description'], ['author', 'name'], ['role', 'position', 'label']);
                $mapped = array_merge($mapped, $this->mapRepeaterLikeProps($repeaterItems, 'testimonial', ['quote', 'text', 'body', 'description'], ['author', 'name'], ['role', 'position', 'label']));
            } elseif (in_array($componentName, ['ProductGridSection', 'FeaturedCategoriesSection', 'CategoryListSection'], true)) {
                $mapped['items'] = $this->normalizeRepeaterItems($repeaterItems, ['title', 'name', 'label'], ['description', 'body', 'text'], ['price', 'value', 'meta']);
                $mapped = array_merge($mapped, $this->mapRepeaterLikeProps($repeaterItems, 'item', ['title', 'name', 'label'], ['description', 'body', 'text'], ['price', 'value']));
            }
        }

        return array_filter($mapped, static fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<int, string>  $keys
     * @return array<int, array<string, mixed>>
     */
    private function extractStructuredList(array $props, array $keys): array
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $props)) {
                continue;
            }

            $value = $props[$key];
            if (is_array($value) && $this->isSequentialArray($value)) {
                return array_values(array_filter($value, static fn (mixed $item): bool => is_array($item)));
            }

            if (is_string($value) && trim($value) !== '') {
                $decoded = json_decode($value, true);
                if (is_array($decoded) && $this->isSequentialArray($decoded)) {
                    return array_values(array_filter($decoded, static fn (mixed $item): bool => is_array($item)));
                }
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $props
     * @param  array<int, string>  $keys
     * @return array<string, string>|null
     */
    private function extractCtaValue(array $props, array $keys, ?string $fallbackLabel = null, ?string $fallbackLink = null): ?array
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $props) || ! is_array($props[$key])) {
                continue;
            }

            $value = $props[$key];
            $label = is_array($value) ? $this->firstExistingValue($value, ['label', 'text', 'title']) : null;
            $link = is_array($value) ? $this->firstExistingValue($value, ['link', 'url', 'href']) : null;

            if ($label !== null || $link !== null) {
                return array_filter([
                    'label' => $label,
                    'link' => $link,
                ], static fn (mixed $entry): bool => is_string($entry) && trim($entry) !== '');
            }
        }

        if ($fallbackLabel === null && $fallbackLink === null) {
            return null;
        }

        return array_filter([
            'label' => $fallbackLabel,
            'link' => $fallbackLink,
        ], static fn (mixed $entry): bool => is_string($entry) && trim($entry) !== '');
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<int, string>  $titleKeys
     * @param  array<int, string>  $descriptionKeys
     * @param  array<int, string>  $extraKeys
     * @return array<int, array<string, mixed>>
     */
    private function normalizeRepeaterItems(array $items, array $titleKeys, array $descriptionKeys, array $extraKeys = []): array
    {
        $normalized = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $entry = array_filter([
                'title' => $this->firstExistingValue($item, $titleKeys),
                'description' => $this->firstExistingValue($item, $descriptionKeys),
                'meta' => $extraKeys !== [] ? $this->firstExistingValue($item, $extraKeys) : null,
                'image' => $this->firstExistingValue($item, ['image', 'image_url', 'imageUrl']),
                'url' => $this->firstExistingValue($item, ['url', 'href', 'link']),
            ], static fn (mixed $value): bool => $value !== null && $value !== '');

            if ($entry !== []) {
                $normalized[] = $entry;
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, mixed>  $items
     * @param  array<int, string>  $titleKeys
     * @param  array<int, string>  $descriptionKeys
     * @param  array<int, string>  $extraKeys
     * @return array<string, mixed>
     */
    private function mapRepeaterLikeProps(array $items, string $prefix, array $titleKeys, array $descriptionKeys, array $extraKeys = []): array
    {
        $mapped = [];

        foreach (array_slice($items, 0, 3) as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $slot = match ($index) {
                0 => 'One',
                1 => 'Two',
                default => 'Three',
            };

            $title = $this->firstExistingValue($item, $titleKeys);
            $description = $this->firstExistingValue($item, $descriptionKeys);
            $extra = $extraKeys !== [] ? $this->firstExistingValue($item, $extraKeys) : null;

            if ($prefix === 'testimonial') {
                if ($title !== null) {
                    $mapped["quote{$slot}"] = $title;
                }
                if ($description !== null) {
                    $mapped["author{$slot}"] = $description;
                }
                if ($extra !== null) {
                    $mapped["role{$slot}"] = $extra;
                }
                continue;
            }

            if ($title !== null) {
                $mapped["{$prefix}{$slot}Title"] = $title;
            }
            if ($description !== null) {
                $mapped["{$prefix}{$slot}Description"] = $description;
            }
            if ($extra !== null) {
                $mapped["{$prefix}{$slot}Meta"] = $extra;
            }
        }

        return $mapped;
    }

    /**
     * @param  array<string, mixed>  $values
     * @param  array<int, string>  $keys
     */
    private function firstExistingValue(array $values, array $keys): mixed
    {
        foreach ($keys as $key) {
            if (! array_key_exists($key, $values)) {
                continue;
            }

            $value = $values[$key];
            if ($value === null) {
                continue;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed === '') {
                    continue;
                }

                return $trimmed;
            }

            return $value;
        }

        return null;
    }

    /**
     * @param  array<int|string, mixed>  $value
     */
    private function isSequentialArray(array $value): bool
    {
        return array_keys($value) === range(0, count($value) - 1);
    }

    private function componentNameToTemplateType(string $componentName): string
    {
        $base = preg_replace('/Section$/', '', $componentName) ?? $componentName;
        $snake = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $base));

        return trim($snake, '_');
    }

    private function upgradeLegacyScaffolds(Project $project): void
    {
        $root = $this->ensureWorkspaceRoot($project);

        $componentFiles = [
            'src/components/Header.tsx' => $this->templateHeader($project),
            'src/components/Footer.tsx' => $this->templateFooter($project),
            'src/layouts/SiteLayout.tsx' => $this->templateSiteLayout(),
        ];

        foreach ($componentFiles as $relativePath => $replacement) {
            $path = $root.'/'.$relativePath;
            if (! is_file($path)) {
                continue;
            }

            $content = File::get($path);
            if ($this->shouldUpgradeLegacyScaffold(basename($relativePath, '.tsx'), $content)) {
                File::put($path, $replacement);
            }
        }

        $sectionDir = $root.'/src/sections';
        if (! is_dir($sectionDir)) {
            return;
        }

        foreach (File::files($sectionDir) as $file) {
            if ($file->getExtension() !== 'tsx') {
                continue;
            }

            $componentName = $file->getFilenameWithoutExtension();
            $content = File::get($file->getPathname());
            if (! $this->shouldUpgradeLegacyScaffold($componentName, $content)) {
                continue;
            }

            File::put($file->getPathname(), $this->templateSection($componentName, $this->componentNameToTemplateType($componentName)));
        }
    }

    private function shouldUpgradeLegacyScaffold(string $componentName, string $content): bool
    {
        $containsStarterCopy = str_contains($content, 'Replace this starter copy with project-specific content.')
            || str_contains($content, 'Section description. Replace with your content.');

        if ((str_contains($content, 'data-webu-field=') || str_contains($content, 'data-webu-section=')) && ! $containsStarterCopy) {
            return false;
        }

        if (str_contains($componentName, 'Header')) {
            return (
                str_contains($content, '<div className="site-brand">Webu</div>')
                && str_contains($content, '<nav className="site-nav" aria-label="Primary navigation">')
            ) || str_contains($content, '<nav>Header</nav>');
        }

        if (str_contains($componentName, 'Footer')) {
            return str_contains($content, '<p>Built with Webu.</p>')
                || preg_match('/<footer[^>]*>\s*<div[^>]*>\s*Footer\s*<\/div>\s*<\/footer>/i', $content) === 1;
        }

        if ($componentName === 'SiteLayout') {
            return str_contains($content, '<Header />') && str_contains($content, '<Footer />');
        }

        $bareSectionPlaceholder = preg_match('/export\s+default\s+function\s+'.preg_quote($componentName, '/').'\s*\(\)\s*\{/i', $content) === 1
            && (
                str_contains($content, '<div className="container">'.$componentName.'</div>')
                || str_contains($content, 'data-section=')
                || preg_match('/<section[^>]*className="section-[^"]+"[^>]*>\s*<div className="container">[^<]+<\/div>\s*<\/section>/i', $content) === 1
            );

        if ($bareSectionPlaceholder) {
            return true;
        }

        return $containsStarterCopy
            || (
                preg_match('/export\s+default\s+function\s+\w+\(\)\s*\{/', $content) === 1
                && str_contains($content, 'className="section-copy"')
                && (str_contains($content, 'data-section=') || str_contains($content, 'className="section section-'))
            );
    }

    private function templateGenericContentSection(string $name, string $type, array $projection = []): string
    {
        $sampleProps = is_array($projection['sample_props'] ?? null) ? $projection['sample_props'] : [];
        $defaults = match ($type) {
            'hero' => [
                'title' => 'Build a polished website faster',
                'subtitle' => 'Use reusable sections, consistent spacing, and a clean visual system from the start.',
                'buttonText' => 'Start the project',
                'buttonLink' => '/contact',
            ],
            'newsletter' => [
                'title' => 'Stay in the loop',
                'subtitle' => 'Collect interest with a short message and a single clear call to action.',
                'buttonText' => 'Subscribe',
                'buttonLink' => '#subscribe',
            ],
            'cta' => [
                'title' => 'Ready to launch?',
                'subtitle' => 'Keep the next step obvious with a short headline and one focused button.',
                'buttonText' => 'Book a call',
                'buttonLink' => '/contact',
            ],
            'form_wrapper', 'form' => [
                'title' => 'Start a conversation',
                'subtitle' => 'Add contact details or a simple form block while keeping the same container rhythm.',
                'buttonText' => 'Send message',
                'buttonLink' => '#contact',
            ],
            'services' => [
                'title' => 'Services built around clear outcomes',
                'subtitle' => 'Summarize the offer in one focused sentence, then guide visitors toward the next step.',
                'buttonText' => 'View services',
                'buttonLink' => '/services',
            ],
            'contact' => [
                'title' => 'Talk with the team',
                'subtitle' => 'Share the best way to reach you and point visitors to the right contact channel.',
                'buttonText' => 'Contact us',
                'buttonLink' => '/contact',
            ],
            'text' => [
                'title' => 'Tell the story behind the section',
                'subtitle' => 'Use a concise paragraph to explain the offer, the context, or the next decision visitors should make.',
                'buttonText' => '',
                'buttonLink' => '',
            ],
            'heading' => [
                'title' => 'Introduce the next section clearly',
                'subtitle' => 'Pair a direct heading with one supporting sentence when extra context is useful.',
                'buttonText' => '',
                'buttonLink' => '',
            ],
            'image' => [
                'title' => 'Feature an image with context',
                'subtitle' => 'Use supporting copy to explain why the visual matters instead of leaving the block empty.',
                'buttonText' => '',
                'buttonLink' => '',
            ],
            default => [
                'title' => preg_replace('/Section$/', '', $name) ?: $name,
                'subtitle' => 'Use this block to introduce the next idea, highlight a proof point, or move visitors toward a clear action.',
                'buttonText' => 'Learn more',
                'buttonLink' => '/contact',
            ],
        };

        $defaults['title'] = (string) ($this->firstExistingValue($sampleProps, ['title', 'headline', 'heading', 'name']) ?? $defaults['title']);
        $defaults['subtitle'] = (string) ($this->firstExistingValue($sampleProps, ['subtitle', 'description', 'body', 'text']) ?? $defaults['subtitle']);
        $defaults['buttonText'] = (string) ($this->firstExistingValue($sampleProps, ['buttonText', 'button_text', 'ctaText', 'cta_text']) ?? $defaults['buttonText']);
        $defaults['buttonLink'] = (string) ($this->firstExistingValue($sampleProps, ['buttonLink', 'button_link', 'ctaLink', 'cta_link', 'url', 'href']) ?? $defaults['buttonLink']);

        $title = $this->toJsLiteral($defaults['title']);
        $subtitle = $this->toJsLiteral($defaults['subtitle']);
        $buttonText = $this->toJsLiteral($defaults['buttonText']);
        $buttonLink = $this->toJsLiteral($defaults['buttonLink']);
        $alignment = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['alignment']) ?? 'left'));
        $layoutVariant = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['layoutVariant', 'layout_variant']) ?? 'default'));
        $styleVariant = $this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['styleVariant', 'style_variant']) ?? 'default'));
        $extraObservedProps = $this->renderObservedPropInterfaceLines($sampleProps, [
            'sectionId',
            'eyebrow',
            'title',
            'headline',
            'subtitle',
            'description',
            'buttonText',
            'buttonLink',
            'primaryCta',
            'primary_cta',
            'secondaryCta',
            'secondary_cta',
            'image',
            'image_url',
            'imageAlt',
            'backgroundImage',
            'alignment',
            'layoutVariant',
            'styleVariant',
        ]);

        $content = <<<TSX
type {$name}Cta = { label?: string; link?: string };

type {$name}Props = {
  sectionId?: string;
  eyebrow?: string;
  title?: string;
  headline?: string;
  subtitle?: string;
  description?: string;
  buttonText?: string;
  buttonLink?: string;
  primaryCta?: {$name}Cta;
  primary_cta?: {$name}Cta;
  secondaryCta?: {$name}Cta;
  secondary_cta?: {$name}Cta;
  image?: string;
  image_url?: string;
  imageAlt?: string;
  backgroundImage?: string;
  alignment?: 'left' | 'center' | 'right';
  layoutVariant?: string;
  styleVariant?: string;
{$extraObservedProps}
};

export default function {$name}({
  sectionId = '{$type}-section',
  eyebrow = '',
  title = {$title},
  headline = '',
  subtitle = {$subtitle},
  description = '',
  buttonText = {$buttonText},
  buttonLink = {$buttonLink},
  primaryCta,
  primary_cta,
  secondaryCta,
  secondary_cta,
  image = '',
  image_url = '',
  imageAlt = '',
  backgroundImage = '',
  alignment = {$alignment},
  layoutVariant = {$layoutVariant},
  styleVariant = {$styleVariant},
}: {$name}Props) {
  const resolvedTitle = title || headline;
  const resolvedSubtitle = subtitle || description;
  const resolvedPrimaryCta = primaryCta ?? primary_cta ?? (buttonText ? { label: buttonText, link: buttonLink } : null);
  const resolvedSecondaryCta = secondaryCta ?? secondary_cta ?? null;
  const resolvedImage = image || image_url;

  return (
    <section
      className={`section section-{$type} section-{$type}--\${layoutVariant} section-{$type}--\${alignment} section-{$type}--\${styleVariant}`}
      data-webu-section="{$name}"
      data-webu-section-local-id={sectionId}
      style={backgroundImage ? { backgroundImage: `url(\${backgroundImage})`, backgroundSize: 'cover', backgroundPosition: 'center' } : undefined}
    >
      <div className="container section-shell">
        <div className="section-copy" style={{ textAlign: alignment }}>
          {eyebrow ? <p className="section-eyebrow" data-webu-field="eyebrow">{eyebrow}</p> : null}
          <h2 className="section-title" data-webu-field="title">{resolvedTitle}</h2>
          {resolvedSubtitle ? <p className="section-description" data-webu-field="subtitle">{resolvedSubtitle}</p> : null}
          {description && description !== resolvedSubtitle ? <p className="section-description" data-webu-field="description">{description}</p> : null}
          <div className="section-actions">
            {resolvedPrimaryCta?.label ? (
              <a
                className="button-primary"
                href={resolvedPrimaryCta.link || '#'}
                data-webu-field="primaryCta.label"
                data-webu-field-url="primaryCta.link"
              >
                {resolvedPrimaryCta.label}
              </a>
            ) : null}
            {resolvedSecondaryCta?.label ? (
              <a
                className="button-secondary"
                href={resolvedSecondaryCta.link || '#'}
                data-webu-field="secondaryCta.label"
                data-webu-field-url="secondaryCta.link"
              >
                {resolvedSecondaryCta.label}
              </a>
            ) : null}
          </div>
        </div>
        {resolvedImage ? (
          <div className="section-media">
            <img className="section-image" src={resolvedImage} alt={imageAlt || resolvedTitle} data-webu-field="image" />
          </div>
        ) : null}
      </div>
    </section>
  );
}

TSX;

        return $this->withProjectionBanner($content, $this->buildSectionProjectionMetadata($name, $projection));
    }

    private function templateFeaturesSection(string $name, array $projection = []): string
    {
        $sampleProps = is_array($projection['sample_props'] ?? null) ? $projection['sample_props'] : [];
        $items = $this->projectionArrayLiteral($sampleProps['items'] ?? null);
        $content = <<<TSX
type {$name}Item = {
  title?: string;
  description?: string;
};

type {$name}Props = {
  sectionId?: string;
  title?: string;
  subtitle?: string;
  items?: {$name}Item[];
  cardOneTitle?: string;
  cardOneDescription?: string;
  cardTwoTitle?: string;
  cardTwoDescription?: string;
  cardThreeTitle?: string;
  cardThreeDescription?: string;
};

export default function {$name}({
  sectionId = 'features-section',
  title = {$this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['title', 'headline']) ?? 'Why teams choose Webu'))},
  subtitle = {$this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['subtitle', 'description']) ?? 'Group core value points into cards that align to the shared container and spacing rules.'))},
  items = {$items},
  cardOneTitle = {$this->toJsLiteral((string) ($sampleProps['cardOneTitle'] ?? 'Reusable sections'))},
  cardOneDescription = {$this->toJsLiteral((string) ($sampleProps['cardOneDescription'] ?? 'Ship pages faster with sections that already follow the same layout system.'))},
  cardTwoTitle = {$this->toJsLiteral((string) ($sampleProps['cardTwoTitle'] ?? 'Clean spacing'))},
  cardTwoDescription = {$this->toJsLiteral((string) ($sampleProps['cardTwoDescription'] ?? 'Keep vertical rhythm consistent across hero, content, and conversion sections.'))},
  cardThreeTitle = {$this->toJsLiteral((string) ($sampleProps['cardThreeTitle'] ?? 'Builder ready'))},
  cardThreeDescription = {$this->toJsLiteral((string) ($sampleProps['cardThreeDescription'] ?? 'Expose editable fields through parameters so updates never require raw code edits.'))},
}: {$name}Props) {
  const resolvedItems = items.length > 0
    ? items
    : [
        { title: cardOneTitle, description: cardOneDescription },
        { title: cardTwoTitle, description: cardTwoDescription },
        { title: cardThreeTitle, description: cardThreeDescription },
      ];

  return (
    <section className="section section-features" data-webu-section="{$name}" data-webu-section-local-id={sectionId}>
      <div className="container">
        <div className="section-copy">
          <h2 className="section-title" data-webu-field="title">{title}</h2>
          <p className="section-description" data-webu-field="subtitle">{subtitle}</p>
        </div>
        <div className="section-grid">
          {resolvedItems.map((item, index) => (
            <article className="feature-card" key={index}>
              <h3 className="card-title" data-webu-field={`items.\${index}.title`}>{item.title}</h3>
              <p className="card-description" data-webu-field={`items.\${index}.description`}>{item.description}</p>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

TSX;

        return $this->withProjectionBanner($content, $this->buildSectionProjectionMetadata($name, $projection));
    }

    private function templateTestimonialsSection(string $name, array $projection = []): string
    {
        $sampleProps = is_array($projection['sample_props'] ?? null) ? $projection['sample_props'] : [];
        $items = $this->projectionArrayLiteral($sampleProps['items'] ?? null);
        $content = <<<TSX
type {$name}Item = {
  title?: string;
  description?: string;
  meta?: string;
};

type {$name}Props = {
  sectionId?: string;
  title?: string;
  subtitle?: string;
  items?: {$name}Item[];
  quoteOne?: string;
  authorOne?: string;
  roleOne?: string;
  quoteTwo?: string;
  authorTwo?: string;
  roleTwo?: string;
  quoteThree?: string;
  authorThree?: string;
  roleThree?: string;
};

export default function {$name}({
  sectionId = 'testimonials-section',
  title = {$this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['title', 'headline']) ?? 'What customers are saying'))},
  subtitle = {$this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['subtitle', 'description']) ?? 'Highlight social proof with concise quotes and customer roles.'))},
  items = {$items},
  quoteOne = {$this->toJsLiteral((string) ($sampleProps['quoteOne'] ?? 'Webu helped us launch quickly without losing flexibility.'))},
  authorOne = {$this->toJsLiteral((string) ($sampleProps['authorOne'] ?? 'Jordan Smith'))},
  roleOne = {$this->toJsLiteral((string) ($sampleProps['roleOne'] ?? 'Founder'))},
  quoteTwo = {$this->toJsLiteral((string) ($sampleProps['quoteTwo'] ?? 'The builder stays organized because the content lives in parameters.'))},
  authorTwo = {$this->toJsLiteral((string) ($sampleProps['authorTwo'] ?? 'Sam Patel'))},
  roleTwo = {$this->toJsLiteral((string) ($sampleProps['roleTwo'] ?? 'Marketing Lead'))},
  quoteThree = {$this->toJsLiteral((string) ($sampleProps['quoteThree'] ?? 'Preview updates instantly, which makes iteration much faster.'))},
  authorThree = {$this->toJsLiteral((string) ($sampleProps['authorThree'] ?? 'Taylor Johnson'))},
  roleThree = {$this->toJsLiteral((string) ($sampleProps['roleThree'] ?? 'Operations Manager'))},
}: {$name}Props) {
  const resolvedItems = items.length > 0
    ? items
    : [
        { title: quoteOne, description: authorOne, meta: roleOne },
        { title: quoteTwo, description: authorTwo, meta: roleTwo },
        { title: quoteThree, description: authorThree, meta: roleThree },
      ];

  return (
    <section className="section section-testimonials" data-webu-section="{$name}" data-webu-section-local-id={sectionId}>
      <div className="container">
        <div className="section-copy">
          <h2 className="section-title" data-webu-field="title">{title}</h2>
          <p className="section-description" data-webu-field="subtitle">{subtitle}</p>
        </div>
        <div className="section-grid">
          {resolvedItems.map((item, index) => (
            <article className="feature-card testimonial-card" key={index}>
              <p className="testimonial-quote" data-webu-field={`items.\${index}.title`}>"{item.title}"</p>
              <p className="testimonial-author" data-webu-field={`items.\${index}.description`}>{item.description}</p>
              <p className="testimonial-role" data-webu-field={`items.\${index}.meta`}>{item.meta}</p>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

TSX;

        return $this->withProjectionBanner($content, $this->buildSectionProjectionMetadata($name, $projection));
    }

    private function templateProductGridSection(string $name, string $type, array $projection = []): string
    {
        $sampleProps = is_array($projection['sample_props'] ?? null) ? $projection['sample_props'] : [];
        $title = $type === 'featured_categories' ? 'Browse popular categories' : ($type === 'category_list' ? 'Explore every category' : 'Featured products');
        $subtitle = $type === 'product_grid'
            ? 'Showcase products or offers in a responsive grid that collapses cleanly on mobile.'
            : 'Use a simple card grid to present navigational groups or collections.';
        $items = $this->projectionArrayLiteral($sampleProps['items'] ?? null);
        $content = <<<TSX
type {$name}Item = {
  title?: string;
  description?: string;
  meta?: string;
  image?: string;
  url?: string;
};

type {$name}Props = {
  sectionId?: string;
  title?: string;
  subtitle?: string;
  items?: {$name}Item[];
  itemOneTitle?: string;
  itemOneDescription?: string;
  itemOneMeta?: string;
  itemTwoTitle?: string;
  itemTwoDescription?: string;
  itemTwoMeta?: string;
  itemThreeTitle?: string;
  itemThreeDescription?: string;
  itemThreeMeta?: string;
};

export default function {$name}({
  sectionId = '{$type}-section',
  title = {$this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['title', 'headline']) ?? $title))},
  subtitle = {$this->toJsLiteral((string) ($this->firstExistingValue($sampleProps, ['subtitle', 'description']) ?? $subtitle))},
  items = {$items},
  itemOneTitle = {$this->toJsLiteral((string) ($sampleProps['itemOneTitle'] ?? 'Starter package'))},
  itemOneDescription = {$this->toJsLiteral((string) ($sampleProps['itemOneDescription'] ?? 'Describe the first product, offer, or category here.'))},
  itemOneMeta = {$this->toJsLiteral((string) ($sampleProps['itemOneMeta'] ?? '$49'))},
  itemTwoTitle = {$this->toJsLiteral((string) ($sampleProps['itemTwoTitle'] ?? 'Growth package'))},
  itemTwoDescription = {$this->toJsLiteral((string) ($sampleProps['itemTwoDescription'] ?? 'Describe the second product, offer, or category here.'))},
  itemTwoMeta = {$this->toJsLiteral((string) ($sampleProps['itemTwoMeta'] ?? '$99'))},
  itemThreeTitle = {$this->toJsLiteral((string) ($sampleProps['itemThreeTitle'] ?? 'Premium package'))},
  itemThreeDescription = {$this->toJsLiteral((string) ($sampleProps['itemThreeDescription'] ?? 'Describe the third product, offer, or category here.'))},
  itemThreeMeta = {$this->toJsLiteral((string) ($sampleProps['itemThreeMeta'] ?? '$149'))},
}: {$name}Props) {
  const resolvedItems = items.length > 0
    ? items
    : [
        { title: itemOneTitle, description: itemOneDescription, meta: itemOneMeta },
        { title: itemTwoTitle, description: itemTwoDescription, meta: itemTwoMeta },
        { title: itemThreeTitle, description: itemThreeDescription, meta: itemThreeMeta },
      ];

  return (
    <section className="section section-{$type}" data-webu-section="{$name}" data-webu-section-local-id={sectionId}>
      <div className="container">
        <div className="section-copy">
          <h2 className="section-title" data-webu-field="title">{title}</h2>
          <p className="section-description" data-webu-field="subtitle">{subtitle}</p>
        </div>
        <div className="section-grid">
          {resolvedItems.map((item, index) => (
            <article className="feature-card" key={index}>
              {item.meta ? <div className="card-meta" data-webu-field={`items.\${index}.meta`}>{item.meta}</div> : null}
              <h3 className="card-title" data-webu-field={`items.\${index}.title`}>{item.title}</h3>
              <p className="card-description" data-webu-field={`items.\${index}.description`}>{item.description}</p>
            </article>
          ))}
        </div>
      </div>
    </section>
  );
}

TSX;

        return $this->withProjectionBanner($content, $this->buildSectionProjectionMetadata($name, $projection));
    }

    /**
     * @param  array<string, mixed>  $projection
     * @return array<string, mixed>
     */
    private function buildSectionProjectionMetadata(string $name, array $projection): array
    {
        $sampleProps = is_array($projection['sample_props'] ?? null) ? $projection['sample_props'] : [];

        return array_filter([
            'projection_role' => (string) ($projection['projection_role'] ?? 'section'),
            'projection_source' => (string) ($projection['projection_source'] ?? 'cms-projection'),
            'component_name' => (string) ($projection['component_name'] ?? $name),
            'section_types' => is_array($projection['types'] ?? null) ? array_values($projection['types']) : null,
            'observed_prop_keys' => array_values(array_keys($sampleProps)),
            'observed_prop_paths' => is_array($projection['prop_paths'] ?? null)
                ? array_values($projection['prop_paths'])
                : $this->flattenProjectionPropPaths($sampleProps),
            'pages' => is_array($projection['pages'] ?? null) ? array_values($projection['pages']) : null,
            'page_paths' => is_array($projection['page_paths'] ?? null) ? array_values($projection['page_paths']) : null,
            'variants' => $projection['variants'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== []);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function withProjectionBanner(string $content, array $metadata): string
    {
        $encoded = json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';

        return '// '.self::GENERATED_PROJECTION_MARKER."\n"
            .'// @webu-projection '.$encoded."\n\n"
            .$content;
    }

    private function stripProjectionBanner(string $content): string
    {
        if (! str_contains($content, self::GENERATED_PROJECTION_MARKER)) {
            return $content;
        }

        return (string) preg_replace(
            '/^\/\/\s+'.preg_quote(self::GENERATED_PROJECTION_MARKER, '/')."\R"
            .'\/\/\s+@webu-projection[^\r\n]*\R\R?/',
            '',
            $content,
            1
        );
    }

    /**
     * @param  array<string, mixed>  $sampleProps
     * @param  array<int, string>  $ignored
     */
    private function renderObservedPropInterfaceLines(array $sampleProps, array $ignored = []): string
    {
        $lines = [];
        foreach ($sampleProps as $key => $value) {
            if (! is_string($key) || trim($key) === '' || in_array($key, $ignored, true)) {
                continue;
            }
            if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) !== 1) {
                continue;
            }

            $type = match (true) {
                is_string($value) => 'string',
                is_bool($value) => 'boolean',
                is_int($value), is_float($value) => 'number',
                is_array($value) && $this->isSequentialArray($value) => 'Array<Record<string, unknown>>',
                is_array($value) => 'Record<string, unknown>',
                default => 'unknown',
            };
            $lines[] = "  {$key}?: {$type};";
        }

        if ($lines === []) {
            return '';
        }

        return "\n".implode("\n", array_values(array_unique($lines)));
    }

    private function projectionArrayLiteral(mixed $value): string
    {
        return is_array($value) ? $this->toJsLiteral($value) : '[]';
    }

    /**
     * @param  array<string, mixed>  $current
     * @param  array<string, mixed>  $incoming
     * @return array<string, mixed>
     */
    private function mergeProjectionSampleProps(array $current, array $incoming): array
    {
        foreach ($incoming as $key => $value) {
            if (! is_string($key) || trim($key) === '') {
                continue;
            }
            if (! array_key_exists($key, $current) || $current[$key] === null || $current[$key] === '' || $current[$key] === []) {
                $current[$key] = $value;
            }
        }

        return $current;
    }

    /**
     * @param  array<string, mixed>|array<int, mixed>  $value
     * @return array<int, string>
     */
    private function flattenProjectionPropPaths(array $value, string $prefix = ''): array
    {
        $paths = [];

        foreach ($value as $key => $entry) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            $segment = (string) $key;
            if ($segment === '') {
                continue;
            }

            $path = $prefix !== '' ? $prefix.'.'.$segment : $segment;
            $paths[] = $path;

            if (is_array($entry)) {
                foreach ($this->flattenProjectionPropPaths($entry, $path) as $nestedPath) {
                    $paths[] = $nestedPath;
                }
            }
        }

        return array_values(array_unique(array_filter($paths)));
    }

    /**
     * @param  array<string, mixed>  $props
     * @return array{layout: array<int, string>, style: array<int, string>}
     */
    private function extractVariantUsage(array $props): array
    {
        $layoutVariant = $this->firstExistingValue($props, ['layoutVariant', 'layout_variant']);
        $styleVariant = $this->firstExistingValue($props, ['styleVariant', 'style_variant']);

        return [
            'layout' => is_string($layoutVariant) && trim($layoutVariant) !== '' ? [trim($layoutVariant)] : [],
            'style' => is_string($styleVariant) && trim($styleVariant) !== '' ? [trim($styleVariant)] : [],
        ];
    }

    private function fileContainsProjectionMarker(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        return str_contains((string) File::get($path), self::GENERATED_PROJECTION_MARKER);
    }

    private function shouldWriteProjectionManagedFile(string $path, string $componentName): bool
    {
        if (! is_file($path)) {
            return true;
        }

        $content = (string) File::get($path);

        return str_contains($content, self::GENERATED_PROJECTION_MARKER)
            || $this->shouldUpgradeLegacyScaffold($componentName, $content);
    }

    private function shouldWriteProjectionManagedPageFile(string $path): bool
    {
        if (! is_file($path)) {
            return true;
        }

        if ($this->fileContainsProjectionMarker($path)) {
            return true;
        }

        $content = (string) File::get($path);

        return str_contains($content, 'data-webu-section="WorkspaceHome"')
            || str_contains($content, 'Start shaping your first page')
            || $this->shouldUpgradeLegacyScaffold('Page', $content);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<string, mixed>>
     */
    private function workspaceProjectionFileCatalog(array $payload): array
    {
        return is_array($payload['files'] ?? null) ? $payload['files'] : [];
    }

    /**
     * @param  array<string, mixed>  $layoutProps
     * @param  array<int, string>  $pageSlugs
     * @return array<int, array<string, mixed>>
     */
    private function buildLayoutProjectionEntries(array $layoutProps, array $pageSlugs): array
    {
        $entries = [
            [
                'component_name' => 'Header',
                'path' => 'src/components/Header.tsx',
                'projection_role' => 'layout-component',
                'projection_source' => 'cms-projection',
                'pages' => $pageSlugs,
                'page_paths' => array_values(array_map(static fn (string $slug): string => 'src/pages/'.$slug.'/Page.tsx', $pageSlugs)),
                'prop_keys' => array_values(array_keys(is_array($layoutProps['headerProps'] ?? null) ? $layoutProps['headerProps'] : [])),
                'prop_paths' => $this->flattenProjectionPropPaths(is_array($layoutProps['headerProps'] ?? null) ? $layoutProps['headerProps'] : []),
                'sample_props' => is_array($layoutProps['headerProps'] ?? null) ? $layoutProps['headerProps'] : [],
                'variants' => $this->extractVariantUsage(is_array($layoutProps['headerProps'] ?? null) ? $layoutProps['headerProps'] : []),
            ],
            [
                'component_name' => 'Footer',
                'path' => 'src/components/Footer.tsx',
                'projection_role' => 'layout-component',
                'projection_source' => 'cms-projection',
                'pages' => $pageSlugs,
                'page_paths' => array_values(array_map(static fn (string $slug): string => 'src/pages/'.$slug.'/Page.tsx', $pageSlugs)),
                'prop_keys' => array_values(array_keys(is_array($layoutProps['footerProps'] ?? null) ? $layoutProps['footerProps'] : [])),
                'prop_paths' => $this->flattenProjectionPropPaths(is_array($layoutProps['footerProps'] ?? null) ? $layoutProps['footerProps'] : []),
                'sample_props' => is_array($layoutProps['footerProps'] ?? null) ? $layoutProps['footerProps'] : [],
                'variants' => $this->extractVariantUsage(is_array($layoutProps['footerProps'] ?? null) ? $layoutProps['footerProps'] : []),
            ],
            [
                'component_name' => 'SiteLayout',
                'path' => 'src/layouts/SiteLayout.tsx',
                'projection_role' => 'layout',
                'projection_source' => 'cms-projection',
                'pages' => $pageSlugs,
                'page_paths' => array_values(array_map(static fn (string $slug): string => 'src/pages/'.$slug.'/Page.tsx', $pageSlugs)),
                'prop_keys' => ['headerProps', 'footerProps'],
                'prop_paths' => ['headerProps', 'footerProps'],
                'sample_props' => [
                    'headerProps' => is_array($layoutProps['headerProps'] ?? null) ? $layoutProps['headerProps'] : [],
                    'footerProps' => is_array($layoutProps['footerProps'] ?? null) ? $layoutProps['footerProps'] : [],
                ],
                'variants' => [
                    'layout' => [],
                    'style' => [],
                ],
            ],
        ];

        return array_values($entries);
    }

    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @param  array<string, array<string, mixed>>  $components
     * @return array<string, array<string, mixed>>
     */
    private function buildWorkspaceProjectionFileCatalog(array $pages, array $components, array $layouts = []): array
    {
        $files = [];

        foreach ($layouts as $layout) {
            if (! is_array($layout)) {
                continue;
            }
            $path = is_string($layout['path'] ?? null) ? trim((string) $layout['path']) : '';
            if ($path === '') {
                continue;
            }
            $files[$path] = [
                'projection_role' => $layout['projection_role'] ?? null,
                'projection_source' => $layout['projection_source'] ?? 'cms-projection',
                'is_generated_projection' => true,
                'component_name' => $layout['component_name'] ?? null,
                'prop_paths' => $layout['prop_paths'] ?? [],
                'pages' => $layout['pages'] ?? [],
                'page_paths' => $layout['page_paths'] ?? [],
            ];
        }

        foreach ($pages as $page) {
            $path = is_string($page['path'] ?? null) ? trim((string) $page['path']) : '';
            if ($path === '') {
                continue;
            }
            $files[$path] = [
                'projection_role' => 'page',
                'projection_source' => 'cms-projection',
                'is_generated_projection' => true,
                'page_slug' => $page['slug'] ?? null,
                'cms_backed' => $page['cms_backed'] ?? true,
                'content_owner' => $page['content_owner'] ?? 'mixed',
                'content_field_paths' => $page['content_field_paths'] ?? [],
                'visual_field_paths' => $page['visual_field_paths'] ?? [],
                'code_field_paths' => $page['code_field_paths'] ?? [],
                'sync_direction' => $page['sync_direction'] ?? 'cms_to_workspace',
                'conflict_status' => $page['conflict_status'] ?? 'clean',
                'layout_files' => $page['layout_files'] ?? [],
                'section_files' => $page['section_files'] ?? [],
            ];
        }

        foreach ($components as $component) {
            if (! is_array($component)) {
                continue;
            }
            $path = is_string($component['path'] ?? null) ? trim((string) $component['path']) : '';
            if ($path === '') {
                continue;
            }
            $files[$path] = [
                'projection_role' => $component['projection_role'] ?? 'section',
                'projection_source' => $component['projection_source'] ?? 'cms-projection',
                'is_generated_projection' => true,
                'component_name' => $component['component_name'] ?? null,
                'section_types' => $component['types'] ?? [],
                'cms_backed' => $component['cms_backed'] ?? true,
                'content_owner' => $component['content_owner'] ?? 'cms',
                'content_field_paths' => $component['content_field_paths'] ?? [],
                'visual_field_paths' => $component['visual_field_paths'] ?? [],
                'code_field_paths' => $component['code_field_paths'] ?? [],
                'sync_direction' => $component['sync_direction'] ?? 'cms_to_workspace',
                'conflict_status' => $component['conflict_status'] ?? 'clean',
                'prop_paths' => $component['prop_paths'] ?? [],
                'pages' => $component['pages'] ?? [],
                'page_paths' => $component['page_paths'] ?? [],
            ];
        }

        ksort($files);

        return $files;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function writeWorkspaceProjectionSnapshot(string $root, array $payload): void
    {
        $path = $root.'/'.self::WORKSPACE_PROJECTION_FILE;
        File::ensureDirectoryExists(dirname($path), 0775, true);
        File::put($path, json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    private function toJsLiteral(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: 'null';
    }

    private function templateGlobalsCss(): string
    {
        return <<<'CSS'
:root {
  --color-primary: #2563eb;
  --color-foreground: #0f172a;
  --color-background: #ffffff;
  --color-muted: #475569;
  --color-border: #dbe3f0;
  --container-desktop: 1290px;
  --container-tablet: 1024px;
  --section-space: 80px;
  --section-space-medium: 60px;
  --section-space-small: 40px;
  --heading-h1: 48px;
  --heading-h2: 36px;
  --heading-h3: 24px;
  --body-size: 16px;
  --line-height: 1.5;
  font-family: "Inter", "Segoe UI", sans-serif;
}

* {
  box-sizing: border-box;
}

body {
  margin: 0;
  color: var(--color-foreground);
  background: var(--color-background);
  font-size: var(--body-size);
  line-height: var(--line-height);
}

a {
  color: inherit;
  text-decoration: none;
}

.site-layout {
  min-height: 100vh;
  display: flex;
  flex-direction: column;
}

main {
  flex: 1;
}

.container {
  width: min(100% - 32px, var(--container-desktop));
  margin: 0 auto;
}

.section {
  padding: var(--section-space) 0;
}

.section-copy {
  max-width: 720px;
}

.section-shell {
  display: grid;
  grid-template-columns: minmax(0, 1.1fr) minmax(0, 0.9fr);
  align-items: center;
  gap: 32px;
}

.section-eyebrow {
  margin: 0 0 12px;
  color: var(--color-primary);
  font-size: 13px;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.section-title {
  margin: 0 0 16px;
  font-size: var(--heading-h2);
  line-height: 1.15;
}

.section-description {
  margin: 0;
  color: var(--color-muted);
}

.section-media {
  width: 100%;
}

.section-image {
  width: 100%;
  display: block;
  border-radius: 24px;
  border: 1px solid var(--color-border);
}

.section-grid {
  display: grid;
  grid-template-columns: repeat(3, minmax(0, 1fr));
  gap: 20px;
  margin-top: 32px;
}

.feature-card {
  padding: 24px;
  border: 1px solid var(--color-border);
  border-radius: 20px;
  background: #ffffff;
}

.card-meta {
  margin-bottom: 12px;
  color: var(--color-primary);
  font-size: 14px;
  font-weight: 700;
}

.card-title,
.testimonial-author {
  margin: 0 0 12px;
  font-size: 20px;
}

.card-description,
.testimonial-role,
.site-footer__description,
.site-footer__copyright {
  margin: 0;
  color: var(--color-muted);
}

.testimonial-quote {
  margin: 0 0 16px;
  font-size: 18px;
  line-height: 1.6;
}

.button-primary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 24px;
  padding: 14px 22px;
  border-radius: 999px;
  background: var(--color-primary);
  color: #ffffff;
  font-weight: 600;
}

.button-secondary {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin-top: 24px;
  padding: 14px 22px;
  border-radius: 999px;
  border: 1px solid var(--color-border);
  background: transparent;
  color: var(--color-foreground);
  font-weight: 600;
}

.button-primary--compact {
  margin-top: 0;
  padding: 10px 18px;
}

.button-secondary--compact {
  margin-top: 0;
  padding: 10px 18px;
}

.section-actions,
.site-header__actions {
  display: flex;
  flex-wrap: wrap;
  align-items: center;
  gap: 12px;
}

.site-header,
.site-footer {
  border-bottom: 1px solid var(--color-border);
  background: rgba(255, 255, 255, 0.94);
}

.site-footer {
  border-top: 1px solid var(--color-border);
  border-bottom: 0;
}

.site-header__inner,
.site-footer__inner {
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 24px;
  padding: 20px 0;
}

.site-brand {
  font-size: 18px;
  font-weight: 700;
}

.site-nav {
  display: flex;
  align-items: center;
  gap: 18px;
  color: var(--color-muted);
}

.site-footer__brand,
.site-footer__links,
.site-footer__contact {
  display: flex;
  flex-direction: column;
  gap: 12px;
}

@media (max-width: 1024px) {
  .container {
    width: min(100% - 28px, var(--container-tablet));
  }

  .section {
    padding: var(--section-space-medium) 0;
  }

  .section-title {
    font-size: 28px;
  }

  .section-grid {
    grid-template-columns: repeat(2, minmax(0, 1fr));
  }
}

@media (max-width: 768px) {
  :root {
    --heading-h1: 28px;
    --heading-h2: 24px;
  }

  .section {
    padding: var(--section-space-small) 0;
  }

  .site-header__inner,
  .site-footer__inner,
  .site-nav {
    flex-direction: column;
    align-items: flex-start;
  }

  .section-shell,
  .section-grid {
    grid-template-columns: 1fr;
  }
}

CSS;
    }
}
