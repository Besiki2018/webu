<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalBlogContentModuleSummaryP5Test extends TestCase
{
    public function test_phase5_blog_content_summary_checkbox_is_locked_to_existing_cms_blog_module(): void
    {
        $docPath = base_path('docs/architecture/UNIVERSAL_BLOG_CONTENT_MODULE_P5_SUMMARY_BASELINE.md');
        $routesPath = base_path('routes/web.php');
        $controllerPath = base_path('app/Http/Controllers/Cms/PanelBlogPostController.php');
        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $featureTestPath = base_path('tests/Feature/Cms/CmsBlogPostsManagementTest.php');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([$docPath, $routesPath, $controllerPath, $cmsPath, $featureTestPath] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $routes = File::get($routesPath);
        $controller = File::get($controllerPath);
        $cms = File::get($cmsPath);
        $featureTest = File::get($featureTestPath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('Blog/content', $doc);
        $this->assertStringContainsString('P5-F2-01', $doc);
        $this->assertStringContainsString('PanelBlogPostController', $doc);
        $this->assertStringContainsString('/panel/sites/{site}/blog-posts', $doc);

        $this->assertStringContainsString("panel.sites.blog-posts.index", $routes);
        $this->assertStringContainsString("panel.sites.blog-posts.store", $routes);
        $this->assertStringContainsString("panel.sites.blog-posts.update", $routes);
        $this->assertStringContainsString("panel.sites.blog-posts.destroy", $routes);

        $this->assertStringContainsString('class PanelBlogPostController extends Controller', $controller);
        $this->assertStringContainsString('function store(Request $request, Site $site)', $controller);
        $this->assertStringContainsString('function update(Request $request, Site $site, BlogPost $blogPost)', $controller);
        $this->assertStringContainsString('function destroy(Site $site, BlogPost $blogPost)', $controller);

        $this->assertStringContainsString("'blog-posts'", $cms);
        $this->assertStringContainsString('/panel/sites/${site.id}/blog-posts', $cms);
        $this->assertStringContainsString('loadBlogPosts', $cms);
        $this->assertStringContainsString('handleSaveBlogPost', $cms);
        $this->assertStringContainsString('handleDeleteBlogPostConfirmed', $cms);

        $this->assertStringContainsString('CmsBlogPostsManagementTest', $featureTest);
        $this->assertStringContainsString('test_owner_can_create_update_and_delete_blog_post', $featureTest);
        $this->assertStringContainsString('test_blog_post_update_is_blocked_for_foreign_tenant', $featureTest);

        $this->assertStringContainsString('- ✅ Blog/content', $roadmap);
    }
}
