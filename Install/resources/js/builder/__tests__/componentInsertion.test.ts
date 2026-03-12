import { beforeEach, describe, expect, it } from 'vitest';
import { createNodeFromComponentKey } from '@/builder/library/componentInsertHelpers';
import { useBuilderStore } from '@/builder/state/builderStore';
import type { BuilderDocument } from '@/builder/types/builderDocument';

function createDocument(): BuilderDocument {
    return {
        projectId: 'project-library',
        pages: {
            'page-home': {
                id: 'page-home',
                title: 'Home',
                slug: 'home',
                rootNodeId: 'root-home',
                status: 'draft',
            },
        },
        nodes: {
            'root-home': {
                id: 'root-home',
                type: 'page',
                parentId: null,
                children: [],
                props: { title: 'Home', slug: 'home' },
                styles: {},
                bindings: {},
                meta: { label: 'Home' },
            },
        },
        rootPageId: 'page-home',
        version: 1,
    };
}

describe('component insertion', () => {
    beforeEach(() => {
        useBuilderStore.getState().initialize(createDocument());
    });

    it('generates a stable id, inserts through the mutation pipeline, and auto-selects the new node', () => {
        const node = createNodeFromComponentKey('text', 'root-home');
        expect(node.id).toMatch(/^text-/);

        useBuilderStore.getState().insertNode(node, 'root-home');

        const state = useBuilderStore.getState();
        expect(state.builderDocument.nodes[node.id]?.componentKey).toBe('text');
        expect(state.builderDocument.nodes['root-home']?.children).toEqual([node.id]);
        expect(state.selectedNodeId).toBe(node.id);
        expect(state.undoStack).toHaveLength(1);
        expect(state.dirty).toBe(true);
    });
});
