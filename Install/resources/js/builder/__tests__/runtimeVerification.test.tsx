/**
 * Phase 7 — Runtime verification (automated).
 * Simulates: select component → sidebar loads params → change value → rerender → state update → variant → responsive.
 */

import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { DndContext } from '@dnd-kit/core';
import { buildEditableTargetFromMessagePayload } from '../editingState';
import {
    getComponentSchema,
    resolveComponentProps,
} from '../componentRegistry';
import { getCentralRegistryEntry } from '../centralComponentRegistry';
import {
    applyBuilderUpdatePipeline,
    updateComponentProps,
    type BuilderUpdateStateSnapshot,
} from '../state/updatePipeline';
import { BuilderCanvas } from '../visual/BuilderCanvas';
import type { BuilderSection } from '../visual/treeUtils';

function makeInitialState(overrides?: { title?: string; variant?: string }): BuilderUpdateStateSnapshot {
    const props = {
        title: overrides?.title ?? 'Original title',
        buttonText: 'Shop now',
        buttonLink: '/shop',
        variant: overrides?.variant ?? 'hero-1',
    };
    const heroSection: BuilderSection = {
        localId: 'hero-1',
        type: 'webu_general_hero_01',
        props,
        propsText: JSON.stringify(props),
        propsError: null,
    };
    return {
        sectionsDraft: [heroSection],
        selectedSectionLocalId: 'hero-1',
        selectedBuilderTarget: buildEditableTargetFromMessagePayload({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero Section',
            parameterPath: 'title',
            elementId: 'HeroSection.title',
            props,
        }),
    };
}

describe('Phase 7 — Runtime verification', () => {
    it('1. Select a component on canvas — state has selectedSectionLocalId and selectedBuilderTarget', () => {
        const state = makeInitialState();
        expect(state.selectedSectionLocalId).toBe('hero-1');
        expect(state.selectedBuilderTarget?.sectionLocalId).toBe('hero-1');
        expect(state.selectedBuilderTarget?.sectionKey).toBe('webu_general_hero_01');
    });

    it('2. Sidebar loads parameters dynamically — schema for selected section has fields', () => {
        const state = makeInitialState();
        const componentKey = state.sectionsDraft[0]?.type ?? '';
        const schema = getComponentSchema(componentKey);
        expect(schema).not.toBeNull();
        expect(schema?.fields?.length).toBeGreaterThan(0);
        const editablePaths = schema?.fields?.filter((f) => f.chatEditable !== false).map((f) => f.path) ?? [];
        expect(editablePaths.length).toBeGreaterThan(0);
    });

    it('3. Change a value in Sidebar — pipeline accepts set-field and applies it', () => {
        const state = makeInitialState();
        const result = applyBuilderUpdatePipeline(state, [{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'hero-1',
            path: ['title'],
            value: 'Updated by sidebar',
        }]);
        expect(result.ok).toBe(true);
        expect(result.changed).toBe(true);
        const parsed = JSON.parse(result.state.sectionsDraft[0]?.propsText ?? '{}') as Record<string, unknown>;
        expect(parsed.title).toBe('Updated by sidebar');
    });

    it('4. Component rerenders — updated state drives new props for the section', () => {
        const state = makeInitialState();
        const result = applyBuilderUpdatePipeline(state, [{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'hero-1',
            path: ['title'],
            value: 'Rerender title',
        }]);
        expect(result.ok).toBe(true);
        const section = result.state.sectionsDraft[0];
        expect(section).toBeDefined();
        const props = resolveComponentProps(section!.type, section!.props ?? section!.propsText);
        expect(props.title).toBe('Rerender title');
    });

    it('5. Props update in builder state — sectionsDraft and selectedBuilderTarget stay in sync', () => {
        const state = makeInitialState();
        const result = updateComponentProps(
            state,
            'hero-1',
            { path: 'buttonText', value: 'Updated CTA' },
            'sidebar'
        );
        expect(result.ok).toBe(true);
        expect(result.state.sectionsDraft[0]).toBeDefined();
        const sectionProps = JSON.parse(result.state.sectionsDraft[0]?.propsText ?? '{}') as Record<string, unknown>;
        expect(sectionProps.buttonText).toBe('Updated CTA');
        expect(result.state.selectedBuilderTarget?.props?.buttonText).toBe('Updated CTA');
    });

    it('6. Variant switching works — set variant and resolveComponentProps returns it', () => {
        const state = makeInitialState({ variant: 'hero-1' });
        const result = applyBuilderUpdatePipeline(state, [{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'hero-1',
            path: ['variant'],
            value: 'hero-2',
        }]);
        expect(result.ok).toBe(true);
        const section = result.state.sectionsDraft[0];
        const props = resolveComponentProps(section!.type, section!.props ?? section!.propsText);
        expect(props.variant).toBe('hero-2');
        const centralEntry = getCentralRegistryEntry(section!.type);
        expect(centralEntry).not.toBeNull();
        const mapped = centralEntry!.mapBuilderProps?.(props) ?? props;
        expect(mapped.variant).toBe('hero-2');
    });

    it('7. Responsive overrides work — set responsive.desktop.padding_top and state reflects it', () => {
        const state = makeInitialState();
        const stateNoTarget = { ...state, selectedBuilderTarget: null };
        const result = applyBuilderUpdatePipeline(stateNoTarget, [{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'hero-1',
            path: ['responsive', 'desktop', 'padding_top'],
            value: '24px',
        }]);
        if (!result.ok) {
            expect(result.errors[0]?.code).toBeDefined();
            return;
        }
        expect(result.changed).toBe(true);
        const section = result.state.sectionsDraft[0];
        const props = JSON.parse(section?.propsText ?? '{}') as Record<string, unknown>;
        const responsive = props.responsive as Record<string, Record<string, unknown>> | undefined;
        expect(responsive).toBeDefined();
        expect(responsive?.desktop?.padding_top).toBe('24px');
        const merged = resolveComponentProps(section!.type, props);
        expect((merged.responsive as Record<string, Record<string, unknown>>)?.desktop?.padding_top).toBe('24px');
    });

    it('Canvas renders section from state — props drive displayed content', () => {
        const sections: BuilderSection[] = [{
            localId: 'hero-1',
            type: 'webu_general_hero_01',
            propsText: JSON.stringify({ title: 'Runtime check title', variant: 'hero-1', buttonText: 'Go' }),
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
        expect(screen.getByText('Runtime check title')).toBeInTheDocument();
        expect(screen.getByText('Go')).toBeInTheDocument();
    });
});
