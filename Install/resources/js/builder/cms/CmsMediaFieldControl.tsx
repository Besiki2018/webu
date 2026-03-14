import type { ChangeEvent } from 'react';
import { Trash2 } from 'lucide-react';
import { toast } from 'sonner';

import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { ImportedStockMedia } from '@/builder/assets/stockImageTypes';

interface CmsMediaControlAsset {
    id?: number | string | null;
    asset_url: string;
    meta_json?: Record<string, unknown> | null;
}

interface CmsMediaValueChange {
    assetUrl: string;
    source: 'upload' | 'media_library' | 'stock_image' | 'manual' | 'remove';
    media?: CmsMediaControlAsset | ImportedStockMedia | null;
}

interface CmsMediaFieldControlProps {
    t: (key: string) => string;
    fieldLabel: string;
    effectiveValue: string;
    isVideoField: boolean;
    onChange: (value: string) => void;
    onMediaChange?: (change: CmsMediaValueChange) => void;
    uploadMediaFile: (file: File) => Promise<CmsMediaControlAsset | null>;
    openMediaPicker?: (options: {
        fieldLabel: string;
        mediaType: 'image' | 'video';
        currentValue: string;
        onApply?: (assetUrl: string) => void;
        onApplyMedia?: (media: CmsMediaControlAsset) => void;
    }) => void;
    onOpenStockImageSearch?: (options: {
        fieldLabel: string;
        currentValue: string;
        onApply?: (assetUrl: string) => void;
        onApplyMedia?: (media: ImportedStockMedia) => void;
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
    onMediaChange,
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

    const applyMediaValueChange = (change: CmsMediaValueChange) => {
        if (typeof onMediaChange === 'function') {
            onMediaChange(change);
            return;
        }

        onChange(change.assetUrl);
    };

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

        applyMediaValueChange({
            assetUrl: uploaded.asset_url,
            source: 'upload',
            media: uploaded,
        });
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
                    onChange={(event) => applyMediaValueChange({
                        assetUrl: event.target.value,
                        source: 'manual',
                    })}
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
                                onApply: (assetUrl) => applyMediaValueChange({
                                    assetUrl,
                                    source: 'media_library',
                                }),
                                onApplyMedia: (media) => applyMediaValueChange({
                                    assetUrl: media.asset_url,
                                    source: 'media_library',
                                    media,
                                }),
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
                                onApply: (assetUrl) => applyMediaValueChange({
                                    assetUrl,
                                    source: 'stock_image',
                                }),
                                onApplyMedia: (media) => applyMediaValueChange({
                                    assetUrl: media.asset_url,
                                    source: 'stock_image',
                                    media,
                                }),
                            });
                        }}
                    >
                        {t('Search Stock Image')}
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
                                applyMediaValueChange({
                                    assetUrl: '',
                                    source: 'remove',
                                });
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
