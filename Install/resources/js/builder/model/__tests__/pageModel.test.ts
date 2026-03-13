import { describe, expect, it } from 'vitest';

import {
    buildBuilderPageModelFromContentJson,
    buildBuilderPageModelFromSectionDrafts,
    builderPageModelToContentJson,
    builderPageModelToSectionDrafts,
    normalizeBuilderSectionDrafts,
} from '../pageModel';

describe('pageModel', () => {
    it('builds a canonical serializable page model from content_json and keeps extra content separate', () => {
        const model = buildBuilderPageModelFromContentJson({
            editor_mode: 'builder',
            text_editor_html: '<p>ignore</p>',
            sections: [
                {
                    type: 'webu_general_hero_01',
                    localId: 'hero-1',
                    props: {
                        title: 'Storefront hero',
                    },
                    binding: {
                        source: 'cms',
                    },
                },
            ],
            seo_blocks: {
                enabled: true,
            },
        });

        expect(model.schemaVersion).toBe(1);
        expect(model.editorMode).toBe('builder');
        expect(model.extraContent).toEqual({
            seo_blocks: {
                enabled: true,
            },
        });
        expect(model.sections).toHaveLength(1);
        expect(model.sections[0]?.localId).toBe('hero-1');
        expect(model.sections[0]?.props.title).toBe('Storefront hero');
        expect(model.sections[0]?.props.buttonText).toBe('Get Started');
        expect(model.sections[0]?.bindingMeta).toEqual({
            source: 'cms',
        });
    });

    it('round-trips page model to drafts and persisted content with explicit props', () => {
        const model = buildBuilderPageModelFromContentJson({
            editor_mode: 'builder',
            sections: [
                {
                    type: 'webu_general_hero_01',
                    localId: 'hero-1',
                    props: {
                        title: 'Commerce hero',
                        style: {
                            background_color: '#f8fafc',
                        },
                    },
                },
            ],
        });
        const drafts = builderPageModelToSectionDrafts(model);
        const rebuiltModel = buildBuilderPageModelFromSectionDrafts(drafts, {
            editorMode: 'builder',
            extraContent: {
                locale: 'en',
            },
        });
        const contentJson = builderPageModelToContentJson(rebuiltModel);
        const sections = Array.isArray(contentJson.sections) ? contentJson.sections : [];
        const hero = sections[0] as Record<string, unknown>;
        const heroProps = hero.props as Record<string, unknown>;

        expect(drafts[0]?.props?.title).toBe('Commerce hero');
        expect(typeof drafts[0]?.propsText).toBe('string');
        expect(contentJson.editor_mode).toBe('builder');
        expect(contentJson.locale).toBe('en');
        expect(hero.localId).toBe('hero-1');
        expect(hero.type).toBe('webu_general_hero_01');
        expect(heroProps.title).toBe('Commerce hero');
        expect(heroProps.buttonText).toBe('Get Started');
        expect((heroProps.style as Record<string, unknown>)?.background_color).toBe('#f8fafc');
    });

    it('preserves invalid raw JSON drafts while keeping explicit props available to the builder', () => {
        const sections = normalizeBuilderSectionDrafts([
            {
                localId: 'hero-1',
                type: 'webu_general_hero_01',
                props: {
                    title: 'Fallback hero',
                },
                propsText: '{invalid-json',
                propsError: 'Invalid JSON',
            },
        ]);

        expect(sections[0]?.props?.title).toBe('Fallback hero');
        expect(sections[0]?.props?.buttonText).toBe('Get Started');
        expect(sections[0]?.propsText).toBe('{invalid-json');
        expect(sections[0]?.propsError).toBe('Invalid JSON');
    });
});
