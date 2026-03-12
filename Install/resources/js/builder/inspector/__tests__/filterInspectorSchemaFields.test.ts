import { describe, expect, it } from 'vitest';

import { filterInspectorSchemaFields, type InspectorSchemaField } from '../filterInspectorSchemaFields';

function makeField(path: string, label: string): InspectorSchemaField {
    return {
        path: path.split('.'),
        type: 'string',
        label,
        definition: {
            type: 'string',
        },
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

describe('filterInspectorSchemaFields', () => {
    it('keeps only the clicked component scope fields instead of the whole section', () => {
        const fields = [
            makeField('title', 'Section title'),
            makeField('menu_items.0.label', 'Menu label'),
            makeField('menu_items.0.url', 'Menu URL'),
            makeField('menu_items.1.label', 'Second menu label'),
            makeField('ctaLink.label', 'CTA label'),
        ];

        const filtered = filterInspectorSchemaFields(fields, {
            previewMode: 'desktop',
            interactionState: 'normal',
            targetPath: 'menu_items.0',
            targetComponentPath: 'menu_items.0',
            targetEditableFields: ['menu_items.0'],
            elementorLike: false,
        });

        expect(filtered.map((field) => field.path.join('.'))).toEqual([
            'menu_items.0.label',
            'menu_items.0.url',
        ]);
    });

    it('shows items.0.* fields when targetPath is items.0.title (exact leaf field)', () => {
        const fields = [
            makeField('title', 'Section title'),
            makeField('items.0.title', 'Card title'),
            makeField('items.0.description', 'Card description'),
            makeField('items.0.image', 'Card image'),
            makeField('items.0.link', 'Card link'),
            makeField('items.1.title', 'Second card title'),
        ];

        const filtered = filterInspectorSchemaFields(fields, {
            previewMode: 'desktop',
            interactionState: 'normal',
            targetPath: 'items.0.title',
            targetComponentPath: 'items.0',
            targetEditableFields: ['items.0.title', 'items.0.description', 'items.0.image', 'items.0.link'],
            elementorLike: false,
        });

        expect(filtered.map((field) => field.path.join('.'))).toEqual([
            'items.0.title',
            'items.0.description',
            'items.0.image',
            'items.0.link',
        ]);
        expect(filtered.map((f) => f.path.join('.'))).not.toContain('items.1.title');
        expect(filtered.map((f) => f.path.join('.'))).not.toContain('title');
    });

    it('does not show whole-section fields when child target items.0.title is selected', () => {
        const fields = [
            makeField('title', 'Section title'),
            makeField('subtitle', 'Section subtitle'),
            makeField('items.0.title', 'Card title'),
            makeField('items.0.description', 'Card description'),
        ];

        const filtered = filterInspectorSchemaFields(fields, {
            previewMode: 'desktop',
            interactionState: 'normal',
            targetPath: 'items.0.title',
            targetComponentPath: 'items.0',
            targetEditableFields: ['items.0.title', 'items.0.description'],
            elementorLike: false,
        });

        expect(filtered.map((f) => f.path.join('.'))).not.toContain('title');
        expect(filtered.map((f) => f.path.join('.'))).not.toContain('subtitle');
        expect(filtered.map((f) => f.path.join('.'))).toContain('items.0.title');
        expect(filtered.map((f) => f.path.join('.'))).toContain('items.0.description');
    });

    it('returns no scoped fields for stale target paths instead of widening by family', () => {
        const fields = [
            makeField('title', 'Title'),
            makeField('subtitle', 'Subtitle'),
            makeField('buttonText', 'Button text'),
        ];

        const filtered = filterInspectorSchemaFields(fields, {
            previewMode: 'desktop',
            interactionState: 'normal',
            targetPath: 'ghost.title',
            targetComponentPath: 'ghost',
            targetEditableFields: ['ghost.title'],
            elementorLike: false,
        });

        expect(filtered).toEqual([]);
    });
});
