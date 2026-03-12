<?php

namespace Tests\Feature\Cms;

use App\Cms\Contracts\CmsPanelMediaServiceContract;
use App\Cms\Contracts\CmsPanelBlogPostServiceContract;
use App\Cms\Contracts\CmsPanelMenuServiceContract;
use App\Cms\Contracts\CmsPanelPageServiceContract;
use App\Cms\Contracts\CmsPanelSiteServiceContract;
use App\Cms\Contracts\CmsPublicSiteServiceContract;
use App\Cms\Contracts\CmsRepositoryContract;
use App\Cms\Repositories\EloquentCmsRepository;
use App\Cms\Services\CmsPanelMediaService;
use App\Cms\Services\CmsPanelBlogPostService;
use App\Cms\Services\CmsPanelMenuService;
use App\Cms\Services\CmsPanelPageService;
use App\Cms\Services\CmsPanelSiteService;
use App\Cms\Services\CmsPublicSiteService;
use App\Http\Controllers\Cms\PanelMediaController;
use App\Http\Controllers\Cms\PanelBlogPostController;
use App\Http\Controllers\Cms\PanelMenuController;
use App\Http\Controllers\Cms\PanelPageController;
use App\Http\Controllers\Cms\PanelSiteController;
use App\Http\Controllers\Cms\PublicSiteController;
use App\Services\CmsRuntimePayloadService;
use App\Services\SiteProvisioningService;
use ReflectionClass;
use Tests\TestCase;

class CmsBoundaryContractsTest extends TestCase
{
    public function test_cms_contract_bindings_resolve_expected_implementations(): void
    {
        $this->assertInstanceOf(EloquentCmsRepository::class, app(CmsRepositoryContract::class));
        $this->assertInstanceOf(CmsPanelPageService::class, app(CmsPanelPageServiceContract::class));
        $this->assertInstanceOf(CmsPanelSiteService::class, app(CmsPanelSiteServiceContract::class));
        $this->assertInstanceOf(CmsPanelMenuService::class, app(CmsPanelMenuServiceContract::class));
        $this->assertInstanceOf(CmsPanelMediaService::class, app(CmsPanelMediaServiceContract::class));
        $this->assertInstanceOf(CmsPanelBlogPostService::class, app(CmsPanelBlogPostServiceContract::class));
        $this->assertInstanceOf(CmsPublicSiteService::class, app(CmsPublicSiteServiceContract::class));
    }

    public function test_cms_controllers_depend_on_service_contracts(): void
    {
        $this->assertConstructorHasDependency(
            PanelPageController::class,
            CmsPanelPageServiceContract::class
        );
        $this->assertConstructorHasDependency(
            PanelSiteController::class,
            CmsPanelSiteServiceContract::class
        );
        $this->assertConstructorHasDependency(
            PanelMenuController::class,
            CmsPanelMenuServiceContract::class
        );
        $this->assertConstructorHasDependency(
            PanelMediaController::class,
            CmsPanelMediaServiceContract::class
        );
        $this->assertConstructorHasDependency(
            PanelBlogPostController::class,
            CmsPanelBlogPostServiceContract::class
        );
        $this->assertConstructorHasDependency(
            PublicSiteController::class,
            CmsPublicSiteServiceContract::class
        );
    }

    public function test_cms_core_services_depend_on_repository_contract(): void
    {
        $this->assertConstructorHasDependency(
            CmsPanelPageService::class,
            CmsRepositoryContract::class
        );
        $this->assertConstructorHasDependency(
            CmsPanelSiteService::class,
            CmsRepositoryContract::class
        );
        $this->assertConstructorHasDependency(
            CmsPanelMenuService::class,
            CmsRepositoryContract::class
        );
        $this->assertConstructorHasDependency(
            CmsPanelMediaService::class,
            CmsRepositoryContract::class
        );
        $this->assertConstructorHasDependency(
            CmsPanelBlogPostService::class,
            CmsRepositoryContract::class
        );
        $this->assertConstructorHasDependency(
            CmsPublicSiteService::class,
            CmsRepositoryContract::class
        );
        $this->assertConstructorHasDependency(
            SiteProvisioningService::class,
            CmsRepositoryContract::class
        );
        $this->assertConstructorHasDependency(
            CmsRuntimePayloadService::class,
            CmsRepositoryContract::class
        );
    }

    private function assertConstructorHasDependency(string $className, string $dependency): void
    {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();
        $types = collect($constructor?->getParameters() ?? [])
            ->map(fn ($parameter): ?string => $parameter->getType()?->getName())
            ->filter()
            ->values()
            ->all();

        $this->assertContains($dependency, $types);
    }
}
