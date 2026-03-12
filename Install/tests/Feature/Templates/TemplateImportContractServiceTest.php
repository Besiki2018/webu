<?php

namespace Tests\Feature\Templates;

use App\Services\TemplateImportContractService;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class TemplateImportContractServiceTest extends TestCase
{
    private string $sourceRoot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceRoot = storage_path('framework/testing/template-import-contract');
        File::deleteDirectory($this->sourceRoot);
        File::ensureDirectoryExists($this->sourceRoot);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->sourceRoot);

        parent::tearDown();
    }

    public function test_validate_source_root_warns_when_declared_demo_content_and_binding_markers_are_missing(): void
    {
        $this->writeJson($this->sourceRoot.'/template.json', [
            'name' => 'Broken Builder Template',
            'demo_content' => true,
            'pages' => ['home', 'shop', 'cart', 'checkout'],
            'components' => [],
        ]);

        File::put($this->sourceRoot.'/index.html', <<<'HTML'
<!doctype html>
<html><body><h1>Template without markers</h1></body></html>
HTML);

        $result = app(TemplateImportContractService::class)->validateSourceRoot($this->sourceRoot);

        $this->assertTrue($result['valid']);
        $this->assertContains(
            'template.json declares demo content but directory is missing: demo-content/',
            $result['warnings']
        );
        $this->assertContains(
            'No Webu CMS binding markers detected in HTML (e.g. data-webu-section/data-webu-field).',
            $result['warnings']
        );
        $this->assertContains(
            'Template appears to include ecommerce pages but no ecommerce binding markers were detected (data-webby-ecommerce-products/cart).',
            $result['warnings']
        );
    }

    public function test_validate_source_root_accepts_valid_demo_content_and_binding_markers_without_contract_warnings(): void
    {
        $this->writeJson($this->sourceRoot.'/template.json', [
            'name' => 'Valid Builder Template',
            'demo_content' => true,
            'pages' => ['home', 'shop', 'product', 'cart', 'checkout'],
            'components' => [
                ['key' => 'header'],
                ['key' => 'hero'],
            ],
        ]);

        File::ensureDirectoryExists($this->sourceRoot.'/demo-content');
        $this->writeJson($this->sourceRoot.'/demo-content/content.json', [
            'datasets' => [
                'products_file' => 'products.json',
                'posts_file' => 'posts.json',
            ],
        ]);
        $this->writeJson($this->sourceRoot.'/demo-content/products.json', [
            'products' => [
                ['name' => 'Demo Product'],
            ],
        ]);
        $this->writeJson($this->sourceRoot.'/demo-content/posts.json', [
            'posts' => [
                ['title' => 'Demo Post'],
            ],
        ]);

        File::put($this->sourceRoot.'/index.html', <<<'HTML'
<!doctype html>
<html>
  <body>
    <header data-webu-menu="header"></header>
    <section data-webu-section="webu_hero_01">
      <h1 data-webu-field="headline">Fallback headline</h1>
    </section>
    <section data-webby-ecommerce-products>
      <article class="product"></article>
    </section>
    <aside data-webby-ecommerce-cart></aside>
  </body>
</html>
HTML);

        $result = app(TemplateImportContractService::class)->validateSourceRoot($this->sourceRoot);

        $this->assertTrue($result['valid'], implode("\n", $result['errors']));
        $this->assertSame([], $result['errors']);
        $this->assertNotContains(
            'template.json declares demo content but directory is missing: demo-content/',
            $result['warnings']
        );
        $this->assertNotContains(
            'No Webu CMS binding markers detected in HTML (e.g. data-webu-section/data-webu-field).',
            $result['warnings']
        );
        $this->assertNotContains(
            'Template appears to include ecommerce pages but no ecommerce binding markers were detected (data-webby-ecommerce-products/cart).',
            $result['warnings']
        );
    }

    public function test_validate_source_root_warns_on_suspected_mojibake_in_html(): void
    {
        $this->writeJson($this->sourceRoot.'/template.json', [
            'name' => 'Encoding Warning Template',
            'pages' => ['home'],
            'components' => [['key' => 'hero']],
        ]);

        File::put($this->sourceRoot.'/index.html', <<<'HTML'
<!doctype html>
<html>
  <body>
    <section data-webu-section="webu_hero_01">
      <h1 data-webu-field="headline">FranÃ§ais title</h1>
      <p>Broken apostrophe â€™ sample</p>
    </section>
  </body>
</html>
HTML);

        $result = app(TemplateImportContractService::class)->validateSourceRoot($this->sourceRoot);

        $this->assertTrue($result['valid']);
        $this->assertTrue(
            collect($result['warnings'])->contains(
                fn (string $warning): bool => str_contains($warning, 'Potential text encoding issue detected in HTML')
                    && str_contains($warning, 'index.html')
            ),
            'Expected mojibake warning was not emitted.'
        );
    }

    public function test_validate_source_root_warns_when_shop_and_cart_page_blueprints_lack_required_ecommerce_markers(): void
    {
        $this->writeJson($this->sourceRoot.'/template.json', [
            'name' => 'Blueprint Marker Warning Template',
            'pageBlueprintsPath' => 'pages/',
            'pages' => ['shop', 'cart'],
            'components' => [
                ['name' => 'header', 'html' => 'components/header/component.html'],
                ['name' => 'product-list', 'html' => 'components/product-list/component.html'],
            ],
        ]);

        File::ensureDirectoryExists($this->sourceRoot.'/components/header');
        File::ensureDirectoryExists($this->sourceRoot.'/components/product-list');
        File::put($this->sourceRoot.'/components/header/component.html', '<header data-webu-menu="header"></header>');
        File::put($this->sourceRoot.'/components/product-list/component.html', '<section data-webu-section="webu_product_grid_01"></section>');

        File::ensureDirectoryExists($this->sourceRoot.'/pages');
        $this->writeJson($this->sourceRoot.'/pages/shop.json', [
            'file' => 'shop-left-sidebar.html',
            'components' => ['header', 'product-list'],
        ]);
        $this->writeJson($this->sourceRoot.'/pages/cart.json', [
            'file' => 'shop-cart.html',
            'components' => ['header'],
        ]);

        File::put($this->sourceRoot.'/shop-left-sidebar.html', '<html><body>shop page</body></html>');
        File::put($this->sourceRoot.'/shop-cart.html', '<html><body>cart page</body></html>');
        File::put($this->sourceRoot.'/index.html', '<html><body><section data-webu-section="x"></section></body></html>');

        $result = app(TemplateImportContractService::class)->validateSourceRoot($this->sourceRoot);

        $this->assertTrue($result['valid']);
        $this->assertTrue(
            collect($result['warnings'])->contains(
                fn (string $warning): bool => str_contains($warning, 'Shop page blueprint (shop.json)')
            )
        );
        $this->assertTrue(
            collect($result['warnings'])->contains(
                fn (string $warning): bool => str_contains($warning, 'Cart page blueprint (cart.json)')
            )
        );
    }

    public function test_validate_source_root_page_blueprints_pass_when_referenced_components_include_ecommerce_markers(): void
    {
        $this->writeJson($this->sourceRoot.'/template.json', [
            'name' => 'Blueprint Marker OK Template',
            'pageBlueprintsPath' => 'pages/',
            'pages' => ['shop', 'cart'],
            'components' => [
                ['name' => 'header', 'html' => 'components/header/component.html'],
                ['name' => 'product-list', 'html' => 'components/product-list/component.html'],
            ],
        ]);

        File::ensureDirectoryExists($this->sourceRoot.'/components/header');
        File::ensureDirectoryExists($this->sourceRoot.'/components/product-list');
        File::put(
            $this->sourceRoot.'/components/header/component.html',
            '<header data-webu-menu="header"></header><div data-webby-ecommerce-cart></div>'
        );
        File::put(
            $this->sourceRoot.'/components/product-list/component.html',
            '<section data-webu-section="webu_product_grid_01" data-webby-ecommerce-products></section>'
        );

        File::ensureDirectoryExists($this->sourceRoot.'/pages');
        $this->writeJson($this->sourceRoot.'/pages/shop.json', [
            'file' => 'shop-left-sidebar.html',
            'components' => ['header', 'product-list'],
        ]);
        $this->writeJson($this->sourceRoot.'/pages/cart.json', [
            'file' => 'shop-cart.html',
            'components' => ['header'],
        ]);

        File::put($this->sourceRoot.'/shop-left-sidebar.html', '<html><body>shop page</body></html>');
        File::put($this->sourceRoot.'/shop-cart.html', '<html><body>cart page</body></html>');
        File::put($this->sourceRoot.'/index.html', '<html><body><section data-webu-section="x"></section></body></html>');

        $result = app(TemplateImportContractService::class)->validateSourceRoot($this->sourceRoot);

        $this->assertTrue($result['valid'], implode("\n", $result['errors']));
        $this->assertFalse(
            collect($result['warnings'])->contains(
                fn (string $warning): bool => str_contains($warning, 'Shop page blueprint (shop.json)')
                    || str_contains($warning, 'Cart page blueprint (cart.json)')
            ),
            'Unexpected page blueprint ecommerce marker warnings were emitted.'
        );
    }

    public function test_validate_source_root_warns_when_product_and_checkout_page_blueprints_lack_runtime_anchors(): void
    {
        $this->writeJson($this->sourceRoot.'/template.json', [
            'name' => 'Runtime Anchor Warning Template',
            'pageBlueprintsPath' => 'pages/',
            'pages' => ['product', 'checkout'],
            'components' => [
                ['name' => 'header', 'html' => 'components/header/component.html'],
                ['name' => 'footer', 'html' => 'components/footer/component.html'],
            ],
        ]);

        File::ensureDirectoryExists($this->sourceRoot.'/components/header');
        File::ensureDirectoryExists($this->sourceRoot.'/components/footer');
        File::put($this->sourceRoot.'/components/header/component.html', '<header data-webu-menu="header"></header>');
        File::put($this->sourceRoot.'/components/footer/component.html', '<footer data-webu-section="webu_footer_01"></footer>');

        File::ensureDirectoryExists($this->sourceRoot.'/pages');
        $this->writeJson($this->sourceRoot.'/pages/product.json', [
            'file' => 'shop-product-detail.html',
            'components' => ['header', 'footer'],
        ]);
        $this->writeJson($this->sourceRoot.'/pages/checkout.json', [
            'file' => 'checkout.html',
            'components' => ['header', 'footer'],
        ]);

        File::put($this->sourceRoot.'/shop-product-detail.html', '<html><body><div class="details">missing runtime anchors</div></body></html>');
        File::put($this->sourceRoot.'/checkout.html', '<html><body><div class="checkout-page">missing order review</div></body></html>');
        File::put($this->sourceRoot.'/index.html', '<html><body><section data-webu-section="x"></section></body></html>');

        $result = app(TemplateImportContractService::class)->validateSourceRoot($this->sourceRoot);

        $this->assertTrue($result['valid']);
        $this->assertTrue(
            collect($result['warnings'])->contains(
                fn (string $warning): bool => str_contains($warning, 'Product page blueprint (product.json)')
            )
        );
        $this->assertTrue(
            collect($result['warnings'])->contains(
                fn (string $warning): bool => str_contains($warning, 'Checkout page blueprint (checkout.json)')
            )
        );
    }

    public function test_validate_source_root_warns_when_auth_account_and_orders_page_blueprints_lack_runtime_markers(): void
    {
        $this->writeJson($this->sourceRoot.'/template.json', [
            'name' => 'Account Runtime Marker Warning Template',
            'pageBlueprintsPath' => 'pages/',
            'pages' => ['login-register', 'my-account', 'orders-list', 'order-status'],
            'components' => [
                ['name' => 'header', 'html' => 'components/header/component.html'],
                ['name' => 'footer', 'html' => 'components/footer/component.html'],
            ],
        ]);

        File::ensureDirectoryExists($this->sourceRoot.'/components/header');
        File::ensureDirectoryExists($this->sourceRoot.'/components/footer');
        File::put($this->sourceRoot.'/components/header/component.html', '<header data-webu-menu="header"></header>');
        File::put($this->sourceRoot.'/components/footer/component.html', '<footer data-webu-section="webu_footer_01"></footer>');

        File::ensureDirectoryExists($this->sourceRoot.'/pages');
        $this->writeJson($this->sourceRoot.'/pages/login-register.json', [
            'file' => 'login-register.html',
            'components' => ['header', 'footer'],
        ]);
        $this->writeJson($this->sourceRoot.'/pages/my-account.json', [
            'file' => 'account.html',
            'components' => ['header', 'footer'],
        ]);
        $this->writeJson($this->sourceRoot.'/pages/orders-list.json', [
            'file' => 'orders.html',
            'components' => ['header', 'footer'],
        ]);
        $this->writeJson($this->sourceRoot.'/pages/order-status.json', [
            'file' => 'order-detail.html',
            'components' => ['header', 'footer'],
        ]);

        File::put($this->sourceRoot.'/login-register.html', '<html><body><div class="auth-page">missing marker</div></body></html>');
        File::put($this->sourceRoot.'/account.html', '<html><body><div class="account-page">missing marker</div></body></html>');
        File::put($this->sourceRoot.'/orders.html', '<html><body><div class="orders-page">missing marker</div></body></html>');
        File::put($this->sourceRoot.'/order-detail.html', '<html><body><div class="order-detail-page">missing marker</div></body></html>');
        File::put($this->sourceRoot.'/index.html', '<html><body><section data-webu-section="x"></section></body></html>');

        $result = app(TemplateImportContractService::class)->validateSourceRoot($this->sourceRoot);

        $this->assertTrue($result['valid']);
        $this->assertTrue(
            collect($result['warnings'])->contains(
                fn (string $warning): bool => str_contains($warning, 'Auth page blueprint (login-register.json)')
            )
        );
        $this->assertTrue(
            collect($result['warnings'])->contains(
                fn (string $warning): bool => str_contains($warning, 'Account page blueprint (my-account.json)')
            )
        );
        $this->assertTrue(
            collect($result['warnings'])->contains(
                fn (string $warning): bool => str_contains($warning, 'Orders page blueprint (orders-list.json)')
            )
        );
        $this->assertTrue(
            collect($result['warnings'])->contains(
                fn (string $warning): bool => str_contains($warning, 'Order detail page blueprint (order-status.json)')
            )
        );
    }

    public function test_validate_source_root_warns_for_missing_and_invalid_declared_page_blueprints(): void
    {
        $this->writeJson($this->sourceRoot.'/template.json', [
            'name' => 'Missing Page Blueprints Template',
            'pageBlueprintsPath' => 'pages/',
            'pages' => ['home', 'shop'],
            'components' => [
                ['key' => 'header'],
            ],
        ]);

        File::ensureDirectoryExists($this->sourceRoot.'/pages');
        File::put($this->sourceRoot.'/pages/home.json', '{invalid json}');
        File::put(
            $this->sourceRoot.'/index.html',
            '<!doctype html><html><body><header data-webu-menu="header"></header></body></html>'
        );

        $result = app(TemplateImportContractService::class)->validateSourceRoot($this->sourceRoot);

        $this->assertTrue($result['valid']);
        $this->assertContains(
            'Declared page blueprint is missing JSON definition: pages/shop.json',
            $result['warnings']
        );
        $this->assertContains(
            'Page blueprint JSON is not valid: pages/home.json',
            $result['warnings']
        );
    }

    public function test_validate_source_root_warns_when_page_blueprint_references_missing_page_template_file(): void
    {
        $this->writeJson($this->sourceRoot.'/template.json', [
            'name' => 'Missing Page Template Reference Template',
            'pageBlueprintsPath' => 'pages/',
            'pages' => ['home'],
            'components' => [
                ['key' => 'header'],
            ],
        ]);

        File::ensureDirectoryExists($this->sourceRoot.'/pages');
        $this->writeJson($this->sourceRoot.'/pages/home.json', [
            'file' => 'home.html',
            'components' => ['header'],
        ]);
        File::put(
            $this->sourceRoot.'/index.html',
            '<!doctype html><html><body><header data-webu-menu="header"></header></body></html>'
        );

        $result = app(TemplateImportContractService::class)->validateSourceRoot($this->sourceRoot);

        $this->assertTrue($result['valid']);
        $this->assertContains(
            'Page blueprint references missing page template file: pages/home.json -> home.html',
            $result['warnings']
        );
    }

    public function test_validate_source_root_product_and_checkout_page_blueprints_pass_with_runtime_anchors(): void
    {
        $this->writeJson($this->sourceRoot.'/template.json', [
            'name' => 'Runtime Anchor OK Template',
            'pageBlueprintsPath' => 'pages/',
            'pages' => ['product', 'checkout'],
            'components' => [
                ['name' => 'header', 'html' => 'components/header/component.html'],
                ['name' => 'footer', 'html' => 'components/footer/component.html'],
            ],
        ]);

        File::ensureDirectoryExists($this->sourceRoot.'/components/header');
        File::ensureDirectoryExists($this->sourceRoot.'/components/footer');
        File::put($this->sourceRoot.'/components/header/component.html', '<header data-webu-menu="header"></header>');
        File::put($this->sourceRoot.'/components/footer/component.html', '<footer data-webu-section="webu_footer_01"></footer>');

        File::ensureDirectoryExists($this->sourceRoot.'/pages');
        $this->writeJson($this->sourceRoot.'/pages/product.json', [
            'file' => 'shop-product-detail.html',
            'components' => ['header', 'footer'],
        ]);
        $this->writeJson($this->sourceRoot.'/pages/checkout.json', [
            'file' => 'checkout.html',
            'components' => ['header', 'footer'],
        ]);

        File::put(
            $this->sourceRoot.'/shop-product-detail.html',
            '<html><body><div class="product_description"></div><button class="btn-addtocart">Add</button></body></html>'
        );
        File::put(
            $this->sourceRoot.'/checkout.html',
            '<html><body><div class="order_review"></div><div class="table-responsive order_table"></div></body></html>'
        );
        File::put($this->sourceRoot.'/index.html', '<html><body><section data-webu-section="x"></section></body></html>');

        $result = app(TemplateImportContractService::class)->validateSourceRoot($this->sourceRoot);

        $this->assertTrue($result['valid'], implode("\n", $result['errors']));
        $this->assertFalse(
            collect($result['warnings'])->contains(
                fn (string $warning): bool => str_contains($warning, 'Product page blueprint (product.json)')
                    || str_contains($warning, 'Checkout page blueprint (checkout.json)')
            ),
            'Unexpected product/checkout runtime anchor warnings were emitted.'
        );
    }

    public function test_validate_source_root_auth_account_and_orders_page_blueprints_pass_with_runtime_markers(): void
    {
        $this->writeJson($this->sourceRoot.'/template.json', [
            'name' => 'Account Runtime Marker OK Template',
            'pageBlueprintsPath' => 'pages/',
            'pages' => ['login', 'account', 'orders', 'order-detail'],
            'components' => [
                ['name' => 'header', 'html' => 'components/header/component.html'],
                ['name' => 'auth-shell', 'html' => 'components/auth-shell/component.html'],
                ['name' => 'account-dashboard', 'html' => 'components/account-dashboard/component.html'],
                ['name' => 'orders-list', 'html' => 'components/orders-list/component.html'],
            ],
        ]);

        File::ensureDirectoryExists($this->sourceRoot.'/components/header');
        File::ensureDirectoryExists($this->sourceRoot.'/components/auth-shell');
        File::ensureDirectoryExists($this->sourceRoot.'/components/account-dashboard');
        File::ensureDirectoryExists($this->sourceRoot.'/components/orders-list');
        File::put($this->sourceRoot.'/components/header/component.html', '<header data-webu-menu="header"></header>');
        File::put($this->sourceRoot.'/components/auth-shell/component.html', '<section data-webby-ecommerce-auth></section>');
        File::put($this->sourceRoot.'/components/account-dashboard/component.html', '<section data-webby-ecommerce-account-dashboard></section>');
        File::put($this->sourceRoot.'/components/orders-list/component.html', '<section data-webby-ecommerce-orders-list></section>');

        File::ensureDirectoryExists($this->sourceRoot.'/pages');
        $this->writeJson($this->sourceRoot.'/pages/login.json', [
            'file' => 'login.html',
            'components' => ['header', 'auth-shell'],
        ]);
        $this->writeJson($this->sourceRoot.'/pages/account.json', [
            'file' => 'account.html',
            'components' => ['header', 'account-dashboard'],
        ]);
        $this->writeJson($this->sourceRoot.'/pages/orders.json', [
            'file' => 'orders.html',
            'components' => ['header', 'orders-list'],
        ]);
        $this->writeJson($this->sourceRoot.'/pages/order-detail.json', [
            'file' => 'order-detail.html',
            'components' => ['header'],
        ]);

        File::put($this->sourceRoot.'/login.html', '<html><body>login page shell</body></html>');
        File::put($this->sourceRoot.'/account.html', '<html><body>account page shell</body></html>');
        File::put($this->sourceRoot.'/orders.html', '<html><body>orders page shell</body></html>');
        File::put($this->sourceRoot.'/order-detail.html', '<html><body><div data-webby-ecommerce-order-detail></div></body></html>');
        File::put($this->sourceRoot.'/index.html', '<html><body><section data-webu-section="x"></section></body></html>');

        $result = app(TemplateImportContractService::class)->validateSourceRoot($this->sourceRoot);

        $this->assertTrue($result['valid'], implode("\n", $result['errors']));
        $this->assertFalse(
            collect($result['warnings'])->contains(
                fn (string $warning): bool => str_contains($warning, 'Auth page blueprint (login.json)')
                    || str_contains($warning, 'Account page blueprint (account.json)')
                    || str_contains($warning, 'Orders page blueprint (orders.json)')
                    || str_contains($warning, 'Order detail page blueprint (order-detail.json)')
            ),
            'Unexpected auth/account/orders runtime marker warnings were emitted.'
        );
    }

    public function test_validate_source_root_warns_when_auth_and_account_page_blueprints_lack_runtime_anchors(): void
    {
        $this->writeJson($this->sourceRoot.'/template.json', [
            'name' => 'Auth Account Runtime Anchor Warning Template',
            'pageBlueprintsPath' => 'pages/',
            'pages' => ['login', 'account'],
            'components' => [
                ['name' => 'header', 'html' => 'components/header/component.html'],
                ['name' => 'footer', 'html' => 'components/footer/component.html'],
            ],
        ]);

        File::ensureDirectoryExists($this->sourceRoot.'/components/header');
        File::ensureDirectoryExists($this->sourceRoot.'/components/footer');
        File::put($this->sourceRoot.'/components/header/component.html', '<header data-webu-menu="header"></header>');
        File::put($this->sourceRoot.'/components/footer/component.html', '<footer data-webu-section="webu_footer_01"></footer>');

        File::ensureDirectoryExists($this->sourceRoot.'/pages');
        $this->writeJson($this->sourceRoot.'/pages/login.json', [
            'file' => 'login.html',
            'components' => ['header', 'footer'],
        ]);
        $this->writeJson($this->sourceRoot.'/pages/account.json', [
            'file' => 'my-account.html',
            'components' => ['header', 'footer'],
        ]);

        File::put($this->sourceRoot.'/login.html', '<html><body><div class="auth-shell">missing auth anchors</div></body></html>');
        File::put($this->sourceRoot.'/my-account.html', '<html><body><div class="profile-page">missing account anchors</div></body></html>');
        File::put($this->sourceRoot.'/index.html', '<html><body><section data-webu-section="x"></section></body></html>');

        $result = app(TemplateImportContractService::class)->validateSourceRoot($this->sourceRoot);

        $this->assertTrue($result['valid']);
        $this->assertTrue(
            collect($result['warnings'])->contains(
                fn (string $warning): bool => str_contains($warning, 'Auth page blueprint (login.json)')
            )
        );
        $this->assertTrue(
            collect($result['warnings'])->contains(
                fn (string $warning): bool => str_contains($warning, 'Account page blueprint (account.json)')
            )
        );
    }

    public function test_validate_source_root_auth_and_account_page_blueprints_pass_with_template_runtime_tokens(): void
    {
        $this->writeJson($this->sourceRoot.'/template.json', [
            'name' => 'Auth Account Runtime Token OK Template',
            'pageBlueprintsPath' => 'pages/',
            'pages' => ['login', 'account'],
            'components' => [
                ['name' => 'header', 'html' => 'components/header/component.html'],
                ['name' => 'footer', 'html' => 'components/footer/component.html'],
            ],
        ]);

        File::ensureDirectoryExists($this->sourceRoot.'/components/header');
        File::ensureDirectoryExists($this->sourceRoot.'/components/footer');
        File::put($this->sourceRoot.'/components/header/component.html', '<header data-webu-menu="header"></header>');
        File::put($this->sourceRoot.'/components/footer/component.html', '<footer data-webu-section="webu_footer_01"></footer>');

        File::ensureDirectoryExists($this->sourceRoot.'/pages');
        $this->writeJson($this->sourceRoot.'/pages/login.json', [
            'file' => 'login.html',
            'components' => ['header', 'footer'],
        ]);
        $this->writeJson($this->sourceRoot.'/pages/account.json', [
            'file' => 'my-account.html',
            'components' => ['header', 'footer'],
        ]);

        File::put(
            $this->sourceRoot.'/login.html',
            '<html><body><div class="login_register_wrap"><div class="login_wrap"></div></div></body></html>'
        );
        File::put(
            $this->sourceRoot.'/my-account.html',
            '<html><body><div class="dashboard_menu"></div><div class="tab-content dashboard_content"></div></body></html>'
        );
        File::put($this->sourceRoot.'/index.html', '<html><body><section data-webu-section="x"></section></body></html>');

        $result = app(TemplateImportContractService::class)->validateSourceRoot($this->sourceRoot);

        $this->assertTrue($result['valid'], implode("\n", $result['errors']));
        $this->assertFalse(
            collect($result['warnings'])->contains(
                fn (string $warning): bool => str_contains($warning, 'Auth page blueprint (login.json)')
                    || str_contains($warning, 'Account page blueprint (account.json)')
            ),
            'Unexpected auth/account runtime anchor warnings were emitted.'
        );
    }

    /**
     * @param  array<int|string, mixed>  $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        $dir = dirname($path);
        File::ensureDirectoryExists($dir);
        File::put($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}
