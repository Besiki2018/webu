<?php

namespace Tests\Feature\Templates;

use App\Models\Plan;
use App\Models\SectionLibrary;
use App\Models\SystemSetting;
use App\Models\Template;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TemplateImportServiceTest extends TestCase
{
    use RefreshDatabase;

    private string $sourceRoot;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');

        $this->sourceRoot = storage_path('framework/testing/webu-shop-design');
        File::deleteDirectory($this->sourceRoot);
        File::ensureDirectoryExists($this->sourceRoot.'/assets/css');
        File::ensureDirectoryExists($this->sourceRoot.'/assets/js');

        File::put($this->sourceRoot.'/assets/css/style.css', 'body{font-family:sans-serif}.hero{padding:16px}');
        File::put($this->sourceRoot.'/assets/js/main.js', 'console.log("shop-design");');

        File::put($this->sourceRoot.'/index.html', $this->pageHtml('Home', '<header class="site-header">Header</header><div class="main_content"><div class="section pb_20"><div class="hero banner">Hero</div></div><div class="section pb_70"><div class="promo">Promo</div></div><div class="section newsletter-wrap"><div class="newsletter subscribe">Newsletter</div></div></div><footer class="site-footer">Footer</footer>'));
        File::put($this->sourceRoot.'/shop-list.html', $this->pageHtml('Shop', '<section class="category-list">Categories</section><section class="product-grid shop-grid">Products<div class="product-card item">Card</div></section><section class="cta-banner">CTA</section>'));
        File::put($this->sourceRoot.'/shop-product-detail.html', $this->pageHtml('Product', '<section class="product-detail">Product</section>'));
        File::put($this->sourceRoot.'/shop-cart.html', $this->pageHtml('Cart', '<section class="cart">Cart</section>'));
        File::put($this->sourceRoot.'/checkout.html', $this->pageHtml('Checkout', '<section class="checkout">Checkout</section>'));
        File::put($this->sourceRoot.'/contact.html', $this->pageHtml('Contact', '<section class="contact">Contact</section>'));

        File::deleteDirectory(public_path('themes/webu-shop-01'));
        File::deleteDirectory(base_path('templates/webu-shop-01'));
        File::delete(storage_path('app/templates/webu-shop-01-template.zip'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sourceRoot);
        File::deleteDirectory(public_path('themes/webu-shop-01'));
        File::deleteDirectory(base_path('templates/webu-shop-01'));
        File::delete(storage_path('app/templates/webu-shop-01-template.zip'));

        parent::tearDown();
    }

    public function test_import_command_creates_branded_template_and_sections(): void
    {
        $plan = Plan::factory()->create();

        $exitCode = Artisan::call('templates:import', [
            '--path' => $this->sourceRoot,
            '--theme' => 'webu-shop-01',
            '--name' => 'Webu Shop 01',
            '--plan' => [$plan->id],
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $template = Template::query()->where('slug', 'webu-shop-01')->first();
        $this->assertNotNull($template);
        $this->assertSame('Webu Shop 01', $template->name);
        $this->assertSame('templates/webu-shop-01-template.zip', $template->getRawOriginal('zip_path'));

        $this->assertFileExists(public_path('themes/webu-shop-01/index.html'));
        $this->assertFileExists(public_path('themes/webu-shop-01/assets/js/webu-theme-runtime.js'));
        $this->assertFileExists(base_path('templates/webu-shop-01/template.json'));
        $this->assertFileExists(base_path('templates/webu-shop-01/mapping.json'));
        $this->assertFileExists(storage_path('app/templates/webu-shop-01-template.zip'));

        $this->assertDatabaseHas('sections_library', ['key' => 'webu_header_01']);
        $this->assertDatabaseHas('sections_library', ['key' => 'webu_hero_01']);
        $this->assertDatabaseHas('sections_library', ['key' => 'webu_product_grid_01']);
        $this->assertDatabaseHas('sections_library', ['key' => 'webu_footer_01']);

        $homeSectionCount = SectionLibrary::query()
            ->where('key', 'like', 'webu_home_section_%')
            ->count();

        $this->assertGreaterThan(0, $homeSectionCount);

        $defaultHomeSections = collect(data_get($template->metadata, 'default_sections.home', []))
            ->map(static fn (mixed $item): string => (string) data_get($item, 'key', ''))
            ->filter(static fn (string $key): bool => $key !== '')
            ->values()
            ->all();

        $this->assertTrue(
            collect($defaultHomeSections)->contains(static fn (string $key): bool => str_starts_with($key, 'webu_home_section_'))
        );

        $planIds = $template->plans()->pluck('plans.id')->map(fn ($id): int => (int) $id)->all();
        $this->assertSame([$plan->id], $planIds);
    }

    public function test_import_command_sanitizes_vendor_branding_from_metadata_and_html(): void
    {
        $exitCode = Artisan::call('templates:import', [
            '--path' => $this->sourceRoot,
            '--theme' => 'webu-shop-01',
            '--name' => 'Webu Shop 01',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $template = Template::query()->where('slug', 'webu-shop-01')->firstOrFail();
        $metadataJson = json_encode($template->metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        $this->assertIsString($metadataJson);
        $this->assertStringNotContainsStringIgnoringCase('shop-design', $metadataJson);
        $this->assertStringNotContainsStringIgnoringCase('themeforest', $metadataJson);
        $this->assertStringNotContainsStringIgnoringCase('pixio', $metadataJson);

        $indexHtml = File::get(public_path('themes/webu-shop-01/index.html'));
        $this->assertStringNotContainsStringIgnoringCase('shop-design', $indexHtml);
        $this->assertStringNotContainsStringIgnoringCase('themeforest', $indexHtml);
        $this->assertStringNotContainsStringIgnoringCase('pixio', $indexHtml);
        $this->assertStringContainsString('assets/js/webu-theme-runtime.js', $indexHtml);
    }

    public function test_import_command_applies_theme_isolation_marker_on_body(): void
    {
        $exitCode = Artisan::call('templates:import', [
            '--path' => $this->sourceRoot,
            '--theme' => 'webu-shop-01',
            '--name' => 'Webu Shop 01',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $html = File::get(public_path('themes/webu-shop-01/index.html'));
        $this->assertStringContainsString('data-webu-theme="webu-shop-01"', $html);

        $shopHtml = File::get(public_path('themes/webu-shop-01/shop-list.html'));
        $this->assertStringContainsString('data-webby-ecommerce-products', $shopHtml);
    }

    public function test_import_command_builds_storefront_runtime_contract_for_cms_and_checkout(): void
    {
        $exitCode = Artisan::call('templates:import', [
            '--path' => $this->sourceRoot,
            '--theme' => 'webu-shop-01',
            '--name' => 'Webu Shop 01',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $runtime = File::get(public_path('themes/webu-shop-01/assets/js/webu-theme-runtime.js'));

        $this->assertStringContainsString('window.WebuCms = {', $runtime);
        $this->assertStringContainsString('window.WebuStorefront = window.WebbyEcommerce;', $runtime);
        $this->assertStringContainsString('window.WebuStorefrontBindings = {', $runtime);
        $this->assertStringContainsString('getShippingOptions', $runtime);
        $this->assertStringContainsString('shipping_provider', $runtime);
        $this->assertStringContainsString('shipping_rate_id', $runtime);
        $this->assertStringContainsString('notes: formSnapshot.notes || null', $runtime);
        $this->assertStringContainsString("setIfMissing('products_menu_url', '/shop');", $runtime);
        $this->assertStringContainsString("setIfMissing('login_url', '/account/login');", $runtime);
        $this->assertStringContainsString("setIfMissing('cart_view_button', { label: 'View Cart', url: '/cart' });", $runtime);
        $this->assertStringContainsString("setIfMissing('account_link_3', { label: 'Orders', url: '/account/orders' });", $runtime);
        $this->assertStringContainsString('function applyGeneralSectionStyleRuntime(container, props) {', $runtime);
        $this->assertStringContainsString('var hideOnTablet = parseBooleanProp(responsiveProps.hide_on_tablet, false);', $runtime);
        $this->assertStringContainsString('var effectiveStyleProps = Object.assign(', $runtime);
        $this->assertStringContainsString('activeResponsiveStyleOverrides,', $runtime);
        $this->assertStringContainsString('normalStateStyleOverrides,', $runtime);
        $this->assertStringContainsString('interactionStateStyleOverrides', $runtime);
        $this->assertStringContainsString("container.setAttribute('data-webu-runtime-style-order', 'base>responsive>state');", $runtime);
        $this->assertStringContainsString("container.setAttribute('data-webu-runtime-positioning-mode', positionMode);", $runtime);
        $this->assertStringContainsString("container.setAttribute('data-webu-runtime-custom-css-present', '1');", $runtime);
        $this->assertStringContainsString("container.setAttribute('data-webu-runtime-hidden-by-advanced-visibility', '1');", $runtime);
        $this->assertStringContainsString('var runtimeCustomCssScopeSequence = 0;', $runtime);
        $this->assertStringContainsString('function normalizeCustomCssScopingInput(raw) {', $runtime);
        $this->assertStringContainsString('function scopeCustomCssTextRecursively(rawCss, scopeSelector) {', $runtime);
        $this->assertStringContainsString('function upsertRuntimeScopedCustomCss(container, customCss, customCssSeed) {', $runtime);
        $this->assertStringContainsString("container.setAttribute('data-webu-runtime-custom-css-scope-id', scopeId);", $runtime);
        $this->assertStringContainsString("container.setAttribute('data-webu-runtime-custom-css-scope', scopeId);", $runtime);
        $this->assertStringContainsString("container.setAttribute('data-webu-runtime-custom-css-scoped', '1');", $runtime);
        $this->assertStringContainsString("container.setAttribute('data-webu-runtime-custom-css-scope-hash', scopeId + ':' + String(scopedCss.length));", $runtime);
        $this->assertStringContainsString('upsertRuntimeScopedCustomCss(', $runtime);
        $this->assertStringContainsString("function applyGeneralComponentStylePresetsRuntime(container, advancedProps) {", $runtime);
        $this->assertStringContainsString("var componentPresetSelections = applyGeneralComponentStylePresetsRuntime(container, advancedProps);", $runtime);
        $this->assertStringContainsString("'data-webu-runtime-component-presets'", $runtime);
        $this->assertStringContainsString("'button:' + componentPresetSelections.button + ';card:' + componentPresetSelections.card + ';input:' + componentPresetSelections.input", $runtime);
        $this->assertStringContainsString("root.style.setProperty('--webu-token-radius-' + cssKey, value);", $runtime);
        $this->assertStringContainsString("var(--webu-token-radius-button, var(--webu-token-radius-base, 8px))", $runtime);
        $this->assertStringContainsString("container.setAttribute('data-testid', dataTestIdAttr);", $runtime);
        $this->assertStringContainsString("container.setAttribute('aria-label', ariaLabelAttr);", $runtime);
        $this->assertStringContainsString('if (isGeneralSectionType(type) && container instanceof HTMLElement) {', $runtime);
        $this->assertStringContainsString('function readRuntimeFirstStringProp(props, keys) {', $runtime);
        $this->assertStringContainsString('function normalizeRuntimeComponentProps(props) {', $runtime);
        $this->assertStringContainsString('function readRuntimeCallToActionPayload(props) {', $runtime);
        $this->assertStringContainsString("var effectiveProps = normalizeRuntimeComponentProps(normalizeFixedSectionProps(type, props) || props);", $runtime);
        $this->assertStringContainsString("var headingValue = readRuntimeFirstStringProp(effectiveProps, ['title', 'headline', 'heading']);", $runtime);
        $this->assertStringContainsString("var subtitleValue = readRuntimeFirstStringProp(effectiveProps, ['subtitle', 'description', 'body', 'text']);", $runtime);
        $this->assertStringContainsString('var ctaPayload = readRuntimeCallToActionPayload(effectiveProps);', $runtime);
        $this->assertStringContainsString("var localeQuery = state.locale ? ('?locale=' + encodeURIComponent(state.locale)) : '';", $runtime);
        $this->assertStringContainsString("'/pages/' + encodeURIComponent(String(page.id)) + localeQuery", $runtime);
    }

    public function test_import_command_preserves_utf8_text_in_default_section_props(): void
    {
        File::put(
            $this->sourceRoot.'/index.html',
            $this->pageHtml(
                'მთავარი',
                '<header class="site-header">Header</header>'
                .'<div class="main_content">'
                .'<div class="section pb_20"><div class="hero banner"><h2>უნიკალური ქართული სათაური</h2><p>ტესტური აღწერა ქართულად</p></div></div>'
                .'<div class="section pb_70"><div class="promo">Promo</div></div>'
                .'<div class="section newsletter-wrap"><div class="newsletter subscribe">Newsletter</div></div>'
                .'</div>'
                .'<footer class="site-footer">Footer</footer>'
            )
        );

        $exitCode = Artisan::call('templates:import', [
            '--path' => $this->sourceRoot,
            '--theme' => 'webu-shop-01',
            '--name' => 'Webu Shop 01',
            '--force' => true,
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $template = Template::query()->where('slug', 'webu-shop-01')->firstOrFail();
        $homeSectionsJson = json_encode(
            data_get($template->metadata, 'default_sections.home', []),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        $this->assertIsString($homeSectionsJson);
        $this->assertStringContainsString('უნიკალური ქართული სათაური', $homeSectionsJson);
        $this->assertStringContainsString('ტესტური აღწერა ქართულად', $homeSectionsJson);
        $this->assertStringNotContainsString('á', $homeSectionsJson);
    }

    private function pageHtml(string $title, string $body): string
    {
        return <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8" />
  <title>{$title} - shop-design</title>
  <link rel="stylesheet" href="assets/css/style.css" />
</head>
<body>
<!-- pixio / themeforest / shop-design -->
{$body}
<script src="assets/js/main.js"></script>
</body>
</html>
HTML;
    }
}
