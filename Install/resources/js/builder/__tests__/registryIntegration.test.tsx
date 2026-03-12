/**
 * Phase 1 — Verify component registry integration with the real builder runtime.
 * Phase 2 — Verify canvas renderer uses the registry (lookup → merge → render).
 */

import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { DndContext } from '@dnd-kit/core';
import { BuilderCanvas } from '../visual/BuilderCanvas';
import type { BuilderSection } from '../visual/treeUtils';
import {
    getComponentRuntimeEntry,
    getComponentSchema,
    getDefaultProps,
    getAvailableComponents,
} from '../componentRegistry';
import {
    REGISTRY_ID_TO_KEY,
    getCentralRegistryEntry,
    isInCentralRegistry,
} from '../centralComponentRegistry';

/** Migrated components with full schema-driven flow (central registry). */
const CENTRAL_REGISTRY_IDS = [
    'webu_header_01',        // Header (+ Navigation)
    'webu_footer_01',       // Footer
    'webu_general_hero_01', // Hero
] as const;

/** Key section types that must be in main REGISTRY (canvas/sidebar/pipeline). */
const MAIN_REGISTRY_IDS = [
    'webu_header_01',
    'webu_footer_01',
    'webu_general_hero_01',
    'webu_general_cta_01',       // CTA
    'webu_general_heading_01',   // Feature / Heading
    'webu_general_card_01',      // Card
    'webu_ecom_product_grid_01',  // Grid
] as const;

describe('Phase 1 — Component registry integration', () => {
    it('central registry file: each entry has component, schema, defaults', () => {
        for (const registryId of CENTRAL_REGISTRY_IDS) {
            const entry = getCentralRegistryEntry(registryId);
            expect(entry, `getCentralRegistryEntry("${registryId}")`).not.toBeNull();
            expect(entry?.component, `component for ${registryId}`).toBeDefined();
            expect(typeof entry?.component === 'function').toBe(true);
            expect(entry?.schema, `schema for ${registryId}`).toBeDefined();
            expect(entry?.defaults, `defaults for ${registryId}`).toBeDefined();
            expect(typeof entry?.defaults === 'object').toBe(true);
        }
    });

    it('REGISTRY_ID_TO_KEY maps all central components', () => {
        expect(Object.keys(REGISTRY_ID_TO_KEY)).toEqual(
            expect.arrayContaining([...CENTRAL_REGISTRY_IDS])
        );
        for (const id of CENTRAL_REGISTRY_IDS) {
            expect(REGISTRY_ID_TO_KEY[id]).toBeTruthy();
            expect(isInCentralRegistry(id)).toBe(true);
        }
    });

    it('all migrated + key section types are in main REGISTRY with schema and defaults', () => {
        const available = getAvailableComponents();
        for (const id of MAIN_REGISTRY_IDS) {
            expect(available, `main REGISTRY contains ${id}`).toContain(id);
            const schema = getComponentSchema(id);
            const defaults = getDefaultProps(id);
            expect(schema, `schema for ${id}`).not.toBeNull();
            expect(schema?.componentKey).toBe(id);
            expect(defaults).toBeDefined();
            expect(typeof defaults === 'object').toBe(true);
        }
    });

    it('runtime entry exists for every main REGISTRY id (canvas can resolve)', () => {
        for (const id of MAIN_REGISTRY_IDS) {
            const entry = getComponentRuntimeEntry(id);
            expect(entry, `getComponentRuntimeEntry("${id}")`).not.toBeNull();
            expect(entry?.componentKey).toBe(id);
            expect(entry?.component).toBeDefined();
            expect(entry?.schema).not.toBeNull();
            expect(entry?.defaults).toBeDefined();
        }
    });

    it('central registry entries are a subset of main REGISTRY (no orphan keys)', () => {
        const centralIds = Object.keys(REGISTRY_ID_TO_KEY);
        const available = getAvailableComponents();
        for (const id of centralIds) {
            expect(available).toContain(id);
            expect(getCentralRegistryEntry(id)).not.toBeNull();
        }
    });
});

describe('Phase 2 — Canvas renderer uses registry', () => {
    it('canvas renders sections via registry (lookup → merge defaults + props → render)', () => {
        const sections: BuilderSection[] = [
            {
                localId: 'header-1',
                type: 'webu_header_01',
                propsText: JSON.stringify({ logoText: 'Phase2CanvasLogo', variant: 'header-1' }),
                propsError: null,
            },
        ];
        render(
            <DndContext>
                <BuilderCanvas
                    sections={sections}
                    selectedElementId={null}
                    hoveredElementId={null}
                    draggingComponentType={null}
                    currentDropTarget={null}
                    sectionDisplayLabelByKey={new Map([['webu_header_01', 'Header']])}
                    onSelect={() => {}}
                    onHover={() => {}}
                    onDeselect={() => {}}
                    t={(k) => k}
                />
            </DndContext>
        );
        expect(screen.getByText('Phase2CanvasLogo')).toBeInTheDocument();
    });

    it('canvas does not import Header/Footer/Hero directly (registry-only resolution)', () => {
        const fs = require('node:fs');
        const path = require('node:path');
        const canvasPath = path.resolve(__dirname, '../visual/BuilderCanvas.tsx');
        const content = fs.readFileSync(canvasPath, 'utf8');
        expect(content).not.toMatch(/from\s+['\"][^'"]*layout\/Header['\"]/);
        expect(content).not.toMatch(/from\s+['\"][^'"]*layout\/Footer['\"]/);
        expect(content).not.toMatch(/from\s+['\"][^'"]*sections\/Hero['\"]/);
    });
});
