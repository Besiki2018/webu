<?php

namespace App\Http\Controllers\Ecommerce;

use App\Http\Controllers\Controller;
use App\Models\Site;
use App\Services\EcommerceDemoSeederService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PanelDemoController extends Controller
{
    /**
     * Seed demo e-commerce data for the site (categories, products, images).
     * Idempotent: safe to call when store is empty; use for "Load demo products for preview".
     */
    public function seedDemo(Site $site, EcommerceDemoSeederService $seeder): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $seeder->run($site->fresh(), false);

        return response()->json([
            'success' => true,
            'message' => __('Demo products and categories have been added. Refresh the product list or open the storefront preview.'),
        ]);
    }
}
