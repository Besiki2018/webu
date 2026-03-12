import { describe, expect, it } from 'vitest';
import { buildSelectedSectionInspectorState } from '../selectedSectionInspectorState';

describe('buildSelectedSectionInspectorState', () => {
    it('filters display fields down to the selected component scope', () => {
        const state = buildSelectedSectionInspectorState({
            selectedSectionDraft: {
                localId: 'grid-1',
                type: 'webu_general_grid_01',
            },
            selectedSectionEffectiveType: null,
            selectedSectionEffectiveParsedProps: {
                items: [
                    {
                        image: '/card-1.jpg',
                        imageAlt: 'Card 1',
                        title: 'Starter',
                        link: '/starter',
                    },
                    {
                        image: '/card-2.jpg',
                        imageAlt: 'Card 2',
                        title: 'Scale',
                        link: '/scale',
                    },
                ],
                columns: 3,
            },
            selectedSectionSchemaProperties: {
                items: {},
            },
            selectedSectionSchemaHtmlTemplate: '',
            selectedBuilderTarget: {
                sectionLocalId: 'grid-1',
                sectionKey: 'webu_general_grid_01',
                path: 'items.0.title',
                componentPath: 'items.0',
                editableFields: ['items.0.title'],
                allowedUpdates: {
                    scope: 'element',
                    operationTypes: ['set-prop'],
                    fieldPaths: [
                        'items.0.title',
                        'items.0.image',
                        'items.0.imageAlt',
                        'items.0.link',
                    ],
                    sectionFieldPaths: ['title', 'items', 'columns'],
                },
            },
            previewMode: 'desktop',
            interactionState: 'normal',
            elementorLike: true,
            normalizeSectionTypeKey: (value) => value.trim().toLowerCase(),
            buildControlMeta: (path, type, label) => ({
                type,
                label,
                group: 'content',
                responsive: false,
                stateful: false,
                dynamic_capable: true,
            }),
        });

        expect(state.inspectorTarget?.componentPath).toBe('items.0');
        expect(state.schemaKey).toBe('webu_general_grid_01');
        expect(state.editableSchemaFieldsForDisplay.map((field) => field.path.join('.'))).toEqual([
            'items.0.image',
            'items.0.imageAlt',
            'items.0.title',
            'items.0.link',
        ]);
    });

    it('resolves aliased section types through the canonical registry schema', () => {
        const state = buildSelectedSectionInspectorState({
            selectedSectionDraft: {
                localId: 'heading-1',
                type: 'heading',
            },
            selectedSectionEffectiveType: null,
            selectedSectionEffectiveParsedProps: {
                subtitle: 'Build faster',
            },
            selectedSectionSchemaProperties: {
                title: {},
            },
            selectedSectionSchemaHtmlTemplate: '',
            selectedBuilderTarget: null,
            previewMode: 'desktop',
            interactionState: 'normal',
            normalizeSectionTypeKey: (value) => value.trim().toLowerCase(),
            buildControlMeta: (path, type, label) => ({
                type,
                label,
                group: 'content',
                responsive: false,
                stateful: false,
                dynamic_capable: true,
            }),
        });

        expect(state.schemaKey).toBe('webu_general_heading_01');
        expect(state.usesSafeFallbackInspector).toBe(false);
        expect(state.resolvedProps?.headline).toBe('New heading');
        expect(state.editableSchemaFields.map((field) => field.path.join('.'))).toContain('headline');
        expect(state.editableSchemaFields.map((field) => field.path.join('.'))).toContain('title');
        expect(state.editableSchemaFields.map((field) => field.path.join('.'))).toContain('subtitle');
    });

    it('ignores stale element targets that do not map to the selected component schema', () => {
        const state = buildSelectedSectionInspectorState({
            selectedSectionDraft: {
                localId: 'hero-1',
                type: 'webu_general_heading_01',
            },
            selectedSectionEffectiveType: null,
            selectedSectionEffectiveParsedProps: {
                title: 'Welcome',
                subtitle: 'Build faster',
            },
            selectedSectionSchemaProperties: {
                title: {},
            },
            selectedSectionSchemaHtmlTemplate: '',
            selectedBuilderTarget: {
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_heading_01',
                path: 'ghost.title',
                componentPath: 'ghost',
                editableFields: ['ghost.title'],
                allowedUpdates: {
                    scope: 'element',
                    operationTypes: ['set-prop'],
                    fieldPaths: ['ghost.title'],
                    sectionFieldPaths: ['title', 'subtitle'],
                },
            },
            previewMode: 'desktop',
            interactionState: 'normal',
            elementorLike: true,
            normalizeSectionTypeKey: (value) => value.trim().toLowerCase(),
            buildControlMeta: (path, type, label) => ({
                type,
                label,
                group: 'content',
                responsive: false,
                stateful: false,
                dynamic_capable: true,
            }),
        });

        expect(state.inspectorTarget).toBeNull();
        expect(state.editableSchemaFieldsForDisplay.map((field) => field.path.join('.'))).toContain('headline');
        expect(state.editableSchemaFieldsForDisplay.map((field) => field.path.join('.'))).toContain('subtitle');
    });

    it('uses a safe empty inspector fallback when no registry schema exists', () => {
        const state = buildSelectedSectionInspectorState({
            selectedSectionDraft: {
                localId: 'custom-1',
                type: 'custom_unknown_block',
            },
            selectedSectionEffectiveType: null,
            selectedSectionEffectiveParsedProps: {
                title: 'Custom title',
            },
            selectedSectionSchemaProperties: {
                title: { type: 'string' },
            },
            selectedSectionSchemaHtmlTemplate: '',
            selectedBuilderTarget: null,
            previewMode: 'desktop',
            interactionState: 'normal',
            elementorLike: true,
            normalizeSectionTypeKey: (value) => value.trim().toLowerCase(),
            buildControlMeta: (path, type, label) => ({
                type,
                label,
                group: 'content',
                responsive: false,
                stateful: false,
                dynamic_capable: true,
            }),
        });

        expect(state.schema).toBeNull();
        expect(state.schemaKey).toBeNull();
        expect(state.usesSafeFallbackInspector).toBe(true);
        expect(state.editableSchemaFields).toHaveLength(0);
        expect(state.editableSchemaFieldsForDisplay).toHaveLength(0);
    });
});
