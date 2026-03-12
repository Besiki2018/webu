<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalLearningExperimentsAdminUiP6Test extends TestCase
{
    public function test_phase6_learning_and_experiments_admin_ui_summary_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/CMS_LEARNING_EXPERIMENTS_ADMIN_UI_P6_BASELINE.md');
        $cmsPath = base_path('resources/js/Pages/Project/Cms.tsx');
        $routesPath = base_path('routes/web.php');
        $controllerPath = base_path('app/Http/Controllers/Cms/PanelLearningController.php');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([$docPath, $cmsPath, $routesPath, $controllerPath] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $cms = File::get($cmsPath);
        $routes = File::get($routesPath);
        $controller = File::get($controllerPath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('Admin UI for learning and experiments', $doc);
        $this->assertStringContainsString('Activity', $doc);
        $this->assertStringContainsString('P6-G2-04', $doc);
        $this->assertStringContainsString('PanelLearningController', $doc);
        $this->assertStringContainsString('/panel/sites/{site}/cms/learning/rules', $doc);
        $this->assertStringContainsString('/panel/sites/{site}/cms/learning/experiments', $doc);

        $this->assertStringContainsString("isLearningAdminActivitySection = isAdminUser && activeSection === 'activity'", $cms);
        $this->assertStringContainsString('data-webu-learning-admin-panel="activity"', $cms);
        $this->assertStringContainsString('loadLearningAdminPanel', $cms);
        $this->assertStringContainsString('handleDisableLearningRule', $cms);
        $this->assertStringContainsString('handleDisableLearningExperiment', $cms);
        $this->assertStringContainsString('/cms/learning/rules/${rule.id}/disable', $cms);
        $this->assertStringContainsString('/cms/learning/experiments/${experiment.id}/disable', $cms);

        $this->assertStringContainsString('class PanelLearningController extends Controller', $controller);
        $this->assertStringContainsString('rules(Request $request, Site $site)', $controller);
        $this->assertStringContainsString('experiments(Request $request, Site $site)', $controller);

        $this->assertStringContainsString('panel.sites.cms.learning.rules.index', $routes);
        $this->assertStringContainsString('panel.sites.cms.learning.experiments.index', $routes);
        $this->assertStringContainsString('panel.sites.cms.learning.rules.disable', $routes);
        $this->assertStringContainsString('panel.sites.cms.learning.experiments.disable', $routes);

        $this->assertStringContainsString('- ✅ Admin UI for learning and experiments', $roadmap);
    }
}
