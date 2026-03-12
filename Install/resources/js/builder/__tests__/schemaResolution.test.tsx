import { cleanup, fireEvent, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import { InspectorPanel } from '@/builder/inspector/InspectorPanel';
import { useBuilderStore } from '@/builder/state/builderStore';
import type { BuilderApiEndpoints } from '@/builder/api/builderApi';
import type { BuilderDocument } from '@/builder/types/builderDocument';

const endpoints: BuilderApiEndpoints = {
    document: '/api/projects/1/builder-document',
    mutations: '/api/projects/1/builder-mutations',
    publish: '/api/projects/1/publish',
    aiSuggestions: '/api/projects/1/builder-ai/suggest',
    assets: '/project/1/files',
    assetsUpload: '/project/1/files',
};

function createDocument(componentKey: string = 'hero'): BuilderDocument {
    return {
        projectId: 'project-inspector',
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
                children: ['node-hero'],
                props: { title: 'Home', slug: 'home' },
                styles: {},
                bindings: {},
                meta: { label: 'Home' },
            },
            'node-hero': {
                id: 'node-hero',
                type: 'component',
                componentKey,
                parentId: 'root-home',
                children: [],
                props: {
                    title: 'Original hero',
                    subtitle: 'Subheading',
                    description: 'Description',
                    buttonText: 'Get started',
                    buttonLink: '#',
                    image: '',
                    variant: 'hero-1',
                },
                styles: {},
                bindings: {},
                meta: { label: 'Hero' },
            },
        },
        rootPageId: 'page-home',
        version: 1,
    };
}

describe('InspectorPanel schema resolution', () => {
    beforeEach(() => {
        useBuilderStore.getState().initialize(createDocument());
        useBuilderStore.getState().selectNode('node-hero');
    });

    afterEach(() => {
        cleanup();
    });

    it('renders fields from the registry schema and patches props through the store', () => {
        render(<InspectorPanel endpoints={endpoints} />);

        expect(screen.getByText('Title')).toBeInTheDocument();
        expect(screen.getByText('Primary Link')).toBeInTheDocument();

        fireEvent.change(screen.getByDisplayValue('Original hero'), {
            target: { value: 'Updated hero' },
        });

        expect(useBuilderStore.getState().builderDocument.nodes['node-hero']?.props.title).toBe('Updated hero');
    });

    it('shows a safe empty state when no schema is registered', () => {
        useBuilderStore.getState().initialize(createDocument('unknown-component'));
        useBuilderStore.getState().selectNode('node-hero');

        render(<InspectorPanel endpoints={endpoints} />);

        expect(screen.getByText('No inspector schema is registered for this node.')).toBeInTheDocument();
    });
});
