import { describe, expect, it } from 'vitest';

import { buildCmsFieldOwnershipSnapshot } from '@/builder/cmsIntegration/contentAuthorityRules';

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
    });
});
