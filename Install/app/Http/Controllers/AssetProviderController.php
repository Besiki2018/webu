<?php

namespace App\Http\Controllers;

use App\Cms\Exceptions\CmsDomainException;
use App\Models\Media;
use App\Models\Project;
use App\Services\Assets\ImageImportService;
use App\Services\Assets\ImageSearchService;
use App\Services\Assets\StockImageProviderConfigurationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetProviderController extends Controller
{
    public function __construct(
        protected ImageSearchService $search,
        protected ImageImportService $import
    ) {}

    public function search(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:30'],
            'orientation' => ['nullable', 'string', 'in:landscape,portrait,square'],
        ]);

        try {
            $results = $this->search->search(
                (string) $validated['query'],
                (int) ($validated['limit'] ?? 12),
                [
                    'orientation' => $validated['orientation'] ?? null,
                ]
            );
        } catch (StockImageProviderConfigurationException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'query' => (string) $validated['query'],
            'results' => $results,
        ]);
    }

    public function import(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'provider' => ['required', 'string', 'in:unsplash,pexels,freepik'],
            'image_id' => ['required', 'string', 'max:191'],
            'download_url' => ['required', 'url', 'max:2048'],
            'project_id' => ['required', 'uuid', 'exists:projects,id'],
            'title' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'license' => ['nullable', 'string', 'max:255'],
            'imported_by' => ['nullable', 'string', 'max:64'],
            'section_local_id' => ['nullable', 'string', 'max:191'],
            'component_key' => ['nullable', 'string', 'max:191'],
            'page_slug' => ['nullable', 'string', 'max:191'],
            'page_id' => ['nullable', 'string', 'max:191'],
            'query' => ['nullable', 'string', 'max:255'],
        ]);

        $project = Project::query()
            ->with('site')
            ->findOrFail((string) $validated['project_id']);
        $this->authorize('update', $project);

        $user = $request->user();
        if (! $user) {
            return response()->json([
                'error' => 'Unauthorized.',
            ], 401);
        }

        try {
            $media = $this->import->import($project, $user, $validated);
        } catch (StockImageProviderConfigurationException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
            ], 422);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Stock image imported successfully.',
            'media' => $this->serializeMedia($media),
        ], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMedia(Media $media): array
    {
        return [
            'id' => $media->id,
            'site_id' => $media->site_id,
            'path' => $media->path,
            'mime' => $media->mime,
            'size' => (int) $media->size,
            'meta_json' => $media->meta_json ?? [],
            'asset_url' => route('public.sites.assets', ['site' => $media->site_id, 'path' => $media->path]),
            'created_at' => $media->created_at?->toISOString(),
        ];
    }
}
