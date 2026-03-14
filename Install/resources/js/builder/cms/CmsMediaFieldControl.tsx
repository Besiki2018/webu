import type { ChangeEvent } from 'react';
import { Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface CmsMediaFieldControlProps {
    t: (key: string) => string;
    fieldLabel: string;
    effectiveValue: string;
    isVideoField: boolean;
    onChange: (value: string) => void;
    uploadMediaFile: (file: File) => Promise<{ asset_url: string } | null>;
    openMediaPicker?: (options: {
        fieldLabel: string;
        mediaType: 'image' | 'video';
        currentValue: string;
        onApply?: (assetUrl: string) => void;
    }) => void;
    onOpenStockImageSearch?: (options: {
        fieldLabel: string;
        currentValue: string;
        onApply?: (assetUrl: string) => void;
    }) => void;
    compact?: boolean;
    pathCaption?: string;
    labelClassName?: string;
}

export function CmsMediaFieldControl({
    t,
    fieldLabel,
    effectiveValue,
    isVideoField,
    onChange,
    uploadMediaFile,
    openMediaPicker,
    onOpenStockImageSearch,
    compact = false,
    pathCaption,
    labelClassName,
}: CmsMediaFieldControlProps) {
    const inputClassName = compact ? 'h-8 text-xs' : undefined;
    const previewClassName = compact ? 'max-h-44' : 'max-h-48';
    const triggerVariant = compact ? 'outline' : 'secondary';
    const triggerSize = compact ? 'sm' : 'default';
    const hasValue = effectiveValue.trim() !== '';
    const triggerLabel = isVideoField
        ? (hasValue ? t('Replace Video') : t('Upload Video'))
        : (hasValue ? t('Replace Image') : t('Upload Image'));
    const canUseMediaLibrary = typeof openMediaPicker === 'function';
    const canUseStockSearch = !isVideoField && typeof onOpenStockImageSearch === 'function';

    const openUploadPicker = (origin: HTMLElement | null) => {
        const input = origin
            ?.closest<HTMLElement>('[data-builder-media-field="true"]')
            ?.querySelector('input[data-builder-media-upload="true"]');
        if (input instanceof HTMLInputElement) {
            input.click();
        }
    };

    const handleDirectUpload = async (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        event.target.value = '';

        if (!file) {
            return;
        }

        const uploaded = await uploadMediaFile(file);
        if (!uploaded) {
            return;
        }

        onChange(uploaded.asset_url);
        toast.success(isVideoField ? t('Video uploaded') : t('Image uploaded'));
    };

    return (
        <div
            data-builder-media-field="true"
            className={compact ? 'space-y-1' : 'space-y-1.5'}
        >
            <Label className={labelClassName}>{t(fieldLabel)}</Label>
            {pathCaption ? (
                <p className="text-[10px] text-muted-foreground break-all">{pathCaption}</p>
            ) : null}
            <input
                data-builder-media-upload="true"
                type="file"
                accept={isVideoField ? 'video/*' : 'image/*'}
                className="sr-only"
                onChange={(event) => {
                    void handleDirectUpload(event);
                }}
            />
            {isVideoField ? (
                <Input
                    className={inputClassName}
                    value={effectiveValue}
                    onChange={(event) => onChange(event.target.value)}
                    placeholder="https://youtube.com/... or /storage/... video"
                />
            ) : null}
            <div className="grid gap-2 sm:grid-cols-2">
                <Button
                    type="button"
                    variant={triggerVariant}
                    size={triggerSize}
                    className={canUseMediaLibrary || canUseStockSearch ? 'w-full' : 'sm:col-span-2 w-full'}
                    onPointerDown={(event) => {
                        event.stopPropagation();
                    }}
                    onClick={(event) => {
                        event.stopPropagation();
                        openUploadPicker(event.currentTarget);
                    }}
                >
                    {triggerLabel}
                </Button>
                {canUseMediaLibrary ? (
                    <Button
                        type="button"
                        variant="outline"
                        size={triggerSize}
                        className="w-full"
                        onPointerDown={(event) => {
                            event.stopPropagation();
                        }}
                        onClick={(event) => {
                            event.stopPropagation();
                            openMediaPicker?.({
                                fieldLabel,
                                mediaType: isVideoField ? 'video' : 'image',
                                currentValue: effectiveValue,
                                onApply: (assetUrl) => onChange(assetUrl),
                            });
                        }}
                    >
                        {t('Media Library')}
                    </Button>
                ) : null}
                {canUseStockSearch ? (
                    <Button
                        type="button"
                        variant="outline"
                        size={triggerSize}
                        className={`${canUseMediaLibrary ? 'sm:col-span-2' : 'sm:col-span-1'} w-full`}
                        onPointerDown={(event) => {
                            event.stopPropagation();
                        }}
                        onClick={(event) => {
                            event.stopPropagation();
                            onOpenStockImageSearch?.({
                                fieldLabel,
                                currentValue: effectiveValue,
                                onApply: (assetUrl) => onChange(assetUrl),
                            });
                        }}
                    >
                        {t('Search Stock')}
                    </Button>
                ) : null}
            </div>
            {hasValue ? (
                <div className="relative">
                    <div
                        role="button"
                        tabIndex={0}
                        onPointerDown={(event) => {
                            event.stopPropagation();
                        }}
                        onClick={(event) => {
                            event.stopPropagation();
                            openUploadPicker(event.currentTarget);
                        }}
                        onKeyDown={(event) => {
                            if (event.key !== 'Enter' && event.key !== ' ') {
                                return;
                            }

                            event.preventDefault();
                            event.stopPropagation();
                            openUploadPicker(event.currentTarget);
                        }}
                        className={`${compact ? 'rounded-md border bg-muted/20 p-1' : 'rounded-md border bg-muted/20 p-1.5'} w-full text-left hover:border-primary/50`}
                    >
                        {isVideoField ? (
                            <video src={effectiveValue} controls className={`${previewClassName} w-full rounded object-cover`} />
                        ) : (
                            <img src={effectiveValue} alt={fieldLabel} className={`${previewClassName} w-full rounded object-cover`} loading="lazy" />
                        )}
                    </div>
                    {!isVideoField ? (
                        <Button
                            type="button"
                            size="icon"
                            variant="destructive"
                            className="absolute right-2 top-2 h-7 w-7 shadow-sm"
                            onPointerDown={(event) => {
                                event.stopPropagation();
                            }}
                            onClick={(event) => {
                                event.stopPropagation();
                                onChange('');
                            }}
                            aria-label={t('Remove image')}
                            title={t('Remove image')}
                        >
                            <Trash2 className="h-3.5 w-3.5" />
                        </Button>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}
