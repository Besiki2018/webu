import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

import { readCurrentBuilderDocs } from './builderContractTestUtils';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS hotel builder component coverage contracts', () => {
    it('keeps canonical synthetic hotel builder component keys and runtime preview markers', () => {
        const cms = read(cmsPagePath);

        [
            'webu_hotel_room_grid_01',
            'webu_hotel_room_detail_01',
            'webu_hotel_room_availability_01',
            'webu_hotel_reservation_form_01',
        ].forEach((key) => expect(cms).toContain(key));

        [
            'data-webby-hotel-rooms',
            'data-webby-hotel-room',
            'data-webby-hotel-availability',
            'data-webby-hotel-reservation-form',
        ].forEach((marker) => expect(cms).toContain(marker));

        expect(cms).toContain('BUILDER_HOTEL_DISCOVERY_LIBRARY_SECTIONS');
        expect(cms).toContain('HOTEL_SECTION_CATEGORY');
        expect(cms).toContain('isSyntheticHotelSectionKey');
        expect(cms).toContain('createSyntheticHotelPlaceholder');
        expect(cms).toContain('applyHotelPreviewState');
        expect(cms).toContain('data-webu-builder-hotel');
        expect(cms).toContain('data-webu-role="hotel-component-data"');
        expect(cms).toContain('data-webu-role="hotel-skeleton-grid"');
        expect(cms).toContain('{{route.params.slug}}');
    });

    it('keeps hotel builder components behind hotel and booking gates where applicable', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('MODULE_HOTEL');
        expect(cms).toContain('syntheticHotelSectionKeySet');
        expect(cms).toContain('syntheticHotelReservationSectionKeySet');
        expect(cms).toContain('builderSectionAvailabilityMatrix');
        expect(cms).toContain('isBuilderSectionAllowedByProjectTypeAvailabilityMatrix');
        expect(cms).toContain("key: 'hotel'");
        expect(cms).toContain("key: 'hotel_reservation'");
        expect(cms).toContain('requiredModules: [MODULE_HOTEL]');
        expect(cms).toContain('requiredModules: [MODULE_HOTEL, MODULE_BOOKING]');
        expect(cms).toContain('BUILDER_UNIVERSAL_TAXONOMY_GROUP_LABELS');
        expect(cms).toContain("hotel: { en: 'Hotel Components'");
    });

    it('documents p5-f4-04 hotel module and builder component gating baseline', () => {
        const doc = readCurrentBuilderDocs();

        expect(doc).toContain('componentRegistry.ts');
        expect(doc).toContain('updatePipeline.ts');
        expect(doc).toContain('Cms.tsx');
        expect(doc).toContain('BuilderCanvas');
    });
});
