import { describe, expect, it } from 'vitest';

import { applyCmsBindingModelToContentJson, buildCmsBindingModelFromBuilderPageModel } from '@/builder/cmsIntegration/cmsBindingModel';
import { CMS_PAGE_BINDING_EXTRA_CONTENT_KEY } from '@/builder/cmsIntegration/cmsPageBinding';
import type { BuilderPageModel } from '@/builder/model/pageModel';

describe('cmsBindingModel', () => {
    it('embeds page and section binding metadata into content_json', () => {
        const model: BuilderPageModel = {
            schemaVersion: 1,
            editorMode: 'builder',
            textEditorHtml: '',
            extraContent: {
                locale: 'en',
            },
            sections: [
                {
                    localId: 'hero-1',
                    type: 'webu_general_hero_01',
                    props: {
                        title: 'CMS title',
                        subtitle: 'CMS subtitle',
                        variant: 'split',
                    },
                    bindingMeta: null,
                },
            ],
        };

        const bindingModel = buildCmsBindingModelFromBuilderPageModel({
            page: {
                id: '1',
                slug: 'home',
                title: 'Home',
                seoTitle: 'Home SEO',
                seoDescription: 'Home SEO description',
            },
            model,
            editor: 'visual_builder',
            createdBy: 'ai',
            timestamp: '2026-03-14T10:00:00.000Z',
        });
        const contentJson = applyCmsBindingModelToContentJson({
            sections: [
                {
                    type: 'webu_general_hero_01',
                    localId: 'hero-1',
                    props: {
                        title: 'CMS title',
                        subtitle: 'CMS subtitle',
                        variant: 'split',
                    },
                },
            ],
        }, bindingModel);

        expect(contentJson[CMS_PAGE_BINDING_EXTRA_CONTENT_KEY]).toMatchObject({
            page: expect.objectContaining({
                slug: 'home',
                title: 'Home',
            }),
        });
        expect(contentJson.sections).toEqual(expect.arrayContaining([
            expect.objectContaining({
                localId: 'hero-1',
                binding: expect.objectContaining({
                    webu_v2: expect.objectContaining({
                        cms_backed: true,
                        content_fields: expect.arrayContaining(['title', 'subtitle']),
                        visual_fields: expect.arrayContaining(['variant']),
                    }),
                }),
            }),
        ]));
    });
});
