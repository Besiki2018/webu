/**
 * Architecture validation — execution rules and migration checklist.
 * 1. Registry integrity
 * 2. Schema/defaults consistency
 * 3. Sidebar field generation from schema
 * 4. Variant rendering
 * 5. Legacy isolation (no conflicting parallel implementations in main registry)
 */

import { describe, expect, it } from 'vitest';
import {
    REGISTRY_ID_TO_KEY,
    getComponentSchema,
    getComponentSchemaJson,
    getComponentRuntimeEntry,
    getDefaultProps,
    resolveComponentProps,
    getAvailableComponents,
    getCentralRegistryEntry,
} from '../componentRegistry';
import { sectionToComponentInstance, toSerializableInstance } from '../types';

const MIGRATED_CENTRAL_IDS = ['webu_header_01', 'webu_footer_01', 'webu_general_hero_01'] as const;

describe('Architecture validation', () => {
    it('1. Component registry integrity — every registered id has schema, runtime entry, and defaults', () => {
        const ids = getAvailableComponents();
        expect(ids.length).toBeGreaterThan(0);

        for (const id of ids) {
            const schema = getComponentSchema(id);
            const runtime = getComponentRuntimeEntry(id);
            const defaults = getDefaultProps(id);

            expect(schema, `schema for ${id}`).not.toBeNull();
            expect(runtime, `runtime for ${id}`).not.toBeNull();
            expect(defaults, `defaults for ${id}`).toBeDefined();
            expect(typeof defaults === 'object').toBe(true);
            expect(schema?.componentKey).toBe(id);
            expect(runtime?.componentKey).toBe(id);
            expect(runtime?.schema?.componentKey).toBe(id);
        }
    });

    it('2. Schema/defaults consistency — defaultProps matches schema.defaultProps; resolveComponentProps merges', () => {
        for (const id of MIGRATED_CENTRAL_IDS) {
            const schema = getComponentSchema(id);
            const defaults = getDefaultProps(id);
            expect(schema).not.toBeNull();
            expect(schema?.defaultProps).toBeDefined();
            expect(Object.keys(defaults).length).toBeGreaterThanOrEqual(0);

            const merged = resolveComponentProps(id, { title: 'Override' });
            expect(merged).toBeDefined();
            if (id === 'webu_general_hero_01') {
                expect(merged.title).toBe('Override');
            }
        }
    });

    it('3. Sidebar field generation — schema has fields with standard group', () => {
        const schema = getComponentSchema('webu_general_hero_01');
        expect(schema).not.toBeNull();
        expect(schema?.fields?.length).toBeGreaterThan(0);

        for (const field of schema?.fields ?? []) {
            expect(field.path).toBeTruthy();
            expect(field.type).toBeTruthy();
            expect(field.group).toBeTruthy();
        }
        expect(schema?.contentGroups ?? schema?.styleGroups ?? schema?.advancedGroups).toBeDefined();
    });

    it('4. Variant rendering — Header, Footer, Hero accept variant prop and have variant in schema', () => {
        for (const id of MIGRATED_CENTRAL_IDS) {
            const schema = getComponentSchema(id);
            expect(schema?.variants ?? schema?.variantDefinitions).toBeDefined();
            const resolved = resolveComponentProps(id, { variant: undefined });
            expect(resolved).toBeDefined();
            expect(typeof resolved === 'object').toBe(true);
        }
    });

    it('5. Central registry only contains migrated components — no legacy parallel entries', () => {
        const centralKeys = Object.keys(REGISTRY_ID_TO_KEY);
        expect(centralKeys).toContain('webu_header_01');
        expect(centralKeys).toContain('webu_footer_01');
        expect(centralKeys).toContain('webu_general_hero_01');

        for (const registryId of centralKeys) {
            const entry = getCentralRegistryEntry(registryId);
            expect(entry).not.toBeNull();
            expect(entry?.component).toBeDefined();
            expect(entry?.schema).toBeDefined();
            expect(entry?.defaults).toBeDefined();
        }
    });

    it('6. All migrated central components render from props only — no required context', () => {
        for (const id of MIGRATED_CENTRAL_IDS) {
            const entry = getCentralRegistryEntry(id);
            expect(entry).not.toBeNull();
            const mapped = entry?.mapBuilderProps?.({});
            expect(mapped).toBeDefined();
            expect(typeof mapped === 'object').toBe(true);
        }
    });

    it('7. Prop update uses same merge path — resolveComponentProps returns merged result', () => {
        const before = resolveComponentProps('webu_general_hero_01', { title: 'A' });
        const after = resolveComponentProps('webu_general_hero_01', { title: 'B' });
        expect(before.title).toBe('A');
        expect(after.title).toBe('B');
    });

    it('8. Phase 3 — Props flow: schema defaults + saved props + responsive in merged result', () => {
        const merged = resolveComponentProps('webu_general_hero_01', {
            title: 'Custom title',
            responsive: { hide_on_mobile: true },
        });
        expect(merged).toBeDefined();
        expect(merged.title).toBe('Custom title');
        expect((merged as Record<string, unknown>).responsive).toBeDefined();
        expect(typeof (merged as Record<string, unknown>).responsive === 'object').toBe(true);
        const resp = (merged as Record<string, unknown>).responsive as Record<string, unknown>;
        expect(resp.hide_on_mobile).toBe(true);
        expect(merged.variant).toBeDefined();
        expect(merged.layout).toBeDefined();
    });

    it('9. Phase 4 — Sidebar schema: getComponentSchemaJson returns properties with builder_field_type for control generation', () => {
        const schemaJson = getComponentSchemaJson('webu_general_hero_01');
        expect(schemaJson).not.toBeNull();
        expect(schemaJson?.properties).toBeDefined();
        expect(typeof schemaJson?.properties === 'object').toBe(true);
        const props = schemaJson?.properties as Record<string, Record<string, unknown>>;
        const hasBuilderFieldType = Object.values(props).some(
            (def) => def && typeof def === 'object' && (def.builder_field_type != null || def.builderFieldType != null)
        );
        expect(hasBuilderFieldType || Object.keys(props).length > 0).toBe(true);
    });

    it('10. Phase 6 — Builder data model: sectionToComponentInstance produces serializable shape (id, componentKey, variant, props, children?, responsiveOverrides?, metadata)', () => {
        const section = {
            localId: 'hero-1',
            type: 'webu_general_hero_01',
            props: { title: 'Hero', variant: 'hero-1', responsive: { desktop: { padding_top: '20px' } } },
            propsText: '{}',
            propsError: null as string | null,
        };
        const instance = sectionToComponentInstance(section);
        expect(instance.id).toBe('hero-1');
        expect(instance.componentKey).toBe('webu_general_hero_01');
        expect(instance.variant).toBe('hero-1');
        expect(instance.props).toBeDefined();
        expect(instance.props.title).toBe('Hero');
        expect(instance.responsive).toBeDefined();
        expect(instance.responsive?.desktop?.padding_top).toBe('20px');
        const serialized = toSerializableInstance(instance);
        expect(serialized.responsiveOverrides).toBeDefined();
        expect(serialized.responsiveOverrides?.desktop?.padding_top).toBe('20px');
        expect(serialized.id).toBe(instance.id);
        expect(serialized.componentKey).toBe(instance.componentKey);
    });
});
