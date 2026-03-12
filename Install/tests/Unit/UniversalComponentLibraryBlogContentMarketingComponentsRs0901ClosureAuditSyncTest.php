<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalComponentLibraryBlogContentMarketingComponentsRs0901ClosureAuditSyncTest extends TestCase
{
    public function test_rs_09_01_closure_audit_locks_blog_content_marketing_runtime_hooks_endpoints_pagination_filters_and_dod_closure(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $backlogPath = base_path('../PROJECT_SOURCE_SPEC_EXECUTION_BACKLOG_KA.md');
        $baselineDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BLOG_CONTENT_MARKETING_COMPONENTS_PARITY_BASELINE_GAP_AUDIT_RS_09_01_2026_02_25.md');
        $closureDocPath = base_path('docs/qa/UNIVERSAL_COMPONENT_LIBRARY_BLOG_CONTENT_MARKETING_COMPONENTS_PARITY_RUNTIME_ENDPOINT_WIDGET_HOOKS_CLOSURE_AUDIT_RS_09_01_2026_02_26.md');

        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $webRoutesPath = base_path('routes/web.php');
        $panelBlogControllerPath = base_path('app/Http/Controllers/Cms/PanelBlogPostController.php');
        $publicSiteControllerPath = base_path('app/Http/Controllers/Cms/PublicSiteController.php');
        $builderServicePath = base_path('app/Services/BuilderService.php');
        $publicCoreOpenApiPath = base_path('docs/openapi/webu-public-core-minimal.v1.openapi.yaml');

        $cmsPublicVerticalFeatureTestPath = base_path('tests/Feature/Cms/CmsPublicVerticalModulesEndpointsTest.php');
        $blogPostsManagementTestPath = base_path('tests/Feature/Cms/CmsBlogPostsManagementTest.php');
        $blogSummaryLockTestPath = base_path('tests/Unit/UniversalBlogContentModuleSummaryP5Test.php');
        $runtimeContractTestPath = base_path('tests/Unit/BuilderServicePublicVerticalRuntimeHelpersContractTest.php');
        $baselineSyncTestPath = base_path('tests/Unit/UniversalComponentLibraryBlogContentMarketingComponentsRs0901BaselineGapAuditSyncTest.php');
        $blogFrontendContractPath = base_path('resources/js/Pages/Project/__tests__/CmsBlogBuilderCoverage.contract.test.ts');
        $activationUnitTestPath = base_path('tests/Unit/UniversalComponentLibraryActivationP5F5Test.php');
        $minimalOpenApiDeliverableTestPath = base_path('tests/Unit/MinimalOpenApiBaseModulesDeliverableTest.php');

        foreach ([
            $roadmapPath,
            $backlogPath,
            $baselineDocPath,
            $closureDocPath,
            $cmsPath,
            $webRoutesPath,
            $panelBlogControllerPath,
            $publicSiteControllerPath,
            $builderServicePath,
            $publicCoreOpenApiPath,
            $cmsPublicVerticalFeatureTestPath,
            $blogPostsManagementTestPath,
            $blogSummaryLockTestPath,
            $runtimeContractTestPath,
            $baselineSyncTestPath,
            $blogFrontendContractPath,
            $activationUnitTestPath,
            $minimalOpenApiDeliverableTestPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $backlog = File::get($backlogPath);
        $closureDoc = File::get($closureDocPath);
        $cms = File::get($cmsPath);
        $routes = File::get($webRoutesPath);
        $panelBlogController = File::get($panelBlogControllerPath);
        $publicSiteController = File::get($publicSiteControllerPath);
        $builderService = File::get($builderServicePath);
        $publicCoreOpenApi = File::get($publicCoreOpenApiPath);
        $cmsPublicVerticalFeatureTest = File::get($cmsPublicVerticalFeatureTestPath);
        $blogPostsManagementTest = File::get($blogPostsManagementTestPath);
        $blogSummaryLockTest = File::get($blogSummaryLockTestPath);
        $runtimeContractTest = File::get($runtimeContractTestPath);
        $blogFrontendContract = File::get($blogFrontendContractPath);
        $activationUnitTest = File::get($activationUnitTestPath);
        $minimalOpenApiDeliverableTest = File::get($minimalOpenApiDeliverableTestPath);

        foreach ([
            '# 9) BLOG / CONTENT MARKETING COMPONENTS',
            '## 9.1 blog.postList',
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
            '`✅` DoD closure achieved: all 3 components pass data + UI parity checks (public endpoint bindings + pagination/category filters verification + builder preview/runtime hook evidence)',
            '`🧪` RS-09-01 closure sync lock added (baseline lock retained)',
        ] as $needle) {
            $this->assertStringContainsString($needle, $backlog);
        }

        foreach ([
            'Status: `DONE`',
            '## Goal (`RS-09-01` Closure Pass)',
            '## ✅ What Was Done (Closure Pass)',
            'GET /public/sites/{site}/posts',
            'GET /public/sites/{site}/posts/{slug}',
            'GET /public/sites/{site}/post-categories',
            'blogPosts(...)',
            'blogPost(...)',
            'blogCategories(...)',
            'page',
            'per_page',
            'q',
            'category',
            'window.WebbyBlog',
            'listPosts(...)',
            'getPost(...)',
            'listCategories(...)',
            'mountPostListWidget',
            'mountPostDetailWidget',
            'mountCategoryListWidget',
            '## Executive Result (`RS-09-01`)',
            '`RS-09-01` is now **DoD-complete** as a blog/content-marketing parity runtime verification task.',
            '## Blog / Content Marketing Runtime Closure Matrix (`blog.postList`, `blog.postDetail`, `blog.categoryList`)',
            'accepted_equivalent_variant',
            '## Endpoint Integration Closure Matrix (`GET /posts`, `GET /posts/:slug`, `GET /post-categories`)',
            '## Pagination / Category Filters Closure (`blog.postList`)',
            'Public API Pagination/Filter Verification (new closure evidence)',
            'meta.page',
            'meta.per_page',
            'meta.category',
            'meta.query',
            '## Published Runtime Hook Closure (`BuilderService`)',
            '[data-webby-blog-post-list]',
            '[data-webby-blog-post-detail]',
            '[data-webby-blog-category-list]',
            '## Feature / Runtime Evidence Added (Closure Pass)',
            'CmsPublicVerticalModulesEndpointsTest.php',
            'BuilderServicePublicVerticalRuntimeHelpersContractTest.php',
            '## DoD Closure Matrix (`RS-09-01`)',
            'all 3 components pass data + UI parity checks',
            '## Remaining Exactness / Modeling Gaps (Truthful, Non-Blocking for `RS-09-01` DoD)',
            'layout',
            '## DoD Verdict (`RS-09-01`)',
            '`RS-09-01` passes and is `DONE`.',
        ] as $needle) {
            $this->assertStringContainsString($needle, $closureDoc);
        }

        foreach ([
            "pagination.textContent = mode === 'load_more'",
            "t('Infinite scroll preview')",
            'variant',
            'show_counts',
            "if (normalizedSectionType === 'webu_blog_post_list_01')",
            "if (normalizedSectionType === 'webu_blog_category_list_01')",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cms);
        }

        foreach ([
            "Route::get('/{site}/posts', [PublicSiteController::class, 'blogPosts'])->name('public.sites.posts.index');",
            "Route::get('/{site}/posts/{slug}', [PublicSiteController::class, 'blogPost'])->name('public.sites.posts.show');",
            "Route::get('/{site}/post-categories', [PublicSiteController::class, 'blogCategories'])->name('public.sites.post-categories.index');",
            "Route::get('/blog-posts', [PanelBlogPostController::class, 'index'])->name('panel.sites.blog-posts.index');",
        ] as $needle) {
            $this->assertStringContainsString($needle, $routes);
        }

        foreach ([
            'class PanelBlogPostController extends Controller',
            'public function index(Site $site): JsonResponse',
            'public function destroy(Site $site, BlogPost $blogPost): JsonResponse',
        ] as $needle) {
            $this->assertStringContainsString($needle, $panelBlogController);
        }

        foreach ([
            'public function blogPosts(Request $request, Site $site): JsonResponse',
            'public function blogPost(Request $request, Site $site, string $slug): JsonResponse',
            'public function blogCategories(Request $request, Site $site): JsonResponse',
            "'q' => ['nullable', 'string', 'max:255']",
            "'category' => ['nullable', 'string', 'max:120']",
            "'page' => ['nullable', 'integer', 'min:1']",
            "'per_page' => ['nullable', 'integer', 'min:1', 'max:50']",
            '\'page\' => $page,',
            '\'per_page\' => $perPage,',
            '\'query\' => $queryText !== \'\' ? $queryText : null,',
            '\'category\' => $categorySlug !== \'\' ? $categorySlug : null,',
            '\'categories\' => $categories,',
            '\'posts_count\' => (int) ($countsByCategory[$row->id] ?? 0),',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicSiteController);
        }

        foreach ([
            'function mountBlogPostListWidget(container) {',
            'function mountBlogPostDetailWidget(container) {',
            'function mountBlogCategoryListWidget(container) {',
            'window.WebbyBlog = window.WebbyBlog || {};',
            'window.WebbyBlog.listPosts = function (params) {',
            "window.WebbyBlog.getPost = function (slug) { return cmsPublicJson('/posts/' + encodeURIComponent(String(slug || ''))); };",
            "window.WebbyBlog.listCategories = function () { return cmsPublicJson('/post-categories'); };",
            'window.WebbyBlog.mountPostListWidget = mountBlogPostListWidget;',
            'window.WebbyBlog.mountPostDetailWidget = mountBlogPostDetailWidget;',
            'window.WebbyBlog.mountCategoryListWidget = mountBlogCategoryListWidget;',
            'var query = new URLSearchParams();',
            'query.set(key, String(data[key]));',
            "return cmsPublicJson('/posts' + (query.toString() ? ('?' + query.toString()) : ''));",
            '[data-webby-blog-post-list]',
            '[data-webby-blog-post-detail]',
            '[data-webby-blog-category-list]',
            'data-webby-blog-runtime',
        ] as $needle) {
            $this->assertStringContainsString($needle, $builderService);
        }

        foreach ([
            '/public/sites/{site}/posts:',
            'summary: Public blog posts list',
            '/public/sites/{site}/posts/{slug}:',
            'summary: Public blog post detail by slug',
            '/public/sites/{site}/post-categories:',
            'summary: Public blog category list',
            'description: Blog posts list',
            'description: Blog category list',
        ] as $needle) {
            $this->assertStringContainsString($needle, $publicCoreOpenApi);
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
            "->assertJsonPath('items.1.posts_count', 1)",
        ] as $needle) {
            $this->assertStringContainsString($needle, $cmsPublicVerticalFeatureTest);
        }

        foreach ([
            'test_owner_can_create_update_and_delete_blog_post',
            "route('panel.sites.blog-posts.store'",
            '->assertCreated()',
        ] as $needle) {
            $this->assertStringContainsString($needle, $blogPostsManagementTest);
        }

        foreach ([
            'test_phase5_blog_content_summary_checkbox_is_locked_to_existing_cms_blog_module',
            'PanelBlogPostController',
            'loadBlogPosts',
            'handleSaveBlogPost',
            'handleDeleteBlogPostConfirmed',
        ] as $needle) {
            $this->assertStringContainsString($needle, $blogSummaryLockTest);
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
            'CMS blog builder component coverage contracts',
            'webu_blog_post_list_01',
            'webu_blog_post_detail_01',
            'webu_blog_category_list_01',
            'data-webby-blog-post-list',
            'data-webby-blog-category-list',
        ] as $needle) {
            $this->assertStringContainsString($needle, $blogFrontendContract);
        }

        $this->assertStringContainsString("key: 'portfolio'", $activationUnitTest);

        foreach ([
            'webu-public-core-minimal.v1.openapi.yaml',
            'webu-services-booking-minimal.v1.openapi.yaml',
        ] as $needle) {
            $this->assertStringContainsString($needle, $minimalOpenApiDeliverableTest);
        }
    }
}
