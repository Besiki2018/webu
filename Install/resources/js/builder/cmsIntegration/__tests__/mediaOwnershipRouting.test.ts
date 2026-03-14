import { describe, expect, it } from 'vitest';

import {
    applySectionMediaBinding,
    resolveCmsMediaFieldOwner,
} from '@/builder/cmsIntegration/mediaOwnershipRouting';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';

function createSectionDraft(): SectionDraft {
    return {
        localId: 'hero-1',
        type: 'webu_general_hero_01',
        props: {
            image: '/storage/original.jpg',
            backgroundImage: '/storage/background.jpg',
        },
        propsText: JSON.stringify({
            image: '/storage/original.jpg',
            backgroundImage: '/storage/background.jpg',
        }),
        propsError: null,
        bindingMeta: null,
    };
}

describe('mediaOwnershipRouting', () => {
    it('classifies CMS-backed and builder-backed image fields separately', () => {
        expect(resolveCmsMediaFieldOwner('webu_general_hero_01', 'image').owner).toBe('cms');
        expect(resolveCmsMediaFieldOwner('webu_general_hero_01', 'backgroundImage').owner).toBe('builder');
    });

    it('stores media provenance for CMS-owned image fields in section binding metadata', () => {
        const section = createSectionDraft();
        const result = applySectionMediaBinding(section, {
            componentKey: 'webu_general_hero_01',
            propPath: ['image'],
            assetUrl: '/storage/imported/hero.jpg',
            media: {
                id: 42,
                metaJson: {
                    stock_provider: 'unsplash',
                    stock_image_id: 'hero-asset-1',
                    imported_by: 'visual_builder',
                },
            },
            projectId: 'project-1',
            pageSlug: 'home',
            source: 'stock_image',
            timestamp: '2026-03-14T12:00:00.000Z',
        });

        const webuMeta = (result.section.bindingMeta?.webu_v2 ?? {}) as Record<string, unknown>;
        const mediaFields = (webuMeta.media_fields ?? {}) as Record<string, Record<string, unknown>>;

        expect(result.owner).toBe('cms');
        expect(mediaFields.image).toMatchObject({
            owner: 'cms',
            asset_url: '/storage/imported/hero.jpg',
            media_id: '42',
            provider: 'unsplash',
            provider_image_id: 'hero-asset-1',
            prop_path: 'image',
            project_id: 'project-1',
            page_slug: 'home',
            source: 'stock_image',
        });
        expect((webuMeta.provenance as Record<string, unknown>)?.last_editor).toBe('visual_builder');
    });
});
