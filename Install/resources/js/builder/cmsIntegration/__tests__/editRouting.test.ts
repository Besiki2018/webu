import { describe, expect, it } from 'vitest';

import { routeBuilderOperationsToCmsEdit } from '@/builder/cmsIntegration/editRouting';
import type { BuilderUpdateOperation } from '@/builder/state/updatePipeline';
import type { SectionDraft } from '@/builder/state/useBuilderCanvasState';

function createSectionDraft(type: string, localId = 'hero-1', props: Record<string, unknown> = {
    title: 'Hello',
    variant: 'split',
}): SectionDraft {
    return {
        localId,
        type,
        props,
        propsText: JSON.stringify(props),
        propsError: null,
        bindingMeta: null,
    };
}

describe('editRouting', () => {
    const sections = [
        createSectionDraft('webu_general_hero_01'),
        createSectionDraft('webu_general_cards_01', 'cards-1', {
            items: [{ image: '/storage/card.jpg', title: 'Card title' }],
        }),
    ];

    it('routes content field edits to CMS content_change', () => {
        const operations: BuilderUpdateOperation[] = [{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'hero-1',
            path: 'title',
            value: 'Updated',
        }];

        expect(routeBuilderOperationsToCmsEdit(operations, sections).route).toBe('content_change');
    });

    it('routes style and layout field edits to structure_change', () => {
        const operations: BuilderUpdateOperation[] = [{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'hero-1',
            path: 'backgroundImage',
            value: '/storage/background.jpg',
        }];

        expect(routeBuilderOperationsToCmsEdit(operations, sections).route).toBe('structure_change');
    });

    it('routes repeater item image edits to content_change', () => {
        const operations: BuilderUpdateOperation[] = [{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'cards-1',
            path: 'items.0.image',
            value: '/storage/card-replacement.jpg',
        }];

        const routed = routeBuilderOperationsToCmsEdit(operations, sections);

        expect(routed.route).toBe('content_change');
        expect(routed.contentFieldPaths).toContain('items.0.image');
    });

    it('still routes layout variants to structure_change', () => {
        const operations: BuilderUpdateOperation[] = [{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'hero-1',
            path: 'variant',
            value: 'stacked',
        }];

        expect(routeBuilderOperationsToCmsEdit(operations, sections).route).toBe('structure_change');
    });

    it('marks combined content and layout edits as mixed_change', () => {
        const operations: BuilderUpdateOperation[] = [{
            kind: 'merge-props',
            source: 'sidebar',
            sectionLocalId: 'hero-1',
            patch: {
                title: 'Updated',
                variant: 'stacked',
            },
        }];

        expect(routeBuilderOperationsToCmsEdit(operations, sections).route).toBe('mixed_change');
    });

    it('routes structural mutations to structure_change', () => {
        const operations: BuilderUpdateOperation[] = [{
            kind: 'reorder-section',
            source: 'drag-drop',
            sectionLocalId: 'hero-1',
            toIndex: 0,
        }];

        expect(routeBuilderOperationsToCmsEdit(operations, sections).route).toBe('structure_change');
    });
});
