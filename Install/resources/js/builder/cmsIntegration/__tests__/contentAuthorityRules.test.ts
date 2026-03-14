import { describe, expect, it } from 'vitest';

import {
    buildCmsFieldOwnershipSnapshot,
    classifyCmsFieldOwner,
    findComponentFieldDefinition,
} from '@/builder/cmsIntegration/contentAuthorityRules';

describe('contentAuthorityRules', () => {
    it('classifies content, visual, and code-oriented fields for hero sections', () => {
        const snapshot = buildCmsFieldOwnershipSnapshot('webu_general_hero_01', {
            title: 'Launch faster',
            subtitle: 'CMS managed copy',
            buttonText: 'Start now',
            variant: 'split',
            advanced: {
                padding_top: '80px',
            },
            submitAction: 'book-demo',
        });

        expect(snapshot.contentFieldPaths).toEqual(expect.arrayContaining([
            'title',
            'subtitle',
            'buttonText',
        ]));
        expect(snapshot.visualFieldPaths).toEqual(expect.arrayContaining([
            'variant',
            'advanced.padding_top',
        ]));
        expect(snapshot.codeFieldPaths).toEqual(expect.arrayContaining([
            'submitAction',
        ]));
        expect(snapshot.fields.find((field) => field.propPath === 'image')?.owner).toBe('cms');
        expect(snapshot.fields.find((field) => field.propPath === 'backgroundImage')?.owner).toBe('builder_structure');
    });

    it('resolves repeater item image fields against schema ownership metadata', () => {
        const cardsImageField = findComponentFieldDefinition('webu_general_cards_01', 'items.0.image');
        const heroAvatarField = findComponentFieldDefinition('webu_general_hero_01', 'statAvatars.0.url');

        expect(cardsImageField?.path).toBe('items.image');
        expect(classifyCmsFieldOwner('items.0.image', cardsImageField ?? null)).toBe('cms');
        expect(heroAvatarField?.path).toBe('statAvatars.url');
        expect(classifyCmsFieldOwner('statAvatars.0.url', heroAvatarField ?? null)).toBe('cms');
    });
});
