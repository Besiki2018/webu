<?php

namespace App\Services;

use App\Booking\Contracts\BookingAuthorizationServiceContract;
use App\Cms\Contracts\CmsRepositoryContract;
use App\Models\Project;
use App\Models\Site;
use App\Services\ProjectWorkspace\ProjectWorkspaceService;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SiteProvisioningService
{
    public function __construct(
        protected CmsRepositoryContract $repository,
        protected CmsTypographyService $typography,
        protected BookingAuthorizationServiceContract $bookingAuthorization,
        protected CmsSectionBindingService $sectionBindings,
        protected SiteDemoContentSeederService $demoContentSeeder,
        protected EcommerceDemoSeederService $ecommerceDemoSeeder,
        protected WebuDesignSnapshotService $designSnapshot,
        protected ProjectWorkspaceService $projectWorkspace
    ) {}

    /**
     * Provision baseline CMS records for a project.
     */
    public function provisionForProject(Project $project): Site
    {
        $site = $this->repository->findSiteByProject($project);
        if (! $site) {
            $site = $this->repository->createSiteForProject($project, [
                'name' => $project->name,
                'primary_domain' => $project->custom_domain,
                'subdomain' => $project->subdomain,
                'status' => $this->resolveSiteStatus($project),
                'locale' => 'ka',
                'theme_settings' => $this->defaultThemeSettings($project),
            ]);
        }

        $this->syncSiteMetaFromProject($site, $project);
        $this->ensureGlobalSettings($site);
        $this->ensureMenus($site, $project);
        $this->ensureDefaultPages($site, $project);
        $this->bookingAuthorization->ensureSiteRoles($site);
        $this->demoContentSeeder->seedForProject($site, $project);
        $this->ensureBakedDesignSnapshot($site);
        $this->ensureProjectCodebase($project);

        return $site;
    }

    /**
     * Initialize real project codebase (workspace + template + CMS-to-code) for AI-editable code.
     */
    private function ensureProjectCodebase(Project $project): void
    {
        try {
            $this->projectWorkspace->initializeProjectCodebase($project);
        } catch (\Throwable $e) {
            Log::warning('Project workspace codebase init failed for project {project_id}: {message}', [
                'project_id' => $project->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bake design snapshot once per site so already-created projects keep a frozen design;
     * builder preview with live_design=1 still loads app.css for current component development.
     */
    private function ensureBakedDesignSnapshot(Site $site): void
    {
        if ($this->designSnapshot->hasBakedDesign($site)) {
            return;
        }
        try {
            $bakedCssUrl = $this->designSnapshot->bakeSiteDesignCss($site);
            if ($bakedCssUrl !== null) {
                $current = is_array($site->theme_settings) ? $site->theme_settings : [];
                $current['design_snapshot'] = [
                    'baked_css_url' => $bakedCssUrl,
                    'detached' => true,
                    'created_at' => now()->toIso8601String(),
                ];
                $site->forceFill(['theme_settings' => $current])->saveQuietly();
            }
        } catch (\Throwable $e) {
            Log::warning('Webu design snapshot bake failed for site {site_id}: {message}', [
                'site_id' => $site->id,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function ensureGlobalSettings(Site $site): void
    {
        $this->repository->firstOrCreateGlobalSetting($site, [
            'contact_json' => [
                'email' => null,
                'phone' => null,
                'address' => null,
            ],
            'social_links_json' => [],
            'analytics_ids_json' => [],
        ]);
    }

    private function ensureMenus(Site $site, Project $project): void
    {
        $pageBlueprints = $this->resolvePageBlueprints($site, $project);
        $headerItems = collect($pageBlueprints)
            ->map(static fn (array $page): array => [
                'label' => (string) ($page['title'] ?? ''),
                'slug' => (string) ($page['slug'] ?? ''),
                'url' => ((string) ($page['slug'] ?? '')) === 'home'
                    ? '/'
                    : '/'.(string) ($page['slug'] ?? ''),
            ])
            ->filter(static fn (array $item): bool => $item['label'] !== '' && $item['slug'] !== '')
            ->take(8)
            ->values()
            ->all();

        if ($headerItems === []) {
            $headerItems = [
                ['label' => 'მთავარი', 'slug' => 'home', 'url' => '/'],
                ['label' => 'ჩვენ შესახებ', 'slug' => 'about', 'url' => '/about'],
                ['label' => 'კონტაქტი', 'slug' => 'contact', 'url' => '/contact'],
            ];
        }

        $footerItems = [
            ['label' => 'Privacy', 'url' => '/privacy'],
            ['label' => 'Terms', 'url' => '/terms'],
        ];

        $headerMenu = $this->repository->firstOrCreateMenu($site, 'header', ['items_json' => $headerItems]);
        $footerMenu = $this->repository->firstOrCreateMenu($site, 'footer', ['items_json' => $footerItems]);

        if (! is_array($headerMenu->items_json) || $headerMenu->items_json === []) {
            $this->repository->updateOrCreateMenu($site, 'header', ['items_json' => $headerItems]);
        }

        if (! is_array($footerMenu->items_json) || $footerMenu->items_json === []) {
            $this->repository->updateOrCreateMenu($site, 'footer', ['items_json' => $footerItems]);
        }
    }

    /**
     * Provision site and pages from a ready template JSON (resources/templates/*.json).
     * Use when creating a project "from template" without a DB Template record.
     *
     * @param  array{provision_demo_store?: bool}  $options  If template is e-commerce: true = seed demo store (EcommerceDemoSeederService), false = empty store
     */
    public function provisionFromReadyTemplate(Project $project, array $templateData, array $options = []): Site
    {
        $site = $this->repository->findSiteByProject($project);
        if (! $site) {
            $site = $this->repository->createSiteForProject($project, [
                'name' => $project->name,
                'primary_domain' => $project->custom_domain,
                'subdomain' => $project->subdomain,
                'status' => $this->resolveSiteStatus($project),
                'locale' => 'ka',
                'theme_settings' => $this->defaultThemeSettings($project),
            ]);
        }

        $preset = trim((string) Arr::get($templateData, 'theme_preset', ''));
        if ($preset !== '' && in_array($preset, array_keys(config('theme-presets', [])), true)) {
            $project->forceFill(['theme_preset' => $preset])->save();
            $site->forceFill([
                'theme_settings' => array_merge(
                    is_array($site->theme_settings) ? $site->theme_settings : [],
                    ['preset' => $preset]
                ),
            ])->save();
        }

        $this->syncSiteMetaFromProject($site, $project);
        $this->ensureGlobalSettings($site);
        $defaultPages = Arr::get($templateData, 'default_pages', []);
        $blueprints = $this->buildPageBlueprintsFromReadyTemplate($site, $defaultPages, $project->isPublished());
        $this->ensureMenusFromBlueprints($site, $project, $blueprints);
        $this->ensureDefaultPages($site, $project, $blueprints);
        $this->bookingAuthorization->ensureSiteRoles($site);

        $isEcommerceTemplate = $this->isEcommerceTemplate($templateData);
        $provisionDemoStore = $options['provision_demo_store'] ?? null;

        if ($isEcommerceTemplate && $provisionDemoStore !== null) {
            $this->demoContentSeeder->seedForProject($site, $project, ['skip_ecommerce' => true]);
            if ($provisionDemoStore) {
                $this->ecommerceDemoSeeder->run($site->fresh(), false);
            }
        } else {
            $this->demoContentSeeder->seedForProject($site, $project);
        }

        $this->ensureBakedDesignSnapshot($site);

        return $site->fresh();
    }

    private function isEcommerceTemplate(array $templateData): bool
    {
        $name = strtolower(trim((string) Arr::get($templateData, 'name', '')));
        if (str_contains($name, 'storefront') || str_contains($name, 'ecommerce') || str_contains($name, 'e-commerce')) {
            return true;
        }
        $pages = Arr::get($templateData, 'default_pages', []);
        foreach ($pages as $p) {
            if (! is_array($p)) {
                continue;
            }
            $slug = strtolower(trim((string) Arr::get($p, 'slug', '')));
            if ($slug === 'shop' || $slug === 'product' || $slug === 'cart' || $slug === 'checkout') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, array{slug: string, title: string, sections: mixed}>  $defaultPages
     * @return array<int, array<string, mixed>>
     */
    public function buildPageBlueprintsFromReadyTemplate(Site $site, array $defaultPages, bool $publishByDefault = false): array
    {
        $blueprints = [];
        foreach ($defaultPages as $templatePage) {
            if (! is_array($templatePage)) {
                continue;
            }
            $slug = Str::slug((string) Arr::get($templatePage, 'slug', ''));
            if ($slug === '') {
                continue;
            }
            $title = trim((string) Arr::get($templatePage, 'title', Str::headline($slug)));
            if ($title === '') {
                $title = Str::headline($slug);
            }
            $sectionSource = Arr::get($templatePage, 'sections', []);
            $sections = $this->buildTemplateSections($sectionSource, $site, $title);
            if ($sections === []) {
                $sections = Arr::get($this->defaultPageContentBySlug($slug, $site, $title), 'sections', []);
            }
            $blueprints[] = [
                'title' => $title,
                'slug' => $slug,
                'status' => $publishByDefault ? 'published' : 'draft',
                'seo_title' => $slug === 'home' ? $site->name : "{$site->name} | {$title}",
                'seo_description' => "{$site->name} - {$title}",
                'content' => ['sections' => $sections],
            ];
        }

        return $blueprints;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blueprints
     */
    private function ensureMenusFromBlueprints(Site $site, Project $project, array $blueprints): void
    {
        $headerItems = collect($blueprints)
            ->map(static fn (array $page): array => [
                'label' => (string) ($page['title'] ?? ''),
                'slug' => (string) ($page['slug'] ?? ''),
                'url' => ((string) ($page['slug'] ?? '')) === 'home' ? '/' : '/'.(string) ($page['slug'] ?? ''),
            ])
            ->filter(static fn (array $item): bool => $item['label'] !== '' && $item['slug'] !== '')
            ->take(8)
            ->values()
            ->all();
        if ($headerItems === []) {
            $headerItems = [
                ['label' => 'მთავარი', 'slug' => 'home', 'url' => '/'],
                ['label' => 'კონტაქტი', 'slug' => 'contact', 'url' => '/contact'],
            ];
        }
        $this->repository->updateOrCreateMenu($site, 'header', ['items_json' => $headerItems]);

        $footerItems = [
            ['label' => 'Privacy', 'url' => '/privacy'],
            ['label' => 'Terms', 'url' => '/terms'],
        ];
        $this->repository->updateOrCreateMenu($site, 'footer', ['items_json' => $footerItems]);
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $overrideBlueprints
     */
    private function ensureDefaultPages(Site $site, Project $project, ?array $overrideBlueprints = null): void
    {
        $defaultPages = $overrideBlueprints ?? $this->resolvePageBlueprints($site, $project);

        foreach ($defaultPages as $pageConfig) {
            $page = $this->repository->firstOrCreatePage(
                $site,
                $pageConfig['slug'],
                [
                    'title' => $pageConfig['title'],
                    'status' => (string) ($pageConfig['status'] ?? 'draft'),
                    'seo_title' => $pageConfig['seo_title'],
                    'seo_description' => $pageConfig['seo_description'],
                ]
            );

            $existingRevisions = $this->repository->countPageRevisions($site, $page);
            if ($existingRevisions > 0) {
                continue;
            }

            $this->repository->createRevision($site, $page, [
                'version' => 1,
                'content_json' => $this->bindContentSections($pageConfig['content']),
                'created_by' => $project->user_id,
                'published_at' => ((string) ($pageConfig['status'] ?? 'draft')) === 'published' ? now() : null,
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function resolvePageBlueprints(Site $site, Project $project): array
    {
        $publishByDefault = $project->isPublished();
        $project->loadMissing('template');
        $metadata = is_array($project->template?->metadata) ? $project->template->metadata : [];
        $templatePages = Arr::get($metadata, 'default_pages', []);
        $templateSections = Arr::get($metadata, 'default_sections', []);

        if (is_array($templatePages) && $templatePages !== []) {
            $blueprints = [];
            foreach ($templatePages as $templatePage) {
                if (! is_array($templatePage)) {
                    continue;
                }

                $slug = Str::slug((string) Arr::get($templatePage, 'slug', ''));
                if ($slug === '') {
                    continue;
                }

                $title = trim((string) Arr::get($templatePage, 'title', Str::headline($slug)));
                if ($title === '') {
                    $title = Str::headline($slug);
                }

                $sectionSource = Arr::get($templateSections, $slug, Arr::get($templatePage, 'sections', []));
                $sections = $this->buildTemplateSections($sectionSource, $site, $title);
                if ($sections === []) {
                    $sections = Arr::get($this->defaultPageContentBySlug($slug, $site, $title), 'sections', []);
                }

                $blueprints[] = [
                    'title' => $title,
                    'slug' => $slug,
                    'status' => $publishByDefault ? 'published' : 'draft',
                    'seo_title' => $slug === 'home' ? $site->name : "{$site->name} | {$title}",
                    'seo_description' => "{$site->name} - {$title}",
                    'content' => [
                        'sections' => $sections,
                    ],
                ];
            }

            if ($blueprints !== []) {
                return $blueprints;
            }
        }

        return [
            [
                'title' => 'მთავარი',
                'slug' => 'home',
                'status' => $publishByDefault ? 'published' : 'draft',
                'seo_title' => $site->name,
                'seo_description' => "{$site->name} - ოფიციალური ვებგვერდი",
                'content' => $this->defaultHomeContent($site),
            ],
            [
                'title' => 'ჩვენ შესახებ',
                'slug' => 'about',
                'status' => $publishByDefault ? 'published' : 'draft',
                'seo_title' => "{$site->name} | ჩვენ შესახებ",
                'seo_description' => "{$site->name}-ის შესახებ ინფორმაცია",
                'content' => $this->defaultAboutContent($site),
            ],
            [
                'title' => 'კონტაქტი',
                'slug' => 'contact',
                'status' => $publishByDefault ? 'published' : 'draft',
                'seo_title' => "{$site->name} | კონტაქტი",
                'seo_description' => "{$site->name}-თან დაკავშირების ინფორმაცია",
                'content' => $this->defaultContactContent($site),
            ],
        ];
    }

    /**
     * @param  mixed  $sectionSource
     * @return array<int, array<string, mixed>>
     */
    private function buildTemplateSections(mixed $sectionSource, Site $site, string $pageTitle): array
    {
        $blueprints = $this->normalizeSectionBlueprints($sectionSource);

        return array_values(array_map(function (array $blueprint) use ($site, $pageTitle): array {
            $key = $blueprint['key'];
            $props = is_array($blueprint['props'] ?? null) ? $blueprint['props'] : [];

            $resolvedProps = array_replace_recursive(
                $this->defaultSectionProps($key, $site, $pageTitle),
                $props
            );

            return $this->sectionBindings->buildSectionPayload($key, $resolvedProps);
        }, $blueprints));
    }

    /**
     * @param  mixed  $source
     * @return array<int, array{key: string, props: array<string, mixed>}>
     */
    private function normalizeSectionBlueprints(mixed $source): array
    {
        if (! is_array($source)) {
            return [];
        }

        $blueprints = [];
        $seenKeys = [];
        foreach ($source as $item) {
            $rawKey = null;
            $props = [];

            if (is_string($item)) {
                $rawKey = $item;
            } elseif (is_array($item)) {
                $rawKey = Arr::get($item, 'key') ?? Arr::get($item, 'type');

                $rawProps = Arr::get($item, 'props');
                if (is_array($rawProps)) {
                    $props = $rawProps;
                }
            }

            $key = trim(Str::lower((string) $rawKey));
            if ($key === '') {
                continue;
            }

            if (isset($seenKeys[$key])) {
                continue;
            }

            $seenKeys[$key] = true;
            $blueprints[] = [
                'key' => $key,
                'props' => $props,
            ];
        }

        return $blueprints;
    }

    /**
     * Default section props from the project's CMS context (site name, page title).
     * So default content that appears in components comes from the project's CMS, not hardcoded.
     * When the project is generated, this content is written into revisions; the user then edits it in the builder.
     *
     * @return array<string, mixed>
     */
    private function defaultSectionProps(string $sectionKey, Site $site, string $pageTitle): array
    {
        $name = $site->name !== '' ? $site->name : 'Site';
        $homeHeadline = $pageTitle === 'Home' ? $name : $pageTitle;
        $subtitle = "{$name} — {$pageTitle}";

        return match ($sectionKey) {
            'hero' => [
                'headline' => $homeHeadline,
                'subheading' => $subtitle,
                'hero_cta_label' => 'Contact',
                'hero_cta_url' => '/contact',
                'hero_cta_secondary_label' => '',
                'hero_cta_secondary_url' => '',
                'image_url' => null,
                'image_alt' => 'Hero',
            ],
            'webu_general_heading_01' => [
                'headline' => $homeHeadline,
                'subheading' => $subtitle,
                'hero_cta_label' => 'Shop now',
                'hero_cta_url' => '/shop',
                'hero_cta_secondary_label' => '',
                'hero_cta_secondary_url' => '',
                'hero_image_url' => null,
                'hero_image_alt' => 'Hero',
                'layout_variant' => 'hero-1',
            ],
            'header' => [
                'logo_text' => $name,
                'logo_url' => '/',
                'menu_items' => [
                    ['label' => 'Home', 'url' => '/'],
                    ['label' => 'Shop', 'url' => '/shop'],
                    ['label' => 'Contact', 'url' => '/contact'],
                ],
                'cta_label' => '',
                'cta_url' => '',
                'layout_variant' => 'header-1',
            ],
            'banner' => [
                'title' => $homeHeadline,
                'subtitle' => $subtitle,
                'cta_label' => 'Learn more',
                'cta_url' => '/contact',
            ],
            'services' => [
                'title' => $pageTitle,
                'items' => [],
            ],
            'faq' => [
                'title' => 'FAQ',
                'items' => [],
            ],
            'contact' => [
                'title' => 'Contact',
                'email' => null,
                'phone' => null,
                'address' => null,
            ],
            'text' => [
                'title' => $pageTitle,
                'body' => "{$name} {$pageTitle}",
            ],
            'footer' => [
                'links' => [],
                'contact' => null,
                'copyright' => '© ' . $name,
            ],
            'webu_ecom_product_grid_01' => [
                'title' => $name . ' — Featured products',
                'add_to_cart_label' => 'Add to cart',
            ],
            'webu_ecom_category_list_01' => [
                'title' => 'Shop by category',
            ],
            'webu_ecom_product_card_01' => [
                'title' => $name,
            ],
            'webu_ecom_cart_page_01' => [
                'title' => 'Your cart',
            ],
            'webu_general_newsletter_01' => [
                'title' => 'Stay updated',
                'text' => 'Subscribe for news and offers from ' . $name,
                'subtitle' => 'Subscribe for news and offers from ' . $name,
                'placeholder' => 'Your email',
                'button_label' => 'Subscribe',
            ],
            'webu_general_placeholder_01' => [
                'title' => $pageTitle,
            ],
            default => [],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultPageContentBySlug(string $slug, Site $site, string $title): array
    {
        return match ($slug) {
            'home' => $this->defaultHomeContent($site),
            'about' => $this->defaultAboutContent($site),
            'contact' => $this->defaultContactContent($site),
            default => [
                'sections' => [
                    [
                        'type' => 'text',
                        'props' => [
                            'title' => $title,
                            'body' => "{$site->name} {$title}",
                        ],
                    ],
                ],
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function bindContentSections(array $content): array
    {
        $sections = is_array($content['sections'] ?? null) ? $content['sections'] : [];
        $boundSections = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $type = trim(Str::lower((string) ($section['type'] ?? '')));
            if ($type === '') {
                continue;
            }

            $props = is_array($section['props'] ?? null) ? $section['props'] : [];
            $payload = $this->sectionBindings->buildSectionPayload($type, $props);

            if (is_array($section['binding'] ?? null)) {
                $payload['binding'] = array_replace($payload['binding'], $section['binding']);
            }

            $boundSections[] = $payload;
        }

        $content['sections'] = array_values($boundSections);

        return $content;
    }

    private function resolveSiteStatus(Project $project): string
    {
        if ($project->trashed()) {
            return 'archived';
        }

        return $project->isPublished() ? 'published' : 'draft';
    }

    private function syncSiteMetaFromProject(Site $site, Project $project): void
    {
        $payload = [];

        if ($site->name !== $project->name) {
            $payload['name'] = $project->name;
        }

        if ($site->primary_domain !== $project->custom_domain) {
            $payload['primary_domain'] = $project->custom_domain;
        }

        if ($site->subdomain !== $project->subdomain) {
            $payload['subdomain'] = $project->subdomain;
        }

        $nextStatus = $this->resolveSiteStatus($project);
        if ($site->status !== $nextStatus) {
            $payload['status'] = $nextStatus;
        }

        $currentTheme = $site->theme_settings ?? [];
        $nextTheme = [
            ...$currentTheme,
            'preset' => $project->theme_preset ?? 'default',
        ];
        $normalizedNextTheme = $this->typography->normalizeThemeSettings($nextTheme);
        if ($currentTheme !== $normalizedNextTheme) {
            $payload['theme_settings'] = $normalizedNextTheme;
        }

        if ($payload !== []) {
            $this->repository->updateSite($site, $payload);
        }
    }

    private function defaultHomeContent(Site $site): array
    {
        return [
            'sections' => [
                [
                    'type' => 'hero',
                    'props' => [
                        'headline' => $site->name,
                        'subtitle' => 'თქვენი ბიზნესის პროფესიონალური ონლაინ პრეზენტაცია.',
                        'primary_cta' => [
                            'label' => 'დაგვიკავშირდით',
                            'url' => '/contact',
                        ],
                    ],
                ],
                [
                    'type' => 'services',
                    'props' => [
                        'title' => 'სერვისები',
                        'items' => [],
                    ],
                ],
            ],
        ];
    }

    private function defaultAboutContent(Site $site): array
    {
        return [
            'sections' => [
                [
                    'type' => 'text',
                    'props' => [
                        'title' => 'ჩვენ შესახებ',
                        'body' => "{$site->name} გთავაზობთ სანდო და ხარისხიან მომსახურებას.",
                    ],
                ],
            ],
        ];
    }

    private function defaultContactContent(Site $site): array
    {
        return [
            'sections' => [
                [
                    'type' => 'contact',
                    'props' => [
                        'title' => 'კონტაქტი',
                        'email' => null,
                        'phone' => null,
                        'address' => null,
                    ],
                ],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultThemeSettings(Project $project): array
    {
        return $this->typography->normalizeThemeSettings([
            'preset' => $project->theme_preset ?? 'default',
        ]);
    }
}
