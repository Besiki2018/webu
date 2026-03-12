<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * AI Layout Generator Playground — test prompt → layout JSON → render with mock CMS data.
 * Auth required so "Create project" works. No raw HTML; layout JSON only.
 */
class AiLayoutPlaygroundController extends Controller
{
    public function index(Request $request)
    {
        $response = Inertia::render('AiLayoutPlayground', []);
        $res = $response->toResponse($request);
        $res->headers->set('X-Robots-Tag', 'noindex, nofollow');
        return $res;
    }
}
