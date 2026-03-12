import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
const summaryDocPath = path.join(ROOT, 'docs/qa/CMS_PHASE2_AUTH_ACCOUNT_ORDERS_C6_COMPLETION_SUMMARY.md');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS ecommerce C6 auth/account/orders builder contracts', () => {
    it('keeps auth scaffold controls and backend settings toggle inheritance hooks', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('webu_ecom_auth_01');
        expect(cms).toContain('use_backend_auth_settings');
        expect(cms).toContain('show_register_tab');
        expect(cms).toContain('show_otp');
        expect(cms).toContain('show_social');
        expect(cms).toContain('resolveEcomAuthBackendFeatureToggles');
        expect(cms).toContain('allow_register');
        expect(cms).toContain('otp_enabled');
        expect(cms).toContain('social_login_enabled');
        expect(cms).toContain('ecom-auth-tab-register');
        expect(cms).toContain('ecom-auth-tab-otp');
        expect(cms).toContain('ecom-auth-social');
        expect(cms).toContain('ecom-auth-backend-settings-note');
        expect(cms).toContain("mode === 'otp'");
        expect(cms).toContain('OTP/social/register visibility inherits backend auth settings.');
    });

    it('keeps account/orders/order-detail unauthorized fallback preview state controls and state resolver support', () => {
        const cms = read(cmsPagePath);

        ['webu_ecom_account_dashboard_01', 'webu_ecom_account_profile_01', 'webu_ecom_orders_list_01', 'webu_ecom_order_detail_01'].forEach((key) => {
            expect(cms).toContain(key);
        });

        ['unauthorized_title', 'unauthorized_description', 'unauthorized_cta_label', 'unauthorized_cta_url'].forEach((field) => {
            expect(cms).toContain(field);
        });

        expect(cms).toContain("data-webu-role-state=\"unauthorized\"");
        expect(cms).toContain('unauthorizedNode?: HTMLElement | null');
        expect(cms).toContain("const unauthorizedMode = normalizedState === 'unauthorized'");
        expect(cms).toContain('applyEcomPreviewState({');
        expect(cms).toContain('unauthorizedNode,');
        expect(cms).toContain('includeUnauthorized: true');
        expect(cms).toContain('/account/login');
    });

    it('keeps account profile scaffold with profile field rows and save/preferences toggles', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('webu_ecom_account_profile_01');
        expect(cms).toContain('data-webby-ecommerce-account-profile');
        expect(cms).toContain('show_phone_field');
        expect(cms).toContain('show_marketing_opt_in');
        expect(cms).toContain('show_addresses_summary');
        expect(cms).toContain('save_label');
        expect(cms).toContain('data-webu-role="ecom-profile-row-name"');
        expect(cms).toContain('data-webu-role="ecom-profile-marketing-optin-row"');
        expect(cms).toContain('data-webu-role="ecom-profile-save-button"');
        expect(cms).toContain("if (normalized === 'webu_ecom_account_profile_01')");
        expect(cms).toContain("if (normalizedSectionType === 'webu_ecom_account_profile_01')");
        expect(cms).toContain('Login required to manage profile');
    });

    it('keeps account security scaffold with unauthorized fallback and preview toggle roles', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('webu_ecom_account_security_01');
        expect(cms).toContain('data-webby-ecommerce-account-security');
        expect(cms).toContain('show_password_panel');
        expect(cms).toContain('show_two_factor_panel');
        expect(cms).toContain('two_factor_enabled');
        expect(cms).toContain('active_sessions_count');
        expect(cms).toContain('trusted_devices_count');
        expect(cms).toContain('data-webu-role="ecom-security-2fa-status"');
        expect(cms).toContain('data-webu-role="ecom-security-sessions-list"');
        expect(cms).toContain('data-webu-role="ecom-security-devices-list"');
        expect(cms).toContain('data-webu-role="ecom-security-tips-list"');
        expect(cms).toContain("if (normalized === 'webu_ecom_account_security_01')");
        expect(cms).toContain("if (normalizedSectionType === 'webu_ecom_account_security_01')");
        expect(cms).toContain('Login required to manage security');
    });

    it('keeps C6 completion summary doc references for roadmap lines and evidence scope note', () => {
        const doc = read(summaryDocPath);

        expect(doc).toContain('PROJECT_ROADMAP_TASKS_KA.md:398');
        expect(doc).toContain('PROJECT_ROADMAP_TASKS_KA.md:401');
        expect(doc).toContain('CmsEcommerceCustomerAuthAccountOrdersC6.contract.test.ts');
        expect(doc).toContain('TemplateStorefrontE2eFlowMatrixSmokeTest.php');
        expect(doc).toContain('Scope Note');
    });
});
