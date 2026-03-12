'use client';

import { useCallback, useEffect, useState } from 'react';
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { ImagePlus } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ProjectType } from '@/builder/projectTypes';
import { projectTypes } from '@/builder/projectTypes';

export interface DesignImportPayload {
  /** Design image: File (from upload/drop) or resolved from designUrl. Caller may pass designUrl to backend to fetch image. */
  designImage: File | null;
  /** Optional URL for design (image URL or Figma screenshot/export URL). */
  designUrl: string;
  projectType: ProjectType;
  preferredStyle: string;
}

const PROJECT_TYPE_LABELS: Record<ProjectType, string> = {
  business: 'Business',
  ecommerce: 'Ecommerce',
  saas: 'SaaS',
  portfolio: 'Portfolio',
  restaurant: 'Restaurant',
  hotel: 'Hotel',
  blog: 'Blog',
  landing: 'Landing',
  education: 'Education',
};

const ACCEPT_IMAGES = 'image/*';
const MAX_PREVIEW_SIZE = 320;

export interface DesignImportPanelProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (payload: DesignImportPayload) => void;
  isProcessing?: boolean;
  t?: (key: string) => string;
}

export function DesignImportPanel({
  open,
  onOpenChange,
  onSubmit,
  isProcessing = false,
  t = (k) => k,
}: DesignImportPanelProps) {
  const [designImage, setDesignImage] = useState<File | null>(null);
  const [designUrl, setDesignUrl] = useState('');
  const [projectType, setProjectType] = useState<ProjectType>('landing');
  const [preferredStyle, setPreferredStyle] = useState('');
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [isDragOver, setIsDragOver] = useState(false);

  const clearPreview = useCallback(() => {
    if (previewUrl) {
      URL.revokeObjectURL(previewUrl);
      setPreviewUrl(null);
    }
  }, [previewUrl]);

  useEffect(() => {
    if (!open) {
      setDesignImage(null);
      setDesignUrl('');
      setProjectType('landing');
      setPreferredStyle('');
      clearPreview();
    }
  }, [open, clearPreview]);

  useEffect(() => {
    if (!designImage) {
      clearPreview();
      return;
    }
    const url = URL.createObjectURL(designImage);
    setPreviewUrl(url);
    return () => URL.revokeObjectURL(url);
  }, [designImage, clearPreview]);

  const handleFileChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (file && file.type.startsWith('image/')) {
      setDesignImage(file);
    }
    e.target.value = '';
  }, []);

  const handleDrop = useCallback(
    (e: React.DragEvent) => {
      e.preventDefault();
      setIsDragOver(false);
      const file = e.dataTransfer.files?.[0];
      if (file && file.type.startsWith('image/')) {
        setDesignImage(file);
      }
    },
    []
  );

  const handleDragOver = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragOver(true);
  }, []);

  const handleDragLeave = useCallback((e: React.DragEvent) => {
    e.preventDefault();
    e.stopPropagation();
    setIsDragOver(false);
  }, []);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      onSubmit({
        designImage,
        designUrl: designUrl.trim(),
        projectType,
        preferredStyle: preferredStyle.trim(),
      });
    },
    [designImage, designUrl, projectType, preferredStyle, onSubmit]
  );

  const hasInput = designImage !== null || designUrl.trim().length > 0;
  const canSubmit = hasInput && !isProcessing;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md" showClose>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <ImagePlus className="h-5 w-5 text-primary" />
            {t('Import Design')}
          </DialogTitle>
          <DialogDescription>
            {t('Upload a design screenshot or paste a Figma/design URL. We’ll map it to Webu components.')}
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="grid gap-4">
          <div className="grid gap-2">
            <Label>{t('Design image')}</Label>
            <div
              className={cn(
                'rounded-lg border-2 border-dashed p-4 text-center transition-colors',
                isDragOver ? 'border-primary bg-primary/5' : 'border-muted-foreground/25 bg-muted/30',
                'min-h-[140px] flex flex-col items-center justify-center gap-2'
              )}
              onDrop={handleDrop}
              onDragOver={handleDragOver}
              onDragLeave={handleDragLeave}
            >
              {previewUrl ? (
                <div className="flex flex-col items-center gap-2">
                  <img
                    src={previewUrl}
                    alt=""
                    className="max-h-[120px] max-w-full object-contain rounded"
                    style={{ maxWidth: MAX_PREVIEW_SIZE }}
                  />
                  <div className="flex items-center gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => {
                        setDesignImage(null);
                        clearPreview();
                      }}
                      disabled={isProcessing}
                    >
                      {t('Remove')}
                    </Button>
                    <span className="text-xs text-muted-foreground truncate max-w-[180px]">
                      {designImage?.name}
                    </span>
                  </div>
                </div>
              ) : (
                <>
                  <p className="text-sm text-muted-foreground">{t('Drag & drop an image here')}</p>
                  <p className="text-xs text-muted-foreground">{t('or')}</p>
                  <label className="cursor-pointer">
                    <input
                      type="file"
                      accept={ACCEPT_IMAGES}
                      onChange={handleFileChange}
                      className="sr-only"
                      disabled={isProcessing}
                    />
                    <Button type="button" variant="secondary" size="sm" asChild>
                      <span>{t('Choose file')}</span>
                    </Button>
                  </label>
                </>
              )}
            </div>
          </div>

          <div className="grid gap-2">
            <Label htmlFor="design-import-url">{t('Design URL')} ({t('optional')})</Label>
            <Input
              id="design-import-url"
              type="url"
              placeholder={t('Paste image URL or Figma screenshot/export link')}
              value={designUrl}
              onChange={(e) => setDesignUrl(e.target.value)}
              disabled={isProcessing}
            />
            <p className="text-[11px] text-muted-foreground">
              {t('Use for Figma screenshot or hosted design image.')}
            </p>
          </div>

          <div className="grid gap-2">
            <Label htmlFor="design-import-project-type">{t('Project type')}</Label>
            <Select
              value={projectType}
              onValueChange={(v) => setProjectType(v as ProjectType)}
              disabled={isProcessing}
            >
              <SelectTrigger id="design-import-project-type">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {projectTypes.map((type) => (
                  <SelectItem key={type} value={type}>
                    {PROJECT_TYPE_LABELS[type]}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          <div className="grid gap-2">
            <Label htmlFor="design-import-style">{t('Preferred style')} ({t('optional')})</Label>
            <Input
              id="design-import-style"
              placeholder={t('e.g. modern, minimal, bold')}
              value={preferredStyle}
              onChange={(e) => setPreferredStyle(e.target.value)}
              disabled={isProcessing}
            />
          </div>

          <DialogFooter className="gap-2 sm:gap-0">
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={isProcessing}
            >
              {t('Cancel')}
            </Button>
            <Button type="submit" disabled={!canSubmit}>
              {isProcessing ? t('Processing…') : t('Import design')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
