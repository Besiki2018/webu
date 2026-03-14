import { useEffect, useMemo, useState } from 'react';
import { CheckCircle2, Download, ExternalLink, Image as ImageIcon, Loader2, Search } from 'lucide-react';
import { toast } from 'sonner';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { searchStockImages, importStockImage } from './stockImageClient';
import {
    createImageImportState,
    setImageImportSearchState,
    withImageImportError,
    withImageImportResults,
} from './imageImportState';
import type {
    ImportedStockMedia,
    StockImageImportRequest,
    StockImageOrientation,
    StockImageProvider,
    StockImageResult,
} from './stockImageTypes';

interface ImageSearchModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    t: (key: string, replacements?: Record<string, string>) => string;
    projectId: string;
    initialQuery?: string;
    orientation?: StockImageOrientation | null;
    importContext?: Omit<StockImageImportRequest, 'provider' | 'image_id' | 'download_url' | 'project_id'>;
    overlayClassName?: string;
    contentClassName?: string;
    title?: string;
    description?: string;
    onImported: (media: ImportedStockMedia, result: StockImageResult) => void;
}

function providerLabel(provider: StockImageProvider): string {
    return provider === 'unsplash'
        ? 'Unsplash'
        : provider === 'pexels'
            ? 'Pexels'
            : 'Freepik';
}

function providerBadgeVariant(provider: StockImageProvider): 'default' | 'secondary' | 'outline' {
    return provider === 'unsplash'
        ? 'default'
        : provider === 'pexels'
            ? 'secondary'
            : 'outline';
}

function formatDimensions(result: StockImageResult): string {
    if (result.width > 0 && result.height > 0) {
        return `${result.width} × ${result.height}`;
    }

    return 'Unknown size';
}

function getApiErrorMessage(error: unknown, fallback: string): string {
    if (typeof error === 'object' && error !== null) {
        const candidate = error as { response?: { data?: { error?: string; message?: string } } };
        return candidate.response?.data?.error ?? candidate.response?.data?.message ?? fallback;
    }

    return fallback;
}

