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
import { Textarea } from '@/components/ui/textarea';
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select';
import { Sparkles } from 'lucide-react';
import type { ProjectType } from '@/builder/projectTypes';
import { projectTypes } from '@/builder/projectTypes';

export interface AIWebsitePromptPayload {
  prompt: string;
  projectType?: ProjectType;
  style?: string;
  brandName?: string;
  language: string;
}

const LANGUAGE_OPTIONS = [
  { value: 'en', label: 'English' },
  { value: 'es', label: 'Spanish' },
  { value: 'fr', label: 'French' },
  { value: 'de', label: 'German' },
  { value: 'it', label: 'Italian' },
  { value: 'pt', label: 'Portuguese' },
  { value: 'nl', label: 'Dutch' },
  { value: 'pl', label: 'Polish' },
  { value: 'ja', label: 'Japanese' },
  { value: 'zh', label: 'Chinese' },
] as const;

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

export interface AIWebsitePromptPanelProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (payload: AIWebsitePromptPayload) => void;
  isGenerating?: boolean;
  /** Optional: translation function for labels */
  t?: (key: string) => string;
}

export function AIWebsitePromptPanel({
  open,
  onOpenChange,
  onSubmit,
  isGenerating = false,
  t = (k) => k,
}: AIWebsitePromptPanelProps) {
  const [prompt, setPrompt] = useState('');
  const [projectType, setProjectType] = useState<ProjectType | ''>('');
  const [style, setStyle] = useState('');
  const [brandName, setBrandName] = useState('');
  const [language, setLanguage] = useState('en');

  useEffect(() => {
    if (!open) {
      setPrompt('');
      setProjectType('');
      setStyle('');
      setBrandName('');
      setLanguage('en');
    }
  }, [open]);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      const trimmed = prompt.trim();
      if (!trimmed) return;
      onSubmit({
        prompt: trimmed,
        ...(projectType && { projectType: projectType as ProjectType }),
        ...(style.trim() && { style: style.trim() }),
        ...(brandName.trim() && { brandName: brandName.trim() }),
        language,
      });
    },
    [prompt, projectType, style, brandName, language, onSubmit]
  );

  const canSubmit = prompt.trim().length > 0 && !isGenerating;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md" showClose>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Sparkles className="h-5 w-5 text-primary" />
            {t('Generate Website With AI')}
          </DialogTitle>
          <DialogDescription>
            {t('Describe the website you want. AI will generate a full site structure and content.')}
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="grid gap-4">
          <div className="grid gap-2">
            <Label htmlFor="ai-website-prompt">{t('Prompt')} *</Label>
            <Textarea
              id="ai-website-prompt"
              placeholder={t('e.g. Create a modern SaaS landing page for an AI marketing tool')}
              value={prompt}
              onChange={(e) => setPrompt(e.target.value)}
              rows={3}
              className="resize-none"
              disabled={isGenerating}
            />
          </div>
          <div className="grid gap-2">
            <Label htmlFor="ai-website-project-type">{t('Project type')} ({t('optional')})</Label>
            <Select
              value={projectType || undefined}
              onValueChange={(v) => setProjectType((v || '') as ProjectType | '')}
              disabled={isGenerating}
            >
              <SelectTrigger id="ai-website-project-type">
                <SelectValue placeholder={t('Auto-detect from prompt')} />
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
            <Label htmlFor="ai-website-style">{t('Style')} ({t('optional')})</Label>
            <Input
              id="ai-website-style"
              placeholder={t('e.g. modern, minimal, bold')}
              value={style}
              onChange={(e) => setStyle(e.target.value)}
              disabled={isGenerating}
            />
          </div>
          <div className="grid gap-2">
            <Label htmlFor="ai-website-brand">{t('Brand name')} ({t('optional')})</Label>
            <Input
              id="ai-website-brand"
              placeholder={t('Your company or site name')}
              value={brandName}
              onChange={(e) => setBrandName(e.target.value)}
              disabled={isGenerating}
            />
          </div>
          <div className="grid gap-2">
            <Label htmlFor="ai-website-language">{t('Language')}</Label>
            <Select value={language} onValueChange={setLanguage} disabled={isGenerating}>
              <SelectTrigger id="ai-website-language">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                {LANGUAGE_OPTIONS.map((opt) => (
                  <SelectItem key={opt.value} value={opt.value}>
                    {opt.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>
          <DialogFooter className="gap-2 sm:gap-0">
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
              disabled={isGenerating}
            >
              {t('Cancel')}
            </Button>
            <Button type="submit" disabled={!canSubmit}>
              {isGenerating ? t('Generating…') : t('Generate')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
