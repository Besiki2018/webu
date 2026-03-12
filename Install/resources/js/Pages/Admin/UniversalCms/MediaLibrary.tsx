import { useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { toast } from 'sonner';
import AdminLayout from '@/Layouts/AdminLayout';
import { AdminPageHeader } from '@/components/Admin/AdminPageHeader';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { useTranslation } from '@/contexts/LanguageContext';
import { ArrowLeft, ImageIcon, Trash2, Upload } from 'lucide-react';
import axios from 'axios';
import type { PageProps } from '@/types';

function getCsrfToken(): string {
    const meta = document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement | null;
    if (meta?.content) return meta.content;
    const match = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

interface MediaFile {
    path: string;
    url: string;
    name: string;
    size: number;
}

interface Website {
    id: string;
    name: string;
    domain: string | null;
}

interface MediaLibraryProps extends PageProps {
    website: Website;
    files: MediaFile[];
    title: string;
    mediaBaseUrl?: string;
}

export default function MediaLibrary({ website, files: initialFiles, title, mediaBaseUrl = '' }: MediaLibraryProps) {
    const { t } = useTranslation();
    const { auth, flash } = usePage<MediaLibraryProps & { flash?: { success?: string; error?: string } }>().props;
    const [files, setFiles] = useState<MediaFile[]>(initialFiles);
    const [uploading, setUploading] = useState(false);
    const [deletingPath, setDeletingPath] = useState<string | null>(null);
    const headers = { 'X-XSRF-TOKEN': getCsrfToken() };

    useEffect(() => {
        if (flash?.success) toast.success(flash.success);
        if (flash?.error) toast.error(flash.error);
    }, [flash?.success, flash?.error]);

    const handleUpload = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setUploading(true);
        const form = new FormData();
        form.append('file', file);
        axios.post(route('admin.universal-cms.media.upload', website.id), form, { headers })
            .then((res) => {
                const path = res.data?.path;
                const url = res.data?.url ?? (mediaBaseUrl ? `${mediaBaseUrl}/${path}` : path);
                setFiles((prev) => [{ path, url, name: file.name, size: file.size }, ...prev]);
                toast.success(t('Image uploaded.'));
            })
            .catch(() => toast.error(t('Upload failed.')))
            .finally(() => setUploading(false));
    };

    const handleDelete = (path: string) => {
        setDeletingPath(path);
        axios.delete(`${route('admin.universal-cms.media.destroy', website.id)}?path=${encodeURIComponent(path)}`, { headers })
            .then(() => {
                setFiles((prev) => prev.filter((f) => f.path !== path));
                toast.success(t('Image deleted.'));
            })
            .catch(() => toast.error(t('Delete failed.')))
            .finally(() => setDeletingPath(null));
    };

    return (
        <AdminLayout user={auth.user} title={title}>
            <Head title={title} />
            <AdminPageHeader
                title={t('Media library')}
                description={t('Upload, delete, or select images for your site sections.')}
            />
            <div className="space-y-6">
                <div className="flex items-center gap-2">
                    <Button variant="ghost" size="sm" asChild>
                        <Link href={route('admin.universal-cms.pages', website.id)}>
                            <ArrowLeft className="h-4 w-4 mr-2" />
                            {t('Back to pages')}
                        </Link>
                    </Button>
                    <label className="cursor-pointer">
                        <input
                            type="file"
                            accept="image/*"
                            className="hidden"
                            onChange={handleUpload}
                            disabled={uploading}
                        />
                        <Button type="button" variant="outline" size="sm" asChild>
                            <span>
                                <Upload className="h-4 w-4 mr-2 inline" />
                                {uploading ? t('Uploading...') : t('Upload image')}
                            </span>
                        </Button>
                    </label>
                </div>
                <Card>
                    <CardContent className="pt-6">
                        <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-4">
                            {files.map((f) => (
                                <div key={f.path} className="relative group rounded-lg border bg-muted/30 overflow-hidden">
                                    <div className="aspect-square flex items-center justify-center bg-muted/50">
                                        <img
                                            src={f.url}
                                            alt={f.name}
                                            className="w-full h-full object-cover"
                                            onError={(e) => {
                                                (e.target as HTMLImageElement).style.display = 'none';
                                            }}
                                        />
                                        {!f.url.match(/\.(jpg|jpeg|png|gif|webp)$/i) && (
                                            <ImageIcon className="h-10 w-10 text-muted-foreground" />
                                        )}
                                    </div>
                                    <p className="text-xs truncate px-2 py-1" title={f.name}>{f.name}</p>
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="icon"
                                        className="absolute top-1 right-1 h-7 w-7 opacity-0 group-hover:opacity-100 transition-opacity"
                                        onClick={() => handleDelete(f.path)}
                                        disabled={deletingPath === f.path}
                                        title={t('Delete image')}
                                    >
                                        <Trash2 className="h-3.5 w-3.5" />
                                    </Button>
                                </div>
                            ))}
                        </div>
                        {files.length === 0 && (
                            <p className="text-center text-muted-foreground py-12">{t('No images yet. Upload one above.')}</p>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AdminLayout>
    );
}