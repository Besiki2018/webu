<?php

namespace Tests\Unit;

use Tests\TestCase;


/** @group docs-sync */
class MinimalOpenApiBaseModulesDeliverableTest extends TestCase
{
    public function test_minimal_openapi_docs_exist_for_source_spec_base_modules_deliverable(): void
    {
        $roadmapPath = base_path('../PROJECT_ROADMAP_TASKS_KA.md');
        $readmePath = base_path('docs/openapi/README.md');
        $publicCorePath = base_path('docs/openapi/webu-public-core-minimal.v1.openapi.yaml');
        $authCustomersPath = base_path('docs/openapi/webu-auth-customers-minimal.v1.openapi.yaml');
        $ecommercePath = base_path('docs/openapi/webu-ecommerce-minimal.v1.openapi.yaml');
        $servicesBookingPath = base_path('docs/openapi/webu-services-booking-minimal.v1.openapi.yaml');
        $webRoutesPath = base_path('routes/web.php');
        $authRoutesPath = base_path('routes/auth.php');

        foreach ([
            $roadmapPath,
            $readmePath,
            $publicCorePath,
            $authCustomersPath,
            $ecommercePath,
            $servicesBookingPath,
            $webRoutesPath,
            $authRoutesPath,
        ] as $path) {
            $this->assertFileExists($path);
        }

        $roadmap = File::get($roadmapPath);
        $readme = File::get($readmePath);
        $publicCore = File::get($publicCorePath);
        $authCustomers = File::get($authCustomersPath);
        $ecommerce = File::get($ecommercePath);
        $servicesBooking = File::get($servicesBookingPath);
        $webRoutes = File::get($webRoutesPath);
        $authRoutes = File::get($authRoutesPath);

        // Source-spec deliverable references.
        $this->assertStringContainsString('# 17) Deliverables', $roadmap);
        $this->assertStringContainsString('3) Minimal OpenAPI docs for base modules:', $roadmap);
        $this->assertStringContainsString('- public settings, pages, media', $roadmap);
        $this->assertStringContainsString('- auth, customers', $roadmap);
        $this->assertStringContainsString('- ecommerce', $roadmap);
        $this->assertStringContainsString('- services + bookings', $roadmap);

        // Index/readme references all module docs.
        $this->assertStringContainsString('webu-public-core-minimal.v1.openapi.yaml', $readme);
        $this->assertStringContainsString('webu-auth-customers-minimal.v1.openapi.yaml', $readme);
        $this->assertStringContainsString('webu-ecommerce-minimal.v1.openapi.yaml', $readme);
        $this->assertStringContainsString('webu-services-booking-minimal.v1.openapi.yaml', $readme);

        foreach ([$publicCore, $authCustomers, $ecommerce, $servicesBooking] as $doc) {
            $this->assertStringContainsString('openapi: 3.0.3', $doc);
            $this->assertStringContainsString('paths:', $doc);
        }

        // Public core paths and route correspondence.
        $this->assertStringContainsString('/public/sites/resolve:', $publicCore);
        $this->assertStringContainsString('/public/sites/{site}/settings:', $publicCore);
        $this->assertStringContainsString('/public/sites/{site}/pages/{slug}:', $publicCore);
        $this->assertStringContainsString('/public/sites/{site}/forms/{key}/submit:', $publicCore);
        $this->assertStringContainsString('/public/sites/{site}/assets/{path}:', $publicCore);
        $this->assertStringContainsString('/panel/sites/{site}/pages:', $publicCore);
        $this->assertStringContainsString('/panel/sites/{site}/media/upload:', $publicCore);
        $this->assertStringContainsString("Route::get('/{site}/settings'", $webRoutes);
        $this->assertStringContainsString("Route::get('/{site}/pages/{slug}'", $webRoutes);
        $this->assertStringContainsString("Route::post('/{site}/forms/{key}/submit'", $webRoutes);
        $this->assertStringContainsString("Route::get('/{site}/assets/{path}'", $webRoutes);
        $this->assertStringContainsString("Route::post('/pages/{page}/publish'", $webRoutes);
        $this->assertStringContainsString("Route::post('/media/upload'", $webRoutes);

        // Auth/customers routes and note about customer JSON endpoint baseline.
        $this->assertStringContainsString('/login:', $authCustomers);
        $this->assertStringContainsString('/register:', $authCustomers);
        $this->assertStringContainsString('/logout:', $authCustomers);
        $this->assertStringContainsString('/auth/{provider}:', $authCustomers);
        $this->assertStringContainsString('/panel/sites/{site}/booking/customers/search:', $authCustomers);
        $this->assertStringContainsString('/customers/me', $authCustomers);
        $this->assertStringContainsString("Route::post('login'", $authRoutes);
        $this->assertStringContainsString("Route::post('register'", $authRoutes);
        $this->assertStringContainsString("Route::post('logout'", $authRoutes);
        $this->assertStringContainsString("Route::get('auth/{provider}'", $authRoutes);
        $this->assertStringContainsString("Route::get('/booking/customers/search'", $webRoutes);

        // Ecommerce public storefront paths and route correspondence.
        foreach ([
            '/public/sites/{site}/ecommerce/payment-options:',
            '/public/sites/{site}/ecommerce/products:',
            '/public/sites/{site}/ecommerce/products/{slug}:',
            '/public/sites/{site}/ecommerce/carts:',
            '/public/sites/{site}/ecommerce/carts/{cart}/items:',
            '/public/sites/{site}/ecommerce/carts/{cart}/coupon:',
            '/public/sites/{site}/ecommerce/carts/{cart}/shipping/options:',
            '/public/sites/{site}/ecommerce/carts/{cart}/checkout:',
            '/public/sites/{site}/ecommerce/orders/{order}/payments/start:',
        ] as $path) {
            $this->assertStringContainsString($path, $ecommerce);
        }
        $this->assertStringContainsString("Route::post('/{site}/ecommerce/carts/{cart}/coupon'", $webRoutes);
        $this->assertStringContainsString("Route::delete('/{site}/ecommerce/carts/{cart}/coupon'", $webRoutes);
        $this->assertStringContainsString("Route::post('/{site}/ecommerce/carts/{cart}/checkout'", $webRoutes);

        // Services + booking public/panel paths and route correspondence.
        foreach ([
            '/public/sites/{site}/booking/services:',
            '/public/sites/{site}/booking/slots:',
            '/public/sites/{site}/booking/bookings:',
            '/panel/sites/{site}/booking/services:',
            '/panel/sites/{site}/booking/staff:',
            '/panel/sites/{site}/booking/calendar:',
            '/panel/sites/{site}/booking/bookings:',
            '/panel/sites/{site}/booking/bookings/{booking}/status:',
            '/panel/sites/{site}/booking/bookings/{booking}/reschedule:',
            '/panel/sites/{site}/booking/bookings/{booking}/cancel:',
        ] as $path) {
            $this->assertStringContainsString($path, $servicesBooking);
        }
        $this->assertStringContainsString("Route::get('/{site}/booking/services'", $webRoutes);
        $this->assertStringContainsString("Route::post('/{site}/booking/bookings'", $webRoutes);
        $this->assertStringContainsString("Route::get('/booking/services'", $webRoutes);
        $this->assertStringContainsString("Route::post('/booking/bookings/{booking}/status'", $webRoutes);
    }
}
