import { useState, useEffect, useCallback, useMemo, useRef, useImperativeHandle, forwardRef } from 'react';
import Editor, { BeforeMount } from '@monaco-editor/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { Loader2, Lock, Save } from 'lucide-react';
import { useTheme } from '@/contexts/ThemeContext';
import { useTranslation } from '@/contexts/LanguageContext';
import type { CodeFileSelectionMeta } from './FileTree';
import { createWorkspaceFileClient } from '@/builder/workspace/workspaceFileClient';
import { describeWorkspaceFileProvenance } from '@/builder/workspace/workspaceFileState';

// Protected files that cannot be edited via the code editor.
// Must match the Go backend's executor.ProtectedWriteFiles list.
const PROTECTED_FILES = [
    'vite.config.ts',
    'tsconfig.json',
    'package.json',
    'package-lock.json',
    'components.json',
    'tailwind.config.ts',
    'tailwind.config.js',
    'postcss.config.js',
    'postcss.config.cjs',
    'index.html',
    'src/main.tsx',
    'src/index.css',
    'template.json',
];

export interface CodeEditorHandle {
    save: () => void;
}

interface CodeEditorProps {
    projectId: string;
    selectedFile: string | null;
    selectedFileMeta?: CodeFileSelectionMeta | null;
    onSave?: () => void | Promise<void>;
    /** When selectedFile matches this path, show virtualFileContent instead of fetching (read-only). */
    virtualFilePath?: string | null;
    virtualFileContent?: string | null;
    /** Language for the virtual file (e.g. 'typescript' for Page.tsx). */
    virtualFileLanguage?: string;
    /** Display name in header when virtual file is selected (e.g. 'Page.tsx'). */
    virtualFileDisplayName?: string;
    virtualFiles?: Array<{
        path: string;
        content: string;
        language?: string;
        displayName?: string;
    }>;
}

