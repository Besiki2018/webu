/**
 * Phase 9 — Basic validation tests for the schema-driven builder architecture.
 * 1. Component registry integrity
 * 2. Schema/defaults consistency
 * 3. Component render-from-props test
 * 4. Sidebar field generation test
 * 5. Prop update rerender test
 * 6. Variant rendering test
 * 7. Legacy compatibility test
 */

import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { DndContext } from '@dnd-kit/core';
import {
    getComponentSchema,
    getComponentSchemaJson,
    getComponentRuntimeEntry,
    getDefaultProps,
    getAvailableComponents,
    resolveComponentProps,
} from '../componentRegistry';
import { getCentralRegistryEntry } from '../centralComponentRegistry';
import { applyBuilderUpdatePipeline } from '../state/updatePipeline';
import { BuilderCanvas } from '../visual/BuilderCanvas';
import type { BuilderSection } from '../visual/treeUtils';

const MIGRATED_IDS = ['webu_header_01', 'webu_footer_01', 'webu_general_hero_01'] as const;
const LEGACY_IDS = ['webu_general_heading_01', 'webu_general_cta_01', 'webu_general_card_01'] as const;

describe('Phase 9 — Architecture validation', () => {
    it('1. Component registry integrity — every registered id has schema, runtime entry, and defaults', () => {
        const ids = getAvailableComponents();
        expect(ids.length).toBeGreaterThan(0);
        for (const id of ids) {
            const schema = getComponentSchema(id);
            const runtime = getComponentRuntimeEntry(id);
            const defaults = getDefaultProps(id);
            expect(schema, `schema for ${id}`).not.toBeNull();
            expect(runtime, `runtime for ${id}`).not.toBeNull();
            expect(defaults).toBeDefined();
            expect(typeof defaults === 'object').toBe(true);
            expect(schema?.componentKey).toBe(id);
            expect(runtime?.componentKey).toBe(id);
        }
    });

    it('2. Schema/defaults consistency — defaults from schema; resolveComponentProps merges overrides', () => {
        for (const id of MIGRATED_IDS) {
            const schema = getComponentSchema(id);
            const defaults = getDefaultProps(id);
            expect(schema).not.toBeNull();
            expect(schema?.defaultProps).toBeDefined();
            expect(Object.keys(defaults).length).toBeGreaterThanOrEqual(0);
            const merged = resolveComponentProps(id, { title: 'Override' });
            expect(merged).toBeDefined();
            if (id === 'webu_general_hero_01') expect(merged.title).toBe('Override');
        }
    });

    it('3. Component render-from-props — canvas renders section using only props from state', () => {
        const sections: BuilderSection[] = [{
            localId: 'hero-1',
            type: 'webu_general_hero_01',
            propsText: JSON.stringify({
                title: 'Render-from-props title',
                subtitle: 'Subtitle from props',
                buttonText: 'Click',
                variant: 'hero-1',
            }),
            propsError: null,
        }];
        render(
            <DndContext>
                <BuilderCanvas
                    sections={sections}
                    selectedElementId={null}
                    hoveredElementId={null}
                    draggingComponentType={null}
                    currentDropTarget={null}
                    sectionDisplayLabelByKey={new Map([['webu_general_hero_01', 'Hero']])}
                    onSelect={() => {}}
                    onHover={() => {}}
                    onDeselect={() => {}}
                    t={(k) => k}
                />
            </DndContext>
        );
        expect(screen.getByText('Render-from-props title')).toBeInTheDocument();
        expect(screen.getByText('Subtitle from props')).toBeInTheDocument();
        expect(screen.getByText('Click')).toBeInTheDocument();
    });

    it('4. Sidebar field generation — schema has fields; getComponentSchemaJson has properties for controls', () => {
        const schema = getComponentSchema('webu_general_hero_01');
        expect(schema).not.toBeNull();
        expect(schema?.fields?.length).toBeGreaterThan(0);
        for (const field of schema?.fields ?? []) {
            expect(field.path).toBeTruthy();
            expect(field.type).toBeTruthy();
            expect(field.group).toBeTruthy();
        }
        const schemaJson = getComponentSchemaJson('webu_general_hero_01');
        expect(schemaJson?.properties).toBeDefined();
        expect(typeof schemaJson?.properties === 'object').toBe(true);
    });

    it('4b. Every registry id has schema JSON for sidebar — getComponentSchemaJson usable for all (controls can be generated)', () => {
        const ids = getAvailableComponents();
        for (const id of ids) {
            const schemaJson = getComponentSchemaJson(id);
            expect(schemaJson, `getComponentSchemaJson("${id}")`).not.toBeNull();
            expect(typeof schemaJson).toBe('object');
            expect(schemaJson?.properties !== undefined || (schemaJson && Object.keys(schemaJson).length > 0)).toBe(true);
        }
    });

    it('5. Prop update rerender — pipeline set-field updates state; resolveComponentProps reflects new value', () => {
        const initialState = {
            sectionsDraft: [{
                localId: 'hero-1',
                type: 'webu_general_hero_01',
                propsText: JSON.stringify({ title: 'Before', variant: 'hero-1' }),
                propsError: null,
            }],
            selectedSectionLocalId: 'hero-1',
            selectedBuilderTarget: null,
        };
        const result = applyBuilderUpdatePipeline(initialState, [{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'hero-1',
            path: ['title'],
            value: 'After update',
        }]);
        expect(result.ok).toBe(true);
        expect(result.changed).toBe(true);
        const section = result.state.sectionsDraft[0];
        const parsed = JSON.parse(section?.propsText ?? '{}') as Record<string, unknown>;
        expect(parsed.title).toBe('After update');
        const merged = resolveComponentProps(section!.type, section!.props ?? section!.propsText);
        expect(merged.title).toBe('After update');
    });

    it('5b. Prop update flows to canvas — pipeline result.state.sectionsDraft passed to BuilderCanvas renders updated value', () => {
        const initialState = {
            sectionsDraft: [{
                localId: 'h1',
                type: 'webu_general_hero_01',
                propsText: JSON.stringify({ title: 'Initial', variant: 'hero-1' }),
                propsError: null,
            }],
            selectedSectionLocalId: null,
            selectedBuilderTarget: null,
        };
        const result = applyBuilderUpdatePipeline(initialState, [{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'h1',
            path: ['title'],
            value: 'Updated by pipeline',
        }]);
        expect(result.ok).toBe(true);
        expect(result.state.sectionsDraft.length).toBe(1);
        render(
            <DndContext>
                <BuilderCanvas
                    sections={result.state.sectionsDraft}
                    selectedElementId={null}
                    hoveredElementId={null}
                    draggingComponentType={null}
                    currentDropTarget={null}
                    sectionDisplayLabelByKey={new Map([['webu_general_hero_01', 'Hero']])}
                    onSelect={() => {}}
                    onHover={() => {}}
                    onDeselect={() => {}}
                    t={(k) => k}
                />
            </DndContext>
        );
        expect(screen.getByText('Updated by pipeline')).toBeInTheDocument();
    });

    it('6. Variant rendering — variant in props; schema has variants; mapBuilderProps passes variant', () => {
        const state = {
            sectionsDraft: [{
                localId: 'hero-1',
                type: 'webu_general_hero_01',
                propsText: JSON.stringify({ title: 'Hero', variant: 'hero-2' }),
                propsError: null,
            }],
            selectedSectionLocalId: null,
            selectedBuilderTarget: null,
        };
        const section = state.sectionsDraft[0];
        const props = resolveComponentProps(section!.type, section!.props ?? section!.propsText);
        expect(props.variant).toBe('hero-2');
        const schema = getComponentSchema('webu_general_hero_01');
        expect(schema?.variants ?? schema?.variantDefinitions).toBeDefined();
        const central = getCentralRegistryEntry(section!.type);
        expect(central).not.toBeNull();
        const mapped = central!.mapBuilderProps?.(props) ?? props;
        expect(mapped.variant).toBe('hero-2');
    });

    it('7. Legacy compatibility — legacy section types have schema + runtime entry and resolveComponentProps works', () => {
        for (const id of LEGACY_IDS) {
            const schema = getComponentSchema(id);
            const runtime = getComponentRuntimeEntry(id);
            expect(schema, `schema for legacy ${id}`).not.toBeNull();
            expect(runtime, `runtime for legacy ${id}`).not.toBeNull();
            expect(schema?.componentKey).toBe(id);
            expect(runtime?.componentKey).toBe(id);
            const defaults = getDefaultProps(id);
            expect(defaults).toBeDefined();
            const merged = resolveComponentProps(id, {});
            expect(merged).toBeDefined();
            expect(typeof merged === 'object').toBe(true);
        }
    });
});
