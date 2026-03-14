import { describe, expect, it } from 'vitest';

import { inferImageImportLayout, inferInteractiveModules } from '@/builder/image-import/layoutInference';
import type { ImageImportDesignExtraction } from '@/builder/image-import/types';

function createExtraction(overrides: Partial<ImageImportDesignExtraction> = {}): ImageImportDesignExtraction {
    return {
        schemaVersion: 1,
        sourceKind: 'upload',
        sourceLabel: 'reference.png',
        projectType: 'landing',
        mode: 'reference',
        blocks: [{
            id: 'block-navbar',
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
                ctaLabel: null,
                secondaryCtaLabel: null,
                items: ['Home', 'Pricing', 'Contact'],
                labels: [],
                imageUrls: [],
            },
            layout: {
                columns: null,
                preserveHierarchy: true,
                preserveSpacing: true,
                hasMedia: false,
                hasButtons: false,
                density: 'balanced',
                alignment: 'split',
            },
            evidence: ['top nav'],
        }, {
            id: 'block-hero',
            kind: 'hero',
            order: 1,
            level: 0,
            parentId: null,
            confidence: 0.92,
            bounds: null,
            content: {
                eyebrow: 'Imported',
                title: 'Book your stay',
                subtitle: 'Boutique hotel booking flow',
                body: null,
                ctaLabel: 'Book now',
                secondaryCtaLabel: 'View rooms',
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
            id: 'block-form',
            kind: 'form',
            order: 2,
            level: 0,
            parentId: null,
            confidence: 0.81,
            bounds: null,
            content: {
                eyebrow: null,
                title: 'Check availability',
                subtitle: 'Reserve instantly',
                body: null,
                ctaLabel: 'Reserve',
                secondaryCtaLabel: null,
                items: [],
                labels: [],
                imageUrls: [],
            },
            layout: {
                columns: 1,
                preserveHierarchy: true,
                preserveSpacing: true,
                hasMedia: false,
                hasButtons: true,
                density: 'compact',
                alignment: 'stack',
            },
            evidence: ['booking form', 'reserve'],
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
        ...overrides,
    };
}

describe('image-import layoutInference', () => {
    it('infers interactive modules from extraction evidence', () => {
        const signals = inferInteractiveModules(createExtraction({
            projectType: 'hotel',
        }));

        expect(signals).toEqual(expect.arrayContaining([
            expect.objectContaining({
                kind: 'booking',
            }),
        ]));
    });

    it('builds layout nodes and keeps preview-safe core sections in reference mode', () => {
        const result = inferImageImportLayout(createExtraction({
            blocks: [{
                id: 'hero-only',
                kind: 'hero',
                order: 0,
                level: 0,
                parentId: null,
                confidence: 0.9,
                bounds: null,
                content: {
                    eyebrow: null,
                    title: 'Imported hero',
                    subtitle: 'Direction from screenshot',
                    body: null,
                    ctaLabel: 'Launch',
                    secondaryCtaLabel: null,
                    items: [],
                    labels: [],
                    imageUrls: [],
                },
                layout: {
                    columns: 2,
                    preserveHierarchy: true,
                    preserveSpacing: true,
                    hasMedia: false,
                    hasButtons: true,
                    density: 'balanced',
                    alignment: 'split',
                },
                evidence: ['hero'],
            }],
        }));

        expect(result.nodes.map((node) => node.kind)).toEqual([
            'header',
            'hero',
            'cta',
            'footer',
        ]);
        expect(result.warnings).toContain('reference_mode_preserves_direction_not_pixels');
    });
});
