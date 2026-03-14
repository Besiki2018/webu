import { describe, expect, it } from 'vitest';

import {
    applyWorkspaceBackedBuilderMutations,
    type WorkspaceProjectionMetadata,
} from '@/builder/codegen/workspaceBackedBuilderAdapter';
import type { WorkspaceManifest } from '@/builder/codegen/types';
import {
    normalizeWorkspaceManifest,
    WORKSPACE_MANIFEST_RELATIVE_PATH,
} from '@/builder/codegen/workspaceManifest';
import type { BuilderUpdateOperation } from '@/builder/state/updatePipeline';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';

function createOwnershipEntry(entry: Record<string, unknown>) {
    return {
        locked: false,
        templateOwned: false,
        lastOperationId: null,
        lastOperationKind: null,
        ...entry,
    };
}

function createSectionDraft(localId: string, type: string, props: Record<string, unknown>): SectionDraft {
    return {
        localId,
        type,
        props,
        propsText: JSON.stringify(props, null, 2),
        propsError: null,
        bindingMeta: null,
    };
}

function createWorkspaceManifest(): WorkspaceManifest {
    return normalizeWorkspaceManifest({
        projectId: 'project-1',
        manifestPath: WORKSPACE_MANIFEST_RELATIVE_PATH,
        fileOwnership: [
            createOwnershipEntry({
                path: 'src/pages/home/Page.tsx',
                kind: 'page',
                ownerType: 'page',
                ownerId: '1',
                generatedBy: 'ai',
                editState: 'ai-generated',
                pageIds: ['1'],
                componentIds: ['1:header-1', '1:hero-1', '1:cta-1'],
                activeGenerationRunId: null,
                checksum: null,
                sectionLocalIds: ['header-1', 'hero-1', 'cta-1'],
                componentKeys: ['webu_header_01', 'webu_general_hero_01', 'webu_general_cta_01'],
                originatingPageId: '1',
                originatingPageSlug: 'home',
                lastEditor: 'ai',
                dirty: false,
                updatedAt: null,
            }),
            createOwnershipEntry({
                path: 'src/layouts/SiteLayout.tsx',
                kind: 'layout',
                ownerType: 'layout',
                ownerId: 'site-layout',
                generatedBy: 'ai',
                editState: 'ai-generated',
                pageIds: ['1'],
                componentIds: [],
                activeGenerationRunId: null,
                checksum: null,
                sectionLocalIds: ['header-1'],
                componentKeys: ['webu_header_01', 'webu_footer_01'],
                originatingPageId: '1',
                originatingPageSlug: 'home',
                lastEditor: 'ai',
                dirty: false,
                updatedAt: null,
            }),
            createOwnershipEntry({
                path: 'src/components/Header.tsx',
                kind: 'layout',
                ownerType: 'layout',
                ownerId: 'site-layout',
                generatedBy: 'ai',
                editState: 'ai-generated',
                pageIds: ['1'],
                componentIds: [],
                activeGenerationRunId: null,
                checksum: null,
                sectionLocalIds: ['header-1'],
                componentKeys: ['webu_header_01'],
                originatingPageId: '1',
                originatingPageSlug: 'home',
                lastEditor: 'ai',
                dirty: false,
                updatedAt: null,
            }),
            createOwnershipEntry({
                path: 'src/components/Footer.tsx',
                kind: 'layout',
                ownerType: 'layout',
                ownerId: 'site-layout',
                generatedBy: 'ai',
                editState: 'ai-generated',
                pageIds: ['1'],
                componentIds: [],
                activeGenerationRunId: null,
                checksum: null,
                sectionLocalIds: ['footer-1'],
                componentKeys: ['webu_footer_01'],
                originatingPageId: '1',
                originatingPageSlug: 'home',
                lastEditor: 'ai',
                dirty: false,
                updatedAt: null,
            }),
            createOwnershipEntry({
                path: 'src/sections/HeroSection.tsx',
                kind: 'component',
                ownerType: 'component',
                ownerId: '1:hero-1',
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
            }),
            createOwnershipEntry({
                path: 'src/sections/CTASection.tsx',
                kind: 'component',
                ownerType: 'component',
                ownerId: '1:cta-1',
                generatedBy: 'ai',
                editState: 'ai-generated',
                pageIds: ['1'],
                componentIds: ['1:cta-1'],
                activeGenerationRunId: null,
                checksum: null,
                sectionLocalIds: ['cta-1'],
                componentKeys: ['webu_general_cta_01'],
                originatingPageId: '1',
                originatingPageSlug: 'home',
                lastEditor: 'ai',
                dirty: false,
                updatedAt: null,
            }),
        ],
    });
}

