import { describe, expect, it } from 'vitest';

import {
    createImageImportState,
    setImageImportSearchState,
    withImageImportError,
    withImageImportResults,
} from '@/builder/assets/imageImportState';

describe('imageImportState', () => {
    it('creates a neutral initial state', () => {
        expect(createImageImportState('cats')).toEqual({
            query: 'cats',
            results: [],
            selectedImageId: null,
            isSearching: false,
            isImporting: false,
            error: null,
        });
    });

    it('stores search results and selects the first result', () => {
        const state = withImageImportResults(createImageImportState(), [{
            provider: 'unsplash',
            id: 'img-1',
            title: 'Cat photo',
            preview_url: '/preview.jpg',
            full_url: '/full.jpg',
            download_url: '/full.jpg',
            width: 1200,
            height: 800,
            author: 'Author',
            license: 'Unsplash License',
        }], 'cat photo');

        expect(state.query).toBe('cat photo');
        expect(state.selectedImageId).toBe('img-1');
        expect(state.results).toHaveLength(1);
    });

    it('can toggle searching and importing flags', () => {
        const state = setImageImportSearchState(createImageImportState(), {
            searching: true,
            importing: true,
            selectedImageId: 'img-2',
        });

        expect(state.isSearching).toBe(true);
        expect(state.isImporting).toBe(true);
        expect(state.selectedImageId).toBe('img-2');
    });

    it('stores errors and clears loading flags', () => {
        const state = withImageImportError(createImageImportState(), 'boom');

        expect(state.error).toBe('boom');
        expect(state.isSearching).toBe(false);
        expect(state.isImporting).toBe(false);
    });
});
