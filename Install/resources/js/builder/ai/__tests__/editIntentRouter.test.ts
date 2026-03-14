import { describe, expect, it } from 'vitest';

import { routeAiEditIntent } from '@/builder/ai/editIntentRouter';

describe('editIntentRouter', () => {
    it('keeps selected-element requests on the safe prop patch lane', () => {
        expect(routeAiEditIntent({
            message: 'Change this headline to Book now',
            hasSelectedElement: true,
            viewMode: 'inspect',
        })).toMatchObject({
            intent: 'prop_patch',
            confidence: 'high',
            reason: 'selected_element_scope',
        });
    });

    it('routes section layout requests to structure_change', () => {
        expect(routeAiEditIntent({
            message: 'Add a pricing section below the testimonials block',
            viewMode: 'inspect',
        })).toMatchObject({
            intent: 'structure_change',
            confidence: 'high',
        });
    });

    it('routes new page requests to page_change', () => {
        expect(routeAiEditIntent({
            message: 'Create an about page for the company',
            viewMode: 'preview',
        })).toMatchObject({
            intent: 'page_change',
            confidence: 'high',
        });
    });

    it('routes explicit workspace file edits to file_change', () => {
        expect(routeAiEditIntent({
            message: 'Update the page route file and add a shared utility',
            viewMode: 'code',
        })).toMatchObject({
            intent: 'file_change',
            confidence: 'high',
        });
    });

    it('routes regenerate requests to regeneration_request', () => {
        expect(routeAiEditIntent({
            message: 'Regenerate the workspace code from the site',
            viewMode: 'code',
        })).toMatchObject({
            intent: 'regeneration_request',
            confidence: 'high',
        });
    });
});
