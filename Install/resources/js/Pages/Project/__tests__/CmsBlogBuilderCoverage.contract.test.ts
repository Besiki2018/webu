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

describe('CMS blog builder component coverage contracts', () => {
    it('keeps canonical blog synthetic builder component keys and runtime preview marker hooks', () => {
        const cms = read(cmsPagePath);

        [
            'webu_blog_post_list_01',
            'webu_blog_post_detail_01',
            'webu_blog_category_list_01',
        ].forEach((key) => expect(cms).toContain(key));

        [
            'data-webby-blog-post-list',
            'data-webby-blog-post-detail',
            'data-webby-blog-category-list',
        ].forEach((marker) => expect(cms).toContain(marker));

        expect(cms).toContain('BUILDER_BLOG_DISCOVERY_LIBRARY_SECTIONS');
        expect(cms).toContain('BLOG_SECTION_CATEGORY');
        expect(cms).toContain('isSyntheticBlogSectionKey');
        expect(cms).toContain('createSyntheticBlogPlaceholder');
        expect(cms).toContain('applyBlogPreviewState');
        expect(cms).toContain('data-webu-builder-blog');
        expect(cms).toContain('data-webu-role="blog-component-data"');
        expect(cms).toContain('data-webu-role="blog-state-box"');
        expect(cms).toContain('data-webu-role="blog-skeleton-grid"');
    });

    it('keeps blog components grouped in universal taxonomy and preview-update wiring in builder runtime', () => {
        const cms = read(cmsPagePath);

        expect(cms).toContain("type BuilderUniversalTaxonomyGroupKey =");
        expect(cms).toContain("| 'blog'");
        expect(cms).toContain("blog: { en: 'Blog Components'");
        expect(cms).toContain('syntheticBlogSectionKeySet');
        expect(cms).toContain('BUILDER_BLOG_DISCOVERY_LIBRARY_SECTIONS.map((item) => normalizeSectionTypeKey(item.key))');
        expect(cms).toContain('isSyntheticBlogSectionKey(normalized)');
        expect(cms).toContain('isSyntheticBlogSectionKey(normalizedSectionType)');
        expect(cms).toContain("if (normalized === 'webu_blog_post_list_01')");
        expect(cms).toContain("if (normalizedSectionType === 'webu_blog_post_list_01')");
    });

    it('documents the current canonical builder architecture for blog coverage instead of the removed gap-audit baseline', () => {
        const doc = readCurrentBuilderDocs();

        expect(doc).toContain('componentRegistry.ts');
        expect(doc).toContain('updatePipeline.ts');
        expect(doc).toContain('schema-driven builder');
        expect(doc).toContain('BuilderCanvas');
    });
});
