import { describe, expect, it } from 'vitest';
import { buildSelectedSectionInspectorState, type SelectedSectionInspectorField } from '../selectedSectionInspectorState';

interface TestControlMeta {
    type: string;
    label: string;
    group: string;
    responsive: boolean;
    stateful: boolean;
    dynamic_capable: boolean;
}

function makeField(path: string, label: string): SelectedSectionInspectorField<TestControlMeta> {
    return {
        path: path.split('.'),
        type: 'string',
        label,
        definition: { control_group: 'content' },
        control_meta: {
            type: 'string',
            label,
            group: 'content',
            responsive: false,
            stateful: false,
            dynamic_capable: true,
        },
    };
}

describe('buildSelectedSectionInspectorState', () => {
    it('filters display fields down to the selected component scope', () => {
        const state = buildSelectedSectionInspectorState({
            selectedSectionDraft: {
                localId: 'header-1',
                type: 'test-menu',
            },
            selectedSectionEffectiveType: null,
            selectedSectionEffectiveParsedProps: {
                menu_items: [
                    { label: 'Home' },
                    { label: 'About' },
                ],
            },
            selectedSectionSchemaProperties: {
                menu_items: {},
            },
            selectedSectionSchemaHtmlTemplate: '',
            selectedBuilderTarget: {
                sectionLocalId: 'header-1',
                sectionKey: 'test-menu',
                path: 'menu_items.0',
                componentPath: 'menu_items.0',
                editableFields: ['menu_items.0.label'],
                allowedUpdates: {
                    scope: 'element',
                    operationTypes: ['set-prop'],
                    fieldPaths: ['menu_items.0.label'],
                    sectionFieldPaths: ['menu_items.0.label'],
                },
            },
            previewMode: 'desktop',
            interactionState: 'normal',
            elementorLike: true,
            normalizeSectionTypeKey: (value) => value.trim().toLowerCase(),
            collectFallbackSchemaFields: () => [
                makeField('menu_items.0.label', 'Item 1'),
                makeField('menu_items.1.label', 'Item 2'),
            ],
            buildControlMeta: (path, type, label) => ({
                type,
                label,
                group: 'content',
                responsive: false,
                stateful: false,
                dynamic_capable: true,
            }),
        });

        expect(state.inspectorTarget?.componentPath).toBe('menu_items.0');
        expect(state.editableSchemaFields).toHaveLength(2);
        expect(state.editableSchemaFieldsForDisplay.map((field) => field.path.join('.'))).toEqual(['menu_items.0.label']);
    });

    it('removes ecommerce-bound auto fields from editable output', () => {
        const state = buildSelectedSectionInspectorState({
            selectedSectionDraft: {
                localId: 'products-1',
                type: 'custom-grid',
            },
            selectedSectionEffectiveType: null,
            selectedSectionEffectiveParsedProps: {},
            selectedSectionSchemaProperties: {
                title: {},
            },
            selectedSectionSchemaHtmlTemplate: '<section data-webby-ecommerce-products></section>',
            selectedBuilderTarget: null,
            previewMode: 'desktop',
            interactionState: 'normal',
            normalizeSectionTypeKey: (value) => value.trim().toLowerCase(),
            collectFallbackSchemaFields: () => [
                makeField('title', 'Title'),
                makeField('name', 'Name'),
                makeField('price', 'Price'),
                makeField('heading_1', 'Heading 1'),
            ],
            buildControlMeta: (path, type, label) => ({
                type,
                label,
                group: 'content',
                responsive: false,
                stateful: false,
                dynamic_capable: true,
            }),
        });

        expect(state.usesEcommerceProductsBinding).toBe(true);
        expect(state.editableSchemaFields.map((field) => field.path.join('.'))).toEqual(['title']);
    });
});
