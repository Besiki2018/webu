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

class TemplateImportCommandTest extends TestCase
{
    use RefreshDatabase;

    private string $importRoot;
    private string $demoRoot;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::set('installation_completed', true, 'boolean', 'system');

        $this->importRoot = storage_path('framework/testing/template-import');
        File::deleteDirectory($this->importRoot);
        File::ensureDirectoryExists($this->importRoot);

        $this->demoRoot = public_path('template-demos');
        File::deleteDirectory($this->demoRoot.'/asset-import-demo');
        File::ensureDirectoryExists($this->demoRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->importRoot);
        File::deleteDirectory($this->demoRoot.'/asset-import-demo');
        File::deleteDirectory($this->demoRoot.'/static-demo-import');
        File::deleteDirectory($this->demoRoot.'/absolute-link-demo');
        File::deleteDirectory($this->demoRoot.'/eliah-nextjs');

        parent::tearDown();
    }

    public function test_import_command_normalizes_metadata_and_generates_section_inventory(): void
    {
        $plan = Plan::factory()->create();

        SectionLibrary::query()->create([
            'key' => 'hero_split_image',
            'category' => 'marketing',
            'schema_json' => [
                'properties' => [
                    'headline' => ['type' => 'string'],
                    'subtitle' => ['type' => 'string'],
                ],
                'bindings' => [
                    'headline' => 'content.headline',
                ],
            ],
            'enabled' => true,
        ]);

        $templateDir = $this->importRoot.'/asset-import-demo';
        File::ensureDirectoryExists($templateDir.'/src');

        File::put($templateDir.'/package.json', json_encode([
            'name' => 'asset-import-demo',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));

        File::put($templateDir.'/src/index.tsx', 'export default function App() { return null; }');

        File::put($templateDir.'/template.json', json_encode([
            'slug' => 'asset-import-demo',
            'name' => 'Asset Import Demo',
            'category' => 'ecommerce',
            'framework' => 'react',
            'module_flags' => [
                'ecommerce' => true,
                'payments' => true,
            ],
            'default_pages' => [
                [
                    'slug' => 'home',
                    'title' => 'Home',
                    'sections' => ['hero_split_image', 'cart_showcase'],
                ],
                [
                    'slug' => 'contact',
                    'title' => 'Contact',
                    'sections' => ['contact_split_form'],
                ],
            ],
            'default_sections' => [
                'home' => [
                    ['key' => 'hero_split_image', 'props' => ['headline' => 'Demo headline']],
                    ['key' => 'cart_showcase'],
                ],
                'contact' => [
                    ['key' => 'contact_split_form'],
                ],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        File::ensureDirectoryExists($this->demoRoot.'/asset-import-demo');
        File::put($this->demoRoot.'/asset-import-demo/index.html', '<!doctype html><html><body>demo</body></html>');

        $exitCode = Artisan::call('templates:import-folder', [
            'path' => $this->importRoot,
            '--plan' => [$plan->id],
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $template = Template::query()->where('slug', 'asset-import-demo')->first();

        $this->assertNotNull($template);
        $this->assertSame('Asset Import Demo', $template->name);

        $metadata = is_array($template->metadata) ? $template->metadata : [];
        $this->assertSame('ecommerce', $metadata['vertical'] ?? null);
        $this->assertSame('React', $metadata['framework'] ?? null);
        $this->assertSame('template-demos/asset-import-demo', data_get($metadata, 'live_demo.path'));

        $this->assertIsArray($metadata['module_flags'] ?? null);
        $this->assertTrue((bool) ($metadata['module_flags']['cms_pages'] ?? false));
        $this->assertTrue((bool) ($metadata['module_flags']['ecommerce'] ?? false));

        $inventory = $metadata['section_inventory'] ?? null;
        $this->assertIsArray($inventory);
        $this->assertSame(3, (int) data_get($inventory, 'summary.total', 0));

        $items = collect(data_get($inventory, 'items', []));
        $hero = $items->firstWhere('key', 'hero_split_image');

        $this->assertIsArray($hero);
        $this->assertSame('hero_split_image', $hero['mapped_key'] ?? null);
        $this->assertContains('cart_showcase', data_get($inventory, 'unmapped_keys', []));

        $this->assertSame($plan->id, $template->plans()->first()?->id);
        $this->assertFileExists(storage_path('app/templates/asset-import-demo-template.zip'));
    }

    public function test_import_command_strict_mode_rejects_invalid_template_root(): void
    {
        $invalidDir = $this->importRoot.'/invalid-template';
        File::ensureDirectoryExists($invalidDir);
        File::put($invalidDir.'/README.md', '# invalid template');

        $exitCode = Artisan::call('templates:import-folder', [
            'path' => $this->importRoot,
            '--strict' => true,
        ]);

        $this->assertSame(1, $exitCode);
        $this->assertNull(Template::query()->where('slug', 'invalid-template')->first());
    }

    public function test_import_command_accepts_gatsby_starter_root_without_src_directory(): void
    {
        $plan = Plan::factory()->create();

        $templateDir = $this->importRoot.'/gatsby-starter-demo';
        File::ensureDirectoryExists($templateDir.'/content');
        File::put($templateDir.'/content/readme.md', '# demo content');
        File::put($templateDir.'/package.json', json_encode([
            'name' => 'gatsby-starter-demo',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));
        File::put($templateDir.'/gatsby-config.js', 'module.exports = {};');

        $exitCode = Artisan::call('templates:import-folder', [
            'path' => $this->importRoot,
            '--plan' => [$plan->id],
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $template = Template::query()->where('slug', 'gatsby-starter-demo')->first();
        $this->assertNotNull($template);
        $this->assertSame('gatsby-starter-demo', data_get($template->metadata, 'import.imported_slug'));
    }

    public function test_import_command_imports_multiple_detected_template_roots_from_monorepo_entry(): void
    {
        $plan = Plan::factory()->create();

        $entryDir = $this->importRoot.'/multi-root-pack';
        $alphaDir = $entryDir.'/starters/alpha';
        $betaDir = $entryDir.'/starters/beta';

        File::ensureDirectoryExists($alphaDir.'/src');
        File::ensureDirectoryExists($betaDir.'/src');

        File::put($alphaDir.'/package.json', json_encode([
            'name' => 'alpha-starter',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));
        File::put($betaDir.'/package.json', json_encode([
            'name' => 'beta-starter',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));

        File::put($alphaDir.'/src/index.tsx', 'export default function Alpha() { return null; }');
        File::put($betaDir.'/src/index.tsx', 'export default function Beta() { return null; }');

        File::put($alphaDir.'/template.json', json_encode([
            'slug' => 'alpha-starter',
            'name' => 'Alpha Starter',
            'default_pages' => [
                ['slug' => 'home', 'title' => 'Home', 'sections' => ['hero']],
            ],
            'default_sections' => [
                'home' => [['key' => 'hero']],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        File::put($betaDir.'/template.json', json_encode([
            'slug' => 'beta-starter',
            'name' => 'Beta Starter',
            'default_pages' => [
                ['slug' => 'home', 'title' => 'Home', 'sections' => ['hero']],
            ],
            'default_sections' => [
                'home' => [['key' => 'hero']],
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $exitCode = Artisan::call('templates:import-folder', [
            'path' => $this->importRoot,
            '--plan' => [$plan->id],
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertNotNull(Template::query()->where('slug', 'alpha-starter')->first());
        $this->assertNotNull(Template::query()->where('slug', 'beta-starter')->first());
    }

    public function test_import_command_generates_distinct_slugs_for_multi_root_entries_without_manifest(): void
    {
        $plan = Plan::factory()->create();

        $entryDir = $this->importRoot.'/monorepo-no-manifest';
        $alphaDir = $entryDir.'/starters/alpha-pack';
        $betaDir = $entryDir.'/starters/beta-pack';

        File::ensureDirectoryExists($alphaDir.'/src');
        File::ensureDirectoryExists($betaDir.'/src');

        File::put($alphaDir.'/package.json', json_encode([
            'name' => 'alpha-pack',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));
        File::put($betaDir.'/package.json', json_encode([
            'name' => 'beta-pack',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));

        File::put($alphaDir.'/src/index.tsx', 'export default function AlphaPack() { return null; }');
        File::put($betaDir.'/src/index.tsx', 'export default function BetaPack() { return null; }');

        $exitCode = Artisan::call('templates:import-folder', [
            'path' => $this->importRoot,
            '--plan' => [$plan->id],
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertNotNull(Template::query()->where('slug', 'alpha-pack')->first());
        $this->assertNotNull(Template::query()->where('slug', 'beta-pack')->first());
    }

    public function test_import_command_publishes_static_demo_from_dist_folder_and_sets_live_demo_path(): void
    {
        $plan = Plan::factory()->create();

        $templateDir = $this->importRoot.'/static-demo-import';
        File::ensureDirectoryExists($templateDir.'/src');
        File::ensureDirectoryExists($templateDir.'/dist');

        File::put($templateDir.'/package.json', json_encode([
            'name' => 'static-demo-import',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));
        File::put($templateDir.'/src/index.tsx', 'export default function StaticDemo() { return null; }');
        File::put($templateDir.'/dist/index.html', '<!doctype html><html><body>static-demo</body></html>');

        $exitCode = Artisan::call('templates:import-folder', [
            'path' => $this->importRoot,
            '--plan' => [$plan->id],
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $template = Template::query()->where('slug', 'static-demo-import')->first();
        $this->assertNotNull($template);
        $this->assertSame('template-demos/static-demo-import', data_get($template->metadata, 'live_demo.path'));
        $this->assertFileExists(public_path('template-demos/static-demo-import/index.html'));
        $this->assertStringContainsString(
            'static-demo',
            (string) File::get(public_path('template-demos/static-demo-import/index.html'))
        );
    }

    public function test_import_command_rewrites_absolute_html_links_to_template_demo_prefix(): void
    {
        $plan = Plan::factory()->create();

        $templateDir = $this->importRoot.'/absolute-link-demo';
        File::ensureDirectoryExists($templateDir.'/src');
        File::ensureDirectoryExists($templateDir.'/dist');
        File::ensureDirectoryExists($templateDir.'/dist/assets');
        File::ensureDirectoryExists($templateDir.'/dist/static/js');

        File::put($templateDir.'/package.json', json_encode([
            'name' => 'absolute-link-demo',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));
        File::put($templateDir.'/src/index.tsx', 'export default function AbsoluteLinkDemo() { return null; }');
        File::put($templateDir.'/dist/index.html', <<<'HTML'
<!doctype html>
<html>
<head>
    <link rel="stylesheet" href="/assets/app.css" />
</head>
<body>
    <img src="/images/hero.png" alt="hero" />
    <a href="/shop">Shop</a>
    <script src="/static/js/app.js"></script>
</body>
</html>
HTML
        );
        File::put($templateDir.'/dist/assets/app.css', 'body{background:#fff}');
        File::put($templateDir.'/dist/static/js/app.js', 'console.log("ok")');

        $exitCode = Artisan::call('templates:import-folder', [
            'path' => $this->importRoot,
            '--plan' => [$plan->id],
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $rewritten = (string) File::get(public_path('template-demos/absolute-link-demo/index.html'));
        $this->assertStringContainsString('/template-demos/absolute-link-demo/assets/app.css', $rewritten);
        $this->assertStringContainsString('/template-demos/absolute-link-demo/images/hero.png', $rewritten);
        $this->assertStringContainsString('/template-demos/absolute-link-demo/shop', $rewritten);
        $this->assertStringContainsString('/template-demos/absolute-link-demo/static/js/app.js', $rewritten);
    }

    public function test_import_command_respects_next_asset_prefix_for_demo_publish_path(): void
    {
        $plan = Plan::factory()->create();

        $templateDir = $this->importRoot.'/asset-prefix-demo';
        File::ensureDirectoryExists($templateDir.'/out');
        File::ensureDirectoryExists($templateDir.'/src');

        File::put($templateDir.'/package.json', json_encode([
            'name' => 'asset-prefix-demo',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));
        File::put($templateDir.'/src/index.tsx', 'export default function AssetPrefixDemo() { return null; }');
        File::put($templateDir.'/template.json', json_encode([
            'slug' => 'asset-prefix-demo',
            'name' => 'Asset Prefix Demo',
            'default_pages' => [
                ['slug' => 'home', 'title' => 'Home', 'sections' => ['hero']],
            ],
        ], JSON_PRETTY_PRINT));

        File::put($templateDir.'/out/index.html', <<<'HTML'
<!doctype html>
<html>
<head>
    <script id="__NEXT_DATA__" type="application/json">{"assetPrefix":"/template-demos/eliah-nextjs"}</script>
</head>
<body>
    <script src="/template-demos/eliah-nextjs/_next/static/runtime/main.js"></script>
</body>
</html>
HTML
        );

        $exitCode = Artisan::call('templates:import-folder', [
            'path' => $this->importRoot,
            '--plan' => [$plan->id],
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());

        $template = Template::query()->where('slug', 'asset-prefix-demo')->first();
        $this->assertNotNull($template);
        $this->assertSame('template-demos/eliah-nextjs', data_get($template->metadata, 'live_demo.path'));
        $this->assertFileExists(public_path('template-demos/eliah-nextjs/index.html'));
    }

    public function test_import_command_treats_direct_path_as_single_template_root_and_skips_node_modules_scan(): void
    {
        $plan = Plan::factory()->create();

        $templateDir = $this->importRoot.'/direct-root-demo';
        File::ensureDirectoryExists($templateDir.'/src');
        File::ensureDirectoryExists($templateDir.'/node_modules/some-lib/src');

        File::put($templateDir.'/package.json', json_encode([
            'name' => 'direct-root-demo',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));
        File::put($templateDir.'/src/index.tsx', 'export default function DirectRootDemo() { return null; }');

        File::put($templateDir.'/node_modules/some-lib/package.json', json_encode([
            'name' => 'some-lib',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));
        File::put($templateDir.'/node_modules/some-lib/src/index.ts', 'export const x = 1;');

        $exitCode = Artisan::call('templates:import-folder', [
            'path' => $templateDir,
            '--plan' => [$plan->id],
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertNotNull(Template::query()->where('slug', 'direct-root-demo')->first());
        $this->assertNull(Template::query()->where('slug', 'some-lib')->first());
    }

    public function test_import_command_reuses_slug_when_existing_template_has_legacy_directory_source_root(): void
    {
        $plan = Plan::factory()->create();

        Template::factory()->create([
            'slug' => 'legacy-directory-source',
            'name' => 'Legacy Directory Source',
            'metadata' => [
                'source_root' => 'directory:legacy-directory-source',
            ],
        ]);

        $templateDir = $this->importRoot.'/legacy-directory-source';
        File::ensureDirectoryExists($templateDir.'/src');
        File::put($templateDir.'/package.json', json_encode([
            'name' => 'legacy-directory-source',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));
        File::put($templateDir.'/src/index.tsx', 'export default function LegacySource() { return null; }');

        $exitCode = Artisan::call('templates:import-folder', [
            'path' => $this->importRoot,
            '--plan' => [$plan->id],
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertSame(1, Template::query()->where('slug', 'legacy-directory-source')->count());
        $this->assertNull(Template::query()->where('slug', 'legacy-directory-source-2')->first());
        $this->assertSame(
            'directory:legacy-directory-source',
            (string) data_get(Template::query()->where('slug', 'legacy-directory-source')->first(), 'metadata.source_root')
        );
    }

    public function test_import_command_reuses_slug_when_existing_template_has_slug_only_source_root(): void
    {
        $plan = Plan::factory()->create();

        Template::factory()->create([
            'slug' => 'legacy-slug-source',
            'name' => 'Legacy Slug Source',
            'metadata' => [
                'source_root' => 'legacy-slug-source',
            ],
        ]);

        $templateDir = $this->importRoot.'/legacy-slug-source';
        File::ensureDirectoryExists($templateDir.'/src');
        File::put($templateDir.'/package.json', json_encode([
            'name' => 'legacy-slug-source',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));
        File::put($templateDir.'/src/index.tsx', 'export default function LegacySlugSource() { return null; }');

        $exitCode = Artisan::call('templates:import-folder', [
            'path' => $this->importRoot,
            '--plan' => [$plan->id],
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertSame(1, Template::query()->where('slug', 'legacy-slug-source')->count());
        $this->assertNull(Template::query()->where('slug', 'legacy-slug-source-2')->first());
    }

    public function test_import_command_reuses_slug_when_existing_template_has_humanized_legacy_source_root(): void
    {
        $plan = Plan::factory()->create();

        Template::factory()->create([
            'slug' => 'react-template',
            'name' => 'React Template',
            'metadata' => [
                'source_root' => 'React Template',
            ],
        ]);

        $templateDir = $this->importRoot.'/react-template';
        File::ensureDirectoryExists($templateDir.'/src');
        File::put($templateDir.'/package.json', json_encode([
            'name' => 'react-template',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));
        File::put($templateDir.'/src/index.tsx', 'export default function ReactTemplate() { return null; }');

        $exitCode = Artisan::call('templates:import-folder', [
            'path' => $this->importRoot,
            '--plan' => [$plan->id],
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertSame(1, Template::query()->where('slug', 'react-template')->count());
        $this->assertNull(Template::query()->where('slug', 'react-template-2')->first());
    }

    public function test_import_command_reuses_slug_when_directory_source_is_wrapped_with_extra_prefix_path(): void
    {
        $plan = Plan::factory()->create();

        Template::factory()->create([
            'slug' => 'prefix-compat',
            'name' => 'Prefix Compat',
            'metadata' => [
                'source_root' => 'directory:starters/prefix-compat',
            ],
        ]);

        $templateDir = $this->importRoot.'/@scope/starters/prefix-compat';
        File::ensureDirectoryExists($templateDir.'/src');
        File::put($templateDir.'/package.json', json_encode([
            'name' => 'prefix-compat',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));
        File::put($templateDir.'/src/index.tsx', 'export default function PrefixCompat() { return null; }');

        $exitCode = Artisan::call('templates:import-folder', [
            'path' => $this->importRoot,
            '--plan' => [$plan->id],
        ]);

        $this->assertSame(0, $exitCode, Artisan::output());
        $this->assertSame(1, Template::query()->where('slug', 'prefix-compat')->count());
        $this->assertNull(Template::query()->where('slug', 'prefix-compat-2')->first());
    }

    public function test_import_command_assigns_distinct_stable_slugs_for_colliding_multi_root_basenames(): void
    {
        $plan = Plan::factory()->create();

        $entryDir = $this->importRoot.'/collision-pack';
        $alphaDir = $entryDir.'/alpha/web';
        $betaDir = $entryDir.'/beta/web';

        File::ensureDirectoryExists($alphaDir.'/src');
        File::ensureDirectoryExists($betaDir.'/src');

        File::put($alphaDir.'/package.json', json_encode([
            'name' => 'alpha-web',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));
        File::put($betaDir.'/package.json', json_encode([
            'name' => 'beta-web',
            'version' => '1.0.0',
        ], JSON_PRETTY_PRINT));

        File::put($alphaDir.'/src/index.tsx', 'export default function AlphaWeb() { return null; }');
        File::put($betaDir.'/src/index.tsx', 'export default function BetaWeb() { return null; }');

        $firstRun = Artisan::call('templates:import-folder', [
            'path' => $this->importRoot,
            '--plan' => [$plan->id],
        ]);

        $this->assertSame(0, $firstRun, Artisan::output());
        $this->assertNotNull(Template::query()->where('slug', 'web')->first());
        $this->assertNotNull(Template::query()->where('slug', 'web-2')->first());

        $countAfterFirstRun = Template::query()->whereIn('slug', ['web', 'web-2'])->count();
        $this->assertSame(2, $countAfterFirstRun);

        $secondRun = Artisan::call('templates:import-folder', [
            'path' => $this->importRoot,
            '--plan' => [$plan->id],
        ]);

        $this->assertSame(0, $secondRun, Artisan::output());
        $countAfterSecondRun = Template::query()->whereIn('slug', ['web', 'web-2'])->count();
        $this->assertSame(2, $countAfterSecondRun);
        $this->assertSame(
            2,
            Template::query()->where('slug', 'like', 'web%')->count(),
            'Re-import must remain stable and avoid web-3/web-4 suffix drift.'
        );
    }
}
