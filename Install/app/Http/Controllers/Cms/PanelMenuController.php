<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Contracts\CmsPanelMenuServiceContract;
use App\Cms\Exceptions\CmsDomainException;
use App\Http\Controllers\Controller;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PanelMenuController extends Controller
{
    public function __construct(
        protected CmsPanelMenuServiceContract $menus
    ) {}

    /**
     * List available menus for site.
     */
    public function index(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);
        $validated = $request->validate([
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        try {
            $payload = $this->menus->index($site, $validated['locale'] ?? null);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($payload);
    }

    /**
     * Create a new menu.
     */
    public function store(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'key' => ['required', 'string', 'max:64'],
            'items_json' => ['sometimes', 'array'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        try {
            $payload = $this->menus->store(
                $site,
                $validated['key'],
                $validated['items_json'] ?? [],
                $validated['locale'] ?? null
            );
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($payload, 201);
    }

    /**
     * Get menu by key for site.
     */
    public function show(Request $request, Site $site, string $key): JsonResponse
    {
        Gate::authorize('view', $site->project);
        $validated = $request->validate([
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        try {
            $payload = $this->menus->show($site, $key, $validated['locale'] ?? null);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($payload);
    }

    /**
     * Update menu items.
     */
    public function update(Request $request, Site $site, string $key): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $validated = $request->validate([
            'items_json' => ['required', 'array'],
            'locale' => ['nullable', 'string', 'max:10'],
        ]);

        try {
            $payload = $this->menus->update($site, $key, $validated['items_json'], $validated['locale'] ?? null);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($payload);
    }

    /**
     * Delete menu by key.
     */
    public function destroy(Site $site, string $key): JsonResponse
    {
        Gate::authorize('update', $site->project);

        try {
            $payload = $this->menus->destroy($site, $key);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json($payload);
    }
}
