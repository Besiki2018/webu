<?php

namespace App\Http\Controllers;

use App\Models\Site;
use App\Models\Template;
use App\Services\BuilderService;
use App\Services\CmsThemeTokenLayerResolver;
use App\Services\TemplateDemoService;
use App\Services\WebuDesignSnapshotService;
use App\Support\OwnedTemplateCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;

class TemplateLiveDemoController extends Controller
{
    /**
     * Serve static template demo files with SPA-style fallback.
     */
    public function show(
        Request $request,
        string $templateSlug,
        TemplateDemoService $templateDemoService,
        BuilderService $builderService,
        ?string $path = null
    ): Response|BinaryFileResponse
    {
        $normalizedSlug = Str::of($templateSlug)->lower()->replaceMatches('/[^a-z0-9\\-]/', '')->value();
        abort_if($normalizedSlug === '', 404);

        $requestedPath = trim((string) ($path ?? ''), '/');
        $template = Template::query()->where('slug', $normalizedSlug)->first();
        $preferGeneratedDemo = OwnedTemplateCatalog::contains($normalizedSlug);
        $requestedPageFromQuery = trim((string) $request->query('slug', ''));
        $requestedPage = $requestedPageFromQuery !== ''
            ? $requestedPageFromQuery
            : ($requestedPath !== '' ? Str::before($requestedPath, '/') : null);
        if ($requestedPage !== null && trim($requestedPage) !== '') {
            $normalizedRequestedPage = trim(Str::lower($requestedPage));
            if (str_ends_with($normalizedRequestedPage, '.html')) {
                $normalizedRequestedPage = Str::beforeLast($normalizedRequestedPage, '.html');
            }

            $requestedPage = in_array($normalizedRequestedPage, ['', 'index'], true)
                ? 'home'
                : $normalizedRequestedPage;
        }
        $requestedLocale = trim((string) $request->query('locale', ''));
        // Demo is project-scoped only when site= is present (opened from that project's CMS).
        // Without site= we show the template's default demo (read-only; no project's builder edits apply).
        $requestedSiteId = trim((string) $request->query('site', ''));
        $requestedSite = $requestedSiteId !== ''
            ? Site::query()->find($requestedSiteId)
            : null;
        $draft = $request->boolean('draft');

        $candidateRoots = [];
        // When site= is present we are in builder/project preview: always use generated demo
        // so draft content and section design are shown. Do not serve static index.html (e.g. stale-static-ecommerce).
        // Use requestedSiteId so we skip static even when the site ID is invalid (then we 404 later instead of serving stale).
        $preferGeneratedForSite = $requestedSiteId !== '';
        if (! $preferGeneratedDemo && ! $preferGeneratedForSite) {
            // Prefer real imported static exports (in this priority) before generated fallback demo.
            // NOTE: We prioritize `themes/{slug}` before `template-demos/{slug}` to avoid
            // stale generated demo exports overriding the actual imported purchased design.
            $candidateRoots = [
                public_path('themes/'.$normalizedSlug),
                public_path('template-demos/'.$normalizedSlug),
                base_path('templates/'.$normalizedSlug.'/runtime'),
            ];
        }

        if (! $preferGeneratedDemo && ! $preferGeneratedForSite) {
            $configuredLiveDemoPath = trim((string) data_get($template?->metadata ?? [], 'live_demo.path', ''));
            if ($configuredLiveDemoPath !== '') {
                $normalizedLiveDemoPath = ltrim($configuredLiveDemoPath, '/');
                $absoluteLiveDemoFile = public_path($normalizedLiveDemoPath);
                if (is_file($absoluteLiveDemoFile)) {
                    $candidateRoots[] = dirname($absoluteLiveDemoFile);
                } else {
                    $absoluteLiveDemoDir = public_path($normalizedLiveDemoPath);
                    if (is_dir($absoluteLiveDemoDir)) {
                        $candidateRoots[] = $absoluteLiveDemoDir;
                    }
                }
            }
        }

        foreach (array_values(array_unique($candidateRoots)) as $root) {
            $response = $this->serveStaticDemoFromRoot($root, $requestedPath);
            if ($response !== null) {
                return $response;
            }
        }

        // Do not render SPA/generated HTML for asset requests.
        if ($this->looksLikeStaticAssetPath($requestedPath)) {
            abort(404);
        }

        // When URL had site= but the site was not found, do not show template default (would be confusing).
        if ($requestedSiteId !== '' && $requestedSite === null) {
            abort(404);
        }

        // Fallback: render backend-generated demo even when template rows are missing.
        // CMS Builder always passes site=..., so we can synthesize a lightweight template identity.
        if (! $template && $requestedSite) {
            $template = new Template();
            $template->forceFill([
                'id' => null,
                'slug' => $normalizedSlug,
                'name' => Str::headline(str_replace('-', ' ', $normalizedSlug)),
                'description' => null,
                'category' => in_array($normalizedSlug, ['ecommerce', 'ekka-demo-8'], true) ? 'ecommerce' : 'business',
                'version' => '1.0.0',
                'thumbnail' => null,
                'metadata' => [],
            ]);
        }
        abort_unless($template, 404);

        // When live_design=1 or layout variant overrides (builder preview), do not cache so design changes react immediately
        $liveDesign = $request->query('live_design') === '1';
        $hasLayoutVariantOverride = $request->query('header_variant') !== null || $request->query('footer_variant') !== null;
        $cacheTtlSeconds = (int) config('builder.preview_payload_cache_ttl', 15);
        $cacheKey = null;
        if ($requestedSite !== null && $cacheTtlSeconds > 0 && ! $liveDesign && ! $hasLayoutVariantOverride) {
            $cacheKey = sprintf(
                'builder_preview_payload:%s:%s:%s:%s:%s',
                $requestedSite->id,
                $template->id ?? $normalizedSlug,
                $requestedPage ?? 'home',
                $requestedLocale !== '' ? $requestedLocale : '_',
                $draft ? 'draft' : 'published'
            );
        }

        $demo = null;
        $themeTokenLayers = null;
        if ($cacheKey !== null) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached) && isset($cached['demo'], $cached['themeTokenLayers'])) {
                $demo = $cached['demo'];
                $themeTokenLayers = $cached['themeTokenLayers'];
            }
        }

        if ($demo === null) {
            $headerVariantOverride = $request->query('header_variant');
            $footerVariantOverride = $request->query('footer_variant');
            $demo = $templateDemoService->buildPayload(
                $template,
                $requestedPage,
                $requestedSite,
                $requestedLocale !== '' ? $requestedLocale : null,
                $draft,
                is_string($headerVariantOverride) && $headerVariantOverride !== '' ? trim($headerVariantOverride) : null,
                is_string($footerVariantOverride) && $footerVariantOverride !== '' ? trim($footerVariantOverride) : null
            );

            if ($requestedSite !== null) {
                $themeTokenLayers = app(CmsThemeTokenLayerResolver::class)->resolveForSite($requestedSite, $requestedSite->project ?? null);
            }

            if ($cacheKey !== null) {
                Cache::put($cacheKey, [
                    'demo' => $demo,
                    'themeTokenLayers' => $themeTokenLayers,
                ], $cacheTtlSeconds);
            }
        }

        $runtimeAppConfig = null;
        $runtimeEcommerceScript = null;

        if ($requestedSite && in_array($normalizedSlug, ['ecommerce', 'ekka-demo-8'], true)) {
            $requestedSite->loadMissing('project');
            $project = $requestedSite->project;
            if ($project) {
                $apiBaseUrl = rtrim((string) config('app.url', ''), '/');
                if ($apiBaseUrl === '') {
                    $apiBaseUrl = rtrim($request->getSchemeAndHttpHost(), '/');
                }

                $runtimeAppConfig = [
                    'ecommerce' => $builderService->runtimeEcommerceConfig($project, $requestedSite, $apiBaseUrl),
                ];
                $runtimeEcommerceScript = $builderService->ecommerceRuntimeScript();
            }
        }

        if ($themeTokenLayers === null && $requestedSite !== null) {
            $themeTokenLayers = app(CmsThemeTokenLayerResolver::class)->resolveForSite($requestedSite, $requestedSite->project ?? null);
        }

        $viewName = $normalizedSlug === 'ekka-demo-8'
            ? 'template-demos.generated-ekka'
            : 'template-demos.generated';

        // When builder requests live_design=1, always use app.css so component design changes in code
        // are visible in the builder; existing projects (published/view without this param) keep baked CSS.
        $siteDesignCssUrl = null;
        if ($request->query('live_design') === '1') {
            $siteDesignCssUrl = null;
        } elseif ($requestedSite !== null) {
            $siteDesignCssUrl = app(WebuDesignSnapshotService::class)->getBakedCssUrl($requestedSite);
        }

        return response()
            ->view($viewName, [
                'template' => $demo['template'],
                'pages' => $demo['pages'],
                'activePage' => $demo['active_page'],
                'meta' => $demo['meta'],
                'layoutHeader' => $demo['layout_header'] ?? null,
                'layoutFooter' => $demo['layout_footer'] ?? null,
                'runtimeAppConfig' => $runtimeAppConfig,
                'runtimeEcommerceScript' => $runtimeEcommerceScript,
                'headerMenuItems' => $demo['header_menu_items'] ?? [],
                'footerMenus' => $demo['footer_menus'] ?? [],
                'footerLayout' => $demo['footer_layout'] ?? [],
                'themeTokenLayers' => $themeTokenLayers,
                'siteDesignCssUrl' => $siteDesignCssUrl,
            ])
            ->header('Cache-Control', 'no-store, max-age=0');
    }

    /**
     * Serve theme/demo-8.html with asset paths rewritten (fallback when no CMS payload).
     */
    private function serveEkkaDemo8(): ?Response
    {
        $path = base_path('../theme/demo-8.html');
        if (! is_file($path)) {
            return null;
        }
        $html = (string) file_get_contents($path);
        $base = rtrim((string) asset('theme-assets'), '/');
        $html = str_replace('"assets/', '"'.$base.'/', $html);
        $html = str_replace("'assets/", "'".$base.'/', $html);
        $html = str_replace('content="assets/', 'content="'.$base.'/', $html);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }

    private function serveStaticDemoFromRoot(string $baseDir, string $requestedPath): Response|BinaryFileResponse|null
    {
        if (! is_dir($baseDir)) {
            return null;
        }

        $candidatePaths = [];
        if ($requestedPath === '') {
            $candidatePaths[] = $baseDir.'/index.html';
        } else {
            $candidatePaths[] = $baseDir.'/'.$requestedPath;
            $candidatePaths[] = $baseDir.'/'.$requestedPath.'.html';
            $candidatePaths[] = $baseDir.'/'.$requestedPath.'/index.html';
        }

        $baseDirRealpath = realpath($baseDir);
        if (! $baseDirRealpath) {
            return null;
        }

        foreach ($candidatePaths as $candidate) {
            $real = realpath($candidate);
            if (! $real || ! is_file($real)) {
                continue;
            }

            if (! Str::startsWith($real, $baseDirRealpath.DIRECTORY_SEPARATOR)) {
                continue;
            }

            return response()->file($real, [
                'Content-Type' => $this->detectMimeType($real),
            ]);
        }

        // SPA fallback: unmatched page routes still resolve to root index.
        if ($this->looksLikeStaticAssetPath($requestedPath)) {
            return null;
        }

        $fallback = realpath($baseDir.'/index.html');
        if ($fallback && is_file($fallback) && Str::startsWith($fallback, $baseDirRealpath.DIRECTORY_SEPARATOR)) {
            return response()->file($fallback, [
                'Content-Type' => 'text/html; charset=UTF-8',
            ]);
        }

        return null;
    }

    private function looksLikeStaticAssetPath(string $requestedPath): bool
    {
        if ($requestedPath === '') {
            return false;
        }

        $extension = strtolower((string) pathinfo($requestedPath, PATHINFO_EXTENSION));

        return $extension !== '' && $extension !== 'html';
    }

    private function detectMimeType(string $filePath): string
    {
        $extension = strtolower((string) pathinfo($filePath, PATHINFO_EXTENSION));

        return match ($extension) {
            'html', 'htm' => 'text/html; charset=UTF-8',
            'css' => 'text/css; charset=UTF-8',
            'js', 'mjs' => 'application/javascript; charset=UTF-8',
            'json' => 'application/json; charset=UTF-8',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            default => 'application/octet-stream',
        };
    }
}
