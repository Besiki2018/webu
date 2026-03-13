import { describe, expect, it } from 'vitest';

import { buildEditableTargetFromMessagePayload } from '@/builder/editingState';
import {
    applyBuilderChangeSetPipeline,
    applyBuilderUpdatePipeline,
    updateComponentProps,
    type BuilderUpdateStateSnapshot,
} from '../updatePipeline';

function makeStateSnapshot(): BuilderUpdateStateSnapshot {
    const heroSection = {
        localId: 'hero-1',
        type: 'webu_general_hero_01',
        propsText: JSON.stringify({
            title: 'Original title',
            buttonText: 'Shop now',
            buttonLink: '/shop',
            advanced: {
                padding_top: '40px',
            },
        }),
        propsError: null,
    };

    return {
        sectionsDraft: [heroSection],
        selectedSectionLocalId: 'hero-1',
        selectedBuilderTarget: buildEditableTargetFromMessagePayload({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            elementId: null,
            props: JSON.parse(heroSection.propsText) as Record<string, unknown>,
        }),
    };
}

function makeRepeatedItemStateSnapshot(): BuilderUpdateStateSnapshot {
    const headerSection = {
        localId: 'header-1',
        type: 'webu_header_01',
        propsText: JSON.stringify({
            logoText: 'Webu',
            menu_items: [
                {
                    label: 'Shop',
                    url: '/shop',
                },
            ],
        }),
        propsError: null,
    };

    return {
        sectionsDraft: [headerSection],
        selectedSectionLocalId: 'header-1',
        selectedBuilderTarget: buildEditableTargetFromMessagePayload({
            sectionLocalId: 'header-1',
            sectionKey: 'webu_header_01',
            componentType: 'webu_header_01',
            componentName: 'Header',
            parameterPath: 'menu_items.0.label',
            componentPath: 'menu_items.0',
            elementId: 'HeaderSection.menu_items.0.label',
            props: JSON.parse(headerSection.propsText) as Record<string, unknown>,
        }),
    };
}

function makeMultiSectionStateSnapshot(): BuilderUpdateStateSnapshot {
    const heroOne = {
        localId: 'hero-1',
        type: 'webu_general_hero_01',
        propsText: JSON.stringify({ title: 'Hero 1' }),
        propsError: null,
    };
    const heroTwo = {
        localId: 'hero-2',
        type: 'webu_general_hero_01',
        propsText: JSON.stringify({ title: 'Hero 2' }),
        propsError: null,
    };

    return {
        sectionsDraft: [heroOne, heroTwo],
        selectedSectionLocalId: 'hero-1',
        selectedBuilderTarget: buildEditableTargetFromMessagePayload({
            sectionLocalId: 'hero-1',
            sectionKey: 'webu_general_hero_01',
            componentType: 'webu_general_hero_01',
            componentName: 'Hero',
            props: JSON.parse(heroOne.propsText) as Record<string, unknown>,
        }),
    };
}

function makeNestedLayoutStateSnapshot(): BuilderUpdateStateSnapshot {
    const layoutSection = {
        localId: 'layout-1',
        type: 'container',
        propsText: JSON.stringify({
            sections: [
                {
                    type: 'webu_general_text_01',
                    props: {
                        content: 'Alpha',
                    },
                },
                {
                    type: 'webu_general_text_01',
                    props: {
                        content: 'Beta',
                    },
                },
            ],
        }),
        propsError: null,
    };

    return {
        sectionsDraft: [layoutSection],
        selectedSectionLocalId: 'layout-1',
        selectedBuilderTarget: buildEditableTargetFromMessagePayload({
            sectionLocalId: 'layout-1',
            sectionKey: 'container',
            componentType: 'container',
            componentName: 'Container',
            props: JSON.parse(layoutSection.propsText) as Record<string, unknown>,
        }),
    };
}

