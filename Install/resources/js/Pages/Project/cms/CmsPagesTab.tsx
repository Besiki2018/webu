import { type FormEvent } from 'react';
import { Loader2, Pencil, Plus, RefreshCw, Trash2, Wand2 } from 'lucide-react';

import { useTranslation } from '@/contexts/LanguageContext';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from '@/components/ui/alert-dialog';

interface PageRevisionMeta {
    id: number;
    version: number;
    created_at?: string | null;
    published_at?: string | null;
}

interface PageSummary {
    id: number;
    title: string;
    slug: string;
    status: string;
    seo_title: string | null;
    seo_description: string | null;
    latest_revision: PageRevisionMeta | null;
    published_revision: PageRevisionMeta | null;
    created_at: string | null;
    updated_at: string | null;
}

interface StorefrontPageTemplatePreset {
    key: string;
    label: string;
    slug: string;
    aliases?: string[];
    route_pattern?: string | null;
    seo_title?: string;
    seo_description?: string;
    optional?: boolean;
    section_blueprints: Array<{ type: string; props: Record<string, unknown> }>;
}

interface CreatePageFormState {
    template_key: string;
    title: string;
    slug: string;
    seo_title: string;
    seo_description: string;
}

interface CmsPagesTabProps {
    availableStorefrontPageTemplatePresets: StorefrontPageTemplatePreset[];
    createPageForm: CreatePageFormState;
    isCreatePageDialogOpen: boolean;
    isCreatingPage: boolean;
    isDeletingPage: boolean;
    isPagesLoading: boolean;
    modalContentClassName: string;
    modalOverlayClassName: string;
    onCreatePageDialogOpenChange: (open: boolean) => void;
    onCreatePageSeoDescriptionChange: (value: string) => void;
    onCreatePageSeoTitleChange: (value: string) => void;
    onCreatePageSlugChange: (value: string) => void;
    onCreatePageSubmit: (event: FormEvent<HTMLFormElement>) => void;
    onCreatePageTemplatePresetChange: (templateKey: string) => void;
    onCreatePageTitleChange: (value: string) => void;
    onDeletePageConfirm: () => void | Promise<void>;
    onOpenPageEditor: (pageId: number, preferredMode?: 'builder' | 'text') => void;
    onPagePendingDeleteChange: (page: PageSummary | null) => void;
    onReloadPages: () => void | Promise<void>;
    pagePendingDelete: PageSummary | null;
    pages: PageSummary[];
    requiredEcommercePageSlugs: string[];
    selectedCreatePageTemplatePreset: StorefrontPageTemplatePreset | null;
    selectedPageId: number | null;
}

function formatDate(value: string | null | undefined): string {
    if (!value) {
        return '—';
    }

    const date = new Date(value);
    if (Number.isNaN(date.getTime())) {
        return '—';
    }

    return date.toLocaleString();
}

