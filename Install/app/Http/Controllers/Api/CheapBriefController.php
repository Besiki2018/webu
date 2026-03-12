<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiWebsiteGeneration\WebsiteBriefExtractor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Ultra Cheap Mode: extract brief from prompt (rule-based, no main model).
 * Returns minimal JSON for frontend; if confidence < 0.75 could add oneQuestion (future).
 */
class CheapBriefController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'prompt' => 'required|string|max:2000',
        ]);

        $brief = app(WebsiteBriefExtractor::class)->extract($validated['prompt']);

        return response()->json([
            'brief' => [
                'websiteType' => $brief['websiteType'],
                'category' => $brief['category'] ?? ($brief['businessType'] ?? 'general'),
                'style' => $brief['style'],
                'language' => $brief['language'],
                'brandName' => $brief['brandName'],
            ],
            'confidence' => 1.0,
        ]);
    }
}
