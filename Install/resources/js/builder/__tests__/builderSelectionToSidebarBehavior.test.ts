/**
 * Real behavior tests for inspect selection -> builder target resolution -> sidebar field filtering.
 * Covers both section-scoped visual builder selection and narrower field-scoped helper behavior.
 */
import { describe, expect, it } from 'vitest';
import { buildEditableTargetFromMention } from '@/builder/editingState';
import { resolveMentionBuilderTarget } from '@/builder/chat/chatBuilderSelection';
import { buildSelectedTargetContext } from '@/builder/selectedTargetContext';
import { filterInspectorSchemaFields, type InspectorSchemaField } from '@/builder/inspector/filterInspectorSchemaFields';

function makeField(path: string, label: string): InspectorSchemaField {
    return {
        path: path.split('.'),
        type: 'string',
        label,
        definition: { type: 'string' },
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

describe('builder selection to sidebar behavior', () => {
    it('nested child clicks still resolve to section scope so the sidebar keeps full component controls', () => {
        const mention = {
            id: 'HeroSection.title',
            tagName: 'h1',
            selector: '[data-webu-field="title"]',
            textPreview: 'Launch faster',
            sectionKey: 'webu_general_hero_01',
            sectionLocalId: 'hero-1',
            parameterName: 'title',
            componentPath: 'title',
            elementId: 'HeroSection.title',
        };
        const structureItems = [{
            localId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            label: 'Hero',
            previewText: 'Launch faster',
            props: {
                title: 'Launch faster',
                subtitle: 'Build in minutes',
                buttonText: 'Get started',
            },
        }];
        const allSectionFields: InspectorSchemaField[] = [
            makeField('title', 'Hero title'),
            makeField('subtitle', 'Hero subtitle'),
            makeField('buttonText', 'CTA label'),
        ];

        const resolved = resolveMentionBuilderTarget({
            element: mention,
            builderStructureItems: structureItems,
            currentBreakpoint: 'desktop',
            currentInteractionState: 'normal',
        });
        const filtered = filterInspectorSchemaFields(allSectionFields, {
            previewMode: 'desktop',
            interactionState: 'normal',
            targetPath: resolved.target?.path ?? null,
            targetComponentPath: resolved.target?.componentPath ?? null,
            targetEditableFields: resolved.target?.allowedUpdates?.sectionFieldPaths ?? [],
            elementorLike: true,
        });

        expect(resolved.target?.sectionLocalId).toBe('hero-1');
        expect(resolved.target?.path).toBeNull();
        expect(resolved.target?.componentPath).toBeNull();
        expect(filtered.map((field) => field.path.join('.'))).toEqual([
            'title',
            'subtitle',
            'buttonText',
        ]);
    });

    it('select component -> buildEditableTargetFromMention -> sidebar shows full editable fields for that component', () => {
        const mention = {
            id: 'CardsSection.items.0.title',
            tagName: 'h3',
            selector: '[data-webu-field="items.0.title"]',
            textPreview: 'Starter',
            sectionKey: 'webu_general_cards_01',
            sectionLocalId: 'cards-1',
            parameterName: 'items.0.title',
            componentPath: 'items.0',
            elementId: 'CardsSection.items.0.title',
        };
        const props = {
            items: [
                { title: 'Starter', description: 'Short copy', link: { label: 'Read more', url: '/starter' } },
            ],
        };

        const target = buildEditableTargetFromMention(mention, props);
        expect(target).not.toBeNull();
        expect(target?.path).toBe('items.0.title');
        expect(target?.componentPath).toBe('items.0');

        const allSectionFields: InspectorSchemaField[] = [
            makeField('title', 'Section title'),
            makeField('items.0.title', 'Card title'),
            makeField('items.0.description', 'Card description'),
            makeField('items.0.link', 'Card link'),
            makeField('items.1.title', 'Second card title'),
        ];

        const filtered = filterInspectorSchemaFields(allSectionFields, {
            previewMode: 'desktop',
            interactionState: 'normal',
            targetPath: target?.path ?? null,
            targetComponentPath: target?.componentPath ?? null,
            targetEditableFields: target?.allowedUpdates?.fieldPaths ?? [],
            elementorLike: false,
        });

        expect(filtered.map((f) => f.path.join('.'))).toContain('items.0.title');
        expect(filtered.map((f) => f.path.join('.'))).toContain('items.0.description');
        expect(filtered.map((f) => f.path.join('.'))).not.toContain('items.1.title');
        expect(filtered.map((f) => f.path.join('.'))).not.toContain('title');
    });

    it('selected target context matches inspect-selected target for AI', () => {
        const target = buildEditableTargetFromMention(
            {
                id: 'HeroSection.title',
                tagName: 'h1',
                selector: '[data-webu-field="title"]',
                textPreview: 'Launch faster',
                sectionKey: 'webu_general_hero_01',
                sectionLocalId: 'hero-1',
                parameterName: 'title',
                componentPath: 'title',
                elementId: 'HeroSection.title',
            },
            { title: 'Launch faster', image: '/hero.jpg', buttonText: 'Shop now' },
        );

        const context = buildSelectedTargetContext(target!);

        expect(context?.parameter_path).toBe('title');
        expect(context?.component_path).toBe('title');
        expect(context?.section_id).toBe('hero-1');
        expect(context?.element_id).toBe('HeroSection.title');
    });

    it('generic CMS hero aliases still expose sidebar controls after inspect selection', () => {
        const target = buildEditableTargetFromMention(
            {
                id: 'HeroSection.title',
                tagName: 'h2',
                selector: '[data-webu-section-local-id="section-0"] [data-webu-field="title"]',
                textPreview: 'მოგესალმებით',
                sectionKey: 'hero',
                sectionLocalId: 'section-0',
                parameterName: 'title',
                componentPath: 'title',
                elementId: 'HeroSection.title',
            },
            {
                title: 'მოგესალმებით',
                subtitle: 'ჩვენ ვქმნით ხარისხს.',
                cta_text: 'დაწყება',
            },
        );

        const fields: InspectorSchemaField[] = [
            makeField('title', 'სათაური'),
            makeField('subtitle', 'ქვესათაური'),
            makeField('buttonText', 'ღილაკის ტექსტი'),
        ];

        const filtered = filterInspectorSchemaFields(fields, {
            previewMode: 'desktop',
            interactionState: 'normal',
            targetPath: target?.path ?? null,
            targetComponentPath: target?.componentPath ?? null,
            targetEditableFields: target?.allowedUpdates?.fieldPaths ?? [],
            elementorLike: false,
        });

        expect(target?.allowedUpdates?.fieldPaths).toContain('title');
        expect(target?.allowedUpdates?.fieldPaths).toContain('buttonText');
        expect(filtered.map((field) => field.path.join('.'))).toContain('title');
        expect(filtered.map((field) => field.path.join('.'))).toContain('subtitle');
        expect(filtered.map((field) => field.path.join('.'))).toContain('buttonText');
    });

    it('nested repeater item selection yields scope-specific fields only', () => {
        const target = buildEditableTargetFromMention(
            {
                id: 'HeaderSection.menu_items.0',
                tagName: 'a',
                selector: '[data-webu-field-scope="menu_items.0"]',
                textPreview: 'Shop',
                sectionKey: 'webu_header_01',
                sectionLocalId: 'header-1',
                parameterName: 'menu_items.0',
                componentPath: 'menu_items.0',
                elementId: 'HeaderSection.menu_items.0',
            },
            {
                logoText: 'Webu',
                menu_items: [{ label: 'Shop', url: '/shop' }],
            },
        );

        const fields: InspectorSchemaField[] = [
            makeField('logoText', 'Logo'),
            makeField('menu_items.0.label', 'Menu label'),
            makeField('menu_items.0.url', 'Menu URL'),
            makeField('menu_items.1.label', 'Second menu'),
        ];

        const filtered = filterInspectorSchemaFields(fields, {
            previewMode: 'desktop',
            interactionState: 'normal',
            targetPath: target?.path ?? null,
            targetComponentPath: target?.componentPath ?? null,
            targetEditableFields: target?.allowedUpdates?.fieldPaths ?? [],
            elementorLike: false,
        });

        expect(filtered.map((f) => f.path.join('.'))).toContain('menu_items.0.label');
        expect(filtered.map((f) => f.path.join('.'))).toContain('menu_items.0.url');
        expect(filtered.map((f) => f.path.join('.'))).not.toContain('logoText');
        expect(filtered.map((f) => f.path.join('.'))).not.toContain('menu_items.1.label');
    });
});
