import { describe, expect, it } from 'vitest';

import { buildProjectGraphPatchFromBuilderOperations } from '@/builder/codegen/builderModelToProjectGraphPatch';
import { createGeneratedProjectGraph } from '@/builder/codegen/projectGraph';
import { buildBuilderPageModelFromGeneratedPage } from '@/builder/codegen/projectGraphToBuilderModel';
import type { BuilderUpdateOperation } from '@/builder/state/updatePipeline';

describe('codegen projectGraph mappings', () => {
    it('maps supported generated sections into the current builder page model', () => {
        const graph = createGeneratedProjectGraph({
            projectId: 'project-1',
            name: 'Acme',
            pages: [{
                id: 'page-home',
                slug: 'home',
                title: 'Home',
                sections: [
                    {
                        id: 'section-header',
                        localId: 'header-1',
                        kind: 'header',
                        registryKey: 'webu_header_01',
                        props: {
                            logo: 'Acme',
                            menu: [{ label: 'Home', url: '/' }],
                        },
                    },
                    {
                        id: 'section-hero',
                        localId: 'hero-1',
                        kind: 'hero',
                        props: {
                            headline: 'Build with Webu',
                            description: 'Ship pages fast.',
                            ctaLabel: 'Start now',
                            ctaUrl: '/start',
                        },
                    },
                    {
                        id: 'section-features',
                        localId: 'features-1',
                        kind: 'features',
                        props: {
                            title: 'Features',
                            items: [{ title: 'Fast', description: 'Really fast' }],
                        },
                    },
                    {
                        id: 'section-cta',
                        localId: 'cta-1',
                        kind: 'cta',
                        props: {
                            title: 'Ready?',
                            ctaLabel: 'Book now',
                            ctaUrl: '/book',
                        },
                    },
                    {
                        id: 'section-footer',
                        localId: 'footer-1',
                        kind: 'footer',
                        registryKey: 'webu_footer_01',
                        props: {
                            logoText: 'Acme',
                            links: [{ label: 'Privacy', url: '/privacy' }],
                        },
                    },
                    {
                        id: 'section-content',
                        localId: 'content-1',
                        kind: 'content',
                        props: {
                            title: 'About',
                            body: 'We build.',
                        },
                    },
                ],
            }],
        });

        const model = buildBuilderPageModelFromGeneratedPage(graph.pages[0]!);

        expect(model.sections.map((section) => section.type)).toEqual([
            'webu_header_01',
            'webu_general_hero_01',
            'webu_general_features_01',
            'webu_general_cta_01',
            'webu_footer_01',
            'webu_general_heading_01',
        ]);
        expect(model.sections[1]?.props.title).toBe('Build with Webu');
        expect(model.sections[2]?.props.items).toHaveLength(1);
        expect(model.sections[3]?.props.buttonUrl).toBe('/book');
    });

    it('maps builder mutations into graph patch instructions', () => {
        const operations: BuilderUpdateOperation[] = [
            {
                kind: 'set-field',
                source: 'chat',
                sectionLocalId: 'hero-1',
                path: 'buttonText',
                value: 'Launch',
            },
            {
                kind: 'insert-section',
                source: 'chat',
                sectionType: 'hero',
                localId: 'hero-2',
                props: {
                    title: 'Another hero',
                },
                insertIndex: 1,
            },
            {
                kind: 'reorder-nested-section',
                source: 'chat',
                sectionLocalId: 'features-1',
                nestedSectionPath: [0],
                toIndex: 2,
            },
        ];

        const patch = buildProjectGraphPatchFromBuilderOperations({
            id: 'page-home',
            slug: 'home',
        }, operations);

        expect(patch[0]).toMatchObject({
            kind: 'update-section-props',
            sectionId: 'hero-1',
            propsPatch: {
                buttonText: 'Launch',
            },
        });
        expect(patch[1]).toMatchObject({
            kind: 'insert-section',
            section: {
                localId: 'hero-2',
                kind: 'hero',
                registryKey: 'webu_general_hero_01',
            },
        });
        expect(patch[2]).toMatchObject({
            kind: 'unsupported-builder-operation',
            operationKind: 'reorder-nested-section',
            reason: 'nested_sections_not_supported_yet',
        });
    });
});