export function CmsPagesTab({
    availableStorefrontPageTemplatePresets,
    createPageForm,
    isCreatePageDialogOpen,
    isCreatingPage,
    isDeletingPage,
    isPagesLoading,
    modalContentClassName,
    modalOverlayClassName,
    onCreatePageDialogOpenChange,
    onCreatePageSeoDescriptionChange,
    onCreatePageSeoTitleChange,
    onCreatePageSlugChange,
    onCreatePageSubmit,
    onCreatePageTemplatePresetChange,
    onCreatePageTitleChange,
    onDeletePageConfirm,
    onOpenPageEditor,
    onPagePendingDeleteChange,
    onReloadPages,
    pagePendingDelete,
    pages,
    requiredEcommercePageSlugs,
    selectedCreatePageTemplatePreset,
    selectedPageId,
}: CmsPagesTabProps) {
    const { t } = useTranslation();

    return (
        <>
            <Card>
                <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <CardTitle>{t('Pages')}</CardTitle>
                        <CardDescription>{t('Manage pages like WordPress: add, edit, and delete from one list')}</CardDescription>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => onCreatePageDialogOpenChange(true)}
                        >
                            <Plus className="h-4 w-4 mr-1.5" />
                            {t('Add New Page')}
                        </Button>
                        <Button
                            variant="outline"
                            size="icon"
                            onClick={() => void onReloadPages()}
                            disabled={isPagesLoading}
                            aria-label={t('Refresh Pages')}
                        >
                            {isPagesLoading ? <Loader2 className="h-4 w-4 animate-spin" /> : <RefreshCw className="h-4 w-4" />}
                        </Button>
                    </div>
                </CardHeader>
                <CardContent>
                    {pages.length === 0 ? (
                        <div className="py-10 text-center text-sm text-muted-foreground">
                            {isPagesLoading ? t('Loading pages...') : t('No pages yet. Create your first page.')}
                        </div>
                    ) : (
                        <div className="overflow-x-auto rounded-lg border">
                            <table className="w-full min-w-[760px] text-sm">
                                <thead className="bg-muted/40 text-left">
                                    <tr>
                                        <th className="px-4 py-3 font-medium">{t('Title')}</th>
                                        <th className="px-4 py-3 font-medium">{t('Slug')}</th>
                                        <th className="px-4 py-3 font-medium">{t('Status')}</th>
                                        <th className="px-4 py-3 font-medium">{t('Updated')}</th>
                                        <th className="px-4 py-3 text-right font-medium">{t('Actions')}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {pages.map((pageItem) => (
                                        <tr key={pageItem.id} className={`border-t ${selectedPageId === pageItem.id ? 'bg-primary/5' : ''}`}>
                                            <td className="px-4 py-3">
                                                <button
                                                    type="button"
                                                    className="text-left font-medium hover:underline"
                                                    onClick={() => onOpenPageEditor(pageItem.id, 'text')}
                                                >
                                                    {pageItem.title}
                                                </button>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">/{pageItem.slug}</td>
                                            <td className="px-4 py-3">
                                                <Badge variant={pageItem.status === 'published' ? 'default' : 'secondary'}>
                                                    {pageItem.status}
                                                </Badge>
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">{formatDate(pageItem.updated_at)}</td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center justify-end gap-1">
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8"
                                                        onClick={() => onOpenPageEditor(pageItem.id, 'text')}
                                                        aria-label={t('Edit Page')}
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        type="button"
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8"
                                                        onClick={() => onOpenPageEditor(pageItem.id, 'builder')}
                                                        aria-label={t('Open Builder')}
                                                    >
                                                        <Wand2 className="h-4 w-4" />
                                                    </Button>
                                                    {(() => {
                                                        const slugNorm = (pageItem.slug ?? '').trim().toLowerCase();
                                                        const isRequired = requiredEcommercePageSlugs.some((slug) => (slug ?? '').trim().toLowerCase() === slugNorm);

                                                        return (
                                                            <Button
                                                                type="button"
                                                                variant="ghost"
                                                                size="icon"
                                                                className="h-8 w-8 text-destructive"
                                                                disabled={isRequired}
                                                                onClick={() => !isRequired && onPagePendingDeleteChange(pageItem)}
                                                                aria-label={isRequired ? t('Required page; cannot be deleted') : t('Delete Page')}
                                                                title={isRequired ? t('This page is required for ecommerce and cannot be deleted.') : undefined}
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </Button>
                                                        );
                                                    })()}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardContent>
            </Card>

            <Dialog open={isCreatePageDialogOpen} onOpenChange={onCreatePageDialogOpenChange}>
                <DialogContent overlayClassName={modalOverlayClassName} className={`${modalContentClassName} sm:max-w-xl`}>
                    <DialogHeader>
                        <DialogTitle>{t('Add New Page')}</DialogTitle>
                        <DialogDescription>{t('Create a new page and start editing with Builder or Text mode')}</DialogDescription>
                    </DialogHeader>
                    <form className="space-y-3" onSubmit={onCreatePageSubmit}>
                        <div className="space-y-2">
                            <Label htmlFor="create-page-template">{t('Page Template')}</Label>
                            <select
                                id="create-page-template"
                                data-webu-role="create-page-template-select"
                                value={createPageForm.template_key}
                                onChange={(event) => onCreatePageTemplatePresetChange(event.target.value)}
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                            >
                                <option value="">{t('Blank Page')}</option>
                                {availableStorefrontPageTemplatePresets.map((preset) => (
                                    <option key={preset.key} value={preset.key}>
                                        {t(preset.label)}{preset.optional ? ` (${t('Optional')})` : ''}
                                    </option>
                                ))}
                            </select>
                            {selectedCreatePageTemplatePreset ? (
                                <p data-webu-role="create-page-template-meta" className="text-xs text-muted-foreground">
                                    {t('Route pattern')}: {selectedCreatePageTemplatePreset.route_pattern ?? '/'}
                                    {' · '}
                                    {t('Sections')}: {selectedCreatePageTemplatePreset.section_blueprints.length}
                                </p>
                            ) : (
                                <p data-webu-role="create-page-template-meta" className="text-xs text-muted-foreground">
                                    {availableStorefrontPageTemplatePresets.length > 0
                                        ? t('Start from a blank builder page or choose a storefront template preset.')
                                        : t('Start from a blank builder page. Storefront presets appear only for ecommerce sites.')}
                                </p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Title')}</Label>
                            <Input
                                value={createPageForm.title}
                                onChange={(event) => onCreatePageTitleChange(event.target.value)}
                                placeholder="Home"
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('Slug')}</Label>
                            <Input
                                value={createPageForm.slug}
                                onChange={(event) => onCreatePageSlugChange(event.target.value)}
                                placeholder="home"
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('SEO Title')}</Label>
                            <Input
                                value={createPageForm.seo_title}
                                onChange={(event) => onCreatePageSeoTitleChange(event.target.value)}
                                placeholder={t('Optional')}
                            />
                        </div>
                        <div className="space-y-2">
                            <Label>{t('SEO Description')}</Label>
                            <Textarea
                                value={createPageForm.seo_description}
                                onChange={(event) => onCreatePageSeoDescriptionChange(event.target.value)}
                                rows={3}
                                placeholder={t('Optional')}
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => onCreatePageDialogOpenChange(false)}>
                                {t('Cancel')}
                            </Button>
                            <Button type="submit" disabled={isCreatingPage}>
                                {isCreatingPage ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Plus className="h-4 w-4 mr-2" />}
                                {t('Create Page')}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <AlertDialog
                open={pagePendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open && !isDeletingPage) {
                        onPagePendingDeleteChange(null);
                    }
                }}
            >
                <AlertDialogContent overlayClassName={modalOverlayClassName} className={modalContentClassName}>
                    <AlertDialogHeader>
                        <AlertDialogTitle>{t('Delete Page?')}</AlertDialogTitle>
                        <AlertDialogDescription>
                            {pagePendingDelete
                                ? t('Are you sure you want to delete "{{title}}"? This action cannot be undone.', { title: pagePendingDelete.title })
                                : t('Are you sure you want to delete this page?')}
                        </AlertDialogDescription>
                    </AlertDialogHeader>
                    <AlertDialogFooter>
                        <AlertDialogCancel disabled={isDeletingPage}>{t('Cancel')}</AlertDialogCancel>
                        <AlertDialogAction
                            className="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                            disabled={isDeletingPage}
                            onClick={(event) => {
                                event.preventDefault();
                                void onDeletePageConfirm();
                            }}
                        >
                            {isDeletingPage ? <Loader2 className="h-4 w-4 mr-2 animate-spin" /> : <Trash2 className="h-4 w-4 mr-2" />}
                            {t('Delete')}
                        </AlertDialogAction>
                    </AlertDialogFooter>
                </AlertDialogContent>
            </AlertDialog>
        </>
    );
}
