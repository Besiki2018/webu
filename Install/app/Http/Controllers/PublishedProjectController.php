<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\CmsRuntimePayloadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Symfony\Component\HttpFoundation\Response;

class PublishedProjectController extends Controller
{
    public function __construct(
        protected CmsRuntimePayloadService $cmsRuntimePayloads
    ) {}

    public function serve(Request $request, string $path = 'index.html'): Response
    {
        $project = $request->attributes->get('subdomain_project')
            ?? $request->attributes->get('custom_domain_project');

        if (! $project) {
            abort(404, 'Project not found');
        }

        $path = ltrim($path, '/') ?: 'index.html';
        $requestedPath = $path;

        if (str_contains($path, '..')) {
            abort(403, 'Invalid path');
        }

        if ($this->isCmsBridgeRequest($path)) {
            return $this->serveCmsBridge($request, $project);
        }

        // Strip /preview/{project_id}/ prefix from asset paths.
        // The in-app preview builds HTML with absolute paths like /preview/{id}/assets/...,
        // but when served via subdomain, these paths arrive here with the prefix intact.
        $previewPrefix = "preview/{$project->id}/";
        if (str_starts_with($path, $previewPrefix)) {
            $path = substr($path, strlen($previewPrefix)) ?: 'index.html';
        }

        $previewPath = "previews/{$project->id}/{$path}";

        if (! Storage::disk('local')->exists($previewPath)) {
            if (! str_contains($path, '.')) {
                $indexPath = "previews/{$project->id}/{$path}/index.html";
                if (Storage::disk('local')->exists($indexPath)) {
                    $previewPath = $indexPath;
                    $path = trim($path, '/').'/index.html';
                } else {
                    // SPA fallback: serve root index.html for client-side routing
                    $spaFallbackPath = "previews/{$project->id}/index.html";
                    if (Storage::disk('local')->exists($spaFallbackPath)) {
                        $previewPath = $spaFallbackPath;
                        $path = 'index.html';
                    } else {
                        abort(404);
                    }
                }
            } else {
                abort(404);
            }
        }

        $fullPath = Storage::disk('local')->path($previewPath);
        $mimeType = $this->getMimeType($path);

        // HTML and JS files need modifications for subdomain serving.
        // Use a published cache to avoid processing on every request.
        $needsProcessing = str_ends_with($path, '.html')
            || str_ends_with($path, '.htm')
            || $mimeType === 'application/javascript';

        if ($needsProcessing) {
            $cachedPath = $this->getCachedPath($project->id, $path, $fullPath);

            if ($mimeType === 'text/html' && $this->shouldInjectDynamicCmsSeo($requestedPath)) {
                $html = file_get_contents($cachedPath);
                if (is_string($html)) {
                    $html = $this->injectDynamicCmsSeo($request, $project, $requestedPath, $html);

                    return response($html, 200, [
                        'Content-Type' => $mimeType,
                        'Cache-Control' => 'public, max-age=3600',
                    ]);
                }
            }

            return response()->file($cachedPath, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=3600',
            ]);
        }

