import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
const docPath = path.join(ROOT, 'docs/architecture/UNIVERSAL_BOOKING_BUILDER_COMPONENTS_P5_F3_04.md');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS booking builder component coverage contracts', () => {
    it('keeps canonical booking discovery component keys and runtime marker hooks for services and bookings flows', () => {
        const cms = read(cmsPagePath);

        [
            'webu_svc_services_list_01',
            'webu_svc_staff_grid_01',
            'webu_svc_service_detail_01',
            'webu_svc_pricing_table_01',
            'webu_svc_faq_01',
            'webu_book_slots_01',
            'webu_book_booking_form_01',
            'webu_book_calendar_01',
            'webu_book_bookings_list_01',
            'webu_book_booking_manage_01',
            'webu_book_finance_summary_01',
        ].forEach((key) => expect(cms).toContain(key));

        [
            'data-webby-booking-services',
            'data-webby-booking-staff',
            'data-webby-booking-service-detail',
            'data-webby-booking-pricing-table',
            'data-webby-booking-faq',
            'data-webby-booking-slots',
            'data-webby-booking-form',
            'data-webby-booking-calendar',
            'data-webby-booking-bookings',
            'data-webby-booking-manage',
            'data-webby-booking-finance',
        ].forEach((marker) => expect(cms).toContain(marker));

        expect(cms).toContain('BUILDER_BOOKING_DISCOVERY_LIBRARY_SECTIONS');
        expect(cms).toContain('BOOKING_SECTION_CATEGORY');
        expect(cms).toContain('preview_state');
        expect(cms).toContain('skeleton_items');
    });

    it('keeps booking builder components behind module/project-type flags including scheduling and finance submodules', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('syntheticBookingSectionKeySet');
        expect(cms).toContain('syntheticBookingSchedulingSectionKeySet');
        expect(cms).toContain('syntheticBookingFinanceSectionKeySet');

        expect(cms).toContain('MODULE_BOOKING');
        expect(cms).toContain('MODULE_BOOKING_TEAM_SCHEDULING');
        expect(cms).toContain('MODULE_BOOKING_FINANCE');
        expect(cms).toContain('project_type_allowed');
        expect(cms).toContain('isModuleProjectTypeAllowed');
        expect(cms).toContain('builderSectionAvailabilityMatrix');
        expect(cms).toContain('isBuilderSectionAllowedByProjectTypeAvailabilityMatrix');

        expect(cms).toContain("key: 'booking'");
        expect(cms).toContain("key: 'booking_scheduling'");
        expect(cms).toContain("key: 'booking_finance'");
        expect(cms).toContain('requiredModules: [MODULE_BOOKING]');
        expect(cms).toContain('requiredModules: [MODULE_BOOKING, MODULE_BOOKING_TEAM_SCHEDULING]');
        expect(cms).toContain('requiredModules: [MODULE_BOOKING, MODULE_BOOKING_FINANCE]');
        expect(cms).toContain('BUILDER_UNIVERSAL_TAXONOMY_GROUP_LABELS');
        expect(cms).toContain("booking: { en: 'Booking Components'");
    });

    it('keeps booking synthetic preview placeholder and state wiring for builder visual editor', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('createSyntheticBookingPlaceholder');
        expect(cms).toContain('data-webu-builder-booking');
        expect(cms).toContain('applyBookingPreviewState');
        expect(cms).toContain('data-webu-role="booking-state-box"');
        expect(cms).toContain('data-webu-role="booking-component-data"');
        expect(cms).toContain("isSyntheticBookingSectionKey(normalized)");
        expect(cms).toContain('isSyntheticBookingSectionKey(normalizedSectionType)');
    });

    it('documents booking builder component project-type gating baseline for p5-f3-04', () => {
        const doc = read(docPath);

        expect(doc).toContain('P5-F3-04');
        expect(doc).toContain('Cms.tsx');
        expect(doc).toContain('project_type_allowed');
        expect(doc).toContain('isModuleProjectTypeAllowed');
        expect(doc).toContain('webu_svc_services_list_01');
        expect(doc).toContain('webu_svc_service_detail_01');
        expect(doc).toContain('webu_svc_pricing_table_01');
        expect(doc).toContain('webu_svc_faq_01');
        expect(doc).toContain('webu_book_slots_01');
        expect(doc).toContain('MODULE_BOOKING_TEAM_SCHEDULING');
        expect(doc).toContain('MODULE_BOOKING_FINANCE');
        expect(doc).toContain('P5-F5-02');
    });
});
