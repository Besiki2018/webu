<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Exceptions\CmsDomainException;
use App\Cms\Services\CmsFormsLeadsService;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteForm;
use App\Models\SiteFormLead;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PanelFormController extends Controller
{
    public function __construct(
        protected CmsFormsLeadsService $forms
    ) {}

    public function index(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        return response()->json($this->forms->listForms($site, $validated));
    }

    public function show(Site $site, SiteForm $form): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json($this->forms->showForm($site, $form));
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:20'],
            'schema_json' => ['required', 'array'],
            'settings_json' => ['nullable', 'array'],
        ]);

        try {
            $form = $this->forms->createForm($site, $validated);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Form created successfully.',
            'form' => $this->forms->showForm($site, $form)['form'],
        ], 201);
    }

    public function update(Request $request, Site $site, SiteForm $form): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'key' => ['sometimes', 'string', 'max:120'],
            'name' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'max:20'],
            'schema_json' => ['sometimes', 'array'],
            'settings_json' => ['nullable', 'array'],
        ]);

        try {
            $updated = $this->forms->updateForm($site, $form, $validated);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Form updated successfully.',
            'form' => $this->forms->showForm($site, $updated)['form'],
        ]);
    }

    public function destroy(Site $site, SiteForm $form): JsonResponse
    {
        Gate::authorize('update', $site->project);

        try {
            $payload = $this->forms->deleteForm($site, $form);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($payload);
    }

    public function leads(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $validated = $request->validate([
            'status' => ['nullable', 'string', 'max:20'],
            'form_key' => ['nullable', 'string', 'max:120'],
            'form_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->forms->listLeads($site, $validated));
    }

    public function updateLeadStatus(Request $request, Site $site, SiteFormLead $lead): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'status' => ['required', 'string', 'max:20'],
        ]);

        try {
            $updated = $this->forms->updateLeadStatus($site, $lead, $validated['status']);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Lead status updated successfully.',
            'lead' => [
                'id' => $updated->id,
                'status' => $updated->status,
                'form_id' => $updated->site_form_id,
                'form_key' => $updated->form?->key,
                'updated_at' => $updated->updated_at?->toISOString(),
            ],
        ]);
    }
}
