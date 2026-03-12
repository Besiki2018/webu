<?php

namespace App\Http\Controllers\Cms;

use App\Cms\Contracts\CmsPanelMediaServiceContract;
use App\Cms\Exceptions\CmsDomainException;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Site;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PanelMediaController extends Controller
{
    public function __construct(
        protected CmsPanelMediaServiceContract $media
    ) {}

    /**
     * List site media assets.
     */
    public function index(Site $site): JsonResponse
    {
        Gate::authorize('view', $site->project);

        return response()->json($this->media->listMedia($site));
    }

    /**
     * Upload site media asset.
     */
    public function upload(Request $request, Site $site): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $user = $request->user();
        if (! $user) {
            return response()->json([
                'error' => 'Unauthorized.',
            ], 401);
        }

        try {
            $maxKb = $this->media->maxUploadKilobytes($user);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        $request->validate([
            'file' => ['required', 'file', "max:{$maxKb}"],
        ]);

        try {
            $media = $this->media->upload($site, $request->file('file'), $user);
        } catch (CmsDomainException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                ...$exception->context(),
            ], $exception->status());
        }

        return response()->json([
            'message' => 'Media uploaded successfully.',
            'media' => $this->serializeMedia($site, $media),
        ], 201);
    }

    /**
     * Update media metadata (alt/description).
     */
    public function update(Request $request, Site $site, Media $media): JsonResponse
    {
        Gate::authorize('update', $site->project);

        $payload = $request->validate([
            'alt' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $updated = $this->media->updateMetadata($site, $media, $payload);

        return response()->json([
            'message' => 'Media details updated successfully.',
            'media' => $this->serializeMedia($site, $updated),
        ]);
    }

    /**
     * Delete a site media asset.
     */
    public function destroy(Site $site, Media $media): JsonResponse
    {
        Gate::authorize('update', $site->project);
        $this->media->delete($site, $media);

        return response()->json([
            'message' => 'Media deleted successfully.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMedia(Site $site, Media $media): array
    {
        return [
            'id' => $media->id,
            'site_id' => $media->site_id,
            'path' => $media->path,
            'mime' => $media->mime,
            'size' => $media->size,
            'meta_json' => $media->meta_json ?? [],
            'asset_url' => route('public.sites.assets', ['site' => $site->id, 'path' => $media->path]),
            'created_at' => $media->created_at?->toISOString(),
        ];
    }
}
