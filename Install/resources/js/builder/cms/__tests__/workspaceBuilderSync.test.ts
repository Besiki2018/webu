import { describe, expect, it } from 'vitest';
import {
    buildWorkspaceStructureItemsFromCodePage,
    resolveWorkspaceBuilderActivePage,
    upsertWorkspaceBuilderCodePages,
} from '@/builder/cms/workspaceBuilderSync';

describe('workspaceBuilderSync', () => {
    it('resolves the active page from bridge page identity', () => {
        const pages = [
            {
                path: 'page-1',
                pageId: 1,
                slug: 'home',
                title: 'Home',
                revisionSource: null,
                sections: [],
            },
            {
                path: 'page-2',
                pageId: 2,
                slug: 'pricing',
                title: 'Pricing',
                revisionSource: null,
                sections: [],
            },
        ];

        expect(resolveWorkspaceBuilderActivePage(pages, {
            pageId: 2,
            pageSlug: 'pricing',
            pageTitle: 'Pricing',
        })?.slug).toBe('pricing');
    });

    it('does not silently fall back to the first page when a different page is explicitly targeted', () => {
        const pages = [
            {
                path: 'page-1',
                pageId: 1,
                slug: 'home',
                title: 'Home',
                revisionSource: null,
                sections: [],
            },
        ];

        expect(resolveWorkspaceBuilderActivePage(pages, {
            pageId: 99,
            pageSlug: 'pricing',
            pageTitle: 'Pricing',
        })).toBeNull();
    });

    it('builds structure items from a code page without hardcoding labels', () => {
        const items = buildWorkspaceStructureItemsFromCodePage({
            path: 'page-1',
            pageId: 1,
            slug: 'home',
            title: 'Home',
            revisionSource: 'draft',
            sections: [{
                localId: 'section-1',
                type: 'webu_general_hero_01',
                props: { headline: 'Hello' },
                propsText: '{"headline":"Hello"}',
            }],
        }, {
            getDisplayLabel: () => 'Hero',
            buildPreviewText: (props) => String(props.headline ?? ''),
        });

        expect(items).toEqual([{
            localId: 'section-1',
            sectionKey: 'webu_general_hero_01',
            label: 'Hero',
            previewText: 'Hello',
            props: { headline: 'Hello' },
        }]);
    });

    it('updates the matching page when a structure snapshot arrives', () => {
        const current = [{
            path: 'page-1',
            pageId: 1,
            slug: 'home',
            title: 'Home',
            revisionSource: null,
            sections: [],
        }];

        const next = upsertWorkspaceBuilderCodePages(current, {
            page: {
                pageId: 1,
                pageSlug: 'home',
                pageTitle: 'Home',
            },
            sections: [{
                localId: 'section-9',
                type: 'webu_general_cta_01',
                props: { title: 'CTA' },
                propsText: '{"title":"CTA"}',
            }],
        }, {
            buildPagePath: (page) => `${page.pageSlug ?? 'page'}/Page.tsx`,
        });

        expect(next).toHaveLength(1);
        expect(next[0]?.revisionSource).toBe('draft');
        expect(next[0]?.sections[0]?.localId).toBe('section-9');
    });
});