export function ImageSearchModal({
    open,
    onOpenChange,
    t,
    projectId,
    initialQuery = '',
    orientation = null,
    importContext,
    overlayClassName,
    contentClassName,
    title,
    description,
    onImported,
}: ImageSearchModalProps) {
    const [state, setState] = useState(() => createImageImportState(initialQuery));
    const [hasSearched, setHasSearched] = useState(false);

    useEffect(() => {
        if (!open) {
            return;
        }

        setState(createImageImportState(initialQuery));
        setHasSearched(false);
    }, [initialQuery, open]);

    const selectedResult = useMemo(
        () => state.results.find((result) => result.id === state.selectedImageId) ?? null,
        [state.results, state.selectedImageId],
    );

    const handleSearch = async () => {
        const query = state.query.trim();
        if (query === '') {
            toast.error(t('Enter a search query first'));
            return;
        }

        setState((prev) => setImageImportSearchState(prev, { searching: true, query }));
        setHasSearched(true);

        try {
            const response = await searchStockImages({
                query,
                limit: 12,
                orientation,
            });
            setState((prev) => withImageImportResults(prev, response.results, query));
        } catch (error) {
            setState((prev) => withImageImportError(prev, getApiErrorMessage(error, t('Failed to search stock images'))));
        }
    };

    const handleImport = async () => {
        if (!selectedResult) {
            toast.error(t('Select an image first'));
            return;
        }

        setState((prev) => setImageImportSearchState(prev, { importing: true }));

        try {
            const response = await importStockImage({
                provider: selectedResult.provider,
                image_id: selectedResult.id,
                download_url: selectedResult.download_url,
                project_id: projectId,
                title: selectedResult.title,
                author: selectedResult.author,
                license: selectedResult.license,
                query: state.query.trim(),
                ...importContext,
            });

            onImported(response.media, selectedResult);
            toast.success(t('Stock image imported'));
            onOpenChange(false);
        } catch (error) {
            setState((prev) => withImageImportError(prev, getApiErrorMessage(error, t('Failed to import stock image'))));
        } finally {
            setState((prev) => setImageImportSearchState(prev, { importing: false }));
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent
                overlayClassName={overlayClassName}
                className={contentClassName ?? 'max-w-6xl p-0 overflow-hidden'}
            >
                <DialogHeader className="border-b bg-muted/20 px-5 py-4 text-start">
                    <DialogTitle>{title ?? t('Stock Image Search')}</DialogTitle>
                    <DialogDescription>
                        {description ?? t('Search Unsplash, Pexels, and Freepik, then import the selected image into your project media library.')}
                    </DialogDescription>
                </DialogHeader>

                <div className="grid min-h-0 grid-cols-1 lg:grid-cols-[minmax(0,1fr)_360px]">
                    <div className="border-b bg-background lg:border-b-0 lg:border-r">
                        <div className="border-b px-5 py-4">
                            <div className="flex flex-col gap-3 md:flex-row">
                                <div className="min-w-0 flex-1">
                                    <Input
                                        value={state.query}
                                        onChange={(event) => setState((prev) => ({
                                            ...prev,
                                            query: event.target.value,
                                            error: null,
                                        }))}
                                        placeholder={t('Search for images')}
                                        className="h-10"
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                void handleSearch();
                                            }
                                        }}
                                    />
                                </div>
                                <Button type="button" onClick={() => void handleSearch()} disabled={state.isSearching} className="h-10 shrink-0">
                                    {state.isSearching ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Search className="mr-2 h-4 w-4" />}
                                    {t('Search')}
                                </Button>
                            </div>
                            <div className="mt-3 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                                <Badge variant="outline">{t('Providers')}: Unsplash / Pexels / Freepik</Badge>
                                {orientation ? <Badge variant="secondary">{t('Orientation')}: {orientation}</Badge> : null}
                            </div>
                            {state.error ? (
                                <p className="mt-3 text-sm text-destructive">{state.error}</p>
                            ) : null}
                        </div>

                        <div className="max-h-[60vh] overflow-y-auto px-5 py-5">
                            {state.results.length === 0 ? (
                                <div className="rounded-lg border border-dashed bg-muted/10 p-10 text-center text-sm text-muted-foreground">
                                    {state.isSearching
                                        ? t('Searching stock providers...')
                                        : hasSearched
                                            ? t('No stock images matched your search')
                                            : t('Search to see stock image results')}
                                </div>
                            ) : (
                                <div className="grid grid-cols-2 gap-4 xl:grid-cols-3">
                                    {state.results.map((result) => {
                                        const selected = state.selectedImageId === result.id;

                                        return (
                                            <button
                                                key={`${result.provider}-${result.id}`}
                                                type="button"
                                                onClick={() => setState((prev) => setImageImportSearchState(prev, { selectedImageId: result.id }))}
                                                className={`overflow-hidden rounded-xl border text-left transition hover:border-primary/60 hover:shadow-sm ${
                                                    selected ? 'border-primary ring-2 ring-primary/20' : 'border-border'
                                                }`}
                                            >
                                                <div className="relative aspect-[4/3] bg-muted/20">
                                                    {result.preview_url ? (
                                                        <img
                                                            src={result.preview_url}
                                                            alt={result.title}
                                                            className="h-full w-full object-cover"
                                                            loading="lazy"
                                                        />
                                                    ) : (
                                                        <div className="flex h-full items-center justify-center text-muted-foreground">
                                                            <ImageIcon className="h-6 w-6" />
                                                        </div>
                                                    )}
                                                    <div className="absolute left-2 top-2 flex items-center gap-2">
                                                        <Badge variant={providerBadgeVariant(result.provider)}>
                                                            {providerLabel(result.provider)}
                                                        </Badge>
                                                        {typeof result.score === 'number' ? (
                                                            <Badge variant="outline">{result.score.toFixed(2)}</Badge>
                                                        ) : null}
                                                    </div>
                                                    {selected ? (
                                                        <div className="absolute right-2 top-2 rounded-full bg-primary p-1 text-primary-foreground shadow">
                                                            <CheckCircle2 className="h-3.5 w-3.5" />
                                                        </div>
                                                    ) : null}
                                                </div>
                                                <div className="space-y-2 px-3 py-3">
                                                    <p className="line-clamp-2 text-sm font-medium">{result.title}</p>
                                                    <div className="flex flex-wrap items-center gap-2 text-[11px] text-muted-foreground">
                                                        <span>{formatDimensions(result)}</span>
                                                        {result.author ? <span>{result.author}</span> : null}
                                                    </div>
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="bg-muted/10">
                        <div className="border-b px-5 py-4">
                            <p className="text-sm font-semibold">{t('Import Details')}</p>
                            <p className="text-xs text-muted-foreground">
                                {t('Selected image will be downloaded to your project and added to the CMS media library.')}
                            </p>
                        </div>

                        <div className="max-h-[60vh] overflow-y-auto px-5 py-5">
                            {selectedResult ? (
                                <div className="space-y-4">
                                    <div className="overflow-hidden rounded-xl border bg-background">
                                        <img
                                            src={selectedResult.preview_url}
                                            alt={selectedResult.title}
                                            className="max-h-72 w-full object-cover"
                                            loading="lazy"
                                        />
                                    </div>

                                    <div className="rounded-xl border bg-background p-4">
                                        <div className="space-y-3 text-sm">
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="text-muted-foreground">{t('Provider')}</span>
                                                <Badge variant={providerBadgeVariant(selectedResult.provider)}>{providerLabel(selectedResult.provider)}</Badge>
                                            </div>
                                            <div className="flex items-start justify-between gap-3">
                                                <span className="text-muted-foreground">{t('Title')}</span>
                                                <span className="max-w-[220px] text-right font-medium">{selectedResult.title}</span>
                                            </div>
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="text-muted-foreground">{t('Author')}</span>
                                                <span className="text-right">{selectedResult.author || t('Unknown')}</span>
                                            </div>
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="text-muted-foreground">{t('License')}</span>
                                                <span className="text-right">{selectedResult.license}</span>
                                            </div>
                                            <div className="flex items-center justify-between gap-3">
                                                <span className="text-muted-foreground">{t('Dimensions')}</span>
                                                <span>{formatDimensions(selectedResult)}</span>
                                            </div>
                                        </div>

                                        <div className="mt-4 flex flex-wrap gap-2">
                                            <Button type="button" onClick={() => void handleImport()} disabled={state.isImporting}>
                                                {state.isImporting ? <Loader2 className="mr-2 h-4 w-4 animate-spin" /> : <Download className="mr-2 h-4 w-4" />}
                                                {t('Import to Project')}
                                            </Button>
                                            <Button
                                                type="button"
                                                variant="outline"
                                                onClick={() => window.open(selectedResult.full_url, '_blank', 'noopener,noreferrer')}
                                            >
                                                <ExternalLink className="mr-2 h-4 w-4" />
                                                {t('Open Full Image')}
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            ) : (
                                <div className="rounded-xl border border-dashed bg-background p-8 text-center text-sm text-muted-foreground">
                                    {t('Select a result to see import details')}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
