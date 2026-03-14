export type StockImageProvider = 'unsplash' | 'pexels' | 'freepik';
export type StockImageOrientation = 'landscape' | 'portrait' | 'square';

export interface StockImageResult {
    provider: StockImageProvider;
    id: string;
    title: string;
    preview_url: string;
    full_url: string;
    download_url: string;
    width: number;
    height: number;
    author: string;
    license: string;
    orientation?: StockImageOrientation;
    score?: number;
    metadata?: Record<string, unknown>;
}

export interface StockImageSearchResponse {
    query: string;
    results: StockImageResult[];
}

export interface ImportedStockMedia {
    id: number;
    site_id: string;
    path: string;
    mime: string;
    size: number;
    meta_json: Record<string, unknown>;
    asset_url: string;
    created_at: string | null;
}

export interface StockImageImportRequest {
    provider: StockImageProvider;
    image_id: string;
    download_url: string;
    project_id: string;
    title?: string | null;
    author?: string | null;
    license?: string | null;
    imported_by?: 'ai' | 'user' | 'visual_builder' | null;
    section_local_id?: string | null;
    component_key?: string | null;
    page_slug?: string | null;
    page_id?: string | null;
    query?: string | null;
}

export interface StockImageImportResponse {
    message?: string;
    media: ImportedStockMedia;
}
