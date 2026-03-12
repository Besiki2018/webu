import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { DndContext } from '@dnd-kit/core';
import { describe, expect, it, vi } from 'vitest';

import { BuilderCanvas } from '../BuilderCanvas';
import type { BuilderSection } from '../treeUtils';

function renderBuilderCanvas(
    sections: BuilderSection[],
    options: {
        onSelect?: ReturnType<typeof vi.fn>;
        onHover?: ReturnType<typeof vi.fn>;
        onSelectTarget?: ReturnType<typeof vi.fn>;
        onHoverTarget?: ReturnType<typeof vi.fn>;
    } = {}
) {
    return render(
        <DndContext>
            <BuilderCanvas
                sections={sections}
                selectedElementId={null}
                hoveredElementId={null}
                draggingComponentType={null}
                currentDropTarget={null}
                sectionDisplayLabelByKey={new Map([
                    ['webu_general_hero_01', 'Hero Section'],
                    ['webu_header_01', 'Header'],
                    ['webu_general_heading_01', 'Heading'],
                    ['webu_general_button_01', 'Button'],
                    ['webu_general_image_01', 'Image'],
                ])}
                onSelect={options.onSelect ?? vi.fn()}
                onHover={options.onHover ?? vi.fn()}
                onSelectTarget={options.onSelectTarget}
                onHoverTarget={options.onHoverTarget}
                onDeselect={vi.fn()}
                t={(key) => key}
            />
        </DndContext>
    );
}

describe('BuilderCanvas registry rendering', () => {
    it('renders registry-backed component content instead of the generic placeholder', () => {
        renderBuilderCanvas([
            {
                localId: 'hero-1',
                type: 'webu_general_hero_01',
                propsText: JSON.stringify({
                    eyebrow: 'New collection',
                    title: 'Online store for modern brands',
                    subtitle: 'Sell faster with schema-driven builder sections.',
                    buttonText: 'Start selling',
                }),
                propsError: null,
            },
        ]);

        expect(screen.getByText('Online store for modern brands')).toBeInTheDocument();
        expect(screen.getByText('Sell faster with schema-driven builder sections.')).toBeInTheDocument();
        expect(screen.getByText('Start selling')).toBeInTheDocument();
        expect(screen.queryByText('No preview content')).not.toBeInTheDocument();
    });

    it('falls back to placeholder when a section key is not registered', () => {
        renderBuilderCanvas([
            {
                localId: 'unknown-1',
                type: 'unknown_section',
                propsText: '{}',
                propsError: null,
            },
        ]);

        expect(screen.getByText('unknown_section')).toBeInTheDocument();
        expect(screen.getByText('No preview content')).toBeInTheDocument();
    });

    it('renders production-ready previews for basic content components instead of the generic schema card', () => {
        renderBuilderCanvas([
            {
                localId: 'heading-1',
                type: 'webu_general_heading_01',
                props: {
                    headline: 'Featured category',
                    subtitle: 'Organize the next content block with a real heading preview.',
                },
                propsText: JSON.stringify({
                    headline: 'Featured category',
                    subtitle: 'Organize the next content block with a real heading preview.',
                }),
                propsError: null,
            },
            {
                localId: 'button-1',
                type: 'webu_general_button_01',
                props: {
                    button: 'Shop sale',
                    button_url: '/sale',
                },
                propsText: JSON.stringify({
                    button: 'Shop sale',
                    button_url: '/sale',
                }),
                propsError: null,
            },
        ]);

        expect(screen.getByText('Featured category')).toBeInTheDocument();
        expect(screen.getByText('Shop sale')).toBeInTheDocument();
        expect(screen.queryByText(/Schema-backed fields/i)).not.toBeInTheDocument();
    });

    it('adds stable builder metadata and emits section-level hover/selection targets', async () => {
        const onSelectTarget = vi.fn();
        const onHoverTarget = vi.fn();

        renderBuilderCanvas([
            {
                localId: 'hero-1',
                type: 'webu_general_hero_01',
                propsText: JSON.stringify({
                    eyebrow: 'New collection',
                    title: 'Online store for modern brands',
                    subtitle: 'Sell faster with schema-driven builder sections.',
                    buttonText: 'Start selling',
                }),
                propsError: null,
            },
        ], {
            onSelectTarget,
            onHoverTarget,
        });

        const titleNode = await screen.findByText('Online store for modern brands');

        await waitFor(() => {
            expect(titleNode).toHaveAttribute('data-builder-path', 'title');
            expect(titleNode).toHaveAttribute('data-builder-component-type', 'webu_general_hero_01');
            expect(titleNode).toHaveAttribute('data-builder-component-name', 'Hero Section');
            expect(titleNode).toHaveAttribute('data-builder-section-id', 'hero-1');
            expect(titleNode.getAttribute('data-builder-id')).toMatch(/hero-1::(field|scope)::title::\d+/);
        });

        fireEvent.mouseMove(titleNode);
        await waitFor(() => {
            expect(onHoverTarget).toHaveBeenCalledWith(expect.objectContaining({
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                path: null,
                elementId: null,
                builderId: 'hero-1',
            }));
        });

        fireEvent.click(titleNode);
        expect(onSelectTarget).toHaveBeenCalledWith(expect.objectContaining({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            path: null,
            componentPath: null,
            elementId: null,
            builderId: 'hero-1',
            instanceId: 'hero-1',
        }));
    });

    it('keeps hover targeting stable when the pointer moves within the same editable node', async () => {
        const onHoverTarget = vi.fn();

        renderBuilderCanvas([
            {
                localId: 'hero-1',
                type: 'webu_general_hero_01',
                propsText: JSON.stringify({
                    title: 'Stable hover title',
                    buttonText: 'Shop now',
                }),
                propsError: null,
            },
        ], {
            onHoverTarget,
        });

        const titleNode = await screen.findByText('Stable hover title');

        fireEvent.mouseMove(titleNode);
        fireEvent.mouseMove(titleNode);
        fireEvent.mouseMove(titleNode);

        await waitFor(() => {
        expect(onHoverTarget).toHaveBeenCalledTimes(1);
        });

        expect(onHoverTarget).toHaveBeenCalledWith(expect.objectContaining({
            path: null,
            sectionLocalId: 'hero-1',
        }));

        fireEvent.mouseLeave(titleNode);
        await waitFor(() => {
            expect(onHoverTarget).toHaveBeenLastCalledWith(null);
        });
    });
});
