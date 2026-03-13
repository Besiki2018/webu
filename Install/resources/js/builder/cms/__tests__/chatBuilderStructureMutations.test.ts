import { describe, expect, it } from 'vitest';

import { buildOptimisticInsertedStructureItems } from '@/builder/cms/chatBuilderStructureMutations';

describe('chatBuilderStructureMutations', () => {
    it('upserts optimistic inserted items by local id instead of duplicating them', () => {
        const items = [
            {
                localId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                label: 'Hero',
                previewText: 'Hero',
                props: {},
            },
            {
                localId: 'draft-1',
                sectionKey: 'webu_general_text_01',
                label: 'Draft text',
                previewText: 'Draft text',
                props: {},
            },
        ];

        const next = buildOptimisticInsertedStructureItems(items, {
            localId: 'draft-1',
            sectionKey: 'webu_general_text_01',
            label: 'Draft text updated',
            previewText: 'Draft text updated',
            props: {},
        }, {
            afterSectionLocalId: 'hero-1',
            placement: 'after',
        });

        expect(next).toHaveLength(2);
        expect(next.filter((item) => item.localId === 'draft-1')).toHaveLength(1);
        expect(next[1]).toEqual(expect.objectContaining({
            localId: 'draft-1',
            previewText: 'Draft text updated',
        }));
    });
});