function createProjectionMetadata(): WorkspaceProjectionMetadata {
    return {
        pages: [{
            page_id: 1,
            slug: 'home',
            title: 'Home',
            path: 'src/pages/home/Page.tsx',
            layout_files: [
                'src/layouts/SiteLayout.tsx',
                'src/components/Header.tsx',
                'src/components/Footer.tsx',
            ],
            section_files: [
                'src/sections/HeroSection.tsx',
                'src/sections/CTASection.tsx',
            ],
            sections: [
                {
                    component_name: 'Header',
                    component_path: 'src/components/Header.tsx',
                    type: 'webu_header_01',
                    local_id: 'header-1',
                },
                {
                    component_name: 'HeroSection',
                    component_path: 'src/sections/HeroSection.tsx',
                    type: 'webu_general_hero_01',
                    local_id: 'hero-1',
                },
                {
                    component_name: 'CTASection',
                    component_path: 'src/sections/CTASection.tsx',
                    type: 'webu_general_cta_01',
                    local_id: 'cta-1',
                },
            ],
        }],
        layouts: [],
        components: [],
        files: {
            'src/pages/home/Page.tsx': {
                projection_role: 'page',
                page_slug: 'home',
            },
            'src/layouts/SiteLayout.tsx': {
                projection_role: 'layout',
                page_slug: 'home',
            },
            'src/components/Header.tsx': {
                projection_role: 'layout',
                page_slug: 'home',
            },
            'src/components/Footer.tsx': {
                projection_role: 'layout',
                page_slug: 'home',
            },
            'src/sections/HeroSection.tsx': {
                projection_role: 'section',
                page_slug: 'home',
                component_name: 'HeroSection',
            },
            'src/sections/CTASection.tsx': {
                projection_role: 'section',
                page_slug: 'home',
                component_name: 'CTASection',
            },
        },
    };
}

function applyMutations(operations: BuilderUpdateOperation[], sectionsDraft: SectionDraft[]) {
    return applyWorkspaceBackedBuilderMutations({
        projectId: 'project-1',
        page: {
            id: 1,
            slug: 'home',
            title: 'Home',
        },
        sectionsDraft,
        projectionMetadata: createProjectionMetadata(),
        layoutOverrides: {
            headerVariant: 'webu_header_01',
            footerVariant: 'webu_footer_01',
        },
        manifest: createWorkspaceManifest(),
        operations,
    });
}

describe('workspaceBackedBuilderAdapter', () => {
    it('maps text edits into graph patches and dirties only page and section files', () => {
        const result = applyMutations([{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'hero-1',
            path: 'title',
            value: 'Updated hero title',
        }], [
            createSectionDraft('header-1', 'webu_header_01', {
                logoText: 'Acme',
            }),
            createSectionDraft('hero-1', 'webu_general_hero_01', {
                title: 'Updated hero title',
                subtitle: 'Ship fast',
            }),
            createSectionDraft('cta-1', 'webu_general_cta_01', {
                title: 'Ready?',
            }),
        ]);

        expect(result.graphPatches).toEqual([
            expect.objectContaining({
                kind: 'update-section-props',
                sectionId: 'hero-1',
                propsPatch: {
                    title: 'Updated hero title',
                },
            }),
        ]);
        expect(result.pageGraph.sections.find((section) => section.localId === 'hero-1')?.props.title).toBe('Updated hero title');
        expect(result.dirtyPaths).toEqual([
            'src/pages/home/Page.tsx',
            'src/sections/HeroSection.tsx',
        ]);

        const heroOwnership = result.manifest.fileOwnership.find((entry) => entry.path === 'src/sections/HeroSection.tsx');
        expect(heroOwnership).toMatchObject({
            dirty: true,
            lastEditor: 'visual_builder',
        });
        expect(heroOwnership?.sectionLocalIds).toContain('hero-1');
        expect(heroOwnership?.componentKeys).toContain('webu_general_hero_01');
    });

    it('maps section reorder mutations without dirtying layout or section component files', () => {
        const result = applyMutations([{
            kind: 'reorder-section',
            source: 'toolbar',
            sectionLocalId: 'hero-1',
            toIndex: 2,
        }], [
            createSectionDraft('header-1', 'webu_header_01', {
                logoText: 'Acme',
            }),
            createSectionDraft('cta-1', 'webu_general_cta_01', {
                title: 'Ready?',
            }),
            createSectionDraft('hero-1', 'webu_general_hero_01', {
                title: 'Hero',
            }),
        ]);

        expect(result.graphPatches).toEqual([
            expect.objectContaining({
                kind: 'move-section',
                sectionId: 'hero-1',
                toIndex: 2,
            }),
        ]);
        expect(result.pageGraph.sections.map((section) => section.localId)).toEqual([
            'header-1',
            'cta-1',
            'hero-1',
        ]);
        expect(result.dirtyPaths).toEqual([
            'src/pages/home/Page.tsx',
        ]);
    });

    it('routes header edits to layout-owned workspace files', () => {
        const result = applyMutations([{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'header-1',
            path: 'logoText',
            value: 'Webu Labs',
        }], [
            createSectionDraft('header-1', 'webu_header_01', {
                logoText: 'Webu Labs',
                ctaText: 'Contact',
            }),
            createSectionDraft('hero-1', 'webu_general_hero_01', {
                title: 'Hero',
            }),
            createSectionDraft('cta-1', 'webu_general_cta_01', {
                title: 'Ready?',
            }),
        ]);

        expect(result.dirtyPaths).toEqual([
            'src/components/Footer.tsx',
            'src/components/Header.tsx',
            'src/layouts/SiteLayout.tsx',
            'src/pages/home/Page.tsx',
        ]);

        const headerLayoutOwnership = result.manifest.fileOwnership.find((entry) => entry.path === 'src/components/Header.tsx');
        expect(headerLayoutOwnership).toMatchObject({
            dirty: true,
            lastEditor: 'visual_builder',
        });
        expect(headerLayoutOwnership?.sectionLocalIds).toContain('header-1');
        expect(headerLayoutOwnership?.componentKeys).toContain('webu_header_01');
    });
});
