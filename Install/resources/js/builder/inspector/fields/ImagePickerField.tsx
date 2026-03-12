import { useState } from 'react';
import { ImagePlus } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { BuilderApiEndpoints } from '@/builder/api/builderApi';
import { AssetPickerModal } from '@/builder/assets/AssetPickerModal';

interface ImagePickerFieldProps {
    endpoints: BuilderApiEndpoints;
    value: string;
    onChange: (value: string) => void;
}

export function ImagePickerField({ endpoints, value, onChange }: ImagePickerFieldProps) {
    const [open, setOpen] = useState(false);

    return (
        <div className="space-y-3">
            {value ? (
                <div className="overflow-hidden rounded-2xl border border-slate-200">
                    <img src={value} alt="Selected asset" className="aspect-[4/3] h-auto w-full object-cover" />
                </div>
            ) : null}
            <Button type="button" variant="outline" className="w-full" onClick={() => setOpen(true)}>
                <ImagePlus className="mr-2 h-4 w-4" />
                Choose asset
            </Button>
            <AssetPickerModal
                endpoints={endpoints}
                open={open}
                onClose={() => setOpen(false)}
                onSelect={(asset) => onChange(asset.url)}
            />
        </div>
    );
}
