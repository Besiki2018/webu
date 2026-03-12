<?php

namespace Tests\Feature\Cms;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/** @group docs-sync */
class CmsRuntimeAliasAdoptionAuditCommandTest extends TestCase
{
    private string $fixtureRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fixtureRoot = storage_path('app/cms/runtime-alias-audit-command-tests');
        File::deleteDirectory($this->fixtureRoot);
        File::ensureDirectoryExists($this->fixtureRoot.'/published/site-a');
        File::ensureDirectoryExists($this->fixtureRoot.'/previews/site-a');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixtureRoot);

        parent::tearDown();
    }

    public function test_it_reports_marker_categories_for_runtime_html_artifacts(): void
    {
        File::put($this->fixtureRoot.'/published/site-a/home.html', <<<'HTML'
<html><body>
  <header data-webu-section="webu_header_01"></header>
  <section data-webu-section="webu_product_grid_01"></section>
  <section data-webu-section="webu_about_section_01"></section>
  <section data-webu-section="webu_promo_banners_01"></section>
  <section data-webu-section="webu_general_section_01"></section>
</body></html>
HTML);

        $this->artisan('cms:runtime-alias-adoption-audit', [
            '--root' => [
                $this->fixtureRoot.'/published',
                $this->fixtureRoot.'/previews',
            ],
            '--assert-min-markers' => 5,
            '--assert-max-unknown' => 0,
        ])
            ->expectsOutputToContain('Runtime alias adoption audit')
            ->expectsOutputToContain('canonical_alias_map_keys: 1')
            ->expectsOutputToContain('legacy_fixed_semantic_keys: 2')
            ->expectsOutputToContain('legacy_page_section_keys: 1')
            ->expectsOutputToContain('legacy_named_componentish_keys: 1')
            ->expectsOutputToContain('other: 0')
            ->assertSuccessful();
    }

    public function test_it_fails_when_unknown_marker_count_exceeds_threshold(): void
    {
        File::put($this->fixtureRoot.'/published/site-a/home.html', <<<'HTML'
<html><body>
  <section data-webu-section="webu_header_01"></section>
  <section data-webu-section="strange-custom-marker"></section>
</body></html>
HTML);

        $exitCode = Artisan::call('cms:runtime-alias-adoption-audit', [
            '--root' => [$this->fixtureRoot.'/published'],
            '--assert-min-markers' => 1,
            '--assert-max-unknown' => 0,
            '--json' => true,
        ]);

        $this->assertSame(1, $exitCode);

        $output = Artisan::output();
        $this->assertStringContainsString('"ok": false', $output);
        $this->assertStringContainsString('"other"', $output);
        $this->assertStringContainsString('unknown marker count 1 exceeds assert-max-unknown=0', $output);
    }
}
