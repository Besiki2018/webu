import { describe, expect, it } from 'vitest';
import { filterCreatePromptExamples } from '../createPromptCatalog';

describe('createPromptCatalog', () => {
    it('removes ecommerce, booking, and portfolio examples from the create page catalog', () => {
        expect(filterCreatePromptExamples([
            'Online store with cart, checkout, and product pages.',
            'სერვისი ჯავშანი',
            'Portfolio / Agency',
            'Create a landing page',
            'Build a dashboard',
        ])).toEqual([
            'Create a landing page',
            'Build a dashboard',
        ]);
    });

    it('deduplicates entries after filtering', () => {
        expect(filterCreatePromptExamples([
            'Create a landing page',
            'create a landing page',
            'Build a dashboard',
        ])).toEqual([
            'Create a landing page',
            'Build a dashboard',
        ]);
    });
});
