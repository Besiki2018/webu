import { describe, expect, it } from 'vitest';

import { createImageImportProjectPlan } from '@/builder/image-import/imageToProjectGraph';
import type { ImageImportDesignExtraction } from '@/builder/image-import/types';

function createExtraction(): ImageImportDesignExtraction {
    return {
        schemaVersion: 1,
        sourceKind: 'upload',
        sourceLabel: 'landing-reference.png',
        projectType: 'landing',
        mode: 'reference',
        blocks: [{
            id: 'block-1',
            kind: 'navbar',
            order: 0,
            level: 0,
            parentId: null,
            confidence: 0.9,
            bounds: null,
            content: {
                eyebrow: null,
                title: 'Acme',
                subtitle: null,
                body: null,
                ctaLabel: 'Login',
                secondaryCtaLabel: null,
                items: ['Home', 'Features', 'Pricing'],
                labels: [],
                imageUrls: [],
            },
            layout: {
                columns: null,
                preserveHierarchy: true,
                preserveSpacing: true,
                hasMedia: false,
                hasButtons: true,
                density: 'balanced',
                alignment: 'split',
            },
            evidence: ['header'],
        }, {
            id: 'block-2',
            kind: 'hero',
            order: 1,
            level: 0,
            parentId: null,
            confidence: 0.94,
            bounds: null,
            content: {
                eyebrow: 'Imported',
                title: 'Ship faster with Webu',
                subtitle: 'Convert screenshots into editable projects',
                body: null,
                ctaLabel: 'Start free',
                secondaryCtaLabel: 'See demo',
                items: [],
                labels: [],
                imageUrls: ['https://example.com/hero.png'],
            },
            layout: {
                columns: 2,
                preserveHierarchy: true,
                preserveSpacing: true,
                hasMedia: true,
                hasButtons: true,
                density: 'airy',
                alignment: 'split',
            },
            evidence: ['hero'],
        }, {
            id: 'block-3',
            kind: 'feature-list',
            order: 2,
            level: 0,
            parentId: null,
            confidence: 0.82,
            bounds: null,
            content: {
                eyebrow: null,
                title: 'Why teams use it',
                subtitle: 'Three reasons',
                body: null,
                ctaLabel: null,
                secondaryCtaLabel: null,
                items: ['Import designs', 'Generate files', 'Edit visually'],
                labels: [],
                imageUrls: [],
            },
            layout: {
                columns: 3,
                preserveHierarchy: false,
                preserveSpacing: false,
                hasMedia: false,
                hasButtons: false,
                density: 'balanced',
                alignment: 'grid',
            },
            evidence: ['features'],
        }, {
            id: 'block-4',
            kind: 'cta',
            order: 3,
            level: 0,
            parentId: null,
            confidence: 0.8,
            bounds: null,
            content: {
                eyebrow: null,
                title: 'Ready to import your next design?',
                subtitle: 'Start with a screenshot',
                body: null,
                ctaLabel: 'Generate site',
                secondaryCtaLabel: null,
                items: [],
                labels: [],
                imageUrls: [],
            },
            layout: {
                columns: 1,
                preserveHierarchy: false,
                preserveSpacing: false,
                hasMedia: false,
                hasButtons: true,
                density: 'balanced',
                alignment: 'stack',
            },
            evidence: ['cta'],
        }, {
            id: 'block-5',
            kind: 'footer',
            order: 4,
            level: 0,
            parentId: null,
            confidence: 0.86,
            bounds: null,
            content: {
                eyebrow: null,
                title: 'Acme',
                subtitle: 'Footer',
                body: 'Built from an image reference',
                ctaLabel: null,
                secondaryCtaLabel: null,
                items: ['Privacy', 'Terms'],
                labels: [],
                imageUrls: [],
            },
            layout: {
                columns: null,
                preserveHierarchy: false,
                preserveSpacing: false,
                hasMedia: false,
                hasButtons: false,
                density: 'balanced',
                alignment: 'stack',
            },
            evidence: ['footer'],
        }],
        functionSignals: [],
        styleDirection: {
            primaryStyle: 'modern',
            isDark: false,
            spacing: 'balanced',
            borderTreatment: 'rounded',
            visualWeight: 'balanced',
        },
        warnings: [],
        extractedAt: null,
        metadata: {},
    };
}

describe('image-import imageToProjectGraph', () => {
    it('creates a code-first project plan with graph, workspace plan, and builder model output', async () => {
        const result = await createImageImportProjectPlan({
            projectId: 'project-1',
            projectName: 'Webu Demo',
            pageSlug: 'home',
            pageTitle: 'Home',
            extraction: createExtraction(),
        });

        expect(result.projectGraph.pages[0]?.entryFilePath).toBe('src/pages/home/Page.tsx');
        expect(result.workspacePlan.fileOperations.some((operation) => operation.path === 'src/App.tsx')).toBe(true);
        expect(result.workspaceOperations[0]).toMatchObject({
            kind: 'scaffold_project',
        });
        expect(result.builderModels[0]?.model.sections.map((section) => section.type)).toEqual([
            'webu_header_01',
            'webu_general_hero_01',
            'webu_general_features_01',
            'webu_general_cta_01',
            'webu_footer_01',
        ]);
        expect(result.projectGraph.generation.phase).toBe('writing_files');
    });
});
