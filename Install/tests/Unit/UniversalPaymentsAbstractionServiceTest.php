<?php

namespace Tests\Unit;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** @group docs-sync */
class UniversalPaymentsAbstractionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        SystemSetting::clearCache();
        SystemSetting::set('installation_completed', true, 'boolean', 'system');
    }

    public function test_it_builds_shared_provider_and_payment_contracts_for_ecommerce_and_booking(): void
    {
        $site = $this->makeSite();

        SitePaymentGatewaySetting::query()->create([
            'site_id' => $site->id,
            'provider_slug' => 'bank-of-georgia',
            'availability' => SitePaymentGatewaySetting::AVAILABILITY_ENABLED,
            'config' => ['sandbox' => true, 'merchant_id' => 'm-1'],
        ]);
        SitePaymentGatewaySetting::query()->create([
            'site_id' => $site->id,
            'provider_slug' => 'fleet',
            'availability' => SitePaymentGatewaySetting::AVAILABILITY_DISABLED,
            'config' => ['sandbox' => true],
        ]);

        $countsBefore = [
            'site_payment_gateway_settings' => DB::table('site_payment_gateway_settings')->count(),
        ];

        $service = app(UniversalPaymentsAbstractionService::class);

        $gatewaySnapshot = $service->gatewaySettingsSnapshot($site);
        $this->assertSame('universal_payments_abstraction', data_get($gatewaySnapshot, 'schema.name'));
        $this->assertSame(1, data_get($gatewaySnapshot, 'schema.version'));
        $this->assertSame('P5-F2-04', data_get($gatewaySnapshot, 'schema.task'));
        $this->assertSame(2, data_get($gatewaySnapshot, 'counts.gateways'));
        $this->assertSame(1, data_get($gatewaySnapshot, 'counts.enabled'));
        $this->assertSame(1, data_get($gatewaySnapshot, 'counts.disabled'));
        $this->assertNull(data_get($gatewaySnapshot, 'gateways.0.config'));

        $providerContract = $service->providerOptionsContract($site, 'ecommerce', 'GEL', [
            [
                'slug' => 'manual',
                'name' => 'Manual',
                'description' => 'Offline payment',
                'supports_installment' => false,
                'modes' => ['full'],
            ],
            [
                'slug' => 'bank-of-georgia',
                'name' => 'Bank of Georgia',
                'description' => 'Card and installments',
                'supports_installment' => true,
                'modes' => ['full', 'installment'],
            ],
        ], ['flow' => 'checkout']);

        $this->assertSame('ecommerce', data_get($providerContract, 'context.domain'));
        $this->assertSame('checkout', data_get($providerContract, 'context.flow'));
        $this->assertSame('GEL', data_get($providerContract, 'currency'));
        $this->assertSame(2, data_get($providerContract, 'provider_count'));
        $this->assertSame('manual', data_get($providerContract, 'providers.0.slug'));
        $this->assertSame('manual', data_get($providerContract, 'providers.0.universal_provider.provider_key'));
        $this->assertFalse((bool) data_get($providerContract, 'providers.0.universal_provider.capabilities.installment'));
        $this->assertTrue((bool) data_get($providerContract, 'providers.1.universal_provider.capabilities.installment'));

        $ecommerceOrder = new EcommerceOrder([
            'site_id' => $site->id,
            'order_number' => 'ORD-P5F204-1',
            'payment_status' => 'pending',
            'currency' => 'GEL',
        ]);
        $ecommerceOrder->setAttribute('id', 501);
        $ecommerceOrder->exists = true;

        $ecommercePayment = new EcommerceOrderPayment([
            'site_id' => $site->id,
            'order_id' => 501,
            'provider' => 'bank-of-georgia',
            'status' => 'captured',
            'method' => 'card',
            'transaction_reference' => 'BOG-ORDER-901',
            'amount' => '125.50',
            'currency' => 'GEL',
            'is_installment' => true,
            'installment_plan_json' => ['months' => 12],
            'raw_payload_json' => ['gateway' => 'bog'],
            'processed_at' => now()->subMinute(),
            'created_at' => now()->subMinutes(2),
            'updated_at' => now()->subMinute(),
        ]);
        $ecommercePayment->setAttribute('id', 901);
        $ecommercePayment->exists = true;
        $ecommercePayment->setRelation('order', $ecommerceOrder);

        $ecommerceUniversal = $service->normalizeEcommercePayment($ecommercePayment);
        $this->assertSame('ecommerce', data_get($ecommerceUniversal, 'domain'));
        $this->assertSame('ecommerce_order_payments', data_get($ecommerceUniversal, 'source.table'));
        $this->assertSame(901, data_get($ecommerceUniversal, 'source.id'));
        $this->assertSame('captured', data_get($ecommerceUniversal, 'status'));
        $this->assertSame('captured', data_get($ecommerceUniversal, 'lifecycle_state'));
        $this->assertTrue((bool) data_get($ecommerceUniversal, 'flags.is_installment'));
        $this->assertSame('ORD-P5F204-1', data_get($ecommerceUniversal, 'meta_json.domain_resource.order_number'));
        $this->assertNull(data_get($ecommerceUniversal, 'raw_payload_json'));

        $booking = new Booking([
            'site_id' => $site->id,
            'booking_number' => 'BK-P5F204-1',
            'status' => Booking::STATUS_CONFIRMED,
            'customer_name' => 'Besik Example',
            'customer_email' => 'besik@example.test',
            'currency' => 'GEL',
            'starts_at' => now()->addDay(),
        ]);
        $booking->setAttribute('id', 701);
        $booking->exists = true;

        $invoice = new BookingInvoice([
            'site_id' => $site->id,
            'booking_id' => 701,
            'invoice_number' => 'INV-P5F204-1',
            'status' => 'issued',
            'currency' => 'GEL',
            'grand_total' => '90.00',
            'paid_total' => '30.00',
            'outstanding_total' => '60.00',
        ]);
        $invoice->setAttribute('id', 702);
        $invoice->exists = true;

        $bookingPayment = new BookingPayment([
            'site_id' => $site->id,
            'booking_id' => 701,
            'invoice_id' => 702,
            'provider' => 'manual',
            'status' => 'paid',
            'method' => 'cash',
            'transaction_reference' => 'BKPAY-703',
            'amount' => '30.00',
            'currency' => 'GEL',
            'is_prepayment' => true,
            'processed_at' => now()->subMinutes(10),
            'raw_payload_json' => ['channel' => 'frontdesk'],
            'meta_json' => ['collector' => 'admin'],
            'created_at' => now()->subMinutes(12),
            'updated_at' => now()->subMinutes(10),
        ]);
        $bookingPayment->setAttribute('id', 703);
        $bookingPayment->exists = true;
        $bookingPayment->setRelation('booking', $booking);
        $bookingPayment->setRelation('invoice', $invoice);

        $bookingUniversal = $service->normalizeBookingPayment($bookingPayment, ['include_raw_payload' => true]);
        $this->assertSame('booking', data_get($bookingUniversal, 'domain'));
        $this->assertSame('booking_payments', data_get($bookingUniversal, 'source.table'));
        $this->assertSame('captured', data_get($bookingUniversal, 'lifecycle_state'));
        $this->assertTrue((bool) data_get($bookingUniversal, 'flags.is_prepayment'));
        $this->assertFalse((bool) data_get($bookingUniversal, 'flags.is_installment'));
        $this->assertSame('BK-P5F204-1', data_get($bookingUniversal, 'meta_json.domain_resource.booking_number'));
        $this->assertSame('INV-P5F204-1', data_get($bookingUniversal, 'meta_json.domain_resource.invoice_number'));
        $this->assertSame('frontdesk', data_get($bookingUniversal, 'raw_payload_json.channel'));

        $this->assertSame($countsBefore, [
            'site_payment_gateway_settings' => DB::table('site_payment_gateway_settings')->count(),
        ]);
    }

    public function test_architecture_doc_and_consumers_lock_p5_f2_04_payments_abstraction_contract(): void
    {
        $docPath = base_path('docs/architecture/UNIVERSAL_PAYMENTS_ABSTRACTION_P5_F2_04.md');
        $this->assertFileExists($docPath);

        $doc = File::get($docPath);
        $ecommerceSource = File::get(base_path('app/Ecommerce/Services/EcommercePublicStorefrontService.php'));
        $bookingSource = File::get(base_path('app/Booking/Services/BookingFinanceService.php'));
        $registrySource = File::get(base_path('app/Cms/Services/CmsModuleRegistryService.php'));

        $this->assertStringContainsString('P5-F2-04', $doc);
        $this->assertStringContainsString('UniversalPaymentsAbstractionService', $doc);
        $this->assertStringContainsString('EcommercePublicStorefrontService', $doc);
        $this->assertStringContainsString('BookingFinanceService', $doc);
        $this->assertStringContainsString('providerOptionsContract', $doc);
        $this->assertStringContainsString('normalizeEcommercePayment', $doc);
        $this->assertStringContainsString('normalizeBookingPayment', $doc);
        $this->assertStringContainsString('gatewaySettingsSnapshot', $doc);
        $this->assertStringContainsString('implemented = true', $doc);

        $this->assertStringContainsString('UniversalPaymentsAbstractionService', $ecommerceSource);
        $this->assertStringContainsString('providerOptionsContract(', $ecommerceSource);
        $this->assertStringContainsString('normalizeEcommercePayment(', $ecommerceSource);
        $this->assertStringContainsString('universal_payment_options', $ecommerceSource);

        $this->assertStringContainsString('UniversalPaymentsAbstractionService', $bookingSource);
        $this->assertStringContainsString('normalizeBookingPayment(', $bookingSource);
        $this->assertStringContainsString('universal_payment', $bookingSource);

        $this->assertStringContainsString("public const MODULE_PAYMENTS = 'payments';", $registrySource);
        $this->assertStringContainsString("'implemented' => true", $registrySource);
    }

    private function makeSite(): Site
    {
        $project = Project::factory()->create();

        $site = $project->fresh()->site;
        $this->assertInstanceOf(Site::class, $site);

        return $site->fresh();
    }
}
