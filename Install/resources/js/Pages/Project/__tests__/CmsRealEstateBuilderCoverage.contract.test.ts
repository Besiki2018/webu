import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { describe, expect, it } from 'vitest';

const TEST_DIR = path.dirname(fileURLToPath(import.meta.url));
const ROOT = path.resolve(TEST_DIR, '../../../../..');
const cmsPagePath = path.join(ROOT, 'resources/js/Pages/Project/Cms.tsx');
const docPath = path.join(ROOT, 'docs/architecture/UNIVERSAL_REAL_ESTATE_MODULE_COMPONENTS_P5_F4_02.md');

function read(filePath: string): string {
    return fs.readFileSync(filePath, 'utf8');
}

describe('CMS real-estate builder component coverage contracts', () => {
    it('keeps canonical synthetic real-estate builder component keys and runtime preview markers', () => {
        const cms = read(cmsPagePath);

        [
            'webu_realestate_property_grid_01',
            'webu_realestate_property_hero_01',
            'webu_realestate_property_detail_01',
            'webu_realestate_search_filters_01',
            'webu_realestate_map_01',
        ].forEach((key) => expect(cms).toContain(key));

        [
            'data-webby-realestate-properties',
            'data-webby-realestate-property',
            'data-webby-realestate-property-detail',
            'data-webby-realestate-search',
            'data-webby-realestate-map',
        ].forEach((marker) => expect(cms).toContain(marker));

        expect(cms).toContain('BUILDER_REAL_ESTATE_DISCOVERY_LIBRARY_SECTIONS');
        expect(cms).toContain('REAL_ESTATE_SECTION_CATEGORY');
        expect(cms).toContain('isSyntheticRealEstateSectionKey');
        expect(cms).toContain('createSyntheticRealEstatePlaceholder');
        expect(cms).toContain('applyRealEstatePreviewState');
        expect(cms).toContain('data-webu-builder-realestate');
        expect(cms).toContain('data-webu-role="realestate-component-data"');
        expect(cms).toContain('data-webu-role="realestate-skeleton-grid"');
        expect(cms).toContain('{{route.params.slug}}');
    });

    it('keeps real-estate builder components behind module availability and project-type gates', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('MODULE_REAL_ESTATE');
        expect(cms).toContain('syntheticRealEstateSectionKeySet');
        expect(cms).toContain('builderSectionAvailabilityMatrix');
        expect(cms).toContain('isBuilderSectionAllowedByProjectTypeAvailabilityMatrix');
        expect(cms).toContain("key: 'real_estate'");
        expect(cms).toContain('requiredModules: [MODULE_REAL_ESTATE]');
        expect(cms).toContain('BUILDER_UNIVERSAL_TAXONOMY_GROUP_LABELS');
        expect(cms).toContain("real_estate: { en: 'Real Estate Components'");
    });

    it('documents p5-f4-02 real-estate module and builder component gating baseline', () => {
        const doc = read(docPath);

        expect(doc).toContain('P5-F4-02');
        expect(doc).toContain('CmsModuleRegistryService');
        expect(doc).toContain('CmsProjectTypeModuleFeatureFlagService');
        expect(doc).toContain('TemplateMetadataNormalizerService');
        expect(doc).toContain('Cms.tsx');
        expect(doc).toContain('MODULE_REAL_ESTATE');
        expect(doc).toContain('project_type_allowed');
        expect(doc).toContain('webu_realestate_map_01');
        expect(doc).toContain('data-webby-realestate-map');
        expect(doc).toContain('{{route.params.slug}}');
        expect(doc).toContain('P5-F4-03');
    });
});
