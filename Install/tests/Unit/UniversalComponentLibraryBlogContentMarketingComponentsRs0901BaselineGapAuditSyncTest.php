<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryBlogContentMarketingComponentsRs0901BaselineGapAuditSyncTest extends TestCase
{
    public function test_rs_09_01_progress_audit_doc_locks_blog_content_marketing_components_parity_endpoint_and_runtime_gap_truth(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $docPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BLOG_CONTENT_MARKETING_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_09_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BLOG_CONTENT_MARKETING_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_09_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $aliasMapPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_SPEC_EQUIVALENCE_ALIAS_MAP.v1.json');
        $webRoutesPath = base_path('routes/web.php');
        $panelBlogControllerPath = base_path('app/Http/Controllers/Cms/PanelBlogPostController.php');
        $publicSiteControllerPath = base_path('app/Http/Controllers/Cms/PublicSiteController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $publicCoreOpenApiPath = base_path('docs/openapi/webu-public-core-minimal.v1.openapi.yaml');

        $blogPostsManagementTestPath = base_path('tests/Feature/Cms/CmsBlogPostsManagementTest.php');
        $cmsPublicVerticalFeatureTestPath = base_path('tests/Feature/Cms/CmsPublicVerticalModulesEndpointsTest.php');
        $blogSummaryLockTestPath = base_path('tests/Unit/UniversalBlogContentModuleSummaryP5Test.php');
        $blogFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsBlogBuilderCoverage.contract.test.ts');
        $activationFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsUniversalComponentLibraryActivation.contract.test.ts');
        $activationUnitTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');
        $coverageGapAuditUnitTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecComponentCoverageGapAuditTest.php');
        $aliasMapUnitTestPath = base_path('tests/Unit/UniversalComponentLibrarySpecEquivalenceAliasMapTest.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $closureSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryBlogContentMarketingComponentsRs0901ClosureAuditSyncTest.php');
        $minimalOpenApiDeliverableTestPath = base_path('tests/Unit/MinimalOpenApiBaseModulesDeliverableTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $docPath,
            $closureDocPath,
            $cmsPath,
            $aliasMapPath,
            $webRoutesPath,
            $panelBlogControllerPath,
            $publicSiteControllerPath,
            $builderServicePath,
            $publicCoreOpenApiPath,
            $blogPostsManagementTestPath,
            $cmsPublicVerticalFeatureTestPath,
            $blogSummaryLockTestPath,
            $blogFrontendContractPath,
            $activationFrontendContractPath,
            $activationUnitTestPath,
            $coverageGapAuditUnitTestPath,
            $aliasMapUnitTestPath,
            $runtimeContractTestPath,
            $closureSyncTestPath,
            $minimalOpenApiDeliverableTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $doc = File::get($docPath);
        $cms = File::get($cmsPath);
        $aliasMap = File::get($aliasMapPath);
        $webRoutes = File::get($webRoutesPath);
        $panelBlogController = File::get($panelBlogControllerPath);
        $publicSiteController = File::get($publicSiteControllerPath);
        $builderService = File::get($builderServicePath);
        $publicCoreOpenApi = File::get($publicCoreOpenApiPath);
        $blogPostsManagementTest = File::get($blogPostsManagementTestPath);
        $cmsPublicVerticalFeatureTest = File::get($cmsPublicVerticalFeatureTestPath);
        $blogSummaryLockTest = File::get($blogSummaryLockTestPath);
        $blogFrontendContract = File::get($blogFrontendContractPath);
        $activationFrontendContract = File::get($activationFrontendContractPath);
        $activationUnitTest = File::get($activationUnitTestPath);
        $coverageGapAuditUnitTest = File::get($coverageGapAuditUnitTestPath);
        $aliasMapUnitTest = File::get($aliasMapUnitTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);
        $minimalOpenApiDeliverableTest = File::get($minimalOpenApiDeliverableTestPath);

        foreach ([
            '# 9) BLOG / CONTENT MARKETING COMPONENTS',
            '## 9.1 blog.postList',
            'Content: layout, pagination, categories',
            'Data: GET /posts',
            '## 9.2 blog.postDetail',
            'Data: GET /posts/:slug',
            '## 9.3 blog.categoryList',
            'Data: GET /post-categories',
        ] as $needle) {
            $this->assertStringContainsString($needle, $roadmap);
        }

        foreach ([
            '- `RS-09-01` (`DONE`, `P1`)',
            'UNIVERSAL_COMPONENT_LIBRARY_BLOG_CONTENT_MARKETING_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_09_01_2026_02_25.md',
            'UNIVERSAL_COMPONENT_LIBRARY_BLOG_CONTENT_MARKETING_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_09_01_2026_02_26.md',
            'UniversalComponentLibraryBlogContentMarketingComponentsRs0901BaselineGapAuditSyncTest.php',
            'UniversalComponentLibraryBlogContentMarketingComponentsRs0901ClosureAuditSyncTest.php',
            'CmsPublicVerticalModulesEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            '`✅` baseline parity/gap audit is preserved and superseded by a closure audit with public blog endpoints + standalone `window.WebbyBlog` runtime hook/selectors evidence',
            '`✅` public blog endpoint bindings are feature-tested (`GET /public/sites/{site}/posts`, `GET /public/sites/{site}/posts/{slug}`, `GET /public/sites/{site}/post-categories`) including `blog.postList` pagination/query/category-filter verification (`page`, `per_page`, `q`, `category`) via `CmsPublicVerticalModulesEndpointsTest.php`',
            '`✅` `BuilderService` now exposes standalone `blog.postList` / `blog.postDetail` / `blog.categoryList` runtime selectors/mounts and `window.WebbyBlog` helper APIs (`listPosts`, `getPost`, `listCategories`, `mountPostListWidget`, `mountPostDetailWidget`, `mountCategoryListWidget`) with contract locks',
            '`✅` minimal public OpenAPI blog route coverage is present (`/public/sites/{site}/posts`, `/public/sites/{site}/posts/{slug}`, `/public/sites/{site}/post-categories`) and included in deliverable sync tests',
            '`✅` DoD closure achieved: all 3 components pass data + UI parity checks (public endpoint bindings + pagination/category filters verification + builder preview/runtime hook evidence)',
            '`⚠️` source exactness gaps remain (`blog.postList` still lacks a source-exact `layout` mode control and category-filter UI binding in builder schema/preview; runtime uses accepted `category` query filter semantics)',
            '`🧪` RS-09-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `BASELINE_RECORDED`',
            '## Scope',
            '## Why This Audit Is Baseline/Gap (Not Final Closure Yet)',
            '## Audit Inputs Reviewed',
            '## What Was Done (This Pass)',
            '## Executive Result (`RS-09-01`)',
            '## Blog / Content Marketing Parity Matrix',
            '### Matrix (`content/style/panel-preview/runtime-data/endpoint/pagination-category-filters/gating/tests`)',
            '`blog.postList`',
            '`blog.postDetail`',
            '`blog.categoryList`',
            '`webu_blog_post_list_01`',
            '`webu_blog_post_detail_01`',
            '`webu_blog_category_list_01`',
            '## Endpoint Contract Verification (`GET /posts`, `GET /posts/:slug`, `GET /post-categories`)',
            '### Source-to-Current Endpoint Matrix',
            '`equivalent_panel_only`',
            '`partial_equivalent_panel_only`',
            '`gap`',
            '## Pagination / Category Filters Verification',
            '### `blog.postList` Preview Behavior (Pagination + Category Visibility)',
            'pagination_mode',
            'load_more',
            'infinite',
            'no explicit category filter control/binding source',
            '### `blog.categoryList` Preview Behavior (Categories UI)',
            '`variant` (`chips` / `list`)',
            '## Builder Preview Parity and Source-Control Exactness Findings',
            'source: `layout`, `pagination`, `categories`',
            'current schema: columns + `pagination_mode` + `show_category` display toggle',
            '## Blog Content Baseline (Panel CRUD + Builder Authoring)',
            'loadBlogPosts',
            'handleSaveBlogPost',
            'handleDeleteBlogPostConfirmed',
            '## Runtime Widget / Binding Status (`postList`, `postDetail`, `categoryList`)',
            'no `window.WebbyBlog` helper',
            'no `data-webby-blog-*` runtime widget mount handling in `BuilderService`',
            '## DoD Verdict (`RS-09-01`)',
            'Conclusion: `RS-09-01` remains `IN_PROGRESS`.',
            '## Unblocking Plan (To Reach DoD + Parity Closure)',
            '## Conclusion',
        ] as $needle) {
            $this->assertStringContainsString($needle, $doc);
        }

        foreach ([
            'webu_blog_post_list_01',
            'webu_blog_post_detail_01',
            'webu_blog_category_list_01',
            'data-webby-blog-post-list',
            'data-webby-blog-post-detail',
            'data-webby-blog-category-list',
            'posts_count',
            'columns_desktop',
            'columns_mobile',
            'show_category',
            'show_excerpt',
            'show_date',
            'pagination_mode',
            'post_slug',
            'show_cover',
            'show_author',
            'show_share',
            'categories_count',
            'variant',
            'show_counts',
            "if (normalized === 'webu_blog_post_list_01')",
            "if (normalized === 'webu_blog_post_detail_01')",
            "if (normalized === 'webu_blog_category_list_01')",
            "if (normalizedSectionType === 'webu_blog_post_list_01')",
            "if (normalizedSectionType === 'webu_blog_post_detail_01')",
            "if (normalizedSectionType === 'webu_blog_category_list_01')",
            'applyBlogPreviewState',
            'createSyntheticBlogPlaceholder',
            'syntheticBlogSectionKeySet',
            "blog: { en: 'Blog Components'",
            "pagination.textContent = mode === 'load_more'",
            "t('Infinite scroll preview')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            'category_filter',
            'show_category_filter',
            'layout_mode',
            'window.WebbyBlog',
            'data-webby-blog-runtime',
        ] as $needle) {
            $this->assertStringNotContainsString($needle, $cms);
        }

        foreach ([
            'source_component_key": "blog.postList"',
            'webu_blog_post_list_01',
            'source_component_key": "blog.postDetail"',
            'webu_blog_post_detail_01',
            'source_component_key": "blog.categoryList"',
            'webu_blog_category_list_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMap);
        }

        foreach ([
            "Route::get('/blog-posts', [PanelBlogPostController::class, 'index'])->name('panel.sites.blog-posts.index');",
            "Route::get('/blog-posts/{blogPost}', [PanelBlogPostController::class, 'show'])->name('panel.sites.blog-posts.show');",
            "Route::post('/blog-posts', [PanelBlogPostController::class, 'store'])->name('panel.sites.blog-posts.store');",
            "Route::put('/blog-posts/{blogPost}', [PanelBlogPostController::class, 'update'])->name('panel.sites.blog-posts.update');",
            "Route::delete('/blog-posts/{blogPost}', [PanelBlogPostController::class, 'destroy'])->name('panel.sites.blog-posts.destroy');",
            "Route::get('/{site}/posts', [PublicSiteController::class, 'blogPosts'])->name('public.sites.posts.index');",
            "Route::get('/{site}/posts/{slug}', [PublicSiteController::class, 'blogPost'])->name('public.sites.posts.show');",
            "Route::get('/{site}/post-categories', [PublicSiteController::class, 'blogCategories'])->name('public.sites.post-categories.index');",
            "Route::get('/{site}/pages/{slug}', [PublicSiteController::class, 'page'])",
            "Route::get('/{site}/assets/{path}', [PublicSiteController::class, 'asset'])",
        ] as $needle) {
            $this->assertStringContainsString($needle, $webRoutes);
        }

        foreach ([
            'class PanelBlogPostController extends Controller',
            'public function index(Site $site): JsonResponse',
            'public function show(Site $site, BlogPost $blogPost): JsonResponse',
            'public function store(Request $request, Site $site): JsonResponse',
            'public function update(Request $request, Site $site, BlogPost $blogPost): JsonResponse',
            'public function destroy(Site $site, BlogPost $blogPost): JsonResponse',
            'return response()->json($this->posts->listPosts($site));',
            'Blog post created successfully.',
            'Blog post updated successfully.',
            'Blog post deleted successfully.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $panelBlogController);
        }

        foreach ([
            'class PublicSiteController extends Controller',
            'public function page(Request $request, Site $site, string $slug): JsonResponse',
            'public function asset(Request $request, Site $site, string $path): BinaryFileResponse|JsonResponse',
            'public function blogPosts(Request $request, Site $site): JsonResponse',
            'public function blogPost(Request $request, Site $site, string $slug): JsonResponse',
            'public function blogCategories(Request $request, Site $site): JsonResponse',
            'Public blog posts list endpoint for blog/content components.',
            'Public blog post detail endpoint by slug.',
            'Public blog categories endpoint.',
            "'category' => ['nullable', 'string', 'max:120']",
            "'page' => ['nullable', 'integer', 'min:1']",
            "'per_page' => ['nullable', 'integer', 'min:1', 'max:50']",
            '\'category\' => $categorySlug !== \'\' ? $categorySlug : null,',
            '\'categories\' => $categories,',
            '\'posts_count\' => (int) ($countsByCategory[$row->id] ?? 0),',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicSiteController);
        }

        foreach ([
            'window.WebbyBooking',
            'booking_selector',
            '[data-webby-booking-widget]',
            'window.WebbyBlog = window.WebbyBlog || {};',
            'window.WebbyBlog.listPosts = function (params) {',
            "window.WebbyBlog.getPost = function (slug) { return cmsPublicJson('/posts/' + encodeURIComponent(String(slug || ''))); };",
            "window.WebbyBlog.listCategories = function () { return cmsPublicJson('/post-categories'); };",
            'window.WebbyBlog.mountPostListWidget = mountBlogPostListWidget;',
            'window.WebbyBlog.mountPostDetailWidget = mountBlogPostDetailWidget;',
            'window.WebbyBlog.mountCategoryListWidget = mountBlogCategoryListWidget;',
            'function mountBlogPostListWidget(container) {',
            'function mountBlogPostDetailWidget(container) {',
            'function mountBlogCategoryListWidget(container) {',
            'data-webby-blog-post-list',
            'data-webby-blog-post-detail',
            'data-webby-blog-category-list',
            'data-webby-blog-runtime',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            '/public/sites/{site}/pages/{slug}:',
            '/public/sites/{site}/assets/{path}:',
            '/public/sites/{site}/posts:',
            '/public/sites/{site}/posts/{slug}:',
            '/public/sites/{site}/post-categories:',
            'summary: Public blog posts list',
            'summary: Public blog post detail by slug',
            'summary: Public blog category list',
            'openapi: 3.0.3',
            'paths:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicCoreOpenApi);
        }

        foreach ([
            'test_owner_can_create_update_and_delete_blog_post',
            'test_blog_post_update_is_blocked_for_foreign_tenant',
            "route('panel.sites.blog-posts.store'",
            "route('panel.sites.blog-posts.update'",
            "route('panel.sites.blog-posts.destroy'",
            '->assertCreated()',
            '->assertStatus(403);',
        ] as $needle) {
            $this->assertStringContainsString($needle, $blogPostsManagementTest);
        }

        foreach ([
            'test_public_blog_portfolio_properties_restaurant_and_hotel_endpoints_return_site_scoped_data',
            "route('public.sites.posts.index'",
            "route('public.sites.posts.show'",
            "route('public.sites.post-categories.index'",
            "'per_page' => 1, 'page' => 2",
            "'category' => 'updates'",
            "'q' => 'launch'",
            "->assertJsonPath('meta.page', 2)",
            "->assertJsonPath('meta.per_page', 1)",
            "->assertJsonPath('meta.category', 'updates')",
            "->assertJsonPath('meta.query', 'launch')",
            "->assertJsonPath('items.0.slug', 'release-wrap-up')",
            "->assertJsonPath('items.1.slug', 'updates')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsPublicVerticalFeatureTest);
        }

        foreach ([
            'test_phase5_blog_content_summary_checkbox_is_locked_to_existing_cms_blog_module',
            'PanelBlogPostController',
            'panel.sites.blog-posts.index',
            'panel.sites.blog-posts.store',
            'loadBlogPosts',
            'handleSaveBlogPost',
            'handleDeleteBlogPostConfirmed',
            'CmsBlogPostsManagementTest',
        ] as $needle) {
            $this->assertStringContainsString($needle, $blogSummaryLockTest);
        }

        foreach ([
            'CMS blog builder component coverage contracts',
            'webu_blog_post_list_01',
            'webu_blog_post_detail_01',
            'webu_blog_category_list_01',
            'data-webby-blog-post-list',
            'data-webby-blog-post-detail',
            'data-webby-blog-category-list',
            'createSyntheticBlogPlaceholder',
            'applyBlogPreviewState',
            "if (normalizedSectionType === 'webu_blog_post_list_01')",
            "if (normalizedSectionType === 'webu_blog_category_list_01')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $blogFrontendContract);
        }

        foreach ([
            'CMS universal component library activation contracts',
            'BUILDER_UNIVERSAL_TAXONOMY_GROUP_ORDER',
            'builderSectionAvailabilityMatrix',
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationFrontendContract);
        }
        foreach ([
            "key: 'booking'",
            "key: 'portfolio'",
        ] as $needle) {
            $this->assertStringContainsString($needle, $activationUnitTest);
        }

        foreach ([
            "rowsByKey['blog.postList']",
            "rowsByKey['blog.postDetail']",
            "rowsByKey['blog.categoryList']",
            'CmsBlogBuilderCoverage.contract.test.ts',
        ] as $needle) {
            $this->assertStringContainsString($needle, $coverageGapAuditUnitTest);
        }

        foreach ([
            "rowsByKey['blog.postDetail']",
            'webu_blog_post_detail_01',
        ] as $needle) {
            $this->assertStringContainsString($needle, $aliasMapUnitTest);
        }

        foreach ([
            'BuilderServicePublicVerticalRuntimeHelpersContractTest',
            'window.WebbyBlog = window.WebbyBlog || {};',
            'window.WebbyBlog.listPosts = function (params) {',
            "window.WebbyBlog.listCategories = function () { return cmsPublicJson('/post-categories'); };",
            'window.WebbyBlog.mountCategoryListWidget = mountBlogCategoryListWidget;',
            'mountBlogPostListWidget(container)',
            'mountBlogPostDetailWidget(container)',
            'mountBlogCategoryListWidget(container)',
            '[data-webby-blog-post-list]',
            '[data-webby-blog-post-detail]',
            '[data-webby-blog-category-list]',
        ] as $needle) {
            $this->assertStringContainsString($needle, $runtimeContractTest);
        }

        foreach ([
            'webu-public-core-minimal.v1.openapi.yaml',
            '/public/sites/{site}/pages/{slug}:',
            '/public/sites/{site}/assets/{path}:',
        ] as $needle) {
            $this->assertStringContainsString($needle, $minimalOpenApiDeliverableTest);
        }
    }
}
