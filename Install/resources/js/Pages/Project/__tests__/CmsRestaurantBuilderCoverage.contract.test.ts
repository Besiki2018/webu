import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
const docPath = path.join(ROOT, 'docs/architecture/UNIVERSAL_RESTAURANT_MODULE_COMPONENTS_P5_F4_03.md');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS restaurant builder component coverage contracts', () => {
    it('keeps canonical synthetic restaurant builder component keys and runtime preview markers', () => {
        const cms = read(cmsPagePath);

        [
            'webu_rest_menu_categories_01',
            'webu_rest_menu_items_01',
            'webu_rest_reservation_slots_01',
            'webu_rest_reservation_form_01',
        ].forEach((key) => expect(cms).toContain(key));

        [
            'data-webby-restaurant-menu-categories',
            'data-webby-restaurant-menu-items',
            'data-webby-restaurant-reservation-slots',
            'data-webby-restaurant-reservation-form',
        ].forEach((marker) => expect(cms).toContain(marker));

        expect(cms).toContain('BUILDER_RESTAURANT_DISCOVERY_LIBRARY_SECTIONS');
        expect(cms).toContain('RESTAURANT_SECTION_CATEGORY');
        expect(cms).toContain('isSyntheticRestaurantSectionKey');
        expect(cms).toContain('createSyntheticRestaurantPlaceholder');
        expect(cms).toContain('applyRestaurantPreviewState');
        expect(cms).toContain('data-webu-builder-restaurant');
        expect(cms).toContain('data-webu-role="restaurant-component-data"');
        expect(cms).toContain('data-webu-role="restaurant-skeleton-grid"');
    });

    it('keeps restaurant builder components behind restaurant and booking gates where applicable', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('MODULE_RESTAURANT');
        expect(cms).toContain('syntheticRestaurantSectionKeySet');
        expect(cms).toContain('syntheticRestaurantReservationSectionKeySet');
        expect(cms).toContain('builderSectionAvailabilityMatrix');
        expect(cms).toContain('isBuilderSectionAllowedByProjectTypeAvailabilityMatrix');
        expect(cms).toContain("key: 'restaurant'");
        expect(cms).toContain("key: 'restaurant_reservation'");
        expect(cms).toContain('requiredModules: [MODULE_RESTAURANT]');
        expect(cms).toContain('requiredModules: [MODULE_RESTAURANT, MODULE_BOOKING]');
        expect(cms).toContain('BUILDER_UNIVERSAL_TAXONOMY_GROUP_LABELS');
        expect(cms).toContain("restaurant: { en: 'Restaurant Components'");
    });

    it('documents p5-f4-03 restaurant module and builder component gating baseline', () => {
        const doc = read(docPath);

        expect(doc).toContain('P5-F4-03');
        expect(doc).toContain('CmsModuleRegistryService');
        expect(doc).toContain('CmsProjectTypeModuleFeatureFlagService');
        expect(doc).toContain('TemplateMetadataNormalizerService');
        expect(doc).toContain('Cms.tsx');
        expect(doc).toContain('MODULE_RESTAURANT');
        expect(doc).toContain('project_type_allowed');
        expect(doc).toContain('webu_rest_reservation_form_01');
        expect(doc).toContain('data-webby-restaurant-reservation-form');
        expect(doc).toContain('MODULE_BOOKING');
        expect(doc).toContain('P5-F4-04');
    });
});
