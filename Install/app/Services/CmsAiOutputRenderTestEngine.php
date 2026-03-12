<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Illuminate\Contracts\Http\Kernel as HttpKernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CmsAiOutputRenderTestEngine
{
    public function __construct(
        protected HttpKernel $httpKernel
    ) {}

    /**
     * Run authenticated preview-route smoke checks (`preview.serve` + `__cms/bootstrap`)
     * for AI-generated output that was already persisted via the current page revision/content model.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function runPreviewSmoke(Project $project, User $actor, array $options = []): array
    {
        $errors = [];
        $warnings = [];
        $checks = [
            'preview_index' => [
                'exists' => false,
                'path' => Storage::disk('local')->path("previews/{$project->id}/index.html"),
            ],
            'preview_html' => [
                'ok' => false,
                'status' => null,
                'headers' => [],
                'markers' => [],
            ],
            'bootstrap' => [],
        ];

        $requiredHtmlMarkers = $this->normalizeStringList(
            $options['required_html_markers'] ?? [
                'data-webu-menu="header"',
                'data-webu-section="webu_header_01"',
                'data-webu-section="webu_footer_01"',
                'id="preview-inspector"',
            ],
            [
                'data-webu-menu="header"',
                'data-webu-section="webu_header_01"',
                'data-webu-section="webu_footer_01"',
                'id="preview-inspector"',
            ]
        );
        $slugs = $this->normalizeStringList($options['slugs'] ?? ['home'], ['home']);
        $routeParamsBySlug = is_array($options['route_params_by_slug'] ?? null) ? $options['route_params_by_slug'] : [];
        $requireNonEmptySections = (bool) ($options['require_nonempty_sections'] ?? true);

        $previewIndexRelative = "previews/{$project->id}/index.html";
        if (Storage::disk('local')->exists($previewIndexRelative)) {
            $checks['preview_index']['exists'] = true;
        } else {
            $errors[] = [
                'type' => 'preview_render',
                'code' => 'missing_preview_index',
                'path' => $previewIndexRelative,
            ];
        }

        try {
            $htmlUrl = route('preview.serve', ['project' => $project->id]);
            $response = $this->dispatchGet($htmlUrl, [], $actor);
            $status = (int) $response->getStatusCode();
            $contentType = strtolower((string) $response->headers->get('Content-Type'));
            $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
            $html = (string) $response->getContent();

            $checks['preview_html'] = [
                'ok' => $status === 200,
                'status' => $status,
                'headers' => [
                    'content_type' => $contentType,
                    'cache_control' => $cacheControl,
                ],
                'markers' => [],
            ];

            if ($status !== 200) {
                $errors[] = [
                    'type' => 'preview_render',
                    'code' => 'preview_html_http_status',
                    'status' => $status,
                ];
            }

            if (! str_contains($contentType, 'text/html')) {
                $errors[] = [
                    'type' => 'preview_render',
                    'code' => 'preview_html_invalid_content_type',
                    'actual' => $contentType,
                ];
            }

            foreach (['no-cache', 'no-store', 'must-revalidate'] as $directive) {
                if (! str_contains($cacheControl, $directive)) {
                    $errors[] = [
                        'type' => 'preview_render',
                        'code' => 'preview_html_missing_cache_directive',
                        'directive' => $directive,
                    ];
                }
            }

            foreach ($requiredHtmlMarkers as $marker) {
                $found = str_contains($html, $marker);
                $checks['preview_html']['markers'][$marker] = $found;
                if (! $found) {
                    $errors[] = [
                        'type' => 'preview_render',
                        'code' => 'preview_html_missing_marker',
                        'marker' => $marker,
                    ];
                }
            }
        } catch (Throwable $e) {
            $errors[] = [
                'type' => 'preview_render',
                'code' => 'preview_html_request_exception',
                'message' => $e->getMessage(),
            ];
        }

        foreach ($slugs as $slug) {
            $routeParams = is_array($routeParamsBySlug[$slug] ?? null) ? $routeParamsBySlug[$slug] : [];
            $bootstrapCheck = [
                'slug' => $slug,
                'ok' => false,
                'status' => null,
                'headers' => [],
                'meta_source' => null,
                'resolved_slug' => null,
                'route_params' => [],
                'sections_count' => null,
            ];

            try {
                $query = array_merge(['slug' => $slug], $routeParams);
                $bootstrapUrl = route('preview.serve', [
                    'project' => $project->id,
                    'path' => '__cms/bootstrap',
                ]).'?'.http_build_query($query);

                $response = $this->dispatchGet($bootstrapUrl, ['HTTP_ACCEPT' => 'application/json'], $actor);
                $status = (int) $response->getStatusCode();
                $contentType = strtolower((string) $response->headers->get('Content-Type'));
                $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
                $decoded = json_decode((string) $response->getContent(), true);
                $payload = is_array($decoded) ? $decoded : null;

                $bootstrapCheck['status'] = $status;
                $bootstrapCheck['headers'] = [
                    'content_type' => $contentType,
                    'cache_control' => $cacheControl,
                ];

                if ($status !== 200) {
                    $errors[] = [
                        'type' => 'preview_render',
                        'code' => 'preview_bootstrap_http_status',
                        'slug' => $slug,
                        'status' => $status,
                    ];
                    $checks['bootstrap'][] = $bootstrapCheck;

                    continue;
                }

                if (! str_contains($contentType, 'application/json')) {
                    $errors[] = [
                        'type' => 'preview_render',
                        'code' => 'preview_bootstrap_invalid_content_type',
                        'slug' => $slug,
                        'actual' => $contentType,
                    ];
                }

                foreach (['no-cache', 'no-store', 'must-revalidate'] as $directive) {
                    if (! str_contains($cacheControl, $directive)) {
                        $errors[] = [
                            'type' => 'preview_render',
                            'code' => 'preview_bootstrap_missing_cache_directive',
                            'slug' => $slug,
                            'directive' => $directive,
                        ];
                    }
                }

                if ($payload === null) {
                    $errors[] = [
                        'type' => 'preview_render',
                        'code' => 'preview_bootstrap_invalid_json',
                        'slug' => $slug,
                    ];
                    $checks['bootstrap'][] = $bootstrapCheck;

                    continue;
                }

                $sections = data_get($payload, 'revision.content_json.sections');
                $bootstrapCheck['ok'] = true;
                $bootstrapCheck['meta_source'] = data_get($payload, 'meta.source');
                $bootstrapCheck['resolved_slug'] = data_get($payload, 'slug');
                $bootstrapCheck['route_params'] = is_array(data_get($payload, 'route.params')) ? data_get($payload, 'route.params') : [];
                $bootstrapCheck['sections_count'] = is_array($sections) ? count($sections) : null;

                if ((string) ($payload['project_id'] ?? '') !== (string) $project->id) {
                    $errors[] = [
                        'type' => 'preview_render',
                        'code' => 'preview_bootstrap_project_id_mismatch',
                        'slug' => $slug,
                    ];
                }

                if (! is_string($payload['site_id'] ?? null) || trim((string) $payload['site_id']) === '') {
                    $errors[] = [
                        'type' => 'preview_render',
                        'code' => 'preview_bootstrap_missing_site_id',
                        'slug' => $slug,
                    ];
                }

                if (data_get($payload, 'meta.source') !== 'cms-runtime-bridge') {
                    $errors[] = [
                        'type' => 'preview_render',
                        'code' => 'preview_bootstrap_invalid_meta_source',
                        'slug' => $slug,
                        'actual' => data_get($payload, 'meta.source'),
                    ];
                }

                if (data_get($payload, 'menus.header.key') !== 'header') {
                    $errors[] = [
                        'type' => 'preview_render',
                        'code' => 'preview_bootstrap_missing_header_menu',
                        'slug' => $slug,
                    ];
                }

                if (! is_array($sections)) {
                    $errors[] = [
                        'type' => 'preview_render',
                        'code' => 'preview_bootstrap_sections_invalid',
                        'slug' => $slug,
                    ];
                } elseif ($requireNonEmptySections && $sections === []) {
                    $errors[] = [
                        'type' => 'preview_render',
                        'code' => 'preview_bootstrap_sections_empty',
                        'slug' => $slug,
                    ];
                }
            } catch (Throwable $e) {
                $errors[] = [
                    'type' => 'preview_render',
                    'code' => 'preview_bootstrap_request_exception',
                    'slug' => $slug,
                    'message' => $e->getMessage(),
                ];
            }

            $checks['bootstrap'][] = $bootstrapCheck;
        }

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'checks' => $checks,
            'summary' => [
                'project_id' => $project->id,
                'slugs' => $slugs,
                'preview_index_exists' => (bool) $checks['preview_index']['exists'],
                'bootstrap_checks' => count($checks['bootstrap']),
            ],
        ];
    }

    /**
     * Run preview smoke checks against current saved project/site state.
     *
     * This engine assumes AI output has already passed validation and save gates.
     *
     * @param  array<string, mixed>  $options
     * @return array<string, mixed>
     */
    public function runPreviewSmokeForProject(Project $project, array $options = []): array
    {
        $site = $project->site()->first();
        if (! $site) {
            return [
                'ok' => false,
                'errors' => [[
                    'type' => 'render_smoke',
                    'code' => 'site_missing',
                    'path' => '$.project.site',
                    'message' => 'Project does not have a site for render smoke testing.',
                ]],
                'warnings' => [],
                'summary' => [
                    'project_id' => (string) $project->id,
                    'site_id' => null,
                    'gate_passed' => false,
                    'checked_pages' => 0,
                ],
                'validation' => [
                    'preview_assets' => [
                        'valid' => false,
                        'error_count' => 0,
                        'warning_count' => 0,
                        'errors' => [],
                        'warnings' => [],
                    ],
                    'bootstrap_smoke' => [
                        'valid' => false,
                        'error_count' => 0,
                        'warning_count' => 0,
                        'checked_pages' => 0,
                        'errors' => [],
                        'warnings' => [],
                        'pages' => [],
                    ],
                ],
            ];
        }

        $aiOutput = is_array($options['ai_output'] ?? null) ? $options['ai_output'] : [];
        $resolvedDomain = is_string($options['resolved_domain'] ?? null) && trim((string) $options['resolved_domain']) !== ''
            ? trim((string) $options['resolved_domain'])
            : 'ai-render-smoke.local';
        $requirePreviewAssets = (bool) ($options['require_preview_assets'] ?? false);
        $checkHtmlPreview = (bool) ($options['check_html_preview'] ?? true);
        $maxPages = max(1, (int) ($options['max_pages'] ?? 10));

        $previewAssetReport = $this->checkPreviewAssets($project, $resolvedDomain, $checkHtmlPreview, $requirePreviewAssets);
        $bootstrapReport = $this->checkBootstrapSmoke($project, $site->id, $resolvedDomain, $aiOutput, $maxPages);

        $errors = array_merge($previewAssetReport['errors'], $bootstrapReport['errors']);
        $warnings = array_merge($previewAssetReport['warnings'], $bootstrapReport['warnings']);

        return [
            'ok' => $errors === [],
            'errors' => $errors,
            'warnings' => $warnings,
            'summary' => [
                'project_id' => (string) $project->id,
                'site_id' => (string) $site->id,
                'gate_passed' => $errors === [],
                'checked_pages' => (int) $bootstrapReport['checked_pages'],
                'checked_preview_assets' => (int) $previewAssetReport['checked_assets'],
            ],
            'validation' => [
                'preview_assets' => [
                    'valid' => $previewAssetReport['errors'] === [],
                    'error_count' => count($previewAssetReport['errors']),
                    'warning_count' => count($previewAssetReport['warnings']),
                    'checked_assets' => (int) $previewAssetReport['checked_assets'],
                    'errors' => $previewAssetReport['errors'],
                    'warnings' => $previewAssetReport['warnings'],
                ],
                'bootstrap_smoke' => [
                    'valid' => $bootstrapReport['errors'] === [],
                    'error_count' => count($bootstrapReport['errors']),
                    'warning_count' => count($bootstrapReport['warnings']),
                    'checked_pages' => (int) $bootstrapReport['checked_pages'],
                    'errors' => $bootstrapReport['errors'],
                    'warnings' => $bootstrapReport['warnings'],
                    'pages' => $bootstrapReport['pages'],
                ],
            ],
        ];
    }

    /**
     * @return array{
     *   checked_assets:int,
     *   errors: array<int, array<string, mixed>>,
     *   warnings: array<int, array<string, mixed>>
     * }
     */
    private function checkPreviewAssets(Project $project, string $resolvedDomain, bool $checkHtmlPreview, bool $requirePreviewAssets): array
    {
        $errors = [];
        $warnings = [];
        $checkedAssets = 0;

        if (! $checkHtmlPreview) {
            return [
                'checked_assets' => 0,
                'errors' => [],
                'warnings' => [],
            ];
        }

        $previewIndexRelative = "previews/{$project->id}/index.html";
        $previewExists = Storage::disk('local')->exists($previewIndexRelative);

        if (! $previewExists) {
            $finding = [
                'type' => 'render_smoke',
                'code' => 'preview_asset_missing',
                'path' => '$.preview.index_html',
                'relative_path' => $previewIndexRelative,
            ];

            if ($requirePreviewAssets) {
                $errors[] = $finding;
            } else {
                $warnings[] = $finding;
            }

            return [
                'checked_assets' => 1,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        $checkedAssets++;

        $htmlUrl = route('app.serve', ['project' => $project->id]);
        $response = $this->dispatchGet($htmlUrl, [
            'HTTP_HOST' => $resolvedDomain,
        ]);

        $status = method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : 500;
        if ($status !== 200) {
            $errors[] = [
                'type' => 'render_smoke',
                'code' => 'preview_html_response_error',
                'path' => '$.preview.http',
                'status' => $status,
                'expected_status' => 200,
            ];

            return [
                'checked_assets' => $checkedAssets,
                'errors' => $errors,
                'warnings' => $warnings,
            ];
        }

        $contentType = strtolower((string) ($response->headers->get('Content-Type') ?? ''));
        if (! str_contains($contentType, 'text/html')) {
            $errors[] = [
                'type' => 'render_smoke',
                'code' => 'preview_html_invalid_content_type',
                'path' => '$.preview.http.headers.content_type',
                'actual' => $contentType,
                'expected_contains' => 'text/html',
            ];
        }

        $html = (string) ($response->getContent() ?? '');
        if ($html === '' || (! str_contains(strtolower($html), '<html') && ! str_contains(strtolower($html), '<!doctype html'))) {
            $errors[] = [
                'type' => 'render_smoke',
                'code' => 'preview_html_empty_or_invalid',
                'path' => '$.preview.http.body',
            ];
        }

        return [
            'checked_assets' => $checkedAssets,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $aiOutput
     * @return array{
     *   checked_pages:int,
     *   pages: array<int, array<string, mixed>>,
     *   errors: array<int, array<string, mixed>>,
     *   warnings: array<int, array<string, mixed>>
     * }
     */
    private function checkBootstrapSmoke(Project $project, int|string $siteId, string $resolvedDomain, array $aiOutput, int $maxPages): array
    {
        $errors = [];
        $warnings = [];
        $pageReports = [];
        $checkedPages = 0;

        $pageHints = $this->resolvePageHints($project, $aiOutput, $maxPages);

        foreach ($pageHints as $pageHint) {
            $pageSlug = (string) ($pageHint['slug'] ?? 'home');
            $routePattern = is_string($pageHint['route_pattern'] ?? null) ? (string) $pageHint['route_pattern'] : null;
            $routeParams = $this->sampleRouteParamsForPage($pageSlug, $routePattern);
            $query = array_merge(['slug' => $pageSlug], $routeParams);

            $bootstrapUrl = route('app.serve', [
                'project' => $project->id,
                'path' => '__cms/bootstrap',
            ]).'?'.http_build_query($query);

            $response = $this->dispatchGet($bootstrapUrl, [
                'HTTP_HOST' => $resolvedDomain,
                'HTTP_ACCEPT' => 'application/json',
            ]);

            $checkedPages++;
            $status = method_exists($response, 'getStatusCode') ? (int) $response->getStatusCode() : 500;
            $contentType = strtolower((string) ($response->headers->get('Content-Type') ?? ''));
            $decoded = json_decode((string) ($response->getContent() ?? ''), true);

            $pageReport = [
                'slug' => $pageSlug,
                'route_pattern' => $routePattern,
                'status' => $status,
                'content_type' => $contentType,
                'route_params' => $routeParams,
                'ok' => false,
            ];

            if ($status !== 200) {
                $errors[] = [
                    'type' => 'render_smoke',
                    'code' => 'bootstrap_response_error',
                    'path' => '$.bootstrap.'.$pageSlug,
                    'slug' => $pageSlug,
                    'status' => $status,
                    'expected_status' => 200,
                ];
                $pageReports[] = $pageReport;
                continue;
            }

            if (! str_contains($contentType, 'application/json')) {
                $errors[] = [
                    'type' => 'render_smoke',
                    'code' => 'bootstrap_invalid_content_type',
                    'path' => '$.bootstrap.'.$pageSlug.'.headers.content_type',
                    'slug' => $pageSlug,
                    'actual' => $contentType,
                    'expected_contains' => 'application/json',
                ];
            }

            if (! is_array($decoded)) {
                $errors[] = [
                    'type' => 'render_smoke',
                    'code' => 'bootstrap_invalid_json',
                    'path' => '$.bootstrap.'.$pageSlug.'.body',
                    'slug' => $pageSlug,
                ];
                $pageReports[] = $pageReport;
                continue;
            }

            if ((string) ($decoded['project_id'] ?? '') !== (string) $project->id) {
                $errors[] = [
                    'type' => 'render_smoke',
                    'code' => 'bootstrap_project_mismatch',
                    'path' => '$.bootstrap.'.$pageSlug.'.project_id',
                    'slug' => $pageSlug,
                    'expected' => (string) $project->id,
                    'actual' => $decoded['project_id'] ?? null,
                ];
            }

            if ((string) ($decoded['site_id'] ?? '') !== (string) $siteId) {
                $errors[] = [
                    'type' => 'render_smoke',
                    'code' => 'bootstrap_site_mismatch',
                    'path' => '$.bootstrap.'.$pageSlug.'.site_id',
                    'slug' => $pageSlug,
                    'expected' => (string) $siteId,
                    'actual' => $decoded['site_id'] ?? null,
                ];
            }

            if ((string) data_get($decoded, 'meta.source') !== 'cms-runtime-bridge') {
                $errors[] = [
                    'type' => 'render_smoke',
                    'code' => 'bootstrap_source_mismatch',
                    'path' => '$.bootstrap.'.$pageSlug.'.meta.source',
                    'slug' => $pageSlug,
                    'expected' => 'cms-runtime-bridge',
                    'actual' => data_get($decoded, 'meta.source'),
                ];
            }

            $sections = data_get($decoded, 'revision.content_json.sections');
            if (! is_array($sections) || $sections === []) {
                $errors[] = [
                    'type' => 'render_smoke',
                    'code' => 'bootstrap_missing_sections',
                    'path' => '$.bootstrap.'.$pageSlug.'.revision.content_json.sections',
                    'slug' => $pageSlug,
                ];
            }

            $resolvedSlug = is_string($decoded['slug'] ?? null) ? (string) $decoded['slug'] : null;
            if ($resolvedSlug !== null && $resolvedSlug !== $pageSlug) {
                $warnings[] = [
                    'type' => 'render_smoke',
                    'code' => 'bootstrap_slug_resolved_via_fallback',
                    'path' => '$.bootstrap.'.$pageSlug.'.slug',
                    'requested_slug' => $pageSlug,
                    'resolved_slug' => $resolvedSlug,
                ];
            }

            if ($routePattern !== null) {
                if (str_contains($routePattern, '{slug}')) {
                    $expectedSlug = (string) ($routeParams['product_slug'] ?? $routeParams['category_slug'] ?? '');
                    if ($expectedSlug !== '' && (string) data_get($decoded, 'route.params.slug') !== $expectedSlug) {
                        $errors[] = [
                            'type' => 'render_smoke',
                            'code' => 'bootstrap_missing_route_slug_param',
                            'path' => '$.bootstrap.'.$pageSlug.'.route.params.slug',
                            'slug' => $pageSlug,
                            'expected' => $expectedSlug,
                            'actual' => data_get($decoded, 'route.params.slug'),
                        ];
                    }
                }

                if (str_contains($routePattern, '{id}')) {
                    $expectedId = (string) ($routeParams['order_id'] ?? $routeParams['id'] ?? '');
                    if ($expectedId !== '' && (string) data_get($decoded, 'route.params.id') !== $expectedId) {
                        $errors[] = [
                            'type' => 'render_smoke',
                            'code' => 'bootstrap_missing_route_id_param',
                            'path' => '$.bootstrap.'.$pageSlug.'.route.params.id',
                            'slug' => $pageSlug,
                            'expected' => $expectedId,
                            'actual' => data_get($decoded, 'route.params.id'),
                        ];
                    }
                }
            }

            if (! is_string(data_get($decoded, 'meta.endpoints.ecommerce_products')) || (string) data_get($decoded, 'meta.endpoints.ecommerce_products') === '') {
                $warnings[] = [
                    'type' => 'render_smoke',
                    'code' => 'bootstrap_endpoint_missing',
                    'path' => '$.bootstrap.'.$pageSlug.'.meta.endpoints.ecommerce_products',
                    'slug' => $pageSlug,
                ];
            }

            $pageReport['ok'] = true;
            $pageReport['resolved_slug'] = $resolvedSlug;
            $pageReport['section_count'] = is_array($sections) ? count($sections) : 0;
            $pageReports[] = $pageReport;
        }

        return [
            'checked_pages' => $checkedPages,
            'pages' => $pageReports,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * @param  array<string, mixed>  $aiOutput
     * @return array<int, array{slug:string,route_pattern:?string}>
     */
    private function resolvePageHints(Project $project, array $aiOutput, int $maxPages): array
    {
        $hints = [];
        $pages = is_array($aiOutput['pages'] ?? null) && array_is_list($aiOutput['pages']) ? $aiOutput['pages'] : [];

        foreach ($pages as $page) {
            if (! is_array($page)) {
                continue;
            }

            $slug = $this->normalizeSlug(is_string($page['slug'] ?? null) ? (string) $page['slug'] : '');
            if ($slug === '') {
                continue;
            }

            $hints[$slug] = [
                'slug' => $slug,
                'route_pattern' => is_string($page['route_pattern'] ?? null) ? (string) $page['route_pattern'] : null,
            ];

            if (count($hints) >= $maxPages) {
                break;
            }
        }

        if ($hints !== []) {
            return array_values($hints);
        }

        $site = $project->site()->first();
        if (! $site) {
            return [['slug' => 'home', 'route_pattern' => null]];
        }

        $pages = $site->pages()->orderBy('id')->get(['slug']);
        foreach ($pages as $page) {
            $slug = $this->normalizeSlug((string) ($page->slug ?? ''));
            if ($slug === '') {
                continue;
            }
            $hints[$slug] = ['slug' => $slug, 'route_pattern' => null];
            if (count($hints) >= $maxPages) {
                break;
            }
        }

        if ($hints === []) {
            $hints['home'] = ['slug' => 'home', 'route_pattern' => null];
        }

        return array_values($hints);
    }

    /**
     * @return array<string, string>
     */
    private function sampleRouteParamsForPage(string $pageSlug, ?string $routePattern): array
    {
        $routePattern = is_string($routePattern) ? strtolower(trim($routePattern)) : '';
        $pageSlug = strtolower(trim($pageSlug));

        if ($routePattern !== '' && str_contains($routePattern, '{slug}')) {
            if (str_contains($pageSlug, 'category')) {
                return ['category_slug' => 'ai-smoke-category'];
            }

            return ['product_slug' => 'ai-smoke-product'];
        }

        if ($routePattern !== '' && str_contains($routePattern, '{id}')) {
            if (str_contains($pageSlug, 'order')) {
                return ['order_id' => '1001'];
            }

            return ['id' => '1001'];
        }

        return [];
    }

    private function normalizeSlug(string $slug): string
    {
        $slug = trim(strtolower($slug));
        $slug = trim($slug, '/');

        return $slug !== '' ? $slug : '';
    }

    /**
     * @param  array<string, string>  $server
     */
    private function dispatchGet(string $url, array $server = [], ?User $actor = null): \Symfony\Component\HttpFoundation\Response
    {
        $previousUser = Auth::user();
        $request = Request::create($url, 'GET', [], [], [], $server);

        try {
            if ($actor instanceof User) {
                Auth::login($actor);
            }

            $response = $this->httpKernel->handle($request);
            $this->httpKernel->terminate($request, $response);

            return $response;
        } finally {
            if ($actor instanceof User) {
                Auth::logout();
                if ($previousUser instanceof User) {
                    Auth::login($previousUser);
                }
            }
        }
    }

    /**
     * @param  mixed  $value
     * @param  array<int, string>  $fallback
     * @return array<int, string>
     */
    private function normalizeStringList(mixed $value, array $fallback): array
    {
        if (! is_array($value)) {
            return $fallback;
        }

        $items = [];
        foreach ($value as $item) {
            if (! is_scalar($item)) {
                continue;
            }

            $normalized = trim((string) $item);
            if ($normalized !== '') {
                $items[] = $normalized;
            }
        }

        $items = array_values(array_unique($items));

        return $items !== [] ? $items : $fallback;
    }
}