describe('updatePipeline', () => {
    it('updates sidebar field edits through one validated pipeline and keeps selected target props in sync', () => {
        const initialState = makeStateSnapshot();
        const result = applyBuilderUpdatePipeline(initialState, [{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'hero-1',
            path: ['buttonText'],
            value: 'Browse collection',
        }]);

        expect(result.ok).toBe(true);
        expect(result.changed).toBe(true);
        expect(result.state.selectedBuilderTarget?.props?.buttonText).toBe('Browse collection');
        expect(JSON.parse(result.state.sectionsDraft[0]?.propsText ?? '{}').buttonText).toBe('Browse collection');
        expect(result.state.selectedBuilderTarget?.sectionLocalId).toBe('hero-1');
    });

    it('rejects field updates that are not declared in component schema', () => {
        const initialState = {
            ...makeStateSnapshot(),
            selectedBuilderTarget: buildEditableTargetFromMessagePayload({
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                componentType: 'webu_general_hero_01',
                componentName: 'Hero',
                props: JSON.parse(makeStateSnapshot().sectionsDraft[0]?.propsText ?? '{}') as Record<string, unknown>,
            }),
        };
        const result = applyBuilderUpdatePipeline(initialState, [{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'hero-1',
            path: ['nonexistentField'],
            value: 'Invalid',
        }]);

        expect(result.ok).toBe(false);
        expect(result.errors[0]?.code).toBe('field_not_found');
        expect(JSON.parse(result.state.sectionsDraft[0]?.propsText ?? '{}').buttonText).toBe('Shop now');
    });

    it('allows broader sidebar section edits even when an exact child target is selected', () => {
        const initialState = makeRepeatedItemStateSnapshot();
        const result = applyBuilderUpdatePipeline(initialState, [{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'header-1',
            path: ['logoText'],
            value: 'Updated logo',
        }]);

        expect(result.ok).toBe(true);
        expect(JSON.parse(result.state.sectionsDraft[0]?.propsText ?? '{}').logoText).toBe('Updated logo');
    });

    it('keeps exact child target validation for non-sidebar edits', () => {
        const initialState = makeRepeatedItemStateSnapshot();
        const result = applyBuilderUpdatePipeline(initialState, [{
            kind: 'set-field',
            source: 'chat',
            sectionLocalId: 'header-1',
            path: ['logoText'],
            value: 'This should be rejected',
        }]);

        expect(result.ok).toBe(false);
        expect(result.errors[0]?.code).toBe('target_mismatch');
        expect(JSON.parse(result.state.sectionsDraft[0]?.propsText ?? '{}').logoText).toBe('Webu');
    });

    it('applies updateText to exact nested target (menu_items.0.label) without affecting sibling fields', () => {
        const initialState = makeRepeatedItemStateSnapshot();
        const result = applyBuilderChangeSetPipeline(initialState, {
            operations: [
                {
                    op: 'updateText',
                    sectionId: 'header-1',
                    path: 'menu_items.0.label',
                    value: 'Store',
                },
            ],
        });

        expect(result.ok).toBe(true);
        const nextProps = JSON.parse(result.state.sectionsDraft[0]?.propsText ?? '{}') as Record<string, unknown>;
        const menuItems = nextProps.menu_items as Array<{ label: string; url: string }>;
        expect(menuItems[0]?.label).toBe('Store');
        expect(menuItems[0]?.url).toBe('/shop');
        expect(nextProps.logoText).toBe('Webu');
    });

    it('applies chat change sets through the same pipeline and validates nested patch paths', () => {
        const initialState = makeStateSnapshot();
        const result = applyBuilderChangeSetPipeline(initialState, {
            operations: [
                {
                    op: 'updateText',
                    sectionId: 'hero-1',
                    path: 'title',
                    value: 'Fresh arrivals',
                },
                {
                    op: 'updateSection',
                    sectionId: 'hero-1',
                    patch: {
                        advanced: {
                            padding_top: '80px',
                        },
                    },
                },
            ],
        });

        const nextProps = JSON.parse(result.state.sectionsDraft[0]?.propsText ?? '{}') as Record<string, unknown>;

        expect(result.ok).toBe(true);
        expect(nextProps.title).toBe('Fresh arrivals');
        expect((nextProps.advanced as Record<string, unknown>)?.padding_top).toBe('80px');
    });

    it('converts setField and replaceImage (url/alt) chat ops into pipeline ops', () => {
        const initialState = makeStateSnapshot();
        const result = applyBuilderChangeSetPipeline(initialState, {
            operations: [
                { op: 'setField', sectionId: 'hero-1', path: 'title', value: 'Chat title' },
                { op: 'replaceImage', sectionId: 'hero-1', url: 'https://example.com/hero.jpg', alt: 'Hero image' },
            ],
        });

        expect(result.ok).toBe(true);
        const nextProps = JSON.parse(result.state.sectionsDraft[0]?.propsText ?? '{}') as Record<string, unknown>;
        expect(nextProps.title).toBe('Chat title');
        expect(nextProps.image).toBe('https://example.com/hero.jpg');
        expect(nextProps.imageAlt).toBe('Hero image');
    });

    it('updates link fields through the same validated pipeline path', () => {
        const initialState = makeStateSnapshot();
        const result = applyBuilderChangeSetPipeline(initialState, {
            operations: [
                { op: 'setField', sectionId: 'hero-1', path: 'buttonLink', value: '/collections/new' },
            ],
        });

        expect(result.ok).toBe(true);
        const nextProps = JSON.parse(result.state.sectionsDraft[0]?.propsText ?? '{}') as Record<string, unknown>;
        expect(nextProps.buttonLink).toBe('/collections/new');
    });

    it('updates image fields through the same validated sidebar pipeline path', () => {
        const initialState = makeStateSnapshot();
        const result = applyBuilderUpdatePipeline(initialState, [{
            kind: 'set-field',
            source: 'sidebar',
            sectionLocalId: 'hero-1',
            path: ['image'],
            value: '/storage/uploads/hero-direct-upload.svg',
        }]);

        expect(result.ok).toBe(true);
        expect(result.changed).toBe(true);
        expect(result.state.selectedBuilderTarget?.props?.image).toBe('/storage/uploads/hero-direct-upload.svg');
        expect(JSON.parse(result.state.sectionsDraft[0]?.propsText ?? '{}').image).toBe('/storage/uploads/hero-direct-upload.svg');
    });

    it('supports structural chat operations through the same pipeline entrypoint', () => {
        const initialState = makeStateSnapshot();
        const result = applyBuilderChangeSetPipeline(initialState, {
            operations: [
                {
                    op: 'insertSection',
                    sectionType: 'webu_general_heading_01',
                    afterSectionId: 'hero-1',
                    props: {
                        headline: 'Why choose us',
                    },
                },
            ],
        }, {
            createSection: ({ sectionType, props, localId }) => ({
                localId: localId ?? 'inserted-1',
                type: sectionType,
                propsText: JSON.stringify(props ?? {}),
                propsError: null,
            }),
        });

        expect(result.ok).toBe(true);
        expect(result.structuralChange).toBe(true);
        expect(result.state.sectionsDraft).toHaveLength(2);
        expect(result.state.sectionsDraft[1]?.localId).toBe('inserted-1');
    });

    it('selects the inserted duplicate through the canonical structural pipeline', () => {
        const initialState = makeMultiSectionStateSnapshot();
        const result = applyBuilderUpdatePipeline(initialState, [{
            kind: 'duplicate-section',
            source: 'toolbar',
            sectionLocalId: 'hero-1',
            newLocalId: 'hero-1-copy',
            selectDuplicate: true,
        }]);

        expect(result.ok).toBe(true);
        expect(result.structuralChange).toBe(true);
        expect(result.state.sectionsDraft.map((section) => section.localId)).toEqual([
            'hero-1',
            'hero-1-copy',
            'hero-2',
        ]);
        expect(result.state.selectedSectionLocalId).toBe('hero-1-copy');
        expect(result.state.selectedBuilderTarget?.sectionLocalId).toBe('hero-1-copy');
    });

    it('repairs selection to the nearest valid sibling when deleting the selected section', () => {
        const initialState = makeMultiSectionStateSnapshot();
        const result = applyBuilderUpdatePipeline(initialState, [{
            kind: 'delete-section',
            source: 'toolbar',
            sectionLocalId: 'hero-1',
        }]);

        expect(result.ok).toBe(true);
        expect(result.structuralChange).toBe(true);
        expect(result.state.sectionsDraft.map((section) => section.localId)).toEqual(['hero-2']);
        expect(result.state.selectedSectionLocalId).toBe('hero-2');
        expect(result.state.selectedBuilderTarget?.sectionLocalId).toBe('hero-2');
    });

    it('routes nested insert/delete/reorder through the same structural pipeline', () => {
        const initialState = makeNestedLayoutStateSnapshot();
        const insertResult = applyBuilderUpdatePipeline(initialState, [{
            kind: 'insert-nested-section',
            source: 'toolbar',
            sectionLocalId: 'layout-1',
            nestedSectionPath: [],
            sectionType: 'webu_general_text_01',
            props: {
                content: 'Gamma',
            },
        }]);
        expect(insertResult.ok).toBe(true);
        expect(insertResult.structuralChange).toBe(true);
        expect((JSON.parse(insertResult.state.sectionsDraft[0]?.propsText ?? '{}').sections as Array<{ props?: { content?: string } }>).map((entry) => entry.props?.content)).toEqual([
            'Alpha',
            'Beta',
            'Gamma',
        ]);

        const reorderResult = applyBuilderUpdatePipeline(insertResult.state, [{
            kind: 'reorder-nested-section',
            source: 'toolbar',
            sectionLocalId: 'layout-1',
            nestedSectionPath: [2],
            toIndex: 0,
        }]);
        expect(reorderResult.ok).toBe(true);
        expect((JSON.parse(reorderResult.state.sectionsDraft[0]?.propsText ?? '{}').sections as Array<{ props?: { content?: string } }>).map((entry) => entry.props?.content)).toEqual([
            'Gamma',
            'Alpha',
            'Beta',
        ]);

        const deleteResult = applyBuilderUpdatePipeline(reorderResult.state, [{
            kind: 'delete-nested-section',
            source: 'toolbar',
            sectionLocalId: 'layout-1',
            nestedSectionPath: [1],
        }]);
        expect(deleteResult.ok).toBe(true);
        expect((JSON.parse(deleteResult.state.sectionsDraft[0]?.propsText ?? '{}').sections as Array<{ props?: { content?: string } }>).map((entry) => entry.props?.content)).toEqual([
            'Gamma',
            'Beta',
        ]);
    });

    it('updateComponentProps (unified entry) validates component, field, patches props, and returns new state', () => {
        const initialState = makeStateSnapshot();
        const result = updateComponentProps(
            initialState,
            'hero-1',
            { path: 'title', value: 'Unified entry title' },
            'sidebar'
        );
        expect(result.ok).toBe(true);
        expect(result.changed).toBe(true);
        expect(JSON.parse(result.state.sectionsDraft[0]?.propsText ?? '{}').title).toBe('Unified entry title');
        const stateNoTarget = { ...initialState, selectedBuilderTarget: null };
        const invalidResult = updateComponentProps(stateNoTarget, 'hero-1', { path: 'nonexistentField', value: 'x' }, 'sidebar');
        expect(invalidResult.ok).toBe(false);
        expect(['field_not_found', 'target_mismatch']).toContain(invalidResult.errors[0]?.code);
    });

    it('marks only variant changes for immediate preview refresh', () => {
        const initialState = makeStateSnapshot();

        const textResult = updateComponentProps(
            initialState,
            'hero-1',
            { path: 'title', value: 'Typing should stay local' },
            'sidebar'
        );
        expect(textResult.ok).toBe(true);
        expect(textResult.immediatePreviewRefresh).toBe(false);

        const variantState: BuilderUpdateStateSnapshot = {
            ...initialState,
            sectionsDraft: [{
                ...initialState.sectionsDraft[0],
                propsText: JSON.stringify({
                    ...JSON.parse(initialState.sectionsDraft[0]?.propsText ?? '{}'),
                    variant: 'hero-1',
                }),
            }],
            selectedBuilderTarget: buildEditableTargetFromMessagePayload({
                sectionLocalId: 'hero-1',
                sectionKey: 'webu_general_hero_01',
                componentType: 'webu_general_hero_01',
                componentName: 'Hero',
                props: {
                    ...JSON.parse(initialState.sectionsDraft[0]?.propsText ?? '{}'),
                    variant: 'hero-1',
                },
            }),
        };
        const variantResult = updateComponentProps(
            variantState,
            'hero-1',
            { path: 'variant', value: 'hero-2' },
            'sidebar'
        );
        expect(variantResult.ok).toBe(true);
        expect(variantResult.immediatePreviewRefresh).toBe(true);
    });

    it('clears selected target cleanly when the selected section is deleted and no fallback section remains', () => {
        const initialState = makeStateSnapshot();
        const result = applyBuilderUpdatePipeline(initialState, [{
            kind: 'delete-section',
            source: 'toolbar',
            sectionLocalId: 'hero-1',
        }]);

        expect(result.ok).toBe(true);
        expect(result.structuralChange).toBe(true);
        expect(result.state.sectionsDraft).toHaveLength(0);
        expect(result.state.selectedSectionLocalId).toBeNull();
        expect(result.state.selectedBuilderTarget).toBeNull();
    });
});
