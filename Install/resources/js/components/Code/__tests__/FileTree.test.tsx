import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor, fireEvent } from '@testing-library/react';
import axios from 'axios';
import { FileTree } from '../FileTree';

vi.mock('axios');
const translate = (value: string) => value;
vi.mock('@/contexts/LanguageContext', () => ({
    useTranslation: () => ({
        t: translate,
    }),
}));

const mockedAxios = vi.mocked(axios);

function mockWorkspaceMetadata(projectId: string) {
    mockedAxios.get.mockImplementation(async (url, config) => {
        if (url === `/panel/projects/${projectId}/workspace/files`) {
            return {
                data: {
                    success: true,
                    files: [
                        {
                            path: 'src/App.tsx',
                            name: 'App.tsx',
                            size: 120,
                            is_dir: false,
                            mod_time: '2026-03-10T00:00:00Z',
                            source_kind: 'workspace',
                            is_editable: true,
                            is_generated_projection: false,
                            projection_role: null,
                            projection_source: 'custom',
                        },
                    ],
                },
            } as never;
        }

        if (url === `/panel/projects/${projectId}/workspace/file` && config?.params?.path === '.webu/workspace-manifest.json') {
            return {
                data: {
                    success: true,
                    content: JSON.stringify({
                        projectId,
                        fileOwnership: [{
                            path: 'src/App.tsx',
                            generatedBy: 'ai',
                            editState: 'mixed',
                            lastEditor: 'user',
                            locked: false,
                            templateOwned: false,
                            lastOperationId: 'workspace-op-1',
                            lastOperationKind: 'update_file',
                        }],
                    }),
                },
            } as never;
        }

        if (url === `/panel/projects/${projectId}/workspace/file` && config?.params?.path === '.webu/workspace-operation-log.json') {
            return {
                data: {
                    success: true,
                    content: JSON.stringify({
                        projectId,
                        entries: [{
                            id: 'workspace-op-1',
                            timestamp: '2026-03-10T10:00:00Z',
                            actor: 'user',
                            source: 'code_editor',
                            operation_kind: 'update_file',
                            path: 'src/App.tsx',
                            previous_path: null,
                            reason: null,
                            preview_refresh_requested: true,
                            before: { exists: true, checksum: 'a', size: 100, line_count: 4 },
                            after: { exists: true, checksum: 'b', size: 120, line_count: 6 },
                        }],
                    }),
                },
            } as never;
        }

        throw new Error(`Unexpected axios.get call: ${String(url)}`);
    });
}

