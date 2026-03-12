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
        mockedAxios.get.mockResolvedValueOnce({
            data: {
                success: true,
                content: 'export default function App() { return null; }',
            },
        } as never);

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
                }}
            />
        );

        await waitFor(() => {
            expect(screen.getByText('This workspace file is currently marked read-only.')).toBeInTheDocument();
        });

        expect(screen.queryByRole('button', { name: 'Save' })).not.toBeInTheDocument();
        expect(mockedAxios.get.mock.calls.length).toBeGreaterThan(0);
        expect(
            mockedAxios.get.mock.calls.every(([url]) => url === '/panel/projects/project-1/workspace/file')
        ).toBe(true);
    });
});
