import { useCallback, useEffect, useMemo, useState, type ComponentType } from 'react';
import axios from 'axios';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { ScrollArea } from '@/components/ui/scroll-area';
import { useTranslation } from '@/contexts/LanguageContext';
import { cn } from '@/lib/utils';
import {
    Briefcase,
    CalendarDays,
    FileCode2,
    FileImage,
    FileText,
    LayoutTemplate,
    Loader2,
    NotebookText,
    Plus,
    Search,
    ShoppingBag,
    UserRound,
    Video,
    Volume2,
} from 'lucide-react';

type AssetSource = 'workspace' | 'project';

interface BuilderFileEntry {
    path: string;
    name?: string;
    is_dir?: boolean;
}

interface StoredProjectFile {
    id: number;
    filename: string;
    original_filename: string;
    mime_type: string;
    url: string;
}

export interface ChatAssetItem {
    id: string;
    label: string;
    path: string;
    source: AssetSource;
    mimeType?: string;
    reference: string;
}

interface ChatAssetSearchPopoverProps {
    projectId?: string | null;
    disabled?: boolean;
    className?: string;
    onSelect: (item: ChatAssetItem) => void;
    onSelectCategory?: (prompt: string) => void;
}

interface WebsiteCategoryItem {
    id: string;
    label: string;
    prompt: string;
    icon: ComponentType<{ className?: string }>;
}

const WEBSITE_CATEGORIES: WebsiteCategoryItem[] = [
    {
        id: 'business',
        label: 'Business website',
        prompt: 'Create a business website',
        icon: Briefcase,
    },
    {
        id: 'ecommerce',
        label: 'Online store',
        prompt: 'Create an online store',
        icon: ShoppingBag,
    },
    {
        id: 'portfolio',
        label: 'Portfolio website',
        prompt: 'Create a portfolio website',
        icon: UserRound,
    },
    {
        id: 'landing',
        label: 'Landing page',
        prompt: 'Create a landing page',
        icon: LayoutTemplate,
    },
    {
        id: 'blog',
        label: 'Blog website',
        prompt: 'Create a blog website',
        icon: NotebookText,
    },
    {
        id: 'booking',
        label: 'Booking website',
        prompt: 'Create a booking website',
        icon: CalendarDays,
    },
];

const ASSET_EXTENSIONS = new Set([
    'png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'avif', 'ico',
    'mp4', 'webm', 'mov', 'm4v',
    'mp3', 'wav', 'ogg', 'm4a', 'flac',
    'pdf', 'txt', 'md', 'json', 'csv',
    'woff', 'woff2', 'ttf', 'otf', 'eot',
]);

const ASSET_PATH_HINTS = ['assets/', 'images/', 'img/', 'media/', 'public/', 'static/', 'uploads/', 'fonts/'];

function getExtension(path: string): string {
    const clean = path.split('?')[0];
    const parts = clean.split('.');
    return parts.length > 1 ? parts[parts.length - 1].toLowerCase() : '';
}

function isLikelyAssetPath(path: string): boolean {
    const normalized = path.toLowerCase();
    const ext = getExtension(normalized);
    if (ASSET_EXTENSIONS.has(ext)) return true;
    return ASSET_PATH_HINTS.some((hint) => normalized.includes(hint));
}

function assetIcon(item: ChatAssetItem) {
    const mime = item.mimeType?.toLowerCase() ?? '';
    const ext = getExtension(item.path);
    if (mime.startsWith('image/') || ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'avif', 'ico'].includes(ext)) {
        return <FileImage className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />;
    }
    if (mime.startsWith('audio/') || ['mp3', 'wav', 'ogg', 'm4a', 'flac'].includes(ext)) {
        return <Volume2 className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />;
    }
    if (mime.startsWith('video/') || ['mp4', 'webm', 'mov', 'm4v'].includes(ext)) {
        return <Video className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />;
    }
    if (['json', 'csv', 'md', 'txt'].includes(ext)) {
        return <FileCode2 className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />;
    }
    return <FileText className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />;
}

function normalizeWorkspaceFiles(files: BuilderFileEntry[]): ChatAssetItem[] {
    return files
        .filter((file) => !file.is_dir && isLikelyAssetPath(file.path))
        .map((file) => ({
            id: `workspace:${file.path}`,
            label: file.name || file.path.split('/').pop() || file.path,
            path: file.path,
            source: 'workspace' as const,
            reference: file.path,
        }));
}

function normalizeProjectFiles(files: StoredProjectFile[]): ChatAssetItem[] {
    return files.map((file) => ({
        id: `project:${file.id}`,
        label: file.original_filename || file.filename,
        path: file.filename,
        source: 'project' as const,
        mimeType: file.mime_type,
        reference: file.url,
    }));
}

