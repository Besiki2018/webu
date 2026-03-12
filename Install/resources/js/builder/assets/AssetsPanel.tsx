import { useEffect } from 'react';
import { Loader2, RefreshCw } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { BuilderApiEndpoints } from '@/builder/api/builderApi';
import { useAssetsStore } from './assetsStore';

interface AssetsPanelProps {
    endpoints: BuilderApiEndpoints;
}

export function AssetsPanel({ endpoints }: AssetsPanelProps) {
    const { items, loading, load } = useAssetsStore();

    useEffect(() => {
        void load(endpoints);
    }, [endpoints, load]);

    return (
        <div className="flex h-full flex-col">
            <div className="flex items-center justify-between border-b border-slate-200 px-4 py-3">
                <div>
                    <div className="text-sm font-semibold text-slate-900">Assets</div>
                    <div className="text-xs text-slate-500">Project files available to image fields and the canvas.</div>
                </div>
                <Button type="button" variant="ghost" size="sm" onClick={() => void load(endpoints)}>
                    <RefreshCw className="h-4 w-4" />
                </Button>
            </div>

            {loading ? (
                <div className="flex flex-1 items-center justify-center text-sm text-slate-500">
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                    Loading assets
                </div>
            ) : (
                <div className="grid gap-3 p-4">
                    {items.map((asset) => (
                        <div key={asset.id} className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
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
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}
