<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\CmsExperiment;
use App\Models\CmsLearnedRule;
use App\Models\Site;
use App\Services\CmsLearningAdminControlService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PanelLearningController extends Controller
{
    public function __construct(
        protected CmsLearningAdminControlService $learning
    ) {}

    public function rules(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:20'],
            'active' => ['nullable'],
            'component_type' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json($this->learning->listLearnedRules($site, $validated));
    }

    public function showRule(Site $site, CmsLearnedRule $rule): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json($this->learning->showLearnedRule($site, $rule));
    }

    public function disableRule(Request $request, Site $site, CmsLearnedRule $rule): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($this->learning->disableLearnedRule($site, $rule, [
            'reason' => $validated['reason'] ?? null,
            'actor_id' => $request->user()?->id,
        ]));
    }

    public function experiments(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:20'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return response()->json($this->learning->listExperiments($site, $validated));
    }

    public function showExperiment(Site $site, CmsExperiment $experiment): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json($this->learning->showExperiment($site, $experiment));
    }

    public function disableExperiment(Request $request, Site $site, CmsExperiment $experiment): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($this->learning->disableExperiment($site, $experiment, [
            'reason' => $validated['reason'] ?? null,
            'actor_id' => $request->user()?->id,
        ]));
    }
}
