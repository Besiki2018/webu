import { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useTranslation } from '@/contexts/LanguageContext';
import { ArrowLeft, FileText, ChevronRight, Plus, MoreVertical, Pencil, Trash2, ImageIcon, Undo2 } from 'lucide-react';
import { route } from 'ziggy-js';
import type { PageProps } from '@/types';

interface WebsitePage {
    id: number;
    slug: string;
    title: string;
    order: number;
    sections_count: number;
}

interface Website {
    id: string;
    name: string;
    domain: string | null;
}

interface PagesIndexProps extends PageProps {
    website: Website;
    pages: WebsitePage[];
    title: string;
    undoPreviousVersion?: number | null;
}

export default function PagesIndex({ website, pages, title, undoPreviousVersion }: PagesIndexProps) {
    const { t } = useTranslation();
    const { auth, flash } = usePage<PagesIndexProps & { flash?: { success?: string; error?: string } }>().props;
    const [addOpen, setAddOpen] = useState(false);
    const [renameOpen, setRenameOpen] = useState(false);
    const [pageToRename, setPageToRename] = useState<WebsitePage | null>(null);

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);
    const [deleteConfirm, setDeleteConfirm] = useState<WebsitePage | null>(null);
    const [addForm, setAddForm] = useState({ title: '', slug: '' });
    const [renameForm, setRenameForm] = useState({ title: '', slug: '' });

    const submitAdd = (e: React.FormEvent) => {
        e.preventDefault();
        router.post(route('admin.universal-cms.pages.store', website.id), addForm, {
            onSuccess: () => {
                setAddOpen(false);
                setAddForm({ title: '', slug: '' });
            },
        });
    };

    const submitRename = (e: React.FormEvent) => {
        e.preventDefault();
        if (!pageToRename) return;
        router.put(route('admin.universal-cms.pages.update', [website.id, pageToRename.id]), renameForm, {
            onSuccess: () => {
                setRenameOpen(false);
                setPageToRename(null);
            },
        });
    };

    const openRename = (page: WebsitePage) => {
        setPageToRename(page);
        setRenameForm({ title: page.title, slug: page.slug });
        setRenameOpen(true);
    };

    const doDelete = (page: WebsitePage) => {
        router.delete(route('admin.universal-cms.pages.destroy', [website.id, page.id]), {
            onSuccess: () => setDeleteConfirm(null),
        });
    };

    const move = (index: number, delta: number) => {
        const newOrder = [...pages];
        const swap = index + delta;
        if (swap < 0 || swap >= newOrder.length) return;
        [newOrder[index], newOrder[swap]] = [newOrder[swap], newOrder[index]];
        router.post(route('admin.universal-cms.pages.reorder', website.id), {
            order: newOrder.map((p) => p.id),
        });
    };

    return (
        <AdminLayout user={auth.user} title={title}>
            <Head title={title} />
            <AdminPageHeader
                title={website.name}
                description={t('Edit page content and sections. Changes appear on the site instantly.')}
            />
            <div className="space-y-6">
                <div className="flex items-center gap-2 flex-wrap">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={route('admin.universal-cms.index')}>
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            {t('Back to websites')}
                        </Link>
                    </Button>
                    <Button size="sm" onClick={() => setAddOpen(true)}>
                        <Plus className="h-4 w-4 mr-2" />
                        {t('Add page')}
                    </Button>
                    <Button variant="outline" size="sm" asChild>
                        <Link href={route('admin.universal-cms.media.library', website.id)}>
                            <ImageIcon className="h-4 w-4 mr-2" />
                            {t('Media library')}
                        </Link>
                    </Button>
                    {undoPreviousVersion != null && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => router.post(route('admin.universal-cms.revisions.undo', String(website.id)))}
                        >
                            <Undo2 className="h-4 w-4 mr-2" />
                            {t('Undo')}
                        </Button>
                    )}
                </div>
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    {pages.map((page, index) => (
                        <Card key={page.id}>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <div className="flex items-center gap-2">
                                    <FileText className="h-5 w-5 text-muted-foreground" />
                                    <CardTitle className="text-base">{page.title}</CardTitle>
                                </div>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="ghost" size="icon" className="h-8 w-8" />
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem onClick={() => openRename(page)}>
                                            <Pencil className="h-4 w-4 mr-2" />
                                            {t('Rename')}
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            className="text-destructive"
                                            onClick={() => setDeleteConfirm(page)}
                                        >
                                            <Trash2 className="h-4 w-4 mr-2" />
                                            {t('Delete')}
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-muted-foreground">/{page.slug}</p>
                                <p className="text-sm mt-1">
                                    {t('{count} sections', { count: String(page.sections_count) })}
                                </p>
                                <div className="mt-2 flex items-center gap-1">
                                    <Button asChild variant="ghost" size="sm" className="flex-1 justify-between">
                                        <Link href={route('admin.universal-cms.page-edit', [website.id, page.id])}>
                                            {t('Edit page')}
                                            <ChevronRight className="h-4 w-4" />
                                        </Link>
                                    </Button>
                                    <div className="flex flex-col gap-0">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-6 w-6"
                                            onClick={() => move(index, -1)}
                                            disabled={index === 0}
                                        >
                                            ↑
                                        </Button>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-6 w-6"
                                            onClick={() => move(index, 1)}
                                            disabled={index === pages.length - 1}
                                        >
                                            ↓
                                        </Button>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                {pages.length === 0 && (
                    <Card>
                        <CardContent className="flex flex-col items-center justify-center py-12">
                            <FileText className="h-12 w-12 text-muted-foreground mb-4" />
                            <p className="text-muted-foreground text-center">
                                {t('No pages yet. Sync from existing site or add a page.')}
                            </p>
                            <Button className="mt-4" onClick={() => setAddOpen(true)}>
                                <Plus className="h-4 w-4 mr-2" />
                                {t('Add page')}
                            </Button>
                        </CardContent>
                    </Card>
                )}
            </div>

            <Dialog open={addOpen} onOpenChange={setAddOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Add page')}</DialogTitle>
                        <DialogDescription>{t('Create a new page with title and URL slug.')}</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitAdd} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="add-title">{t('Page title')}</Label>
                            <Input
                                id="add-title"
                                value={addForm.title}
                                onChange={(e) => setAddForm((f) => ({ ...f, title: e.target.value }))}
                                placeholder={t('e.g. About')}
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="add-slug">{t('Page address (slug)')}</Label>
                            <Input
                                id="add-slug"
                                value={addForm.slug}
                                onChange={(e) => setAddForm((f) => ({ ...f, slug: e.target.value.toLowerCase().replace(/[^a-z0-9-]/g, '-') }))}
                                placeholder="about"
                                required
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setAddOpen(false)}>
                                {t('Cancel')}
                            </Button>
                            <Button type="submit">{t('Create')}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={renameOpen} onOpenChange={(o) => !o && setPageToRename(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Rename page')}</DialogTitle>
                        <DialogDescription>{t('Update page title and URL slug.')}</DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitRename} className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="rename-title">{t('Page title')}</Label>
                            <Input
                                id="rename-title"
                                value={renameForm.title}
                                onChange={(e) => setRenameForm((f) => ({ ...f, title: e.target.value }))}
                                required
                            />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="rename-slug">{t('Page address (slug)')}</Label>
                            <Input
                                id="rename-slug"
                                value={renameForm.slug}
                                onChange={(e) => setRenameForm((f) => ({ ...f, slug: e.target.value }))}
                                required
                            />
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setRenameOpen(false)}>
                                {t('Cancel')}
                            </Button>
                            <Button type="submit">{t('Save')}</Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog open={!!deleteConfirm} onOpenChange={(o) => !o && setDeleteConfirm(null)}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{t('Delete page?')}</DialogTitle>
                        <DialogDescription>{t('This action permanently removes the page and its sections.')}</DialogDescription>
                    </DialogHeader>
                    <p className="text-muted-foreground">
                        {deleteConfirm && t('This will remove the page "{title}" and all its sections. This cannot be undone.', { title: deleteConfirm.title })}
                    </p>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setDeleteConfirm(null)}>{t('Cancel')}</Button>
                        <Button variant="destructive" onClick={() => deleteConfirm && doDelete(deleteConfirm)}>{t('Delete')}</Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    );
}
