import { useState, useEffect, useCallback, useMemo, type ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Skeleton } from '@/components/ui/skeleton';
import { RefreshCw, ChevronRight, ChevronDown, FileText, Folder, FolderOpen } from 'lucide-react';
import axios from 'axios';
import { useTranslation } from '@/contexts/LanguageContext';

type ProjectionSource = 'custom' | 'cms-projection' | 'detached-projection' | null;

interface FileEntry {
    path: string;
    name: string;
    size: number;
    is_dir: boolean;  // API returns snake_case
    mod_time: string;
    source_kind?: 'workspace';
    is_editable?: boolean;
    is_generated_projection?: boolean;
    projection_role?: string | null;
    projection_source?: ProjectionSource;
}

interface VirtualFileEntry {
    path: string;
    displayName?: string | null;
    sourceLabel?: string | null;
}

export interface CodeFileSelectionMeta {
    sourceKind: 'workspace' | 'derived-preview';
    isEditable: boolean;
    isGeneratedProjection: boolean;
    projectionRole?: string | null;
    projectionSource?: ProjectionSource;
    sourceLabel?: string | null;
}

interface FileTreeProps {
    projectId: string;
    onFileSelect: (path: string, meta: CodeFileSelectionMeta) => void;
    selectedFile: string | null;
    refreshTrigger?: number;
    /** Virtual file from builder (e.g. generated Page.tsx). Always read-only. */
    virtualFilePath?: string | null;
    virtualFileName?: string | null;
    virtualFiles?: VirtualFileEntry[];
    /** When provided, show "Regenerate code from site" button to sync workspace from CMS. */
    onRegenerateFromSite?: () => void | Promise<void>;
    isRegenerating?: boolean;
}

interface TreeNodeData {
    name: string;
    path: string;
    isDir: boolean;
    children: TreeNodeData[];
    sourceKind: 'workspace' | 'derived-preview';
    isEditable?: boolean;
    isGeneratedProjection?: boolean;
    projectionRole?: string | null;
    projectionSource?: ProjectionSource;
    sourceLabel?: string | null;
}

