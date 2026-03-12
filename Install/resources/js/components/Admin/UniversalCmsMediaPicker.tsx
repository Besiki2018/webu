import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useTranslation } from '@/contexts/LanguageContext';
import { ImageIcon, Loader2, Trash2 } from 'lucide-react';
import axios from 'axios';

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

interface UniversalCmsMediaPickerProps {
    websiteId: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSelect: (path: string, url: string) => void;
    uploadRoute: string;
    listRoute: string;
    destroyRoute?: string;
}

export function UniversalCmsMediaPicker({
    websiteId,
    open,
    onOpenChange,
    onSelect,
    uploadRoute,
    listRoute,
    destroyRoute,
}: UniversalCmsMediaPickerProps) {
    const { t } = useTranslation();
    const [files, setFiles] = useState<MediaFile[]>([]);
    const [loading, setLoading] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [deletingPath, setDeletingPath] = useState<string | null>(null);
    const headers = { 'X-XSRF-TOKEN': getCsrfToken() };

    const loadFiles = () => {
        if (!websiteId || !listRoute) return;
        setLoading(true);
        axios.get(listRoute)
            .then((res) => setFiles(res.data?.files ?? []))
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        if (!open || !websiteId) return;
        loadFiles();
    }, [open, websiteId, listRoute]);

    const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0];
        if (!file) return;
        setUploading(true);
        const form = new FormData();
        form.append('file', file);
        axios.post(uploadRoute, form, { headers })
            .then((res) => {
                const path = res.data?.path;
                const url = res.data?.url;
                if (path) setFiles((prev) => [{ path, url: url ?? path, name: file.name, size: file.size }, ...prev]);
            })
            .finally(() => setUploading(false));
    };

    const handleDelete = (e: React.MouseEvent, path: string) => {
        e.stopPropagation();
        if (!destroyRoute) return;
        setDeletingPath(path);
        axios.delete(`${destroyRoute}?path=${encodeURIComponent(path)}`, { headers })
            .then(() => setFiles((prev) => prev.filter((f) => f.path !== path)))
            .finally(() => setDeletingPath(null));
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl max-h-[80vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{t('Select image')}</DialogTitle>
                    <DialogDescription>{t('Upload or choose an image from media library.')}</DialogDescription>
                </DialogHeader>
                <div className="space-y-4">
                    <div className="flex items-center gap-2">
                        <label className="cursor-pointer">
                            <input
                                type="file"
                                accept="image/*"
                                className="hidden"
                                onChange={handleFileChange}
                                disabled={uploading}
                            />
                            <Button type="button" variant="outline" size="sm" asChild>
                                <span>{uploading ? t('Uploading...') : t('Upload image')}</span>
                            </Button>
                        </label>
                    </div>
                    {loading ? (
                        <div className="flex items-center justify-center py-12">
                            <Loader2 className="h-8 w-8 animate-spin text-muted-foreground" />
                        </div>
                    ) : (
                        <div className="grid grid-cols-3 sm:grid-cols-4 gap-3">
                            {files.map((f) => (
                                <div
                                    key={f.path}
                                    className="relative group aspect-square rounded-lg border bg-muted/50 overflow-hidden"
                                >
                                    <button
                                        type="button"
                                        className="w-full h-full flex items-center justify-center hover:ring-2 ring-primary"
                                        onClick={() => {
                                            onSelect(f.path, f.url);
                                            onOpenChange(false);
                                        }}
                                    >
                                        <img
                                            src={f.url}
                                            alt={f.name}
                                            className="w-full h-full object-cover"
                                            onError={(e) => {
                                                (e.target as HTMLImageElement).style.display = 'none';
                                            }}
                                        />
                                        {!f.url.match(/\.(jpg|jpeg|png|gif|webp)$/i) && (
                                            <ImageIcon className="h-8 w-8 text-muted-foreground" />
                                        )}
                                    </button>
                                    {destroyRoute && (
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="icon"
                                            className="absolute top-1 right-1 h-7 w-7 opacity-0 group-hover:opacity-100 transition-opacity"
                                            onClick={(e) => handleDelete(e, f.path)}
                                            disabled={deletingPath === f.path}
                                            title={t('Delete image')}
                                        >
                                            <Trash2 className="h-3.5 w-3.5" />
                                        </Button>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                    {!loading && files.length === 0 && (
                        <p className="text-sm text-muted-foreground text-center py-6">{t('No images. Upload one above.')}</p>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}
