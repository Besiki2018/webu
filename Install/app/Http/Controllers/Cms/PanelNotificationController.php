<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Exceptions\CmsDomainException;
use App\Cms\Services\CmsNotificationsModuleService;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\SiteNotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PanelNotificationController extends Controller
{
    public function __construct(
        protected CmsNotificationsModuleService $notifications
    ) {}

    public function templates(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $validated = $request->validate([
            'channel' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:20'],
            'event_key' => ['nullable', 'string', 'max:120'],
        ]);

        return response()->json($this->notifications->listTemplates($site, $validated));
    }

    public function showTemplate(Site $site, SiteNotificationTemplate $template): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json($this->notifications->showTemplate($site, $template));
    }

    public function storeTemplate(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:255'],
            'channel' => ['required', 'string', 'max:20'],
            'event_key' => ['required', 'string', 'max:120'],
            'locale' => ['nullable', 'string', 'max:12'],
            'status' => ['nullable', 'string', 'max:20'],
            'subject_template' => ['nullable', 'string', 'max:500'],
            'body_template' => ['required', 'string'],
            'variables_json' => ['nullable', 'array'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $template = $this->notifications->createTemplate($site, $validated, $request->user()?->id);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Notification template created successfully.',
            'template' => $this->notifications->showTemplate($site, $template)['template'],
        ], 201);
    }

    public function updateTemplate(Request $request, Site $site, SiteNotificationTemplate $template): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'key' => ['sometimes', 'string', 'max:120'],
            'name' => ['sometimes', 'string', 'max:255'],
            'channel' => ['sometimes', 'string', 'max:20'],
            'event_key' => ['sometimes', 'string', 'max:120'],
            'locale' => ['sometimes', 'string', 'max:12'],
            'status' => ['sometimes', 'string', 'max:20'],
            'subject_template' => ['nullable', 'string', 'max:500'],
            'body_template' => ['sometimes', 'string'],
            'variables_json' => ['sometimes', 'array'],
            'meta_json' => ['nullable', 'array'],
        ]);

        try {
            $updated = $this->notifications->updateTemplate($site, $template, $validated, $request->user()?->id);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Notification template updated successfully.',
            'template' => $this->notifications->showTemplate($site, $updated)['template'],
        ]);
    }

    public function destroyTemplate(Site $site, SiteNotificationTemplate $template): JsonResponse
    {
        Gate::authorize('update', $site->project);

        try {
            $payload = $this->notifications->deleteTemplate($site, $template);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($payload);
    }

    public function logs(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $validated = $request->validate([
            'channel' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:20'],
            'event_key' => ['nullable', 'string', 'max:120'],
            'template_id' => ['nullable', 'integer'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($this->notifications->listLogs($site, $validated));
    }

    public function previewDispatch(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'template_id' => ['nullable', 'integer'],
            'template_key' => ['nullable', 'string', 'max:120'],
            'recipient' => ['required', 'string', 'max:255'],
            'payload_json' => ['nullable', 'array'],
            'meta_json' => ['nullable', 'array'],
            'status' => ['nullable', 'string', 'max:20'],
            'provider' => ['nullable', 'string', 'max:80'],
            'provider_message_id' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $payload = $this->notifications->previewDispatchAndLog($site, $validated, $request->user()?->id);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($payload, 201);
    }
}
