<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Contracts\CmsModuleRegistryServiceContract;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PanelModuleController extends Controller
{
    public function __construct(
        protected CmsModuleRegistryServiceContract $modules
    ) {}

    /**
     * Return module registry state for a site.
     */
    public function index(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json(
            $this->modules->modules($site, $request->user())
        );
    }

    /**
     * Return feature/module entitlement matrix for a site.
     */
    public function entitlements(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json(
            $this->modules->entitlements($site, $request->user())
        );
    }
}
