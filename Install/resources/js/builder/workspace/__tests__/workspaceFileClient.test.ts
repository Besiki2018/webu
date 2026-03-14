import { describe, expect, it, vi, beforeEach } from 'vitest';
import axios from 'axios';

import { createWorkspaceFileClient, isAllowedWorkspacePath, normalizeWorkspacePath } from '@/builder/workspace/workspaceFileClient';

vi.mock('axios');

const mockedAxios = vi.mocked(axios);

describe('workspaceFileClient', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('rejects traversal-like paths during normalization', () => {
        expect(normalizeWorkspacePath('../src/App.tsx')).toBe('');
        expect(normalizeWorkspacePath('src/../App.tsx')).toBe('');
        expect(isAllowedWorkspacePath('../src/App.tsx')).toBe(false);
    });

    it('aggregates changed paths when applying patch sets', async () => {
        mockedAxios.get.mockImplementation(async (url, config) => {
            if (url === '/panel/projects/project-1/workspace/file' && config?.params?.path === 'src/App.tsx') {
                return { data: { success: true, content: 'old app' } } as never;
            }

            if (url === '/panel/projects/project-1/workspace/file' && config?.params?.path === 'src/pages/home/Page.tsx') {
                return { data: { success: false } } as never;
            }

            if (url === '/panel/projects/project-1/workspace/file' && config?.params?.path === '.webu/workspace-manifest.json') {
                return { data: { success: true, content: JSON.stringify({ projectId: 'project-1', fileOwnership: [] }) } } as never;
            }

            if (url === '/panel/projects/project-1/workspace/file' && config?.params?.path === '.webu/workspace-operation-log.json') {
                return { data: { success: true, content: JSON.stringify({ projectId: 'project-1', entries: [] }) } } as never;
            }

            if (url === '/panel/projects/project-1/workspace/files') {
                return {
                    data: {
                        success: true,
                        files: [{
                            path: 'src/App.tsx',
                            name: 'App.tsx',
                            size: 40,
                            is_dir: false,
                            mod_time: '2026-03-10T10:00:00Z',
                        }, {
                            path: 'src/pages/home/Page.tsx',
                            name: 'Page.tsx',
                            size: 80,
                            is_dir: false,
                            mod_time: '2026-03-10T10:00:00Z',
                        }],
                    },
                } as never;
            }

            throw new Error(`Unexpected axios.get call: ${String(url)}`);
        });
        mockedAxios.post.mockResolvedValue({ data: { success: true } } as never);

        const client = createWorkspaceFileClient('project-1');
        const result = await client.applyOperation({
            kind: 'apply_patch_set',
            operations: [{
                kind: 'update_file',
                path: 'src/App.tsx',
                content: 'new app',
            }, {
                kind: 'create_file',
                path: 'src/pages/home/Page.tsx',
                content: 'new page',
            }],
        });

        expect(result.changedPaths).toEqual(['src/App.tsx', 'src/pages/home/Page.tsx']);
        expect(mockedAxios.post).toHaveBeenCalledTimes(2);
    });
});
