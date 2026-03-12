<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class UniversalLearningAdminControlsP6G2Test extends TestCase
{
    public function test_p6_g2_04_admin_controls_for_learning_and_experiments_contract_is_locked(): void
    {
        $docPath = base_path('docs/architecture/CMS_LEARNING_ADMIN_CONTROLS_P6_G2_04.md');
        $controllerPath = base_path('app/Http/Controllers/Cms/PanelLearningController.php');
        $servicePath = base_path('app/Services/CmsLearningAdminControlService.php');
        $routesPath = base_path('routes/web.php');
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');

        foreach ([$docPath, $controllerPath, $servicePath, $routesPath] as $path) {
            $this->assertFileExists($path);
        }

        $doc = File::get($docPath);
        $controller = File::get($controllerPath);
        $service = File::get($servicePath);
        $routes = File::get($routesPath);
        $roadmap = File::get($roadmapPath);

        $this->assertStringContainsString('P6-G2-04', $doc);
        $this->assertStringContainsString('learned rules', strtolower($doc));
        $this->assertStringContainsString('experiments', strtolower($doc));
        $this->assertStringContainsString('inspect', strtolower($doc));
        $this->assertStringContainsString('disable', strtolower($doc));
        $this->assertStringContainsString('tenant.route.scope', $doc);
        $this->assertStringContainsString('P6-G3-01', $doc);

        $this->assertStringContainsString('class PanelLearningController extends Controller', $controller);
        $this->assertStringContainsString('rules(Request $request, Site $site)', $controller);
        $this->assertStringContainsString('disableRule(Request $request, Site $site, CmsLearnedRule $rule)', $controller);
        $this->assertStringContainsString('experiments(Request $request, Site $site)', $controller);
        $this->assertStringContainsString('disableExperiment(Request $request, Site $site, CmsExperiment $experiment)', $controller);

        $this->assertStringContainsString('class CmsLearningAdminControlService', $service);
        $this->assertStringContainsString('listLearnedRules(Site $site', $service);
        $this->assertStringContainsString('disableLearnedRule(Site $site, CmsLearnedRule $rule', $service);
        $this->assertStringContainsString('listExperiments(Site $site', $service);
        $this->assertStringContainsString('disableExperiment(Site $site, CmsExperiment $experiment', $service);
        $this->assertStringContainsString("status = 'disabled'", str_replace('->', ' ', strtolower($service)));
        $this->assertStringContainsString("status = 'paused'", str_replace('->', ' ', strtolower($service)));

        $this->assertStringContainsString("panel.sites.cms.learning.rules.index", $routes);
        $this->assertStringContainsString("panel.sites.cms.learning.rules.disable", $routes);
        $this->assertStringContainsString("panel.sites.cms.learning.experiments.index", $routes);
        $this->assertStringContainsString("panel.sites.cms.learning.experiments.disable", $routes);

        $this->assertStringContainsString("`P6-G2-04` (✅ `DONE`) Admin controls to inspect/disable learned rules.", $roadmap);
    }
}
