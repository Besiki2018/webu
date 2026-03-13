/**
 * Phase 13 — Validate existing components after refactor.
 * Ensures: canvas renders component, sidebar can load parameters, props update flow works.
 */

import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import {
    getCentralRegistryEntry,
    getComponentSchema,
    getComponentRuntimeEntry,
    resolveComponentProps,
} from '../componentRegistry';
import { BuilderCanvas } from '../visual/BuilderCanvas';

/** Registry IDs for components we must support: Header, Footer, Hero, Feature, CTA, Navigation (header), Cards, Grids */
const REQUIRED_COMPONENT_IDS = [
    'webu_header_01',           // Header + Navigation
    'webu_footer_01',           // Footer
    'webu_general_hero_01',     // Hero
    'webu_general_heading_01',   // Feature / Heading
    'webu_general_cta_01',      // CTA
    'webu_general_button_01',   // CTA (button)
    'webu_general_card_01',     // Cards
    'webu_ecom_product_grid_01', // Grids (product grid)
    'webu_general_newsletter_01', // Newsletter (often used as CTA)
] as const;

describe('Phase 13 — Component validation', () => {
    it('all required components have schema with fields (sidebar can load parameters)', () => {
        for (const id of REQUIRED_COMPONENT_IDS) {
            const schema = getComponentSchema(id);
            expect(schema, `getComponentSchema("${id}")`).not.toBeNull();
            expect(schema?.componentKey, `schema.componentKey for ${id}`).toBe(id);
            expect(Array.isArray(schema?.fields), `schema.fields for ${id}`).toBe(true);
            expect((schema?.fields?.length ?? 0) > 0, `schema.fields.length > 0 for ${id}`).toBe(true);
        }
    });

    it('all required components have runtime entry (canvas can resolve component)', () => {
        for (const id of REQUIRED_COMPONENT_IDS) {
            const entry = getComponentRuntimeEntry(id);
            expect(entry, `getComponentRuntimeEntry("${id}")`).not.toBeNull();
            expect(entry?.componentKey).toBe(id);
            expect(typeof entry?.component === 'function', `runtimeEntry.component for ${id}`).toBe(true);
            expect(entry?.schema).not.toBeNull();
            expect(entry?.defaults).toBeDefined();
        }
    });

    it('Header, Footer, Hero have central registry entry and mapBuilderProps', () => {
        const centralIds = ['webu_header_01', 'webu_footer_01', 'webu_general_hero_01'] as const;
        for (const id of centralIds) {
            const entry = getCentralRegistryEntry(id);
            expect(entry, `getCentralRegistryEntry("${id}")`).not.toBeNull();
            expect(entry?.component).toBeDefined();
            expect(entry?.defaults).toBeDefined();
            const mapped = entry?.mapBuilderProps?.({ title: 'Test', menu_items: [] });
            expect(mapped).toBeDefined();
            expect(typeof mapped === 'object').toBe(true);
        }
    });

    it('resolveComponentProps merges defaults and overrides (props update flow)', () => {
        const props = resolveComponentProps('webu_general_hero_01', {
            title: 'Custom title',
            buttonText: 'Shop now',
        });
        expect(props).toBeDefined();
        expect(props.title).toBe('Custom title');
        expect(props.buttonText).toBe('Shop now');
        // Defaults from schema should fill in other keys when merged
        const fromEmpty = resolveComponentProps('webu_general_hero_01', {});
        expect(fromEmpty).toBeDefined();
        expect(typeof fromEmpty).toBe('object');
    });

    it('resolveComponentProps parses propsText string and propagates aliases (heading)', () => {
        const propsText = JSON.stringify({ headline: 'Feature' });
        const props = resolveComponentProps('webu_general_heading_01', propsText);
        expect(props).toBeDefined();
        expect(props.headline).toBe('Feature');
        expect(props.title).toBe('Feature'); // alias propagation so pickFirstValue works
    });

    it('canvas renders Header section', () => {
        render(
            <BuilderCanvas
                sections={[
                    {
                        localId: 'h1',
                        type: 'webu_header_01',
                        propsText: JSON.stringify({ logoText: 'My Site', menu_items: [] }),
                        propsError: null,
                    },
                ]}
                selectedElementId={null}
                hoveredElementId={null}
                draggingComponentType={null}
                currentDropTarget={null}
                sectionDisplayLabelByKey={new Map([['webu_header_01', 'Header']])}
                onSelect={() => {}}
                onHover={() => {}}
                t={(k) => k}
            />
        );
        expect(screen.getByRole('region', { name: /builder canvas/i })).toBeInTheDocument();
        expect(document.querySelector('[data-builder-section-id="h1"]')).toBeInTheDocument();
        expect(document.querySelector('[data-builder-surface="true"]')).toBeInTheDocument();
    });

    it('canvas renders Footer section', () => {
        render(
            <BuilderCanvas
                sections={[
                    {
                        localId: 'f1',
                        type: 'webu_footer_01',
                        propsText: JSON.stringify({ logoText: 'Footer', copyright: '© 2026' }),
                        propsError: null,
                    },
                ]}
                selectedElementId={null}
                hoveredElementId={null}
                draggingComponentType={null}
                currentDropTarget={null}
                sectionDisplayLabelByKey={new Map([['webu_footer_01', 'Footer']])}
                onSelect={() => {}}
                onHover={() => {}}
                t={(k) => k}
            />
        );
        expect(document.querySelector('[data-builder-section-id="f1"]')).toBeInTheDocument();
    });

    it('canvas renders Hero section with props (props update rerenders)', () => {
        const { rerender } = render(
            <BuilderCanvas
                sections={[
                    {
                        localId: 'hero-1',
                        type: 'webu_general_hero_01',
                        propsText: JSON.stringify({
                            title: 'First title',
                            buttonText: 'Click',
                        }),
                        propsError: null,
                    },
                ]}
                selectedElementId={null}
                hoveredElementId={null}
                draggingComponentType={null}
                currentDropTarget={null}
                sectionDisplayLabelByKey={new Map([['webu_general_hero_01', 'Hero Section']])}
                onSelect={() => {}}
                onHover={() => {}}
                t={(k) => k}
            />
        );
        expect(screen.getByText('First title')).toBeInTheDocument();
        expect(screen.getByText('Click')).toBeInTheDocument();

        rerender(
            <BuilderCanvas
                sections={[
                    {
                        localId: 'hero-1',
                        type: 'webu_general_hero_01',
                        propsText: JSON.stringify({
                            title: 'Updated title',
                            buttonText: 'Updated button',
                        }),
                        propsError: null,
                    },
                ]}
                selectedElementId={null}
                hoveredElementId={null}
                draggingComponentType={null}
                currentDropTarget={null}
                sectionDisplayLabelByKey={new Map([['webu_general_hero_01', 'Hero Section']])}
                onSelect={() => {}}
                onHover={() => {}}
                t={(k) => k}
            />
        );
        expect(screen.getByText('Updated title')).toBeInTheDocument();
        expect(screen.getByText('Updated button')).toBeInTheDocument();
    });

    it('canvas renders Feature (Heading), CTA (Button), Card, and Grid sections', () => {
        render(
            <BuilderCanvas
                sections={[
                    { localId: 'head-1', type: 'webu_general_heading_01', propsText: JSON.stringify({ headline: 'Feature' }), propsError: null },
                    { localId: 'btn-1', type: 'webu_general_button_01', propsText: JSON.stringify({ button: 'CTA' }), propsError: null },
                    { localId: 'card-1', type: 'webu_general_card_01', propsText: JSON.stringify({ title: 'Card' }), propsError: null },
                    { localId: 'grid-1', type: 'webu_ecom_product_grid_01', propsText: JSON.stringify({ title: 'Grid' }), propsError: null },
                ]}
                selectedElementId={null}
                hoveredElementId={null}
                draggingComponentType={null}
                currentDropTarget={null}
                sectionDisplayLabelByKey={new Map([
                    ['webu_general_heading_01', 'Heading'],
                    ['webu_general_button_01', 'Button'],
                    ['webu_general_card_01', 'Card'],
                    ['webu_ecom_product_grid_01', 'Product Grid'],
                ])}
                onSelect={() => {}}
                onHover={() => {}}
                t={(k) => k}
            />
        );
        expect(screen.getByText('Feature')).toBeInTheDocument();
        expect(screen.getByText('CTA')).toBeInTheDocument();
        expect(screen.getAllByText('Card').length).toBeGreaterThan(0); // label + content
        expect(screen.getByText('Grid')).toBeInTheDocument();
        expect(document.querySelector('[data-builder-section-id="head-1"]')).toBeInTheDocument();
        expect(document.querySelector('[data-builder-section-id="btn-1"]')).toBeInTheDocument();
        expect(document.querySelector('[data-builder-section-id="card-1"]')).toBeInTheDocument();
        expect(document.querySelector('[data-builder-section-id="grid-1"]')).toBeInTheDocument();
    });
});
