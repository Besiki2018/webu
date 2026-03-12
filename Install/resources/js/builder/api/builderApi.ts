import axios from 'axios';
import type { BuilderDocument } from '@/builder/types/builderDocument';
import type { BuilderMutation } from '@/builder/mutations/dispatchBuilderMutation';
import type { BuilderAiSuggestion } from '@/builder/ai/aiTypes';

export interface BuilderApiEndpoints {
    document: string;
    mutations: string;
    publish: string;
    aiSuggestions: string;
    assets: string;
    assetsUpload: string;
}

export interface BuilderAsset {
    id: number;
    filename: string;
    original_filename?: string;
    mime_type: string;
    size: number;
    human_size?: string;
    is_image?: boolean;
    url: string;
    created_at: string;
}

export async function fetchBuilderDocument(endpoint: string) {
    const response = await axios.get<{
        document: BuilderDocument;
        published_document: BuilderDocument | null;
    }>(endpoint);

    return response.data;
}

export async function saveBuilderDocument(endpoint: string, document: BuilderDocument) {
    const response = await axios.put<{ document: BuilderDocument }>(endpoint, { document });

    return response.data.document;
}

export async function applyBuilderMutations(endpoint: string, mutations: BuilderMutation[]) {
    const response = await axios.post<{ document: BuilderDocument }>(endpoint, { mutations });

    return response.data.document;
}

export async function publishBuilderDocument(endpoint: string) {
    const response = await axios.post<{ document: BuilderDocument }>(endpoint);

    return response.data.document;
}

export async function requestAiBuilderSuggestions(
    endpoint: string,
    prompt: string,
    document: BuilderDocument,
    selectedNodeId: string | null,
) {
    const response = await axios.post<{ suggestions: BuilderAiSuggestion[] }>(endpoint, {
        prompt,
        document,
        selected_node_id: selectedNodeId,
    });

    return response.data.suggestions;
}

export async function fetchBuilderAssets(endpoint: string) {
    const response = await axios.get<{ files: BuilderAsset[] }>(endpoint, {
        params: {
            per_page: 64,
        },
    });

    return response.data.files;
}

export async function uploadBuilderAsset(endpoint: string, file: File) {
    const formData = new FormData();
    formData.append('file', file);

    const response = await axios.post<{ file: BuilderAsset }>(endpoint, formData, {
        headers: {
            'Content-Type': 'multipart/form-data',
        },
    });

    return response.data.file;
}
