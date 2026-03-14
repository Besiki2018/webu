import { beforeEach, describe, expect, it, vi } from 'vitest';
import axios from 'axios';

import { importStockImage, searchStockImages } from '@/builder/assets/stockImageClient';

vi.mock('axios');

const mockedAxios = vi.mocked(axios);

describe('stockImageClient', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('searches stock images through the backend endpoint', async () => {
        mockedAxios.post.mockResolvedValueOnce({
            data: {
                query: 'veterinary clinic',
                results: [{ provider: 'unsplash', id: '1' }],
            },
        } as never);

        const response = await searchStockImages({
            query: 'veterinary clinic',
            limit: 8,
            orientation: 'landscape',
        });

        expect(mockedAxios.post).toHaveBeenCalledWith('/api/assets/search', {
            query: 'veterinary clinic',
            limit: 8,
            orientation: 'landscape',
        });
        expect(response.query).toBe('veterinary clinic');
    });

    it('imports a selected stock image through the backend endpoint', async () => {
        mockedAxios.post.mockResolvedValueOnce({
            data: {
                media: {
                    id: 7,
                    asset_url: '/storage/example.jpg',
                },
            },
        } as never);

        const response = await importStockImage({
            provider: 'pexels',
            image_id: '7',
            download_url: 'https://images.pexels.com/photos/7/example.jpeg',
            project_id: 'project-1',
            imported_by: 'visual_builder',
        });

        expect(mockedAxios.post).toHaveBeenCalledWith('/api/assets/import', {
            provider: 'pexels',
            image_id: '7',
            download_url: 'https://images.pexels.com/photos/7/example.jpeg',
            project_id: 'project-1',
            imported_by: 'visual_builder',
        });
        expect(response.media.id).toBe(7);
    });
});
