import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeAll, beforeEach, describe, expect, it } from 'vitest';
import { CanvasWorkspace } from '@/builder/canvas/CanvasWorkspace';
import { useBuilderStore } from '@/builder/state/builderStore';
import type { BuilderDocument } from '@/builder/types/builderDocument';

function createDocument(): BuilderDocument {
    return {
        projectId: 'project-selection',
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
                children: ['node-text', 'node-button'],
                props: { title: 'Home', slug: 'home' },
                styles: {},
                bindings: {},
                meta: { label: 'Home' },
            },
            'node-text': {
                id: 'node-text',
                type: 'text',
                componentKey: 'text',
                parentId: 'root-home',
                children: [],
                props: { text: 'Hello canvas' },
                styles: {},
                bindings: {},
                meta: { label: 'Text' },
            },
            'node-button': {
                id: 'node-button',
                type: 'button',
                componentKey: 'button',
                parentId: 'root-home',
                children: [],
                props: { text: 'Tap me', href: '#' },
                styles: {},
                bindings: {},
                meta: { label: 'Button' },
            },
        },
        rootPageId: 'page-home',
        version: 1,
    };
}

beforeAll(() => {
    if (! globalThis.CSS) {
        Object.defineProperty(globalThis, 'CSS', {
            value: { escape: (value: string) => value },
            configurable: true,
        });

        return;
    }

    if (typeof globalThis.CSS.escape !== 'function') {
        Object.defineProperty(globalThis.CSS, 'escape', {
            value: (value: string) => value,
            configurable: true,
        });
    }
});

describe('CanvasWorkspace selection resolver', () => {
    beforeEach(() => {
        useBuilderStore.getState().initialize(createDocument());
    });

    afterEach(() => {
        cleanup();
    });

    it('selects the nearest node, keeps hover separate, and falls back to the page root on blank clicks', () => {
        const { container } = render(
            <div className="h-screen">
                <CanvasWorkspace />
            </div>,
        );

        fireEvent.click(screen.getByText('Hello canvas'));
        expect(useBuilderStore.getState().selectedNodeId).toBe('node-text');

        fireEvent.mouseMove(screen.getByText('Tap me'));
        expect(useBuilderStore.getState().hoveredNodeId).toBe('node-button');
        expect(useBuilderStore.getState().selectedNodeId).toBe('node-text');

        const workspaceSurface = container.querySelector('.relative.grid');
        expect(workspaceSurface).not.toBeNull();
        fireEvent.click(workspaceSurface!);

        expect(useBuilderStore.getState().selectedNodeId).toBe('root-home');
    });
});
