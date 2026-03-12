import { create } from 'zustand';
import type { BuilderApiEndpoints, BuilderAsset } from '@/builder/api/builderApi';
import { fetchBuilderAssets, uploadBuilderAsset } from '@/builder/api/builderApi';

interface AssetsStoreState {
    items: BuilderAsset[];
    loading: boolean;
    uploading: boolean;
    error: string | null;
    lastLoadedAt: number | null;
    load: (endpoints: BuilderApiEndpoints) => Promise<void>;
    upload: (endpoints: BuilderApiEndpoints, file: File) => Promise<BuilderAsset | null>;
}

export const useAssetsStore = create<AssetsStoreState>((set) => ({
    items: [],
    loading: false,
    uploading: false,
    error: null,
    lastLoadedAt: null,
    load: async (endpoints) => {
        set({ loading: true, error: null });
        try {
            const items = await fetchBuilderAssets(endpoints.assets);
            set({
                items,
                loading: false,
                lastLoadedAt: Date.now(),
            });
        } catch (error) {
            set({
                loading: false,
                error: error instanceof Error ? error.message : 'Failed to load assets',
            });
        }
    },
    upload: async (endpoints, file) => {
        set({ uploading: true, error: null });
        try {
            const uploaded = await uploadBuilderAsset(endpoints.assetsUpload, file);
            set((state) => ({
                items: [uploaded, ...state.items],
                uploading: false,
            }));

            return uploaded;
        } catch (error) {
            set({
                uploading: false,
                error: error instanceof Error ? error.message : 'Failed to upload asset',
            });

            return null;
        }
    },
}));
