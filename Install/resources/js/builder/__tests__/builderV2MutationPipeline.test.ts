import { describe, expect, it } from 'vitest';
import type { BuilderDocument } from '@/builder/types/builderDocument';
import { dispatchBuilderMutation } from '@/builder/mutations/dispatchBuilderMutation';

function createDocument(): BuilderDocument {
    return {
        projectId: 'project-1',
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
                children: ['node-hero', 'node-footer'],
                props: { title: 'Home', slug: 'home' },
                styles: {},
                bindings: {},
                meta: { label: 'Home' },
            },
            'node-hero': {
                id: 'node-hero',
                type: 'component',
                componentKey: 'hero',
                parentId: 'root-home',
                children: [],
                props: { title: 'Start here' },
                styles: {},
                bindings: {},
                meta: { label: 'Hero' },
            },
            'node-footer': {
                id: 'node-footer',
                type: 'component',
                componentKey: 'footer',
                parentId: 'root-home',
                children: [],
                props: { copyright: 'Copyright' },
                styles: {},
                bindings: {},
                meta: { label: 'Footer' },
            },
        },
        rootPageId: 'page-home',
        version: 1,
    };
}

function createContext(document: BuilderDocument) {
    return {
        builderDocument: document,
        activePageId: document.rootPageId,
        selectedNodeId: 'node-hero',
        hoveredNodeId: null,
        devicePreset: 'desktop' as const,
        undoStack: [],
        redoStack: [],
        dirty: false,
        validationErrors: {},
    };
}

describe('builder V2 mutation pipeline', () => {
    it('patches props and pushes history entries', () => {
        const context = createContext(createDocument());
        const result = dispatchBuilderMutation(context, {
            type: 'PATCH_NODE_PROPS',
            payload: {
                nodeId: 'node-hero',
                patch: {
                    title: 'Updated hero',
                },
            },
        });

        expect(result.builderDocument.nodes['node-hero']?.props.title).toBe('Updated hero');
        expect(result.undoStack).toHaveLength(1);
        expect(result.dirty).toBe(true);
    });

    it('supports style edits with undo and redo', () => {
        const styled = dispatchBuilderMutation(createContext(createDocument()), {
            type: 'PATCH_NODE_STYLES',
            payload: {
                nodeId: 'node-hero',
                patch: {
                    backgroundColor: '#0f172a',
                },
            },
        });

        expect(styled.builderDocument.nodes['node-hero']?.styles?.backgroundColor).toBe('#0f172a');

        const undone = dispatchBuilderMutation(styled, {
            type: 'UNDO',
            payload: {},
        });

        expect(undone.builderDocument.nodes['node-hero']?.styles?.backgroundColor).toBeUndefined();

        const redone = dispatchBuilderMutation(undone, {
            type: 'REDO',
            payload: {},
        });

        expect(redone.builderDocument.nodes['node-hero']?.styles?.backgroundColor).toBe('#0f172a');
    });

    it('supports insert with undo and redo', () => {
        const inserted = dispatchBuilderMutation(createContext(createDocument()), {
            type: 'INSERT_NODE',
            payload: {
                parentId: 'root-home',
                node: {
                    id: 'node-banner',
                    type: 'component',
                    componentKey: 'banner',
                    parentId: 'root-home',
                    children: [],
                    props: {
                        title: 'Inserted banner',
                    },
                    styles: {},
                    bindings: {},
                    meta: {
                        label: 'Banner',
                    },
                },
                index: 1,
            },
        });

        expect(inserted.builderDocument.nodes['node-banner']).toBeDefined();
        expect(inserted.builderDocument.nodes['root-home']?.children).toEqual(['node-hero', 'node-banner', 'node-footer']);

        const undone = dispatchBuilderMutation(inserted, {
            type: 'UNDO',
            payload: {},
        });

        expect(undone.builderDocument.nodes['node-banner']).toBeUndefined();
        expect(undone.builderDocument.nodes['root-home']?.children).toEqual(['node-hero', 'node-footer']);

        const redone = dispatchBuilderMutation(undone, {
            type: 'REDO',
            payload: {},
        });

        expect(redone.builderDocument.nodes['node-banner']).toBeDefined();
        expect(redone.builderDocument.nodes['root-home']?.children).toEqual(['node-hero', 'node-banner', 'node-footer']);
    });

    it('remaps selection after delete and supports undo/redo', () => {
        const patched = dispatchBuilderMutation(createContext(createDocument()), {
            type: 'DELETE_NODE',
            payload: {
                nodeId: 'node-hero',
            },
        });

        expect(patched.builderDocument.nodes['node-hero']).toBeUndefined();
        expect(patched.selectedNodeId).toBe('node-footer');

        const undone = dispatchBuilderMutation(patched, {
            type: 'UNDO',
            payload: {},
        });

        expect(undone.builderDocument.nodes['node-hero']).toBeDefined();
        expect(undone.selectedNodeId).toBe('node-hero');

        const redone = dispatchBuilderMutation(undone, {
            type: 'REDO',
            payload: {},
        });

        expect(redone.builderDocument.nodes['node-hero']).toBeUndefined();
        expect(redone.selectedNodeId).toBe('node-footer');
    });

    it('supports move with undo and redo', () => {
        const moved = dispatchBuilderMutation(createContext(createDocument()), {
            type: 'MOVE_NODE',
            payload: {
                nodeId: 'node-footer',
                targetParentId: 'root-home',
                index: 0,
            },
        });

        expect(moved.builderDocument.nodes['root-home']?.children).toEqual(['node-footer', 'node-hero']);

        const undone = dispatchBuilderMutation(moved, {
            type: 'UNDO',
            payload: {},
        });

        expect(undone.builderDocument.nodes['root-home']?.children).toEqual(['node-hero', 'node-footer']);

        const redone = dispatchBuilderMutation(undone, {
            type: 'REDO',
            payload: {},
        });

        expect(redone.builderDocument.nodes['root-home']?.children).toEqual(['node-footer', 'node-hero']);
    });
});
