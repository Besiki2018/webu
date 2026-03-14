import { describe, expect, it } from 'vitest';

import { createGeneratedPage, createGeneratedSection } from '@/builder/codegen/projectGraph';
import { normalizeWorkspaceManifest } from '@/builder/codegen/workspaceManifest';
import { buildCmsBindingModelFromBuilderPageModel } from '@/builder/cmsIntegration/cmsBindingModel';
import {
    applyCmsBindingModelToGeneratedPage,
    applyCmsBindingModelToWorkspaceManifest,
} from '@/builder/cmsIntegration/workspaceCmsSync';
import type { BuilderPageModel } from '@/builder/model/pageModel';

describe('workspaceCmsSync', () => {
    it('projects CMS ownership metadata into generated pages and workspace manifest entries', () => {
        const model: BuilderPageModel = {
            schemaVersion: 1,
            editorMode: 'builder',
            textEditorHtml: '',
            extraContent: {},
            sections: [
                {
                    localId: 'hero-1',
                    type: 'webu_general_hero_01',
                    props: {
                        title: 'Launch faster',
                        subtitle: 'Ship with confidence',
                        variant: 'split',
                    },
                    bindingMeta: null,
                },
            ],
        };
        const bindingModel = buildCmsBindingModelFromBuilderPageModel({
            page: {
                id: '1',
                slug: 'home',
                title: 'Home',
            },
            model,
            editor: 'visual_builder',
            createdBy: 'ai',
            timestamp: '2026-03-14T10:00:00.000Z',
        });
        const page = applyCmsBindingModelToGeneratedPage(createGeneratedPage({
            id: '1',
            slug: 'home',
            title: 'Home',
            sections: [
                createGeneratedSection({
                    id: '1:hero-1',
                    localId: 'hero-1',
                    registryKey: 'webu_general_hero_01',
                    props: {
                        title: 'Launch faster',
                        subtitle: 'Ship with confidence',
                        variant: 'split',
                    },
                }),
            ],
        }), bindingModel);
        const manifest = applyCmsBindingModelToWorkspaceManifest(normalizeWorkspaceManifest({
            projectId: 'project-1',
            generatedPages: [{
                pageId: '1',
                slug: 'home',
                title: 'Home',
                routePath: '/',
                entryFilePath: 'src/pages/home/Page.tsx',
                layoutId: 'site-layout',
                sectionIds: ['1:hero-1'],
            }],
            fileOwnership: [{
                path: 'src/pages/home/Page.tsx',
                kind: 'page',
                ownerType: 'page',
                ownerId: '1',
                generatedBy: 'ai',
                editState: 'ai-generated',
                pageIds: ['1'],
                componentIds: ['1:hero-1'],
                activeGenerationRunId: null,
                checksum: null,
                sectionLocalIds: ['hero-1'],
                componentKeys: ['webu_general_hero_01'],
                originatingPageId: '1',
                originatingPageSlug: 'home',
                lastEditor: 'ai',
                dirty: false,
                updatedAt: null,
                locked: false,
                templateOwned: false,
                lastOperationId: null,
                lastOperationKind: null,
            }],
        }), bindingModel);

        expect(page.cmsBacked).toBe(true);
        expect(page.cmsFieldPaths).toEqual(expect.arrayContaining(['title', 'subtitle']));
        expect(page.visualFieldPaths).toEqual(expect.arrayContaining(['variant']));
        expect(manifest.generatedPages[0]).toMatchObject({
            cmsBacked: true,
            contentOwner: 'mixed',
        });
        expect(manifest.fileOwnership[0]).toMatchObject({
            cmsBacked: true,
            cmsFieldPaths: expect.arrayContaining(['title', 'subtitle']),
            visualFieldPaths: expect.arrayContaining(['variant']),
        });
    });
});
