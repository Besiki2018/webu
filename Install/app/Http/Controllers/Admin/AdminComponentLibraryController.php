<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CmsComponentLibraryCatalogService;
use App\Models\Template;
use App\Services\CmsThemeTokenLayerResolver;
use App\Services\TemplateDemoService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/**
 * Admin: CMS Section Library — visual catalog of all builder components (SectionLibrary).
 * Only admins see this. Used to browse how components look and link to edit (CMS Sections).
 */
class AdminComponentLibraryController extends Controller
{
    public function __construct(
        protected CmsComponentLibraryCatalogService $catalogService
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $sections = collect($this->catalogService->buildCatalog())
            ->map(function (array $item): array {
                return [
                    'id' => $item['id'],
                    'key' => $item['key'],
                    'category' => $item['category'],
                    'category_label' => $item['category_label'],
                    'label' => $item['label'],
                    'description' => $item['description'],
                    'location_hint' => $item['location_hint'],
                    'enabled' => $item['enabled'],
                    'preview_url' => route('admin.component-library.preview', ['key' => $item['key']]),
                ];
            })
            ->values();

        $grouped = $sections->groupBy('category')->map(function ($items, $category) {
            $first = $items->first();
            return [
                'category' => $category,
                'category_label' => is_array($first) ? ($first['category_label'] ?? $category) : $category,
                'items' => $items->values()->all(),
            ];
        })->values()->all();

        return Inertia::render('Admin/ComponentLibrary', [
            'user' => $request->user()->only('id', 'name', 'email', 'avatar', 'role'),
            'groupedSections' => $grouped,
            'cmsSectionsUrl' => route('admin.cms-sections'),
        ]);
    }

    /**
     * Full-page preview of a single section (builder component) for visual design check.
     * When the component has multiple layout/style variants, each is shown in a separate block with a label.
     */
    public function preview(
        Request $request,
        string $key,
        TemplateDemoService $templateDemoService,
        CmsThemeTokenLayerResolver $themeResolver
    ): SymfonyResponse {
        abort_unless($request->user()?->isAdmin(), 403);

        $normalizedKey = Str::lower(trim($key));
        if ($normalizedKey === 'header') {
            return redirect()->route('admin.component-library.preview', ['key' => 'webu_header_01'], 301);
        }
        $variantConfig = config('component-variants', []);
        unset($variantConfig['design_rules'], $variantConfig['allowed_section_keys']);

        $configKey = $this->resolveVariantConfigKey($normalizedKey, array_keys($variantConfig));
        $layoutVariants = [];
        $styleVariants = [];
        if ($configKey !== null && isset($variantConfig[$configKey]) && is_array($variantConfig[$configKey])) {
            $entry = $variantConfig[$configKey];
            $layoutVariants = is_array($entry['layout_variants'] ?? null) ? $entry['layout_variants'] : [];
            $styleVariants = is_array($entry['style_variants'] ?? null) ? $entry['style_variants'] : [];
        }

        $variantSections = [];
        $propKey = $this->variantPropKeyForSection($normalizedKey);

        if ($layoutVariants !== [] || $styleVariants !== []) {
            $combos = [];
            if ($layoutVariants !== [] && $styleVariants !== []) {
                foreach ($layoutVariants as $layout) {
                    foreach ($styleVariants as $style) {
                        $combos[] = ['layout' => $layout, 'style' => $style, 'label' => $layout . ' / ' . $style];
                    }
                }
            } elseif ($layoutVariants !== []) {
                foreach ($layoutVariants as $layout) {
                    $combos[] = ['layout' => $layout, 'style' => null, 'label' => $layout];
                }
            } else {
                foreach ($styleVariants as $style) {
                    $combos[] = ['layout' => null, 'style' => $style, 'label' => $style];
                }
            }

            foreach ($combos as $combo) {
                $props = [];
                if ($combo['layout'] !== null) {
                    $props[$propKey] = $combo['layout'];
                }
                if ($combo['style'] !== null) {
                    $props['style_variant'] = $combo['style'];
                }
                $section = $templateDemoService->buildSingleSectionDemoForPreviewWithProps($normalizedKey, $props);
                if ($section !== null) {
                    $variantSections[] = [
                        'variant_label' => $combo['label'],
                        'section' => $section,
                    ];
                }
            }
        }

        if ($variantSections === []) {
            $section = $templateDemoService->buildSingleSectionDemoForPreview($normalizedKey);
            if (! $section) {
                abort(404, 'Section not found or no template available for demo.');
            }
            $variantSections = [['variant_label' => null, 'section' => $section]];
        }

        $template = Template::query()->where('slug', 'ecommerce')->first()
            ?? Template::query()->first();
        if (! $template) {
            $template = $templateDemoService->getSyntheticTemplateForPreview();
        }
        $templateSlug = (string) $template->slug;
        $themeTokenLayers = $themeResolver->resolveForTemplate($template, null);
        $siteDesignCssUrl = null;

        return response()->view('template-demos.component-preview', [
            'section' => $variantSections[0]['section'],
            'variantSections' => $variantSections,
            'template' => [
                'slug' => $templateSlug,
                'name' => $template->name ?? 'Component',
                'thumbnail_url' => $template->preview_image_url ?? null,
            ],
            'themeTokenLayers' => $themeTokenLayers,
            'siteDesignCssUrl' => $siteDesignCssUrl,
            'componentFolderPath' => null,
            'back_url' => route('admin.component-library.index'),
            'back_label' => __('Back to Components'),
        ]);
    }

    private function resolveVariantConfigKey(string $sectionKey, array $configKeys): ?string
    {
        if (in_array($sectionKey, $configKeys, true)) {
            return $sectionKey;
        }
        $lower = strtolower($sectionKey);
        if (str_contains($lower, 'header') && ! str_contains($lower, 'footer')) {
            return in_array('webu_header_01', $configKeys, true) ? 'webu_header_01' : null;
        }
        if (str_contains($lower, 'footer')) {
            return in_array('webu_footer_01', $configKeys, true) ? 'webu_footer_01' : (in_array('footer', $configKeys, true) ? 'footer' : null);
        }
        if (str_contains($lower, 'hero')) {
            return in_array('hero', $configKeys, true) ? 'hero' : null;
        }

        return null;
    }

    private function variantPropKeyForSection(string $sectionKey): string
    {
        $lower = strtolower($sectionKey);
        if (str_contains($lower, 'heading') || str_contains($lower, 'hero')) {
            return 'hero_variant';
        }

        return 'layout_variant';
    }
}
