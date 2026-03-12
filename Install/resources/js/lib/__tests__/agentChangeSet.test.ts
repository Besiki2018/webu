import { describe, expect, it } from 'vitest';
import {
    changeSetHasUnsyncedOperations,
    extractPreviewLayoutOverrides,
    getBuilderSyncableChangeSet,
    resolveChangeSetScopeLabel,
} from '@/lib/agentChangeSet';

describe('agentChangeSet helpers', () => {
    it('keeps only builder-syncable operations for iframe sync', () => {
        const changeSet = {
            operations: [
                { op: 'updateSection', sectionId: 'hero-1', patch: { title: 'New' } },
                { op: 'updateGlobalComponent', component: 'header', patch: { layout_variant: 'header-4' } },
            ],
        };

        expect(getBuilderSyncableChangeSet(changeSet)).toEqual({
            operations: [
                { op: 'updateSection', sectionId: 'hero-1', patch: { title: 'New' } },
            ],
        });
        expect(changeSetHasUnsyncedOperations(changeSet)).toBe(true);
    });

    it('extracts preview layout overrides from global header/footer updates', () => {
        const changeSet = {
            operations: [
                { op: 'updateGlobalComponent', component: 'header', patch: { layout_variant: 'header-5' } },
                { op: 'updateGlobalComponent', component: 'footer', patch: { variant: 'footer-3' } },
            ],
        };

        expect(extractPreviewLayoutOverrides(changeSet)).toEqual({
            header_variant: 'header-5',
            footer_variant: 'footer-3',
        });
    });

    it('reports global header scope instead of home-page-only for global changes', () => {
        const labels = {
            homePage: 'Home page',
            homePageOnly: 'Home page only',
            page: (slug: string) => `Page: ${slug}`,
            siteWide: 'Site-wide changes',
            siteWideHeader: 'Site-wide header',
            siteWideFooter: 'Site-wide footer',
            siteWideTheme: 'Site-wide theme',
        };

        expect(resolveChangeSetScopeLabel({
            operations: [
                { op: 'updateGlobalComponent', component: 'header', patch: { layout_variant: 'header-6' } },
            ],
        }, 'home', labels)).toBe('Site-wide header');

        expect(resolveChangeSetScopeLabel({
            operations: [
                { op: 'updateSection', sectionId: 'hero-1', patch: { title: 'New' } },
                { op: 'updateGlobalComponent', component: 'header', patch: { layout_variant: 'header-6' } },
            ],
        }, 'home', labels)).toBe('Home page + Site-wide header');
    });
});