describe('FileTree', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('separates workspace files from derived preview files and preserves source metadata', async () => {
        mockWorkspaceMetadata('project-1');

        const onFileSelect = vi.fn();
        render(
            <FileTree
                projectId="project-1"
                onFileSelect={onFileSelect}
                selectedFile={null}
                virtualFiles={[
                    {
                        path: 'derived-preview/pages/home/Page.tsx',
                        displayName: 'preview-home.tsx',
                        sourceLabel: 'Derived preview',
                    },
                ]}
            />
        );

        await waitFor(() => {
            expect(screen.getByText('Workspace')).toBeInTheDocument();
            expect(screen.getByText('Derived preview')).toBeInTheDocument();
        });

        fireEvent.click(screen.getByText('src'));
        fireEvent.click(screen.getByText('App.tsx'));
        expect(onFileSelect).toHaveBeenCalledWith('src/App.tsx', expect.objectContaining({
            sourceKind: 'workspace',
            isEditable: true,
            provenance: expect.objectContaining({
                generatedBy: 'ai',
                editState: 'mixed',
                lastEditor: 'user',
            }),
            diff: expect.objectContaining({
                status: 'updated',
                operationKind: 'update_file',
            }),
        }));

        fireEvent.click(screen.getByRole('button', { name: /Derived preview/i }));
        await waitFor(() => {
            expect(screen.getByText('preview-home.tsx')).toBeInTheDocument();
        });
        fireEvent.click(screen.getByText('preview-home.tsx'));
        expect(onFileSelect).toHaveBeenCalledWith('derived-preview/pages/home/Page.tsx', expect.objectContaining({
            sourceKind: 'derived-preview',
            isEditable: false,
        }));

        expect(mockedAxios.get).toHaveBeenCalledTimes(3);
        expect(mockedAxios.get).toHaveBeenCalledWith('/panel/projects/project-1/workspace/files');
    });

    it('counts only files not directories in workspace badge', async () => {
        mockedAxios.get.mockImplementation(async (url, config) => {
            if (url === '/panel/projects/project-1/workspace/files') {
                return {
                    data: {
                        success: true,
                        files: [
                            { path: 'src', name: 'src', is_dir: true, size: 0, mod_time: '' },
                            { path: 'src/App.tsx', name: 'App.tsx', is_dir: false, size: 100, mod_time: '' },
                            { path: 'src/pages', name: 'pages', is_dir: true, size: 0, mod_time: '' },
                            { path: 'src/pages/home/Page.tsx', name: 'Page.tsx', is_dir: false, size: 200, mod_time: '' },
                        ],
                    },
                } as never;
            }

            if (url === '/panel/projects/project-1/workspace/file' && (
                config?.params?.path === '.webu/workspace-manifest.json'
                || config?.params?.path === '.webu/workspace-operation-log.json'
            )) {
                return {
                    data: {
                        success: true,
                        content: JSON.stringify({ projectId: 'project-1', fileOwnership: [], entries: [] }),
                    },
                } as never;
            }

            throw new Error(`Unexpected axios.get call: ${String(url)}`);
        });

        render(
            <FileTree
                projectId="project-1"
                onFileSelect={vi.fn()}
                selectedFile={null}
            />
        );

        await waitFor(() => {
            expect(screen.getByText('Workspace')).toBeInTheDocument();
        });
        expect(screen.getByText('2')).toBeInTheDocument();
    });

    it('collapses derived preview by default and expands on header click', async () => {
        mockedAxios.get.mockImplementation(async (url, config) => {
            if (url === '/panel/projects/project-1/workspace/files') {
                return { data: { success: true, files: [] } } as never;
            }

            if (url === '/panel/projects/project-1/workspace/file' && (
                config?.params?.path === '.webu/workspace-manifest.json'
                || config?.params?.path === '.webu/workspace-operation-log.json'
            )) {
                return {
                    data: {
                        success: true,
                        content: JSON.stringify({ projectId: 'project-1', fileOwnership: [], entries: [] }),
                    },
                } as never;
            }

            throw new Error(`Unexpected axios.get call: ${String(url)}`);
        });

        render(
            <FileTree
                projectId="project-1"
                onFileSelect={vi.fn()}
                selectedFile={null}
                virtualFiles={[
                    { path: 'derived-preview/pages/home/Page.tsx', displayName: 'Page.tsx', sourceLabel: 'Derived' },
                ]}
            />
        );

        await waitFor(() => {
            expect(screen.getByText('Derived preview')).toBeInTheDocument();
        });
        expect(screen.queryByText('Page.tsx')).not.toBeInTheDocument();

        fireEvent.click(screen.getByRole('button', { name: /Derived preview/i }));
        await waitFor(() => {
            expect(screen.getByText('Page.tsx')).toBeInTheDocument();
        });
    });

    it('keeps the file tree scroll area in a min-height-constrained flex column', async () => {
        mockedAxios.get.mockImplementation(async (url, config) => {
            if (url === '/panel/projects/project-1/workspace/files') {
                return { data: { success: true, files: [] } } as never;
            }

            if (url === '/panel/projects/project-1/workspace/file' && (
                config?.params?.path === '.webu/workspace-manifest.json'
                || config?.params?.path === '.webu/workspace-operation-log.json'
            )) {
                return {
                    data: {
                        success: true,
                        content: JSON.stringify({ projectId: 'project-1', fileOwnership: [], entries: [] }),
                    },
                } as never;
            }

            throw new Error(`Unexpected axios.get call: ${String(url)}`);
        });

        const { container } = render(
            <FileTree
                projectId="project-1"
                onFileSelect={vi.fn()}
                selectedFile={null}
            />
        );

        await waitFor(() => {
            expect(screen.getByText('Files')).toBeInTheDocument();
        });

        const scrollArea = container.querySelector('[data-slot="scroll-area"]');
        expect(scrollArea).toHaveClass('min-h-0');
        expect(scrollArea).toHaveClass('flex-1');
    });
});
