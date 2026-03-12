import { describe, expect, it } from 'vitest';
import { adaptAiSuggestions } from '@/builder/ai/aiMutationAdapter';

describe('adaptAiSuggestions', () => {
    it('keeps only safe structured builder mutations', () => {
        const suggestions = adaptAiSuggestions([
            {
                id: 'suggestion-1',
                title: 'Safe edits',
                summary: 'Mix of valid and invalid operations',
                mutations: [
                    {
                        type: 'PATCH_NODE_PROPS',
                        payload: {
                            nodeId: 'node-hero',
                            patch: { title: 'Updated' },
                        },
                    },
                    {
                        type: 'PATCH_NODE_STYLES',
                        payload: {
                            nodeId: 'node-hero',
                            patch: { backgroundColor: '#000000' },
                        },
                    },
                    {
                        type: 'MOVE_NODE',
                        payload: {
                            nodeId: 'node-footer',
                            targetParentId: 'root-home',
                        },
                    },
                ],
            },
            {
                id: 'suggestion-2',
                title: 'Invalid insert',
                summary: 'Missing payload shape',
                mutations: [
                    {
                        type: 'INSERT_NODE',
                        payload: {
                            parentId: 'root-home',
                        },
                    },
                ],
            },
        ]);

        expect(suggestions).toHaveLength(1);
        expect(suggestions[0]?.mutations.map((mutation) => mutation.type)).toEqual([
            'PATCH_NODE_PROPS',
            'MOVE_NODE',
        ]);
    });
});
