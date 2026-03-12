import { describe, expect, it } from 'vitest';
import { getComponentSchema } from '@/builder/componentRegistry';
import type { BuilderComponentSchema } from '@/builder/componentRegistry';
import { collectBuilderSchemaPrimitiveFieldDescriptors, collectSchemaPrimitiveFieldDescriptors } from '../schemaPrimitiveFields';

describe('collectSchemaPrimitiveFieldDescriptors', () => {
    it('expands array object items into indexed primitive fields', () => {
        const fields = collectSchemaPrimitiveFieldDescriptors({
            menu_items: {
                type: 'array',
                title: 'Menu items',
                items: {
                    type: 'object',
                    properties: {
                        label: { type: 'string', title: 'Label' },
                        url: { type: 'string', title: 'URL' },
                    },
                },
            },
        }, {
            values: {
                menu_items: [
                    { label: 'Home', url: '/' },
                    { label: 'Shop', url: '/shop' },
                ],
            },
        });

        expect(fields.map((field) => field.path.join('.'))).toEqual(expect.arrayContaining([
            'menu_items.0.label',
            'menu_items.0.url',
            'menu_items.1.label',
            'menu_items.1.url',
        ]));
        expect(fields.find((field) => field.path.join('.') === 'menu_items.0.label')?.label).toBe('Menu items 1 / Label');
    });

    it('expands additionalProperties records backed by array items', () => {
        const fields = collectSchemaPrimitiveFieldDescriptors({
            menus: {
                type: 'object',
                title: 'Menus',
                additionalProperties: {
                    type: 'array',
                    items: {
                        type: 'object',
                        properties: {
                            label: { type: 'string', title: 'Label' },
                            url: { type: 'string', title: 'URL' },
                        },
                    },
                },
            },
        }, {
            values: {
                menus: {
                    shop: [{ label: 'All products', url: '/shop' }],
                    support: [{ label: 'Contact', url: '/contact' }],
                },
            },
        });

        expect(fields.map((field) => field.path.join('.'))).toEqual(expect.arrayContaining([
            'menus.shop.0.label',
            'menus.shop.0.url',
            'menus.support.0.label',
            'menus.support.0.url',
        ]));
        expect(fields.find((field) => field.path.join('.') === 'menus.shop.0.label')?.label).toBe('Menus / Shop 1 / Label');
    });

    it('keeps direct nested object labels simple while indexing primitive arrays', () => {
        const fields = collectSchemaPrimitiveFieldDescriptors({
            primary_cta: {
                type: 'object',
                title: 'Primary CTA',
                properties: {
                    label: { type: 'string', title: 'Label' },
                    url: { type: 'string', title: 'URL' },
                },
            },
            tabs: {
                type: 'array',
                title: 'Tabs',
                items: { type: 'string', title: 'Tab' },
            },
        }, {
            values: {
                primary_cta: {
                    label: 'Book now',
                    url: '/book',
                },
                tabs: ['Overview', 'Specs'],
            },
        });

        expect(fields.find((field) => field.path.join('.') === 'primary_cta.label')?.label).toBe('Label');
        expect(fields.find((field) => field.path.join('.') === 'primary_cta.url')?.label).toBe('URL');
        expect(fields.find((field) => field.path.join('.') === 'tabs.0')?.label).toBe('Tabs 1');
        expect(fields.find((field) => field.path.join('.') === 'tabs.1')?.label).toBe('Tabs 2');
    });
});

describe('collectBuilderSchemaPrimitiveFieldDescriptors', () => {
    it('expands repeater item fields and builder defaults for missing runtime props', () => {
        const schema: BuilderComponentSchema = {
            componentKey: 'test_cards',
            displayName: 'Test Cards',
            category: 'sections',
            defaultProps: {},
            fields: [
                { path: 'title', label: 'Title', type: 'text', group: 'content' },
                {
                    path: 'items',
                    label: 'Cards',
                    type: 'repeater',
                    group: 'content',
                    default: [],
                    itemFields: [
                        { path: 'image', label: 'Image', type: 'image', group: 'content' },
                        { path: 'title', label: 'Title', type: 'text', group: 'content' },
                        { path: 'description', label: 'Description', type: 'richtext', group: 'content' },
                        { path: 'link', label: 'Link', type: 'link', group: 'content', default: '#' },
                    ],
                },
                { path: 'layout.alignment', label: 'Alignment', type: 'alignment', group: 'layout', default: 'center' },
                { path: 'backgroundColor', label: 'Background', type: 'color', group: 'style', default: '#ffffff' },
                { path: 'advanced.padding_top', label: 'Padding top', type: 'spacing', group: 'advanced', default: '' },
                { path: 'responsive.desktop.padding_top', label: 'Desktop padding top', type: 'spacing', group: 'responsive', default: '' },
            ],
        };
        const fields = collectBuilderSchemaPrimitiveFieldDescriptors(schema, {
            values: {
                title: 'Cards',
                items: [],
            },
        });

        const fieldPaths = fields.map((field) => field.path.join('.'));
        expect(fieldPaths).toEqual(expect.arrayContaining([
            'title',
            'items.0.image',
            'items.0.title',
            'items.0.description',
            'items.0.link',
            'backgroundColor',
            'advanced.padding_top',
            'layout.alignment',
            'responsive.desktop.padding_top',
        ]));

        expect(fields.find((field) => field.path.join('.') === 'items.0.link')?.definition.builder_field_type).toBe('link');
        expect(fields.find((field) => field.path.join('.') === 'items.0.title')?.label).toBe('Cards 1 / Title');
    });

    it('surfaces menu and link compound fields with scope-friendly defaults', () => {
        const schema = getComponentSchema('webu_header_01');
        const fields = collectBuilderSchemaPrimitiveFieldDescriptors(schema, {
            values: {
                menu_items: [],
                ctaLink: {
                    label: 'Get started',
                    url: '/start',
                },
            },
        });

        const fieldPaths = fields.map((field) => field.path.join('.'));
        expect(fieldPaths).toEqual(expect.arrayContaining([
            'menu_items.0.label',
            'menu_items.0.url',
            'ctaLink',
            'layoutVariant',
            'backgroundColor',
        ]));

        expect(fields.find((field) => field.path.join('.') === 'ctaLink')?.definition.default).toEqual({
            label: '',
            url: '#',
        });
    });
});
