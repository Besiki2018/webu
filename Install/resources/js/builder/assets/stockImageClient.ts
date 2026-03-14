import axios from 'axios';

import type {
    StockImageImportRequest,
    StockImageImportResponse,
    StockImageOrientation,
    StockImageSearchResponse,
} from './stockImageTypes';

export async function searchStockImages(input: {
    query: string;
    limit?: number;
    orientation?: StockImageOrientation | null;
}): Promise<StockImageSearchResponse> {
    const response = await axios.post<StockImageSearchResponse>('/api/assets/search', {
        query: input.query,
        limit: input.limit ?? 12,
        orientation: input.orientation ?? undefined,
    });

    return response.data;
}

export async function importStockImage(input: StockImageImportRequest): Promise<StockImageImportResponse> {
    const response = await axios.post<StockImageImportResponse>('/api/assets/import', input);

    return response.data;
}
