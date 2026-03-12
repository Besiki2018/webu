<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Exceptions\CmsDomainException;
use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Models\User;
use App\Services\CmsTelemetryCollectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicTelemetryController extends Controller
{
    public function __construct(
        protected CmsTelemetryCollectorService $telemetry
    ) {}

    public function options(Request $request, Site $site): Response
    {
        try {
            $this->assertVisible($site, $request->user());
        } catch (CmsDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response('', 204)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept')
            ->header('Vary', 'Origin');
    }

    public function store(Request $request, Site $site): JsonResponse
    {
        try {
            $this->assertVisible($site, $request->user());
            $payload = $this->telemetry->collectFromRequest($site, $request, [
                'channel' => 'public',
            ]);
        } catch (CmsDomainException $exception) {
            return $this->corsJson([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return $this->corsJson($payload, 202);
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

    /**
     * @param  array<string, mixed>  $payload
     */
    private function corsJson(array $payload, int $status = 200): JsonResponse
    {
        return response()
            ->json($payload, $status)
            ->header('Access-Control-Allow-Origin', '*')
            ->header('Access-Control-Allow-Methods', 'POST, OPTIONS')
            ->header('Access-Control-Allow-Headers', 'Content-Type, Accept')
            ->header('Vary', 'Origin');
    }
}