export function ChatAssetSearchPopover({
    projectId,
    disabled = false,
    className,
    onSelect,
    onSelectCategory,
}: ChatAssetSearchPopoverProps) {
    const { t } = useTranslation();
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [assets, setAssets] = useState<ChatAssetItem[]>([]);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const loadAssets = useCallback(async () => {
        if (!projectId) {
            setAssets([]);
            return;
        }

        setLoading(true);
        setError(null);

        try {
            const [workspaceResult, projectResult] = await Promise.allSettled([
                axios.get(`/builder/projects/${projectId}/files`),
                axios.get(`/project/${projectId}/files`, {
                    params: { per_page: 100, page: 1 },
                }),
            ]);

            if (workspaceResult.status === 'rejected' && projectResult.status === 'rejected') {
                setAssets([]);
                setError(t('Failed to load assets'));
                return;
            }

            let merged: ChatAssetItem[] = [];

            if (workspaceResult.status === 'fulfilled') {
                const workspaceFiles = Array.isArray(workspaceResult.value.data?.files)
                    ? workspaceResult.value.data.files as BuilderFileEntry[]
                    : [];
                merged = merged.concat(normalizeWorkspaceFiles(workspaceFiles));
            }

            if (projectResult.status === 'fulfilled') {
                const projectFiles = Array.isArray(projectResult.value.data?.files)
                    ? projectResult.value.data.files as StoredProjectFile[]
                    : [];
                merged = merged.concat(normalizeProjectFiles(projectFiles));
            }

            // Dedupe by reference string.
            const deduped = new Map<string, ChatAssetItem>();
            for (const item of merged) {
                if (!deduped.has(item.reference)) {
                    deduped.set(item.reference, item);
                }
            }

            setAssets(Array.from(deduped.values()));

            if (deduped.size === 0) {
                setError(t('No assets found'));
            }
        } catch {
            setAssets([]);
            setError(t('Failed to load assets'));
        } finally {
            setLoading(false);
        }
    }, [projectId, t]);

    useEffect(() => {
        if (!open) return;
        void loadAssets();
    }, [open, loadAssets]);

    const filteredAssets = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (needle === '') {
            return assets.slice(0, 60);
        }

        return assets
            .filter((item) =>
                item.label.toLowerCase().includes(needle)
                || item.path.toLowerCase().includes(needle)
                || item.reference.toLowerCase().includes(needle)
            )
            .slice(0, 80);
    }, [assets, query]);

    const disabledTrigger = disabled;

    return (
        <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
                <Button
                    type="button"
                    size="icon"
                    variant="ghost"
                    disabled={disabledTrigger}
                    className={cn('h-9 w-9 rounded-lg', className)}
                    aria-label={t('Website categories')}
                    title={t('Website categories')}
                >
                    <Plus className="h-4 w-4" />
                </Button>
            </PopoverTrigger>

            <PopoverContent align="start" className="w-[360px] p-0">
                <div className="border-b px-3 py-2">
                    <p className="text-sm font-medium">{t('Website categories')}</p>
                </div>

                <div className="p-3">
                    <div className="grid grid-cols-2 gap-2">
                        {WEBSITE_CATEGORIES.map((category) => {
                            const Icon = category.icon;
                            return (
                                <button
                                    key={category.id}
                                    type="button"
                                    onClick={() => {
                                        onSelectCategory?.(t(category.prompt));
                                        setOpen(false);
                                        setQuery('');
                                    }}
                                    className="flex items-center gap-2 rounded-lg border border-border/70 bg-background px-2.5 py-2 text-start text-xs transition-colors hover:bg-accent hover:text-accent-foreground"
                                >
                                    <Icon className="h-3.5 w-3.5 shrink-0 text-muted-foreground" />
                                    <span className="truncate">{t(category.label)}</span>
                                </button>
                            );
                        })}
                    </div>

                    {projectId && (
                        <>
                            <div className="my-3 border-t" />
                            <p className="text-xs font-medium text-muted-foreground">{t('Asset search')}</p>
                            <div className="relative mt-2">
                                <Search className="absolute start-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    value={query}
                                    onChange={(event) => setQuery(event.target.value)}
                                    placeholder={t('Search assets...')}
                                    className="ps-8 h-9"
                                />
                            </div>

                            <ScrollArea className="mt-2 h-[220px]">
                                {loading ? (
                                    <div className="flex items-center justify-center py-8 text-sm text-muted-foreground">
                                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                                        {t('Loading assets...')}
                                    </div>
                                ) : error ? (
                                    <div className="py-8 text-center text-sm text-muted-foreground">{error}</div>
                                ) : filteredAssets.length === 0 ? (
                                    <div className="py-8 text-center text-sm text-muted-foreground">
                                        {t('No assets found')}
                                    </div>
                                ) : (
                                    <div className="space-y-1 pe-1">
                                        {filteredAssets.map((item) => (
                                            <button
                                                key={item.id}
                                                type="button"
                                                onClick={() => {
                                                    onSelect(item);
                                                    setOpen(false);
                                                    setQuery('');
                                                }}
                                                className="flex w-full items-center gap-2 rounded-md px-2 py-2 text-start hover:bg-muted"
                                            >
                                                {assetIcon(item)}
                                                <div className="min-w-0 flex-1">
                                                    <p className="truncate text-sm">{item.label}</p>
                                                    <p className="truncate text-xs text-muted-foreground">{item.reference}</p>
                                                </div>
                                            </button>
                                        ))}
                                    </div>
                                )}
                            </ScrollArea>
                        </>
                    )}
                    {!projectId && (
                        <p className="mt-3 text-xs text-muted-foreground">
                            {t('Pick a website category to auto-fill your prompt.')}
                        </p>
                    )}
                </div>
            </PopoverContent>
        </Popover>
    );
}

export default ChatAssetSearchPopover;
