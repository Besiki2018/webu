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

describe('CMS portfolio builder component coverage contracts', () => {
    it('keeps canonical synthetic portfolio builder component keys and runtime preview markers', () => {
        const cms = read(cmsPagePath);

        [
            'webu_portfolio_projects_grid_01',
            'webu_portfolio_project_hero_01',
            'webu_portfolio_project_detail_01',
            'webu_portfolio_gallery_01',
            'webu_portfolio_metrics_01',
        ].forEach((key) => expect(cms).toContain(key));

        [
            'data-webby-portfolio-projects',
            'data-webby-portfolio-project',
            'data-webby-portfolio-project-detail',
            'data-webby-portfolio-gallery',
            'data-webby-portfolio-metrics',
        ].forEach((marker) => expect(cms).toContain(marker));

        expect(cms).toContain('BUILDER_PORTFOLIO_DISCOVERY_LIBRARY_SECTIONS');
        expect(cms).toContain('PORTFOLIO_SECTION_CATEGORY');
        expect(cms).toContain('isSyntheticPortfolioSectionKey');
        expect(cms).toContain('createSyntheticPortfolioPlaceholder');
        expect(cms).toContain('applyPortfolioPreviewState');
        expect(cms).toContain('data-webu-builder-portfolio');
        expect(cms).toContain('data-webu-role="portfolio-component-data"');
        expect(cms).toContain('data-webu-role="portfolio-skeleton-grid"');
    });

    it('keeps portfolio builder components behind portfolio module availability and project-type gates', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain('MODULE_PORTFOLIO');
        expect(cms).toContain('syntheticPortfolioSectionKeySet');
        expect(cms).toContain('isModuleProjectTypeAllowed');
        expect(cms).toContain('project_type_allowed');
        expect(cms).toContain('builderSectionAvailabilityMatrix');
        expect(cms).toContain('isBuilderSectionAllowedByProjectTypeAvailabilityMatrix');
        expect(cms).toContain("key: 'portfolio'");
        expect(cms).toContain('requiredModules: [MODULE_PORTFOLIO]');
        expect(cms).toContain('BUILDER_UNIVERSAL_TAXONOMY_GROUP_LABELS');
        expect(cms).toContain("portfolio: { en: 'Portfolio Components'");
    });

    it('documents p5-f4-01 portfolio module and builder component gating baseline', () => {
        const doc = readCurrentBuilderDocs();

        expect(doc).toContain('componentRegistry.ts');
        expect(doc).toContain('updatePipeline.ts');
        expect(doc).toContain('Cms.tsx');
        expect(doc).toContain('BuilderCanvas');
    });
});
