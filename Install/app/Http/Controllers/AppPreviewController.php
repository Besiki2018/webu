<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\CmsRuntimePayloadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AppPreviewController extends Controller
{
    public function __construct(
        protected CmsRuntimePayloadService $cmsRuntimePayloads
    ) {}

    /**
     * Serve clean preview files (no inspector script).
     * Access controlled by project visibility settings.
     */
    public function serve(Request $request, Project $project, string $path = 'index.html'): Response
    {
        // Check visibility-based access
        if (! $this->canAccess($project)) {
            abort(404); // Return 404 to not leak project existence
        }

        // Clean and validate the path
        $path = ltrim($path, '/');
        if (empty($path)) {
            $path = 'index.html';
        }

        // Prevent directory traversal
        if (str_contains($path, '..')) {
            abort(403, 'Invalid path');
        }

        if ($this->isCmsBridgeRequest($path)) {
            return $this->serveCmsBridge($request, $project);
        }

        $previewPath = "previews/{$project->id}/{$path}";
        $fullPath = Storage::disk('local')->path($previewPath);

        // Check if the file exists (not directory)
        if (! is_file($fullPath)) {
            $fallbackAssetPath = $this->resolveMissingAssetFallback($project, $path);
            if ($fallbackAssetPath !== null) {
                return response()->file($fallbackAssetPath, [
                    'Content-Type' => $this->getMimeType($path),
                    'Cache-Control' => 'no-cache, no-store, must-revalidate',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                ]);
            }

            // Try index.html for directory requests
            if (! str_contains($path, '.')) {
                $indexPath = "previews/{$project->id}/{$path}/index.html";
                $indexFullPath = Storage::disk('local')->path($indexPath);
                if (is_file($indexFullPath)) {
                    $fullPath = $indexFullPath;
                    $path = rtrim($path, '/').'/index.html';
                } else {
                    // SPA fallback: serve root index.html for client-side routing
                    // This allows React Router to handle routes like /login, /signup, etc.
                    $spaFallbackPath = "previews/{$project->id}/index.html";
                    $spaFallbackFullPath = Storage::disk('local')->path($spaFallbackPath);
                    if (is_file($spaFallbackFullPath)) {
                        $fullPath = $spaFallbackFullPath;
                        $path = 'index.html';
                    } else {
                        abort(404);
                    }
                }
            } else {
                abort(404);
            }
        }

        $mimeType = $this->getMimeType($path);

        // For HTML files, update the base tag to use /app/ instead of /preview/
        if (str_ends_with($path, '.html') || str_ends_with($path, '.htm')) {
            $html = file_get_contents($fullPath);
            $html = preg_replace(
                '/<base href="\/preview\/([^"]+)"/',
                '<base href="/app/$1"',
                $html
            );

            return response($html, 200, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);
        }

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ]);
    }

    private function isCmsBridgeRequest(string $path): bool
    {
        return $path === '__cms/bootstrap' || $path === '__cms/bootstrap/';
    }

    private function serveCmsBridge(Request $request, Project $project): Response
    {
        $slug = $request->query('slug', 'home');
        $locale = $request->query('locale');

        $payload = $this->cmsRuntimePayloads->buildBootstrapPayload(
            project: $project,
            slug: is_string($slug) ? $slug : 'home',
            locale: is_string($locale) ? $locale : null,
            resolvedDomain: $request->getHost(),
            routeParams: $this->extractCmsRouteParams($request)
        );

        return response()
            ->json($payload)
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    /**
     * Check if the current user can access this project.
     */
    protected function canAccess(Project $project): bool
    {
        // If published with public visibility, anyone can access
        if ($project->published_visibility === 'public') {
            return true;
        }

        // For private visibility OR unpublished projects, only owner can access
        return Auth::check() && Auth::id() === $project->user_id;
    }

    /**
     * Get MIME type based on file extension.
     */
    protected function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        $mimeTypes = [
            'html' => 'text/html',
            'htm' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'mjs' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
            'otf' => 'font/otf',
            'txt' => 'text/plain',
            'xml' => 'application/xml',
            'pdf' => 'application/pdf',
            'map' => 'application/json',
        ];

        return $mimeTypes[$extension] ?? 'application/octet-stream';
    }

    private function resolveMissingAssetFallback(Project $project, string $path): ?string
    {
        $relativePath = ltrim($path, '/');
        if ($relativePath === '' || str_contains($relativePath, '/')) {
            return null;
        }

        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        if (! in_array($extension, ['svg', 'png', 'jpg', 'jpeg', 'webp', 'gif'], true)) {
            return null;
        }

        $previewAssetPath = Storage::disk('local')->path("previews/{$project->id}/assets/{$relativePath}");
        if (is_file($previewAssetPath)) {
            return $previewAssetPath;
        }

        $site = $project->site;
        if (! $site) {
            return null;
        }

        $siteMediaPath = "site-media/{$site->id}/demo/{$relativePath}";
        if (Storage::disk('public')->exists($siteMediaPath)) {
            return Storage::disk('public')->path($siteMediaPath);
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    private function extractCmsRouteParams(Request $request): array
    {
        $params = [];

        foreach ($request->query() as $key => $value) {
            $key = trim((string) $key);
            if ($key === '' || in_array($key, ['slug', 'locale'], true)) {
                continue;
            }

            if (is_scalar($value) || $value === null || is_array($value)) {
                $params[$key] = $value;
            }
        }

        return $params;
    }
}
