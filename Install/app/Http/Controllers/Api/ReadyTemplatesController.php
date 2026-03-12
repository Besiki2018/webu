<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ReadyTemplatesService;
use Illuminate\Http\JsonResponse;

class ReadyTemplatesController extends Controller
{
    public function list(ReadyTemplatesService $service): JsonResponse
    {
        return response()->json($service->list());
    }
}
