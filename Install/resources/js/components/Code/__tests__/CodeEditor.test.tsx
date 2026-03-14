import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import axios from 'axios';
import { CodeEditor } from '../CodeEditor';

vi.mock('axios');
vi.mock('@monaco-editor/react', () => ({
    default: ({ value }: { value: string }) => <div data-testid="mock-editor">{value}</div>,
}));
vi.mock('@/contexts/ThemeContext', () => ({
    useTheme: () => ({
        resolvedTheme: 'light',
    }),
}));
const translate = (value: string) => value;
vi.mock('@/contexts/LanguageContext', () => ({
    useTranslation: () => ({
        t: translate,
    }),
}));

const mockedAxios = vi.mocked(axios);

describe('CodeEditor', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('keeps derived preview files read-only without fetching workspace content', async () => {
        render(
            <CodeEditor
                projectId="project-1"
                selectedFile="derived-preview/pages/home/Page.tsx"
                selectedFileMeta={{
                    sourceKind: 'derived-preview',
                    isEditable: false,
                    isGeneratedProjection: false,
                    projectionRole: 'derived-preview',
                    projectionSource: null,
                    sourceLabel: 'Derived preview',
                }}
                virtualFiles={[
                    {
                        path: 'derived-preview/pages/home/Page.tsx',
                        content: 'export default function PreviewHome() { return null; }',
                        language: 'typescript',
                        displayName: 'preview-home.tsx',
                    },
                ]}
            />
        );

        await waitFor(() => {
            expect(screen.getByText('Read-only')).toBeInTheDocument();
            expect(screen.getByText('Derived preview files are read-only.')).toBeInTheDocument();
        });

        expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument();
        expect(mockedAxios.get).not.toHaveBeenCalled();
    });

    it('uses workspace editability metadata instead of pretending read-only files are editable', async () => {
        mockedAxios.get.mockImplementation(async (url, config) => {
            if (url === '/panel/projects/project-1/workspace/file' && config?.params?.path === 'src/App.tsx') {
                return {
                    data: {
                        success: true,
                        content: 'export default function App() { return null; }',
                    },
                } as never;
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
                            is_editable: false,
                            is_generated_projection: true,
                            projection_role: 'page',
                            projection_source: 'cms-projection',
                        }],
                    },
                } as never;
            }

            if (url === '/panel/projects/project-1/workspace/file' && config?.params?.path === '.webu/workspace-manifest.json') {
                return {
                    data: {
                        success: true,
                        content: JSON.stringify({
                            projectId: 'project-1',
                            fileOwnership: [{
                                path: 'src/App.tsx',
                                generatedBy: 'ai',
                                editState: 'ai-generated',
                                lastEditor: 'ai',
                                locked: true,
                                templateOwned: true,
                                lastOperationId: 'workspace-op-1',
                                lastOperationKind: 'update_file',
                            }],
                        }),
                    },
                } as never;
            }

            if (url === '/panel/projects/project-1/workspace/file' && config?.params?.path === '.webu/workspace-operation-log.json') {
                return {
                    data: {
                        success: true,
                        content: JSON.stringify({
                            projectId: 'project-1',
                            entries: [{
                                id: 'workspace-op-1',
                                timestamp: '2026-03-10T10:00:00Z',
                                actor: 'ai',
                                source: 'ai_workspace',
                                operation_kind: 'update_file',
                                path: 'src/App.tsx',
                                previous_path: null,
                                reason: null,
                                preview_refresh_requested: true,
                                before: { exists: true, checksum: 'a', size: 20, line_count: 1 },
                                after: { exists: true, checksum: 'b', size: 40, line_count: 1 },
                            }],
                        }),
                    },
                } as never;
            }

            throw new Error(`Unexpected axios.get call: ${String(url)}`);
        });

        render(
            <CodeEditor
                projectId="project-1"
                selectedFile="src/App.tsx"
                selectedFileMeta={{
                    sourceKind: 'workspace',
                    isEditable: false,
                    isGeneratedProjection: true,
                    projectionRole: 'page',
                    projectionSource: 'cms-projection',
                    sourceLabel: 'Workspace',
                    provenance: {
                        generatedBy: 'ai',
                        editState: 'ai-generated',
                        lastEditor: 'ai',
                        locked: true,
                        templateOwned: true,
                        dirty: false,
                        lastOperationId: 'workspace-op-1',
                        lastOperationKind: 'update_file',
                    },
                    diff: {
                        status: 'updated',
                        operationKind: 'update_file',
                        previousPath: null,
                        changedAt: '2026-03-10T10:00:00Z',
                        checksumBefore: 'a',
                        checksumAfter: 'b',
                        byteDelta: 20,
                        lineDelta: 0,
                    },
                }}
            />
        );

        await waitFor(() => {
            expect(screen.getByText('This workspace file is currently marked read-only.')).toBeInTheDocument();
        });

        expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument();
        expect(screen.getByText('AI generated')).toBeInTheDocument();
        expect(screen.getByText('updated • 2026-03-10T10:00:00Z')).toBeInTheDocument();
        expect(mockedAxios.get.mock.calls.some(([url]) => url === '/panel/projects/project-1/workspace/files')).toBe(true);
        expect(mockedAxios.get.mock.calls.some(([url, config]) => (
            url === '/panel/projects/project-1/workspace/file'
            && config?.params?.path === 'src/App.tsx'
        ))).toBe(true);
    });
});
