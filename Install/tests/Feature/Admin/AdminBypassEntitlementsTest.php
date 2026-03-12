<?php

namespace Tests\Feature\Admin;

use App\Models\Plan;
use App\Models\User;
use App\Services\BuildCreditService;
use App\Services\EntitlementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminBypassEntitlementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_bypass_unlocks_features_and_quotas_even_on_restrictive_plan(): void
    {
        $plan = Plan::factory()->create([
            'monthly_build_credits' => 0,
            'max_projects' => 0,
            'enable_subdomains' => false,
            'enable_custom_domains' => false,
            'allow_private_visibility' => false,
            'enable_firebase' => false,
            'allow_user_firebase_config' => false,
            'enable_file_storage' => false,
            'enable_booking_prepayment' => false,
            'enable_ecommerce' => false,
            'enable_booking' => false,
            'enable_online_payments' => false,
            'enable_installments' => false,
            'enable_shipping' => false,
            'enable_custom_fonts' => false,
            'max_storage_mb' => 0,
        ]);

        $admin = User::factory()
            ->admin()
            ->withPlan($plan)
            ->create([
                'build_credits' => 0,
            ]);

        $this->assertTrue($admin->hasAdminBypass());
        $this->assertTrue($admin->hasUnlimitedCredits());
        $this->assertTrue($admin->canCreateMoreProjects());
        $this->assertTrue($admin->canUseSubdomains());
        $this->assertTrue($admin->canCreateMoreSubdomains());
        $this->assertTrue($admin->canUseCustomDomains());
        $this->assertTrue($admin->canCreateMoreCustomDomains());
        $this->assertTrue($admin->canUsePrivateVisibility());
        $this->assertTrue($admin->canUseFirebase());
        $this->assertTrue($admin->canUseOwnFirebaseConfig());
        $this->assertTrue($admin->canUseFileStorage());
        $this->assertTrue($admin->canUseBookingPrepayment());
        $this->assertTrue($admin->canUseEcommerce());
        $this->assertTrue($admin->canUseBooking());
        $this->assertTrue($admin->canUseOnlinePayments());
        $this->assertTrue($admin->canUseInstallments());
        $this->assertTrue($admin->canUseShipping());
        $this->assertTrue($admin->canUseCustomFonts());
        $this->assertTrue($admin->canUseEcommerceInventory());
        $this->assertTrue($admin->canUseEcommerceAccounting());
        $this->assertTrue($admin->canUseEcommerceRsIntegration());
        $this->assertTrue($admin->canUseBookingTeamScheduling());
        $this->assertTrue($admin->canUseBookingFinance());
        $this->assertTrue($admin->canUseBookingAdvancedCalendar());
        $this->assertSame(-1, $admin->getRemainingStorageBytes());
    }

    public function test_admin_bypass_is_respected_by_entitlement_and_build_credit_services(): void
    {
        $admin = User::factory()->admin()->create([
            'plan_id' => null,
            'build_credits' => 0,
        ]);

        $entitlements = app(EntitlementService::class);
        $buildCredits = app(BuildCreditService::class);

        $this->assertTrue($entitlements->allows($admin, EntitlementService::FEATURE_ECOMMERCE));
        $this->assertTrue($entitlements->allows($admin, EntitlementService::FEATURE_BOOKING));

        $result = $buildCredits->canPerformBuild($admin);
        $this->assertTrue($result['allowed']);
        $this->assertNull($result['reason']);
    }
}

