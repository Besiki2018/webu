import type { StockImageResult } from './stockImageTypes';

export interface ImageImportState {
    query: string;
    results: StockImageResult[];
    selectedImageId: string | null;
    isSearching: boolean;
    isImporting: boolean;
    error: string | null;
}

export function createImageImportState(query = ''): ImageImportState {
    return {
        query,
        results: [],
        selectedImageId: null,
        isSearching: false,
        isImporting: false,
        error: null,
    };
}

export function withImageImportResults(
    state: ImageImportState,
    results: StockImageResult[],
    query: string,
): ImageImportState {
    return {
        ...state,
        query,
        results,
        selectedImageId: results[0]?.id ?? null,
        isSearching: false,
        error: null,
    };
}

export function withImageImportError(state: ImageImportState, error: string): ImageImportState {
    return {
        ...state,
        isSearching: false,
        isImporting: false,
        error,
    };
}

export function setImageImportSearchState(
    state: ImageImportState,
    input: { searching?: boolean; importing?: boolean; selectedImageId?: string | null; query?: string },
): ImageImportState {
    return {
        ...state,
        query: input.query ?? state.query,
        selectedImageId: input.selectedImageId ?? state.selectedImageId,
        isSearching: input.searching ?? state.isSearching,
        isImporting: input.importing ?? state.isImporting,
    };
}