        return response()->file($fullPath, [
            'Content-Type' => $mimeType,
            'Cache-Control' => 'public, max-age=3600',
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
     * Get the cached (subdomain-ready) version of a file.
     * Creates/updates the cache if the source file is newer.
     */
    private function getCachedPath(string $projectId, string $relativePath, string $sourcePath): string
    {
        $publishedPath = "published/{$projectId}/{$relativePath}";
        $cachedFullPath = Storage::disk('local')->path($publishedPath);

        // Serve cached version if it exists and is newer than the source
        if (file_exists($cachedFullPath) && filemtime($cachedFullPath) >= filemtime($sourcePath)) {
            return $cachedFullPath;
        }

        // Process the file for subdomain serving
        $content = file_get_contents($sourcePath);

        if (str_ends_with($relativePath, '.html') || str_ends_with($relativePath, '.htm')) {
            // Rewrite <base> tag to "/" so relative asset paths resolve correctly
            $content = preg_replace(
                '/<base\s+href="[^"]*"\s*\/?>/',
                '<base href="/">',
                $content
            );
        }

        if (str_ends_with($relativePath, '.js')) {
            // Fix React Router basename fallback: the builder template derives basename
            // from <base href>, falling back to "/preview" when empty. After rewriting
            // <base> to "/", the stripped value is "" (falsy), hitting this fallback.
            // Replace with "/" so the router matches the subdomain root.
            $content = str_replace('||"/preview"', '||"/"', $content);
        }

        // Ensure the cache directory exists and write
        $cacheDir = dirname($cachedFullPath);
        if (! is_dir($cacheDir)) {
            mkdir($cacheDir, 0775, true);
        }
        file_put_contents($cachedFullPath, $content);

        return $cachedFullPath;
    }

    private function getMimeType(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($extension) {
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'webp' => 'image/webp',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'ico' => 'image/x-icon',
            default => 'application/octet-stream',
        };
    }

    private function shouldInjectDynamicCmsSeo(string $requestedPath): bool
    {
        $trimmed = trim($requestedPath, '/');

        if ($trimmed === '') {
            return false;
        }

        return ! str_contains($trimmed, '.');
    }

    private function injectDynamicCmsSeo(Request $request, Project $project, string $requestedPath, string $html): string
    {
        try {
            [$slug, $routeParams] = $this->resolveCmsSeoRouteContext($requestedPath);
            $payload = $this->cmsRuntimePayloads->buildBootstrapPayload(
                project: $project,
                slug: $slug,
                locale: is_string($request->query('locale')) ? $request->query('locale') : null,
                resolvedDomain: $request->getHost(),
                routeParams: array_merge($routeParams, $this->extractCmsRouteParams($request))
            );

            return $this->applySeoTagsToHtml($html, $payload, $request, $project);
        } catch (Throwable) {
            return $html;
        }
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function resolveCmsSeoRouteContext(string $requestedPath): array
    {
        $normalized = trim(strtolower($requestedPath), '/');
        if ($normalized === '' || $normalized === 'index.html') {
            return ['home', []];
        }

        $segments = array_values(array_filter(explode('/', $normalized)));
        $rawSegments = array_values(array_filter(explode('/', trim($requestedPath, '/'))));
        $raw = static fn (int $index): string => urldecode((string) ($rawSegments[$index] ?? ''));

        $params = [];
        $first = $segments[0] ?? '';
        $second = $segments[1] ?? '';
        $third = $segments[2] ?? '';

        if (in_array($first, ['product', 'products'], true)) {
            if (isset($segments[1])) {
                $params['product_slug'] = $raw(1);
                $params['slug'] = $raw(1);
            }

            return ['product', $params];
        }

        if (in_array($first, ['category', 'categories'], true)) {
            if (isset($segments[1])) {
                $params['category_slug'] = $raw(1);
                $params['slug'] = $raw(1);
            }

            return ['shop', $params];
        }

        if ($first === 'shop') {
            if (in_array($second, ['category', 'categories'], true) && isset($segments[2])) {
                $params['category_slug'] = $raw(2);
                $params['slug'] = $raw(2);
            } elseif (($segments[1] ?? '') !== '' && ! in_array($second, ['search', 'filter'], true)) {
                $params['category_slug'] = $raw(1);
                $params['slug'] = $raw(1);
            }

            return ['shop', $params];
        }

        if ($first === 'account') {
            if (in_array($second, ['login', 'register'], true)) {
                $params['auth_mode'] = $second;

                return ['login', $params];
            }

            if ($second === 'orders' && isset($segments[2])) {
                $params['order_id'] = $raw(2);
                $params['id'] = $raw(2);

                return ['order', $params];
            }

            if ($second === 'orders') {
                return ['orders', $params];
            }

            return ['account', $params];
        }

        if (in_array($first, ['orders'], true)) {
            if (isset($segments[1])) {
                $params['order_id'] = $raw(1);
                $params['id'] = $raw(1);

                return ['order', $params];
            }

            return ['orders', $params];
        }

        if (in_array($first, ['login', 'register', 'auth'], true)) {
            $params['auth_mode'] = $first;

            return ['login', $params];
        }

        if ($first === 'cart') {
            return ['cart', $params];
        }

        if ($first === 'checkout') {
            return ['checkout', $params];
        }

        return [$this->cmsRuntimePayloads->normalizeSlug($normalized), $params];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applySeoTagsToHtml(string $html, array $payload, Request $request, Project $project): string
    {
        $pageSeoTitle = trim((string) data_get($payload, 'page.seo_title', ''));
        $pageTitle = trim((string) data_get($payload, 'page.title', ''));
        $fallbackTitle = trim((string) ($project->published_title ?: $project->name ?: 'Webby Project'));
        $title = $pageSeoTitle !== '' ? $pageSeoTitle : ($pageTitle !== '' ? $pageTitle : $fallbackTitle);

        $pageSeoDescription = trim((string) data_get($payload, 'page.seo_description', ''));
        $fallbackDescription = trim((string) ($project->published_description ?? ''));
        $description = $pageSeoDescription !== '' ? $pageSeoDescription : $fallbackDescription;

        $resolvedSlug = (string) ($payload['slug'] ?? '');
        $requestedSlug = (string) ($payload['requested_slug'] ?? '');
        $pageMissing = data_get($payload, 'page') === null;
        $fallbackResolved = $requestedSlug !== '' && $resolvedSlug !== '' && $requestedSlug !== $resolvedSlug;
        $shouldNoIndex = $pageMissing || $fallbackResolved;

        $currentUrl = htmlspecialchars($request->url(), ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeDescription = htmlspecialchars($description, ENT_QUOTES, 'UTF-8');

        $html = preg_replace('/<title>.*?<\/title>/is', "<title>{$safeTitle}</title>", $html) ?? $html;

        $patterns = [
            '/<meta[^>]+name=["\']description["\'][^>]*>/i',
            '/<meta[^>]+property=["\']og:title["\'][^>]*>/i',
            '/<meta[^>]+property=["\']og:description["\'][^>]*>/i',
            '/<meta[^>]+property=["\']og:url["\'][^>]*>/i',
            '/<meta[^>]+name=["\']twitter:title["\'][^>]*>/i',
            '/<meta[^>]+name=["\']twitter:description["\'][^>]*>/i',
            '/<meta[^>]+name=["\']robots["\'][^>]*>/i',
            '/<link[^>]+rel=["\']canonical["\'][^>]*>/i',
        ];
        foreach ($patterns as $pattern) {
            $html = preg_replace($pattern, '', $html) ?? $html;
        }

        $tags = [
            sprintf('<meta property="og:title" content="%s">', $safeTitle),
            sprintf('<meta name="twitter:title" content="%s">', $safeTitle),
            sprintf('<meta property="og:url" content="%s">', $currentUrl),
            sprintf('<link rel="canonical" href="%s">', $currentUrl),
        ];

        if ($description !== '') {
            $tags[] = sprintf('<meta name="description" content="%s">', $safeDescription);
            $tags[] = sprintf('<meta property="og:description" content="%s">', $safeDescription);
            $tags[] = sprintf('<meta name="twitter:description" content="%s">', $safeDescription);
        }

        if ($shouldNoIndex) {
            $tags[] = '<meta name="robots" content="noindex, nofollow">';
        }

        $tagBlock = "\n    ".implode("\n    ", $tags)."\n";

        if (stripos($html, '</head>') !== false) {
            $html = preg_replace('/<\/head>/i', $tagBlock.'</head>', $html, 1) ?? $html;
        }

        return $html;
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
