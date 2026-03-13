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

describe('CMS nav/footer builder coverage contracts', () => {
    it('keeps canonical standalone nav/footer general component keys and preview marker hooks', () => {
        const cms = read(cmsPagePath);

        [
            'webu_general_nav_logo_01',
            'webu_general_nav_menu_01',
            'webu_general_nav_search_01',
            'webu_general_nav_account_icon_01',
            'webu_general_footer_01',
        ].forEach((key) => expect(cms).toContain(key));

        [
            'data-webby-nav-logo',
            'data-webby-nav-menu',
            'data-webby-nav-search',
            'data-webby-nav-account-icon',
            'data-webby-footer-layout',
        ].forEach((marker) => expect(cms).toContain(marker));
    });

    it('keeps createGeneralPlaceholder and preview update branches for nav/footer components', () => {
        const cms = read(cmsPagePath);

        [
            "if (normalized === 'webu_general_nav_logo_01')",
            "if (normalized === 'webu_general_nav_menu_01')",
            "if (normalized === 'webu_general_nav_search_01')",
            "if (normalized === 'webu_general_nav_account_icon_01')",
            "if (normalized === 'webu_general_footer_01')",
            "if (normalizedSectionType === 'webu_general_nav_logo_01')",
            "if (normalizedSectionType === 'webu_general_nav_menu_01')",
            "if (normalizedSectionType === 'webu_general_nav_search_01')",
            "if (normalizedSectionType === 'webu_general_nav_account_icon_01')",
            "if (normalizedSectionType === 'webu_general_footer_01')",
        ].forEach((needle) => expect(cms).toContain(needle));

        [
            'data-webu-role="nav-menu-list"',
            'data-webu-role="nav-search-results"',
            'data-webu-role="nav-account-badge"',
            'data-webu-role="footer-link-columns"',
            'data-webu-role="footer-newsletter"',
        ].forEach((needle) => expect(cms).toContain(needle));
    });

    it('syncs gap-audit doc rows for nav/footer keys as equivalent coverage', () => {
        const doc = readCurrentBuilderDocs();

        expect(doc).toContain('componentRegistry.ts');
        expect(doc).toContain('updatePipeline.ts');
        expect(doc).toContain('schema-driven builder');
        expect(doc).toContain('BuilderCanvas');
    });
});