export function FileTree({
    projectId,
    onFileSelect,
    selectedFile,
    refreshTrigger = 0,
    virtualFilePath,
    virtualFileName,
    virtualFiles,
    onRegenerateFromSite,
    isRegenerating = false,
}: FileTreeProps) {
    const { t } = useTranslation();
    const [files, setFiles] = useState<FileEntry[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [expanded, setExpanded] = useState<Set<string>>(new Set(['.', 'src']));
    const [derivedPreviewCollapsed, setDerivedPreviewCollapsed] = useState(true);

    const normalizedVirtualFiles = useMemo(() => {
        const nextFiles = new Map<string, VirtualFileEntry>();

        (virtualFiles ?? []).forEach((entry) => {
            const path = entry.path?.trim();
            if (!path) {
                return;
            }

            nextFiles.set(path, {
                path,
                displayName: entry.displayName?.trim() || null,
                sourceLabel: entry.sourceLabel?.trim() || t('Derived preview'),
            });
        });

        const fallbackPath = virtualFilePath?.trim() ?? '';
        if (fallbackPath !== '' && !nextFiles.has(fallbackPath)) {
            nextFiles.set(fallbackPath, {
                path: fallbackPath,
                displayName: virtualFileName?.trim() || null,
                sourceLabel: t('Derived preview'),
            });
        }

        return Array.from(nextFiles.values());
    }, [t, virtualFileName, virtualFilePath, virtualFiles]);

    useEffect(() => {
        if (normalizedVirtualFiles.length === 0) {
            return;
        }

        setExpanded((prev) => {
            const next = new Set(prev);

            normalizedVirtualFiles.forEach((entry) => {
                const parts = entry.path.split('/');
                for (let index = 1; index < parts.length; index += 1) {
                    next.add(parts.slice(0, index).join('/'));
                }
            });

            return next;
        });
    }, [normalizedVirtualFiles]);

    const fetchFiles = useCallback(async () => {
        setLoading(true);
        setError(null);
        try {
            const workspaceRes = await axios.get<{ success?: boolean; files?: FileEntry[] }>(`/panel/projects/${projectId}/workspace/files`);
            if (workspaceRes.data?.success === true && Array.isArray(workspaceRes.data.files)) {
                setFiles(workspaceRes.data.files);
                setLoading(false);
                return;
            }
            setError(t('Failed to load files'));
            setFiles([]);
        } finally {
            setLoading(false);
        }
    }, [projectId, t]);

    useEffect(() => {
        fetchFiles();
    }, [fetchFiles, refreshTrigger]);

    const toggleDir = (path: string) => {
        setExpanded(prev => {
            const next = new Set(prev);
            if (next.has(path)) {
                next.delete(path);
            } else {
                next.add(path);
            }
            return next;
        });
    };

    const workspaceFileCount = useMemo(
        () => files.filter((f) => !f.is_dir).length,
        [files]
    );
    const workspaceTree = useMemo(() => buildTree(
        files.map((entry) => ({
            path: entry.path,
            displayPath: entry.path,
            is_dir: entry.is_dir,
            sourceKind: 'workspace' as const,
            isEditable: entry.is_editable ?? true,
            isGeneratedProjection: entry.is_generated_projection ?? false,
            projectionRole: entry.projection_role ?? null,
            projectionSource: entry.projection_source ?? null,
            sourceLabel: t('Workspace'),
        }))
    ), [files, t]);
    const derivedTree = useMemo(() => buildTree(
        normalizedVirtualFiles.map((entry) => ({
            path: entry.path,
            displayPath: entry.displayName?.trim() || entry.path,
            is_dir: false,
            sourceKind: 'derived-preview' as const,
            isEditable: false,
            isGeneratedProjection: false,
            projectionRole: 'derived-preview',
            projectionSource: null,
            sourceLabel: entry.sourceLabel?.trim() || t('Derived preview'),
        }))
    ), [normalizedVirtualFiles, t]);
    const hasAnyFiles = workspaceTree.children.length > 0 || derivedTree.children.length > 0;

    return (
        <div className="flex h-full min-h-0 flex-col bg-muted/30">
            <div className="flex h-10 shrink-0 items-center justify-between gap-2 border-b px-3">
                <h2 className="text-sm font-semibold shrink-0">{t('Files')}</h2>
                <div className="flex items-center gap-1 shrink-0">
                    {onRegenerateFromSite ? (
                        <Button
                            variant="outline"
                            size="sm"
                            className="h-7 text-xs px-2"
                            onClick={onRegenerateFromSite}
                            disabled={loading || isRegenerating}
                        >
                            {isRegenerating ? (
                                <RefreshCw className="h-3 w-3 animate-spin" />
                            ) : (
                                t('Regenerate from site')
                            )}
                        </Button>
                    ) : null}
                    <Button
                        variant="ghost"
                        size="icon"
                        className="h-6 w-6"
                        onClick={fetchFiles}
                        disabled={loading}
                    >
                        <RefreshCw className={`h-3.5 w-3.5 ${loading ? 'animate-spin' : ''}`} />
                    </Button>
                </div>
            </div>

            <ScrollArea className="min-h-0 flex-1">
                <div className="p-2">
                    {error && !hasAnyFiles ? (
                        <p className="text-destructive text-sm text-center py-4">{error}</p>
                    ) : loading && files.length === 0 && normalizedVirtualFiles.length === 0 ? (
                        <div className="space-y-1">
                            {/* Skeleton file tree */}
                            <div className="flex items-center gap-2 py-1 px-2">
                                <Skeleton className="h-3.5 w-3.5" />
                                <Skeleton className="h-4 w-4" />
                                <Skeleton className="h-4 w-16" />
                            </div>
                            <div className="flex items-center gap-2 py-1 px-2 ps-5">
                                <Skeleton className="h-3.5 w-3.5" />
                                <Skeleton className="h-4 w-4" />
                                <Skeleton className="h-4 w-24" />
                            </div>
                            <div className="flex items-center gap-2 py-1 px-2 ps-5">
                                <Skeleton className="h-3.5 w-3.5" />
                                <Skeleton className="h-4 w-4" />
                                <Skeleton className="h-4 w-20" />
                            </div>
                            <div className="flex items-center gap-2 py-1 px-2 ps-8">
                                <Skeleton className="h-3.5 w-3.5 opacity-0" />
                                <Skeleton className="h-4 w-4" />
                                <Skeleton className="h-4 w-28" />
                            </div>
                            <div className="flex items-center gap-2 py-1 px-2 ps-8">
                                <Skeleton className="h-3.5 w-3.5 opacity-0" />
                                <Skeleton className="h-4 w-4" />
                                <Skeleton className="h-4 w-20" />
                            </div>
                            <div className="flex items-center gap-2 py-1 px-2">
                                <Skeleton className="h-3.5 w-3.5 opacity-0" />
                                <Skeleton className="h-4 w-4" />
                                <Skeleton className="h-4 w-32" />
                            </div>
                        </div>
                    ) : !hasAnyFiles ? (
                        <p className="text-muted-foreground text-sm text-center py-4">
                            {t('No files yet')}
                        </p>
                    ) : (
                        <>
                            {error ? (
                                <p className="px-2 pb-2 text-xs text-destructive">{error}</p>
                            ) : null}
                            {workspaceTree.children.length > 0 ? (
                                <FileGroup
                                    title={t('Workspace')}
                                    description={t('Editable real project files. AI project-edit uses only these files.')}
                                    count={workspaceFileCount}
                                >
                                    <TreeNode
                                        node={workspaceTree}
                                        depth={0}
                                        expanded={expanded}
                                        onToggle={toggleDir}
                                        onSelect={onFileSelect}
                                        selectedFile={selectedFile}
                                    />
                                </FileGroup>
                            ) : null}
                            {derivedTree.children.length > 0 ? (
                                <FileGroup
                                    title={t('Derived preview')}
                                    description={t('Read-only CMS projection for inspection. AI never sees these; edits apply to workspace files above.')}
                                    count={normalizedVirtualFiles.length}
                                    collapsible
                                    collapsed={derivedPreviewCollapsed}
                                    onCollapsedChange={setDerivedPreviewCollapsed}
                                >
                                    {!derivedPreviewCollapsed ? (
                                        <TreeNode
                                            node={derivedTree}
                                            depth={0}
                                            expanded={expanded}
                                            onToggle={toggleDir}
                                            onSelect={onFileSelect}
                                            selectedFile={selectedFile}
                                        />
                                    ) : null}
                                </FileGroup>
                            ) : null}
                        </>
                    )}
                </div>
            </ScrollArea>
        </div>
    );
}

interface TreeBuildEntry {
    path: string;
    displayPath: string;
    is_dir: boolean;
    sourceKind: 'workspace' | 'derived-preview';
    isEditable: boolean;
    isGeneratedProjection: boolean;
    projectionRole?: string | null;
    projectionSource?: ProjectionSource;
    sourceLabel?: string | null;
}

function buildTree(files: TreeBuildEntry[]): TreeNodeData {
    const root: TreeNodeData = {
        name: '.',
        path: '.',
        isDir: true,
        children: [],
        sourceKind: 'workspace',
    };

    for (const file of files) {
        const parts = file.displayPath.split('/').filter(Boolean);
        let current = root;

        for (let i = 0; i < parts.length; i++) {
            const part = parts[i];
            const path = i === parts.length - 1
                ? file.path
                : `${file.sourceKind}:${parts.slice(0, i + 1).join('/')}`;
            const isLast = i === parts.length - 1;

            let child = current.children.find(c => c.name === part);
            if (!child) {
                child = {
                    name: part,
                    path,
                    isDir: isLast ? file.is_dir : true,
                    children: [],
                    sourceKind: file.sourceKind,
                    isEditable: isLast ? file.isEditable : undefined,
                    isGeneratedProjection: isLast ? file.isGeneratedProjection : undefined,
                    projectionRole: isLast ? file.projectionRole ?? null : null,
                    projectionSource: isLast ? file.projectionSource ?? null : null,
                    sourceLabel: isLast ? file.sourceLabel ?? null : null,
                };
                current.children.push(child);
            }
            current = child;
        }
    }

    // Sort: directories first, then alphabetically
    const sortChildren = (node: TreeNodeData) => {
        node.children.sort((a, b) => {
            if (a.isDir && !b.isDir) return -1;
            if (!a.isDir && b.isDir) return 1;
            return a.name.localeCompare(b.name);
        });
        node.children.forEach(sortChildren);
    };
    sortChildren(root);

    return root;
}

interface TreeNodeProps {
    node: TreeNodeData;
    depth: number;
    expanded: Set<string>;
    onToggle: (path: string) => void;
    onSelect: (path: string, meta: CodeFileSelectionMeta) => void;
    selectedFile: string | null;
}

function TreeNode({ node, depth, expanded, onToggle, onSelect, selectedFile }: TreeNodeProps) {
    if (node.name === '.') {
        return (
            <>
                {node.children.map(child => (
                    <TreeNode
                        key={child.path}
                        node={child}
                        depth={0}
                        expanded={expanded}
                        onToggle={onToggle}
                        onSelect={onSelect}
                        selectedFile={selectedFile}
                    />
                ))}
            </>
        );
    }

    const isExpanded = expanded.has(node.path);
    const isSelected = node.path === selectedFile;
    const indent = depth * 12;

    const getFileIcon = (name: string) => {
        const ext = name.split('.').pop()?.toLowerCase();
        switch (ext) {
            case 'tsx':
            case 'ts':
                return <span className="text-blue-500">TS</span>;
            case 'jsx':
            case 'js':
                return <span className="text-yellow-500">JS</span>;
            case 'css':
                return <span className="text-purple-500">CSS</span>;
            case 'html':
                return <span className="text-orange-500">HTML</span>;
            case 'json':
                return <span className="text-green-500">{ }</span>;
            case 'md':
                return <span className="text-gray-500">MD</span>;
            default:
                return <FileText className="h-4 w-4 text-muted-foreground" />;
        }
    };
    const badges = !node.isDir ? (() => {
        if (node.sourceKind === 'derived-preview') {
            return [{ label: 'Read-only', variant: 'secondary' as const }];
        }

        if (node.projectionSource === 'detached-projection') {
            return [{ label: 'Custom override', variant: 'outline' as const }];
        }

        if (node.isGeneratedProjection) {
            return [{ label: 'CMS projection', variant: 'outline' as const }];
        }

        return [];
    })() : [];

    return (
        <div>
            <div
                onClick={() => {
                    if (node.isDir) {
                        onToggle(node.path);
                    } else {
                        onSelect(node.path, {
                            sourceKind: node.sourceKind,
                            isEditable: node.isEditable ?? (node.sourceKind === 'workspace'),
                            isGeneratedProjection: node.isGeneratedProjection ?? false,
                            projectionRole: node.projectionRole ?? null,
                            projectionSource: node.projectionSource ?? null,
                            sourceLabel: node.sourceLabel ?? null,
                        });
                    }
                }}
                className={`flex items-center gap-1.5 py-1 px-2 rounded cursor-pointer text-sm
                    ${isSelected ? 'bg-primary/10 text-primary' : 'text-foreground hover:bg-muted'}`}
                style={{ paddingLeft: `${indent + 8}px` }}
            >
                {node.isDir ? (
                    <>
                        {isExpanded ? (
                            <ChevronDown className="h-3.5 w-3.5 text-muted-foreground" />
                        ) : (
                            <ChevronRight className="h-3.5 w-3.5 text-muted-foreground" />
                        )}
                        {isExpanded ? (
                            <FolderOpen className="h-4 w-4 text-primary" />
                        ) : (
                            <Folder className="h-4 w-4 text-primary" />
                        )}
                    </>
                ) : (
                    <>
                        <span className="w-3.5" />
                        <span className="w-4 h-4 text-xs font-mono flex items-center justify-center">
                            {getFileIcon(node.name)}
                        </span>
                    </>
                )}
                <span className="min-w-0 flex-1 truncate">{node.name}</span>
                {badges.map((badge) => (
                    <Badge key={badge.label} variant={badge.variant} className="h-5 shrink-0 px-1.5 text-[10px]">
                        {badge.label}
                    </Badge>
                ))}
            </div>

            {node.isDir && isExpanded && (
                <>
                    {node.children.map(child => (
                        <TreeNode
                            key={child.path}
                            node={child}
                            depth={depth + 1}
                            expanded={expanded}
                            onToggle={onToggle}
                            onSelect={onSelect}
                            selectedFile={selectedFile}
                        />
                    ))}
                </>
            )}
        </div>
    );
}

interface FileGroupProps {
    title: string;
    description: string;
    count: number;
    children: ReactNode;
    collapsible?: boolean;
    collapsed?: boolean;
    onCollapsedChange?: (collapsed: boolean) => void;
}

function FileGroup({ title, description, count, children, collapsible = false, collapsed = false, onCollapsedChange }: FileGroupProps) {
    return (
        <div className="mb-3 last:mb-0">
            <div className="mb-2 px-2">
                <div className="flex items-center gap-2">
                    {collapsible ? (
                        <button
                            type="button"
                            onClick={() => onCollapsedChange?.(!collapsed)}
                            className="flex items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-muted-foreground hover:text-foreground transition-colors"
                        >
                            {collapsed ? (
                                <ChevronRight className="h-3.5 w-3.5" />
                            ) : (
                                <ChevronDown className="h-3.5 w-3.5" />
                            )}
                            {title}
                        </button>
                    ) : (
                        <p className="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{title}</p>
                    )}
                    <Badge variant="outline" className="h-5 text-[10px]">
                        {count}
                    </Badge>
                </div>
                <p className="mt-1 text-[11px] leading-4 text-muted-foreground">{description}</p>
            </div>
            {children}
        </div>
    );
}
