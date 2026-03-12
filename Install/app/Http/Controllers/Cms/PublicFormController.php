<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Exceptions\CmsDomainException;
use App\Cms\Services\CmsFormsLeadsService;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicFormController extends Controller
{
    public function __construct(
        protected CmsFormsLeadsService $forms
    ) {}

    public function show(Request $request, Site $site, string $key): JsonResponse
    {
        try {
            $this->assertVisible($site, $request->user());
            $payload = $this->forms->showPublicForm($site, $key);
        } catch (CmsDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload);
    }

    public function submit(Request $request, Site $site, string $key): JsonResponse
    {
        try {
            $this->assertVisible($site, $request->user());
            $payload = $this->forms->submitPublicLead($site, $key, $request->all(), $request);
        } catch (CmsDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload, 201);
    }

    private function assertVisible(Site $site, ?User $viewer): void
    {
        if ($site->status === 'archived') {
            throw new CmsDomainException('Site not found.', 404);
        }

        if ($site->status === 'published') {
            return;
        }

        if (! $viewer) {
            throw new CmsDomainException('Site not found.', 404);
        }

        if (method_exists($viewer, 'hasAdminBypass') && $viewer->hasAdminBypass()) {
            return;
        }

        $project = $site->project;
        if ($project && (string) $project->user_id === (string) $viewer->id) {
            return;
        }

        throw new CmsDomainException('Site not found.', 404);
    }

    private function corsJson(array $payload, int $status = 200): JsonResponse
    {
        return response()
            ->json($payload, $status)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept')
            ->header('Vary', 'Origin');
    }
}
