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
import { Wand2 } from 'lucide-react';

export interface RefineLayoutPanelProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  onSubmit: (command: string) => void;
  isProcessing?: boolean;
  /** Disable submit when there are no sections to refine */
  hasSections?: boolean;
  t?: (key: string) => string;
}

const EXAMPLE_COMMANDS = [
  'Improve this layout',
  'Make hero more modern',
  'Add CTA section',
  'Make layout minimal',
];

export function RefineLayoutPanel({
  open,
  onOpenChange,
  onSubmit,
  isProcessing = false,
  hasSections = true,
  t = (k) => k,
}: RefineLayoutPanelProps) {
  const [command, setCommand] = useState('');

  useEffect(() => {
    if (!open) {
      setCommand('');
    }
  }, [open]);

  const handleSubmit = useCallback(
    (e: React.FormEvent) => {
      e.preventDefault();
      const trimmed = command.trim();
      if (!trimmed) return;
      onSubmit(trimmed);
      setCommand('');
      onOpenChange(false);
    },
    [command, onSubmit, onOpenChange]
  );

  const canSubmit = command.trim().length > 0 && hasSections && !isProcessing;

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-md" showClose>
        <DialogHeader>
          <DialogTitle className="flex items-center gap-2">
            <Wand2 className="h-5 w-5 text-primary" />
            {t('Refine layout')}
          </DialogTitle>
          <DialogDescription>
            {t('Describe how you want to change the page. We’ll update the layout or sections.')}
          </DialogDescription>
        </DialogHeader>
        <form onSubmit={handleSubmit} className="grid gap-4">
          <div className="grid gap-2">
            <Label htmlFor="refine-command">{t('What should we change?')}</Label>
            <Input
              id="refine-command"
              type="text"
              placeholder={t('e.g. Make hero more modern')}
              value={command}
              onChange={(e) => setCommand(e.target.value)}
              disabled={isProcessing}
            />
            <p className="text-[11px] text-muted-foreground">
              {t('Examples:')}{' '}
              {EXAMPLE_COMMANDS.map((ex, i) => (
                <span key={ex}>
                  {i > 0 ? ', ' : ''}
                  &quot;{ex}&quot;
                </span>
              ))}
            </p>
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
              {isProcessing ? t('Applying…') : t('Refine')}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