export const CodeEditor = forwardRef<CodeEditorHandle, CodeEditorProps>(function CodeEditor({
    projectId,
    selectedFile,
    selectedFileMeta = null,
    onSave,
    virtualFilePath,
    virtualFileContent,
    virtualFileLanguage = 'typescript',
    virtualFileDisplayName,
    virtualFiles,
}, ref) {
    const { t } = useTranslation();
    const [content, setContent] = useState('');
    const [originalContent, setOriginalContent] = useState('');
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const { resolvedTheme } = useTheme();

    const normalizedVirtualFiles = useMemo(() => {
        const nextFiles = new Map<string, {
            path: string;
            content: string;
            language: string;
            displayName?: string;
        }>();

        (virtualFiles ?? []).forEach((entry) => {
            const path = entry.path?.trim();
            if (!path) {
                return;
            }

            nextFiles.set(path, {
                path,
                content: entry.content ?? '',
                language: entry.language || 'typescript',
                displayName: entry.displayName,
            });
        });

        const fallbackPath = virtualFilePath?.trim() ?? '';
        if (fallbackPath !== '' && !nextFiles.has(fallbackPath)) {
            nextFiles.set(fallbackPath, {
                path: fallbackPath,
                content: virtualFileContent ?? '',
                language: virtualFileLanguage,
                displayName: virtualFileDisplayName ?? undefined,
            });
        }

        return nextFiles;
    }, [virtualFileContent, virtualFileDisplayName, virtualFileLanguage, virtualFilePath, virtualFiles]);

    const selectedVirtualFile = selectedFile ? normalizedVirtualFiles.get(selectedFile) ?? null : null;
    const isVirtual = selectedVirtualFile !== null;
    const effectiveContent = selectedVirtualFile?.content ?? content;
    const workspaceFileClient = useMemo(() => createWorkspaceFileClient(projectId, {
        onPreviewRefresh: onSave,
    }), [onSave, projectId]);

    const fetchFile = useCallback(async (path: string) => {
        setLoading(true);
        setError(null);
        try {
            const workspaceRes = await workspaceFileClient.readFile(path);
            setContent(workspaceRes.content);
            setOriginalContent(workspaceRes.content);
        } catch {
            setContent('');
            setOriginalContent('');
            setError(t('Failed to load file'));
        } finally {
            setLoading(false);
        }
    }, [t, workspaceFileClient]);

    useEffect(() => {
        if (selectedVirtualFile) {
            setContent(selectedVirtualFile.content);
            setOriginalContent(selectedVirtualFile.content);
            return;
        }
        if (selectedFile) {
            fetchFile(selectedFile);
        } else {
            setContent('');
            setOriginalContent('');
        }
    }, [fetchFile, selectedFile, selectedVirtualFile]);

    const handleSave = useCallback(async () => {
        if (!selectedFile || content === originalContent) return;

        setSaving(true);
        setError(null);
        try {
            await workspaceFileClient.writeFile(selectedFile, content, {
                actor: 'user',
                source: 'code_editor',
            });
            setOriginalContent(content);
        } catch {
            setError(t('Failed to save file'));
        } finally {
            setSaving(false);
        }
    }, [content, selectedFile, t, workspaceFileClient]);

    const handleSaveRef = useRef(handleSave);
    handleSaveRef.current = handleSave;
    useImperativeHandle(ref, () => ({ save: () => { void handleSaveRef.current(); } }), []);

    const isReadOnly = useMemo(() => {
        if (!selectedFile) return false;
        if (isVirtual) return true;
        if (selectedFileMeta && !selectedFileMeta.isEditable) return true;
        return PROTECTED_FILES.includes(selectedFile);
    }, [isVirtual, selectedFile, selectedFileMeta]);
    const readOnlyReason = useMemo(() => {
        if (!selectedFile || !isReadOnly) {
            return null;
        }
        if (isVirtual) {
            return t('Derived preview files are read-only.');
        }
        if (selectedFileMeta && !selectedFileMeta.isEditable) {
            return t('This workspace file is currently marked read-only.');
        }
        if (PROTECTED_FILES.includes(selectedFile)) {
            return t('This protected workspace file cannot be edited here.');
        }
        return t('This file is read-only.');
    }, [isReadOnly, isVirtual, selectedFile, selectedFileMeta, t]);
    const sourceBadge = isVirtual
        ? t('Derived preview')
        : (selectedFileMeta?.sourceKind === 'workspace' ? t('Workspace file') : t('Workspace'));
    const projectionBadge = !isVirtual && selectedFileMeta?.isGeneratedProjection
        ? t('CMS projection')
        : null;
    const overrideBadge = !isVirtual && selectedFileMeta?.projectionSource === 'detached-projection'
        ? t('Custom override')
        : null;
    const provenanceBadge = !isVirtual
        ? describeWorkspaceFileProvenance(selectedFileMeta?.provenance)
        : null;
    const diffSummary = !isVirtual && selectedFileMeta?.diff?.changedAt
        ? `${selectedFileMeta.diff.status} • ${selectedFileMeta.diff.changedAt}`
        : null;

    // Auto-save: debounced save after content change (only for editable, non-virtual files)
    const autoSaveDelayMs = 2000;
    useEffect(() => {
        if (isVirtual || isReadOnly || !selectedFile || content === originalContent) return;
        const timer = setTimeout(() => {
            void handleSaveRef.current();
        }, autoSaveDelayMs);
        return () => clearTimeout(timer);
    }, [content, selectedFile, isVirtual, isReadOnly]);

    // Keyboard shortcut for save (Cmd/Ctrl+S)
    useEffect(() => {
        const handleKeyDown = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key === 's') {
                e.preventDefault();
                if (!isReadOnly) {
                    handleSave();
                }
            }
        };
        window.addEventListener('keydown', handleKeyDown);
        return () => window.removeEventListener('keydown', handleKeyDown);
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [content, originalContent, selectedFile, isReadOnly]);

    const getLanguage = (path: string | null): string => {
        if (!path) return 'plaintext';
        const ext = path.split('.').pop()?.toLowerCase();
        switch (ext) {
            case 'tsx':
                return 'typescript';
            case 'ts':
                return 'typescript';
            case 'jsx':
                return 'javascript';
            case 'js':
                return 'javascript';
            case 'css':
                return 'css';
            case 'html':
                return 'html';
            case 'json':
                return 'json';
            case 'md':
                return 'markdown';
            default:
                return 'plaintext';
        }
    };

    const hasChanges = !isVirtual && content !== originalContent;

    const handleEditorWillMount: BeforeMount = (monaco) => {
        // Configure TypeScript compiler options for JSX/TSX support
        monaco.languages.typescript.typescriptDefaults.setCompilerOptions({
            target: monaco.languages.typescript.ScriptTarget.ES2020,
            allowNonTsExtensions: true,
            moduleResolution: monaco.languages.typescript.ModuleResolutionKind.NodeJs,
            module: monaco.languages.typescript.ModuleKind.ESNext,
            noEmit: true,
            esModuleInterop: true,
            jsx: monaco.languages.typescript.JsxEmit.ReactJSX,
            reactNamespace: 'React',
            allowJs: true,
            typeRoots: ['node_modules/@types'],
        });

        // Disable semantic validation to avoid false positives without full type definitions
        monaco.languages.typescript.typescriptDefaults.setDiagnosticsOptions({
            noSemanticValidation: true,
            noSyntaxValidation: false,
        });

        // Same for JavaScript
        monaco.languages.typescript.javascriptDefaults.setCompilerOptions({
            target: monaco.languages.typescript.ScriptTarget.ES2020,
            allowNonTsExtensions: true,
            moduleResolution: monaco.languages.typescript.ModuleResolutionKind.NodeJs,
            module: monaco.languages.typescript.ModuleKind.ESNext,
            noEmit: true,
            jsx: monaco.languages.typescript.JsxEmit.ReactJSX,
            allowJs: true,
        });

        monaco.languages.typescript.javascriptDefaults.setDiagnosticsOptions({
            noSemanticValidation: true,
            noSyntaxValidation: false,
        });
    };

    const editorTheme = resolvedTheme === 'dark' ? 'vs-dark' : 'light';

    return (
        <div className="h-full flex flex-col bg-background">
            {/* Header */}
            <div className="h-10 px-3 border-b flex items-center justify-between">
                <div className="flex items-center gap-2 min-w-0">
                    <span className="text-sm font-medium truncate">
                        {isVirtual
                            ? (selectedVirtualFile?.displayName || selectedFile || t('No file selected'))
                            : (selectedFile || t('No file selected'))}
                    </span>
                    {selectedFile ? (
                        <Badge variant={isVirtual ? 'secondary' : 'outline'} className="text-[10px] shrink-0">
                            {sourceBadge}
                        </Badge>
                    ) : null}
                    {projectionBadge ? (
                        <Badge variant="outline" className="text-[10px] shrink-0">
                            {projectionBadge}
                        </Badge>
                    ) : null}
                    {overrideBadge ? (
                        <Badge variant="outline" className="text-[10px] shrink-0">
                            {overrideBadge}
                        </Badge>
                    ) : null}
                    {provenanceBadge ? (
                        <Badge variant="secondary" className="text-[10px] shrink-0">
                            {provenanceBadge}
                        </Badge>
                    ) : null}
                    {isReadOnly && (
                        <Badge variant="secondary" className="gap-1 text-xs shrink-0">
                            <Lock className="h-3 w-3" />
                            {t('Read-only')}
                        </Badge>
                    )}
                    {!isReadOnly && hasChanges && (
                        <span className="w-2 h-2 rounded-full bg-yellow-500 shrink-0" title={t('Unsaved changes')} />
                    )}
                </div>

                <div className="flex items-center gap-2">
                    {diffSummary && !error ? <span className="text-xs text-muted-foreground">{diffSummary}</span> : null}
                    {readOnlyReason && !error ? <span className="text-xs text-muted-foreground">{readOnlyReason}</span> : null}
                    {error && <span className="text-xs text-destructive">{error}</span>}
                    {!isReadOnly && (
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={handleSave}
                            disabled={!hasChanges || saving}
                            className="gap-1"
                        >
                            {saving ? (
                                <Loader2 className="h-3.5 w-3.5 animate-spin" />
                            ) : (
                                <Save className="h-3.5 w-3.5" />
                            )}
                            {t('Save')}
                        </Button>
                    )}
                </div>
            </div>

            {/* Editor */}
            <div className="flex-1">
                {!selectedFile ? (
                    <div className="h-full flex items-center justify-center text-muted-foreground">
                        {t('Select a file to edit')}
                    </div>
                ) : isVirtual ? (
                    <Editor
                        height="100%"
                        language={selectedVirtualFile?.language ?? virtualFileLanguage}
                        value={effectiveContent}
                        theme={editorTheme}
                        beforeMount={handleEditorWillMount}
                        options={{
                            fontSize: 13,
                            fontFamily: 'JetBrains Mono, Menlo, Monaco, Consolas, monospace',
                            minimap: { enabled: false },
                            scrollBeyondLastLine: false,
                            lineNumbers: 'on',
                            tabSize: 2,
                            wordWrap: 'on',
                            automaticLayout: true,
                            padding: { top: 8 },
                            readOnly: true,
                        }}
                    />
                ) : loading ? (
                    <div className="h-full p-4 space-y-2">
                        {/* Skeleton code lines */}
                        <div className="flex gap-4">
                            <Skeleton className="h-4 w-6" />
                            <Skeleton className="h-4 w-48" />
                        </div>
                        <div className="flex gap-4">
                            <Skeleton className="h-4 w-6" />
                            <Skeleton className="h-4 w-32" />
                        </div>
                        <div className="flex gap-4">
                            <Skeleton className="h-4 w-6" />
                            <Skeleton className="h-4 w-64" />
                        </div>
                        <div className="flex gap-4">
                            <Skeleton className="h-4 w-6" />
                            <Skeleton className="h-4 w-40" />
                        </div>
                        <div className="flex gap-4">
                            <Skeleton className="h-4 w-6" />
                            <Skeleton className="h-4 w-56" />
                        </div>
                        <div className="flex gap-4">
                            <Skeleton className="h-4 w-6" />
                            <Skeleton className="h-4 w-24" />
                        </div>
                        <div className="flex gap-4">
                            <Skeleton className="h-4 w-6" />
                            <Skeleton className="h-4 w-72" />
                        </div>
                        <div className="flex gap-4">
                            <Skeleton className="h-4 w-6" />
                            <Skeleton className="h-4 w-36" />
                        </div>
                        <div className="flex gap-4">
                            <Skeleton className="h-4 w-6" />
                            <Skeleton className="h-4 w-20" />
                        </div>
                        <div className="flex gap-4">
                            <Skeleton className="h-4 w-6" />
                            <Skeleton className="h-4 w-52" />
                        </div>
                    </div>
                ) : (
                    <Editor
                        height="100%"
                        language={getLanguage(selectedFile)}
                        value={effectiveContent}
                        onChange={value => setContent(value || '')}
                        theme={editorTheme}
                        beforeMount={handleEditorWillMount}
                        options={{
                            fontSize: 13,
                            fontFamily: 'JetBrains Mono, Menlo, Monaco, Consolas, monospace',
                            minimap: { enabled: false },
                            scrollBeyondLastLine: false,
                            lineNumbers: 'on',
                            tabSize: 2,
                            wordWrap: 'on',
                            automaticLayout: true,
                            padding: { top: 8 },
                            readOnly: isReadOnly,
                        }}
                    />
                )}
            </div>
        </div>
    );
});
