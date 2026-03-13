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

describe('CMS universal component library activation contracts (P5-F5-01 / P5-F5-02)', () => {
    it('keeps canonical universal taxonomy group keys, order, labels, and grouped library rendering hooks', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('type BuilderUniversalTaxonomyGroupKey =');
        expect(cms).toContain('BUILDER_UNIVERSAL_TAXONOMY_GROUP_ORDER');
        expect(cms).toContain('BUILDER_UNIVERSAL_TAXONOMY_GROUP_LABELS');
        expect(cms).toContain('taxonomyGroupItems');
        expect(cms).toContain('BUILDER_UNIVERSAL_TAXONOMY_GROUP_ORDER.forEach');
        expect(cms).toContain('BUILDER_UNIVERSAL_TAXONOMY_GROUP_LABELS[groupKey]');

        [
            "general: { en: 'General Elements'",
            "ecommerce: { en: 'Ecommerce Components'",
            "booking: { en: 'Booking Components'",
            "portfolio: { en: 'Portfolio Components'",
            "real_estate: { en: 'Real Estate Components'",
            "restaurant: { en: 'Restaurant Components'",
            "hotel: { en: 'Hotel Components'",
            "design: { en: 'Design Components'",
        ].forEach((labelLine) => expect(cms).toContain(labelLine));
    });

    it('keeps a centralized project-type availability matrix for universal and vertical builder components', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('const builderSectionAvailabilityMatrix = useMemo(() => ([');
        expect(cms).toContain('isBuilderSectionAllowedByProjectTypeAvailabilityMatrix');
        expect(cms).toContain('return isBuilderSectionAllowedByProjectTypeAvailabilityMatrix(normalizedKey);');

        [
            "key: 'ecommerce'",
            "key: 'booking'",
            "key: 'booking_scheduling'",
            "key: 'booking_finance'",
            "key: 'portfolio'",
            "key: 'real_estate'",
            "key: 'restaurant'",
            "key: 'restaurant_reservation'",
            "key: 'hotel'",
            "key: 'hotel_reservation'",
        ].forEach((ruleKey) => expect(cms).toContain(ruleKey));

        [
            'requiredModules: [MODULE_ECOMMERCE]',
            'requiredModules: [MODULE_BOOKING]',
            'requiredModules: [MODULE_BOOKING, MODULE_BOOKING_TEAM_SCHEDULING]',
            'requiredModules: [MODULE_BOOKING, MODULE_BOOKING_FINANCE]',
            'requiredModules: [MODULE_PORTFOLIO]',
            'requiredModules: [MODULE_REAL_ESTATE]',
            'requiredModules: [MODULE_RESTAURANT]',
            'requiredModules: [MODULE_RESTAURANT, MODULE_BOOKING]',
            'requiredModules: [MODULE_HOTEL]',
            'requiredModules: [MODULE_HOTEL, MODULE_BOOKING]',
        ].forEach((requiredModules) => expect(cms).toContain(requiredModules));
    });

    it('keeps matrix enforcement aligned with backend availability and project-type gates', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('project_type_allowed');
        expect(cms).toContain('isModuleProjectTypeAllowed');
        expect(cms).toContain('isModuleAvailable');
        expect(cms).toContain('for (const rule of builderSectionAvailabilityMatrix)');
        expect(cms).toContain('for (const moduleKey of rule.requiredModules)');
        expect(cms).toContain('if (!isModuleProjectTypeAllowed(moduleKey))');
        expect(cms).toContain('if (!isModuleAvailable(moduleKey))');
    });

    it('documents the F5 universal component library activation baseline and deferred scope', () => {
        const doc = readCurrentBuilderDocs();

        expect(doc).toContain('componentRegistry.ts');
        expect(doc).toContain('updatePipeline.ts');
        expect(doc).toContain('Cms.tsx');
        expect(doc).toContain('BuilderCanvas');
    });
});
