<?php

namespace Tests\Unit;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class BuilderBookingServicesRuntimeHooksContractTest extends TestCase
{
    public function test_builder_booking_runtime_script_exposes_services_component_endpoints_selectors_and_mounts(): void
    {
        $path = base_path('app/Services/BuilderService.php');
        $this->assertFileExists($path);

        $source = File::get($path);

        foreach ([
            "'service_detail_url_pattern' =>",
            "'pricing_url' =>",
            "'faq_url' =>",
            "'services_selector' => '[data-webby-booking-services]'",
            "'service_detail_selector' => '[data-webby-booking-service-detail]'",
            "'pricing_table_selector' => '[data-webby-booking-pricing-table]'",
            "'faq_selector' => '[data-webby-booking-faq]'",
            'function getService(slug) {',
            'function mountServicesWidget(container, options) {',
            'function mountServiceDetailWidget(container, options) {',
            'function mountPricingTableWidget(container, options) {',
            'function mountFaqWidget(container, options) {',
            'document.querySelectorAll(servicesSelector)',
            'document.querySelectorAll(serviceDetailSelector)',
            'document.querySelectorAll(pricingTableSelector)',
            'document.querySelectorAll(faqSelector)',
            'getService: getService,',
            'mountServicesWidget: mountServicesWidget,',
            'mountServiceDetailWidget: mountServiceDetailWidget,',
            'mountPricingTableWidget: mountPricingTableWidget,',
            'mountFaqWidget: mountFaqWidget,',
            '[data-webby-booking-services]',
            '[data-webby-booking-service-detail]',
            '[data-webby-booking-pricing-table]',
            '[data-webby-booking-faq]',
        ] as $needle) {
            $this->assertStringContainsString($needle, $source);
        }
    }
}
