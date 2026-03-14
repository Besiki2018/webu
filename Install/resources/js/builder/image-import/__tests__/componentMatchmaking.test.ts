import { describe, expect, it } from 'vitest';

import { matchImageImportComponents } from '@/builder/image-import/componentMatchmaking';
import type { ImageImportDesignExtraction, ImageImportLayoutInference } from '@/builder/image-import/types';

function createExtraction(mode: ImageImportDesignExtraction['mode'] = 'reference'): ImageImportDesignExtraction {
    return {
        schemaVersion: 1,
        sourceKind: 'upload',
        sourceLabel: 'design.png',
        projectType: 'ecommerce',
        mode,
        blocks: [],
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

describe('image-import componentMatchmaking', () => {
    it('prefers existing registry components for compatible inferred blocks', () => {
        const layout: ImageImportLayoutInference = {
            nodes: [{
                id: 'node-1',
                kind: 'product-grid',
                sourceBlockIds: ['block-1'],
                order: 0,
                sectionLabel: 'Featured products',
                registryHint: 'product-grid',
                mode: 'reference',
                propsSeed: {
                    title: 'Featured products',
                    items: [{ title: 'Chair' }],
                },
                preserveHierarchy: false,
                preserveSpacing: false,
                repetition: 1,
                interactiveModule: 'ecommerce',
                metadata: {},
            }],
            functionModules: [],
            warnings: [],
        };

        const result = matchImageImportComponents(createExtraction(), layout, { pageSlug: 'home' });
        expect(result[0]).toEqual(expect.objectContaining({
            matchKind: 'registry',
            registryKey: 'webu_ecom_product_grid_01',
        }));
    });

    it('falls back to generated components when exact recreation has no acceptable registry match', () => {
        const layout: ImageImportLayoutInference = {
            nodes: [{
                id: 'node-1',
                kind: 'generated',
                sourceBlockIds: ['block-1'],
                order: 0,
                sectionLabel: 'Magazine mosaic',
                registryHint: null,
                mode: 'recreate',
                propsSeed: {
                    title: 'Magazine mosaic',
                },
                preserveHierarchy: true,
                preserveSpacing: true,
                repetition: 1,
                interactiveModule: null,
                metadata: {},
            }],
            functionModules: [],
            warnings: [],
        };

        const result = matchImageImportComponents(createExtraction('recreate'), layout, { pageSlug: 'home' });
        expect(result[0]).toEqual(expect.objectContaining({
            matchKind: 'generated',
            registryKey: null,
        }));
        expect(result[0]?.generatedComponent?.filePath).toContain('src/sections/imported/');
    });
});
