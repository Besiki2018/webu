<?php

namespace App\Http\Controllers\Cms;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\CmsTelemetryCollectorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PanelTelemetryController extends Controller
{
    public function __construct(
        protected CmsTelemetryCollectorService $telemetry
    ) {}

    public function store(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        $payload = $this->telemetry->collectFromRequest($site, $request, [
            'channel' => 'panel',
        ]);

        return response()->json($payload, 202);
    }
}
