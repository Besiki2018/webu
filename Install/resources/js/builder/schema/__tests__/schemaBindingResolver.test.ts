import { describe, expect, it } from 'vitest';

import { getComponentSchema } from '@/builder/componentRegistry';

import { resolveSchemaPreferredStringProp } from '../schemaBindingResolver';

describe('resolveSchemaPreferredStringProp', () => {
    it('prefers schema-backed title over stale legacy headline values for hero sections', () => {
        const schema = getComponentSchema('webu_general_hero_01');

        const result = resolveSchemaPreferredStringProp(
            schema,
            {
                headline: 'Legacy heading',
                title: 'Runtime hero title',
            },
            ['title', 'headline', 'heading'],
        );

        expect(result).toBe('Runtime hero title');
    });

    it('falls back to candidate order when no schema-backed field is present', () => {
        const result = resolveSchemaPreferredStringProp(
            null,
            {
                heading: 'Plain fallback heading',
                title: '',
            },
            ['title', 'headline', 'heading'],
        );

        expect(result).toBe('Plain fallback heading');
    });
});
