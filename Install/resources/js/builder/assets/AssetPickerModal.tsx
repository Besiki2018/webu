import { useEffect } from 'react';
import { Loader2, Upload } from 'lucide-react';
import { Dialog, DialogContent, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import type { BuilderApiEndpoints, BuilderAsset } from '@/builder/api/builderApi';
import { useAssetsStore } from './assetsStore';

interface AssetPickerModalProps {
    endpoints: BuilderApiEndpoints;
    open: boolean;
    onClose: () => void;
    onSelect: (asset: BuilderAsset) => void;
}

export function AssetPickerModal({ endpoints, open, onClose, onSelect }: AssetPickerModalProps) {
    const { items, loading, uploading, load, upload } = useAssetsStore();

    useEffect(() => {
        if (open) {
            void load(endpoints);
        }
    }, [endpoints, load, open]);

    return (
        <Dialog open={open} onOpenChange={(nextOpen) => ! nextOpen && onClose()}>
            <DialogContent className="max-w-4xl">
                <DialogHeader>
                    <DialogTitle>Select Asset</DialogTitle>
                </DialogHeader>

                <div className="mb-4 flex items-center justify-between gap-3">
                    <label className="inline-flex cursor-pointer items-center gap-2 rounded-full border border-slate-300 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">
                        <Upload className="h-4 w-4" />
                        Upload asset
                        <input
                            type="file"
                            className="hidden"
                            onChange={(event) => {
                                const file = event.target.files?.[0];
                                if (file) {
                                    void upload(endpoints, file);
                                }
                                event.currentTarget.value = '';
                            }}
                        />
                    </label>
                    {uploading ? (
                        <div className="inline-flex items-center gap-2 text-sm text-slate-500">
                            <Loader2 className="h-4 w-4 animate-spin" />
                            Uploading
                        </div>
                    ) : null}
                </div>

                {loading ? (
                    <div className="flex min-h-48 items-center justify-center text-slate-500">
                        <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                        Loading assets
                    </div>
                ) : (
                    <div className="grid max-h-[60vh] grid-cols-2 gap-4 overflow-y-auto md:grid-cols-4">
                        {items.map((asset) => (
                            <button
                                key={asset.id}
                                type="button"
                                onClick={() => {
                                    onSelect(asset);
                                    onClose();
                                }}
                                className="overflow-hidden rounded-2xl border border-slate-200 bg-white text-left transition hover:border-slate-400 hover:shadow-sm"
                            >
                                <div className="aspect-[4/3] bg-slate-100">
                                    {asset.is_image ? (
                                        <img src={asset.url} alt={asset.filename} className="h-full w-full object-cover" />
                                    ) : (
                                        <div className="flex h-full items-center justify-center text-xs uppercase tracking-[0.2em] text-slate-400">
                                            {asset.mime_type}
                                        </div>
                                    )}
                                </div>
                                <div className="p-3">
                                    <div className="truncate text-sm font-medium text-slate-900">
                                        {asset.original_filename ?? asset.filename}
                                    </div>
                                    <div className="text-xs text-slate-500">{asset.human_size ?? `${asset.size} bytes`}</div>
                                </div>
                            </button>
                        ))}
                    </div>
                )}

                <div className="flex justify-end">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
    );
}
